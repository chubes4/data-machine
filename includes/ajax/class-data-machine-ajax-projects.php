<?php
/**
 * Handles AJAX requests related to projects from the dashboard.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.13.0 // Or appropriate version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Ajax_Projects {

    private $db_projects;
    private $db_modules;
    private $db_jobs;
    private $locator;

    /**
     * Initialize hooks and dependencies.
     */
    public function __construct(
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Jobs $db_jobs,
        Data_Machine_Service_Locator $locator
    ) {
        $this->db_projects = $db_projects;
        $this->db_modules = $db_modules;
        $this->db_jobs = $db_jobs;
        $this->locator = $locator;
        // Hooks for project dashboard actions
        add_action('wp_ajax_dm_run_now', [$this, 'handle_run_now']);
        add_action('wp_ajax_dm_edit_schedule', [$this, 'handle_edit_schedule']);
        add_action('wp_ajax_dm_get_project_schedule_data', [$this, 'handle_get_project_schedule_data']);
        // Hooks moved from Project_Ajax_Handler
        add_action('wp_ajax_dm_get_project_modules', [$this, 'get_project_modules_ajax_handler']);
        add_action('wp_ajax_dm_create_project', [$this, 'create_project_ajax_handler']);
    }

    /**
     * Handle the 'Run Now' action for a project.
     */
    public function handle_run_now() {
        // 1. Check nonce for security
        check_ajax_referer( 'dm_run_now_nonce', 'nonce' );

        // 2. Check user capability
        if ( ! current_user_can( 'manage_options' ) ) { // Adjust capability if needed
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // 3. Get project ID from POST data
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        if ( empty( $project_id ) ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        // 4. Verify user ownership of the project
        $user_id = get_current_user_id();
        $project = $this->db_projects->get_project( $project_id, $user_id );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // 5. Fetch associated modules
        $modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
        if ( empty( $modules ) ) {
            wp_send_json_success( array( 'message' => 'Project \'' . esc_html($project->project_name) . '\' has no modules to run.' ) );
            wp_die();
        }

        // 6. Trigger the actual project execution logic by creating jobs
        $jobs_created_count = 0;
        $modules_processed_names = [];
        $errors = [];

        foreach ( $modules as $module ) {
            // Check if module schedule is explicitly set to inherit project schedule
            $module_interval = $module->schedule_interval ?? 'manual';
            if ($module_interval !== 'project_schedule') {
                error_log("Data Machine Run Now (Project: {$project_id}): Skipping module {$module->module_id} as its schedule ({$module_interval}) is not 'project_schedule'.");
                continue; // Skip modules not set to follow the project schedule
            }

            // Also skip if the module itself is paused
            $module_status = $module->schedule_status ?? 'active';
            if ($module_status !== 'active') {
                error_log("Data Machine Run Now (Project: {$project_id}): Skipping module {$module->module_id} as its status is '{$module_status}'.");
                continue; // Skip paused modules
            }

            // Skip if the module input type is 'files'
            $module_input_type = $module->data_source_type ?? null;
            if ($module_input_type === 'files') {
                error_log("Data Machine Run Now (Project: {$project_id}): Skipping module {$module->module_id} as its input type is 'files'.");
                continue; // Skip file input modules
            }

            try {
                // --- Get Input Data using appropriate handler ---
                $input_handler = $this->get_input_handler_for_module( $module );
                if (!$input_handler) {
                    throw new Exception("Could not find or load input handler for type: {$module->data_source_type}");
                }

                // Decode module's data source config
                $source_config = json_decode(wp_unslash($module->data_source_config), true) ?: [];

                // Get the input data packet
                // Pass module_id in the first argument (simulating POST)
                // Pass empty array for files (second argument)
                // Pass source config (third argument)
                // Pass the $user_id (current logged-in user) determined earlier
                $input_data_packet = $input_handler->get_input_data(['module_id' => $module->module_id], [], $source_config, $user_id);

                // Skip if input handler returned no data (e.g., no new RSS items, or maybe no file for 'files' type?)
                // Check specifically for the 'no_input_data' message OR an error flag
                if ( (isset($input_data_packet['message']) && $input_data_packet['message'] === 'no_input_data') || isset($input_data_packet['error']) ) {
                     // Log the specific reason if available
                     $skip_reason = $input_data_packet['message'] ?? ($input_data_packet['error'] ? 'Error from input handler' : 'Unknown reason');
                     error_log("Data Machine: Skipping job for Module ID: {$module->module_id} ('{$module->module_name}') - Reason: {$skip_reason}");
                     // If it was an actual error, add it to the main error list
                     if (isset($input_data_packet['error'])) {
                        $errors[] = "Module '{$module->module_name}': " . ($input_data_packet['message'] ?? 'Input handler error');
                     }
                     continue; // Skip to next module
                }

                // --- Prepare Job Data ---
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
                $input_data_json = wp_json_encode($input_data_packet);

                if ($module_config_json === false || $input_data_json === false) {
                    throw new Exception('Failed to serialize job data. Error: ' . json_last_error_msg());
                }

                // --- Create Job in DB ---
                $job_id = $this->db_jobs->create_job(
                    $module->module_id,
                    $user_id,
                    $module_config_json,
                    $input_data_json
                );

                if ( false === $job_id ) {
                    throw new Exception('Failed to create database job record.');
                }

                // --- Schedule WP-Cron Event ---
                $scheduled = wp_schedule_single_event( time(), 'dm_run_job_event', array( 'job_id' => $job_id ) );

                if ( false === $scheduled ) {
                    // Attempt to clean up the job record if scheduling fails?
                    // $this->db_jobs->delete_job($job_id); // Requires delete_job method
                    throw new Exception('Failed to schedule job using WP-Cron.');
                }

                $jobs_created_count++;
                $modules_processed_names[] = $module->module_name;

            } catch (Exception $e) {
                $error_message = "Error processing module '{$module->module_name}' (ID: {$module->module_id}): " . $e->getMessage();
                error_log("Data Machine Run Now: " . $error_message);
                $errors[] = $error_message;
                // Continue to next module even if one fails
            }
        }

        // 7. Send response
        $success_message = sprintf(
            'Scheduled %d job(s) for project \'%s\'. Modules: %s.',
            $jobs_created_count,
            esc_html($project->project_name),
            implode(', ', array_map('esc_html', $modules_processed_names))
        );
        if (!empty($errors)) {
            wp_send_json_error( array(
                'message' => 'Completed with errors. ' . $success_message,
                'errors' => $errors
            ) );
        } else {
             wp_send_json_success( array( 'message' => $success_message ) );
        }

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    /**
     * Handle the 'Edit Schedule' action.
     *
     * Placeholder - Functionality TBD (e.g., open modal, save data).
     */
    public function handle_edit_schedule() {
        // 1. Check nonce
        check_ajax_referer( 'dm_edit_schedule_nonce', 'nonce' );

        // 2. Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // 3. Get and validate parameters
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        $interval = isset( $_POST['schedule_interval'] ) ? sanitize_text_field( $_POST['schedule_interval'] ) : 'manual';
        $status = isset( $_POST['schedule_status'] ) ? sanitize_text_field( $_POST['schedule_status'] ) : 'paused';
        // Get module schedules data (expecting an array like [module_id => ['interval' => '...', 'status' => '...']])
        $module_schedules = isset( $_POST['module_schedules'] ) && is_array($_POST['module_schedules']) ? $_POST['module_schedules'] : [];

        if ( empty( $project_id ) ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        // Basic validation (more specific validation happens in DB class method)
        // Remove 'manual' from allowed project intervals
        $allowed_intervals = ['every_5_minutes', 'hourly', 'twicedaily', 'daily', 'weekly'];
        $allowed_statuses = ['active', 'paused'];
        if ( !in_array($interval, $allowed_intervals) || !in_array($status, $allowed_statuses) ) {
             // If interval is 'manual', maybe default it or log a specific error?
             // For now, just reject invalid values directly.
             wp_send_json_error( 'Invalid project schedule interval or status provided.', 400 );
        }

        // 4. Update database (ownership check is done within the update method)
        $user_id = get_current_user_id();

        // --- PRE-PROCESS MODULE SCHEDULES FOR 'FILES' INPUT --- 
        // Fetch the modules again to securely check their data_source_type
        $current_modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
        $module_type_map = [];
        if ($current_modules) {
            foreach ($current_modules as $mod) {
                $module_type_map[$mod->module_id] = $mod->data_source_type ?? null;
            }
        }

        $processed_module_schedules = [];
        foreach ($module_schedules as $mod_id => $schedule_data) {
            $module_id = absint($mod_id);
            if (!isset($module_type_map[$module_id])) {
                // Module ID sent from frontend doesn't belong to this project/user, skip it.
                continue; 
            }

            // If the module type is 'files', force schedule to paused and project_schedule
            if ($module_type_map[$module_id] === 'files') {
                 error_log("Data Machine Edit Schedule: Forcing schedule for file input module ID: {$module_id}");
                 $processed_module_schedules[$module_id] = [
                    'interval' => 'project_schedule', // Default non-running interval
                    'status'   => 'paused'            // Always paused
                 ];
            } else {
                // Sanitize and validate non-file input modules
                $mod_interval = isset($schedule_data['interval']) ? sanitize_text_field($schedule_data['interval']) : 'project_schedule';
                $mod_status = isset($schedule_data['status']) ? sanitize_text_field($schedule_data['status']) : 'active';

                // Allow 'project_schedule' for modules, but not 'manual'
                $allowed_module_intervals = ['project_schedule', 'every_5_minutes', 'hourly', 'twicedaily', 'daily', 'weekly'];
                $allowed_module_statuses = ['active', 'paused'];

                if (!in_array($mod_interval, $allowed_module_intervals) || !in_array($mod_status, $allowed_module_statuses)) {
                    // Log invalid data but perhaps proceed with defaults? Or skip? For now, skip.
                    error_log("Data Machine Edit Schedule: Invalid interval ('{$mod_interval}') or status ('{$mod_status}') for module ID: {$module_id}");
                    continue; 
                }

                 $processed_module_schedules[$module_id] = [
                    'interval' => $mod_interval,
                    'status'   => $mod_status
                 ];
            }
        }
        // --- END PRE-PROCESSING ---

        try {
            // Update project schedule
            $project_updated = $this->db_projects->update_project_schedule(
                $project_id,
                $user_id,
                $interval,
                $status
            );

            // Update module schedules (pass the processed array)
            $modules_updated = $this->db_modules->update_module_schedules(
                $project_id, 
                $user_id, 
                $processed_module_schedules // Use the pre-processed schedules
            );

            if ( $project_updated === false || $modules_updated === false ) {
                // Error occurred (false indicates error, 0 or more is rows affected)
                throw new Exception('Failed to update schedule in the database.');
            }

            // Clear relevant WP Cron schedules (safer to clear both project and individual module hooks)
            wp_clear_scheduled_hook( 'dm_run_project_event', array( 'project_id' => $project_id ) );
            // Re-schedule project event if it's now active
            if ($status === 'active' && in_array($interval, $allowed_intervals)) { // Use project $allowed_intervals
                wp_schedule_event( time(), $interval, 'dm_run_project_event', array( 'project_id' => $project_id ) );
            }

            // Clear and potentially reschedule individual module crons
            foreach ( $processed_module_schedules as $module_id => $schedule_data ) {
                 wp_clear_scheduled_hook( 'dm_run_module_event', array( 'module_id' => $module_id ) );
                 // Reschedule if module has specific interval (not project) and is active
                 if ($schedule_data['status'] === 'active' 
                     && $schedule_data['interval'] !== 'project_schedule'
                     && in_array($schedule_data['interval'], $allowed_module_intervals)) 
                 {
                     wp_schedule_event( time(), $schedule_data['interval'], 'dm_run_module_event', array( 'module_id' => $module_id ) );
                 }
            }

            wp_send_json_success( [ 'message' => 'Schedule updated successfully.' ] );

        } catch (Exception $e) {
            error_log("Data Machine Error updating schedule: " . $e->getMessage());
            wp_send_json_error( 'Error updating schedule: ' . $e->getMessage(), 500 );
        }

        wp_die();
    }

    /**
     * AJAX handler to fetch project and module schedule data for the modal.
     */
    public function handle_get_project_schedule_data() {
        // 1. Check nonce
        check_ajax_referer( 'dm_get_schedule_data_nonce', 'nonce' );

        // 2. Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }

        // 3. Get project ID
        $project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        if ( empty( $project_id ) ) {
            wp_send_json_error( 'Missing project ID.', 400 );
        }

        // 4. Get Project Data (verify ownership)
        $user_id = get_current_user_id();
        $project = $this->db_projects->get_project( $project_id, $user_id );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found or permission denied.', 404 );
        }

        // 5. Get Modules Data
        $modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
        if ($modules === null) { // Check for null specifically, empty array is okay
             wp_send_json_error( 'Could not retrieve modules for project.', 500 );
        }

        // 6. Prepare data for JSON response
        $project_data = [
            'project_id' => $project->project_id,
            'project_name' => $project->project_name,
            'schedule_interval' => $project->schedule_interval ?? 'manual',
            'schedule_status' => $project->schedule_status ?? 'paused'
        ];

        $modules_data = [];
        foreach ($modules as $module) {
             $modules_data[] = [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
                'schedule_interval' => $module->schedule_interval ?? 'project_schedule',
                'schedule_status' => $module->schedule_status ?? 'active', // Default module status?
                'data_source_type' => $module->data_source_type ?? null // Add data source type
             ];
        }

        // 7. Send success response
        wp_send_json_success( [
            'project' => $project_data,
            'modules' => $modules_data
        ] );

        wp_die();
    }

    /**
     * AJAX handler to get modules for a specific project.
     * @since 0.12.0 (Moved 0.15.0)
     */
    public function get_project_modules_ajax_handler() {
        // Note: Nonce name matches JS expectation
        check_ajax_referer('dm_get_project_modules_nonce', 'nonce');

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $user_id = get_current_user_id();

        if (empty($project_id) || empty($user_id)) {
            wp_send_json_error(['message' => __('Missing project ID or user ID.', 'data-machine')]);
            return;
        }

        // Use injected dependency
        $modules = $this->db_modules->get_modules_for_project($project_id, $user_id);

        if ($modules === null) {
            // This indicates either project not found or user doesn't own it
            wp_send_json_error(['message' => __('Project not found or permission denied.', 'data-machine')]);
            return;
        }

        // Prepare modules for JSON
        $modules_data = array_map(function($module) {
            return [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
            ];
        }, $modules);

        wp_send_json_success(['modules' => $modules_data]);
    }

    /**
     * AJAX handler to create a new project.
     * @since 0.12.0 (Moved 0.15.0)
     */
    public function create_project_ajax_handler() {
        // Note: Nonce name matches JS expectation
        check_ajax_referer('dm_create_project_nonce', 'nonce');

        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $user_id = get_current_user_id();

        if (empty(trim($project_name)) || empty($user_id)) {
            wp_send_json_error(['message' => __('Project name is required.', 'data-machine')]);
            return;
        }

        // Use injected dependency
        $project_id = $this->db_projects->create_project($user_id, $project_name);

        if ($project_id === false) {
            wp_send_json_error(['message' => __('Failed to create project in database.', 'data-machine')]);
            return;
        }

        wp_send_json_success([
            'message' => __('Project created successfully.', 'data-machine'),
            'project_id' => $project_id,
            'project_name' => $project_name // Return sanitized name
        ]);
    }

    /**
     * Helper function to get the correct input handler for a module.
     *
     * @param object $module The module object.
     * @return Data_Machine_Input_Handler_Interface|null Instance of the handler or null if not found.
     */
    public function get_input_handler_for_module($module) {
        $handler = null;
        $type = $module->data_source_type ?? null;
        $handler_key = 'input_' . $type; // Example key structure

        // Option 1: Try getting from locator (if registered)
        if ($type && $this->locator->has($handler_key)) {
            $handler_instance = $this->locator->get($handler_key);
            if ($handler_instance instanceof Data_Machine_Input_Handler_Interface) {
                 return $handler_instance;
            }
        }

        // Option 2: Fallback to direct instantiation (like in ModuleAjaxHandler)
        // This requires maintaining this logic in multiple places - less ideal.
        // Consider registering all input handlers in the locator.
        $handler_class = null;
        $handler_file = null;

        switch ($type) {
            case 'files':
                // Already retrieved via locator usually, but include fallback
                return $this->locator->get('input_files');
            case 'airdrop_rest_api':
                $handler_class = 'Data_Machine_Input_Airdrop_Rest_Api';
                $handler_file = DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-airdrop_rest_api.php';
                break;
            case 'public_rest_api':
                $handler_class = 'Data_Machine_Input_Public_Rest_Api';
                $handler_file = DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-public-rest-api.php';
                break;
            case 'rss':
                $handler_class = 'Data_Machine_Input_Rss';
                $handler_file = DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-rss.php';
                break;
            case 'reddit':
                $handler_class = 'Data_Machine_Input_Reddit';
                $handler_file = DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-reddit.php';
                break;
            // Add other cases here
        }

        if ($handler_class && $handler_file) {
            if (!class_exists($handler_class)) {
                 if (file_exists($handler_file)) {
                     require_once $handler_file;
                 } else {
                     error_log("Data Machine: Input handler file not found: {$handler_file}");
                     return null;
                 }
            }
            // Assume constructor takes the locator if needed
            if (class_exists($handler_class)){
                 $handler = new $handler_class($this->locator);
                 if ($handler instanceof Data_Machine_Input_Handler_Interface) {
                    return $handler;
                 }
            }
        }

        error_log("Data Machine: Could not instantiate input handler for type: {$type}");
        return null;
    }

} 