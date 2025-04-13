<?php
/**
 * Plugin Name:     Data Machine
 * Plugin URI:      chubes.net
 * Description:     A plugin to automatically collect data from files using OpenAI API, fact-check it, and return a final output.
 * Version:         0.1.0
 * Author:          Chris Huber
 * Author URI:      chubes.net
 * Text Domain:     data-machine
 * Domain Path:     /languages
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

// Include necessary base classes and interfaces first
require_once DATA_MACHINE_PATH . 'includes/class-service-locator.php';
require_once DATA_MACHINE_PATH . 'includes/interfaces/interface-data-machine-output-handler.php';
require_once DATA_MACHINE_PATH . 'includes/interfaces/interface-input-handler.php';
require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php'; // Updated path
require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php'; // Added projects class
require_once DATA_MACHINE_PATH . 'includes/database/class-database-jobs.php'; // Jobs Database Class
require_once DATA_MACHINE_PATH . 'includes/database/class-database-processed-items.php'; // Processed Items Database Class
require_once DATA_MACHINE_PATH . 'includes/ajax/class-module-ajax-handler.php'; // Added Module AJAX Handler
require_once DATA_MACHINE_PATH . 'includes/ajax/class-ajax-job-status.php'; // Modular Job Status AJAX Handler
require_once DATA_MACHINE_PATH . 'includes/ajax/class-data-machine-ajax-projects.php'; // AJAX Handler for Dashboard Project Actions
require_once DATA_MACHINE_PATH . 'includes/ajax/class-data-machine-ajax-locations.php'; // AJAX Handler for Location Actions
require_once DATA_MACHINE_PATH . 'admin/class-data-machine-remote-locations.php'; // Admin Form Handler for Remote Locations
require_once DATA_MACHINE_PATH . 'includes/helpers/class-import-export.php'; // Added Import/Export Helper
require_once DATA_MACHINE_PATH . 'includes/helpers/class-data-machine-logger.php'; // Updated Logger Class path

/**
 * Register custom rewrite endpoint for Instagram OAuth
 */
function dm_register_oauth_instagram_endpoint() {
    add_rewrite_rule('^oauth-instagram/?$', 'index.php?dm_oauth_instagram=1', 'top');
}
add_action('init', 'dm_register_oauth_instagram_endpoint');

/**
 * Add custom query var for Instagram OAuth
 */
function dm_add_oauth_instagram_query_var($vars) {
    $vars[] = 'dm_oauth_instagram';
    return $vars;
}
add_filter('query_vars', 'dm_add_oauth_instagram_query_var');

/**
 * Handle the Instagram OAuth endpoint
 */
function dm_handle_oauth_instagram_endpoint() {
    if (get_query_var('dm_oauth_instagram')) {
        include_once plugin_dir_path(__FILE__) . 'includes/helpers/oauth-instagram.php';
        exit;
    }
}
add_action('template_redirect', 'dm_handle_oauth_instagram_endpoint');

/**
 * Begins execution of the plugin.
 *
 * @since    0.1.0
 */
