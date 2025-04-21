<?php

/**
 * Class Data_Machine_Job_Executor
 *
 * Centralizes job execution logic for Data Machine, ensuring consistency across
 * manual, cron-based, and "Process Now" job initiations.
 *
 * @package Data_Machine
 * @subpackage Engine
 */
class Data_Machine_Job_Executor {

	/**
	 * Service Locator instance.
	 *
	 * @var Data_Machine_Service_Locator
	 */
	private $locator;

	/**
	 * Processed Items Database instance.
	 *
	 * @var Data_Machine_Database_Processed_Items
	 */
	private $db_processed_items;

	/**
	 * Jobs Database instance.
	 * @var Data_Machine_Database_Jobs
	 */
	private $db_jobs;

	/**
	 * Processing Orchestrator instance.
	 * @var Data_Machine_Processing_Orchestrator
	 */
	private $processing_orchestrator;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Service_Locator $locator Service Locator instance for dependency injection.
	 */
	public function __construct(Data_Machine_Service_Locator $locator) {
		$this->locator = $locator;
		// Get dependencies from the locator
		$this->db_processed_items = $this->locator->get('database_processed_items');
		$this->db_jobs = $this->locator->get('database_jobs');
		$this->processing_orchestrator = $this->locator->get('orchestrator');
	}

