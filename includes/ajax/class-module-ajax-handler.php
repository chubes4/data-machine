<?php
/**
 * Handles AJAX requests related to Modules and Processing Jobs.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.12.0
 */
class Data_Machine_Module_Ajax_Handler {

    /** @var Data_Machine_Service_Locator */
    private $locator;

    /**
     * Constructor.
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct( Data_Machine_Service_Locator $locator ) {
        $this->locator = $locator;
    }

    /**
	 * Generic AJAX handler to process data based on module's data source type.
     * Moved from Data_Machine class.
	 * @since 0.7.0 (Moved 0.12.0)
	 */
	public function process_data_source_ajax_handler() {
		check_ajax_referer( 'file_processing_nonce', 'nonce' );

		$module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
		$user_id = get_current_user_id();

		if (empty($module_id) || empty($user_id)) {
			wp_send_json_error(array('message' => __('Missing module ID or user ID.', 'data-machine')));
			return;
		}

        // Get dependencies from locator
        $db_modules = $this->locator->get('database_modules');
        $orchestrator = $this->locator->get('orchestrator');
        // Input handlers are retrieved dynamically below or via locator

		// --- Ownership Check (using Project) ---
        $module = $db_modules->get_module($module_id); // Get module without user check first
        if (!$module || !isset($module->project_id)) {
             wp_send_json_error(array('message' => __('Invalid module or project association missing.', 'data-machine')));
             return;
        }
        // Ensure Database_Projects class is loaded if not already via autoloader/main file
        // Note: Getting db_projects from locator assumes it's registered
        $db_projects = $this->locator->get('database_projects');
        if (!$db_projects) {
             // Fallback if not registered (should not happen ideally)
             if (!class_exists('Data_Machine_Database_Projects')) {
                 require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
             }
             $db_projects = new Data_Machine_Database_Projects();
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            wp_send_json_error(array('message' => __('Permission denied for this module.', 'data-machine')));
            return;
        }
        // --- End Ownership Check ---


		if (!isset($module->data_source_type)) {
			wp_send_json_error(array('message' => __('Module data source type not configured.', 'data-machine')));
			return;
		}

		$input_handler = null;
		$data_source_type = $module->data_source_type;

		// --- Input Handler Selection ---
        // TODO: Refactor this to use the locator more effectively, maybe register input handlers too?
		if ($data_source_type === 'files') {
			$input_handler = $this->locator->get('input_files'); // Get from locator
		} elseif ($data_source_type === 'airdrop_rest_api') { // Corrected slug check
			$input_handler = $this->locator->get('input_airdrop_rest_api'); // Get from locator
		} elseif ($data_source_type === 'public_rest_api') { // Added new handler
					// Instantiate the Public REST API handler
					if (!class_exists('Data_Machine_Input_Public_Rest_Api')) {
						 require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-public_rest_api.php';
					}
					// Assuming constructor accepts the locator
					$input_handler = new Data_Machine_Input_Public_Rest_Api( $this->locator );
				} elseif ($data_source_type === 'rss') { // Added RSS handler
							// Instantiate the RSS handler
							if (!class_exists('Data_Machine_Input_Rss')) {
								 require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-rss.php';
							}
							// Assuming constructor accepts the locator
							$input_handler = new Data_Machine_Input_Rss( $this->locator );
						} elseif ($data_source_type === 'reddit') { // Added Reddit handler
									// Instantiate the Reddit handler
									if (!class_exists('Data_Machine_Input_Reddit')) {
										 require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-reddit.php';
									}
									// Assuming constructor accepts the locator
									$input_handler = new Data_Machine_Input_Reddit( $this->locator );
								} elseif ($data_source_type === 'instagram') { // Corrected Instagram handler check
									// Instantiate the Instagram handler
									if (!class_exists('Data_Machine_Input_Instagram')) {
										 require_once DATA_MACHINE_PATH . 'includes/input/class-data-machine-input-instagram.php';
									}
									// Instagram handler does not need the locator currently
									$input_handler = new Data_Machine_Input_Instagram();
								}
								// TODO: Add elseif blocks here for other future input types
		else {
			wp_send_json_error(array('message' => sprintf(__('Unsupported data source type: %s', 'data-machine'), $data_source_type)));
			return;
		}

		// --- Execute Input Handler & Enqueue Job ---
		if ($input_handler instanceof Data_Machine_Input_Handler_Interface) {
			try {
				// Decode configs first
				$decoded_output_config = !empty($module->output_config) ? json_decode(wp_unslash($module->output_config), true) : array();
				$decoded_ds_config = !empty($module->data_source_config) ? json_decode(wp_unslash($module->data_source_config), true) : array();

				// Ensure they are arrays after decoding
				if (!is_array($decoded_output_config)) $decoded_output_config = [];
				if (!is_array($decoded_ds_config)) $decoded_ds_config = [];

				// Pass the decoded source config and user ID along with POST and FILES
				$input_results = $input_handler->get_input_data($_POST, $_FILES, $decoded_ds_config, $user_id);

				// --- Handle Input Results --- 
				
				// Case 1: No new items found by the input handler
				if (is_array($input_results) && isset($input_results['status']) && $input_results['status'] === 'no_new_items') {
					wp_send_json_success( array(
						'status' => 'success_no_items',
						'message' => $input_results['message'] ?? __( 'No new items found matching the criteria.', 'data-machine' ),
						'output_result' => array( 'message' => __( 'No new input data found.', 'data-machine' ) )
					) );
					wp_die();
				}

				// Case 2: Input handler returned an array of eligible item packets
				// Note: Files handler might return a single packet directly, need to standardize or handle.
				// For now, assume handlers returning data return an array of packets.
				if (is_array($input_results) && !isset($input_results['status'])) {
					$job_ids = [];
					$jobs_queued = 0;
					$job_errors = 0;

					// Prepare shared module config once
					$module_job_config = array(
						'module_id' => $module->module_id,
						'project_id' => $module->project_id, 
						'output_type' => $module->output_type,
						'output_config' => $decoded_output_config, // Use already decoded config
						'process_data_prompt' => $module->process_data_prompt,
						'fact_check_prompt' => $module->fact_check_prompt,
						'finalize_response_prompt' => $module->finalize_response_prompt,
						'log_steps' => true, // Enable stepwise logging for admin page jobs
					);
					$module_config_json = wp_json_encode($module_job_config);
					if ($module_config_json === false) {
						throw new Exception( __( 'Failed to serialize shared module config for jobs.', 'data-machine' ) . ' Error: ' . json_last_error_msg() );
					}

					// Loop through each eligible item packet and create a job
					foreach ($input_results as $item_packet) {
						if (!is_array($item_packet)) continue; // Skip invalid entries

						$input_data_json = wp_json_encode($item_packet);
						if ($input_data_json === false) {
							// Use Logger Service
							$this->locator->get('logger')->error('Failed to serialize input data for a job', ['module_id' => $module_id, 'item_metadata' => $item_packet['metadata'] ?? 'N/A', 'json_error' => json_last_error_msg()]);
							$job_errors++;
							continue; // Skip this item
						}

						// Insert Job into DB
						global $wpdb;
						$jobs_table = $wpdb->prefix . 'dm_jobs';
						$inserted = $wpdb->insert(
							$jobs_table,
							array(
								'module_id' => $module_id,
								'user_id' => $user_id,
								'status' => 'pending',
								'module_config' => $module_config_json, // Use shared config
								'input_data' => $input_data_json,
								'created_at' => current_time( 'mysql', 1 ), 
							),
							array( '%d', '%d', '%s', '%s', '%s', '%s' )
						);

						if ( false === $inserted ) {
							// Use Logger Service
							$this->locator->get('logger')->error('Failed to insert job into database', ['module_id' => $module_id, 'db_error' => $wpdb->last_error, 'item_metadata' => $item_packet['metadata'] ?? 'N/A']);
							$job_errors++;
							continue; // Skip scheduling for this item
						}

						$job_id = $wpdb->insert_id;
						$job_ids[] = $job_id; // Collect successful job IDs

						// Schedule Cron Event for this Job
						$scheduled = wp_schedule_single_event( time(), 'dm_run_job_event', array( 'job_id' => $job_id ) );
						if ( false === $scheduled ) {
							// Use Logger Service
							$this->locator->get('logger')->error('Failed to schedule WP-Cron event for job', ['job_id' => $job_id]);
							// Don't increment $job_errors here, job was inserted but scheduling failed (WP Cron handles retries often)
						}
						
						$jobs_queued++;
					} // End foreach ($input_results as $item_packet)

					// Send final response based on queuing results
					if ($jobs_queued > 0) {
						$message = sprintf(
							_n(
								'Successfully queued %d processing job.', 
								'Successfully queued %d processing jobs.',
								$jobs_queued, 
								'data-machine'
							), 
							$jobs_queued
						);
						if ($job_errors > 0) {
							$message .= ' ' . sprintf(
								_n(
									'Could not create job for %d item.',
									'Could not create jobs for %d items.',
									$job_errors,
									'data-machine'
								), 
								$job_errors
							) . ' ' . __('See error log for details.', 'data-machine');
						}
						wp_send_json_success( array(
							'status' => 'processing_queued',
							'message' => $message,
							'job_ids' => $job_ids, // Return array of queued job IDs
							'job_id' => isset($job_ids[0]) ? $job_ids[0] : null // For single-job polling compatibility
						) );
					} else {
						// All items resulted in errors during job creation
						throw new Exception( __( 'Failed to create processing jobs for any of the found items.', 'data-machine' ) );
					}
					
				} else {
					// Case 3: Unexpected return type from input handler
					// Use Logger Service
					$this->locator->get('logger')->error('Unexpected data format returned by input handler.', ['module_id' => $module_id, 'data_source_type' => $data_source_type]);
					throw new Exception( __( 'Unexpected data format received from input handler.', 'data-machine' ) );
				}

			} catch (Exception $e) {
				// Catch exceptions from get_input_data or orchestrator->run
				 // Use Logger Service
				$this->locator->get('logger')->error('Error during AJAX processing: ' . $e->getMessage(), ['data_source_type' => $data_source_type, 'module_id' => $module_id]); 
				wp_send_json_error( array( 'message' => $e->getMessage() ) );
			}
		} else {
			// Use Logger Service
			$this->locator->get('logger')->error('Could not load the appropriate input handler.', ['data_source_type' => $data_source_type, 'module_id' => $module_id]); 
			wp_send_json_error(array('message' => __('Could not load the appropriate input handler.', 'data-machine')));
		}
		wp_die(); // Ensure execution stops
	}

