<?php

class Data_Machine_Job_Worker {

	/** @var Data_Machine_Logger */
	private $logger;

	/** @var Data_Machine_Database_Jobs */
	private $db_jobs;

	/** @var Data_Machine_Processing_Orchestrator */
	private $orchestrator;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Logger $logger Logger service.
	 * @param Data_Machine_Database_Jobs $db_jobs Jobs DB service.
	 * @param Data_Machine_Processing_Orchestrator $orchestrator Processing Orchestrator service.
	 */
	public function __construct(
		Data_Machine_Logger $logger,
		Data_Machine_Database_Jobs $db_jobs,
		Data_Machine_Processing_Orchestrator $orchestrator
	) {
		$this->logger = $logger;
		$this->db_jobs = $db_jobs;
		$this->orchestrator = $orchestrator;
	}

	/**
	 * Processes a single job.
	 *
	 * @param int $job_id The ID of the job to process.
	 * @return void
	 */
	public function process_job( $job_id ) {
		// Use injected logger and db_jobs
		$logger  = $this->logger;
		$db_jobs = $this->db_jobs;

		if ( ! $db_jobs ) { // This check might be redundant now with type hinting, but leave for safety? Or remove? Let's remove.
			$logger->error( 'Job Worker: Database jobs service not properly injected.' );
			return; // Cannot proceed
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
			// 1. Decode Module Configuration
			$module_config = json_decode( $job->module_config, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $module_config ) ) {
				$json_error = json_last_error_msg();
				throw new Exception( 'Failed to decode module configuration for job. Error: ' . $json_error );
			}

			// 2. Decode Input Data from Job
			$input_data_packet = json_decode( $job->input_data, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $input_data_packet ) ) {
				$json_error = json_last_error_msg();
				throw new Exception( 'Failed to decode input data for job. Error: ' . $json_error );
			}

			// 3. Use the injected Processing Orchestrator service
			$orchestrator = $this->orchestrator; // No need to check, ensured by constructor
			if ( ! $orchestrator ) { // This check is redundant now with type hinting
				 throw new Exception( 'Failed to retrieve Processing Orchestrator service (should be injected).' );
			}

			// 4. Execute the processing job via the orchestrator, passing the fetched data
			$logger->info( "Job Worker: Calling orchestrator.", [ 'job_id' => $job_id, 'module_id' => $job->module_id ] );
			$orchestrator_results = $orchestrator->run( $input_data_packet, $module_config, $job->user_id, $job_id );

			// 5. Check for WP_Error from Orchestrator
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

			// Ensure we have data to encode before proceeding
			if (is_null($final_result_data)) {
				// Log a warning or potentially throw an error if output_result is crucial
				$logger->warning("Job Worker: Orchestrator did not return 'output_result'. Storing null.", ['job_id' => $job_id, 'module_id' => $job->module_id]);
				// Decide how to handle: store null, or throw an exception? Storing null for now.
				$result_json = null;
			} else {
				$result_json = wp_json_encode($final_result_data);
				if ($result_json === false) {
					$json_error = json_last_error_msg();
					throw new Exception('Failed to encode orchestrator result for job. Error: ' . $json_error);
				}
			}

			// Update job status to 'complete' using DB service
			// Pass null or the encoded JSON string
			$db_jobs->complete_job( $job_id, 'complete', $result_json );
			$logger->info("Job Worker: Job completed successfully.", ['job_id' => $job_id, 'module_id' => $job->module_id]);

		} catch ( Exception $e ) {
			// Handle exceptions during processing
			$logger->error(
				'Job Worker: Exception during processing.',
				[
					'job_id'    => $job_id,
					'module_id' => isset($job->module_id) ? $job->module_id : 'unknown', // Ensure module_id exists
					'error'     => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
				]
			);
			// Update job status to 'failed' using DB service
			$db_jobs->fail_job( $job_id, $e->getMessage() );
		}
	}
}