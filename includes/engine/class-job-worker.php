<?php
/**
 * Handles the execution of scheduled Data Machine jobs via WP-Cron.
 *
 * Retrieves job data, invokes the processing orchestrator, and updates job status.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      1.6.0 // Or appropriate version
 */
class Data_Machine_Job_Worker {

    /**
     * Service Locator instance.
     *
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Projects DB instance.
     *
     * @var Data_Machine_Database_Projects
     */
    private $db_projects;

    /**
     * Modules DB instance.
     *
     * @var Data_Machine_Database_Modules
     */
    private $db_modules;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        // Also get DB handlers needed for timestamp updates
        $this->db_projects = $this->locator->get('database_projects');
        $this->db_modules = $this->locator->get('database_modules');
    }

    /**
	 * Processes a specific job identified by its ID.
     * This is the callback function for the 'dm_run_job_event' WP-Cron event.
     *
	 * @param int $job_id The ID of the job to process.
	 */
	public function process_job( $job_id ) {
		// Retrieve the job details from the database.
        $db_jobs = $this->locator->get('database_jobs');
        $logger = $this->locator->get('logger'); // Get logger

        if (!$db_jobs || !$logger) {
            error_log("Data Machine Job Worker: Failed to get required services (database_jobs or logger) for job ID: " . $job_id);
            return; // Cannot proceed without core services
        }

        $job = $db_jobs->get_job($job_id);

		if ( ! $job ) {
			$logger->error('Job Worker: Job not found in database.', ['job_id' => $job_id]);
			return; // Exit if job doesn't exist
		}

		// Prevent re-processing completed or failed jobs
		if ( in_array( $job->status, ['complete', 'failed'] ) ) {
			$logger->warning('Job Worker: Attempted to run job that is already completed or failed.', ['job_id' => $job_id, 'status' => $job->status]);
			return;
		}

		// Update job status to 'processing' and set start time using DB service
        $db_jobs->start_job($job_id);
        $logger->info("Job Worker: Starting processing.", ['job_id' => $job_id, 'module_id' => $job->module_id]);

		try {
			// Decode the necessary data from the job record
			$module_config = json_decode( $job->module_config, true );
			$input_data    = json_decode( $job->input_data, true );

			// Validate decoded data
			if ( json_last_error() !== JSON_ERROR_NONE || !is_array($module_config) || !is_array($input_data) ) {
                $json_error = json_last_error_msg();
				throw new Exception( 'Failed to decode module configuration or input data for job. Error: ' . $json_error );
			}

			// Get the Processing Orchestrator service
			$orchestrator = $this->locator->get( 'orchestrator' );
			if ( ! $orchestrator ) {
				throw new Exception( 'Failed to retrieve Processing Orchestrator service.' );
			}

			// Execute the processing job via the orchestrator
			$orchestrator_results = $orchestrator->run( $input_data, $module_config, $job->user_id, $job_id );

            // Check for WP_Error from Orchestrator
            if ( is_wp_error( $orchestrator_results ) ) {
                // Log the error from WP_Error
                $logger->error(
                    'Job Worker: Orchestrator returned WP_Error.',
                    [
                        'job_id' => $job_id,
                        'module_id' => $job->module_id,
                        'error_code' => $orchestrator_results->get_error_code(),
                        'error_message' => $orchestrator_results->get_error_message()
                    ]
                );
                // Rethrow as an exception to be caught by the main catch block
                throw new Exception( "Orchestrator failed: " . $orchestrator_results->get_error_message() );
            }

            // Log successful orchestrator run details if available (example, adjust as needed)
            $log_context = ['job_id' => $job_id, 'module_id' => $job->module_id];
            $logger->debug("Job Worker: Orchestrator run completed.", array_merge($log_context, ['result_status' => $orchestrator_results['status'] ?? 'unknown']));


			// Prepare the final result for DB storage (using output_result from orchestrator)
            // Assuming orchestrator returns a structured array including 'output_result'
            $final_result_data = $orchestrator_results['output_result'] ?? null; // Get the result from the output handler step
			$result_json = wp_json_encode( $final_result_data );

            if ( $result_json === false ) {
                $json_error = json_last_error_msg();
                $logger->error('Job Worker: Failed to encode final result data for database.', array_merge($log_context, ['json_error' => $json_error]));
                // Store the encoding error itself as the result
                $result_json = wp_json_encode(['error' => 'Failed to encode final result', 'json_error' => $json_error]);
            }

            // Update job status to 'complete' and save final result using DB service
            $completed = $db_jobs->complete_job($job_id, 'complete', $result_json);

            if (!$completed) {
                $logger->error('Job Worker: Failed to update job status to complete in database.', $log_context);
                // Even if job status update fails, maybe still try to update last run?
                // For now, we'll only update last run if the job status was successfully marked complete.
            } else {
                 $logger->info('Job Worker: Processing completed successfully.', $log_context);

                // --- START: Update Last Run Timestamps ---
                $module_id = $job->module_id ?? null;
                $project_id = $module_config['project_id'] ?? null; // Get project_id from config

                if ($module_id && $this->db_modules) {
                    $this->db_modules->update_module_last_run($module_id);
                    $logger->debug('Job Worker: Updated module last_run_at.', ['job_id' => $job_id, 'module_id' => $module_id]);
                } else {
                    if (!$module_id) $logger->warning('Job Worker: Cannot update module last_run_at, module_id missing.', ['job_id' => $job_id]);
                    if (!$this->db_modules) $logger->warning('Job Worker: Cannot update module last_run_at, db_modules service missing.', ['job_id' => $job_id]);
                }

                if ($project_id && $this->db_projects) {
                    $this->db_projects->update_project_last_run($project_id);
                    $logger->debug('Job Worker: Updated project last_run_at.', ['job_id' => $job_id, 'project_id' => $project_id]);
                } else {
                    if (!$project_id) $logger->warning('Job Worker: Cannot update project last_run_at, project_id missing from module config.', ['job_id' => $job_id]);
                    if (!$this->db_projects) $logger->warning('Job Worker: Cannot update project last_run_at, db_projects service missing.', ['job_id' => $job_id]);
                }
                // --- END: Update Last Run Timestamps ---
            }

		} catch ( Exception $e ) {
			// Log the exception
			$logger->error('Job Worker: Exception during processing.', [
                'job_id' => $job_id,
                'module_id' => $job->module_id ?? 'unknown', // module_id might not be set if decoding failed early
                'error' => $e->getMessage(),
                // Optionally include trace for debugging, but be mindful of log size
                // 'trace' => $e->getTraceAsString()
            ]);

			// Update job status to 'failed' and store error message using DB service
            $error_data_json = wp_json_encode(['error' => $e->getMessage()]);
            if ($error_data_json === false) {
                $error_data_json = '{"error": "Failed to encode error message"}';
            }
            $db_jobs->complete_job($job_id, 'failed', $error_data_json);
		}
	} // End process_job

} // End class Data_Machine_Job_Worker 