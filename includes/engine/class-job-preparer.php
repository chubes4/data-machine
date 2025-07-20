<?php
/**
 * Class Data_Machine_Job_Preparer
 *
 * Handles all job preparation logic including input data fetching, validation,
 * filtering, and configuration preparation. Extracted from Job Executor to
 * improve separation of concerns and maintainability.
 *
 * @package Data_Machine
 * @subpackage Engine
 */
class Data_Machine_Job_Preparer {

	/**
	 * Processed Items Database instance.
	 *
	 * @var Data_Machine_Database_Processed_Items
	 */
	private $db_processed_items;

	/**
	 * Handler Factory instance.
	 *
	 * @var Data_Machine_Handler_Factory
	 */
	private $handler_factory;

	/**
	 * Logger instance (optional).
	 * @var ?Data_Machine_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Database_Processed_Items $db_processed_items Processed Items DB service.
	 * @param Data_Machine_Handler_Factory $handler_factory Handler Factory service.
	 * @param Data_Machine_Logger|null $logger Logger service (optional).
	 */
	public function __construct(
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Handler_Factory $handler_factory,
		?Data_Machine_Logger $logger = null
	) {
		$this->db_processed_items = $db_processed_items;
		$this->handler_factory = $handler_factory;
		$this->logger = $logger;
	}

	/**
	 * Validates that all required dependencies are available.
	 *
	 * @throws Exception If any required dependency is missing.
	 */
	private function validate_dependencies(): void {
		if (!$this->db_processed_items) {
			throw new Exception('Processed Items DB service not available in Job Preparer.');
		}
		if (!$this->handler_factory) {
			throw new Exception('Handler Factory service not available in Job Preparer.');
		}
	}

	/**
	 * Safely encodes data to JSON with error handling.
	 *
	 * @param mixed $data The data to encode.
	 * @param string $context Context for error logging.
	 * @return string|WP_Error JSON string on success, WP_Error on failure.
	 */
	public function safe_json_encode($data, string $context = '') {
		$json = wp_json_encode($data);
		if ($json === false) {
			$error_msg = "Failed to encode data to JSON" . ($context ? " in {$context}" : "") . ".";
			$this->logger?->error($error_msg, ['context' => $context]);
			return new WP_Error('json_encode_error', $error_msg);
		}
		return $json;
	}

