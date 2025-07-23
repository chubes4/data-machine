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

// Include necessary base classes and interfaces first
require_once DATA_MACHINE_PATH . 'includes/class-data-machine-constants.php'; // Constants must be loaded first
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-base-input-handler.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-base-output-handler.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/class-data-machine-handler-http-service.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php'; // Updated path
require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php'; // Added projects class
require_once DATA_MACHINE_PATH . 'includes/database/class-database-jobs.php'; // Jobs Database Class
require_once DATA_MACHINE_PATH . 'includes/database/class-database-processed-items.php'; // Processed Items Database Class
require_once DATA_MACHINE_PATH . 'admin/module-config/ajax/class-module-config-ajax.php'; // Updated Module AJAX Handler path
require_once DATA_MACHINE_PATH . 'admin/projects/class-project-management-ajax.php'; // Corrected AJAX Handler path for Dashboard Project Actions
require_once DATA_MACHINE_PATH . 'admin/remote-locations/class-data-machine-remote-locations-form-handler.php'; // Remote Locations Form Handler
require_once DATA_MACHINE_PATH . 'admin/projects/class-data-machine-import-export.php'; // Added Import/Export Helper
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-logger.php'; // Updated Logger Class path
require_once DATA_MACHINE_PATH . 'includes/engine/filters/class-data-machine-prompt-builder.php'; // Centralized prompt builder
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-action-scheduler.php'; // Action Scheduler service
require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-openai.php'; // OpenAI API integration
require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-factcheck.php'; // Fact check API
require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-finalize.php'; // Finalize API
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-memory-guard.php'; // Memory protection service
require_once DATA_MACHINE_PATH . 'admin/projects/class-data-machine-scheduler.php'; // Added Scheduler class
require_once DATA_MACHINE_PATH . 'admin/projects/class-data-machine-ajax-scheduler.php'; // Added AJAX Scheduler class
require_once DATA_MACHINE_PATH . 'admin/module-config/RegisterSettings.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/HandlerFactory.php';
require_once DATA_MACHINE_PATH . 'admin/remote-locations/RemoteLocationService.php';
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-reddit.php';
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-twitter.php'; // Added missing include
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-threads.php'; // Add Threads OAuth
require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-facebook.php'; // Add Facebook OAuth
require_once DATA_MACHINE_PATH . 'includes/engine/class-job-status-manager.php'; // Centralized job status management
require_once DATA_MACHINE_PATH . 'includes/engine/class-job-creator.php'; // Unified job creation class
require_once DATA_MACHINE_PATH . 'admin/module-config/SettingsFields.php';
require_once DATA_MACHINE_PATH . 'admin/module-config/class-dm-module-config-handler.php';
require_once DATA_MACHINE_PATH . 'admin/module-config/ajax/module-config-remote-locations-ajax.php';
require_once DATA_MACHINE_PATH . 'admin/projects/class-file-upload-handler.php';
// Include the new module config assets file
// require_once DATA_MACHINE_PATH . 'admin/module-config/module-config-enqueue-assets.php';



