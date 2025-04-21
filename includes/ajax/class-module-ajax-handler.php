<?php
/**
 * Handles AJAX requests related to Modules and Processing Jobs.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.12.0
 */
class Data_Machine_Module_Ajax_Handler {

    /** @var Data_Machine_Service_Locator */
    private $locator;

    /**
     * Constructor.
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct( Data_Machine_Service_Locator $locator ) {
        $this->locator = $locator;
        
        // Register AJAX hooks
        add_action('wp_ajax_process_data_source', array($this, 'process_data_source_ajax_handler'));
        add_action('wp_ajax_get_module_data', array($this, 'get_module_data_ajax_handler'));
        // Add wp_ajax_nopriv_ if these actions need to be available to non-logged-in users (unlikely for module management)
    }

    /**
	 * Generic AJAX handler to process data based on module's data source type.
     * Moved from Data_Machine class.
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

        // Get dependencies from locator
        $db_modules = $this->locator->get('database_modules');
        $logger = $this->locator->get('logger');
        // Get Job Executor
        $job_executor = $this->locator->get('job_executor');

        if (!$db_modules || !$logger || !$job_executor) {
            error_log("Data Machine Module AJAX: Failed to get required services (db_modules, logger, or job_executor) for module ID: " . $module_id);
            wp_send_json_error(array('message' => __('Internal server error. Required services missing.', 'data-machine')));
            return;
        }

		// --- Ownership Check (using Project) ---
        $module = $db_modules->get_module($module_id); // Get module without user check first
        if (!$module || !isset($module->project_id)) {
             wp_send_json_error(array('message' => __('Invalid module or project association missing.', 'data-machine')));
             return;
        }
        // Ensure Database_Projects class is loaded if not already via autoloader/main file
        // Note: Getting db_projects from locator assumes it's registered
        $db_projects = $this->locator->get('database_projects');
        if (!$db_projects) {
             // Fallback if not registered (should not happen ideally)
             if (!class_exists('Data_Machine_Database_Projects')) {
                 require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
             }
             $db_projects = new Data_Machine_Database_Projects();
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
                $input_handler = $this->locator->get('input_files');
                if (!$input_handler) {
                    throw new Exception(__( 'File input handler not found.', 'data-machine' ));
                }
                // Decode source config for the handler
                $source_config_decoded = json_decode(wp_unslash($module->data_source_config ?? '{}'), true) ?: [];
                // Call get_input_data directly with $_POST and $_FILES
                // Note: $_POST is used here, but the handler primarily needs $_FILES['file_upload']
                // The handler already pulls module_id from $_POST if needed, but we also pass it.
                $input_data_for_executor = $input_handler->get_input_data($_POST, $_FILES, $source_config_decoded, $user_id);
                // $input_data_for_executor will now be an array containing the single file packet, or an Exception was thrown.
            }

            // Call the job executor.
            // Pass the pre-fetched data ONLY if it was fetched (i.e., for file uploads)
            // Otherwise, pass null to let the executor fetch data itself.
            $result = null;
            if ($module->data_source_type === 'files') {
                // Ensure $input_data_for_executor is valid before calling
                if (empty($input_data_for_executor) || !is_array($input_data_for_executor)) {
                    throw new Exception(__( 'File input handler did not return valid data.', 'data-machine' ));
                }
                // The file handler returns an array containing the single packet, use that directly.
                $result = $job_executor->schedule_job_from_file($module, $user_id, 'manual_file', $input_data_for_executor[0]);
            } else {
                // For other types, call the config scheduler
                $result = $job_executor->schedule_job_from_config($module, $user_id, 'manual_ajax');
            }

            if (is_wp_error($result)) {
                // Job Executor returned an error (e.g., input fetch failed, DB error)
                $logger->error('Manual AJAX Trigger: Job Executor failed.', [
                    'module_id' => $module_id,
                    'user_id' => $user_id,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message()
                ]);
                wp_send_json_error(array('message' => $result->get_error_message()));

            } elseif (is_int($result) && $result > 0) {
                // Job Executor successfully created and scheduled a job
                $job_id = $result;
                $logger->info('Manual AJAX Trigger: Job successfully queued.', ['module_id' => $module_id, 'user_id' => $user_id, 'job_id' => $job_id]);
                wp_send_json_success(array(
                    'status' => 'processing_queued', // Maintain status for potential JS polling
                    'message' => sprintf(__( 'Processing job successfully queued (Job ID: %d). The results will appear when processing is complete.', 'data-machine' ), $job_id),
                    'job_id' => $job_id // Return the single Job ID
                ));

            } elseif ($result === 0) {
                // Job Executor found no new items after duplicate filtering
                $logger->info('Manual AJAX Trigger: No new items found to process after filtering.', ['module_id' => $module_id, 'user_id' => $user_id]);
                wp_send_json_success(array(
                    'status' => 'success_no_items',
                    'message' => __( 'No new items found matching the criteria after checking for duplicates.', 'data-machine' )
                    // 'output_result' => array( 'message' => __( 'No new input data found.', 'data-machine' ) ) // Optional: Keep for JS consistency?
                ));
            } else {
                 // Unexpected result from Job Executor
                 $logger->error('Manual AJAX Trigger: Unexpected result from Job Executor.', ['module_id' => $module_id, 'user_id' => $user_id, 'result' => $result]);
                 wp_send_json_error(array('message' => __('An unexpected error occurred during job initiation.', 'data-machine')));
            }

        } catch (Exception $e) {
            // Catch any unexpected exceptions during the process
            $logger->error('Error during Manual AJAX processing trigger: ' . $e->getMessage(), ['module_id' => $module_id, 'user_id' => $user_id]); 
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
        // --- End Refactored --- 

		/* --- REMOVED OLD LOGIC --- 
        // Old code that fetched input, looped, inserted jobs, and scheduled events is removed.
        // The Job Executor now handles this entire process.
        --- END REMOVED OLD LOGIC --- */