	/**
	 * Safely decodes JSON with error handling.
	 *
	 * @param string $json The JSON string to decode.
	 * @param string $context Context for error logging.
	 * @param bool $associative Whether to return associative array.
	 * @return mixed|WP_Error Decoded data on success, WP_Error on failure.
	 */
	public function safe_json_decode(string $json, string $context = '', bool $associative = true) {
		if (empty($json)) {
			return $associative ? [] : new stdClass();
		}
		
		$decoded = json_decode($json, $associative);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$error_msg = "Failed to decode JSON" . ($context ? " in {$context}" : "") . ": " . json_last_error_msg();
			$this->logger?->error($error_msg, ['context' => $context, 'json_snippet' => substr($json, 0, 200) . '...']);
			return new WP_Error('json_decode_error', $error_msg);
		}
		return $decoded;
	}

	/**
	 * Prepares module configuration by decoding JSON fields.
	 *
	 * @param object $module The module object.
	 * @return array|WP_Error Prepared configuration array on success, WP_Error on failure.
	 */
	public function prepare_job_config(object $module) {
		$this->validate_dependencies();

		$module_id = $module->module_id ?? 0;
		$this->logger?->info("Job Preparer: Preparing job config for module {$module_id}.");

		// Decode data source configuration
		$data_source_config = [];
		if (isset($module->data_source_config) && is_string($module->data_source_config)) {
			$decoded = $this->safe_json_decode($module->data_source_config, "data_source_config for module {$module_id}");
			if (is_wp_error($decoded)) {
				return $decoded;
			}
			$data_source_config = $decoded;
		}

		// Decode output configuration
		$output_config = [];
		if (isset($module->output_config) && is_string($module->output_config)) {
			$decoded = $this->safe_json_decode($module->output_config, "output_config for module {$module_id}");
			if (is_wp_error($decoded)) {
				return $decoded;
			}
			$output_config = $decoded;
		}

		// Prepare the configuration array
		$module_config_data = [
			'module_id' => $module_id,
			'module_name' => $module->module_name ?? '',
			'project_id' => $module->project_id ?? 0,
			'data_source_type' => $module->data_source_type ?? '',
			'data_source_config' => $data_source_config,
			'output_type' => $module->output_type ?? '',
			'output_config' => $output_config,
			'schedule_interval' => $module->schedule_interval ?? 'manual',
			'schedule_status' => $module->schedule_status ?? 'active',
			'process_data_prompt' => $module->process_data_prompt ?? '',
			'fact_check_prompt' => $module->fact_check_prompt ?? '',
			'finalize_prompt' => $module->finalize_prompt ?? '',
			'skip_fact_check' => $module->skip_fact_check ?? false,
		];

		return $module_config_data;
	}

	/**
	 * Gets the appropriate input handler for a module.
	 *
	 * @param object $module The module object.
	 * @return Data_Machine_Input_Handler_Interface|WP_Error Input handler on success, WP_Error on failure.
	 */
	public function get_input_handler(object $module) {
		$this->validate_dependencies();

		$data_source_type = $module->data_source_type ?? '';
		if (empty($data_source_type)) {
			return new WP_Error('missing_data_source_type', 'Module data source type is not specified.');
		}

		try {
			$input_handler = $this->handler_factory->create_input_handler($data_source_type);
			if (is_wp_error($input_handler)) {
				return $input_handler;
			}
			if (!$input_handler) {
				return new WP_Error('handler_not_found', "Input handler for type '{$data_source_type}' not found.");
			}
			return $input_handler;
		} catch (Exception $e) {
			return new WP_Error('handler_creation_failed', "Failed to create input handler: " . $e->getMessage());
		}
	}

	/**
	 * Fetches input data using the appropriate input handler.
	 *
	 * @param Data_Machine_Input_Handler_Interface $input_handler The input handler.
	 * @param object $module The module object.
	 * @param int $user_id The user ID.
	 * @return array|WP_Error Input data array on success, WP_Error on failure.
	 */
	public function fetch_input_data(Data_Machine_Input_Handler_Interface $input_handler, object $module, int $user_id) {
		$this->validate_dependencies();

		$module_id = $module->module_id ?? 0;
		$this->logger?->info("Job Preparer: Fetching input data for module {$module_id}.");

		try {
			// Decode data source configuration
			$data_source_config = [];
			if (isset($module->data_source_config) && is_string($module->data_source_config)) {
				$decoded = $this->safe_json_decode($module->data_source_config, "module data_source_config for module {$module_id}");
				if (is_wp_error($decoded)) {
					return $decoded;
				}
				$data_source_config = $decoded;
			}

			// Fetch input data
			$input_data = $input_handler->get_input_data($module, $data_source_config, $user_id);

			// Check for no new items
			if (is_array($input_data) && isset($input_data['status']) && $input_data['status'] === 'no_new_items') {
				$this->logger?->info("Job Preparer: No new items found for module {$module_id}.");
				return $input_data;
			}

			// Validate input data format
			if (!is_array($input_data)) {
				return new WP_Error('invalid_input_data', 'Input handler returned invalid data format.');
			}

			$this->logger?->info("Job Preparer: Successfully fetched " . count($input_data) . " items for module {$module_id}.");
			return $input_data;

		} catch (Exception $e) {
			$error_message = "Error fetching input data for module {$module_id}: " . $e->getMessage();
			$this->logger?->error($error_message, ['module_id' => $module_id, 'error' => $e->getMessage()]);
			return new WP_Error('input_fetch_failed', $error_message);
		}
	}

	/**
	 * Extracts a unique identifier from an item based on source type.
	 *
	 * @param array $item The item data.
	 * @param string $source_type The source type.
	 * @param int|null $module_id The module ID (for logging).
	 * @param string|null $context The context (for logging).
	 * @return string|null The item identifier or null if not found.
	 */
	public function extract_item_identifier(array $item, string $source_type, ?int $module_id = null, ?string $context = null): ?string {
		$log_context = ['source_type' => $source_type, 'module_id' => $module_id, 'context' => $context];

		// Check for explicit identifier in metadata
		if (isset($item['metadata']['item_identifier_to_log'])) {
			return (string) $item['metadata']['item_identifier_to_log'];
		}

		// Try source-specific identifier patterns
		switch ($source_type) {
			case 'rss':
				$identifier = $item['metadata']['guid'] ?? $item['metadata']['source_url'] ?? null;
				break;
			case 'reddit':
				$identifier = $item['metadata']['original_id'] ?? null;
				break;
			case 'rest_api':
				$identifier = $item['metadata']['original_id'] ?? $item['metadata']['source_url'] ?? null;
				break;
			case 'files':
				$identifier = $item['metadata']['file_hash'] ?? $item['metadata']['file_name'] ?? null;
				break;
			default:
				$identifier = $item['metadata']['original_id'] ?? $item['metadata']['source_url'] ?? null;
		}

		if (empty($identifier)) {
			$this->logger?->warning("Job Preparer: Could not extract identifier for item.", $log_context);
			return null;
		}

		return (string) $identifier;
	}

	/**
	 * Filters out items that have already been processed.
	 *
	 * @param array $input_data The input data array.
	 * @param object $module The module object.
	 * @return array|WP_Error Filtered items array on success, WP_Error on failure.
	 */
	public function filter_processed_items(array $input_data, object $module) {
		$this->validate_dependencies();

		$module_id = $module->module_id ?? 0;
		$source_type = $module->data_source_type ?? '';
		
		$this->logger?->info("Job Preparer: Filtering processed items for module {$module_id}.");

		$items_to_process = [];
		$processed_count = 0;
		$invalid_count = 0;

		foreach ($input_data as $item) {
			if (!is_array($item)) {
				$this->logger?->warning("Job Preparer: Skipping non-array item.", ['module_id' => $module_id]);
				$invalid_count++;
				continue;
			}

			$item_identifier = $this->extract_item_identifier($item, $source_type, $module_id, 'filter_processed_items');
			
			if (empty($item_identifier)) {
				$this->logger?->warning("Job Preparer: Skipping item due to missing identifier.", ['module_id' => $module_id]);
				$invalid_count++;
				continue;
			}

			// Check if item has been processed
			if (!$this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier)) {
				$items_to_process[] = $item;
			} else {
				$this->logger?->info("Job Preparer: Skipping already processed item.", [
					'module_id' => $module_id,
					'item_identifier' => $item_identifier
				]);
				$processed_count++;
			}
		}

		$this->logger?->info("Job Preparer: Filtering complete.", [
			'module_id' => $module_id,
			'total_items' => count($input_data),
			'items_to_process' => count($items_to_process),
			'already_processed' => $processed_count,
			'invalid_items' => $invalid_count
		]);

		return $items_to_process;
	}

	/**
	 * Prepares a complete job data packet ready for execution.
	 *
	 * @param object $module The module object.
	 * @param int $user_id The user ID.
	 * @param array|null $pre_fetched_items Pre-fetched items (for file uploads).
	 * @return array|WP_Error Job data packet on success, WP_Error on failure.
	 */
	public function prepare_job_packet(object $module, int $user_id, ?array $pre_fetched_items = null) {
		$this->validate_dependencies();

		$module_id = $module->module_id ?? 0;
		$this->logger?->info("Job Preparer: Preparing job packet for module {$module_id}.");

		try {
			// 1. Prepare job configuration
			$job_config = $this->prepare_job_config($module);
			if (is_wp_error($job_config)) {
				return $job_config;
			}

			// 2. Handle input data
			$input_data = null;
			
			if ($pre_fetched_items !== null) {
				// Use pre-fetched items (file uploads)
				$this->logger?->info("Job Preparer: Using pre-fetched items for module {$module_id}.");
				$input_data = $pre_fetched_items;
			} else {
				// Fetch input data dynamically
				$input_handler = $this->get_input_handler($module);
				if (is_wp_error($input_handler)) {
					return $input_handler;
				}

				$input_data = $this->fetch_input_data($input_handler, $module, $user_id);
				if (is_wp_error($input_data)) {
					return $input_data;
				}

				// Check for no new items
				if (is_array($input_data) && isset($input_data['status']) && $input_data['status'] === 'no_new_items') {
					return $input_data;
				}
			}

			// 3. Filter processed items
			$items_to_process = $this->filter_processed_items($input_data, $module);
			if (is_wp_error($items_to_process)) {
				return $items_to_process;
			}

			// 4. Check if there are items to process
			if (empty($items_to_process)) {
				$this->logger?->info("Job Preparer: No new items to process for module {$module_id}.");
				return ['status' => 'no_new_items', 'message' => 'No new items found to process.'];
			}

			// 5. Prepare final job packet
			$job_packet = [
				'module_config' => $job_config,
				'input_data' => $items_to_process,
				'user_id' => $user_id,
				'module_id' => $module_id,
				'status' => 'ready'
			];

			$this->logger?->info("Job Preparer: Job packet prepared successfully.", [
				'module_id' => $module_id,
				'items_count' => count($items_to_process)
			]);

			return $job_packet;

		} catch (Exception $e) {
			$error_message = "Error preparing job packet for module {$module_id}: " . $e->getMessage();
			$this->logger?->error($error_message, ['module_id' => $module_id, 'error' => $e->getMessage()]);
			return new WP_Error('job_preparation_failed', $error_message);
		}
	}
}