	/**
	 * Executes a job for a given module.
	 *
	 * Main method responsible for orchestrating the entire job execution process.
	 *
	 * @param object $module The module object for which the job is being executed.
	 * @param int    $user_id The ID of the user initiating the job (project owner).
	 * @param string $context Context of job execution ('manual', 'cron_project', 'cron_module', 'process_now').
	 * @param array|null $pre_fetched_input_data Optional pre-fetched input data array. If provided, skips internal fetching.
	 * @return int|WP_Error Job ID on successful job creation and scheduling, WP_Error object on failure.
	 */
	public function execute_job(object $module, int $user_id, string $context, ?array $pre_fetched_input_data = null) {
		$logger = $this->locator->get('logger'); // Get logger

		try {
			// 1. Input Data Acquisition
			$input_data = null;
			if ($pre_fetched_input_data !== null) {
				// Use pre-fetched data if provided (e.g., from AJAX handler for file uploads)
				$input_data = $pre_fetched_input_data;
				$logger->info("Using pre-fetched input data.", ['module_id' => $module->module_id, 'context' => $context]);
			} else {
				// Fetch data using the appropriate input handler
				$input_handler = $this->get_input_handler($module);
				if (!$input_handler) {
					throw new Exception("Could not load input handler for type: {$module->data_source_type}");
				}
				$input_data = $this->fetch_input_data($input_handler, $module, $user_id);
				if (is_wp_error($input_data)) {
					return $input_data; // Return WP_Error directly
				}
			}

			// --- START: Explicit check for 'no_new_items' status --- 
			if (is_array($input_data) && isset($input_data['status']) && $input_data['status'] === 'no_new_items') {
			    $logger->info("Input handler returned no new items.", [
			        'module_id' => $module->module_id,
			        'context' => $context,
			        'message' => $input_data['message'] ?? 'N/A'
			    ]);
			    return 0; // Indicate no job was created
			}
			// --- END: Explicit check ---

			// Ensure we actually got data (array of items) - this check is now secondary but still useful for totally empty returns or non-array data
			if (empty($input_data) || !is_array($input_data)) { 
			    $logger->info("Input handler returned no data or unexpected format.", ['module_id' => $module->module_id, 'context' => $context, 'returned_data_type' => gettype($input_data)]);
			    return 0; // Indicate no job was created as there was no input
			}

			// --- START: Filter out already processed items ---
			$items_to_process = [];
			$source_type = $module->data_source_type;
			$module_id = $module->module_id;

			if (!$this->db_processed_items) {
				$logger->error("Database service for processed items (database_processed_items) not found.", ['module_id' => $module_id]);
				// Decide whether to proceed without check or return error. Returning error is safer.
				return new WP_Error('service_not_found', __('Database service for processed items is unavailable.', 'data-machine'));
			}

			foreach ($input_data as $item) {
			    // --- START: Validate item structure ---
			    // Ensure the current item is actually an array before proceeding
			    if (!is_array($item)) {
			        $logger->warning("Skipping non-array item found in input data.", [
			            'module_id' => $module_id,
			            'context' => $context,
			            'item_type' => gettype($item)
			        ]);
			        continue; // Skip to the next item in the input data array
			    }
			    // --- END: Validate item structure ---

				$item_identifier = null;

				// Determine the unique identifier based on source type
				switch ($source_type) {
					case 'rss':
						// Corrected: Look inside the metadata array where the handler places it
						$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
						break;
					case 'reddit':
						// Use the specific key confirmed from Data_Machine_Input_Reddit
						$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
						break;
					case 'public_rest_api':
						// Look inside the metadata array where the handler places it
						$item_identifier = $item['metadata']['item_identifier_to_log'] ?? $item['metadata']['original_id'] ?? null;
						break;
					case 'files':
						// Look inside the metadata array where the handler places it (persistent_path)
						$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
						break;
					case 'airdrop_rest_api':
						// Look for the identifier at the top level
						$item_identifier = $item['item_identifier'] ?? null;
						break;
					// Add cases for other known source types here
					// case 'some_other_source':
					// 	$item_identifier = $item['unique_field'] ?? null;
					// 	break;
					default:
						// Attempt common fallback identifiers if type is unknown or not explicitly handled
						$item_identifier = $item['id'] ?? $item['guid'] ?? $item['url'] ?? $item['link'] ?? null;
						// If it's still null, log a warning
						if (is_null($item_identifier)) {
							$logger->warning("Could not determine unique identifier for unknown source type or item structure.", [
								'module_id' => $module_id,
								'source_type' => $source_type,
								'item_keys' => array_keys($item) // Log available keys for debugging
							]);
						}
				}

				// Ensure we have a non-empty identifier
				if (empty($item_identifier)) {
					$logger->warning("Skipping item due to missing or empty identifier after checking source type.", [
						'module_id' => $module_id,
						'source_type' => $source_type,
						'item_keys' => array_keys($item)
					]);
					continue; // Skip items where identifier couldn't be determined
				}

				// Check if item has already been processed
				if (!$this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier)) {
					// Item is new, add it to the list to be processed
					$items_to_process[] = $item;
				} else {
					// Item already processed, log for debugging if needed
					$logger->info("Skipping already processed item.", ['module_id' => $module_id, 'source_type' => $source_type, 'item_identifier' => $item_identifier]);
				}
			}

			// If no new items are left after filtering, don't create a job
			if (empty($items_to_process)) {
				$logger->info("No new items to process after filtering duplicates.", ['module_id' => $module_id, 'context' => $context]);
				return 0; // Indicate no job was created
			}
			// --- END: Filter out already processed items ---

			// 3. Prepare Job Config
			$module_config_data = $this->prepare_job_config($module);
			if (is_wp_error($module_config_data)) {
				return $module_config_data; // Return WP_Error
			}

			// 4. Create Job in DB and Schedule (Pass the filtered list)
			$job_id = $this->create_and_schedule_job_event($module, $user_id, $module_config_data, null);

			if (is_wp_error($job_id)) {
				return $job_id; // Return WP_Error
			}

			return $job_id; // Return Job ID on success

		} catch (Exception $e) {
			$error_message = "Error executing job: " . $e->getMessage();
			$logger->error($error_message, ['module_id' => $module->module_id ?? 'unknown', 'context' => $context ?? 'unknown', 'error' => $e->getMessage()]); // Ensure module_id and context are set or default
			return new WP_Error('job_execution_error', $error_message);
		}
	}

	/**
	 * Gets the appropriate input handler for the module's data source type.
	 *
	 * @param object $module The module object.
	 * @return Data_Machine_Input_Handler_Interface|null Instance of the input handler, or null if not found.
	 */
	private function get_input_handler(object $module) {
		$handler_key = 'input_' . $module->data_source_type;
		$handler = $this->locator->get($handler_key);

		if ($handler instanceof Data_Machine_Input_Handler_Interface) {
			return $handler;
		}

		// Optionally log an error if the handler is not found or not the correct type
		$logger = $this->locator->get('logger');
		if ($logger) {
			$logger->warning("Could not find or load a valid input handler for type: {$module->data_source_type}", ['module_id' => $module->module_id]);
		}

		return null;
	}

	/**
	 * Fetches input data using the provided input handler.
	 *
	 * @param Data_Machine_Input_Handler_Interface $input_handler Instance of the input handler.
	 * @param object $module The module object.
	 * @param int    $user_id The ID of the user initiating the job.
	 * @return array|WP_Error An array of input data items, or a WP_Error object on failure.
	 */
	private function fetch_input_data(Data_Machine_Input_Handler_Interface $input_handler, object $module, int $user_id) {
		try {
			// Decode the data source config from the module object
			$source_config_decoded = json_decode(wp_unslash($module->data_source_config ?? '{}'), true) ?: [];

			// Prepare arguments for the handler's get_input_data method
			// Pass module_id within the $post_data argument for compatibility with handlers expecting it there
			$post_data_arg = ['module_id' => $module->module_id]; 
			$files_data_arg = []; // Empty array for files data in non-file contexts

			// Call the handler with the expected signature
			$input_data = $input_handler->get_input_data($post_data_arg, $files_data_arg, $source_config_decoded, $user_id);

			if (is_wp_error($input_data)) {
				$logger = $this->locator->get('logger');
				if ($logger) {
					$logger->error("Error fetching input data: " . $input_data->get_error_message(), [
						'module_id' => $module->module_id,
						'error_code' => $input_data->get_error_code(),
					]);
				}
				return $input_data; // Propagate WP_Error
			}

			// Ensure the result is an array
			if (!is_array($input_data)) {
				throw new Exception('Input handler did not return an array or WP_Error.');
			}

			return $input_data;

		} catch (Exception $e) {
			$logger = $this->locator->get('logger');
			if ($logger) {
				$logger->error("Exception during input data fetch: " . $e->getMessage(), ['module_id' => $module->module_id]);
			}
			return new WP_Error('input_data_fetch_exception', $e->getMessage());
		}
	}

	/**
	 * Prepares the JSON encoded module configuration string.
	 *
	 * @param object $module The module object.
	 * @return string|WP_Error JSON encoded module configuration string, or a WP_Error object on failure.
	 */
	private function prepare_job_config(object $module) {
		try {
			$module_job_config = [
				'module_id' => $module->module_id ?? null,
				'project_id' => $module->project_id ?? null,
				'module_name' => $module->module_name ?? '',
				'process_data_prompt' => $module->process_data_prompt ?? '',
				'fact_check_prompt' => $module->fact_check_prompt ?? '',
				'finalize_response_prompt' => $module->finalize_response_prompt ?? '',
				'data_source_type' => $module->data_source_type ?? '',
				// Decode nested JSON config strings into arrays before encoding the main config
				'data_source_config' => json_decode(wp_unslash($module->data_source_config ?? '{}'), true) ?: [],
				'output_type' => $module->output_type ?? '',
				'output_config' => json_decode(wp_unslash($module->output_config ?? '{}'), true) ?: [],
			];

			// --- Log prepared config array ---
			$logger = $this->locator->get('logger');
			if ($logger) {
				// Log keys or a limited depth to avoid excessively large logs
				$loggable_config = $module_job_config; // Copy to modify for logging
				if (isset($loggable_config['process_data_prompt'])) $loggable_config['process_data_prompt'] = substr($loggable_config['process_data_prompt'], 0, 100) . '...';
				if (isset($loggable_config['fact_check_prompt'])) $loggable_config['fact_check_prompt'] = substr($loggable_config['fact_check_prompt'], 0, 100) . '...';
				if (isset($loggable_config['finalize_response_prompt'])) $loggable_config['finalize_response_prompt'] = substr($loggable_config['finalize_response_prompt'], 0, 100) . '...';
				$logger->debug("Prepared job config array (before encoding)", ['module_id' => $module->module_id ?? 'unknown', 'config_keys' => array_keys($loggable_config), 'config_structure' => $loggable_config]); // Log structure/keys
			}
			// --- End log ---

			return $module_job_config; // Return the array directly

		} catch (Exception $e) {
			$logger = $this->locator->get('logger');
			if ($logger) {
				$logger->error("Exception during job config preparation: " . $e->getMessage(), ['module_id' => $module->module_id ?? 'unknown']);
			}
			return new WP_Error('job_config_prepare_exception', $e->getMessage());
		}
	}

	/**
	 * Creates and schedules a job initiated from module configuration.
	 *
	 * Called by 'Run Now', Scheduler callbacks.
	 * Creates a job record *without* input data and schedules the background event.
	 *
	 * @param object $module The module object.
	 * @param int    $user_id The user ID (project owner).
	 * @param string $context Context ('run_now', 'cron_project', 'cron_module').
	 * @return int|WP_Error Job ID on success, WP_Error on failure.
	 */
	public function schedule_job_from_config(object $module, int $user_id, string $context) {
		$logger = $this->locator->get('logger');
		try {
			// Prepare config returns an array now
			$module_config_data = $this->prepare_job_config($module);
			if (is_wp_error($module_config_data)) {
				return $module_config_data;
			}

			// Call helper to create DB record and schedule event (pass null for items_to_process_json)
			// Pass the config array directly
			$job_id = $this->create_and_schedule_job_event($module, $user_id, $module_config_data, null);

			if (is_wp_error($job_id)) {
				$logger->error("Failed to create/schedule config-based job.", ['module_id' => $module->module_id, 'context' => $context, 'error' => $job_id->get_error_message()]);
				return $job_id;
			}

			$logger->info("Scheduled config-based job.", ['module_id' => $module->module_id, 'context' => $context, 'job_id' => $job_id]);
			return $job_id;

		} catch (Exception $e) {
			$logger->error("Exception scheduling config-based job: " . $e->getMessage(), ['module_id' => $module->module_id, 'context' => $context]);
			return new WP_Error('schedule_config_job_exception', $e->getMessage());
		}
	}

	/**
	 * Creates and schedules a job initiated from a manual file upload.
	 *
	 * Called by the 'Process' button AJAX handler for 'files' type modules.
	 * Creates a job record *with* the pre-processed file input data packet.
	 *
	 * @param object $module The module object.
	 * @param int    $user_id The user ID (project owner).
	 * @param string $context Context ('manual_file').
	 * @param array  $file_input_data_packet The input data packet derived from the uploaded file.
	 * @return int|WP_Error Job ID on success, WP_Error on failure.
	 */
	public function schedule_job_from_file(object $module, int $user_id, string $context, array $file_input_data_packet) {
		$logger = $this->locator->get('logger');
		try {
			// Ensure the packet isn't empty
			if (empty($file_input_data_packet)) {
				throw new Exception("File input data packet cannot be empty.");
			}

			// Mark the file as processed immediately (using the identifier from the packet)
			$source_type = $module->data_source_type;
			$module_id = $module->module_id;
			$item_identifier = $file_input_data_packet['metadata']['item_identifier_to_log'] ?? null;

			if (empty($item_identifier)) {
				$logger->warning("Could not determine identifier from file input packet.", ['module_id' => $module_id, 'context' => $context]);
				// Proceed with scheduling, but log warning. Downstream processing might fail without identifier.
			} elseif ($this->db_processed_items) {
				$marked = $this->db_processed_items->add_processed_item($module_id, $source_type, $item_identifier);
				if (!$marked) {
					$logger->warning("Failed to mark file item as processed in database.", ['module_id' => $module_id, 'source_type' => $source_type, 'item_identifier' => $item_identifier]);
				}
			} else {
				$logger->error("Processed items DB service not available for marking file item.", ['module_id' => $module_id]);
			}

			// Prepare config returns an array now
			$module_config_data = $this->prepare_job_config($module);
			if (is_wp_error($module_config_data)) {
				return $module_config_data;
			}

			// JSON encode the single file packet (needs to be in an array structure like other inputs)
			$items_to_process_json = wp_json_encode([$file_input_data_packet]);
			if (false === $items_to_process_json) {
				throw new Exception("Failed to JSON encode file input data packet: " . json_last_error_msg());
			}

			// Call helper to create DB record and schedule event
			// Pass the config array directly
			$job_id = $this->create_and_schedule_job_event($module, $user_id, $module_config_data, $items_to_process_json);

			if (is_wp_error($job_id)) {
				$logger->error("Failed to create/schedule file-based job.", ['module_id' => $module->module_id, 'context' => $context, 'error' => $job_id->get_error_message()]);
				return $job_id;
			}

			$logger->info("Scheduled file-based job.", ['module_id' => $module->module_id, 'context' => $context, 'job_id' => $job_id]);
			return $job_id;

		} catch (Exception $e) {
			$logger->error("Exception scheduling file-based job: " . $e->getMessage(), ['module_id' => $module->module_id, 'context' => $context]);
			return new WP_Error('schedule_file_job_exception', $e->getMessage());
		}
	}

	/**
	 * Executes a scheduled job.
	 *
	 * This method is hooked to the 'dm_run_job_event' action.
	 * It loads the job, fetches input data IF needed (for config-based jobs),
	 * filters items, calls the Processing Orchestrator for each item, and updates job status.
	 *
	 * @param int $job_id The ID of the job to execute.
	 */
	public function run_scheduled_job(int $job_id) {
		$logger = $this->locator->get('logger');
		$logger->info("Running scheduled job...", ['job_id' => $job_id]);

		$job = null;
		$module = null;
		$module_job_config = null;
		$final_job_status = 'failed'; // Default to failed unless explicitly set otherwise

		try {
			// 1. Load Job Data
			if (!$this->db_jobs) throw new Exception("Jobs DB service not available.");
			$job = $this->db_jobs->get_job($job_id);
			if (!$job) throw new Exception("Job record not found.");

			// Update job status to 'running' using the correct method
			$this->db_jobs->start_job($job_id, 'running');

			// --- Log raw config from DB before decoding ---
			$raw_config_from_db = $job->module_config ?? '[CONFIG NOT FOUND ON JOB OBJECT]';
			$logger->debug("Raw module_config from DB for job", ['job_id' => $job_id, 'raw_config' => $raw_config_from_db]);
			// --- End log ---

			// Decode module config right away - Remove wp_unslash as it corrupts the JSON escapes
			$module_job_config = json_decode($job->module_config ?? '{}', true);
			// --- Log JSON decode error ---
			$json_decode_error = json_last_error_msg();
			if ($json_decode_error !== 'No error') {
				$logger->warning("JSON decode error for module_config", ['job_id' => $job_id, 'error_message' => $json_decode_error]);
			}
			// --- End log ---
			if (empty($module_job_config) || !isset($module_job_config['module_id'])) {
				// Log the problematic config if decode seemed okay but validation failed
				if ($json_decode_error === 'No error') {
					$logger->warning("Module config validation failed despite successful JSON decode.", ['job_id' => $job_id, 'decoded_config_keys' => is_array($module_job_config) ? array_keys($module_job_config) : gettype($module_job_config)]);
				}
				throw new Exception("Invalid or missing module config in job record.");
			}
			$module_id = $module_job_config['module_id'];
			$user_id = $job->user_id;

			// 2. Determine Items to Process
			$items_to_process = [];
			$initial_items_json = $job->input_data; // Assuming this field holds the data

			if (!empty($initial_items_json)) {
				// File-based job: Data was pre-fetched and stored
				$logger->info("Job has pre-stored input data (likely file-based).", ['job_id' => $job_id, 'module_id' => $module_id]);
				$decoded_items = json_decode($initial_items_json, true);
				if (is_array($decoded_items)) {
					// File-based jobs only have one item
					$items_to_process = $decoded_items;
				} else {
					$logger->warning("Failed to decode pre-stored input data.", ['job_id' => $job_id, 'module_id' => $module_id]);
				}
			} else {
				// Config-based job: Fetch and filter data now
				$logger->info("Fetching input data for config-based job.", ['job_id' => $job_id, 'module_id' => $module_id]);

				// Load the module object (needed for input handler)
				$db_modules = $this->locator->get('database_modules');
				if (!$db_modules) throw new Exception("Modules DB service not available.");
				$module = $db_modules->get_module($module_id);
				if (!$module) throw new Exception("Module object not found for ID: {$module_id}");

				$input_handler = $this->get_input_handler($module);
				if (!$input_handler) {
					throw new Exception("Could not load input handler for type: {$module->data_source_type}");
				}

				$fetched_data = $this->fetch_input_data($input_handler, $module, $user_id);

				if (is_wp_error($fetched_data)) {
					throw new Exception("Error fetching input data: " . $fetched_data->get_error_message());
				}
				if (is_array($fetched_data) && isset($fetched_data['status']) && $fetched_data['status'] === 'no_new_items') {
					$logger->info("Input handler returned no new items during job execution.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items';
					// Leave items_to_process empty
				} elseif (empty($fetched_data) || !is_array($fetched_data)) {
					$logger->warning("Input handler returned no data or unexpected format during job execution.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items'; // Treat as no items
					// Leave items_to_process empty
				} else {
					// --- START: Apply item_count limit from config --- 
					$data_source_config = $module_job_config['data_source_config'] ?? [];
					// Find the key for item count (handle potential inconsistencies, e.g., 'item_count' vs 'reddit.item_count')
					$item_count_limit = 1; // Default to 1 if not found
					if (isset($data_source_config['item_count'])) {
						$item_count_limit = max(1, absint($data_source_config['item_count']));
					} elseif (isset($module_job_config['data_source_type'])) {
						$source_type = $module_job_config['data_source_type'];
						if (isset($data_source_config[$source_type]['item_count'])) { // Check for nested key like 'reddit' => ['item_count' => N]
							$item_count_limit = max(1, absint($data_source_config[$source_type]['item_count']));
						}
					}

					if (count($fetched_data) > $item_count_limit) {
						$logger->info("Applying item_count limit ({$item_count_limit}) to fetched data.", ['job_id' => $job_id, 'module_id' => $module_id, 'original_count' => count($fetched_data)]);
						$fetched_data = array_slice($fetched_data, 0, $item_count_limit);
					}
					// --- END: Apply item_count limit --- 

					// Filter fetched data (now potentially limited by item_count)
					$items_to_process = $this->filter_processed_items($fetched_data, $module);
					if (empty($items_to_process)) {
						$logger->info("No new items found after filtering (and applying item_count limit).", ['job_id' => $job_id, 'module_id' => $module_id]);
						$final_job_status = 'completed_no_items';
					}
				}
			}

			// 3. Process Items
			if (!empty($items_to_process)) {
				$processed_successfully = 0;
				$processing_errors = 0;
				$total_items = count($items_to_process);
				$logger->info("Starting processing for {$total_items} item(s).", ['job_id' => $job_id, 'module_id' => $module_id]);

				foreach ($items_to_process as $index => $item_packet) {
					$item_log_id = $item_packet['metadata']['item_identifier_to_log'] ?? ('item_' . ($index + 1));
					$logger->info("Processing item {$item_log_id}...", ['job_id' => $job_id, 'module_id' => $module_id]);

					// --- Add log here ---
					$logger->debug("Item packet structure being passed to orchestrator", [
						'job_id' => $job_id,
						'module_id' => $module_id,
						'item_log_id' => $item_log_id,
						// Use wp_json_encode for better readability and depth control if needed
						'item_packet_json' => wp_json_encode($item_packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
						// Fallback if JSON encode fails or for very complex structures
						'item_packet_print_r' => print_r($item_packet, true) 
					]);
					// --- End log ---

					if (!$this->processing_orchestrator) throw new Exception("Processing Orchestrator not available.");

					$orchestrator_result = $this->processing_orchestrator->run($item_packet, $module_job_config, $user_id, $job_id);

					if (is_wp_error($orchestrator_result)) {
						$processing_errors++;
						$logger->error("Error processing item {$item_log_id}: " . $orchestrator_result->get_error_message(), ['job_id' => $job_id, 'module_id' => $module_id, 'error_code' => $orchestrator_result->get_error_code()]);
						// Decide: Stop job on first error, or continue with other items?
						// For now, let's continue processing other items but mark job as failed overall if any item fails.
						$final_job_status = 'failed';
					} else {
						$processed_successfully++;
						$logger->info("Successfully processed item {$item_log_id}.", ['job_id' => $job_id, 'module_id' => $module_id, 'output_status' => $orchestrator_result['output_result']['status'] ?? 'unknown']);

						// --- START: Mark item as processed AFTER successful processing ---
						$source_type = $module_job_config['data_source_type'] ?? null;
						// Use the new helper method to get the identifier
						$item_identifier = $this->_get_item_identifier($item_packet, $source_type, $module_id, 'run_scheduled_job_post_process');

						// Ensure we have a non-empty identifier before marking
						if (!empty($item_identifier) && $this->db_processed_items && $source_type) { // Also check source_type is known
							$marked = $this->db_processed_items->add_processed_item($module_id, $source_type, $item_identifier);
							if (!$marked) {
								$logger->warning("Failed to mark successfully processed item in database.", [
									'job_id' => $job_id,
									'module_id' => $module_id,
									'source_type' => $source_type,
									'item_identifier' => $item_identifier,
									'last_db_error' => $this->locator->get('wpdb') ? $this->locator->get('wpdb')->last_error : 'WPDB not available in locator'
								]);
							} else {
								$logger->debug("Successfully marked item as processed in database.", [
									'job_id' => $job_id,
									'module_id' => $module_id,
									'item_identifier' => $item_identifier
								]);
							}
						} elseif (empty($item_identifier)) {
							$logger->warning("Could not determine identifier for successfully processed item, cannot mark as processed.", [
								'job_id' => $job_id,
								'module_id' => $module_id,
								'source_type' => $source_type,
								'item_packet_keys' => isset($item_packet['data']) && is_array($item_packet['data']) ? array_keys($item_packet['data']) : 'N/A'
							]);
						}
						// --- END: Mark item as processed ---
					}
				}

				// Determine final status based on item processing outcomes
				if ($processing_errors === 0 && $processed_successfully > 0) {
					$final_job_status = 'completed';
				} elseif ($processed_successfully > 0 && $processing_errors > 0) {
					$final_job_status = 'completed_with_errors'; // Or keep as 'failed'?
				} else {
					// No successes, keep default 'failed' status
				}
				$logger->info("Finished processing items.", ['job_id' => $job_id, 'module_id' => $module_id, 'total' => $total_items, 'success' => $processed_successfully, 'errors' => $processing_errors]);

			} else {
				// No items to process (either fetched none, or filtered all out)
				// Status should already be 'completed_no_items' if set earlier
				if ($final_job_status !== 'completed_no_items') {
					$logger->info("No items to process for job.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items'; // Ensure status is correct
				}
			}

			// 4. Final Job Update - use complete_job
			// $this->db_jobs->update_job_status($job_id, $final_job_status); // INCORRECT CALL
			// Prepare result data for completion (can be null if no items were processed)
			$result_data_for_db = null;
			if ($final_job_status === 'completed' || $final_job_status === 'completed_with_errors') {
				// In a real scenario, you might want to aggregate results or errors here.
				// For now, we'll just store a success/error indicator based on the status.
				$result_data_for_db = json_encode(['status' => $final_job_status, 'message' => 'Job finished processing.']);
			} elseif ($final_job_status === 'completed_no_items') {
				// Store a message for no items status
				$result_data_for_db = json_encode(['status' => $final_job_status, 'message' => 'No new items found to process.']);
			}

			// --- START: Add Detailed Logging Before complete_job Call ---
			$logger->debug("Preparing to call complete_job.", [
				'job_id' => $job_id,
				'final_job_status_value' => $final_job_status,
				'final_job_status_type' => gettype($final_job_status),
				'result_data_for_db_type' => gettype($result_data_for_db),
				'result_data_for_db_value' => (is_string($result_data_for_db) ? substr($result_data_for_db, 0, 200) . '...' : $result_data_for_db) // Log snippet or value
			]);
			// --- END: Add Detailed Logging ---

			// Call complete_job - Note: returns bool, could check it.
			$db_updated = $this->db_jobs->complete_job($job_id, $final_job_status, $result_data_for_db);
			// Log completion status
			$logger->info("Job execution finished.", ['job_id' => $job_id, 'module_id' => $module_job_config['module_id'] ?? 'N/A', 'final_status' => $final_job_status, 'db_update_success' => (bool)$db_updated]);

			// --- START: Update DB with fetched/filtered input data ---
			if (!empty($items_to_process)) {
				$input_json_for_db = json_encode($items_to_process);
				if ($input_json_for_db !== false) {
					$this->db_jobs->update_job_input_data($job_id, $input_json_for_db);
				} else {
					$logger->warning('Failed to JSON encode items_to_process before updating job input_data.', ['job_id' => $job_id, 'module_id' => $module_id]);
				}
			}
			// --- END: Update DB input_data ---

		} catch (Exception $e) {
			$error_message = "Critical error during job execution: " . $e->getMessage();
			$logger->error($error_message, ['job_id' => $job_id, 'module_id' => $module_job_config['module_id'] ?? 'N/A', 'trace' => $e->getTraceAsString()]);
			// Ensure status is marked as failed if exception occurs before final update
			if ($job && $this->db_jobs) {
				// Prepare error data for storage
				$error_data_for_db = json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
				$this->db_jobs->complete_job($job_id, 'failed', $error_data_for_db);
				// Optionally add error message to job record if a field exists
				// $this->db_jobs->update_job_error_message($job_id, $error_message);
			}
		}
	}

	/**
	 * Filters a list of fetched items, removing those already processed.
	 *
	 * @param array  $fetched_data The fetched input data.
	 * @param object $module The module object.
	 * @return array Filtered items to be processed.
	 */
	private function filter_processed_items(array $fetched_data, object $module) {
		$logger = $this->locator->get('logger'); // Initialize logger
		$items_to_process = [];
		$source_type = $module->data_source_type;
		$module_id = $module->module_id;
		$context = 'filter_processed_items'; // Define context more specifically

		if (!$this->db_processed_items) {
			$logger->error("Database service for processed items not available in filter_processed_items.", ['module_id' => $module_id]);
			return []; // Cannot filter without the DB service
		}

		foreach ($fetched_data as $item) {
			// --- START: Validate item structure ---
			// Ensure the current item is actually an array before proceeding
			if (!is_array($item)) {
				$logger->warning("Skipping non-array item found in input data.", [
					'module_id' => $module_id,
					'context' => $context,
					'item_type' => gettype($item)
				]);
				continue; // Skip to the next item in the input data array
			}
			// --- END: Validate item structure ---

			// Get identifier using the helper method
			$item_identifier = $this->_get_item_identifier($item, $source_type, $module_id, $context);

			// Ensure we have a non-empty identifier
			if (empty($item_identifier)) {
				// Warning is logged inside _get_item_identifier now
				// $logger->warning("Skipping item due to missing or empty identifier after checking source type.", [
				// 	'module_id' => $module_id,
				// 	'source_type' => $source_type,
				// 	'item_keys' => array_keys($item)
				// ]);
				continue; // Skip items where identifier couldn't be determined
			}

			// Check if item has already been processed
			if (!$this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier)) {
				// Item is new, add it to the list to be processed
				$items_to_process[] = $item;
			} else {
				// Item already processed, log for debugging if needed
				$logger->info("Skipping already processed item.", ['module_id' => $module_id, 'source_type' => $source_type, 'item_identifier' => $item_identifier]);
			}
		}

		return $items_to_process;
	}

	/**
	 * Extracts the unique identifier from an item based on its source type.
	 * Centralizes the logic previously duplicated in several methods.
	 *
	 * @param array  $item        The item data packet.
	 * @param string $source_type The data source type (e.g., 'rss', 'reddit').
	 * @param int|null $module_id Optional module ID for logging context.
	 * @param string|null $context   Optional execution context for logging.
	 * @return string|null The unique identifier string, or null if not found.
	 */
	private function _get_item_identifier(array $item, string $source_type, ?int $module_id = null, ?string $context = null): ?string {
		$logger = $this->locator->get('logger');
		$item_identifier = null;

		switch ($source_type) {
			case 'rss':
				// Look inside the metadata array where the handler places it
				$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
				break;
			case 'reddit':
				// Use the specific key confirmed from Data_Machine_Input_Reddit
				// Often the same as original_id but explicitly set for logging/tracking
				$item_identifier = $item['metadata']['item_identifier_to_log'] ?? $item['metadata']['original_id'] ?? null;
				break;
			case 'public_rest_api':
				// Look inside the metadata array where the handler places it
				$item_identifier = $item['metadata']['item_identifier_to_log'] ?? $item['metadata']['original_id'] ?? null;
				break;
			case 'files':
				// Look inside the metadata array where the handler places it (persistent_path)
				$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
				break;
			case 'airdrop_rest_api':
				// Look for the identifier at the top level
				$item_identifier = $item['item_identifier'] ?? null;
				break;
			// Add cases for other known source types here
			// case 'some_other_source':
			// 	$item_identifier = $item['unique_field'] ?? null;
			// 	break;
			default:
				// Attempt common fallback identifiers if type is unknown or not explicitly handled
				$item_identifier = $item['id'] ?? $item['guid'] ?? $item['url'] ?? $item['link'] ?? null;
				// If it's still null, log a warning
				if (is_null($item_identifier) && $logger) {
					$logger->warning("Could not determine unique identifier for unknown source type or item structure.", [
						'module_id' => $module_id ?? 'unknown',
						'context' => $context ?? 'unknown',
						'source_type' => $source_type,
						'item_keys' => array_keys($item) // Log available keys for debugging
					]);
				}
		}

		// Log if identifier is empty after checks
		if (empty($item_identifier) && $logger) {
			$logger->warning("Item identifier is empty after checking source type.", [
				'module_id' => $module_id ?? 'unknown',
				'context' => $context ?? 'unknown',
				'source_type' => $source_type,
				'item_keys' => array_keys($item)
			]);
		}

		return $item_identifier;
	}

	/**
	 * Creates the job record in the database and schedules the WP Cron event.
	 *
	 * @param object $module The module object.
	 * @param int    $user_id The user ID.
	 * @param array  $module_config_data Array of module configuration for the job.
	 * @param string|null $items_to_process_json JSON encoded array of item packets, or null if data is fetched later.
	 * @return int|WP_Error Job ID on success, WP_Error on failure.
	 */
	private function create_and_schedule_job_event(object $module, int $user_id, array $module_config_data, ?string $items_to_process_json) {
		$logger = $this->locator->get('logger');

		// Get module ID safely
		$module_id = $module->module_id ?? $module_config_data['module_id'] ?? null;
		if (!$module_id) {
			$logger->error("Cannot determine module ID for job creation.", ['config_keys' => array_keys($module_config_data)]);
			return new WP_Error('job_create_missing_module_id', __('Internal error: Missing module ID for job creation.', 'data-machine'));
		}
		$module_id = absint($module_id); // Ensure it's an integer

		// Validate the received array (basic check)
		if (empty($module_config_data)) {
			$logger->error("Invalid module config data array received (empty).", ['module_id' => $module_id]);
			return new WP_Error('job_create_invalid_config', __('Internal error preparing job configuration data.', 'data-machine'));
		}

		// --- Encode Config Data for DB ---
		$module_config_json_for_db = json_encode($module_config_data);
		if ($module_config_json_for_db === false) {
			$logger->error("Failed to JSON encode module config before DB insert.", ['module_id' => $module_id, 'error' => json_last_error_msg()]);
			return new WP_Error('job_create_encode_error', __('Internal error encoding job configuration.', 'data-machine'));
		}
		// --- End Encode ---

		// --- Log JSON string before DB insert ---
		$logger->debug("JSON config string prepared for DB insert", ['module_id' => $module_id, 'json_string' => $module_config_json_for_db]);
		// --- End log ---

		if (!$this->db_jobs) {
			$logger->critical("Database service for jobs (database_jobs) not found.", ['module_id' => $module_id]);
			return new WP_Error('service_not_found', __('Database service for jobs is unavailable.', 'data-machine'));
		}

		// Create Job Record (passing $module_config_json_for_db and $items_to_process_json which might be null)
		$job_id = $this->db_jobs->create_job($module_id, $user_id, $module_config_json_for_db, $items_to_process_json);
		if (!$job_id || is_wp_error($job_id)) { // Check for WP_Error return from create_job
			$error_msg = 'Failed to create job record in database.';
			$error_code = 'job_db_create_failed';
			if (is_wp_error($job_id)) {
				$error_msg .= ' ' . $job_id->get_error_message();
				$error_code = $job_id->get_error_code(); // Use specific code if available
			}
			$logger->error($error_msg, ['module_id' => $module_id]);
			return new WP_Error($error_code, __($error_msg, 'data-machine'));
		}
		// Ensure job_id is an integer if create_job returns a numeric string or similar
		$job_id = absint($job_id);

		// Schedule the actual execution via WP Cron
		$result = wp_schedule_single_event(time(), 'dm_run_job_event', ['job_id' => $job_id]);

		// Check if scheduling failed (returns false on failure, WP_Error is less common but possible)
		if ($result === false) {
			$logger->error("Failed to schedule job execution event (wp_schedule_single_event returned false).", ['module_id' => $module_id, 'job_id' => $job_id]);
			// Attempt cleanup: delete the job record we just created, as it won't run
			$deleted = $this->db_jobs->delete_job($job_id);
			$logger->info("Attempted to delete unschedulable job.", ['job_id' => $job_id, 'deleted' => $deleted]);
			return new WP_Error('job_schedule_failed', __('Failed to schedule job for background processing.', 'data-machine'));
		}
		// Check for WP_Error specifically (might happen with filters)
		if (is_wp_error($result)) {
			$logger->error("Error scheduling job execution event (wp_schedule_single_event returned WP_Error).", ['module_id' => $module_id, 'job_id' => $job_id, 'error' => $result->get_error_message()]);
			// Attempt cleanup
			$deleted = $this->db_jobs->delete_job($job_id);
			$logger->info("Attempted to delete unschedulable job.", ['job_id' => $job_id, 'deleted' => $deleted]);
			return new WP_Error('job_schedule_failed', __('Failed to schedule job for background processing.', 'data-machine') . ' ' . $result->get_error_message());
		}

		$logger->info("Job record created and event scheduled.", ['module_id' => $module_id, 'job_id' => $job_id]);
		return $job_id; // Return the new Job ID
	}

	/**
	 * Original method for creating/scheduling job - kept for reference, potentially remove later
	 * Creates a new job record in the database and schedules it.
	 *
	 * @deprecated Use create_and_schedule_job_event instead.
	 * @param object $module The module object.
	 * @param int    $user_id The ID of the user initiating the job.
	 * @param string $module_config_json JSON encoded module configuration string.
	 * @param array  $items_to_process Array of input data items confirmed to be processed for this job.
	 * @return int|WP_Error Job ID of the newly created job, or a WP_Error object on failure.
	 */
	private function create_and_schedule_job(object $module, int $user_id, string $module_config_json, array $items_to_process) {
		// This method's logic is now largely incorporated into execute_job calling create_and_schedule_job_event
		// It requires items to be encoded *before* calling it.
		$logger = $this->locator->get('logger');
		$logger->warning("Deprecated method create_and_schedule_job called. Refactor to use create_and_schedule_job_event.", ['module_id' => $module->module_id]);

		try {
			// Encode input data for storage (Use the filtered list)
			$input_data_json = json_encode($items_to_process);
			if ($input_data_json === false) {
				throw new Exception('Failed to JSON encode input data: ' . json_last_error_msg());
			}

			// Now call the new central method
			// Need to decode the module_config_json back to an array first
			$module_config_data = json_decode($module_config_json, true);
			if ($module_config_data === null && json_last_error() !== JSON_ERROR_NONE) {
				throw new Exception('Failed to decode module config JSON in deprecated wrapper: ' . json_last_error_msg());
			}

			return $this->create_and_schedule_job_event($module, $user_id, $module_config_data, $input_data_json);

		} catch (Exception $e) {
			if ($logger) {
				$logger->error("Exception within deprecated create_and_schedule_job wrapper: " . $e->getMessage(), [
					'module_id' => $module->module_id,
				]);
			}
			return new WP_Error('job_create_schedule_exception_deprecated', $e->getMessage());
		}
	}
}