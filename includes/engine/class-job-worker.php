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
	 * Safely decode JSON with error handling.
	 *
	 * @param string $json The JSON string to decode.
	 * @param string $context Context for error logging.
	 * @return mixed|WP_Error Decoded data on success, WP_Error on failure.
	 */
	private function safe_json_decode(string $json, string $context = ''): mixed {
		if (empty($json)) {
			return [];
		}
		
		$decoded = json_decode($json, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$error_msg = "Failed to decode JSON" . ($context ? " in {$context}" : "") . ": " . json_last_error_msg();
			$this->logger?->error($error_msg, ['context' => $context, 'json_snippet' => substr($json, 0, 200) . '...']);
			return new WP_Error('json_decode_error', $error_msg);
		}
		return $decoded;
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
			$module_config = $this->safe_json_decode( $job->module_config, "module config for job {$job_id}" );
			if ( is_wp_error( $module_config ) ) {
				throw new Exception( $module_config->get_error_message() );
			}

			// 2. Decode Input Data from Job
			$input_data_packet = $this->safe_json_decode( $job->input_data, "input data for job {$job_id}" );
			if ( is_wp_error( $input_data_packet ) ) {
				throw new Exception( $input_data_packet->get_error_message() );
			}

			// 3. Use the injected Processing Orchestrator service
			$orchestrator = $this->orchestrator;

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

			// Handle async output processing
			$output_result = $orchestrator_results['output_result'] ?? null;
			
			// Output job should always be queued for async processing
			if (is_array($output_result) && isset($output_result['status']) && $output_result['status'] === 'queued') {
				$logger->info("Job Worker: Job processing complete, output queued for async processing.", [
					'job_id' => $job_id, 
					'module_id' => $job->module_id,
					'action_id' => $output_result['action_id'] ?? 'unknown',
					'output_type' => $output_result['output_type'] ?? 'unknown'
				]);
				
				// Update job status to 'processing_output' to indicate async output processing
				$result_json = wp_json_encode($output_result);
				$db_jobs->update_job_status($job_id, 'processing_output', $result_json);
				
			} else {
				// This should not happen with the new async system
				throw new Exception('Orchestrator did not return queued output job status. Expected async processing.');
			}

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