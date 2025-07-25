<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      https://wordpress.org/plugins/data-machine/
 * Description:     A powerful WordPress plugin that automatically collects data from various sources using OpenAI API, fact-checks it, and publishes the results to multiple platforms including WordPress, Twitter, Facebook, Threads, and Bluesky.
 * Version:         0.1.0
 * Author:          Chris Huber
 * Author URI:      https://chubes.net
 * Text Domain:     data-machine
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'DATA_MACHINE_VERSION', '0.1.0' );

/** Define plugin path constant */
define( 'DATA_MACHINE_PATH', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader and dependencies (includes Action Scheduler)
require_once __DIR__ . '/vendor/autoload.php';

// Load AI HTTP Client library for unified multi-provider AI integration
require_once __DIR__ . '/lib/ai-http-client/ai-http-client.php';

// PSR-4 Autoloading - no manual includes needed
use DataMachine\{DataMachine, Constants, CoreHandlerRegistry};
use DataMachine\Admin\{AdminPage, AdminMenuAssets};
use DataMachine\Admin\OAuth\{Twitter as OAuthTwitter, Reddit as OAuthReddit, Threads as OAuthThreads, Facebook as OAuthFacebook, ApiAuthPage};
use DataMachine\Admin\Projects\{Scheduler, AjaxScheduler, ImportExport, FileUploadHandler, ProjectManagementAjax};
use DataMachine\Admin\ModuleConfig\{RegisterSettings, SettingsFields, ModuleConfigHandler};
use DataMachine\Admin\ModuleConfig\Ajax\{ModuleConfigAjax, RemoteLocationsAjax};
use DataMachine\Admin\RemoteLocations\{RemoteLocationService, FormHandler as RemoteLocationsFormHandler, SyncRemoteLocations};
use DataMachine\Api\{FactCheck, Finalize};
use DataMachine\Database\{Jobs as DatabaseJobs, Modules as DatabaseModules, Projects as DatabaseProjects, ProcessedItems as DatabaseProcessedItems, RemoteLocations as DatabaseRemoteLocations};
use DataMachine\Engine\{JobCreator, ProcessingOrchestrator, JobStatusManager, ProcessData, ProcessedItemsManager};
use DataMachine\Engine\Filters\{AiResponseParser, PromptBuilder, MarkdownConverter};
use DataMachine\Handlers\{HandlerFactory, HttpService};
use DataMachine\Handlers\Input\Files as InputFiles;
use DataMachine\Helpers\{Logger, MemoryGuard, EncryptionHelper, ActionScheduler};



/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // --- Instantiate core dependencies ---
    $logger = new Logger();
    $encryption_helper = new EncryptionHelper();
    $action_scheduler = new ActionScheduler($logger);
    $memory_guard = new MemoryGuard($logger);

    // Register API/Auth admin_post handlers
    if (is_admin()) {
        new ApiAuthPage($logger);
    }

    // Hook the logger's display method to admin notices
    if (is_admin()) { // Only hook in the admin area
        add_action( 'admin_notices', array( $logger, 'display_admin_notices' ) );
    }

    // Database classes
    $db_projects = new DatabaseProjects();
    $db_modules = new DatabaseModules($db_projects, $logger);
    $db_jobs = new DatabaseJobs($db_projects, $logger);
    $db_processed_items = new DatabaseProcessedItems($logger);
    $db_remote_locations = new DatabaseRemoteLocations();

    // Handler services
    $handler_http_service = new HttpService($logger);

    // OAuth handlers
    $oauth_twitter = new OAuthTwitter($logger); // Assumes constructor handles missing credentials gracefully or fetches globally if needed
    $oauth_reddit = new OAuthReddit($logger);   // Assumes constructor handles missing credentials gracefully or fetches globally if needed

    // Get Threads app credentials from options
    $threads_client_id = get_option('threads_app_id', '');
    $threads_client_secret = get_option('threads_app_secret', '');
    $oauth_threads = new OAuthThreads($threads_client_id, $threads_client_secret, $logger);

    // Get Facebook app credentials from options
    $facebook_client_id = get_option('facebook_app_id', '');
    $facebook_client_secret = get_option('facebook_app_secret', '');
    $oauth_facebook = new OAuthFacebook($facebook_client_id, $facebook_client_secret, $logger);

    // Centralized prompt builder - now uses library for everything
    $prompt_builder = new PromptBuilder();
    $prompt_builder->register_all_sections();

    // Initialize core handler auto-registration system
    CoreHandlerRegistry::init();

    // Register core handlers explicitly via filter system (replaces filesystem scanning)
    add_filter('dm_register_handlers', function($handlers) {
        // Input handlers
        $handlers['input']['files'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Files',
            'label' => __('File Upload', 'data-machine')
        ];
        $handlers['input']['local_word_press'] = [
            'class' => 'DataMachine\\Handlers\\Input\\LocalWordPress',
            'label' => __('Local WordPress', 'data-machine')
        ];
        $handlers['input']['airdrop_rest_api'] = [
            'class' => 'DataMachine\\Handlers\\Input\\AirdropRestApi',
            'label' => __('Airdrop REST API (Helper Plugin)', 'data-machine')
        ];
        $handlers['input']['public_rest_api'] = [
            'class' => 'DataMachine\\Handlers\\Input\\PublicRestApi',
            'label' => __('Public REST API', 'data-machine')
        ];
        $handlers['input']['rss'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Rss',
            'label' => 'RSS Feed'
        ];
        $handlers['input']['reddit'] = [
            'class' => 'DataMachine\\Handlers\\Input\\Reddit',
            'label' => 'Reddit Subreddit'
        ];
        
        // Output handlers
        $handlers['output']['publish_local'] = [
            'class' => 'DataMachine\\Handlers\\Output\\PublishLocal',
            'label' => 'Publish Locally'
        ];
        $handlers['output']['publish_remote'] = [
            'class' => 'DataMachine\\Handlers\\Output\\PublishRemote',
            'label' => 'Publish Remotely'
        ];
        $handlers['output']['twitter'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Twitter',
            'label' => __('Post to Twitter', 'data-machine')
        ];
        $handlers['output']['facebook'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Facebook',
            'label' => __('Facebook', 'data-machine')
        ];
        $handlers['output']['threads'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Threads',
            'label' => __('Threads', 'data-machine')
        ];
        $handlers['output']['bluesky'] = [
            'class' => 'DataMachine\\Handlers\\Output\\Bluesky',
            'label' => __('Post to Bluesky', 'data-machine')
        ];
        
        return $handlers;
    }, 5); // Priority 5 to run before CoreHandlerRegistry (priority 10)


    // API services - now use AI HTTP Client library directly
    $factcheck_api = new FactCheck();
    $finalize_api = new Finalize();

    // Process data
    $process_data = new ProcessData();

    // Remote location service
    $remote_location_service = new RemoteLocationService($db_remote_locations);

    // Remote locations sync service
    $sync_remote_locations = new SyncRemoteLocations($db_remote_locations, $logger);

    // Remote locations form handler
    $remote_locations_form_handler = new RemoteLocationsFormHandler($db_remote_locations, $logger, $sync_remote_locations);

    // Job worker, orchestrator, job executor, scheduler
    $job_status_manager = new JobStatusManager($db_jobs, $db_projects, $logger);
    $job_creator = new JobCreator($db_jobs, $db_modules, $db_projects, $action_scheduler, $logger);
    $scheduler = new Scheduler($job_creator, $db_projects, $db_modules, $action_scheduler, $db_jobs, $logger);
    
    // Processed items manager
    $processed_items_manager = new ProcessedItemsManager($db_processed_items, $logger);

    // Handler factory using PSR-4 autoloading and service locator pattern
    $handler_factory = new HandlerFactory();

    // Settings fields
    $settings_fields = new SettingsFields($handler_factory, $remote_location_service);

    // Admin page
    $admin_page = new AdminPage(
        DATA_MACHINE_VERSION,
        $db_modules,
        $db_projects,
        $logger,
        $settings_fields,
        $handler_factory,
        $remote_locations_form_handler
    );

    // Module handler
    $module_handler = new ModuleConfigHandler(
        $db_modules,
        $handler_factory,
        $logger
    );
    $module_handler->init_hooks();

    // Orchestrator
    $orchestrator = new ProcessingOrchestrator(
        $process_data,
        $factcheck_api,
        $finalize_api,
        $handler_factory,
        $prompt_builder,
        $logger,
        $action_scheduler,
        $db_jobs,
        $job_status_manager,
        $db_modules
    );

    // AI HTTP Client with plugin context and AI type
    $ai_http_client = new AI_HTTP_Client([
        'plugin_context' => 'data-machine',
        'ai_type' => 'llm'
    ]);


    // Step configuration validator
    $step_validator = new \DataMachine\Engine\StepConfigurationValidator();

    // Global container for Action Scheduler access
    global $data_machine_container;
    $data_machine_container = array(
        'handler_factory' => $handler_factory,
        'db_jobs' => $db_jobs,
        'db_modules' => $db_modules,
        'db_projects' => $db_projects,
        'db_processed_items' => $db_processed_items,
        'processed_items_manager' => $processed_items_manager,
        'job_status_manager' => $job_status_manager,
        'job_creator' => $job_creator,
        'action_scheduler' => $action_scheduler,
        'logger' => $logger,
        'ai_http_client' => $ai_http_client,
        'step_validator' => $step_validator
    );

    // Import/export handler
    $import_export_handler = new ImportExport($db_projects, $db_modules);

    // AJAX handlers
    $module_ajax_handler = new ModuleConfigAjax($db_modules, $db_projects, $db_remote_locations, $logger);
    $dashboard_ajax_handler = new ProjectManagementAjax($db_projects, $db_modules, $db_jobs, $db_processed_items, $job_creator, $logger);
    $ajax_scheduler = new AjaxScheduler($db_projects, $db_modules, $scheduler);
    $ajax_auth = new \DataMachine\Admin\OAuth\AjaxAuth();
    $remote_locations_ajax_handler = new RemoteLocationsAjax($db_remote_locations, $logger);
    $file_upload_handler = new FileUploadHandler($db_modules, $db_projects, $logger);

    // --- Instantiate Main Plugin ---
    $register_settings = new RegisterSettings(DATA_MACHINE_VERSION);
    $plugin = new DataMachine(
        DATA_MACHINE_VERSION,
        $register_settings,
        $admin_page,
        $factcheck_api,
        $finalize_api,
        $process_data,
        $db_modules,
        $orchestrator,
        $oauth_reddit,
        $oauth_twitter,
        $oauth_threads,
        $oauth_facebook,
        $db_remote_locations,
        $logger
	);

	// --- Run the Plugin ---
	$plugin->run();

    // Register hooks for AJAX handlers
    add_action( 'wp_ajax_dm_get_module_data', array( $module_ajax_handler, 'get_module_data_ajax_handler' ) );

    add_action( 'wp_ajax_dm_run_now', array( $dashboard_ajax_handler, 'handle_run_now' ) );
    add_action( 'wp_ajax_dm_edit_schedule', array( $dashboard_ajax_handler, 'handle_edit_schedule' ) );
    add_action( 'wp_ajax_dm_get_project_schedule_data', array( $dashboard_ajax_handler, 'handle_get_project_schedule_data' ) );

    // Register async pipeline step hooks
    add_action( 'dm_input_job_event', array( $orchestrator, 'execute_input_step' ), 10, 1 );
    add_action( 'dm_process_job_event', array( $orchestrator, 'execute_process_step' ), 10, 1 );
    add_action( 'dm_factcheck_job_event', array( $orchestrator, 'execute_factcheck_step' ), 10, 1 );
    add_action( 'dm_finalize_job_event', array( $orchestrator, 'execute_finalize_step' ), 10, 1 );
    add_action( 'dm_output_job_event', array( $action_scheduler, 'handle_output_job' ), 10, 1 );

    $scheduler->init_hooks();
}


// Initialize after plugins_loaded to ensure Action Scheduler is available
add_action('plugins_loaded', 'run_data_machine', 20);


/**
 * Allows JSON file uploads.
 */
function dm_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter( 'upload_mimes', 'dm_allow_json_upload' );


// Activation and deactivation hooks (if needed)
register_activation_hook( __FILE__, 'activate_data_machine' );
register_deactivation_hook( __FILE__, array( '\\DataMachine\\DataMachine', 'deactivate' ) );

function activate_data_machine() {
	// Action Scheduler is now bundled as a library - no dependency check needed

	// Create/Update tables using static methods where available
	\DataMachine\Database\Projects::create_table();
	\DataMachine\Database\Modules::create_table();
	\DataMachine\Database\Jobs::create_table();
	\DataMachine\Database\RemoteLocations::create_table(); // Add table creation call

	// Instantiate and call instance method for Processed_Items
	$db_processed_items = new \DataMachine\Database\ProcessedItems();
	$db_processed_items->create_table();

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
}