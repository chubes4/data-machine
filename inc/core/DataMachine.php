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

namespace DataMachine\Core;

use DataMachine\Admin\{AdminPage, AdminMenuAssets};
use DataMachine\Admin\RemoteLocations\FormHandler as RemoteLocationsFormHandler;
use DataMachine\Database\{Modules, Projects, Jobs};
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
	 * Settings are now handled through the pipeline configuration system.
	 */

    /**
     * Admin Page class instance.
     * @since    0.1.0
     * @var      \DataMachine\Admin\AdminPage    $admin_page    Admin Page class instance.
     */
    public $admin_page;



	// ProcessData class removed - functionality moved to ProcessStep

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
	 * Initialize the class using pure filter-based architecture.
	 * Parameter-less constructor - all services accessed via ultra-direct filters.
	 * @since    0.1.0
	 */
	public function __construct() {
		$this->version = DATA_MACHINE_VERSION;
		
		// Pure filter-based architecture - all services accessed via ultra-direct filters
		// Settings are handled through pipeline configuration
		$this->admin_page = apply_filters('dm_get_admin_page', null);
		$this->db_modules = apply_filters('dm_get_db_modules', null);
		$this->orchestrator = apply_filters('dm_get_orchestrator', null);
		$this->db_remote_locations = apply_filters('dm_get_db_remote_locations', null);
		$this->logger = apply_filters('dm_get_logger', null);
		
		// OAuth handlers now register their own hooks via the filter system
	}

	/**
	 * Define the core functionality of the plugin using pure filter-based architecture.
	 * @since    0.1.0
	 */
	public function run() {
		// Pure filter-based architecture - all services accessed via ultra-direct filters
		$admin_menu_assets = apply_filters('dm_get_admin_menu_assets', null);
		if ($admin_menu_assets) {
			$admin_menu_assets->init_hooks();
		}

		// Settings are handled through the pipeline configuration system
		// if ($this->register_settings) {
		//     $this->register_settings->init_hooks();
		// } - REMOVED

		// Module configuration is handled through the pipeline system
		// $module_handler = apply_filters('dm_get_module_config_handler', null); - REMOVED
		// if ($module_handler) {
		//     $module_handler->init_hooks();
		// } - REMOVED

		// All dependencies accessed via filters for pure filter-based architecture
		// AJAX handler hooks and scheduler logic handled via filter-based services
		// Prompt Builder accessed via dm_get_prompt_builder filter when needed
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
