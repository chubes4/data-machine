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

namespace DataMachine;

use DataMachine\Admin\{AdminPage, AdminMenuAssets};
use DataMachine\Admin\ModuleConfig\{RegisterSettings, ModuleConfigHandler};
use DataMachine\Admin\RemoteLocations\FormHandler as RemoteLocationsFormHandler;
use DataMachine\Database\Modules;
use DataMachine\Helpers\Logger;

// PSR-4 autoloading handles class loading - no manual includes needed

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * The main plugin class.
 */
class DataMachine {

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
	 * @var      \DataMachine\Admin\ModuleConfig\RegisterSettings    $register_settings    Settings registration class instance.
	 */
	public $register_settings;

    /**
     * Admin Page class instance.
     * @since    0.1.0
     * @var      \DataMachine\Admin\AdminPage    $admin_page    Admin Page class instance.
     */
    public $admin_page;


	/**
	 * FactCheck API class instance.
	 * @since    0.1.0
	 * @var      \DataMachine\Api\FactCheck    $factcheck_api    FactCheck API class instance.
	 */
	public $factcheck_api;

	/**
	 * Finalize API class instance.
	 * @since    0.1.0
	 * @var      \DataMachine\Api\Finalize    $finalize_api    Finalize API class instance.
	 */
	public $finalize_api;

	/**
	 * Process Data class instance.
	 * @since    0.1.0
	 * @var      \DataMachine\Engine\ProcessData    $process_data    Process Data class instance.
	 */
	public $process_data;

	/**
	 * Database Modules class instance.
	 * @since    0.2.0
	 * @var      Modules    $db_modules    Database Modules class instance.
	 */
	public $db_modules;

	/**
	 * Processing Orchestrator class instance.
	 * @since    0.7.0
	 * @var      \DataMachine\Engine\ProcessingOrchestrator    $orchestrator    Orchestrator class instance.
	 */
	public $orchestrator; // Added property



	/**
	 * Database Remote Locations class instance.
	 * @var RemoteLocations
	 */
	public $db_remote_locations;

	/**
	 * Logger instance.
	 * @var Logger
	 */
	public $logger;

	/**
	 * Twitter OAuth handler instance.
	 * @var \DataMachine\Admin\OAuth\Twitter
	 */
	public $oauth_twitter;

	   /**
	 * Threads OAuth handler instance.
	 * @var \DataMachine\Admin\OAuth\Threads
	 */
	public $oauth_threads;

	   /**
	 * Facebook OAuth handler instance.
	 * @var \DataMachine\Admin\OAuth\Facebook
	 */
	public $oauth_facebook;

	/**
	 * Reddit OAuth handler instance.
	 * @var \DataMachine\Admin\OAuth\Reddit
	 */
	public $oauth_reddit;

	/**
	 * Initialize the class and set its properties.
	 * @since    0.1.0
	 */
	// Streamlined constructor with only needed dependencies
	public function __construct(
		$version,
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
	) {
		$this->version = $version;
		$this->register_settings = $register_settings;
		$this->admin_page = $admin_page;
		$this->factcheck_api = $factcheck_api;
		$this->finalize_api = $finalize_api;
		$this->process_data = $process_data;
		$this->db_modules = $db_modules;
		$this->orchestrator = $orchestrator;
		$this->db_remote_locations = $db_remote_locations;
		$this->logger = $logger;
		$this->oauth_twitter = $oauth_twitter;
		$this->oauth_threads = $oauth_threads;
		$this->oauth_facebook = $oauth_facebook;
		$this->oauth_reddit = $oauth_reddit;
		// Register hooks for OAuth handlers
		$oauth_reddit->register_hooks();
		$oauth_twitter->register_hooks();
		$oauth_threads->register_hooks();
		$oauth_facebook->register_hooks();
	}

	/**
	 * Define the core functionality of the plugin.
	 * @since    0.1.0
	 */
	public function run() {
		// Instantiate and initialize admin menu/assets handler
		$admin_menu_assets = new AdminMenuAssets(
			$this->version,
			$this->admin_page,
			$this->db_modules
		);
		$admin_menu_assets->init_hooks();

		// Initialize settings registration handler
		$register_settings = new RegisterSettings($this->version);
		$register_settings->init_hooks();

		// Initialize module handler
		$module_handler = new ModuleConfigHandler(
			$this->db_modules,
			$this->admin_page->handler_factory,
			$this->logger
		);
		$module_handler->init_hooks();

		// Remove legacy/placeholder null assignments for AJAX handlers and others
		// All dependencies should be injected or instantiated as needed

		// (AJAX handler hooks and scheduler logic should be handled in the main bootstrap, not here)

		// Note: Prompt Builder is instantiated in main bootstrap file and injected into orchestrator
	}

	/**
	 * Plugin activation hook.
	 * @since    0.2.0
	 */
	public static function activate() {
		// Add option if it doesn't exist
		if ( false === get_option( 'dm_options' ) ) {
			add_option( 'dm_options', array() );
		}
		
		// DB classes loaded via autoloading for table creation

		// Create/Update tables using static methods
		Modules::create_table();
		Projects::create_table();
		Jobs::create_table();

		// Add an option to track the version, useful for future upgrades
		add_option( 'dm_db_version', DATA_MACHINE_VERSION );
	}

	/**
	 * Plugin deactivation hook.
	 * @since    0.2.0
	 */
	public static function deactivate() {
		// Add deactivation tasks here if needed
	}

} // End Class Data_Machine
