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

	/** @var Data_Machine_Service_Locator */
	private $locator; // Inject locator to get output handlers

	/**
	 * Constructor. Dependencies are injected.
	 *
	 * @param Data_Machine_process_data $process_data_handler Instance of Process Data handler.
	 * @param Data_Machine_API_FactCheck $factcheck_api Instance of FactCheck API handler.
	 * @param Data_Machine_API_Finalize $finalize_api Instance of Finalize API handler.
	 * @param Data_Machine_Service_Locator $locator Service Locator instance.
	 */
	public function __construct(
		Data_Machine_process_data $process_data_handler,
		Data_Machine_API_FactCheck $factcheck_api,
		Data_Machine_API_Finalize $finalize_api,
		Data_Machine_Service_Locator $locator // Inject locator
	) {
		$this->process_data_handler = $process_data_handler;
		$this->factcheck_api = $factcheck_api;
		$this->finalize_api = $finalize_api;
		$this->locator = $locator; // Store locator
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
	public function run( array $input_data_packet, array $module_job_config, $user_id ) {

		// --- Configuration & Validation ---
		$api_key = get_option('openai_api_key');
		if (empty($api_key)) {
			return new WP_Error('missing_api_key', __('OpenAI API Key is missing.', 'data-machine'));
		}
		// Use the config array instead of the object
		if (empty($module_job_config) || !isset($module_job_config['module_id'])) {
			return new WP_Error('invalid_module_config', __('Invalid module configuration provided for job.', 'data-machine'));
		}
		$module_id = $module_job_config['module_id']; // For logging

		$process_data_prompt = $module_job_config['process_data_prompt'] ?? '';
		$fact_check_prompt = $module_job_config['fact_check_prompt'] ?? '';
		$finalize_response_prompt = $module_job_config['finalize_response_prompt'] ?? '';

		if (empty($process_data_prompt) || empty($fact_check_prompt) || empty($finalize_response_prompt)) {
			return new WP_Error('missing_prompts', __('One or more required prompts are missing in the module settings.', 'data-machine'));
		}

		// --- Step 1: Initial Processing ---
		$initial_output = '';
		try {
			// Use the injected instance
			$this->log_orchestrator_step('Step 1: Calling process_data', $module_id, $input_data_packet['metadata'] ?? []);
			$process_result = $this->process_data_handler->process_data($api_key, $process_data_prompt, $input_data_packet);
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
			error_log('Orchestrator - Process Step Error: ' . $e->getMessage() . ' | Context: ' . print_r(['module_id' => $module_id, 'metadata' => $input_data_packet['metadata'] ?? []], true));
			return new WP_Error('process_step_failed', $e->getMessage());
		}

		// --- Step 2: Fact Check ---
		$fact_checked_content = '';
		try {
			// Use the injected instance
			$this->log_orchestrator_step('Step 2: Calling fact_check_response', $module_id, $input_data_packet['metadata'] ?? []);
			$factcheck_result = $this->factcheck_api->fact_check_response($api_key, $initial_output, $fact_check_prompt);
			$this->log_orchestrator_step('Step 2: Received fact_check_response result', $module_id, $input_data_packet['metadata'] ?? [], ['is_wp_error' => is_wp_error($factcheck_result), 'has_output' => !is_wp_error($factcheck_result) && !empty($factcheck_result['fact_check_results'])]);

			if (is_wp_error($factcheck_result)) {
				throw new Exception($factcheck_result->get_error_message(), $factcheck_result->get_error_code());
			}
			$fact_checked_content = $factcheck_result['fact_check_results'] ?? '';
		} catch (Exception $e) {
			$this->log_orchestrator_step('Step 2: Exception in fact_check_response', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
			error_log('Orchestrator - FactCheck Step Error: ' . $e->getMessage() . ' | Context: ' . print_r(['module_id' => $module_id, 'metadata' => $input_data_packet['metadata'] ?? []], true));
			return new WP_Error('factcheck_step_failed', $e->getMessage());
		}


		// --- Step 3: Finalize ---
		$final_output_string = '';
		try {
			// Use the injected instance
			$this->log_orchestrator_step('Step 3: Calling finalize_response', $module_id, $input_data_packet['metadata'] ?? []);
			// Use the prompt modifier to inject category/tag instructions if needed
			$prompt_modifier = $this->locator->get('prompt_modifier');
			$modified_finalize_prompt = $prompt_modifier::modify_finalize_prompt($finalize_response_prompt, $module_job_config, $input_data_packet);

			$finalize_result = $this->finalize_api->finalize_response(
				$api_key,
				$modified_finalize_prompt,
				$process_data_prompt,
				$initial_output,
				$fact_checked_content,
				$module_job_config, // Pass the config array
				$input_data_packet['metadata'] ?? [] // Pass input metadata
			);
			$this->log_orchestrator_step('Step 3: Received finalize_response result', $module_id, $input_data_packet['metadata'] ?? [], ['is_wp_error' => is_wp_error($finalize_result), 'has_output' => !is_wp_error($finalize_result) && !empty($finalize_result['final_output'])]);

			if (is_wp_error($finalize_result)) {
				throw new Exception($finalize_result->get_error_message(), $finalize_result->get_error_code());
			}
			$final_output_string = $finalize_result['final_output'] ?? '';
			if (empty($final_output_string)) {
				throw new Exception(__('Finalization returned empty output.', 'data-machine'));
			}
		} catch (Exception $e) {
			$this->log_orchestrator_step('Step 3: Exception in finalize_response', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
			error_log('Orchestrator - Finalize Step Error: ' . $e->getMessage() . ' | Context: ' . print_r(['module_id' => $module_id, 'metadata' => $input_data_packet['metadata'] ?? []], true));
			return new WP_Error('finalize_step_failed', $e->getMessage());
		}


		// --- Step 4: Output Delegation ---
		$output_handler_result = null;
		$output_type = $module_job_config['output_type'] ?? null;
		$output_handler_key = 'output_' . $output_type; // Construct key for locator

		try {
			// Ensure helper class is loaded
			require_once DATA_MACHINE_PATH . 'includes/helpers/class-ai-response-parser.php';

			// Get the appropriate output handler from the locator
			if ($this->locator->has($output_handler_key)) {
				$output_handler = $this->locator->get($output_handler_key);
				if ($output_handler instanceof Data_Machine_Output_Handler_Interface) {
					// Pass the simplified config array AND input metadata to the handler
					$this->log_orchestrator_step('Step 4: Calling output handler handle()', $module_id, $input_data_packet['metadata'] ?? [], ['handler_key' => $output_handler_key]);
					$output_handler_result = $output_handler->handle( $final_output_string, $module_job_config, $user_id, $input_data_packet['metadata'] ?? [] );
					$this->log_orchestrator_step('Step 4: Received output handler result', $module_id, $input_data_packet['metadata'] ?? [], ['handler_key' => $output_handler_key, 'is_wp_error' => is_wp_error($output_handler_result), 'result_status' => is_array($output_handler_result) ? ($output_handler_result['status'] ?? 'unknown') : 'non-array']);
				} else {
					throw new Exception('Registered output service is not a valid handler: ' . $output_handler_key);
				}
			} else {
				throw new Exception('Unsupported or unregistered output type configured: ' . $output_type);
			}

			// Check if the handler returned an error
			if (is_wp_error($output_handler_result)) {
				throw new Exception($output_handler_result->get_error_message());
			}

		} catch (Exception $e) {
			$this->log_orchestrator_step('Step 4: Exception in output handling', $module_id, $input_data_packet['metadata'] ?? [], ['error' => $e->getMessage()]);
			error_log('Orchestrator - Output Step Error: ' . $e->getMessage() . ' | Context: ' . print_r(['module_id' => $module_id, 'output_type' => $output_type, 'metadata' => $input_data_packet['metadata'] ?? []], true));
			$error_code = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 'output_step_failed';
			return new WP_Error($error_code, $e->getMessage());
		}

		// --- Construct Final Consolidated Response ---
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
		// Use the same transient logging for now
		// TODO: Replace with a proper logging service later
		$errors = get_transient('Data_Machine_errors');
		if (!is_array($errors)) $errors = array();
		$log_entry = [
			'message' => 'Orchestrator Log: ' . $message,
			'details' => array_merge(
				['module_id' => $module_id, 'source_url' => $metadata['source_url'] ?? 'N/A'],
				$details
			),
			'time' => current_time('timestamp')
		];
		$errors[] = $log_entry;
		set_transient('Data_Machine_errors', $errors, 60 * 60); // Store for 1 hour
	}

} // End class