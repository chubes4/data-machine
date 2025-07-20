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
	 * Remote Locations Database instance.
	 * @var Data_Machine_Database_Remote_Locations
	 */
	private $db_remote_locations;

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
	 * Job Preparer service.
	 * @var Data_Machine_Job_Preparer
	 */
	private $job_preparer;

	/**
	 * Job Filter service.
	 * @var Data_Machine_Job_Filter
	 */
	private $job_filter;

	/**
	 * Constructor.
	 *
	 * @param Data_Machine_Database_Processed_Items $db_processed_items Processed Items DB service.
	 * @param Data_Machine_Database_Jobs $db_jobs Jobs DB service.
	 * @param Data_Machine_Database_Modules $db_modules Modules DB service.
	 * @param Data_Machine_Database_Projects $db_projects Projects DB service.
	 * @param Data_Machine_Database_Remote_Locations $db_remote_locations Remote Locations DB service.
	 * @param Data_Machine_Processing_Orchestrator $processing_orchestrator Processing Orchestrator service.
	 * @param Data_Machine_Handler_Factory $handler_factory Handler Factory service.
	 * @param Data_Machine_Action_Scheduler $action_scheduler Action Scheduler service.
	 * @param Data_Machine_Job_Preparer $job_preparer Job Preparer service.
	 * @param Data_Machine_Job_Filter $job_filter Job Filter service.
	 * @param Data_Machine_Logger|null $logger Logger service (optional).
	 */
	public function __construct(
		Data_Machine_Database_Processed_Items $db_processed_items,
		Data_Machine_Database_Jobs $db_jobs,
		Data_Machine_Database_Modules $db_modules,
		Data_Machine_Database_Projects $db_projects,
		Data_Machine_Database_Remote_Locations $db_remote_locations,
		Data_Machine_Processing_Orchestrator $processing_orchestrator,
		Data_Machine_Handler_Factory $handler_factory,
		Data_Machine_Action_Scheduler $action_scheduler,
		Data_Machine_Job_Preparer $job_preparer,
		Data_Machine_Job_Filter $job_filter,
		?Data_Machine_Logger $logger = null
	) {
		$this->db_processed_items = $db_processed_items;
		$this->db_jobs = $db_jobs;
		$this->db_modules = $db_modules;
		$this->db_projects = $db_projects;
		$this->db_remote_locations = $db_remote_locations;
		$this->processing_orchestrator = $processing_orchestrator;
		$this->handler_factory = $handler_factory;
		$this->action_scheduler = $action_scheduler;
		$this->job_preparer = $job_preparer;
		$this->job_filter = $job_filter;
		$this->logger = $logger;
	}

	/**
	 * Executes a job for a given module.
	 *
	 * Main method responsible for orchestrating the entire job execution process.
	 * Now uses Job Preparer for all preparation logic.
	 *
	 * @param object $module The module object for which the job is being executed.
	 * @param int    $user_id The ID of the user initiating the job (project owner).
	 * @param string $context Context of job execution ('manual', 'cron_project', 'cron_module', 'process_now').
	 * @param array|null $pre_fetched_input_data Optional pre-fetched input data array. If provided, skips internal fetching.
	 * @return int|WP_Error Job ID on successful job creation and scheduling, WP_Error object on failure.
	 */
	public function execute_job(object $module, int $user_id, string $context, ?array $pre_fetched_input_data = null) {
		try {
			$module_id = $module->module_id ?? 0;
			
			// Job concurrency will be handled by Job Filter during scheduling
			
			$this->logger?->info("Job Executor: Starting job execution for module {$module_id}.", ['context' => $context]);

			// Use Job Preparer to handle all preparation logic
			$job_packet = $this->job_preparer->prepare_job_packet($module, $user_id, $pre_fetched_input_data);
			
			if (is_wp_error($job_packet)) {
				return $job_packet; // Return WP_Error directly
			}

			// Check for no new items
			if (is_array($job_packet) && isset($job_packet['status']) && $job_packet['status'] === 'no_new_items') {
				$this->logger?->info("Job Executor: No new items to process.", [
					'module_id' => $module_id,
					'context' => $context,
					'message' => $job_packet['message'] ?? 'N/A'
				]);
				return 0; // Indicate no job was created
			}

			// Validate job packet
			if (!isset($job_packet['module_config']) || !isset($job_packet['input_data'])) {
				return new WP_Error('invalid_job_packet', 'Job preparation returned invalid packet structure.');
			}

			$module_config_data = $job_packet['module_config'];
			$items_to_process = $job_packet['input_data'];

			// Encode the items to process as JSON string for the job entry
			$items_to_process_json = $this->job_preparer->safe_json_encode($items_to_process, 'items_to_process');
			if (is_wp_error($items_to_process_json)) {
				return $items_to_process_json;
			}

			// 4. Create Job in DB and Schedule (Pass the filtered list)
			$job_id = $this->create_and_schedule_job_event($module, $user_id, $module_config_data, $items_to_process_json);

			if (is_wp_error($job_id)) {
				return $job_id; // Return WP_Error
			}

			$this->logger?->info("Job Executor: Job created successfully.", ['module_id' => $module_id, 'job_id' => $job_id]);
			return $job_id; // Return Job ID on success

		} catch (Exception $e) {
			$error_message = "Error executing job: " . $e->getMessage();
			$this->logger?->error($error_message, ['module_id' => $module->module_id ?? 'unknown', 'context' => $context ?? 'unknown', 'error' => $e->getMessage()]); // Ensure module_id and context are set or default
			return new WP_Error('job_execution_error', $error_message);
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
						/* translators: %s: Module name */
						sprintf(__('Authentication failed for "%s". Please check your credentials and re-authenticate if necessary.', 'data-machine'), $module_name)
				];
			}
		}
		
		// Check for rate limiting errors
		if (preg_match('/rate limit/i', $error_message) || preg_match('/Code:\s*429/i', $error_message)) {
			return [
				'is_auth_error' => false,
				'enhanced_message' => $error_message . "\n\n" . 
					/* translators: %s: Module name */
					sprintf(__('Rate limit exceeded for "%s". This job will be retried automatically later.', 'data-machine'), $module_name)
			];
		}
		
		// Return original message with module context
		return [
			'is_auth_error' => false,
			/* translators: %1$s: Module name, %2$s: Error message */
			'enhanced_message' => sprintf(__('Error in "%1$s": %2$s', 'data-machine'), $module_name, $error_message)
		];
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
		// Use Job Filter to check if scheduling is allowed
		if ($this->job_filter && !$this->job_filter->can_schedule_job($module->module_id)) {
			$this->logger?->info("Skipping job scheduling - module has active jobs", [
				'module_id' => $module->module_id, 
				'context' => $context
			]);
			return new WP_Error('active_job_exists', 'Another job is already running for this module');
		}
		
		$this->logger?->info("Scheduled config-based job.", ['module_id' => $module->module_id, 'context' => $context]);
		
		// Use Job Preparer for config preparation
		$job_config = $this->job_preparer->prepare_job_config($module);
		if (is_wp_error($job_config)) {
			return $job_config;
		}
		
		return $this->create_and_schedule_job_event($module, $user_id, $job_config, null);
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
		
		// Use Job Preparer for config preparation
		$job_config = $this->job_preparer->prepare_job_config($module);
		if (is_wp_error($job_config)) {
			return $job_config;
		}
		
		return $this->create_and_schedule_job_event($module, $user_id, $job_config, wp_json_encode([$file_input_data_packet]));
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
			// 1. Load Job Data and Update Status FIRST
			if (!$this->db_jobs) throw new Exception("Jobs DB service not available.");
			$job = $this->db_jobs->get_job($job_id);
			if (!$job) throw new Exception("Job record not found.");

			// Update job status to 'running' BEFORE concurrency check
			$this->db_jobs->start_job($job_id, 'running');

			// 2. Check if another job is running for this module (now excludes this job)
			if ($this->has_running_job_for_module($job_id)) {
				$this->logger?->info("Skipping job - another job is already running for this module", ['job_id' => $job_id]);
				// Mark this job as failed since it can't run
				$this->db_jobs->complete_job($job_id, 'failed', wp_json_encode(['error' => 'Another job already running for this module']));
				return;
			}

			// Log raw config from DB before decoding
			$raw_config_from_db = $job->module_config ?? null;
			if (empty($raw_config_from_db)) {
				throw new Exception("Module config is empty in the job record.");
			}
			$this->logger?->debug("Raw module_config from DB for job", ['job_id' => $job_id, 'raw_config' => $raw_config_from_db]);

			// Decode module config
			$module_job_config = $this->job_preparer->safe_json_decode($raw_config_from_db, "module_config for job {$job_id}");
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
				// For publish_remote, fetch remote_site_info from database using location_id
				elseif ($output_type_slug === 'publish_remote') {
					// Get the specific config for the 'publish_remote' handler
					$publish_remote_config = $output_config[$output_type_slug] ?? [];
					$this->logger?->debug("Checking for publish_remote config.", ['job_id' => $job_id, 'publish_remote_config' => $publish_remote_config]);

					// Get location_id from the config
					$location_id = $publish_remote_config['location_id'] ?? null;
					if (!$location_id) {
						$error_msg = "location_id missing in publish_remote configuration.";
						$this->logger?->error($error_msg, ['job_id' => $job_id, 'config' => $publish_remote_config]);
						$this->db_jobs->complete_job($job_id, 'failed', wp_json_encode(['error' => $error_msg]));
						return;
					}

					// Fetch remote site info from database using location_id and user_id
					$remote_location = $this->db_remote_locations->get_location($location_id, $job->user_id, false);
					if (!$remote_location || empty($remote_location->synced_site_info)) {
						$error_msg = "Remote location not found or no synced site info available.";
						$this->logger?->error($error_msg, ['job_id' => $job_id, 'location_id' => $location_id]);
						$this->db_jobs->complete_job($job_id, 'failed', wp_json_encode(['error' => $error_msg]));
						return;
					}

					// Decode the synced site info
					$remote_site_info = json_decode($remote_location->synced_site_info, true);
					if (!$remote_site_info) {
						$error_msg = "Failed to decode synced site info for remote location.";
						$this->logger?->error($error_msg, ['job_id' => $job_id, 'location_id' => $location_id]);
						$this->db_jobs->complete_job($job_id, 'failed', wp_json_encode(['error' => $error_msg]));
						return;
					}

					// Extract taxonomies from the remote site info
					$remote_taxonomies = $remote_site_info['taxonomies'] ?? [];
					$this->logger?->debug("Fetched remote taxonomies from database", ['job_id' => $job_id, 'location_id' => $location_id, 'taxonomies' => array_keys($remote_taxonomies)]);

					if (!empty($remote_taxonomies) && is_array($remote_taxonomies)) {
						$enabled_taxonomies_for_job = array_keys($remote_taxonomies);
						
						// Add remote_site_info to the config for the handler to use
						$module_job_config['output_config'][$output_type_slug]['remote_site_info'] = $remote_site_info;
					} else {
						$error_msg = "No taxonomies found in remote site info.";
						$this->logger?->error($error_msg, ['job_id' => $job_id, 'location_id' => $location_id]);
						$this->db_jobs->complete_job($job_id, 'failed', wp_json_encode(['error' => $error_msg]));
						return;
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
				$decoded_items = $this->job_preparer->safe_json_decode($initial_items_json, "pre-stored input data for job {$job_id}");
				if (is_wp_error($decoded_items)) {
					$this->logger?->warning("Failed to decode pre-stored input data: " . $decoded_items->get_error_message(), ['job_id' => $job_id, 'module_id' => $module_id]);
				} else {
					$items_to_process = $decoded_items;
				}
			} else {
				// Config-based job: Fetch and filter data now using Job Preparer
				$this->logger?->info("Fetching input data for config-based job using Job Preparer.", ['job_id' => $job_id, 'module_id' => $module_id]);

				// Load the module object using the injected DB Modules service
				if (!$this->db_modules) throw new Exception("Modules DB service (db_modules) not available.");
				$module = $this->db_modules->get_module($module_id);
				if (!$module) throw new Exception("Module object not found for ID: {$module_id}");

				// Use Job Preparer to fetch and filter input data
				$input_handler = $this->job_preparer->get_input_handler($module);
				if (is_wp_error($input_handler)) {
					throw new Exception("Could not load input handler: " . $input_handler->get_error_message());
				}

				$fetched_data = $this->job_preparer->fetch_input_data($input_handler, $module, $user_id);
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

					// Items are already filtered by the input handler, so use them directly
					$items_to_process = $fetched_data;
					if (empty($items_to_process)) {
						$this->logger?->info("No new items found after input handler filtering (and applying item_count limit).", ['job_id' => $job_id, 'module_id' => $module_id]);
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

						// --- MOVED: Do NOT mark item as processed here ---
						// Items will be marked as processed after output job completes successfully
						// This prevents duplicate processing if the same job runs again before output finishes
						$this->logger?->debug("Item processing queued, will be marked as processed after output job completes.", [
							'job_id' => $job_id,
							'module_id' => $module_id,
							'item_identifier' => $item_log_id
						]);
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

			$result_data_for_db = $this->job_preparer->safe_json_encode($result_payload, "result_payload for job {$job_id}");
			if (is_wp_error($result_data_for_db)) {
				$this->logger?->error('Failed to JSON encode result_payload for job completion: ' . $result_data_for_db->get_error_message(), ['job_id' => $job_id, 'module_id' => $module_id]);
				// Fallback to a simple error message if encoding fails
				$result_data_for_db = $this->job_preparer->safe_json_encode(['status' => 'failed', 'message' => 'Job failed: Error encoding result data.'], 'fallback result');
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
				$error_data_for_db = $this->job_preparer->safe_json_encode([
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
	 * Simple check if there's already a running job for this module.
	 *
	 * @param int $job_id The job ID to check.
	 * @return bool True if another job is running for this module.
	 */
	private function has_running_job_for_module(int $job_id): bool {
		if (!$this->db_jobs) {
			return false;
		}

		$job = $this->db_jobs->get_job($job_id);
		if (!$job) {
			return false;
		}

		// Check for active jobs for this module, excluding the current job
		return $this->db_jobs->has_active_jobs_for_module($job->module_id, $job_id);
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
			$config_snapshot_json = $this->job_preparer->safe_json_encode($module_config_data, 'module config snapshot');
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