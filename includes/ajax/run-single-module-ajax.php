<?php
/**
 * Handles AJAX requests for running a single module (main processing page).
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.12.0
 */
class Data_Machine_Run_Single_Module_Ajax {
    /** @var Data_Machine_Database_Modules */
    private $db_modules;

    /** @var Data_Machine_Database_Projects */
    private $db_projects;

    /** @var Data_Machine_Job_Executor */
    private $job_executor;

    /** @var Data_Machine_Input_Files */
    private $input_files_handler;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Modules $db_modules Modules DB service.
     * @param Data_Machine_Database_Projects $db_projects Projects DB service.
     * @param Data_Machine_Job_Executor $job_executor Job Executor service.
     * @param Data_Machine_Input_Files $input_files_handler Files Input Handler service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Job_Executor $job_executor,
        Data_Machine_Input_Files $input_files_handler,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->job_executor = $job_executor;
        $this->input_files_handler = $input_files_handler;
        $this->logger = $logger;

        // Register AJAX hook for processing data
        add_action('wp_ajax_process_data_source', array($this, 'process_data_source_ajax_handler'));
    }

    /**
     * AJAX handler to process data based on module's data source type.
     *
     * @since 0.7.0 (Moved 0.12.0)
     */
    public function process_data_source_ajax_handler() {
        check_ajax_referer( 'file_processing_nonce', 'nonce' );

        $module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
        $user_id = get_current_user_id();

        if (empty($module_id) || empty($user_id)) {
            wp_send_json_error(array('message' => __('Missing module ID or user ID.', 'data-machine')));
            return;
        }

        // Get dependencies from properties
        $db_modules = $this->db_modules;
        $logger = $this->logger;
        $job_executor = $this->job_executor;
        $db_projects = $this->db_projects;

        // --- Ownership Check (using Project) ---
        $module = $db_modules->get_module($module_id); // Get module without user check first
        if (!$module || !isset($module->project_id)) {
            wp_send_json_error(array('message' => __('Invalid module or project association missing.', 'data-machine')));
            return;
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            wp_send_json_error(array('message' => __('Permission denied for this module.', 'data-machine')));
            return;
        }
        // --- End Ownership Check ---

        if (!isset($module->data_source_type)) {
            wp_send_json_error(array('message' => __('Module data source type not configured.', 'data-machine')));
            return;
        }

        // --- Refactored: Use Job Executor ---
        try {
            $input_data_for_executor = null; // Initialize variable

            // --- Handle File Uploads Specifically for Manual AJAX ---
            if ($module->data_source_type === 'files') {
                $input_handler = $this->input_files_handler; // Use injected handler
                if (!$input_handler) { // Should not happen
                    throw new Exception(__( 'File input handler not found (should be injected).', 'data-machine' ));
                }
                // Decode source config for the handler
                $source_config_decoded = json_decode(wp_unslash($module->data_source_config ?? '{}'), true) ?: [];
                // Call get_input_data directly with $_POST and $_FILES
                $input_data_for_executor = $input_handler->get_input_data($_POST, $_FILES, $source_config_decoded, $user_id);
            }

            // Call the job executor.
            $result = null;
            if ($module->data_source_type === 'files') {
                if (empty($input_data_for_executor) || !is_array($input_data_for_executor)) {
                    throw new Exception(__( 'File input handler did not return valid data.', 'data-machine' ));
                }
                $result = $job_executor->schedule_job_from_file($module, $user_id, 'manual_file', $input_data_for_executor[0]);
            } else {
                $result = $job_executor->schedule_job_from_config($module, $user_id, 'manual_ajax');
            }

            if (is_wp_error($result)) {
                $logger?->error('Manual AJAX Trigger: Job Executor failed.', [
                    'module_id' => $module_id,
                    'user_id' => $user_id,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message()
                ]);
                wp_send_json_error(array('message' => $result->get_error_message()));

            } elseif (is_int($result) && $result > 0) {
                $job_id = $result;
                $logger?->info('Manual AJAX Trigger: Job successfully queued.', ['module_id' => $module_id, 'user_id' => $user_id, 'job_id' => $job_id]);
                wp_send_json_success(array(
                    'status' => 'processing_queued',
                    'message' => sprintf(__( 'Processing job successfully queued (Job ID: %d). The results will appear when processing is complete.', 'data-machine' ), $job_id),
                    'job_id' => $job_id
                ));

            } elseif ($result === 0) {
                $logger?->info('Manual AJAX Trigger: No new items found to process after filtering.', ['module_id' => $module_id, 'user_id' => $user_id]);
                wp_send_json_success(array(
                    'status' => 'success_no_items',
                    'message' => __( 'No new items found matching the criteria after checking for duplicates.', 'data-machine' )
                ));
            } else {
                $logger?->error('Manual AJAX Trigger: Unexpected result from Job Executor.', ['module_id' => $module_id, 'user_id' => $user_id, 'result' => $result]);
                wp_send_json_error(array('message' => __('An unexpected error occurred during job initiation.', 'data-machine')));
            }

        } catch (Exception $e) {
            $logger?->error('Error during Manual AJAX processing trigger: ' . $e->getMessage(), ['module_id' => $module_id, 'user_id' => $user_id]); 
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
        // --- End Refactored ---

        wp_die(); // Ensure execution stops
    }
}
