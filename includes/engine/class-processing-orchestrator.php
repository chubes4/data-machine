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

	/** @var Data_Machine_Handler_Factory */
	private $handler_factory;

	/** @var Data_Machine_Prompt_Builder */
	private $prompt_builder;

	/** @var Data_Machine_Logger */
	private $logger;

	/**
	 * Constructor. Dependencies are injected.
	 *
	 * @param Data_Machine_process_data $process_data_handler Instance of Process Data handler.
	 * @param Data_Machine_API_FactCheck $factcheck_api Instance of FactCheck API handler.
	 * @param Data_Machine_API_Finalize $finalize_api Instance of Finalize API handler.
	 * @param Data_Machine_Handler_Factory $handler_factory Handler Factory instance.
	 * @param Data_Machine_Prompt_Builder $prompt_builder Instance of centralized prompt builder.
	 * @param Data_Machine_Logger $logger Logger instance.
	 */
	public function __construct(
		Data_Machine_process_data $process_data_handler,
		Data_Machine_API_FactCheck $factcheck_api,
		Data_Machine_API_Finalize $finalize_api,
		Data_Machine_Handler_Factory $handler_factory,
		Data_Machine_Prompt_Builder $prompt_builder,
		Data_Machine_Logger $logger
	) {
		$this->process_data_handler = $process_data_handler;
		$this->factcheck_api = $factcheck_api;
		$this->finalize_api = $finalize_api;
		$this->handler_factory = $handler_factory;
		$this->prompt_builder = $prompt_builder;
		$this->logger = $logger;
	}

	/**
	 * Runs the complete processing flow for given input and configuration.
	 * Process -> FactCheck -> Finalize -> Output Delegation
	 *
	 * @since 0.6.0 (Refactored 0.10.0)
	 * @param array  $input_data_packet The standardized input data packet from the Input Handler.
	 * @param array  $module_job_config Simplified module configuration array for the job.
	 * @param int    $user_id Current user ID.
	 * @return array|WP_Error Result from the final output handler or WP_Error on failure at any step.
	 */
	public function run( array $input_data_packet, array $module_job_config, $user_id, $job_id = null ) {

		// --- Configuration & Validation ---
		$api_key = get_user_meta($user_id, 'dm_openai_api_key', true);
		if (empty($api_key)) {
			$api_key = get_option('openai_api_key'); // Fallback for backward compatibility
		}
		if (empty($api_key)) {
			return new WP_Error('missing_api_key', __('OpenAI API Key is missing.', 'data-machine'));
		}
		// Use the config array instead of the object
		if (empty($module_job_config) || !isset($module_job_config['module_id'])) {
			return new WP_Error('invalid_module_config', __('Invalid module configuration provided for job.', 'data-machine'));
		}
		$module_id = $module_job_config['module_id']; // For logging

		// --- Build System Prompt using centralized builder ---
		$project_id = absint($module_job_config['project_id'] ?? 0);
		$system_prompt = $this->prompt_builder->build_system_prompt($project_id, $user_id);

		// --- Extract and validate base prompts ---
		$process_data_prompt = $module_job_config['process_data_prompt'] ?? '';
		$fact_check_prompt = $module_job_config['fact_check_prompt'] ?? '';
		$finalize_response_prompt = $module_job_config['finalize_response_prompt'] ?? '';
		$skip_fact_check = isset($module_job_config['skip_fact_check']) ? (bool)$module_job_config['skip_fact_check'] : false;

		// Validate required prompts - fact check prompt is only required if skip_fact_check is false
		if (empty($process_data_prompt) || empty($finalize_response_prompt)) {
			return new WP_Error('missing_prompts', __('Process data prompt and finalize response prompt are required in the module settings.', 'data-machine'));
		}
		if (!$skip_fact_check && empty($fact_check_prompt)) {
			return new WP_Error('missing_fact_check_prompt', __('Fact check prompt is required when fact checking is enabled. Either provide a fact check prompt or enable "Skip Fact Check" in the module settings.', 'data-machine'));
		}

		// --- Step 1: Initial Processing ---
		$initial_output = '';
		global $wpdb;
		$jobs_table = isset($wpdb) ? $wpdb->prefix . 'dm_jobs' : null;
		try {
		    // Use the injected instance
		    // Use $input_data_packet for logging and processing
		    $this->log_orchestrator_step('Step 1: Calling process_data', $module_id, $input_data_packet['metadata'] ?? []);
		    $enhanced_process_prompt = $this->prompt_builder->build_process_data_prompt($process_data_prompt, $input_data_packet);
		    $process_result = $this->process_data_handler->process_data($api_key, $system_prompt, $enhanced_process_prompt, $input_data_packet);
		    $this->log_orchestrator_step('Step 1: Received process_data result', $module_id, $input_data_packet['metadata'] ?? [], ['status' => $process_result['status'] ?? 'unknown', 'has_output' => !empty($process_result['json_output'])]);

		    if (isset($process_result['status']) && $process_result['status'] === 'error') {
		        throw new Exception($process_result['message'] ?? 'Unknown error during initial processing.');
		    }
		    $initial_output = $process_result['json_output'] ?? '';
		    if (empty($initial_output)) {
		        throw new Exception(__('Initial processing returned empty output.', 'data-machine'));
		    }
		} catch (Exception $e) {
		    $this->log_orchestrator_step('Step 1: Exception in process_data', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
		                    // Error logging removed for production
		    return new WP_Error('process_step_failed', $e->getMessage());
		}

		// --- Step 2: Fact Check ---
		$fact_checked_content = '';
		// START: Add conditional check for skip_fact_check (variable already declared above)

		if (!$skip_fact_check) {
		    $this->log_orchestrator_step('Step 2: Running Fact Check (skip_fact_check is false)', $module_id, $input_data_packet['metadata'] ?? []);
		    try {
		        // Use the injected instance
		        // Use $input_data_packet for logging
		        $this->log_orchestrator_step('Step 2a: Calling fact_check_response API', $module_id, $input_data_packet['metadata'] ?? []);
		        $enhanced_fact_check_prompt = $this->prompt_builder->build_fact_check_prompt($fact_check_prompt);
		        $factcheck_result = $this->factcheck_api->fact_check_response($api_key, $system_prompt, $enhanced_fact_check_prompt, $initial_output);
		        $this->log_orchestrator_step('Step 2b: Received fact_check_response result', $module_id, $input_data_packet['metadata'] ?? [], ['is_wp_error' => is_wp_error($factcheck_result), 'has_output' => !is_wp_error($factcheck_result) && !empty($factcheck_result['fact_check_results'])]);
		        // Debug: Log the raw result immediately after the API call

		        if (is_wp_error($factcheck_result)) {
		            // Use only the error message, and set code to 0 (Exception expects int)
		            throw new Exception($factcheck_result->get_error_message(), 0);
		        }
		        $fact_checked_content = $factcheck_result['fact_check_results'] ?? '';
		    } catch (Exception $e) {
		        $this->log_orchestrator_step('Step 2c: Exception in fact_check_response', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
		                            // Error logging removed for production
		        return new WP_Error('factcheck_step_failed', $e->getMessage());
		    }
		} else {
		    $this->log_orchestrator_step('Step 2: Skipping Fact Check (skip_fact_check is true)', $module_id, $input_data_packet['metadata'] ?? []);
		    // $fact_checked_content remains empty as initialized
		}
		// END: Add conditional check for skip_fact_check


		// --- Step 3: Finalize ---
		$final_output_string = '';
		try {
		    // Use the injected instance
		    // Use $input_data_packet for logging and prompt modification
		    $this->log_orchestrator_step('Step 3: Calling finalize_response', $module_id, $input_data_packet['metadata'] ?? []);
		    // Use the centralized prompt builder to create enhanced finalize prompt
		    $enhanced_finalize_prompt = $this->prompt_builder->build_finalize_prompt($finalize_response_prompt, $module_job_config, $input_data_packet);
		    $finalize_user_message = $this->prompt_builder->build_finalize_user_message($enhanced_finalize_prompt, $initial_output, $fact_checked_content, $module_job_config, $input_data_packet['metadata'] ?? []);

		    $finalize_result = $this->finalize_api->finalize_response(
		        $api_key,
		        $system_prompt,
		        $finalize_user_message,
		        '', // Initial output already included in user message
		        '', // Fact check results already included in user message
		        $module_job_config, // Pass the config array
		        $input_data_packet['metadata'] ?? [] // Pass metadata from actual packet
		    );
		    $this->log_orchestrator_step('Step 3: Received finalize_response result', $module_id, $input_data_packet['metadata'] ?? [], ['is_wp_error' => is_wp_error($finalize_result), 'has_output' => !is_wp_error($finalize_result) && !empty($finalize_result['final_output'])]);

		    if (is_wp_error($finalize_result)) {
		        // Use only the error message, and set code to 0 (Exception expects int)
		        throw new Exception($finalize_result->get_error_message(), 0);
		    }
		    $final_output_string = $finalize_result['final_output'] ?? '';
		    if (empty($final_output_string)) {
		        throw new Exception(__('Finalization returned empty output.', 'data-machine'));
		    }
		} catch (Exception $e) {
		    $this->log_orchestrator_step('Step 3: Exception in finalize_response', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
		                    // Error logging removed for production
		    return new WP_Error('finalize_step_failed', $e->getMessage());
		}


		// --- Step 4: Output Delegation ---
		$output_handler_result = null;
		$output_type = $module_job_config['output_type'] ?? null;

		try {
			// Ensure helper class is loaded
			require_once DATA_MACHINE_PATH . 'includes/helpers/class-ai-response-parser.php';

			// Get the appropriate output handler using the handler factory
            if (empty($output_type)) {
                throw new Exception('Output type is not defined in module configuration.');
            }
            
            $output_handler = $this->handler_factory->create_handler('output', $output_type);

			if ($output_handler instanceof Data_Machine_Output_Handler_Interface) {
				// Pass the simplified config array AND input metadata to the handler
				// Use $input_data_packet for metadata
				$this->log_orchestrator_step('Step 4: Calling output handler handle()', $module_id, $input_data_packet['metadata'] ?? [], ['handler_type' => $output_type]);
				$output_handler_result = $output_handler->handle( $final_output_string, $module_job_config, $user_id, $input_data_packet['metadata'] ?? [] );
				$this->log_orchestrator_step('Step 4: Received output handler result', $module_id, $input_data_packet['metadata'] ?? [], ['handler_type' => $output_type, 'is_wp_error' => is_wp_error($output_handler_result), 'result_status' => is_array($output_handler_result) ? ($output_handler_result['status'] ?? 'unknown') : 'non-array']);
			} else {
                // Log error if handler creation failed or returned wrong type
                $error_details = is_wp_error($output_handler) ? $output_handler->get_error_message() : 'Invalid handler type returned by factory.';
                $this->logger?->error('Failed to create or retrieve a valid output handler from factory.', ['output_type' => $output_type, 'error' => $error_details]);
				throw new Exception('Could not create or retrieve a valid output handler for type: ' . $output_type);
			}

			// Check if the handler returned an error
			if (is_wp_error($output_handler_result)) {
				throw new Exception($output_handler_result->get_error_message());
			}

		} catch (Exception $e) {
			$this->log_orchestrator_step('Step 4: Exception in output handling', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
			// Error logging removed for production
			$error_code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 'output_step_failed';
			return new WP_Error($error_code, $e->getMessage());
		}

		// --- Construct Final Consolidated Response ---
		// Use $input_data_packet for logging
		$this->log_orchestrator_step('Step 5: Orchestration complete, returning result', $module_id, $input_data_packet['metadata'] ?? []);
		return array(
			'status'             => 'processing-complete',
			'initial_output'     => $initial_output,
			'fact_check_results' => $fact_checked_content,
			'final_output_string'=> $final_output_string,
			'output_result'      => $output_handler_result
		);
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