		wp_die(); // Ensure execution stops
	}

    /**
	 * AJAX handler to fetch data for a specific module.
     * Moved from Data_Machine class.
	 * @since 0.2.0 (Moved 0.12.0)
	 */
	public function get_module_data_ajax_handler() {
		check_ajax_referer('dm_get_module_nonce', 'nonce');

		$module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
		$user_id = get_current_user_id();

		if (empty($module_id)) {
			wp_send_json_error(array('message' => 'Module ID missing.'));
			return;
		}

        // Get dependencies from locator
        $db_modules = $this->locator->get('database_modules');
        // Ensure Database_Projects class is loaded if not already via autoloader/main file
        if (!class_exists('Data_Machine_Database_Projects')) {
             require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
        }
        $db_projects = $this->locator->get('database_projects'); // Assumes registered

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

        $logger = $this->locator->get('logger'); // Ensure logger is available

        // Decode configs first
        $raw_ds_config_string = $module->data_source_config ?? null;
        // $logger->debug('Get Module AJAX: Raw data_source_config from DB', ['raw_string' => $raw_ds_config_string]); // Less verbose logging
        $decoded_ds_config = !empty($raw_ds_config_string) ? json_decode(wp_unslash($raw_ds_config_string), true) : array();
        if (!is_array($decoded_ds_config)) $decoded_ds_config = []; // Ensure array
        // $logger->debug('Get Module AJAX: Decoded data_source_config', ['decoded' => $decoded_ds_config, 'json_error' => json_last_error_msg()]);

        $raw_output_config_string = $module->output_config ?? null;
        // $logger->debug('Get Module AJAX: Raw output_config from DB', ['raw_string' => $raw_output_config_string]); // Less verbose logging
        $decoded_output_config = !empty($raw_output_config_string) ? json_decode(wp_unslash($raw_output_config_string), true) : array();
        if (!is_array($decoded_output_config)) $decoded_output_config = []; // Ensure array
        // $logger->debug('Get Module AJAX: Decoded output_config', ['decoded' => $decoded_output_config, 'json_error' => json_last_error_msg()]);

        // --- Refactored Nesting Logic --- 
        $final_ds_config = [];
        $final_output_config = [];
        // (Smart nesting logic remains unchanged - it will include remote_site_info if present in $decoded_*_config)
        // Check Data Source Config structure
        if (count($decoded_ds_config) === 1 && key($decoded_ds_config) === $module->data_source_type) {
            $final_ds_config = $decoded_ds_config;
            // $logger->debug('Get Module AJAX: DS config already nested.', ['slug' => $module->data_source_type]);
        } elseif (!empty($decoded_ds_config)) {
            $final_ds_config = [$module->data_source_type => $decoded_ds_config];
            // $logger->debug('Get Module AJAX: Nesting DS config.', ['slug' => $module->data_source_type]);
        } else {
             $final_ds_config = [$module->data_source_type => []];
            // $logger->debug('Get Module AJAX: Creating empty nested DS config structure.', ['slug' => $module->data_source_type]);
        }
        // Check Output Config structure
        if (count($decoded_output_config) === 1 && key($decoded_output_config) === $module->output_type) {
            $final_output_config = $decoded_output_config;
            // $logger->debug('Get Module AJAX: Output config already nested.', ['slug' => $module->output_type]);
        } elseif (!empty($decoded_output_config)) {
            $final_output_config = [$module->output_type => $decoded_output_config];
            // $logger->debug('Get Module AJAX: Nesting Output config.', ['slug' => $module->output_type]);
        } else {
            $final_output_config = [$module->output_type => []];
            // $logger->debug('Get Module AJAX: Creating empty nested Output config structure.', ['slug' => $module->output_type]);
        }
        // --- End Refactored Nesting Logic ---

		// Prepare data to return using the consistently nested configs
		$data_to_return = array(
			'module_id' => $module->module_id,
            'project_id' => $module->project_id,
			'module_name' => $module->module_name,
			'process_data_prompt' => $module->process_data_prompt,
			'fact_check_prompt' => $module->fact_check_prompt,
			'finalize_response_prompt' => $module->finalize_response_prompt,
			'data_source_type' => $module->data_source_type,
			'output_type' => $module->output_type,
            // Use the consistently structured final config arrays
			'output_config' => $final_output_config,
			'data_source_config' => $final_ds_config
		);

		// Log the structure (keys) being sent, not the full data
		$logger->debug('Get Module AJAX: Final data_to_return keys', ['keys' => array_keys($data_to_return)]);

		wp_send_json_success($data_to_return);
	}

} // End class Data_Machine_Module_Ajax_Handler