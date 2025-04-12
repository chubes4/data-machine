<?php

/**
 * The main plugin class.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 */

// Include necessary files FIRST
require_once plugin_dir_path( __FILE__ ) . '../admin/class-data-machine-admin-page.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-data-machine-api-openai.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-data-machine-api-factcheck.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-data-machine-api-finalize.php';
require_once plugin_dir_path( __FILE__ ) . 'class-process-data.php';
require_once plugin_dir_path( __FILE__ ) . 'database/class-database-modules.php'; // Updated path
require_once DATA_MACHINE_PATH . 'includes/class-processing-orchestrator.php'; // Added include
require_once DATA_MACHINE_PATH . 'includes/interfaces/interface-input-handler.php'; // Moved up
require_once DATA_MACHINE_PATH . 'includes/interfaces/interface-data-machine-output-handler.php'; // Add correct interface back
require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-publish_local.php';
require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-publish_remote.php';
require_once DATA_MACHINE_PATH . 'includes/output/class-data-machine-output-data_export.php';
require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-files.php';
require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-airdrop_rest_api.php'; // Renamed from rest-api
require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-public_rest_api.php'; // Added new public handler
require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-rss.php';
require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-reddit.php';


/**
 * The main plugin class.
 */
class Data_Machine {

	/**
	 * The plugin version.
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current plugin version.
	 */
	private $version;

	/**
	 * Settings class instance.
	 * @since    0.1.0
	 * @var      Data_Machine_Register_Settings    $register_settings    Settings registration class instance.
	 */
	public $register_settings;

    /**
     * Admin Page class instance.
     * @since    0.1.0
     * @var      Data_Machine_Admin_Page    $admin_page    Admin Page class instance.
     */
    public $admin_page;

	/**
	 * OpenAI API class instance.
	 * @since    0.1.0
	 * @var      Data_Machine_API_OpenAI    $openai_api    OpenAI API class instance.
	 */
	public $openai_api;

	/**
	 * FactCheck API class instance.
	 * @since    0.1.0
	 * @var      Data_Machine_API_FactCheck    $factcheck_api    FactCheck API class instance.
	 */
	public $factcheck_api;

	/**
	 * Finalize API class instance.
	 * @since    0.1.0
	 * @var      Data_Machine_API_Finalize    $finalize_api    Finalize API class instance.
	 */
	public $finalize_api;

	/**
	 * Process Data class instance.
	 * @since    0.1.0
	 * @var      Data_Machine_process_data    $process_data    Process Data class instance.
	 */
	public $process_data;

	/**
	 * Database Modules class instance.
	 * @since    0.2.0
	 * @var      Data_Machine_Database_Modules    $db_modules    Database Modules class instance.
	 */
	public $db_modules;

	/**
	 * Processing Orchestrator class instance.
	 * @since    0.7.0
	 * @var      Data_Machine_Processing_Orchestrator    $orchestrator    Orchestrator class instance.
	 */
	public $orchestrator; // Added property

	/**
	 * Output handler for local publishing.
	 * @var Data_Machine_Output_Publish_Local
	 */
	public $output_publish_local;

	/**
	 * Output handler for remote publishing.
	 * @var Data_Machine_Output_Publish_Remote
	 */
	public $output_publish_remote;

	/**
	 * Output handler for data export.
	 * @var Data_Machine_Output_Data_Export
	 * @since 0.7.0
	 */
	public $output_data_export;

	/**
	 * Input handler for file uploads.
	 * @var Data_Machine_Input_Files
	 */
	public $input_files;

	/**
	 * Service Locator instance.
	 * @since    0.9.0
	 * @access   private
	 * @var      Data_Machine_Service_Locator
	 */
	private $locator;

