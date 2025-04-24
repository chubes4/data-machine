class Data_Machine_Module_Config_Ajax {
    // ... existing properties ...

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Modules $db_modules Modules DB service.
     * @param Data_Machine_Database_Projects $db_projects Projects DB service.
     * @param Data_Machine_Job_Executor $job_executor Job Executor service.
     * @param Data_Machine_Input_Files $input_files_handler Files Input Handler service.
     * @param Data_Machine_Database_Remote_Locations $db_remote_locations Remote Locations DB service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Job_Executor $job_executor,
        Data_Machine_Input_Files $input_files_handler,
        Data_Machine_Database_Remote_Locations $db_remote_locations,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->job_executor = $job_executor;
        $this->input_files_handler = $input_files_handler;
        $this->db_remote_locations = $db_remote_locations;
        $this->logger = $logger;

        // Register AJAX hooks for module configuration page actions
        add_action('wp_ajax_get_project_modules', array($this, 'get_project_modules_ajax_handler'));
        add_action('wp_ajax_get_module_details', array($this, 'get_module_details_ajax_handler'));
        add_action('wp_ajax_save_module_settings', array($this, 'save_module_settings_ajax_handler'));
        add_action('wp_ajax_delete_module', array($this, 'delete_module_ajax_handler'));
        add_action('wp_ajax_get_handler_template', array($this, 'get_handler_template_ajax_handler'));
        add_action('wp_ajax_dm_test_remote_connection', array($this, 'test_remote_connection_ajax_handler'));
        add_action('wp_ajax_dm_save_remote_location', array($this, 'save_remote_location_ajax_handler'));
        add_action('wp_ajax_dm_delete_remote_location', array($this, 'delete_remote_location_ajax_handler'));
        add_action('wp_ajax_dm_get_remote_locations', array($this, 'get_remote_locations_ajax_handler'));
    }

    // ... rest of the class methods ...
} 