    /**
	 * AJAX handler to fetch data for a specific module.
     * Moved from Data_Machine class.
	 * @since 0.2.0 (Moved 0.12.0)
	 */
	public function get_module_data_ajax_handler() {
		check_ajax_referer('dm_get_module_nonce', 'nonce');

		$module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
		$user_id = get_current_user_id();

		if (empty($module_id)) {
			wp_send_json_error(array('message' => 'Module ID missing.'));
			return;
		}

        // Get dependencies from locator
        $db_modules = $this->locator->get('database_modules');
        // Ensure Database_Projects class is loaded if not already via autoloader/main file
        if (!class_exists('Data_Machine_Database_Projects')) {
             require_once DATA_MACHINE_PATH . 'includes/database/class-database-projects.php';
        }
        $db_projects = $this->locator->get('database_projects'); // Assumes registered

		// --- Ownership Check (using Project) ---
        $module = $db_modules->get_module($module_id); // Get module without user check first
        if (!$module || !isset($module->project_id)) {
             wp_send_json_error(array('message' => __('Invalid module or project association missing.', 'data-machine')));
             return;
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            wp_send_json_error(array('message' => __('Permission denied for this module.', 'data-machine')));
            return;
        }
        // --- End Ownership Check ---


        // Decode configs first
        $decoded_output_config = !empty($module->output_config) ? json_decode(wp_unslash($module->output_config), true) : array();
        $decoded_ds_config = !empty($module->data_source_config) ? json_decode(wp_unslash($module->data_source_config), true) : array();

        // Ensure they are arrays after decoding
        if (!is_array($decoded_output_config)) $decoded_output_config = [];
        if (!is_array($decoded_ds_config)) $decoded_ds_config = [];
        
        // Extract remote_site_info from configs if they exist
        $output_remote_site_info = null;
        $ds_remote_site_info = null;
        
        if (isset($decoded_output_config['remote_site_info'])) {
            $output_remote_site_info = $decoded_output_config['remote_site_info'];
            // Remove from top level to avoid duplication when nesting
            unset($decoded_output_config['remote_site_info']);
        }
        
        if (isset($decoded_ds_config['remote_site_info'])) {
            $ds_remote_site_info = $decoded_ds_config['remote_site_info'];
            // Remove from top level to avoid duplication when nesting
            unset($decoded_ds_config['remote_site_info']);
        }

		// Prepare data to return
		$data_to_return = array(
			'module_id' => $module->module_id,
            'project_id' => $module->project_id, // Include project ID
			'module_name' => $module->module_name,
			'process_data_prompt' => $module->process_data_prompt,
			'fact_check_prompt' => $module->fact_check_prompt,
			'finalize_response_prompt' => $module->finalize_response_prompt,
			'data_source_type' => $module->data_source_type,
			'output_type' => $module->output_type,
			// Return configs nested under their handler slug key
			'output_config' => [$module->output_type => $decoded_output_config],
			'data_source_config' => [$module->data_source_type => $decoded_ds_config]
		);

		// Ensure the top-level keys exist even if config was empty
		if (!isset($data_to_return['output_config'][$module->output_type])) {
			$data_to_return['output_config'][$module->output_type] = [];
		}
		if (!isset($data_to_return['data_source_config'][$module->data_source_type])) {
			$data_to_return['data_source_config'][$module->data_source_type] = [];
		}
		
		// Add remote_site_info back to the response at both locations for backward compatibility
		// 1. At the top level of each config section
		if ($output_remote_site_info) {
		    $data_to_return['output_config']['remote_site_info'] = $output_remote_site_info;
		}
		if ($ds_remote_site_info) {
		    $data_to_return['data_source_config']['remote_site_info'] = $ds_remote_site_info;
		}
		
		// 2. Also include inside the handler-specific config for better future structure
		if ($output_remote_site_info && $module->output_type === 'publish_remote') {
		    $data_to_return['output_config'][$module->output_type]['remote_site_info'] = $output_remote_site_info;
		}
		if ($ds_remote_site_info && $module->data_source_type === 'airdrop_rest_api') { // Corrected slug check
		    $data_to_return['data_source_config'][$module->data_source_type]['remote_site_info'] = $ds_remote_site_info;
		}

		wp_send_json_success($data_to_return);
	}