	/**
	 * Initialize the class and set its properties.
	 * @since    0.1.0
	 */
	// Modified constructor to accept the Service Locator
	public function __construct(Data_Machine_Service_Locator $locator) {
		$this->locator = $locator; // Store the locator
		$this->version = DATA_MACHINE_VERSION;

		// Get dependencies from the locator
		// Note: We access properties directly here, but could use getter methods
		// on the locator if more complex logic/checks were needed.
		$this->register_settings = $this->locator->get('register_settings');
		$this->admin_page = $this->locator->get('admin_page');
		$this->openai_api = $this->locator->get('openai_api');
		$this->factcheck_api = $this->locator->get('factcheck_api');
		$this->finalize_api = $this->locator->get('finalize_api');
		$this->process_data = $this->locator->get('process_data');
		$this->db_modules = $this->locator->get('database_modules');
		$this->orchestrator = $this->locator->get('orchestrator');
		$this->output_publish_local = $this->locator->get('output_publish_local');
		$this->output_publish_remote = $this->locator->get('output_publish_remote');
		$this->output_data_export = $this->locator->get('output_data_export');
		$this->input_files = $this->locator->get('input_files');
		// Input_Rest_Api is still instantiated dynamically in the AJAX handler,
		// but could be registered and retrieved here if needed elsewhere.
	}

	/**
	 * Define the core functionality of the plugin.
	 * @since    0.1.0
	 */
	public function run() {
		// Remove old admin menu and enqueue scripts hooks
		// add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		// add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_admin_assets' ) );

		// Instantiate and initialize admin menu/assets handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/utilities/class-data-machine-admin-menu-assets.php'; // Updated path to utilities directory
		$admin_menu_assets = new Data_Machine_Admin_Menu_Assets(
			$this->version,
			$this->locator,
			$this->admin_page // Pass the admin page handler instance
		);
		$admin_menu_assets->init_hooks();
		
		// Register menu assets with the service locator
		$this->locator->register('admin_menu_assets', function() use ($admin_menu_assets) {
			return $admin_menu_assets;
		});

		// Initialize admin notices handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/utilities/class-data-machine-admin-notices.php';
		$admin_notices = new Data_Machine_Admin_Notices($this->locator);
		$admin_notices->init_hooks();
		
		// Register notices handler with the service locator
		$this->locator->register('admin_notices', function() use ($admin_notices) {
			return $admin_notices;
		});
		
		// Initialize settings registration handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/utilities/class-data-machine-register-settings.php';
		$register_settings = new Data_Machine_Register_Settings($this->version, $this->locator);
		$register_settings->init_hooks();
		
		// Register settings handler with the service locator
		$this->locator->register('register_settings', function() use ($register_settings) {
			return $register_settings;
		});

		// Initialize module handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/utilities/class-data-machine-module-handler.php';
		$module_handler = new Data_Machine_Module_Handler($this->locator);
		$module_handler->init_hooks();
		
		// Register module handler with the service locator
		$this->locator->register('module_handler', function() use ($module_handler) {
			return $module_handler;
		});
		
		// Initialize Public API AJAX handler
		require_once plugin_dir_path( __FILE__ ) . 'ajax/class-data-machine-ajax-public-api.php';
		$public_rest_api_ajax_handler = new Data_Machine_Ajax_Public_API($this->locator);
		$public_rest_api_ajax_handler->init_hooks();
		
		// Register Public API AJAX handler with the service locator
		$this->locator->register('ajax_public_api', function() use ($public_rest_api_ajax_handler) {
			return $public_rest_api_ajax_handler;
		});
		// Register Prompt Modifier helper with the service locator
		require_once DATA_MACHINE_PATH . 'includes/helpers/class-prompt-modifier.php';
		$this->locator->register('prompt_modifier', function() {
			return new Data_Machine_Prompt_Modifier();
		});
		
		
		

		// Register remote locations handler with the service locator
		$this->locator->register('remote_locations', function() {
		    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-data-machine-remote-locations.php';
		    return new Data_Machine_Remote_Locations($this->locator);
		});

		// Get AJAX handlers from locator
		$module_ajax_handler = $this->locator->get('module_ajax_handler');
		$settings_handler = $this->locator->get('register_settings'); // Already retrieved earlier, but good to be explicit
		$dashboard_ajax_handler = $this->locator->get('dashboard_ajax_handler'); // Get the dashboard handler

		// Hook Module AJAX actions
		add_action( 'wp_ajax_process_data', array( $module_ajax_handler, 'process_data_source_ajax_handler' ) );
		add_action( 'wp_ajax_dm_get_module_data', array( $module_ajax_handler, 'get_module_data_ajax_handler' ) );
		add_action( 'wp_ajax_dm_check_job_status', array( $module_ajax_handler, 'dm_check_job_status_ajax_handler' ) );
		add_action( 'wp_ajax_dm_save_module', array( $module_ajax_handler, 'save_module_ajax_handler' ) );

		// Hook Dashboard Project AJAX actions
		add_action( 'wp_ajax_dm_run_now', array( $dashboard_ajax_handler, 'handle_run_now' ) );
		add_action( 'wp_ajax_dm_edit_schedule', array( $dashboard_ajax_handler, 'handle_edit_schedule' ) );
		add_action( 'wp_ajax_dm_get_project_schedule_data', array( $dashboard_ajax_handler, 'handle_get_project_schedule_data' ) );

		// Hook for our custom cron job
		add_action( 'dm_run_job_event', array( $module_ajax_handler, 'dm_run_job_callback' ) );

        // Hook for the project-level schedule trigger
        add_action( 'dm_run_project_schedule', array( $this, 'run_scheduled_project' ) ); // Point to a method in this class

        // Hook for the module-level schedule trigger
        add_action( 'dm_run_module_schedule', array( $this, 'run_scheduled_module' ) ); // New hook

        // Add custom cron schedules
        add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Adds custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['every_5_minutes'] = array(
			'interval' => 300, // 5 * 60 seconds
			'display'  => __( 'Every 5 Minutes', 'data-machine' )
		);
		// Add other custom intervals here if needed
		return $schedules;
	}

