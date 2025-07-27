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

// Load centralized service filter registration
require_once __DIR__ . '/inc/DataMachineFilters.php';

// PSR-4 Autoloading - no manual includes needed
use DataMachine\Core\{DataMachine, Constants, CoreHandlerRegistry};
use DataMachine\Admin\{AdminPage, AdminMenuAssets};
use DataMachine\Admin\OAuth\{Twitter as OAuthTwitter, Reddit as OAuthReddit, Threads as OAuthThreads, Facebook as OAuthFacebook, ApiAuthPage, AjaxAuth};
use DataMachine\Admin\Projects\{Scheduler, AjaxScheduler, ImportExport, FileUploadHandler, ProjectManagementAjax, PipelineManagementAjax, ProjectPipelineStepsAjax, ModalConfigAjax};
use DataMachine\Admin\ModuleConfig\{RegisterSettings, SettingsFields, ModuleConfigHandler};
use DataMachine\Admin\ModuleConfig\Ajax\{ModuleConfigAjax, RemoteLocationsAjax};
use DataMachine\Admin\RemoteLocations\{RemoteLocationService, FormHandler as RemoteLocationsFormHandler, SyncRemoteLocations};
use DataMachine\Database\{Jobs as DatabaseJobs, Modules as DatabaseModules, Projects as DatabaseProjects, ProcessedItems as DatabaseProcessedItems, RemoteLocations as DatabaseRemoteLocations};
use DataMachine\Engine\{JobCreator, ProcessingOrchestrator, JobStatusManager, ProcessedItemsManager};
use DataMachine\Engine\Filters\{AiResponseParser, PromptBuilder, MarkdownConverter};
use DataMachine\Core\Handlers\HttpService;
use DataMachine\Core\Handlers\Input\Files as InputFiles;
use DataMachine\Helpers\{Logger, EncryptionHelper, ProjectPromptsService, ProjectPipelineConfigService};




/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // Initialize pure filter-based service registry
    // ServiceRegistry removed - all services now use ultra-direct filters
    
    // Register ultra-direct service filters for most heavily used services
    dm_register_direct_service_filters();
    
    // Admin setup
    if (is_admin()) {
        // Use pure filter-based instantiation for API auth page
        $api_auth_page = apply_filters('dm_get_api_auth_page', null);
        $logger = apply_filters('dm_get_logger', null);
        add_action('admin_notices', array($logger, 'display_admin_notices'));
    }

    // Initialize core handler auto-registration system
    CoreHandlerRegistry::init();

    // Register core step types
    add_filter('dm_register_step_types', function($step_types) {
        $step_types['input'] = [
            'class' => 'DataMachine\\Core\\Steps\\InputStep',
            'label' => __('Input Step', 'data-machine'),
            'type' => 'input'
        ];
        
        $step_types['ai'] = [
            'class' => 'DataMachine\\Core\\Steps\\AIStep',
            'label' => __('AI Processing Step', 'data-machine'),
            'type' => 'ai'
        ];
        
        $step_types['output'] = [
            'class' => 'DataMachine\\Core\\Steps\\OutputStep',
            'label' => __('Output Step', 'data-machine'),
            'type' => 'output'
        ];
        
        return $step_types;
    }, 5);

    // Maintain compatibility with existing dm_register_pipeline_step_types filter
    add_filter('dm_register_pipeline_step_types', function($step_types) {
        $registered_step_types = apply_filters('dm_register_step_types', []);
        return $registered_step_types;
    }, 10);


    // Admin initialization - use filter-based service access
    $admin_page = apply_filters('dm_get_admin_page', null);

    // Module handler - uses pure filter-based service access
    $module_handler = apply_filters('dm_get_module_config_handler', null);
    $module_handler->init_hooks();

    // Import/export handler - pure filter-based instantiation
    $import_export_handler = apply_filters('dm_get_import_export_handler', null);

    // AJAX handlers - all use pure filter-based instantiation for extensibility
    $module_ajax_handler = apply_filters('dm_get_module_ajax_handler', null);
    $dashboard_ajax_handler = apply_filters('dm_get_dashboard_ajax_handler', null);
    $ajax_scheduler = apply_filters('dm_get_ajax_scheduler', null);
    $ajax_auth = apply_filters('dm_get_ajax_auth', null);
    $remote_locations_ajax_handler = apply_filters('dm_get_remote_locations_ajax_handler', null);
    $file_upload_handler = apply_filters('dm_get_file_upload_handler', null);
    $pipeline_management_ajax_handler = apply_filters('dm_get_pipeline_management_ajax_handler', null);
    $pipeline_steps_ajax_handler = apply_filters('dm_get_pipeline_steps_ajax_handler', null);
    $modal_config_ajax_handler = apply_filters('dm_get_modal_config_ajax_handler', null);
    $modal_config_ajax_handler->init_hooks();

    // --- Main Plugin Instantiation - pure filter-based for external override capability ---
    $register_settings = apply_filters('dm_get_register_settings', null);
    $plugin = apply_filters('dm_get_data_machine_plugin', null);

    // --- Run the Plugin ---
    $plugin->run();

    // Register hooks for AJAX handlers
    add_action( 'wp_ajax_dm_get_module_data', array( $module_ajax_handler, 'get_module_data_ajax_handler' ) );

    add_action( 'wp_ajax_dm_run_now', array( $dashboard_ajax_handler, 'handle_run_now' ) );
    add_action( 'wp_ajax_dm_edit_schedule', array( $dashboard_ajax_handler, 'handle_edit_schedule' ) );
    add_action( 'wp_ajax_dm_get_project_schedule_data', array( $dashboard_ajax_handler, 'handle_get_project_schedule_data' ) );

    // Register async pipeline step hooks dynamically using current step types
    $pipeline_steps = apply_filters( 'dm_register_pipeline_step_types', [] );
    $orchestrator = apply_filters('dm_get_orchestrator', null);
    foreach ( $pipeline_steps as $step_name => $step_config ) {
        $hook_name = 'dm_' . $step_name . '_job_event';
        
        // All steps use the unified dynamic orchestrator
        add_action( $hook_name, function( $job_id ) use ( $orchestrator, $step_name ) {
            return $orchestrator->execute_step( $step_name, $job_id );
        }, 10, 1 );
    }

    // Initialize scheduler hooks
    $scheduler = apply_filters('dm_get_scheduler', null);
    if ($scheduler) {
        $scheduler->init_hooks();
    }

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
register_deactivation_hook( __FILE__, array( '\\DataMachine\\Core\\DataMachine', 'deactivate' ) );

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