    /**
	 * Callback function for the WP-Cron event to process a job.
     * Moved from Data_Machine class.
	 * @since 0.10.0 (Moved 0.12.0)
	 * @param int $job_id The ID of the job to process.
	 */
	public function dm_run_job_callback( $job_id ) {
		// --- Basic Log: Confirm callback start ---
		error_log("--- DM Job Callback STARTING for Job ID: " . $job_id . " ---");
		// --- End Basic Log ---

		global $wpdb;
		$jobs_table = $wpdb->prefix . 'dm_jobs';

		// 1. Retrieve job details
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $jobs_table WHERE job_id = %d", $job_id ) );

		// Basic validation
		if ( ! $job || $job->status !== 'pending' ) {
			// Check if it was already processed or failed, to avoid duplicate processing
			if ($job && ($job->status === 'complete' || $job->status === 'failed')) {
				// Use Logger Service (Warning level might be suitable)
				$this->locator->get('logger')->warning( 'Cron job callback invoked for already processed/failed job', [ 'job_id' => $job_id, 'job_status' => $job->status ] );
			} else {
				// Use Logger Service
				$this->locator->get('logger')->error( 'Cron job callback invoked for invalid or non-pending job', [ 'job_id' => $job_id, 'job_status' => $job->status ?? 'not_found' ] );
			}
			return; // Don't proceed
		}

		// 2. Update status to 'processing'
		$wpdb->update(
			$jobs_table,
			array(
				'status' => 'processing',
				'started_at' => current_time( 'mysql', 1 ) // GMT
			),
			array( 'job_id' => $job_id ),
			array( '%s', '%s' ),
			array( '%d' ) 
		);

		$final_status = 'failed'; // Default to failed unless successful
		$result_data = null;
        $orchestrator = $this->locator->get('orchestrator'); // Get orchestrator via locator

		try {
			// 3. Deserialize data
			$module_config_unslashed = wp_unslash( $job->module_config ?? '' );
			// Don't unslash input_data before json_decode, let the decoder handle JSON escapes
			$input_data_raw = $job->input_data ?? '';
			// Ensure module config is treated as an array now
			$module_job_config = json_decode( $module_config_unslashed, true ); // Decode as array
			$input_data_packet = json_decode( $input_data_raw, true ); // Decode raw input data

			if ( ! $module_job_config || ! is_array( $module_job_config ) || ! $input_data_packet || ! is_array( $input_data_packet ) ) {
				// Use Logger Service
				$this->locator->get('logger')->error('JSON Decode Failure in Job Callback', [ 'job_id' => $job_id, 'json_error' => json_last_error_msg() ]);
				throw new Exception( __( 'Failed to decode job data.', 'data-machine' ) );
			}

			// Log for debugging (Keep direct error_log for now? Or use logger->debug?)
			error_log("DM Job Callback {$job_id}: Input data AFTER json_decode: " . print_r($input_data_packet, true)); // Log after decoding
			// $this->locator->get('logger')->debug("DM Job Callback {$job_id}: Input data AFTER json_decode: ", $input_data_packet);

			if (empty($module_job_config)) { // Simplified check, input_data checked below
				throw new Exception('Job data (module config) is missing or invalid.');
			}

			// Sanity check input data format (should be an array packet)
			if (empty($input_data_packet) || !is_array($input_data_packet) || (!isset($input_data_packet['content_string']) && !isset($input_data_packet['file_info']))) {
				// Check if it's accidentally nested
				if (!empty($input_data_packet) && is_array($input_data_packet) && isset($input_data_packet[0]) && is_array($input_data_packet[0]) && (isset($input_data_packet[0]['content_string']) || isset($input_data_packet[0]['file_info']))) {
					 error_log("DM Job Callback {$job_id}: Detected nested input_data. Correcting.");
					 $input_data_packet = $input_data_packet[0]; // <<< POTENTIAL FIX AREA
					 error_log("DM Job Callback {$job_id}: Input data AFTER correction: " . print_r($input_data_packet, true)); // Log after correction
				} else {
					// If not nested or empty, it's genuinely malformed
					// Log with Logger Service
					$this->locator->get('logger')->error('DM Job Callback {$job_id}: Malformed input_data detected.', ['data_export' => $input_data_packet]); 
					throw new Exception('Malformed input data packet received by job runner.');
				}
			} else {
				 // Keep direct error_log for simple confirmation?
				 error_log("DM Job Callback {$job_id}: Input data appears valid (not nested or corrected). Passing as is.");
				 // $this->locator->get('logger')->debug("DM Job Callback {$job_id}: Input data appears valid (not nested or corrected).");
			}

			// 4. Run the orchestrator
            if (!$orchestrator) {
                 throw new Exception('Orchestrator service not found.');
            }
			$result = $orchestrator->run( $input_data_packet, $module_job_config, $job->user_id, $job_id );

			// 5. Process result
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			} elseif ( isset( $result['status'] ) && $result['status'] === 'error' ) {
				throw new Exception( $result['message'] ?? __( 'Orchestrator returned an unknown error.', 'data-machine' ) );
			} else {
				// Success!
				$final_status = 'complete';
				$result_data = wp_json_encode( $result ); // Store the successful result

				// --- Log Processed Item --- 
				if ( isset( $input_data_packet['metadata']['source_type'] ) && isset( $input_data_packet['metadata']['item_identifier_to_log'] ) && isset( $module_job_config['module_id'] ) ) {
					$db_processed_items = $this->locator->get('database_processed_items');
					if ($db_processed_items) {
						$module_id_to_log = absint($module_job_config['module_id']);
						$source_type_to_log = sanitize_text_field($input_data_packet['metadata']['source_type']);
						$identifier_to_log = $input_data_packet['metadata']['item_identifier_to_log']; // Keep as is, DB class handles escaping

						if ($module_id_to_log > 0 && !empty($source_type_to_log) && !empty($identifier_to_log)) {
							$added = $db_processed_items->add_processed_item($module_id_to_log, $source_type_to_log, $identifier_to_log);
							if (!$added) {
								// Log failure, but don't fail the whole job
								// Use Logger Service
								$this->locator->get('logger')->error('Failed to add processed item record to tracking table', [
									'job_id' => $job_id,
									'module_id' => $module_id_to_log,
									'source_type' => $source_type_to_log,
									'item_identifier' => substr($identifier_to_log, 0, 100) . '...' // Truncate for logging
								]);
							} else {
								// Optional: Log success
								// $this->locator->get('logger')->info("Data Machine: Successfully logged processed item for job {$job_id}");
							}
						} else {
							// Use Logger Service
							$this->locator->get('logger')->error('Missing data needed to log processed item', [
								'job_id' => $job_id,
								'module_id' => $module_id_to_log ?? 'missing',
								'source_type' => $source_type_to_log ?? 'missing',
								'identifier_present' => !empty($identifier_to_log)
							]);
						}
					} else {
						// Use Logger Service
						$this->locator->get('logger')->error('Database_Processed_Items service not found when trying to log processed item.', ['job_id' => $job_id]);
					}
				} else {
					// Use Logger Service
					$this->locator->get('logger')->error('Missing data needed to log processed item', [
						'job_id' => $job_id,
						'module_id' => $module_id_to_log ?? 'missing',
						'source_type' => $source_type_to_log ?? 'missing',
						'identifier_present' => !empty($identifier_to_log)
					]);
				}
				// --- End Log Processed Item ---
			}

		} catch ( Exception $e ) {
			// Store the error message
			$result_data = wp_json_encode( array( 'error' => $e->getMessage() ) );
			// Use Logger Service
			$this->locator->get('logger')->error( 'Exception during background job execution', [ 'job_id' => $job_id, 'error' => $e->getMessage() ] );
			// $final_status remains 'failed'
		}