	/**
	 * Run scheduled project logic
	 * @param int $project_id The ID of the project to run.
	 */
	public function run_scheduled_project( $project_id ) {
		// This function needs to essentially mimic the logic of handle_run_now,
		// but without user context (like nonce checks, capability checks).
		// It should trigger jobs for all relevant modules in the project.

		error_log("Data Machine Cron: Triggered run_scheduled_project for Project ID: {$project_id}");

		// Get dependencies via locator (we are in the main class)
		$db_projects = $this->locator->get('database_projects');
		$db_modules = $this->locator->get('database_modules');
		$db_jobs = $this->locator->get('database_jobs');

		// 1. Verify project exists and is active
		$project = $db_projects->get_project( $project_id ); // Get project without user check
		if ( ! $project || $project->schedule_status !== 'active' || $project->schedule_interval === 'manual' ) {
			error_log("Data Machine Cron: Project {$project_id} not found, inactive, or set to manual. Aborting scheduled run.");
			// Optional: Clear the schedule if project is gone?
			// wp_clear_scheduled_hook('dm_run_project_schedule', array($project_id));
			return;
		}

		// 2. Fetch associated modules
		// Note: get_modules_for_project requires a user_id for validation.
		// For cron, we might need a way to get modules just by project_id OR
		// associate the cron run with the project owner's user_id.
		// Let's assume we run as the project owner for now.
		$user_id = $project->user_id;
		$modules = $db_modules->get_modules_for_project( $project_id, $user_id );
		if ( empty( $modules ) ) {
			error_log("Data Machine Cron: Project {$project_id} has no modules to run.");
			return;
		}

		// 3. Trigger jobs (similar logic to handle_run_now)
		$jobs_created_count = 0;
		$dashboard_ajax_handler = $this->locator->get('dashboard_ajax_handler'); // Get the handler containing the helper

		foreach ( $modules as $module ) {
			// Check if module schedule is set to inherit the project schedule
			$module_interval = $module->schedule_interval ?? 'manual'; // Default to manual if null
			$module_status = $module->schedule_status ?? 'active'; // Default to active if null
			
			// IMPORTANT: Assuming 'project_schedule' is the value used for inheritance.
			if ($module_interval !== 'project_schedule') {
				error_log("Data Machine Cron (Project: {$project_id}): Skipping module {$module->module_id} as its schedule ({$module_interval}) is not 'project_schedule'.");
				continue; // Skip modules not explicitly set to follow the project schedule
			}

			// Also skip if the module itself is paused
			if ($module_status !== 'active') {
				error_log("Data Machine Cron (Project: {$project_id}): Skipping module {$module->module_id} as its status is '{$module_status}'.");
				continue; // Skip paused modules
			}

			// Skip if the module input type is 'files'
			$module_input_type = $module->data_source_type ?? null;
			if ($module_input_type === 'files') {
				error_log("Data Machine Cron (Project: {$project_id}): Skipping module {$module->module_id} as its input type is 'files'.");
				continue; // Skip file input modules
			}

			try {
				// Use the helper method from the AJAX handler class (or move it to a shared location)
				$input_handler = $dashboard_ajax_handler->get_input_handler_for_module( $module ); // Requires making the helper public
				 if (!$input_handler) {
					 throw new Exception("Cron: Could not load input handler for type: {$module->data_source_type}");
				 }
				$source_config = json_decode(wp_unslash($module->data_source_config), true) ?: [];
				$fetched_data = $input_handler->get_input_data(['module_id' => $module->module_id], [], $source_config, $user_id);

				// Check for errors or skip conditions returned directly
				 if ( is_wp_error($fetched_data) || (isset($fetched_data['message']) && $fetched_data['message'] === 'no_input_data') || isset($fetched_data['error']) ) {
					 $skip_reason = is_wp_error($fetched_data) ? $fetched_data->get_error_message() : ($fetched_data['message'] ?? ($fetched_data['error'] ? 'Error from input handler' : 'Unknown reason'));
					 error_log("Data Machine Cron: Skipping job creation for Module ID: {$module->module_id} in Project {$project_id} - Reason: {$skip_reason}");
					 continue; // Skip to next module if no input data for this one
				 }

                 // Prepare the base module config (used for all items from this module)
                 $module_job_config = array(
                    'module_id' => $module->module_id,
                    'project_id' => $module->project_id,
                    'output_type' => $module->output_type,
                    'output_config' => json_decode(wp_unslash($module->output_config), true) ?: [],
                    'process_data_prompt' => $module->process_data_prompt,
                    'fact_check_prompt' => $module->fact_check_prompt,
                    'finalize_response_prompt' => $module->finalize_response_prompt,
                 );
                 $module_config_json = wp_json_encode($module_job_config);
                 if ($module_config_json === false) {
                     throw new Exception('Cron: Failed to serialize base module config for module ' . $module->module_id . '. Error: ' . json_last_error_msg());
                 }

                 // --- Handle single item vs multiple items ---
                 $items_to_process = [];
                 if (is_array($fetched_data) && isset($fetched_data[0]) && (is_array($fetched_data[0]) || is_object($fetched_data[0]))) {
                     $items_to_process = $fetched_data;
                 } elseif (is_array($fetched_data) || is_object($fetched_data)) { // Assume it's a single item packet
                     $items_to_process[] = $fetched_data;
                 } else {
                     throw new Exception("Cron: Unexpected data format received from input handler for module {$module->module_id} in Project {$project_id}.");
                 }

                 $module_jobs_created = 0;
                 foreach ($items_to_process as $item_data_packet) {
                     try {
                         $input_data_json = wp_json_encode($item_data_packet); // Encode SINGLE item packet
                         if ($input_data_json === false) {
                             throw new Exception('Cron: Failed to serialize single item data for module ' . $module->module_id . '. Error: ' . json_last_error_msg());
                         }

                         $job_id = $db_jobs->create_job($module->module_id, $user_id, $module_config_json, $input_data_json);
                         if ( false === $job_id ) { throw new Exception('Cron: Failed to create database job record for item in module ' . $module->module_id . '.'); }

                         $scheduled = wp_schedule_single_event( time(), 'dm_run_job_event', array( 'job_id' => $job_id ) );
                         if ( false === $scheduled ) { throw new Exception('Cron: Failed to schedule single job event for item in module ' . $module->module_id . '.'); }

                         $module_jobs_created++;
                     } catch (Exception $item_exception) {
                         error_log("Data Machine Cron Error creating/scheduling job for one item in module {$module->module_id} (Project {$project_id}): " . $item_exception->getMessage());
                         // Continue processing other items for this module
                     }
                 }
                 $jobs_created_count += $module_jobs_created; // Add to total project count
                 if ($module_jobs_created > 0) {
                    error_log("Data Machine Cron: Scheduled {$module_jobs_created} individual jobs for module {$module->module_id} in Project {$project_id}.");
                 }

			} catch (Exception $e) {
				 error_log("Data Machine Cron Error processing module {$module->module_id} for project {$project_id}: " . $e->getMessage());
				 // Continue to next module
			}
		}
		error_log("Data Machine Cron: Successfully scheduled {$jobs_created_count} jobs for project {$project_id}.");

		// 4. Update the last run timestamp for the project
		if ($jobs_created_count > 0) { // Only update if jobs were actually scheduled
			$this->locator->get('database_projects')->update_project_last_run($project_id);
		}
	}

