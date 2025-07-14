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
     * Logger instance.
     * @var Data_Machine_Logger
     * @since NEXT_VERSION
     */
    private $logger;

    /**
     * Handler Registry instance.
     * @var Data_Machine_Handler_Registry
     * @since NEXT_VERSION
     */
    public $handler_registry;

    /**
     * Settings Fields service instance.
     * @var Data_Machine_Settings_Fields
     * @since NEXT_VERSION
     */
    private $settings_fields;

    /**
     * Handler Factory instance.
     * @var Data_Machine_Handler_Factory
     * @since NEXT_VERSION
     */
    public $handler_factory;

    /**
     * Remote Locations Admin handler instance.
     * @var Data_Machine_Remote_Locations
     * @since NEXT_VERSION
     */
    private $remote_locations_admin;

    /**
     * Module Config Handler instance.
     * @var Data_Machine_Module_Handler
     * @since NEXT_VERSION
     */
    private $module_config_handler;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string                                   $version                The plugin version.
     * @param    Data_Machine_Database_Modules            $db_modules             Injected DB Modules instance.
     * @param    Data_Machine_Database_Projects           $db_projects            Injected DB Projects instance.
     * @param    Data_Machine_Logger                      $logger                 Injected Logger instance.
     * @param    Data_Machine_Handler_Registry            $handler_registry       Injected Handler Registry instance.
     * @param    Data_Machine_Settings_Fields             $settings_fields        Injected Settings Fields instance.
     * @param    Data_Machine_Handler_Factory             $handler_factory        Injected Handler Factory instance.
     * @param    Data_Machine_Remote_Locations            $remote_locations_admin Injected Remote Locations Admin instance.
     */
    public function __construct(
        $version,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Logger $logger,
        Data_Machine_Handler_Registry $handler_registry,
        Data_Machine_Settings_Fields $settings_fields,
        Data_Machine_Handler_Factory $handler_factory,
        Data_Machine_Remote_Locations $remote_locations_admin
    ) {
        $this->version = $version;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->logger = $logger;
        $this->handler_registry = $handler_registry;
        $this->settings_fields = $settings_fields;
        $this->handler_factory = $handler_factory;
        $this->remote_locations_admin = $remote_locations_admin;
        // Instantiate the module config handler with all required dependencies
        require_once plugin_dir_path(__FILE__) . '../module-config/class-dm-module-config-handler.php';
        $this->module_config_handler = new Data_Machine_Module_Handler($db_modules, $handler_registry, $handler_factory, $logger);
        // Hook for project management page (if any form processing is needed in future)
        add_action( 'load-dm-run-single-module_page_dm-project-management', array( $this, 'process_project_management_page' ) );
        add_action( 'load-data-machine_page_dm-project-management', array( $this, 'process_project_management_page' ) );
        // Hook for API keys page (if any form processing is needed in future)
        add_action( 'load-dm-run-single-module_page_dm-api-keys', array( $this, 'process_api_keys_page' ) );
        add_action( 'load-data-machine_page_dm-api-keys', array( $this, 'process_api_keys_page' ) );
        // Hook for remote locations page (if any form processing is needed in future)
        add_action( 'load-dm-run-single-module_page_dm-remote-locations', array( $this, 'process_remote_locations_page' ) );
        add_action( 'load-data-machine_page_dm-remote-locations', array( $this, 'process_remote_locations_page' ) );
        // Hook for jobs page (if any form processing is needed in future)
        add_action( 'load-dm-run-single-module_page_dm-jobs', array( $this, 'process_jobs_page' ) );
        add_action( 'load-data-machine_page_dm-jobs', array( $this, 'process_jobs_page' ) );
    }

    /**
     * Display the main admin page content.
     */
    public function display_admin_page() {
        // Make required services available to the included template file
        $db_projects = $this->db_projects;
        $db_modules = $this->db_modules;
        // Note: $logger is not explicitly used in the template logic, but $db_modules needs it.
        // Since $db_modules is already instantiated correctly with the logger, we don\'t need to pass $logger itself.
        
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/run-single-module-page.php';
    }

    /**
     * Display the settings page content by including the template file.
     */
    public function display_settings_page() {
        // Dependencies
        $handler_registry = $this->handler_registry;
        $db_projects = $this->db_projects;
        $handler_factory = $this->handler_factory;
        $db_modules = $this->db_modules;

        // Get handler lists
        $input_handlers = $handler_registry->get_input_handlers();
        $output_handlers = $handler_registry->get_output_handlers();

        // Get available projects for the current user
        $user_id = get_current_user_id();
        $projects = $db_projects ? $db_projects->get_projects_for_user($user_id) : [];

        // All fetched variables ($handler_registry, $db_projects,
        // $db_modules, $input_handlers, $output_handlers, $projects, $user_id)
        // are available to the included template.
        include_once plugin_dir_path( __FILE__ ) . 'templates/module-config-page.php';
    }

    /**
     * Display the project management page content.
     */
    public function display_project_management_page() {
        // Make DB instances available to the included template file
        $db_projects = $this->db_projects;
        $db_modules = $this->db_modules;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/project-management-page.php';
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
        $logger = $this->logger;
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/api-keys-page.php';
    }

    /**
     * Renders the Remote Locations admin page content by loading templates.
     */
    public function display_remote_locations_page() {
        // Ensure the capability is checked before displaying the page
        if (!current_user_can('manage_options')) { // Adjust capability as needed
            wp_die(__( 'Sorry, you are not allowed to access this page.' ));
        }
        $remote_locations_handler = $this->remote_locations_admin; // Use injected property

        // Call the method from the injected handler to display the page content
        $remote_locations_handler->display_page();
    }

    /**
     * Renders the Jobs List page.
     *
     * @since NEXT_VERSION
     */
    public function display_jobs_page() {
        // Security check
        if (!current_user_can('manage_options')) { // Adjust capability as needed
            wp_die(__( 'Permission denied.', 'data-machine' ));
        }

        // Ensure the List Table class file is loaded
        $list_table_file = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/class-data-machine-jobs-list-table.php';
        if (file_exists($list_table_file)) {
            require_once $list_table_file;
        } else {
            // Handle error - class file missing
            echo '<div class="error"><p>' . __( 'Error: Jobs List Table class file not found.', 'data-machine' ) . '</p></div>';
            return;
        }

        // Create an instance of our package class...
        $jobs_list_table = new Data_Machine_Jobs_List_Table();
        // Fetch, prepare, sort, and filter our data...
        $jobs_list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post">
                <?php // Maybe add nonce fields here if we add bulk actions later ?>
                <?php $jobs_list_table->display(); ?>
            </form>
        </div>
        <?php
    }



    // Stub methods for future form processing on other admin pages
    public function process_project_management_page() {}
    public function process_api_keys_page() {}
    public function process_remote_locations_page() {}
    public function process_jobs_page() {}
}
