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
require_once DATA_MACHINE_PATH . 'includes/engine/class-processing-orchestrator.php'; // Ensuring this path is correct
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
require_once DATA_MACHINE_PATH . 'includes/engine/class-job-executor.php'; // ADDED: Job Executor
require_once DATA_MACHINE_PATH . 'includes/class-service-locator.php';


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

		// Register hooks for OAuth handlers
		$this->locator->get('oauth_reddit')->register_hooks();
		// TODO: Add Instagram hook registration once its handler is refactored
		// $this->locator->get('oauth_instagram')->register_hooks(); 
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
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/settings/class-data-machine-register-settings.php';
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

		// Instantiate the Job Worker (Ideally, register in Locator)
		require_once DATA_MACHINE_PATH . 'includes/engine/class-job-worker.php'; // Ensure class is loaded
		$job_worker = new Data_Machine_Job_Worker($this->locator);

		// Hook Module AJAX actions
		add_action( 'wp_ajax_process_data', array( $module_ajax_handler, 'process_data_source_ajax_handler' ) );
		add_action( 'wp_ajax_dm_get_module_data', array( $module_ajax_handler, 'get_module_data_ajax_handler' ) );
		add_action( 'wp_ajax_dm_check_job_status', array( $module_ajax_handler, 'dm_check_job_status_ajax_handler' ) );
		add_action( 'wp_ajax_dm_save_module', array( $module_ajax_handler, 'save_module_ajax_handler' ) );

		// Hook Dashboard Project AJAX actions
		add_action( 'wp_ajax_dm_run_now', array( $dashboard_ajax_handler, 'handle_run_now' ) );
		add_action( 'wp_ajax_dm_edit_schedule', array( $dashboard_ajax_handler, 'handle_edit_schedule' ) );
		add_action( 'wp_ajax_dm_get_project_schedule_data', array( $dashboard_ajax_handler, 'handle_get_project_schedule_data' ) );

		// Hook for our custom cron job - NOW using Job Worker
		$job_executor = $this->locator->get('job_executor');
		if ($job_executor) {
			add_action( 'dm_run_job_event', array( $job_executor, 'run_scheduled_job' ), 10, 1 ); // Pass 1 argument (job_id)
		} else {
			// Log critical error if executor isn't available
			$logger = $this->locator->get('logger');
			if ($logger) {
				$logger->critical("Job Executor service not found during hook registration.");
			}
			// Optionally trigger a WordPress admin notice or log to PHP error log
			// error_log("CRITICAL: Data Machine Job Executor service failed to load.");
		}

		// Initialize Scheduler hooks
		$scheduler = $this->locator->get('scheduler');
		$scheduler->init_hooks();
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