function run_data_machine() {
	// Create the Service Locator instance
	$locator = new Data_Machine_Service_Locator();

	// --- Register ALL Services ---
	// Register services that DON'T depend on the main plugin instance first

	// Register Handler Registry
	require_once DATA_MACHINE_PATH . 'includes/class-handler-registry.php';
	$locator->register('handler_registry', function($locator) {
		return new Data_Machine_Handler_Registry(DATA_MACHINE_PATH);
	});

	// Register all output handlers with the locator
	$handler_registry = $locator->get('handler_registry');
	foreach ($handler_registry->get_output_handlers() as $slug => $handler_info) {
		$key = 'output_' . $slug;
		$class_name = $handler_info['class'];
		$locator->register($key, function($locator) use ($class_name) {
			// Special case: publish-remote needs the remote locations DB
			if ($class_name === 'Data_Machine_Output_Publish_Remote') {
				return new $class_name($locator->get('database_remote_locations'), $locator->get('logger'));
			}
			return new $class_name();
		});
	}

	// Register Logger Service (No dependencies)
	$locator->register('logger', function($locator) {
		// Path required above
		return new Data_Machine_Logger();
	});

	// Register Database Projects first (as Modules might depend on it later)
	$locator->register('database_projects', function($locator) {
		// Path already required above
		return new Data_Machine_Database_Projects($locator);
	});

	// Register Database Modules
	$locator->register('database_modules', function($locator) {
		// Path already required above
		return new Data_Machine_Database_Modules($locator); // Pass locator
	});

	// Register Database Jobs
	$locator->register('database_jobs', function($locator) {
		// Path required above
		return new Data_Machine_Database_Jobs($locator);
	});

	// Register Processed Items
	$locator->register('database_processed_items', function($locator) {
		// Path required above
		return new Data_Machine_Database_Processed_Items($locator);
	});

	// Register Database Remote Locations
	$locator->register('database_remote_locations', function($locator) {
		// Path required in activation hook, ensure it's loaded if needed elsewhere too
		if (!class_exists('Data_Machine_Database_Remote_Locations')) require_once DATA_MACHINE_PATH . 'includes/database/class-database-remote-locations.php';
		return new Data_Machine_Database_Remote_Locations($locator);
	});

	// Register Import/Export Handler (depends on locator, and DB classes being registered)
	$locator->register('import_export_handler', function($locator) {
		// Path already required above
		return new Data_Machine_Import_Export($locator); // Pass locator
	});

	// --- Force instantiation to register hooks --- 
	$locator->get('import_export_handler');
	// --- End force instantiation ---

	// Register Module AJAX Handler (depends on Locator)
	$locator->register('module_ajax_handler', function($locator) {
		return new Data_Machine_Module_Ajax_Handler(
			$locator // Pass the locator itself
		);
	});

	// Register Dashboard Project AJAX Handler
	$locator->register('dashboard_ajax_handler', function($locator) {
		// Ensure dependencies are loaded if not already
		if (!class_exists('Data_Machine_Database_Jobs')) require_once DATA_MACHINE_PATH . 'includes/database/class-database-jobs.php';

		return new Data_Machine_Ajax_Projects(
			$locator->get('database_projects'),
			$locator->get('database_modules'),
			$locator->get('database_jobs'), // Inject DB Jobs
			$locator // Inject locator itself
		);
	});

	// Register Locations AJAX Handler
	$locator->register('locations_ajax_handler', function($locator) {
		// File already required at the top of this file
		return new Data_Machine_Ajax_Locations(
			$locator // Pass locator for service access
		);
	});

	// Register Remote Locations Admin Form Handler
	$locator->register('remote_locations_admin', function($locator) {
		// File already required at the top of this file
		return new Data_Machine_Remote_Locations(
			$locator // Pass locator for service access
		);
	});

	// Settings class has been removed and its functionality distributed to utility classes:
	// - Data_Machine_Register_Settings - handles WordPress settings registration
	// - Data_Machine_Module_Handler - handles module selection and creation
	// - Data_Machine_API_AJAX_Handler - handles API-related AJAX operations

	// Explicitly register the Register Settings service
	$locator->register('register_settings', function($locator) {
		if (!class_exists('Data_Machine_Register_Settings')) require_once DATA_MACHINE_PATH . 'admin/utilities/class-data-machine-register-settings.php';
		return new Data_Machine_Register_Settings(DATA_MACHINE_VERSION, $locator);
	});

	// Register Settings Fields Service
	$locator->register('settings_fields', function($locator) {
		if (!class_exists('Data_Machine_Settings_Fields')) require_once DATA_MACHINE_PATH . 'admin/class-data-machine-settings-fields.php';
		return new Data_Machine_Settings_Fields($locator);
	});

	// Register Handler Registry Service
	$locator->register('handler_registry', function($locator) {
		if (!class_exists('Data_Machine_Handler_Registry')) require_once DATA_MACHINE_PATH . 'includes/class-handler-registry.php';
		return new Data_Machine_Handler_Registry();
	});

	$locator->register('admin_page', function($locator) {
		if (!class_exists('Data_Machine_Admin_Page')) require_once DATA_MACHINE_PATH . 'admin/class-data-machine-admin-page.php';
		return new Data_Machine_Admin_Page(
			DATA_MACHINE_VERSION,
			$locator->get('database_modules'), // Inject the DB Modules instance
			$locator->get('database_projects'), // Inject the DB Projects instance
			$locator // Pass the locator itself
		);
	});

	$locator->register('openai_api', function($locator) {
		if (!class_exists('Data_Machine_API_OpenAI')) require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-openai.php';
		return new Data_Machine_API_OpenAI();
	});

	$locator->register('factcheck_api', function($locator) {
		if (!class_exists('Data_Machine_API_FactCheck')) require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-factcheck.php';
		return new Data_Machine_API_FactCheck();
	});

	$locator->register('finalize_api', function($locator) {
		if (!class_exists('Data_Machine_API_Finalize')) require_once DATA_MACHINE_PATH . 'includes/api/class-data-machine-api-finalize.php';
		return new Data_Machine_API_Finalize();
	});

	$locator->register('output_publish_local', function($locator) {
		if (!class_exists('Data_Machine_Output_Publish_Local')) require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-publish_local.php';
		return new Data_Machine_Output_Publish_Local();
	});
	$locator->register('output_publish_remote', function($locator) {
		if (!class_exists('Data_Machine_Output_Publish_Remote')) require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-publish_remote.php';
		return new Data_Machine_Output_Publish_Remote(
			$locator->get('database_remote_locations'), // Inject DB service
			$locator->get('logger') // Inject Logger service
		);
	});
	$locator->register('output_data_export', function($locator) {
		if (!class_exists('Data_Machine_Output_Data_Export')) require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-data_export.php';
		return new Data_Machine_Output_Data_Export();
	});

	// Register services that depend on OTHER services (but not the main plugin yet)

	$locator->register('process_data', function($locator) {
		if (!class_exists('Data_Machine_process_data')) require_once DATA_MACHINE_PATH . 'includes/class-process-data.php';
		return new Data_Machine_process_data(
			$locator->get('openai_api') // Inject OpenAI API
			// Logger dependency removed
		);
	});

	$locator->register('orchestrator', function($locator) {
		if (!class_exists('Data_Machine_Processing_Orchestrator')) require_once DATA_MACHINE_PATH . 'includes/class-processing-orchestrator.php';
		return new Data_Machine_Processing_Orchestrator(
			$locator->get('process_data'), // Inject Process_Data
			$locator->get('factcheck_api'), // Inject FactCheck_API
			$locator->get('finalize_api'),  // Inject Finalize_API
			$locator // Inject Locator itself (to get output handlers later)
		);
	});

	$locator->register('input_files', function($locator) {
		if (!class_exists('Data_Machine_Input_Files')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-files.php';
		return new Data_Machine_Input_Files(
			$locator->get('orchestrator'), // Inject Orchestrator
			$locator->get('database_modules') // Inject DB Modules (path updated above)
			// Plugin dependency removed
		);
	});

	$locator->register('input_airdrop_rest_api', function($locator) {
		if (!class_exists('Data_Machine_Input_Airdrop_Rest_Api')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-airdrop_rest_api.php';
		return new Data_Machine_Input_Airdrop_Rest_Api($locator); // Constructor needs locator
	});

	$locator->register('input_public_rest_api', function($locator) {
		if (!class_exists('Data_Machine_Input_Public_Rest_Api')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-public_rest_api.php';
		return new Data_Machine_Input_Public_Rest_Api($locator); // Constructor needs locator
	});

	$locator->register('input_reddit', function($locator) {
		if (!class_exists('Data_Machine_Input_Reddit')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-reddit.php';
		return new Data_Machine_Input_Reddit($locator); // Constructor needs locator
	});

	$locator->register('input_rss', function($locator) {
		if (!class_exists('Data_Machine_Input_Rss')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-rss.php';
		return new Data_Machine_Input_Rss($locator); // Constructor needs locator
	});

	$locator->register('input_instagram', function($locator) {
		if (!class_exists('Data_Machine_Input_Instagram')) require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-instagram.php';
		// Assuming the Instagram handler constructor does not require the locator for now
		return new Data_Machine_Input_Instagram();
	});

	// --- Force instantiation of admin pages/handlers to register hooks ---
	// Get services that need their hooks registered in their constructors.
	$locator->get('import_export_handler');
	$locator->get('settings_fields');
	$locator->get('admin_page'); // Main settings/dashboard page
	$locator->get('module_ajax_handler');
	$locator->get('dashboard_ajax_handler');
	$locator->get('locations_ajax_handler'); // Force instantiate the locations AJAX handler
	$locator->get('remote_locations_admin'); // Force instantiate the remote locations admin handler
	// $locator->get('remote_locations_admin_page'); // Removed - Handled by admin_page service now

	// --- Instantiate Main Plugin ---
	// Now that all dependencies are registered, instantiate the main plugin
	if (!class_exists('Data_Machine')) require_once DATA_MACHINE_PATH . 'includes/class-data-machine.php';
	$plugin = new Data_Machine($locator); // Pass the locator

	// --- Run the Plugin ---
	$plugin->run();

}
run_data_machine();

// Ensure the job status AJAX handler is registered
if (class_exists('Data_Machine_Ajax_Job_Status')) {
    new Data_Machine_Ajax_Job_Status();
}

/**
 * Allow JSON file uploads for import functionality.
 *
 * @param array $mime_types Existing allowed MIME types.
 * @return array Modified MIME types.
 */
function dm_allow_json_upload( $mime_types ) {
	// Only allow JSON uploads for users who can manage options (or adjust capability)
	if ( current_user_can( 'manage_options' ) ) {
		$mime_types['json'] = 'application/json';
	}
	return $mime_types;
}
add_filter( 'upload_mimes', 'dm_allow_json_upload' );

// Activation and deactivation hooks (if needed)
register_activation_hook( __FILE__, 'activate_data_machine' );
register_deactivation_hook( __FILE__, array( 'Data_Machine', 'deactivate' ) );

function activate_data_machine() {
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

	// Maybe clear scheduled crons? (Consider if necessary)
	// wp_clear_scheduled_hook('Data_Machine_process_job_cron');
}