		// 6. Update final status and result
		$wpdb->update(
			$jobs_table,
			array(
				'status' => $final_status,
				'result_data' => $result_data,
				'completed_at' => current_time( 'mysql', 1 ) // GMT
			),
			array( 'job_id' => $job_id ),
			array( '%s', '%s', '%s' ), // format
			array( '%d' ) // where_format
		);

		// --- Cleanup: Delete the persistent file if it exists ---
		if ( isset( $input_data_packet['file_info']['persistent_path'] ) ) {
			$persistent_file_path = $input_data_packet['file_info']['persistent_path'];
			if ( file_exists( $persistent_file_path ) ) {
				if ( ! unlink( $persistent_file_path ) ) {
					// Log an error if deletion fails, but don't stop execution
					// Use Logger Service
					$this->locator->get('logger')->error( 'Failed to delete persistent job file', [ 'job_id' => $job_id, 'path' => $persistent_file_path ] );
				}
			}
		}
		// --- End Cleanup ---
	} // End dm_run_job_callback


	/**
	 * AJAX handler for saving module settings.
	 *
	 * @deprecated 1.5.0 Use standard form submission for better user experience and proper admin notices.
	 * This handler is maintained for backwards compatibility only and will be removed in a future version.
	 */
	public function save_module_ajax_handler() {
		// Log deprecation warning
		$initial_logger = $this->locator->get('logger');
		if ($initial_logger) {
			$initial_logger->warning('DEPRECATED: The save_module_ajax_handler is deprecated and will be removed in a future version. Use standard form submission instead.');
		} else {
			error_log('Data Machine Warning: save_module_ajax_handler is deprecated. Use standard form submission instead.');
		}

		// Log that the handler was reached
		$initial_logger = $this->locator->get('logger');
		if ($initial_logger) {
			$initial_logger->info('Save Module AJAX: Handler reached.');
		} else {
			error_log('Data Machine Error: Logger service not available at start of save_module_ajax_handler.');
		}

		$nonce_action = 'dm_save_module_nonce';
		check_ajax_referer( $nonce_action, 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'data-machine' ) ] );
			return;
		}

		// Get services from locator
		$db_modules = $this->locator->get('database_modules');
		$logger     = $this->locator->get('logger');

		if ( ! $db_modules || ! $logger ) {
			wp_send_json_error( [ 'message' => __( 'Internal error: Required services missing.', 'data-machine' ) ] );
			return;
		}

		// --- Parse Form Data ---
		// WordPress might parse the form data into nested arrays based on field names like 'output_config[handler_slug][setting]'
		// We need to retrieve the raw POST data or rely on WP's parsing. Let's assume WP parsing for now.
		$form_data = wp_unslash( $_POST ); // Use unslashed POST data

		$module_id = isset( $form_data['Data_Machine_current_module'] ) ? absint( $form_data['Data_Machine_current_module'] ) : 0;
		$project_id = isset( $form_data['project_id'] ) ? absint( $form_data['project_id'] ) : 0; // Need to ensure this is passed from JS

		// Basic validation
		if ( $module_id === 0 && empty( $project_id ) ) {
			$logger->error( 'Save Module AJAX: Missing project ID for new module.', [ 'user_id' => $user_id, 'post_data' => $form_data ] );
			wp_send_json_error( [ 'message' => __( 'Error: Project ID is missing for new module creation.', 'data-machine' ) ] );
			return;
		}

		// --- Prepare Module Data Array ---
		$module_data = [];
		$module_data['module_name']              = sanitize_text_field( $form_data['module_name'] ?? '' );
		$module_data['process_data_prompt']      = wp_kses_post( $form_data['process_data_prompt'] ?? '' );
		$module_data['fact_check_prompt']        = wp_kses_post( $form_data['fact_check_prompt'] ?? '' );
		$module_data['finalize_response_prompt'] = wp_kses_post( $form_data['finalize_response_prompt'] ?? '' );
		$module_data['data_source_type']         = sanitize_text_field( $form_data['data_source_type'] ?? 'files' );
		$module_data['output_type']              = sanitize_text_field( $form_data['output_type'] ?? 'data_export' );

		// Extract nested configs - IMPORTANT: Assumes field names like "data_source_config[handler_slug][setting_key]"
		$ds_config_raw = $form_data['data_source_config'] ?? [];
		$out_config_raw = $form_data['output_config'] ?? [];

		// We only want the config for the *selected* handlers
		$module_data['data_source_config'] = isset( $ds_config_raw[ $module_data['data_source_type'] ] )
											? $ds_config_raw[ $module_data['data_source_type'] ]
											: [];
		$module_data['output_config']      = isset( $out_config_raw[ $module_data['output_type'] ] )
											? $out_config_raw[ $module_data['output_type'] ]
											: [];
											
		// --- Sanitize Configs (Example - Needs more robust sanitization based on expected fields) ---
		// This is a basic example. Ideally, each handler should define its expected settings and sanitize them.
		$module_data['data_source_config'] = map_deep( $module_data['data_source_config'], 'sanitize_text_field' );
		$module_data['output_config']      = map_deep( $module_data['output_config'], 'sanitize_text_field' );
		// Note: Some fields might need wp_kses_post, absint, filter_var(..., FILTER_VALIDATE_URL) etc.
		// map_deep might be too broad; specific sanitization per field is better.

		$logger->info( 'Save Module AJAX: Preparing to save data.', [
			'user_id' => $user_id,
			'module_id' => $module_id,
			'project_id' => $project_id,
			'parsed_module_data' => $module_data // Log the data being saved
		] );

		// --- Perform DB Operation ---
		try {
			if ( $module_id > 0 ) {
				// Update existing module (update_module handles ownership check internally)
				$result = $db_modules->update_module( $module_id, $module_data, $user_id );
				if ( $result === false ) {
					// update_module returns false on DB error or permission error
					$logger->error( 'Save Module AJAX: Failed to update module.', [ 'module_id' => $module_id, 'user_id' => $user_id ] );
					wp_send_json_error( [ 'message' => __( 'Failed to update module. Check permissions or logs.', 'data-machine' ) ] );
				} elseif ( $result === 0 ) {
					// No rows affected (data might be identical)
					wp_send_json_success( [
						'message' => __( 'Module settings unchanged.', 'data-machine' ),
						'module_id' => $module_id,
						'operation' => 'update_no_change'
					] );
				} else {
					// Success
					wp_send_json_success( [
						'message' => __( 'Module updated successfully.', 'data-machine' ),
						'module_id' => $module_id,
						'operation' => 'update'
					] );
				}
			} else {
				// Create new module
				$new_module_id = $db_modules->create_module( $project_id, $module_data );
				if ( $new_module_id ) {
					wp_send_json_success( [
						'message' => __( 'Module created successfully.', 'data-machine' ),
						'module_id' => $new_module_id,
						'operation' => 'create'
					] );
				} else {
					$logger->error( 'Save Module AJAX: Failed to create module.', [ 'project_id' => $project_id, 'user_id' => $user_id ] );
					wp_send_json_error( [ 'message' => __( 'Failed to create module. Check logs.', 'data-machine' ) ] );
				}
			}
		} catch ( Exception $e ) {
			$logger->error( 'Save Module AJAX: Exception occurred.', [ 'error' => $e->getMessage(), 'user_id' => $user_id, 'module_id' => $module_id ] );
			wp_send_json_error( [ 'message' => __( 'An unexpected error occurred.', 'data-machine' ) . ' ' . $e->getMessage() ] );
		}

		wp_die(); // Should not be reached if wp_send_json_* is called
	}

} // End class Data_Machine_Module_Ajax_Handler