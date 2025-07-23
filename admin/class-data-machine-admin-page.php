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
     * @var Dependency_Injection_Handler_Factory
     * @since NEXT_VERSION
     */
    public $handler_factory;

    /**
     * Remote Locations Form Handler instance.
     * @var Data_Machine_Remote_Locations_Form_Handler
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
     * @param    Dependency_Injection_Handler_Factory             $handler_factory        Injected Handler Factory instance.
     * @param    Data_Machine_Remote_Locations_Form_Handler $remote_locations_admin Injected Remote Locations Form Handler instance.
     */
    public function __construct(
        $version,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Logger $logger,
        Data_Machine_Handler_Registry $handler_registry,
        Data_Machine_Settings_Fields $settings_fields,
        Dependency_Injection_Handler_Factory $handler_factory,
        Data_Machine_Remote_Locations_Form_Handler $remote_locations_admin
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
        require_once plugin_dir_path(__FILE__) . 'module-config/class-dm-module-config-handler.php';
        $this->module_config_handler = new Data_Machine_Module_Handler($db_modules, $handler_registry, $handler_factory, $logger);
        // Hook for project management page (if any form processing is needed in future)
        add_action( 'load-data-machine_page_dm-project-management', array( $this, 'process_project_management_page' ) );
        // Hook for API keys page (if any form processing is needed in future)
        add_action( 'load-data-machine_page_dm-api-keys', array( $this, 'process_api_keys_page' ) );
        // Hook for remote locations page (if any form processing is needed in future)
        add_action( 'load-data-machine_page_dm-remote-locations', array( $this, 'process_remote_locations_page' ) );
        // Hook for jobs page (if any form processing is needed in future)
        add_action( 'load-data-machine_page_dm-jobs', array( $this, 'process_jobs_page' ) );
        
        // Admin post handlers for log management
        add_action( 'admin_post_dm_update_log_level', array( $this, 'handle_update_log_level' ) );
        add_action( 'admin_post_dm_clear_logs', array( $this, 'handle_clear_logs' ) );
        
        // AJAX handler for log refresh
        add_action( 'wp_ajax_dm_refresh_logs', array( $this, 'handle_refresh_logs_ajax' ) );
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
        include_once plugin_dir_path( __FILE__ ) . 'page-templates/module-config-page.php';
    }

    /**
     * Display the project management page content.
     */
    public function display_project_management_page() {
        // Make DB instances available to the included template file
        $db_projects = $this->db_projects;
        $db_modules = $this->db_modules;
        // Load the template file
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/page-templates/project-management-page.php';
    }

    /**
     * Display the API Keys settings page.
     *
     * @since NEXT_VERSION
     */
    public function display_api_keys_page() {
        // Security check: Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'data-machine'));
        }



        // Display the settings page content
        $logger = $this->logger;
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/page-templates/api-keys-page.php';
    }

    /**
     * Renders the Remote Locations admin page content by loading templates.
     */
    public function display_remote_locations_page() {
        // Ensure the capability is checked before displaying the page
        if (!current_user_can('manage_options')) { // Adjust capability as needed
            wp_die(__( 'Sorry, you are not allowed to access this page.', 'data-machine' ));
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
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'data-machine'));
        }

        // Make logger instance available to the template
        $logger = $this->logger;
        
        // Load the template file
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/page-templates/jobs.php';
    }



    // Stub methods for future form processing on other admin pages
    public function process_project_management_page() {}
    public function process_api_keys_page() {}
    public function process_remote_locations_page() {}
    public function process_jobs_page() {}

    /**
     * Handle log level update form submission.
     */
    public function handle_update_log_level() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'data-machine'));
        }

        if (!wp_verify_nonce($_POST['dm_log_level_nonce'] ?? '', 'dm_update_log_level')) {
            wp_die(__('Security check failed.', 'data-machine'));
        }

        $new_log_level = sanitize_key($_POST['dm_log_level'] ?? 'info');
        $available_levels = Data_Machine_Logger::get_available_log_levels();

        if (!array_key_exists($new_log_level, $available_levels)) {
            $this->logger->add_admin_error('Invalid log level selected.');
        } else {
            update_option('dm_log_level', $new_log_level);
            $this->logger->add_admin_success('Log level updated successfully to: ' . $available_levels[$new_log_level]);
        }

        // Redirect back to logs tab
        wp_redirect(admin_url('admin.php?page=dm-jobs&tab=logs'));
        exit;
    }

    /**
     * Handle clear logs form submission.
     */
    public function handle_clear_logs() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'data-machine'));
        }

        if (!wp_verify_nonce($_POST['dm_clear_logs_nonce'] ?? '', 'dm_clear_logs')) {
            wp_die(__('Security check failed.', 'data-machine'));
        }

        if ($this->logger->clear_logs()) {
            $this->logger->add_admin_success('Log files cleared successfully.');
        } else {
            $this->logger->add_admin_error('Failed to clear some log files.');
        }

        // Redirect back to logs tab
        wp_redirect(admin_url('admin.php?page=dm-jobs&tab=logs'));
        exit;
    }

    /**
     * Handle AJAX request to refresh logs.
     */
    public function handle_refresh_logs_ajax() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_refresh_logs')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        try {
            $recent_logs = $this->logger->get_recent_logs(50);
            wp_send_json_success(['logs' => $recent_logs]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Failed to retrieve logs: ' . $e->getMessage()]);
        }
    }
}