	/**
	 * Callback function triggered by the module-level WP Cron schedule.
	 *
	 * @param int $module_id The ID of the module to run.
	 */
	public function run_scheduled_module( $module_id ) {
		error_log("Data Machine Cron: Triggered run_scheduled_module for Module ID: {$module_id}");

		// Get dependencies
		$db_modules = $this->locator->get('database_modules');
		$db_jobs = $this->locator->get('database_jobs');
		$dashboard_ajax_handler = $this->locator->get('dashboard_ajax_handler');

		// 1. Get module and verify it should run
		$module = $db_modules->get_module( $module_id ); // Get module details
		if ( ! $module || $module->schedule_status !== 'active' || $module->schedule_interval === 'manual' || $module->schedule_interval === 'project_schedule' ) {
			error_log("Data Machine Cron: Module {$module_id} not found, inactive, or not set for individual schedule. Aborting scheduled run.");
			wp_clear_scheduled_hook('dm_run_module_schedule', array($module_id)); // Clear potentially orphaned hook
			return;
		}

		// Skip if the module input type is 'files' - this shouldn't normally be scheduled, but check anyway
		$module_input_type = $module->data_source_type ?? null;
		if ($module_input_type === 'files') {
			error_log("Data Machine Cron (Module: {$module_id}): Skipping module as its input type is 'files'.");
			// Might also clear the hook again just in case
			wp_clear_scheduled_hook('dm_run_module_schedule', array($module_id));
			return; // Skip file input modules
		}

		// 2. Determine User ID (Project Owner)
		$db_projects = $this->locator->get('database_projects');
		$project = $db_projects->get_project($module->project_id);
		if (!$project) {
			 error_log("Data Machine Cron: Could not find project {$module->project_id} for module {$module_id}.");
			 return;
		}
		$user_id = $project->user_id;

		// 3. Trigger job (similar logic to run_scheduled_project but only for this module)
		try {
			$input_handler = $dashboard_ajax_handler->get_input_handler_for_module( $module );
			 if (!$input_handler) {
				 throw new Exception("Cron: Could not load input handler for type: {$module->data_source_type}");
			 }
			$source_config = json_decode(wp_unslash($module->data_source_config), true) ?: [];
			$fetched_data = $input_handler->get_input_data(['module_id' => $module->module_id], [], $source_config, $user_id);

			// Check for errors or skip conditions returned directly
			if ( is_wp_error($fetched_data) || (isset($fetched_data['message']) && $fetched_data['message'] === 'no_input_data') || isset($fetched_data['error']) ) {
				 $skip_reason = is_wp_error($fetched_data) ? $fetched_data->get_error_message() : ($fetched_data['message'] ?? ($fetched_data['error'] ? 'Error from input handler' : 'Unknown reason'));
				 error_log("Data Machine Cron: Skipping job creation for Module ID: {$module->module_id} - Reason: {$skip_reason}");
				 return;
			 }

             // Prepare the base module config (used for all items)
             $module_job_config = array(
                'module_id' => $module->module_id,
                'project_id' => $module->project_id,
                'output_type' => $module->output_type,
                'output_config' => json_decode(wp_unslash($module->output_config), true) ?: [],
                'process_data_prompt' => $module->process_data_prompt,
                'fact_check_prompt' => $module->fact_check_prompt,
                'finalize_response_prompt' => $module->finalize_response_prompt,
             );
             $module_config_json = wp_json_encode($module_job_config);
             if ($module_config_json === false) {
                 throw new Exception('Cron: Failed to serialize base module config. Error: ' . json_last_error_msg());
             }

             // --- Handle single item vs multiple items --- 
             $items_to_process = [];
             // Check if it looks like an array of items (numeric keys, values are arrays/objects)
             if (is_array($fetched_data) && isset($fetched_data[0]) && (is_array($fetched_data[0]) || is_object($fetched_data[0]))) {
                 $items_to_process = $fetched_data;
             } elseif (is_array($fetched_data) || is_object($fetched_data)) { // Assume it's a single item packet
                 $items_to_process[] = $fetched_data;
             } else {
                 // Unexpected format from get_input_data
                 throw new Exception("Cron: Unexpected data format received from input handler for module {$module->module_id}.");
             }

             $jobs_created_count = 0;
             foreach ($items_to_process as $item_data_packet) {
                 try {
                     $input_data_json = wp_json_encode($item_data_packet); // Encode SINGLE item packet
                     if ($input_data_json === false) {
                         throw new Exception('Cron: Failed to serialize single item data. Error: ' . json_last_error_msg());
                     }

                     $job_id = $db_jobs->create_job($module->module_id, $user_id, $module_config_json, $input_data_json);
                     if ( false === $job_id ) { throw new Exception('Cron: Failed to create database job record for item.'); }

                     $scheduled = wp_schedule_single_event( time(), 'dm_run_job_event', array( 'job_id' => $job_id ) );
                     if ( false === $scheduled ) { throw new Exception('Cron: Failed to schedule single job event for item.'); }

                     $jobs_created_count++;
                 } catch (Exception $item_exception) {
                     // Log error for this specific item but continue with others
                     error_log("Data Machine Cron Error creating/scheduling job for one item in module {$module->module_id}: " . $item_exception->getMessage());
                 }
             }
             error_log("Data Machine Cron: Scheduled {$jobs_created_count} individual jobs for module {$module->module_id}.");

		} catch (Exception $e) {
			 error_log("Data Machine Cron Error processing module {$module->module_id}: " . $e->getMessage());
			 return; // Exit if main processing fails before item loop
		}
	}

