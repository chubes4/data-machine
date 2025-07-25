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
use DataMachine\{DataMachine, Constants};
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

    // Centralized prompt builder
    $prompt_builder = new PromptBuilder($db_projects);

    // Register core handlers using the same hook system external plugins will use
    add_filter('dm_register_handlers', 'data_machine_register_core_handlers');


    // Create minimal handler instances for main plugin constructor
    // (These are legacy dependencies - actual handlers are created via factory)
    $output_publish_local = null; // Will be created via factory when needed
    $output_publish_remote = null; // Will be created via factory when needed
    
    // Note: $input_files will be created after processed_items_manager is available

    // API services - now use AI HTTP Client library directly
    $factcheck_api = new FactCheck();
    $finalize_api = new Finalize();

    // Process data
    $process_data = new ProcessData();



    // Handler factory will be created after processed items manager

    // Remote location service
    $remote_location_service = new RemoteLocationService($db_remote_locations);

    // Settings fields will be created after handler factory

    // Remote locations sync service
    $sync_remote_locations = new SyncRemoteLocations($db_remote_locations, $logger);

    // Remote locations form handler
    $remote_locations_form_handler = new RemoteLocationsFormHandler($db_remote_locations, $logger, $sync_remote_locations);

    // Admin page will be created after dependencies are available

    // Register settings - Handled in Data_Machine class

    // Module handler will be created after dependencies are available

    // Public API AJAX handler

    // Job worker, orchestrator, job executor, scheduler
    $job_status_manager = new JobStatusManager($db_jobs, $db_projects, $logger);
    
    // Orchestrator will be created after dependencies are available
    $job_creator = new JobCreator($db_jobs, $db_modules, $db_projects, $action_scheduler, $logger);
    $scheduler = new Scheduler($job_creator, $db_projects, $db_modules, $action_scheduler, $db_jobs, $logger);
    
    // Processed items manager
    $processed_items_manager = new ProcessedItemsManager($db_processed_items, $logger);

    // Create legacy Files handler with correct dependency
    $input_files = new InputFiles($db_modules, $db_projects, $processed_items_manager, $logger);

    // Handler factory (replace with DI version if available)
    $handler_factory = new HandlerFactory(
        $logger,
        $processed_items_manager,
        $encryption_helper,
        $oauth_twitter,
        $oauth_reddit,
        $oauth_threads,
        $oauth_facebook,
        $db_remote_locations,
        $db_modules,
        $db_projects,
        $handler_http_service
    );

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

    // AI HTTP Client with plugin context
    $ai_http_client = new AI_HTTP_Client(['plugin_context' => 'data-machine']);

    // Global container for Action Scheduler access
    global $data_machine_container;
    $data_machine_container = array(
        'handler_factory' => $handler_factory,
        'db_jobs' => $db_jobs,
        'db_processed_items' => $db_processed_items,
        'processed_items_manager' => $processed_items_manager,
        'job_status_manager' => $job_status_manager,
        'job_creator' => $job_creator,
        'action_scheduler' => $action_scheduler,
        'logger' => $logger,
        'ai_http_client' => $ai_http_client
    );

    // Import/export handler
    $import_export_handler = new ImportExport($db_projects, $db_modules);

    // AJAX handlers
    $module_ajax_handler = new ModuleConfigAjax($db_modules, $db_projects, $input_files, $db_remote_locations, $logger);
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
        $output_publish_local,
        $output_publish_remote,
        $input_files,
        $oauth_reddit,
        $oauth_twitter,
        $oauth_threads,  // Pass new handler
        $oauth_facebook, // Pass new handler
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
 * Register all core handlers using the same hook system external plugins will use.
 * This "eats our own dog food" and validates the external API.
 *
 * @param array $handlers Existing handlers array
 * @return array Updated handlers array with core handlers registered
 */
function data_machine_register_core_handlers($handlers) {
    // Input Handlers - all using same API as external plugins
    $handlers['input']['files'] = [
        'class' => 'DataMachine\Handlers\Input\Files',
        'label' => __('File Upload', 'data-machine')
    ];
    
    $handlers['input']['rss'] = [
        'class' => 'DataMachine\Handlers\Input\Rss',
        'label' => __('RSS Feed', 'data-machine')
    ];
    
    $handlers['input']['reddit'] = [
        'class' => 'DataMachine\Handlers\Input\Reddit',
        'label' => __('Reddit Posts', 'data-machine')
    ];
    
    $handlers['input']['public_rest_api'] = [
        'class' => 'DataMachine\Handlers\Input\PublicRestApi',
        'label' => __('Public REST API', 'data-machine')
    ];
    
    $handlers['input']['airdrop_rest_api'] = [
        'class' => 'DataMachine\Handlers\Input\AirdropRestApi',
        'label' => __('Airdrop REST API', 'data-machine')
    ];

    // Output Handlers - all using same API as external plugins
    $handlers['output']['publish_local'] = [
        'class' => 'DataMachine\Handlers\Output\PublishLocal',
        'label' => __('WordPress Post', 'data-machine')
    ];
    
    $handlers['output']['publish_remote'] = [
        'class' => 'DataMachine\Handlers\Output\PublishRemote',
        'label' => __('Remote WordPress', 'data-machine')
    ];
    
    $handlers['output']['twitter'] = [
        'class' => 'DataMachine\Handlers\Output\Twitter',
        'label' => __('Twitter/X', 'data-machine')
    ];
    
    $handlers['output']['facebook'] = [
        'class' => 'DataMachine\Handlers\Output\Facebook',
        'label' => __('Facebook', 'data-machine')
    ];
    
    $handlers['output']['threads'] = [
        'class' => 'DataMachine\Handlers\Output\Threads',
        'label' => __('Threads', 'data-machine')
    ];
    
    $handlers['output']['bluesky'] = [
        'class' => 'DataMachine\Handlers\Output\Bluesky',
        'label' => __('Bluesky', 'data-machine')
    ];
    
    return $handlers;
}

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