/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // --- Instantiate core dependencies ---
    $logger = new Data_Machine_Logger();
    $encryption_helper = new Data_Machine_Encryption_Helper();
    $action_scheduler = new Data_Machine_Action_Scheduler($logger);
    $memory_guard = new Data_Machine_Memory_Guard($logger);

    // Register API/Auth admin_post handlers
    if (is_admin()) {
        require_once DATA_MACHINE_PATH . 'admin/oauth/class-dm-api-auth-page.php';
        new Data_Machine_Api_Auth_Page($logger);
    }

    // Hook the logger's display method to admin notices
    if (is_admin()) { // Only hook in the admin area
        add_action( 'admin_notices', array( $logger, 'display_admin_notices' ) );
    }

    // Database classes
    $db_projects = new Data_Machine_Database_Projects();
    $db_modules = new Data_Machine_Database_Modules($db_projects, $logger);
    $db_jobs = new Data_Machine_Database_Jobs($db_projects, $logger);
    $db_processed_items = new Data_Machine_Database_Processed_Items($logger);
    $db_remote_locations = new Data_Machine_Database_Remote_Locations();

    // Handler services
    $handler_http_service = new Data_Machine_Handler_HTTP_Service($logger);

    // OAuth handlers
    $oauth_twitter = new Data_Machine_OAuth_Twitter($logger); // Assumes constructor handles missing credentials gracefully or fetches globally if needed
    $oauth_reddit = new Data_Machine_OAuth_Reddit($logger);   // Assumes constructor handles missing credentials gracefully or fetches globally if needed

    // Get Threads app credentials from options
    $threads_app_credentials = get_option('dm_threads_app_credentials', []);
    $threads_client_id = $threads_app_credentials['client_id'] ?? '';
    $threads_client_secret = $threads_app_credentials['client_secret'] ?? '';
    $oauth_threads = new Data_Machine_OAuth_Threads($threads_client_id, $threads_client_secret, $logger);

    // Get Facebook app credentials from options
    $facebook_app_credentials = get_option('dm_facebook_app_credentials', []);
    $facebook_client_id = $facebook_app_credentials['client_id'] ?? '';
    $facebook_client_secret = $facebook_app_credentials['client_secret'] ?? '';
    $oauth_facebook = new Data_Machine_OAuth_Facebook($facebook_client_id, $facebook_client_secret, $logger);

    // Centralized prompt builder
    $prompt_builder = new Data_Machine_Prompt_Builder($db_projects);

    // Handler registry
    $handler_registry = new Data_Machine_Handler_Registry();

    // Output handlers
    $output_publish_local = new Data_Machine_Output_Publish_Local($db_processed_items);
    $output_publish_remote = new Data_Machine_Output_Publish_Remote($db_remote_locations, $handler_http_service, $logger, $db_processed_items);
    $output_data_export = new Data_Machine_Output_Data_Export();
    $output_twitter = new Data_Machine_Output_Twitter($oauth_twitter, $logger);
    // Add other output handlers as needed

    // Input handlers
    $input_files = new Data_Machine_Input_Files($db_modules, $db_projects, $db_processed_items, $logger);
    $input_airdrop_rest_api = new Data_Machine_Input_Airdrop_Rest_Api($db_modules, $db_projects, $db_processed_items, $db_remote_locations, $handler_http_service, $logger);
    $input_public_rest_api = new Data_Machine_Input_Public_Rest_Api($db_modules, $db_projects, $db_processed_items, $handler_http_service, $logger);
    $input_reddit = new Data_Machine_Input_Reddit($db_modules, $db_projects, $db_processed_items, $oauth_reddit, $handler_http_service, $logger);
    $input_rss = new Data_Machine_Input_Rss($db_modules, $db_projects, $db_processed_items, $handler_http_service, $logger);
    // Add other input handlers as needed

    // API services
    $openai_api = new Data_Machine_API_OpenAI();
    $factcheck_api = new Data_Machine_API_FactCheck($openai_api);
    $finalize_api = new Data_Machine_API_Finalize($openai_api);

    // Process data
    $process_data = new Data_Machine_process_data($openai_api);



    // Handler factory (replace with DI version if available)
    $handler_factory = new Dependency_Injection_Handler_Factory(
        $handler_registry,
        $logger,
        $db_processed_items,
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

    // Remote location service
    $remote_location_service = new Data_Machine_Remote_Location_Service($db_remote_locations);

    // Settings fields
    $settings_fields = new Data_Machine_Settings_Fields($handler_factory, $handler_registry, $remote_location_service);

    // Remote locations sync service
    $sync_remote_locations = new Data_Machine_Sync_Remote_Locations($db_remote_locations, $logger);

    // Remote locations form handler
    $remote_locations_form_handler = new Data_Machine_Remote_Locations_Form_Handler($db_remote_locations, $logger, $sync_remote_locations);

    // Admin page
    $admin_page = new Data_Machine_Admin_Page(
        DATA_MACHINE_VERSION,
        $db_modules,
        $db_projects,
        $logger,
        $handler_registry,
        $settings_fields,
        $handler_factory,
        $remote_locations_form_handler
    );

    // Register settings - Handled in Data_Machine class

    // Module handler
    $module_handler = new Data_Machine_Module_Handler(
        $db_modules,
        $handler_registry,
        $handler_factory,
        $logger
    );
    $module_handler->init_hooks();

    // Public API AJAX handler

    // Job worker, orchestrator, job executor, scheduler
    $job_status_manager = new Data_Machine_Job_Status_Manager($db_jobs, $db_projects, $logger);
    
    $orchestrator = new Data_Machine_Processing_Orchestrator(
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
    $job_creator = new Data_Machine_Job_Creator($db_jobs, $db_modules, $db_projects, $action_scheduler, $logger);
    $scheduler = new Data_Machine_Scheduler($job_creator, $db_projects, $db_modules, $action_scheduler, $db_jobs, $logger);

    // Global container for Action Scheduler access
    global $data_machine_container;
    $data_machine_container = array(
        'handler_factory' => $handler_factory,
        'db_jobs' => $db_jobs,
        'db_processed_items' => $db_processed_items,
        'job_status_manager' => $job_status_manager,
        'job_creator' => $job_creator,
        'action_scheduler' => $action_scheduler,
        'logger' => $logger
    );

    // Import/export handler
    $import_export_handler = new Data_Machine_Import_Export($db_projects, $db_modules);

    // AJAX handlers
    $module_ajax_handler = new Data_Machine_Module_Config_Ajax($db_modules, $db_projects, $input_files, $db_remote_locations, $logger);
    $dashboard_ajax_handler = new Data_Machine_Project_Management_Ajax($db_projects, $db_modules, $db_jobs, $db_processed_items, $job_creator, $logger);
    $ajax_scheduler = new Data_Machine_Ajax_Scheduler($db_projects, $db_modules, $scheduler);
    $ajax_auth = new Data_Machine_Ajax_Auth();
    $remote_locations_ajax_handler = new Data_Machine_Module_Config_Remote_Locations_Ajax($db_remote_locations, $logger);
    $file_upload_handler = new Data_Machine_File_Upload_Handler($db_modules, $db_projects, $logger);

    // --- Instantiate Main Plugin ---
    if (!class_exists('Data_Machine')) require_once DATA_MACHINE_PATH . 'includes/class-data-machine.php';
    $register_settings = new Data_Machine_Register_Settings(DATA_MACHINE_VERSION);
    $plugin = new Data_Machine(
            DATA_MACHINE_VERSION,
        $register_settings,
        $admin_page,
        $openai_api,
        $factcheck_api,
        $finalize_api,
        $process_data,
            $db_modules,
        $orchestrator,
        $output_publish_local,
        $output_publish_remote,
        $output_data_export,
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
    // Note: OUTPUT_JOB_HOOK is registered by Action Scheduler helper in init_hooks()

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
register_deactivation_hook( __FILE__, array( 'Data_Machine', 'deactivate' ) );

function activate_data_machine() {
	// Action Scheduler is now bundled as a library - no dependency check needed

	// Include database class files directly as autoloading might not be ready
	require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
	require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php';
	require_once DATA_MACHINE_PATH . 'includes/database/class-database-jobs.php';
	require_once DATA_MACHINE_PATH . 'includes/database/class-database-processed-items.php';
	require_once DATA_MACHINE_PATH . 'includes/database/class-database-remote-locations.php'; // Add new class

	// Create/Update tables using static methods where available
	Data_Machine_Database_Projects::create_table();
	Data_Machine_Database_Modules::create_table();
	Data_Machine_Database_Jobs::create_table();
	Data_Machine_Database_Remote_Locations::create_table(); // Add table creation call

	// Instantiate and call instance method for Processed_Items
	$db_processed_items = new Data_Machine_Database_Processed_Items();
	$db_processed_items->create_table();

	// Set a transient flag for first-time admin notice or setup wizard (optional)
	set_transient( 'dm_activation_notice', true, 5 * MINUTE_IN_SECONDS );
}