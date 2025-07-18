<?php

require_once dirname(__DIR__, 2) . '/module-config/Handler_Config_Helper.php';

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
	 * Modules Database instance.
	 * @var Data_Machine_Database_Modules
	 */
	private $db_modules;

	/**
	 * Projects Database instance.
	 * @var Data_Machine_Database_Projects
	 */
	private $db_projects;

	/**
	 * Processing Orchestrator instance.
	 * @var Data_Machine_Processing_Orchestrator
	 */
	private $processing_orchestrator;

	/**
	 * Handler Factory instance.
	 * @var Data_Machine_Handler_Factory
	 */
	private $handler_factory;

	/**
	 * Job Worker instance.
	 * @var Data_Machine_Job_Worker
	 */
	private $job_worker;

	/**
	 * Logger instance (optional).
	 * @var ?Data_Machine_Logger
	 */
	private $logger;

	/**
	 * Action Scheduler service.
	 * @var Data_Machine_Action_Scheduler
	 */
	private $action_scheduler;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Database_Processed_Items $db_processed_items Processed Items DB service.
	 * @param Data_Machine_Database_Jobs $db_jobs Jobs DB service.
	 * @param Data_Machine_Database_Modules $db_modules Modules DB service.
	 * @param Data_Machine_Database_Projects $db_projects Projects DB service.
	 * @param Data_Machine_Processing_Orchestrator $processing_orchestrator Processing Orchestrator service.
	 * @param Data_Machine_Handler_Factory $handler_factory Handler Factory service.
	 * @param Data_Machine_Job_Worker $job_worker Job Worker service.
	 * @param Data_Machine_Action_Scheduler $action_scheduler Action Scheduler service.
	 * @param Data_Machine_Logger|null $logger Logger service (optional).
	 */
	public function __construct(
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Database_Jobs $db_jobs,
		Data_Machine_Database_Modules $db_modules,
		Data_Machine_Database_Projects $db_projects,
		Data_Machine_Processing_Orchestrator $processing_orchestrator,
		Data_Machine_Handler_Factory $handler_factory,
		Data_Machine_Job_Worker $job_worker,
		Data_Machine_Action_Scheduler $action_scheduler,
		?Data_Machine_Logger $logger = null
	) {
		$this->db_processed_items = $db_processed_items;
		$this->db_jobs = $db_jobs;
		$this->db_modules = $db_modules;
		$this->db_projects = $db_projects;
		$this->processing_orchestrator = $processing_orchestrator;
		$this->handler_factory = $handler_factory;
		$this->job_worker = $job_worker;
		$this->action_scheduler = $action_scheduler;
		$this->logger = $logger;
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
		try {
			// 1. Input Data Acquisition
			$input_data = null;
			if ($pre_fetched_input_data !== null) {
				// Use pre-fetched data if provided (e.g., from AJAX handler for file uploads)
				$input_data = $pre_fetched_input_data;
				$this->logger?->info("Using pre-fetched input data.", ['module_id' => $module->module_id, 'context' => $context]);
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
				$this->logger?->info("Input handler returned no new items.", [
			        'module_id' => $module->module_id,
			        'context' => $context,
			        'message' => $input_data['message'] ?? 'N/A'
			    ]);
			    return 0; // Indicate no job was created
			}
			// --- END: Explicit check ---

			// Ensure we actually got data (array of items) - this check is now secondary but still useful for totally empty returns or non-array data
			if (empty($input_data) || !is_array($input_data)) { 
				$this->logger?->info("Input handler returned no data or unexpected format.", ['module_id' => $module->module_id, 'context' => $context, 'returned_data_type' => gettype($input_data)]);
			    return 0; // Indicate no job was created as there was no input
			}

			// --- START: Filter out already processed items ---
			$items_to_process = [];
			$source_type = $module->data_source_type;
			$module_id = $module->module_id;

			if (!$this->db_processed_items) {
				$this->logger?->error("Database service for processed items (db_processed_items) not injected or unavailable.", ['module_id' => $module_id]);
				// Decide whether to proceed without check or return error. Returning error is safer.
				return new WP_Error('service_not_found', __('Database service for processed items is unavailable.', 'data-machine'));
			}

			foreach ($input_data as $item) {
			    if (!is_array($item)) {
					$this->logger?->warning("Skipping non-array item found in input data.", [
			            'module_id' => $module_id,
			            'context' => $context,
			            'item_type' => gettype($item)
			        ]);
			        continue;
			    }

				$item_identifier = $this->_get_item_identifier($item, $source_type, $module_id, $context);

				if (empty($item_identifier)) {
					$this->logger?->warning("Skipping item due to missing or empty identifier after checking source type.", [
						'module_id' => $module_id,
						'source_type' => $source_type,
						'item_keys' => array_keys($item)
					]);
					continue;
				}

				if (!$this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier)) {
					$items_to_process[] = $item;
				} else {
					$this->logger?->info("Skipping already processed item.", ['module_id' => $module_id, 'source_type' => $source_type, 'item_identifier' => $item_identifier]);
				}
			}

			// If no new items are left after filtering, don't create a job
			if (empty($items_to_process)) {
				$this->logger?->info("No new items to process after filtering duplicates.", ['module_id' => $module_id, 'context' => $context]);
				return 0; // Indicate no job was created
			}
			// --- END: Filter out already processed items ---

			// 3. Prepare Job Config
			$module_config_data = $this->prepare_job_config($module);
			if (is_wp_error($module_config_data)) {
				return $module_config_data; // Return WP_Error
			}

			// Encode the items to process as JSON string for the job entry
			$items_to_process_json = $this->safe_json_encode($items_to_process, 'items_to_process');
			if (is_wp_error($items_to_process_json)) {
				return $items_to_process_json;
			}

			// 4. Create Job in DB and Schedule (Pass the filtered list)
			$job_id = $this->create_and_schedule_job_event($module, $user_id, $module_config_data, $items_to_process_json);

			if (is_wp_error($job_id)) {
				return $job_id; // Return WP_Error
			}

			return $job_id; // Return Job ID on success

		} catch (Exception $e) {
			$error_message = "Error executing job: " . $e->getMessage();
			$this->logger?->error($error_message, ['module_id' => $module->module_id ?? 'unknown', 'context' => $context ?? 'unknown', 'error' => $e->getMessage()]); // Ensure module_id and context are set or default
			return new WP_Error('job_execution_error', $error_message);
		}
	}

	/**
	 * Gets the appropriate input handler for the module's data source type.
	 *
	 * @param object $module The module object.
	 * @return Data_Machine_Input_Handler_Interface|null Instance of the input handler, or null if not found.
	 */
	private function get_input_handler(object $module): ?Data_Machine_Input_Handler_Interface {
		try {
			$handler = $this->handler_factory->create_handler('input', $module->data_source_type);
		} catch (Exception $e) {
			$this->logger?->error("Failed to create input handler.", [
				'module_id' => $module->module_id,
				'handler_type' => $module->data_source_type,
				'error' => $e->getMessage()
			]);
			return null;
		}

		if ($handler instanceof Data_Machine_Input_Handler_Interface) {
			return $handler;
		}

		// Optionally log an error if the handler is not found or not the correct type
		$this->logger?->warning("Created handler is not a valid Data_Machine_Input_Handler_Interface.", [
			'module_id' => $module->module_id,
			'handler_type' => $module->data_source_type,
			'handler_class' => is_object($handler) ? get_class($handler) : gettype($handler)
		]);

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
			// Ensure data_source_config is an array (decode if needed)
			if (isset($module->data_source_config) && is_string($module->data_source_config)) {
				$decoded = $this->safe_json_decode($module->data_source_config, 'module data_source_config');
				if (!is_wp_error($decoded)) {
					$module->data_source_config = $decoded;
				}
			}
			// Determine the handler type/slug from the module
			$handler_type = $module->data_source_type;
			// Use the helper to extract the correct config sub-array for this handler
			$handler_config = Handler_Config_Helper::get_handler_config($module, $handler_type);
			// Call the handler's method with only its config
			return $input_handler->get_input_data($module, $handler_config, $user_id);
		} catch (Exception $e) {
			// Enhanced error handling for authentication issues
			$error_message = $e->getMessage();
			$error_details = $this->enhance_error_message($error_message, $module);
			
			$this->logger?->error("Error fetching input data: " . $error_details['enhanced_message'], [
				'module_id' => $module->module_id,
				'handler_class' => get_class($input_handler),
				'handler_type' => $module->data_source_type ?? 'unknown',
				'error' => $error_message,
				'is_auth_error' => $error_details['is_auth_error'],
				'user_id' => $user_id
			]);
			return new WP_Error('input_fetch_error', "Error fetching input data: " . $error_details['enhanced_message']);
		}
	}

	/**
	 * Enhances error messages and determines if they're authentication-related.
	 *
	 * @param string $error_message The original error message.
	 * @param object $module The module object for context.
	 * @return array Array with 'is_auth_error' (bool) and 'enhanced_message' (string).
	 */
	private function enhance_error_message(string $error_message, object $module): array {
		$module_name = $module->module_name ?? "Module {$module->module_id}";
		
		// Check for authentication errors
		$auth_patterns = [
			'/Code:\s*401/i',
			'/not currently logged in/i',
			'/authentication.*failed/i',
			'/invalid.*credentials/i',
			'/unauthorized/i',
			'/access.*denied/i',
			'/permission.*denied/i',
			'/token.*expired/i',
			'/token.*invalid/i',
			'/login.*required/i'
		];
		
		foreach ($auth_patterns as $pattern) {
			if (preg_match($pattern, $error_message)) {
				return [
					'is_auth_error' => true,
					'enhanced_message' => $error_message . "\n\n" . 
						sprintf(__('Authentication failed for "%s". Please check your credentials and re-authenticate if necessary.', 'data-machine'), $module_name)
				];
			}
		}
		
		// Check for rate limiting errors
		if (preg_match('/rate limit/i', $error_message) || preg_match('/Code:\s*429/i', $error_message)) {
			return [
				'is_auth_error' => false,
				'enhanced_message' => $error_message . "\n\n" . 
					sprintf(__('Rate limit exceeded for "%s". This job will be retried automatically later.', 'data-machine'), $module_name)
			];
		}
		
		// Return original message with module context
		return [
			'is_auth_error' => false,
			'enhanced_message' => sprintf(__('Error in "%s": %s', 'data-machine'), $module_name, $error_message)
		];
	}

	/**
	 * Prepares the module configuration data into a structured array.
	 *
	 * @param object $module The module object.
	 * @return array|WP_Error The structured configuration array or WP_Error on JSON decode failure.
	 */
	private function prepare_job_config(object $module) {
		$config = [
			'module_id' => $module->module_id,
			'module_name' => $module->module_name,
			'project_id' => $module->project_id,
			'process_data_prompt' => $module->process_data_prompt,
			'fact_check_prompt' => $module->fact_check_prompt,
			'finalize_response_prompt' => $module->finalize_response_prompt,
			'skip_fact_check' => isset($module->skip_fact_check) ? (int)$module->skip_fact_check : 0,
			'data_source_type' => $module->data_source_type,
			'output_type' => $module->output_type,
			// Decode JSON config fields
			'data_source_config' => null,
			'output_config' => null,
		];

		// Decode data_source_config
		if (!empty($module->data_source_config)) {
			$decoded_ds_config = $this->safe_json_decode($module->data_source_config, "data_source_config for module {$module->module_id}");
			if (is_wp_error($decoded_ds_config)) {
				return $decoded_ds_config;
			}
			$config['data_source_config'] = $decoded_ds_config;
		}

		// Decode output_config
		if (!empty($module->output_config)) {
			$decoded_out_config = $this->safe_json_decode($module->output_config, "output_config for module {$module->module_id}");
			if (is_wp_error($decoded_out_config)) {
				return $decoded_out_config;
			}
			$config['output_config'] = $decoded_out_config;
		}

		return $config;
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
		$this->logger?->info("Scheduled config-based job.", ['module_id' => $module->module_id, 'context' => $context]);
		return $this->create_and_schedule_job_event($module, $user_id, $this->prepare_job_config($module), null);
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
		$this->logger?->info("Scheduled file-based job.", ['module_id' => $module->module_id, 'context' => $context]);
		return $this->create_and_schedule_job_event($module, $user_id, $this->prepare_job_config($module), wp_json_encode([$file_input_data_packet]));
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
		$this->logger?->info("Running scheduled job...", ['job_id' => $job_id]);

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

			// Log raw config from DB before decoding
			$raw_config_from_db = $job->module_config ?? null;
			if (empty($raw_config_from_db)) {
				throw new Exception("Module config is empty in the job record.");
			}
			$this->logger?->debug("Raw module_config from DB for job", ['job_id' => $job_id, 'raw_config' => $raw_config_from_db]);

			// Decode module config
			$module_job_config = $this->safe_json_decode($raw_config_from_db, "module_config for job {$job_id}");
			if (is_wp_error($module_job_config)) {
				throw new Exception($module_job_config->get_error_message());
			}

			// Validate decoded structure
			if (empty($module_job_config) || !is_array($module_job_config) || !isset($module_job_config['module_id'])) {
				$this->logger?->warning("Decoded module config is invalid or missing module_id.", [
					'job_id' => $job_id,
					'decoded_config_type' => gettype($module_job_config),
					'decoded_config_keys' => is_array($module_job_config) ? array_keys($module_job_config) : 'N/A'
				]);
				throw new Exception("Invalid or missing module config after decoding.");
			}
			$module_id = $module_job_config['module_id'];
			$user_id = $job->user_id;

			// --- START: Populate enabled_taxonomies for the handler --- 
			$enabled_taxonomies_for_job = [];
			if (isset($module_job_config['output_type']) && ($module_job_config['output_type'] === 'publish_remote' || $module_job_config['output_type'] === 'publish_local')) {
				$output_config = $module_job_config['output_config'] ?? [];
				$output_type_slug = $module_job_config['output_type']; // e.g., 'publish_remote'

				// --- START Granular Debugging ---
				$this->logger?->debug("Entered enabled_taxonomies population block.", ['job_id' => $job_id, 'output_type' => $output_type_slug]);
				$this->logger?->debug("Output Config received:", ['job_id' => $job_id, 'output_config' => $output_config]);
				// --- END Granular Debugging ---

				// For publish_local, fetch local public taxonomies
				if ($output_type_slug === 'publish_local') {
					$local_taxonomies = get_taxonomies(['public' => true], 'names');
					$enabled_taxonomies_for_job = array_values($local_taxonomies);
					$this->logger?->debug("Fetched local public taxonomies for publish_local", ['job_id' => $job_id, 'taxonomies' => $enabled_taxonomies_for_job]);
				} 
				// For publish_remote, extract from the *nested* remote_site_info
				elseif ($output_type_slug === 'publish_remote') {
					// Get the specific config for the 'publish_remote' handler
					$publish_remote_config = $output_config[$output_type_slug] ?? [];
					// --- START Granular Debugging ---
					$this->logger?->debug("Checking for publish_remote config.", ['job_id' => $job_id, 'publish_remote_config_exists' => isset($output_config[$output_type_slug]), 'publish_remote_config' => $publish_remote_config]);
					// --- END Granular Debugging ---

					// Access remote_site_info within that handler's config
					$remote_site_info = $publish_remote_config['remote_site_info'] ?? [];
					// --- START Granular Debugging ---
					$this->logger?->debug("Checking for remote_site_info.", ['job_id' => $job_id, 'remote_site_info_exists' => isset($publish_remote_config['remote_site_info']), 'remote_site_info' => $remote_site_info]);
					// --- END Granular Debugging ---

					$remote_taxonomies = $remote_site_info['taxonomies'] ?? [];
					// --- START Granular Debugging ---
					$this->logger?->debug("Checking for remote_taxonomies.", ['job_id' => $job_id, 'remote_taxonomies_exist' => isset($remote_site_info['taxonomies']), 'remote_taxonomies' => $remote_taxonomies]);
					// --- END Granular Debugging ---

					if (!empty($remote_taxonomies) && is_array($remote_taxonomies)) {
						$enabled_taxonomies_for_job = array_keys($remote_taxonomies);
						$this->logger?->debug("Extracted remote taxonomies from synced data", ['job_id' => $job_id, 'taxonomies' => $enabled_taxonomies_for_job]);
					} else {
						$this->logger?->warning("Remote site info or taxonomies missing/invalid in output_config for publish_remote.", ['job_id' => $job_id, 'output_config_keys' => array_keys($output_config)]);
					}
				}
			}
			// Add the determined list to the config that gets passed down
			$module_job_config['enabled_taxonomies'] = $enabled_taxonomies_for_job;
			$this->logger?->debug("Populated enabled_taxonomies in job config", ['job_id' => $job_id, 'enabled_taxonomies' => $enabled_taxonomies_for_job]);
			// --- END: Populate enabled_taxonomies --- 

			// 2. Determine Items to Process
			$items_to_process = [];
			$initial_items_json = $job->input_data; // Assuming this field holds the data

			if (!empty($initial_items_json)) {
				// File-based job: Data was pre-fetched and stored
				$this->logger?->info("Job has pre-stored input data (likely file-based).", ['job_id' => $job_id, 'module_id' => $module_id]);
				$decoded_items = $this->safe_json_decode($initial_items_json, "pre-stored input data for job {$job_id}");
				if (is_wp_error($decoded_items)) {
					$this->logger?->warning("Failed to decode pre-stored input data: " . $decoded_items->get_error_message(), ['job_id' => $job_id, 'module_id' => $module_id]);
				} else {
					$items_to_process = $decoded_items;
				}
			} else {
				// Config-based job: Fetch and filter data now
				$this->logger?->info("Fetching input data for config-based job.", ['job_id' => $job_id, 'module_id' => $module_id]);

				// Load the module object using the injected DB Modules service
				if (!$this->db_modules) throw new Exception("Modules DB service (db_modules) not available.");
				$module = $this->db_modules->get_module($module_id);
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
					$this->logger?->info("Input handler returned no new items during job execution.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items';
				} elseif (empty($fetched_data) || !is_array($fetched_data)) {
					$this->logger?->warning("Input handler returned no data or unexpected format during job execution.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items';
				} else {
					// Apply item_count limit
					$data_source_config = $module_job_config['data_source_config'] ?? [];
					$item_count_limit = 1;
					if (isset($data_source_config['item_count'])) {
						$item_count_limit = max(1, absint($data_source_config['item_count']));
					} elseif (isset($module_job_config['data_source_type'])) {
						$source_type = $module_job_config['data_source_type'];
						if (isset($data_source_config[$source_type]['item_count'])) {
							$item_count_limit = max(1, absint($data_source_config[$source_type]['item_count']));
						}
					}
					if (count($fetched_data) > $item_count_limit) {
						$this->logger?->info("Applying item_count limit ({$item_count_limit}) to fetched data.", ['job_id' => $job_id, 'module_id' => $module_id, 'original_count' => count($fetched_data)]);
						$fetched_data = array_slice($fetched_data, 0, $item_count_limit);
					}

					// Filter items (pass the $module object which is now available)
					$items_to_process = $this->filter_processed_items($fetched_data, $module);
					if (empty($items_to_process)) {
						$this->logger?->info("No new items found after filtering (and applying item_count limit).", ['job_id' => $job_id, 'module_id' => $module_id]);
						$final_job_status = 'completed_no_items';
					}
				}
			}

			// 3. Process Items
			if (!empty($items_to_process)) {
				$processed_successfully = 0;
				$processing_errors = 0;
				$item_errors = []; // Array to store individual item error messages
				$total_items = count($items_to_process);
				$this->logger?->info("Starting processing for {$total_items} item(s).", ['job_id' => $job_id, 'module_id' => $module_id]);

				foreach ($items_to_process as $index => $item_packet) {
					$item_log_id = $item_packet['metadata']['item_identifier_to_log'] ?? ('item_' . ($index + 1));
					$this->logger?->info("Processing item {$item_log_id}...", ['job_id' => $job_id, 'module_id' => $module_id]);

					// --- Add log here ---
					$this->logger?->debug("Item packet structure being passed to orchestrator", [
						'job_id' => $job_id,
						'module_id' => $module_id,
						'item_log_id' => $item_log_id,
						// Use wp_json_encode for better readability and depth control if needed
						'item_packet_json' => wp_json_encode($item_packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
						// Fallback if JSON encode fails or for very complex structures
						                                            'item_packet_debug' => 'Debug output removed for production'
					]);
					// --- End log ---

					if (!$this->processing_orchestrator) throw new Exception("Processing Orchestrator not available.");

					$orchestrator_result = $this->processing_orchestrator->run($item_packet, $module_job_config, $user_id, $job_id);

					if (is_wp_error($orchestrator_result)) {
						$processing_errors++;
						$error_message = $orchestrator_result->get_error_message();
						$this->logger?->error("Error processing item {$item_log_id}: " . $error_message, ['job_id' => $job_id, 'module_id' => $module_id, 'error_code' => $orchestrator_result->get_error_code()]);
						// Store the error message for this item
						$item_errors[$item_log_id] = $error_message;
						// Decide: Stop job on first error, or continue with other items?
						// For now, let's continue processing other items but mark job as failed overall if any item fails.
						$final_job_status = 'failed';
					} else {
						$processed_successfully++;
						$this->logger?->info("Successfully processed item {$item_log_id}.", ['job_id' => $job_id, 'module_id' => $module_id, 'output_status' => $orchestrator_result['output_result']['status'] ?? 'unknown']);

						// --- START: Mark item as processed AFTER successful processing ---
						$source_type = $module_job_config['data_source_type'] ?? null;
						// Use the new helper method to get the identifier
						$item_identifier = $this->_get_item_identifier($item_packet, $source_type, $module_id, 'run_scheduled_job_post_process');

						// Ensure we have a non-empty identifier before marking
						if (!empty($item_identifier) && $this->db_processed_items && $source_type) { // Also check source_type is known
							$marked = $this->db_processed_items->add_processed_item($module_id, $source_type, $item_identifier);
							if (!$marked) {
								$this->logger?->warning("Failed to mark successfully processed item in database.", [
									'job_id' => $job_id,
									'module_id' => $module_id,
									'source_type' => $source_type,
									'item_identifier' => $item_identifier,
									'last_db_error' => $this->handler_factory->create_handler('wpdb_key') ? $this->handler_factory->create_handler('wpdb_key')->last_error : 'WPDB not available in locator'
								]);
							} else {
								$this->logger?->debug("Successfully marked item as processed in database.", [
									'job_id' => $job_id,
									'module_id' => $module_id,
									'item_identifier' => $item_identifier
								]);
							}
						} elseif (empty($item_identifier)) {
							$this->logger?->warning("Could not determine identifier for successfully processed item, cannot mark as processed.", [
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
				$this->logger?->info("Finished processing items.", ['job_id' => $job_id, 'module_id' => $module_id, 'total' => $total_items, 'success' => $processed_successfully, 'errors' => $processing_errors]);

			} else {
				// No items to process (either fetched none, or filtered all out)
				// Status should already be 'completed_no_items' if set earlier
				if ($final_job_status !== 'completed_no_items') {
					$this->logger?->info("No items to process for job.", ['job_id' => $job_id, 'module_id' => $module_id]);
					$final_job_status = 'completed_no_items'; // Ensure status is correct
				}
			}

			// 4. Final Job Update - use complete_job
			// $this->db_jobs->update_job_status($job_id, $final_job_status); // INCORRECT CALL
			// Prepare result data for completion (can be null if no items were processed)
			$result_data_for_db = null;
			$result_payload = ['status' => $final_job_status];

			if ($final_job_status === 'completed' || $final_job_status === 'completed_with_errors') {
				$result_payload['message'] = 'Job finished processing.';
				if (!empty($item_errors)) {
					$result_payload['item_errors'] = $item_errors; // Include item-specific errors
				}
			} elseif ($final_job_status === 'completed_no_items') {
				$result_payload['message'] = 'No new items found to process.';
			} elseif ($final_job_status === 'failed' && !empty($item_errors)) {
				// If job failed and we have item errors, include them
				$result_payload['message'] = 'Job failed during item processing.';
				$result_payload['item_errors'] = $item_errors;
			} else {
				// Generic failed message if no specific item errors were captured
				$result_payload['message'] = 'Job failed.';
			}

			$result_data_for_db = $this->safe_json_encode($result_payload, "result_payload for job {$job_id}");
			if (is_wp_error($result_data_for_db)) {
				$this->logger?->error('Failed to JSON encode result_payload for job completion: ' . $result_data_for_db->get_error_message(), ['job_id' => $job_id, 'module_id' => $module_id]);
				// Fallback to a simple error message if encoding fails
				$result_data_for_db = $this->safe_json_encode(['status' => 'failed', 'message' => 'Job failed: Error encoding result data.'], 'fallback result');
			}


			// --- START: Add Detailed Logging Before complete_job Call ---
			$this->logger?->debug("Preparing to call complete_job.", [
				'job_id' => $job_id,
				'final_job_status_value' => $final_job_status,
				'final_job_status_type' => gettype($final_job_status),
				'result_data_for_db_type' => gettype($result_data_for_db),
				'result_data_for_db_value' => (is_string($result_data_for_db) ? substr($result_data_for_db, 0, 500) . '...' : $result_data_for_db) // Log snippet or value
			]);
			// --- END: Add Detailed Logging ---

			// Call complete_job - Note: returns bool, could check it.
			$db_updated = $this->db_jobs->complete_job($job_id, $final_job_status, $result_data_for_db);
			// Log completion status
			$this->logger?->info("Job execution finished.", ['job_id' => $job_id, 'module_id' => $module_job_config['module_id'] ?? 'N/A', 'final_status' => $final_job_status, 'db_update_success' => (bool)$db_updated]);

			// --- START: Update DB with fetched/filtered input data ---
			if (!empty($items_to_process)) {
				$input_json_for_db = json_encode($items_to_process);
				if ($input_json_for_db !== false) {
					$this->db_jobs->update_job_input_data($job_id, $input_json_for_db);
				} else {
					$this->logger?->warning('Failed to JSON encode items_to_process before updating job input_data.', ['job_id' => $job_id, 'module_id' => $module_id]);
				}
			}
			// --- END: Update DB input_data ---

			// Update the last run time for the associated module
			if (isset($module_job_config['module_id'])) {
				if ($this->db_modules instanceof Data_Machine_Database_Modules) {
					$this->db_modules->update_module_last_run($module_job_config['module_id']);
				} else {
					$this->logger?->error("Could not update module last run time: DB Modules service unavailable or not injected correctly.", ['job_id' => $job_id, 'module_id' => $module_job_config['module_id']]);
				}
			}
			// Update the last run time for the associated project
			if (isset($module_job_config['project_id'])) {
				if ($this->db_projects instanceof Data_Machine_Database_Projects) {
					$this->db_projects->update_project_last_run($module_job_config['project_id']);
				} else {
					$this->logger?->error("Could not update project last run time: DB Projects service unavailable or not injected correctly.", ['job_id' => $job_id, 'project_id' => $module_job_config['project_id']]);
				}
			}

		} catch (Exception $e) {
			// Determine if this is an authentication-related error
			$error_details = $this->enhance_error_message($e->getMessage(), (object)$module_job_config);
			$job_status = $error_details['is_auth_error'] ? 'failed_auth' : 'failed';
			
			$error_message = "Critical error during job execution: " . $e->getMessage();
			$this->logger?->error($error_message, [
				'job_id' => $job_id, 
				'module_id' => $module_job_config['module_id'] ?? 'N/A', 
				'is_auth_error' => $error_details['is_auth_error'],
				'final_status' => $job_status,
				'trace' => $e->getTraceAsString()
			]);
			
			// Ensure status is marked as failed if exception occurs before final update
			if ($job && $this->db_jobs) {
				// Prepare enhanced error data for storage
				$error_data_for_db = $this->safe_json_encode([
					'error' => $e->getMessage(), 
					'trace' => $e->getTraceAsString(),
					'is_authentication_error' => $error_details['is_auth_error'],
					'module_type' => $module_job_config['data_source_type'] ?? 'unknown',
					'timestamp' => current_time('mysql', true)
				], "error data for job {$job_id}");
				$this->db_jobs->complete_job($job_id, $job_status, $error_data_for_db);
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
	private function filter_processed_items(array $fetched_data, object $module): array {
		$items_to_process = [];
		$source_type = $module->data_source_type;
		$module_id = $module->module_id;
		$context = 'filtering'; // Context for logging

		if (!$this->db_processed_items) {
			$this->logger?->error("Processed items database service not available during filtering.", ['module_id' => $module_id]);
			// Return original data or empty array? Returning original is risky.
			// Returning empty prevents processing but logs error.
			return [];
		}

		foreach ($fetched_data as $item) {
			if (!is_array($item)) {
				$this->logger?->warning("Skipping non-array item found during filtering.", [
					'module_id' => $module_id,
					'item_type' => gettype($item)
				]);
				continue;
			}

			$item_identifier = $this->_get_item_identifier($item, $source_type, $module_id, $context);

			if (empty($item_identifier)) {
				$this->logger?->warning("Skipping item during filtering due to missing identifier.", [
					'module_id' => $module_id,
					'source_type' => $source_type,
					'item_keys' => array_keys($item)
				]);
				continue;
			}

			if (!$this->db_processed_items->has_item_been_processed($module_id, $source_type, $item_identifier)) {
				$items_to_process[] = $item;
			} else {
				$this->logger?->info("Filtered out already processed item.", [
					'module_id' => $module_id,
					'source_type' => $source_type,
					'item_identifier' => $item_identifier
				]);
			}
		}

		return $items_to_process;
	}

	/**
	 * Safely encode data to JSON with error handling.
	 *
	 * @param mixed $data The data to encode.
	 * @param string $context Context for error logging.
	 * @return string|WP_Error JSON string on success, WP_Error on failure.
	 */
	private function safe_json_encode($data, string $context = ''): mixed {
		$json = wp_json_encode($data);
		if ($json === false) {
			$error_msg = "Failed to encode data as JSON" . ($context ? " in {$context}" : "") . ": " . json_last_error_msg();
			$this->logger?->error($error_msg, ['context' => $context, 'data_type' => gettype($data)]);
			return new WP_Error('json_encode_error', $error_msg);
		}
		return $json;
	}

	/**
	 * Safely decode JSON with error handling.
	 *
	 * @param string $json The JSON string to decode.
	 * @param string $context Context for error logging.
	 * @param bool $associative Whether to return associative arrays.
	 * @return mixed|WP_Error Decoded data on success, WP_Error on failure.
	 */
	private function safe_json_decode(string $json, string $context = '', bool $associative = true): mixed {
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
		$item_identifier = null;

		switch ($source_type) {
			case 'rss':
			case 'reddit':
			case 'public_rest_api':
			case 'files':
				// These handlers store it in metadata['item_identifier_to_log']
				$item_identifier = $item['metadata']['item_identifier_to_log'] ?? null;
				// Fallback for public_rest_api if needed
				if (is_null($item_identifier) && $source_type === 'public_rest_api') {
					$item_identifier = $item['metadata']['original_id'] ?? null;
				}
				break;
			case 'airdrop_rest_api':
				// This handler puts it at the top level
				$item_identifier = $item['item_identifier'] ?? null;
				break;
			default:
				// Attempt common fallbacks for unknown types
				$item_identifier = $item['id'] ?? $item['guid'] ?? $item['url'] ?? $item['link'] ?? null;
				if (is_null($item_identifier)) {
					$this->logger?->warning("Could not determine item identifier from common fields.", [
						'module_id' => $module_id,
						'source_type' => $source_type,
						'context' => $context,
						'item_keys' => array_keys($item)
					]);
				}
		}

		// Return null if empty or not found
		return empty($item_identifier) ? null : (string) $item_identifier;
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
		try {
			// Ensure db_jobs is available
			if (!$this->db_jobs) {
				throw new Exception("Database service for jobs (db_jobs) is not available.");
			}

			// Check for existing active jobs for this module to prevent duplicates
			if ($this->db_jobs->has_active_jobs_for_module($module->module_id)) {
				$this->logger?->info("Skipping job creation - module already has active jobs.", [
					'module_id' => $module->module_id,
					'module_name' => $module->module_name ?? 'Unknown'
				]);
				return 0; // Return 0 to indicate no new job was created (consistent with existing behavior)
			}

			// Encode the config snapshot
			$config_snapshot_json = $this->safe_json_encode($module_config_data, 'module config snapshot');
			if (is_wp_error($config_snapshot_json)) {
				throw new Exception($config_snapshot_json->get_error_message());
			}

			// Create the job entry - Corrected arguments
            // create_job expects: module_id, user_id, module_config_json, input_data_json
            // It sets status internally to 'pending'.
			$job_id = $this->db_jobs->create_job(
				$module->module_id,        // Arg 1: module_id
				$user_id,                  // Arg 2: user_id
				$config_snapshot_json,     // Arg 3: module_config_json
				$items_to_process_json     // Arg 4: input_data_json (can be null)
			);

			if (!$job_id || is_wp_error($job_id)) {
				$error_msg = is_wp_error($job_id) ? $job_id->get_error_message() : "Failed to create job entry in database.";
				throw new Exception($error_msg);
			}

			// Schedule the single event to run the job worker
			// Pass only the job_id as the argument
			$scheduled = $this->action_scheduler->schedule_single_job('dm_run_job_event', array($job_id));

			if ($scheduled === false) {
				// Attempt to mark job as failed if scheduling fails
				// Note: create_job sets status to 'pending', run_scheduled_job sets it to 'running'/'failed'/'completed'
                // We might want a dedicated method in db_jobs to update status directly.
                // For now, let's log and potentially delete the pending job.
                $this->db_jobs->delete_job($job_id); // Attempt to delete the unusable job
                $this->logger?->error("Failed to schedule WP Cron event. Deleted pending job.", ['job_id' => $job_id]);
				throw new Exception("Failed to schedule the WP Cron single event for job execution.");
			}

			$this->logger?->info("Successfully created and scheduled job event.", ['job_id' => $job_id, 'module_id' => $module->module_id]);
			return $job_id;

		} catch (Exception $e) {
			$error_message = "Error creating/scheduling job: " . $e->getMessage();
			$this->logger?->error($error_message, ['module_id' => $module->module_id, 'error' => $e->getMessage()]);
			return new WP_Error('job_creation_scheduling_error', $error_message);
		}
	}

}