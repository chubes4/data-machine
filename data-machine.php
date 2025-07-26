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
use DataMachine\Helpers\{Logger, MemoryGuard, EncryptionHelper, ActionScheduler, ProjectPromptsService, ProjectPipelineConfigService};
use DataMachine\Contracts\{LoggerInterface, ActionSchedulerInterface};



/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
    // Initialize pure filter-based service registry
    \DataMachine\ServiceRegistry::init();
    
    // Admin setup
    if (is_admin()) {
        new ApiAuthPage();
        $logger = apply_filters('dm_get_service', null, 'logger');
        add_action('admin_notices', array($logger, 'display_admin_notices'));
    }

    // Initialize core handler auto-registration system
    CoreHandlerRegistry::init();

    // Register universal 3-step pipeline architecture
    add_filter('dm_register_pipeline_steps', function($steps) {
        return [
            'input' => [
                'class' => 'DataMachine\\Engine\\Steps\\InputStep',
                'next' => 'ai'
            ],
            'ai' => [
                'class' => 'DataMachine\\Engine\\Steps\\AIStep',
                'next' => 'output'
            ],
            'output' => [
                'class' => 'DataMachine\\Engine\\Steps\\OutputStep',
                'next' => null
            ]
        ];
    }, 5);


    // Admin initialization
    new AdminPage();

    // Module handler - uses filter-based service access
    $module_handler = new ModuleConfigHandler();
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
	
	// Clean up legacy active project/module user meta keys
	dm_cleanup_legacy_user_meta();
}

/**
 * Clean up orphaned user meta keys from the legacy active project/module system.
 * This removes the Data_Machine_current_project and Data_Machine_current_module
 * user meta keys that are no longer needed after removing the active selection feature.
 */
function dm_cleanup_legacy_user_meta() {
	global $wpdb;
	
	// Delete legacy user meta keys for active project/module selections
	$legacy_keys = [
		'Data_Machine_current_project',
		'Data_Machine_current_module',
		'auto_data_collection_current_project', // Old plugin name
		'auto_data_collection_current_module'   // Old plugin name
	];
	
	foreach ($legacy_keys as $meta_key) {
		$deleted = $wpdb->delete(
			$wpdb->usermeta,
			['meta_key' => $meta_key],
			['%s']
		);
		
		if ($deleted !== false && $deleted > 0) {
			error_log("Data Machine: Cleaned up {$deleted} orphaned user meta entries for key: {$meta_key}");
		}
	}
}