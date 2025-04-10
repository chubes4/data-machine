<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 */

/**
 * The admin-specific functionality of the plugin.
 */
class Data_Machine_Admin_Page {

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * Database Modules class instance.
     *
     * @since    0.2.0
     * @access   private
     * @var      Data_Machine_Database_Modules    $db_modules    Database Modules class instance.
     */
    private $db_modules;

    /**
     * Database Projects class instance.
     *
     * @since    0.13.0
     * @access   private
     * @var      Data_Machine_Database_Projects   $db_projects   Database Projects class instance.
     */
    private $db_projects;

    /**
     * Service Locator instance.
     *
     * @since    0.14.0 // Or current version
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string                                   $version         The plugin version.
     * @param    Data_Machine_Database_Modules    $db_modules Injected DB Modules instance.
     * @param    Data_Machine_Database_Projects   $db_projects Injected DB Projects instance.
     * @param    Data_Machine_Service_Locator     $locator     Injected Service Locator instance.
     */
    public function __construct( $version, Data_Machine_Database_Modules $db_modules, Data_Machine_Database_Projects $db_projects, Data_Machine_Service_Locator $locator ) {
        $this->version = $version;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->locator = $locator; // Store locator
      
    }

    /**
     * Display the main admin page content.
     */
    public function display_admin_page() {
        // Make locator available to the included template file
        $locator = $this->locator; 
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/main-admin-page.php';
    }

    /**
     * Display the settings page content by including the template file.
     */
    public function display_settings_page() {
        // Make locator available to the included template file
        $locator = $this->locator;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/settings-page.php';
    }

    /**
     * Display the project dashboard page content.
     */
    public function display_project_dashboard_page() {
        // Make DB instances available to the included template file
        $db_projects = $this->db_projects;
        $db_modules = $this->db_modules;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/project-dashboard-page.php';
    }

    /**
     * Display the API Keys settings page.
     *
     * @since NEXT_VERSION
     */
    public function display_api_keys_page() {
        // Security check: Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        // Display the settings page content
        // Load the template file
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/api-keys-page.php';
    }

    /**
     * Renders the Remote Locations admin page content by loading templates.
     */
    public function display_remote_locations_page() {
        // Get or instantiate the Remote Locations handler
        $remote_locations_handler = $this->locator->get('remote_locations');
        
        // If the handler isn't in the service locator, instantiate it
        if (!$remote_locations_handler) {
            require_once plugin_dir_path(__FILE__) . 'class-data-machine-remote-locations.php';
            $remote_locations_handler = new Data_Machine_Remote_Locations($this->locator);
        }
        
        // Delegate the display logic to the specialized class
        $remote_locations_handler->display_page();
    }
   
   } // End class
