<?php
/**
 * Orchestrates the multi-step data processing flow.
 *
 * Takes input data and module configuration, runs it through the
 * necessary API steps (Process, FactCheck, Finalize), and delegates
 * the final output action to the appropriate handler.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes
 * @since      0.6.0 // Or the next version number
 */
class Data_Machine_Processing_Orchestrator {

	/** @var Data_Machine_process_data */
	private $process_data_handler;

	/** @var Data_Machine_API_FactCheck */
	private $factcheck_api;

	/** @var Data_Machine_API_Finalize */
	private $finalize_api;

	/** @var Dependency_Injection_Handler_Factory */
	private $handler_factory;

	/** @var Data_Machine_Prompt_Builder */
	private $prompt_builder;

	/** @var Data_Machine_Logger */
	private $logger;

	/** @var Data_Machine_Action_Scheduler */
	private $action_scheduler;

	/** @var Data_Machine_Database_Jobs */
	private $db_jobs;

	/** @var Data_Machine_Job_Status_Manager */
	private $job_status_manager;

	/** @var Data_Machine_Database_Modules */
	private $db_modules;

	/**
	 * Constructor. Dependencies are injected.
	 *
	 * @param Data_Machine_process_data $process_data_handler Instance of Process Data handler.
	 * @param Data_Machine_API_FactCheck $factcheck_api Instance of FactCheck API handler.
	 * @param Data_Machine_API_Finalize $finalize_api Instance of Finalize API handler.
	 * @param Dependency_Injection_Handler_Factory $handler_factory Handler Factory instance.
	 * @param Data_Machine_Prompt_Builder $prompt_builder Instance of centralized prompt builder.
	 * @param Data_Machine_Logger $logger Logger instance.
	 * @param Data_Machine_Action_Scheduler $action_scheduler Action Scheduler service.
	 * @param Data_Machine_Database_Jobs $db_jobs Database Jobs service.
	 * @param Data_Machine_Job_Status_Manager $job_status_manager Job Status Manager service.
	 * @param Data_Machine_Database_Modules $db_modules Database Modules service.
	 */
	public function __construct(
		Data_Machine_process_data $process_data_handler,
		Data_Machine_API_FactCheck $factcheck_api,
		Data_Machine_API_Finalize $finalize_api,
		Dependency_Injection_Handler_Factory $handler_factory,
		Data_Machine_Prompt_Builder $prompt_builder,
		Data_Machine_Logger $logger,
		Data_Machine_Action_Scheduler $action_scheduler,
		Data_Machine_Database_Jobs $db_jobs,
		Data_Machine_Job_Status_Manager $job_status_manager,
		Data_Machine_Database_Modules $db_modules
	) {
		$this->process_data_handler = $process_data_handler;
		$this->factcheck_api = $factcheck_api;
		$this->finalize_api = $finalize_api;
		$this->handler_factory = $handler_factory;
		$this->prompt_builder = $prompt_builder;
		$this->logger = $logger;
		$this->action_scheduler = $action_scheduler;
		$this->db_jobs = $db_jobs;
		$this->job_status_manager = $job_status_manager;
		$this->db_modules = $db_modules;
	}
	/**
	 * Execute Step 1: Input Data Processing (async).
	 *
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_input_step( int $job_id ): bool {
		try {
			// Mark job as started
			if ( ! $this->job_status_manager->start( $job_id ) ) {
				return false;
			}

			$job = $this->db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$this->job_status_manager->fail( $job_id, 'Job not found in database' );
				return false;
			}

			$module_job_config = json_decode( $job->module_config, true );
			$module_id = $module_job_config['module_id'] ?? 0;
			$data_source_type = $module_job_config['data_source_type'] ?? '';

			if ( empty( $data_source_type ) ) {
				$this->logger->error( 'Missing data source type for input step', ['job_id' => $job_id, 'module_id' => $module_id] );
				$this->job_status_manager->fail( $job_id, 'Missing data source type for input step' );
				return false;
			}

			// Get input handler and fetch data
			$input_handler = $this->handler_factory->create_handler( 'input', $data_source_type );
			if ( is_wp_error( $input_handler ) ) {
				$error_message = 'Failed to create input handler: ' . $input_handler->get_error_message();
				$this->logger->error( 'Failed to create input handler', ['job_id' => $job_id, 'handler_type' => $data_source_type] );
				$this->job_status_manager->fail( $job_id, $error_message );
				return false;
			}

			// Get the full module object from database
			$user_id = $module_job_config['user_id'] ?? 0;
			$module = $this->db_modules->get_module( $module_id, $user_id );
			if ( ! $module ) {
				$this->logger->error( 'Module not found for input step', ['job_id' => $job_id, 'module_id' => $module_id, 'user_id' => $user_id] );
				$this->job_status_manager->fail( $job_id, 'Module not found for input step' );
				return false;
			}

			// Extract source config from module
			$source_config = json_decode( $module->data_source_config ?? '{}', true );
			if ( ! is_array( $source_config ) ) {
				$this->logger->error( 'Invalid data source config in module', ['job_id' => $job_id, 'module_id' => $module_id] );
				$this->job_status_manager->fail( $job_id, 'Invalid data source config in module' );
				return false;
			}

			// Fetch input data using the correct method signature
			$input_data_packet = $input_handler->get_input_data( $module, $source_config, $user_id );
			if ( is_wp_error( $input_data_packet ) ) {
				$error_message = 'Input handler failed: ' . $input_data_packet->get_error_message();
				$this->logger->error( 'Input handler failed to fetch data', ['job_id' => $job_id, 'handler_type' => $data_source_type] );
				$this->job_status_manager->fail( $job_id, $error_message );
				return false;
			}

			if ( empty( $input_data_packet ) ) {
				$this->logger->error( 'Input handler returned empty data', ['job_id' => $job_id, 'handler_type' => $data_source_type] );
				$this->job_status_manager->fail( $job_id, 'Input handler returned empty data' );
				return false;
			}

			// Store input data and schedule next step
			$json_data = wp_json_encode( $input_data_packet );
			if ( $json_data === false ) {
				$this->logger->error( 'Failed to JSON encode input data', ['job_id' => $job_id, 'json_error' => json_last_error_msg()] );
				$this->job_status_manager->fail( $job_id, 'Failed to JSON encode input data: ' . json_last_error_msg() );
				return false;
			}
			
			$this->logger->info( 'JSON encoded input data successfully', ['job_id' => $job_id, 'data_size' => strlen($json_data)] );
			
			$success = $this->db_jobs->update_step_data( $job_id, 1, $json_data );
			if ( ! $success ) {
				$this->logger->error( 'Failed to store input data', ['job_id' => $job_id] );
				$this->job_status_manager->fail( $job_id, 'Failed to store input data in database' );
				return false;
			}

			// Schedule Step 2: Process
			if ( ! $this->schedule_next_step( $job_id, 'dm_process_job_event' ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to schedule process step' );
				return false;
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Exception in input step', ['job_id' => $job_id, 'error' => $e->getMessage()] );
			$this->job_status_manager->fail( $job_id, 'Input step failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Execute Step 2: Process Data (async).
	 *
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_process_step( int $job_id ): bool {
		try {
			$job = $this->db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$this->job_status_manager->fail( $job_id, 'Job not found in database for process step' );
				return false;
			}

			$input_data_json = $this->db_jobs->get_step_data( $job_id, 1 );
			if ( empty( $input_data_json ) ) {
				$this->job_status_manager->fail( $job_id, 'No input data available for process step' );
				return false;
			}

			$input_data_packet = json_decode( $input_data_json, true );
			if ( empty( $input_data_packet ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to parse input data for process step' );
				return false;
			}

			$module_job_config = json_decode( $job->module_config, true );

			$result = $this->execute_process_step_logic( $job_id, $input_data_packet, $module_job_config );
			
			if ( ! $result ) {
				$this->job_status_manager->fail( $job_id, 'Process step logic failed' );
				return false;
			}

			if ( ! $this->schedule_next_step( $job_id, 'dm_factcheck_job_event' ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to schedule fact check step' );
				return false;
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Exception in process step', ['job_id' => $job_id, 'error' => $e->getMessage()] );
			$this->job_status_manager->fail( $job_id, 'Process step failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Execute Step 3: Fact Check (async).
	 *
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_factcheck_step( int $job_id ): bool {
		try {
			$job = $this->db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$this->job_status_manager->fail( $job_id, 'Job not found in database for fact check step' );
				return false;
			}

			$input_data_json = $this->db_jobs->get_step_data( $job_id, 1 );
			if ( empty( $input_data_json ) ) {
				$this->job_status_manager->fail( $job_id, 'No input data available for fact check step' );
				return false;
			}

			$input_data_packet = json_decode( $input_data_json, true );
			if ( empty( $input_data_packet ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to parse input data for fact check step' );
				return false;
			}

			$module_job_config = json_decode( $job->module_config, true );

			$result = $this->execute_factcheck_step_logic( $job_id, $input_data_packet, $module_job_config );
			
			if ( ! $result ) {
				$this->job_status_manager->fail( $job_id, 'Fact check step logic failed' );
				return false;
			}

			if ( ! $this->schedule_next_step( $job_id, 'dm_finalize_job_event' ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to schedule finalize step' );
				return false;
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Exception in factcheck step', ['job_id' => $job_id, 'error' => $e->getMessage()] );
			$this->job_status_manager->fail( $job_id, 'Fact check step failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Execute Step 4: Finalize (async).
	 *
	 * @param int $job_id The job ID.
	 * @return bool True on success, false on failure.
	 */
	public function execute_finalize_step( int $job_id ): bool {
		try {
			$job = $this->db_jobs->get_job( $job_id );
			if ( ! $job ) {
				$this->job_status_manager->fail( $job_id, 'Job not found in database for finalize step' );
				return false;
			}

			$input_data_json = $this->db_jobs->get_step_data( $job_id, 1 );
			if ( empty( $input_data_json ) ) {
				$this->job_status_manager->fail( $job_id, 'No input data available for finalize step' );
				return false;
			}

			$input_data_packet = json_decode( $input_data_json, true );
			if ( empty( $input_data_packet ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to parse input data for finalize step' );
				return false;
			}

			$module_job_config = json_decode( $job->module_config, true );

			$result = $this->execute_finalize_step_logic( $job_id, $input_data_packet, $module_job_config );
			
			if ( ! $result ) {
				$this->job_status_manager->fail( $job_id, 'Finalize step logic failed' );
				return false;
			}

			if ( ! $this->schedule_next_step( $job_id, 'dm_output_job_event' ) ) {
				$this->job_status_manager->fail( $job_id, 'Failed to schedule output step' );
				return false;
			}

			return true;

		} catch ( Exception $e ) {
			$this->logger->error( 'Exception in finalize step', ['job_id' => $job_id, 'error' => $e->getMessage()] );
			$this->job_status_manager->fail( $job_id, 'Finalize step failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Schedule the next step in the async pipeline.
	 *
	 * @param int    $job_id The job ID.
	 * @param string $hook   The hook name for the next step.
	 * @return bool True on success, false on failure.
	 */
	private function schedule_next_step( int $job_id, string $hook ): bool {
		$action_id = $this->action_scheduler->schedule_single_job(
			$hook,
			['job_id' => $job_id],
			time() + 1 // Schedule immediately
		);

		$success = $action_id !== false && $action_id !== 0;
		
		if ( ! $success ) {
			$this->logger->error( 'Failed to schedule next step', [
				'job_id' => $job_id,
				'hook' => $hook,
				'action_id' => $action_id
			] );
		}
		
		return $success;
	}

	/**
	 * Execute process step logic (Step 2).
	 */
	private function execute_process_step_logic( int $job_id, array $input_data_packet, array $module_job_config ): bool {
		$api_key = get_user_meta( $module_job_config['user_id'] ?? 0, 'dm_openai_api_key', true );
		if ( empty( $api_key ) ) {
			$api_key = get_option( 'openai_api_key' );
		}
		if ( empty( $api_key ) ) {
			return false;
		}

		$project_id = absint( $module_job_config['project_id'] ?? 0 );
		$system_prompt = $this->prompt_builder->build_system_prompt( $project_id, $module_job_config['user_id'] ?? 0 );
		$process_data_prompt = $module_job_config['process_data_prompt'] ?? '';

		try {
			$enhanced_process_prompt = $this->prompt_builder->build_process_data_prompt( $process_data_prompt, $input_data_packet );
			$process_result = $this->process_data_handler->process_data( $api_key, $system_prompt, $enhanced_process_prompt, $input_data_packet );

			if ( isset( $process_result['status'] ) && $process_result['status'] === 'error' ) {
				return false;
			}

			$initial_output = $process_result['json_output'] ?? '';
			if ( empty( $initial_output ) ) {
				return false;
			}

			// Store result in database
			$this->db_jobs->update_step_data( $job_id, 2, wp_json_encode( ['processed_output' => $initial_output] ) );
			return true;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Execute fact check step logic (Step 3).
	 */
	private function execute_factcheck_step_logic( int $job_id, array $input_data_packet, array $module_job_config ): bool {
		$skip_fact_check = isset( $module_job_config['skip_fact_check'] ) ? (bool) $module_job_config['skip_fact_check'] : false;
		
		if ( $skip_fact_check ) {
			// Store empty fact check result and continue
			$this->db_jobs->update_step_data( $job_id, 3, wp_json_encode( ['fact_checked_content' => ''] ) );
			return true;
		}

		$api_key = get_user_meta( $module_job_config['user_id'] ?? 0, 'dm_openai_api_key', true );
		if ( empty( $api_key ) ) {
			$api_key = get_option( 'openai_api_key' );
		}
		if ( empty( $api_key ) ) {
			return false;
		}

		$project_id = absint( $module_job_config['project_id'] ?? 0 );
		$system_prompt = $this->prompt_builder->build_system_prompt( $project_id, $module_job_config['user_id'] ?? 0 );
		$fact_check_prompt = $module_job_config['fact_check_prompt'] ?? '';

		// Get processed data from previous step
		$processed_data_json = $this->db_jobs->get_step_data( $job_id, 2 );
		$processed_data = json_decode( $processed_data_json, true );
		$initial_output = $processed_data['processed_output'] ?? '';

		try {
			$enhanced_fact_check_prompt = $this->prompt_builder->build_fact_check_prompt( $fact_check_prompt );
			$factcheck_result = $this->factcheck_api->fact_check_response( $api_key, $system_prompt, $enhanced_fact_check_prompt, $initial_output );

			if ( is_wp_error( $factcheck_result ) ) {
				return false;
			}

			$fact_checked_content = $factcheck_result['fact_check_results'] ?? '';
			
			// Store result in database
			$this->db_jobs->update_step_data( $job_id, 3, wp_json_encode( ['fact_checked_content' => $fact_checked_content] ) );
			return true;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Execute finalize step logic (Step 4).
	 */
	private function execute_finalize_step_logic( int $job_id, array $input_data_packet, array $module_job_config ): bool {
		$api_key = get_user_meta( $module_job_config['user_id'] ?? 0, 'dm_openai_api_key', true );
		if ( empty( $api_key ) ) {
			$api_key = get_option( 'openai_api_key' );
		}
		if ( empty( $api_key ) ) {
			return false;
		}

		$project_id = absint( $module_job_config['project_id'] ?? 0 );
		$system_prompt = $this->prompt_builder->build_system_prompt( $project_id, $module_job_config['user_id'] ?? 0 );
		$finalize_response_prompt = $module_job_config['finalize_response_prompt'] ?? '';

		// Get data from previous steps
		$processed_data_json = $this->db_jobs->get_step_data( $job_id, 2 );
		$processed_data = json_decode( $processed_data_json, true );
		$initial_output = $processed_data['processed_output'] ?? '';

		$factcheck_data_json = $this->db_jobs->get_step_data( $job_id, 3 );
		$factcheck_data = json_decode( $factcheck_data_json, true );
		$fact_checked_content = $factcheck_data['fact_checked_content'] ?? '';

		try {
			$enhanced_finalize_prompt = $this->prompt_builder->build_finalize_prompt( $finalize_response_prompt, $module_job_config, $input_data_packet );
			$finalize_user_message = $this->prompt_builder->build_finalize_user_message( $enhanced_finalize_prompt, $initial_output, $fact_checked_content, $module_job_config, $input_data_packet['metadata'] ?? [] );

			$finalize_result = $this->finalize_api->finalize_response(
				$api_key,
				$system_prompt,
				$finalize_user_message,
				$initial_output,
				$fact_checked_content,
				$module_job_config,
				$input_data_packet['metadata'] ?? []
			);

			if ( is_wp_error( $finalize_result ) ) {
				return false;
			}

			$final_output_string = $finalize_result['final_output'] ?? '';
			if ( empty( $final_output_string ) ) {
				return false;
			}

			// Store result in database
			$this->db_jobs->update_step_data( $job_id, 4, wp_json_encode( ['final_output_string' => $final_output_string] ) );
			return true;

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Helper function for logging orchestrator steps.
	 *
	 * @param string $message Log message.
	 * @param mixed $module_id Module ID.
	 * @param array $metadata Input metadata.
	 * @param array $details Additional details.
	 */
	private function log_orchestrator_step(string $message, $module_id, array $metadata = [], array $details = []) {
		// Use the injected logger service
		$context = array_merge(
			['module_id' => $module_id, 'source_url' => $metadata['source_url'] ?? 'N/A'],
			$details
		);
		$this->logger->info('Orchestrator: ' . $message, $context);
	}

} // End class