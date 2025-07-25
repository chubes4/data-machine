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
use DataMachine\Database\{Jobs as DatabaseJobs, Modules as DatabaseModules, Projects as DatabaseProjects, ProcessedItems as DatabaseProcessedItems, RemoteLocations as DatabaseRemoteLocations};
use DataMachine\Engine\{JobCreator, ProcessingOrchestrator, JobStatusManager, ProcessedItemsManager};
use DataMachine\Engine\Filters\{AiResponseParser, PromptBuilder, MarkdownConverter};
use DataMachine\Handlers\{HandlerFactory, HttpService};
use DataMachine\Handlers\Input\Files as InputFiles;
use DataMachine\Helpers\{Logger, MemoryGuard, EncryptionHelper, ActionScheduler};
use DataMachine\Contracts\{LoggerInterface, ActionSchedulerInterface};



/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // Simple service registry using filter-based access
    $services = [];
    
    // Core services - simple instantiation
    $services['logger'] = new Logger();
    $services['encryption_helper'] = new EncryptionHelper();
    $services['action_scheduler'] = new ActionScheduler($services['logger']);
    $services['memory_guard'] = new MemoryGuard($services['logger']);
    
    // Database services
    $services['db_projects'] = new DatabaseProjects();
    $services['db_modules'] = new DatabaseModules($services['db_projects'], $services['logger']);
    $services['db_jobs'] = new DatabaseJobs($services['db_projects'], $services['logger']);
    $services['db_processed_items'] = new DatabaseProcessedItems($services['logger']);
    $services['db_remote_locations'] = new DatabaseRemoteLocations();
    
    // Handler services
    $services['http_service'] = new HttpService($services['logger']);
    
    // OAuth services
    $services['oauth_twitter'] = new OAuthTwitter($services['logger']);
    $services['oauth_reddit'] = new OAuthReddit($services['logger']);
    $threads_client_id = get_option('threads_app_id', '');
    $threads_client_secret = get_option('threads_app_secret', '');
    $services['oauth_threads'] = new OAuthThreads($threads_client_id, $threads_client_secret, $services['logger']);
    $facebook_client_id = get_option('facebook_app_id', '');
    $facebook_client_secret = get_option('facebook_app_secret', '');
    $services['oauth_facebook'] = new OAuthFacebook($facebook_client_id, $facebook_client_secret, $services['logger']);
    
    // AI and engine services
    $services['prompt_builder'] = new PromptBuilder();
    $services['prompt_builder']->register_all_sections();
    $services['ai_http_client'] = new AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
    $services['job_status_manager'] = new JobStatusManager($services['db_jobs'], $services['db_projects'], $services['logger']);
    $services['job_creator'] = new JobCreator($services['db_jobs'], $services['db_modules'], $services['db_projects'], $services['action_scheduler'], $services['logger']);
    $services['processed_items_manager'] = new ProcessedItemsManager($services['db_processed_items'], $services['logger']);
    $services['handler_factory'] = new HandlerFactory($services['logger']);
    $services['scheduler'] = new Scheduler($services['job_creator'], $services['db_projects'], $services['db_modules'], $services['action_scheduler'], $services['db_jobs'], $services['logger']);
    $services['orchestrator'] = new ProcessingOrchestrator($services['logger'], $services['action_scheduler'], $services['db_jobs']);
    
    // Get frequently used services for convenience
    $logger = $services['logger'];
    $db_projects = $services['db_projects'];
    $db_modules = $services['db_modules'];
    $db_jobs = $services['db_jobs'];
    $db_processed_items = $services['db_processed_items'];
    $db_remote_locations = $services['db_remote_locations'];
    $handler_factory = $services['handler_factory'];
    $job_creator = $services['job_creator'];
    $orchestrator = $services['orchestrator'];
    $scheduler = $services['scheduler'];
    
    // Filter-based service access
    add_filter('dm_get_service', function($service, $service_name) use ($services) {
        return $services[$service_name] ?? null;
    }, 10, 2);
    
    // Admin setup
    if (is_admin()) {
        new ApiAuthPage($logger);
        add_action('admin_notices', array($logger, 'display_admin_notices'));
    }

    // Initialize core handler auto-registration system
    CoreHandlerRegistry::init();

    // Register default 5-step pipeline
    add_filter('dm_register_pipeline_steps', function($steps) {
        return [
            'input' => [
                'class' => 'DataMachine\\Engine\\Steps\\InputStep',
                'next' => 'process'
            ],
            'process' => [
                'class' => 'DataMachine\\Engine\\Steps\\ProcessStep', 
                'next' => 'factcheck'
            ],
            'factcheck' => [
                'class' => 'DataMachine\\Engine\\Steps\\FactCheckStep',
                'next' => 'finalize'
            ],
            'finalize' => [
                'class' => 'DataMachine\\Engine\\Steps\\FinalizeStep',
                'next' => 'output'
            ],
            'output' => [
                'class' => 'DataMachine\\Engine\\Steps\\OutputStep',
                'next' => null
            ]
        ];
    }, 5);


    // Additional services
    $remote_location_service = new RemoteLocationService($db_remote_locations);
    $sync_remote_locations = new SyncRemoteLocations($db_remote_locations, $logger);
    $remote_locations_form_handler = new RemoteLocationsFormHandler($db_remote_locations, $logger, $sync_remote_locations);

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
        $db_modules,
        $orchestrator,
        $services['oauth_reddit'],
        $services['oauth_twitter'],
        $services['oauth_threads'],
        $services['oauth_facebook'],
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

    // Register async pipeline step hooks dynamically
    $pipeline_steps = apply_filters( 'dm_register_pipeline_steps', [] );
    foreach ( $pipeline_steps as $step_name => $step_config ) {
        $hook_name = 'dm_' . $step_name . '_job_event';
        
        // All steps use the unified dynamic orchestrator
        add_action( $hook_name, function( $job_id ) use ( $orchestrator, $step_name ) {
            return $orchestrator->execute_step( $step_name, $job_id );
        }, 10, 1 );
    }

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