	/**
	 * Plugin activation hook.
	 * @since    0.2.0
	 */
	public static function activate() {
		// Add option if it doesn't exist
		if ( false === get_option( 'Data_Machine_options' ) ) {
			add_option( 'Data_Machine_options', array() );
		}
		
		// Ensure DB classes are loaded for table creation
		require_once DATA_MACHINE_PATH . 'includes/database/class-database-modules.php'; // Keep these for static create_table calls
		require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
		require_once DATA_MACHINE_PATH . 'includes/database/class-database-jobs.php';
		require_once DATA_MACHINE_PATH . 'includes/class-service-locator.php'; // Ensure Locator class is loaded

		// Create/Update tables using static methods
		Data_Machine_Database_Modules::create_table();
		Data_Machine_Database_Projects::create_table();
		Data_Machine_Database_Jobs::create_table();

		// Add an option to track the version, useful for future upgrades
		add_option( 'Data_Machine_db_version', DATA_MACHINE_VERSION );

		// Create and setup the service locator for creating defaults
		$locator = new Data_Machine_Service_Locator();
		
		// Register the necessary services needed by create_default_project_and_module
		$locator->register('database_projects', function($locator) {
			// Class is already required above in activate()
			return new Data_Machine_Database_Projects();
		});
		$locator->register('database_modules', function($locator) {
			// Class is already required above in activate()
			return new Data_Machine_Database_Modules($locator); // Pass locator
		});
	}

	/**
	 * Plugin deactivation hook.
	 * @since    0.2.0
	 */
	public static function deactivate() {
		// Add deactivation tasks here if needed
	}

} // End Class Data_Machine
