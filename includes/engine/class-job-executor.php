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
	 * Constructor.
	 *
	 * @param Data_Machine_Service_Locator $locator Service Locator instance for dependency injection.
	 */
	public function __construct(Data_Machine_Service_Locator $locator) {
		$this->locator = $locator;
		// Get the processed items database handler from the locator
		$this->db_processed_items = $this->locator->get('database_processed_items');
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
						// Common unique fields for RSS feeds
						$item_identifier = $item['guid'] ?? $item['link'] ?? $item['id'] ?? null;
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
					// Mark it as processed NOW
					$marked = $this->db_processed_items->add_processed_item($module_id, $source_type, $item_identifier);
					if (!$marked) {
						$logger->warning("Failed to mark item as processed in database.", ['module_id' => $module_id, 'source_type' => $source_type, 'item_identifier' => $item_identifier]);
						// Decide whether to continue processing the item anyway or skip it. Skipping is safer to prevent duplicates if DB fails temporarily.
						// For now, we'll let it proceed into the job, but log the failure.
					}
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
			$module_config_json = $this->prepare_job_config($module);
			if (is_wp_error($module_config_json)) {
				return $module_config_json; // Return WP_Error
			}

			// 4. Create Job in DB and Schedule (Pass the filtered list)
			$job_id = $this->create_and_schedule_job($module, $user_id, $module_config_json, $items_to_process);
			if (is_wp_error($job_id)) {
				return $job_id; // Return WP_Error
			}

			return $job_id; // Return Job ID on success

		} catch (Exception $e) {
			$error_message = "Error executing job: " . $e->getMessage();
			$logger->error($error_message, ['module_id' => $module->module_id, 'context' => $context, 'error' => $e->getMessage()]);
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

			$module_config_json = json_encode($module_job_config);

			if ($module_config_json === false) {
				throw new Exception('Failed to JSON encode module configuration: ' . json_last_error_msg());
			}

			return $module_config_json;

		} catch (Exception $e) {
			$logger = $this->locator->get('logger');
			if ($logger) {
				$logger->error("Exception during job config preparation: " . $e->getMessage(), ['module_id' => $module->module_id ?? 'unknown']);
			}
			return new WP_Error('job_config_prepare_exception', $e->getMessage());
		}
	}

	/**
	 * Creates a new job record in the database and schedules it.
	 *
	 * @param object $module The module object.
	 * @param int    $user_id The ID of the user initiating the job.
	 * @param string $module_config_json JSON encoded module configuration string.
	 * @param array  $items_to_process Array of input data items confirmed to be processed for this job.
	 * @return int|WP_Error Job ID of the newly created job, or a WP_Error object on failure.
	 */
	private function create_and_schedule_job(object $module, int $user_id, string $module_config_json, array $items_to_process) {
		$db_jobs = $this->locator->get('database_jobs');
		$logger = $this->locator->get('logger');

		if (!$db_jobs) {
			if ($logger) {
				$logger->error("Database service for jobs (database_jobs) not found.", ['module_id' => $module->module_id]);
			}
			return new WP_Error('service_not_found', __('Database service for jobs is unavailable.', 'data-machine'));
		}

		try {
			// 1. Encode input data for storage (Use the filtered list)
			$input_data_json = json_encode($items_to_process);
			if ($input_data_json === false) {
				throw new Exception('Failed to JSON encode input data: ' . json_last_error_msg());
			}

			// 2. Create the job record in the database using individual arguments
			$job_id = $db_jobs->create_job(
				absint($module->module_id),
				absint($user_id),
				$module_config_json,
				$input_data_json
			);

			if (!$job_id || is_wp_error($job_id)) {
				$error_message = 'Failed to create job record in database.';
				if (is_wp_error($job_id)) {
					$error_message .= ' ' . $job_id->get_error_message();
				}
				throw new Exception($error_message);
			}

			// 3. Schedule the WP-Cron event to process the job
			$schedule_result = wp_schedule_single_event(time(), 'dm_run_job_event', ['job_id' => $job_id]);

			if ($schedule_result === false) {
				// Optional: Update job status to indicate scheduling failure?
				// $db_jobs->update_job_status($job_id, 'schedule_failed');
				throw new Exception('Failed to schedule WP-Cron event for job processing.');
			}

			if ($logger) {
				$logger->info("Job created and scheduled successfully.", ['job_id' => $job_id, 'module_id' => $module->module_id]);
			}

			// 4. Return the new Job ID
			return (int) $job_id;

		} catch (Exception $e) {
			if ($logger) {
				$logger->error("Exception during job creation/scheduling: " . $e->getMessage(), [
					'module_id' => $module->module_id,
					// Include job_id if available, e.g., if DB insert succeeded but scheduling failed
					'job_id' => isset($job_id) && $job_id && !is_wp_error($job_id) ? $job_id : null,
				]);
			}
			// Maybe return the specific error from create_job if it was a WP_Error
			if (isset($job_id) && is_wp_error($job_id)) {
			    return $job_id;
			}
			return new WP_Error('job_create_schedule_exception', $e->getMessage());
		}
	}
}