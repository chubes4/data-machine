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
require_once plugin_dir_path( __FILE__ ) . 'engine/class-process-data.php';
require_once plugin_dir_path( __FILE__ ) . 'database/class-database-modules.php'; // Updated path
require_once DATA_MACHINE_PATH . 'includes/engine/class-processing-orchestrator.php'; // Ensuring this path is correct
require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-output-publish_local.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-output-publish_remote.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/output/class-data-machine-output-data_export.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-input-files.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-input-airdrop_rest_api.php'; // Renamed from rest-api
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-input-public_rest_api.php'; // Added new public handler
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-input-rss.php';
require_once DATA_MACHINE_PATH . 'includes/handlers/input/class-data-machine-input-reddit.php';

use Data_Machine\Includes\Database\Data_Machine_Database_Modules;
use Data_Machine\Includes\Database\Data_Machine_Database_Projects;
use Data_Machine\Includes\Logging\Data_Machine_Logger; // Assuming logger namespace

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
	 * Database Remote Locations class instance.
	 * @var Data_Machine_Database_Remote_Locations
	 */
	public $db_remote_locations;

	/**
	 * Logger instance.
	 * @var Data_Machine_Logger
	 */
	public $logger;

	/**
	 * Twitter OAuth handler instance.
	 * @var Data_Machine_OAuth_Twitter
	 */
	public $oauth_twitter;

	   /**
	 * Threads OAuth handler instance.
	 * @var Data_Machine_OAuth_Threads
	 */
	public $oauth_threads;

	   /**
	 * Facebook OAuth handler instance.
	 * @var Data_Machine_OAuth_Facebook
	 */
	public $oauth_facebook;

	/**
	 * Initialize the class and set its properties.
	 * @since    0.1.0
	 */
	// Refactored constructor to accept all dependencies directly
	public function __construct(
		$version,
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
		      $oauth_threads,  // Added
		      $oauth_facebook, // Added
		$db_remote_locations,
		$logger
	) {
		$this->version = $version;
		$this->register_settings = $register_settings;
		$this->admin_page = $admin_page;
		$this->openai_api = $openai_api;
		$this->factcheck_api = $factcheck_api;
		$this->finalize_api = $finalize_api;
		$this->process_data = $process_data;
		$this->db_modules = $db_modules;
		$this->orchestrator = $orchestrator;
		$this->output_publish_local = $output_publish_local;
		$this->output_publish_remote = $output_publish_remote;
		$this->output_data_export = $output_data_export;
		$this->input_files = $input_files;
		$this->db_remote_locations = $db_remote_locations;
		$this->logger = $logger;
		$this->oauth_twitter = $oauth_twitter; // Corrected potential typo if present
		$this->oauth_threads = $oauth_threads;   // Ensure assignment
		$this->oauth_facebook = $oauth_facebook; // Ensure assignment
		// Register hooks for OAuth handlers
		$oauth_reddit->register_hooks();
		$oauth_twitter->register_hooks();
		$oauth_threads->register_hooks();  // Ensure hook registration
		$oauth_facebook->register_hooks(); // Ensure hook registration
	}

	/**
	 * Define the core functionality of the plugin.
	 * @since    0.1.0
	 */
	public function run() {
		// Instantiate and initialize admin menu/assets handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-data-machine-admin-menu-assets.php';
		$admin_menu_assets = new Data_Machine_Admin_Menu_Assets(
			$this->version,
			$this->admin_page,
			$this->db_modules
		);
		$admin_menu_assets->init_hooks();

		// Initialize settings registration handler
		require_once DATA_MACHINE_PATH . 'admin/module-config/RegisterSettings.php';
		$register_settings = new Data_Machine_Register_Settings($this->version);
		$register_settings->init_hooks();

		// Initialize module handler
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/module-config/class-dm-module-config-handler.php';
		$module_handler = new Data_Machine_Module_Handler(
			$this->db_modules,
			$this->admin_page->handler_registry,
			$this->admin_page->handler_factory,
			$this->logger
		);
		$module_handler->init_hooks();

		// Remove legacy/placeholder null assignments for AJAX handlers and others
		// All dependencies should be injected or instantiated as needed

		// (AJAX handler hooks and scheduler logic should be handled in the main bootstrap, not here)

		// Note: Prompt Builder is instantiated in main bootstrap file and injected into orchestrator

		// Remote locations handler (if needed elsewhere, move to bootstrap)
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/remote-locations/class-data-machine-remote-locations-form-handler.php';
		$remote_locations = new Data_Machine_Remote_Locations_Form_Handler($this->db_remote_locations, $this->logger);

		// Remove legacy/placeholder null assignments for AJAX handlers and others
		// All dependencies should be injected or instantiated as needed

		// (AJAX handler hooks and scheduler logic should be handled in the main bootstrap, not here)
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

		// Create/Update tables using static methods
		Data_Machine_Database_Modules::create_table();
		Data_Machine_Database_Projects::create_table();
		Data_Machine_Database_Jobs::create_table();

		// Add an option to track the version, useful for future upgrades
		add_option( 'Data_Machine_db_version', DATA_MACHINE_VERSION );
	}

	/**
	 * Plugin deactivation hook.
	 * @since    0.2.0
	 */
	public static function deactivate() {
		// Add deactivation tasks here if needed
	}

} // End Class Data_Machine
