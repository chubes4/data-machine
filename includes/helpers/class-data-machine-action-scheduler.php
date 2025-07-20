<?php

/**
 * Class Data_Machine_Action_Scheduler
 *
 * Wrapper service for Action Scheduler integration with Data Machine.
 * Provides queue management, concurrency controls, and unified scheduling interface.
 *
 * @package Data_Machine
 * @subpackage Helpers
 */
class Data_Machine_Action_Scheduler {

	/**
	 * Action group for Data Machine jobs
	 */
	// Removed - now using Data_Machine_Constants::ACTION_GROUP

	/**
	 * Maximum concurrent jobs allowed
	 */
	// Removed - now using Data_Machine_Constants::MAX_CONCURRENT_JOBS

	/**
	 * Logger instance
	 * @var Data_Machine_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Data_Machine_Logger $logger Logger instance
	 */
	public function __construct( Data_Machine_Logger $logger = null ) {
		$this->logger = $logger;
		// Use the official Action Scheduler init hook for guaranteed API availability
		add_action( 'action_scheduler_init', array( $this, 'init_hooks' ) );
	}

	/**
	 * Initialize Action Scheduler hooks and settings
	 */
	public function init_hooks() {
		// Only initialize if Action Scheduler is available
		if ( ! $this->is_available() ) {
			$this->logger?->warning( 'Action Scheduler not available, skipping hook initialization' );
			return;
		}

		// Set concurrent queue limit
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'set_concurrent_limit' ) );
		
		// Add our action group to allowed groups (if needed)
		add_filter( 'action_scheduler_store_class', array( $this, 'maybe_configure_store' ) );
		
		// Register output job handler for Action Scheduler
		add_action( Data_Machine_Constants::OUTPUT_JOB_HOOK, array( $this, 'handle_output_job' ), 10, 5 );
	}

	/**
	 * Set concurrent batch limit for Action Scheduler
	 *
	 * @param int $concurrent_batches Default concurrent batches
	 * @return int Modified concurrent batches
	 */
	public function set_concurrent_limit( $concurrent_batches ) {
		return Data_Machine_Constants::MAX_CONCURRENT_JOBS;
	}

	/**
	 * Configure Action Scheduler store if needed
	 *
	 * @param string $store_class Store class name
	 * @return string Store class name
	 */
	public function maybe_configure_store( $store_class ) {
		// Future hook for store configuration if needed
		return $store_class;
	}

	/**
	 * Schedule a single job to run immediately
	 *
	 * @param string $hook Action hook name
	 * @param array  $args Arguments to pass to the action
	 * @param int    $timestamp When to run (default: now)
	 * @return int|false Action ID on success, false on failure
	 */
	public function schedule_single_job( $hook, $args = array(), $timestamp = null ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger?->error( 'Action Scheduler not available for scheduling job', array(
				'hook' => $hook,
				'args' => $args
			) );
			return false;
		}

		if ( $timestamp === null ) {
			$timestamp = time();
		}

		try {
			$action_id = as_schedule_single_action( 
				$timestamp, 
				$hook, 
				$args, 
				Data_Machine_Constants::ACTION_GROUP 
			);

			$this->logger?->info( 'Job scheduled via Action Scheduler', array(
				'action_id' => $action_id,
				'hook' => $hook,
				'args' => $args,
				'timestamp' => $timestamp
			) );

			return $action_id;

		} catch ( Exception $e ) {
			$this->logger?->error( 'Failed to schedule Action Scheduler job', array(
				'hook' => $hook,
				'args' => $args,
				'error' => $e->getMessage()
			) );
			return false;
		}
	}

	/**
	 * Schedule a recurring job
	 *
	 * @param int    $timestamp First run timestamp
	 * @param int    $interval_in_seconds Interval between runs
	 * @param string $hook Action hook name
	 * @param array  $args Arguments to pass to the action
	 * @return int|false Action ID on success, false on failure
	 */
	public function schedule_recurring_job( $timestamp, $interval_in_seconds, $hook, $args = array() ) {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			$this->logger?->error( 'Action Scheduler not available for recurring job', array(
				'hook' => $hook,
				'args' => $args
			) );
			return false;
		}

		try {
			$action_id = as_schedule_recurring_action( 
				$timestamp, 
				$interval_in_seconds, 
				$hook, 
				$args, 
				Data_Machine_Constants::ACTION_GROUP 
			);

			$this->logger?->info( 'Recurring job scheduled via Action Scheduler', array(
				'action_id' => $action_id,
				'hook' => $hook,
				'args' => $args,
				'timestamp' => $timestamp,
				'interval' => $interval_in_seconds
			) );

			return $action_id;

		} catch ( Exception $e ) {
			$this->logger?->error( 'Failed to schedule recurring Action Scheduler job', array(
				'hook' => $hook,
				'args' => $args,
				'error' => $e->getMessage()
			) );
			return false;
		}
	}

	/**
	 * Cancel all scheduled actions for a specific hook and arguments
	 *
	 * @param string $hook Action hook name
	 * @param array  $args Arguments (optional)
	 * @return void
	 */
	public function cancel_scheduled_jobs( $hook, $args = array() ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			$this->logger?->error( 'Action Scheduler not available for canceling jobs', array(
				'hook' => $hook,
				'args' => $args
			) );
			return;
		}

		try {
			as_unschedule_all_actions( $hook, $args, Data_Machine_Constants::ACTION_GROUP );

			$this->logger?->info( 'Canceled scheduled jobs via Action Scheduler', array(
				'hook' => $hook,
				'args' => $args
			) );

		} catch ( Exception $e ) {
			$this->logger?->error( 'Failed to cancel Action Scheduler jobs', array(
				'hook' => $hook,
				'args' => $args,
				'error' => $e->getMessage()
			) );
		}
	}

	/**
	 * Get count of pending/running jobs for specific hook
	 *
	 * @param string $hook Action hook name
	 * @param array  $args Arguments (optional)
	 * @return int Number of pending/running jobs
	 */
	public function get_pending_job_count( $hook, $args = array() ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$pending_actions = as_get_scheduled_actions( array(
			'hook' => $hook,
			'args' => $args,
			'status' => array( 'pending', 'in-progress' ),
			'group' => Data_Machine_Constants::ACTION_GROUP
		) );

		return count( $pending_actions );
	}

	/**
	 * Check if Action Scheduler is available and functional
	 *
	 * @return bool True if Action Scheduler is available
	 */
	public function is_available() {
		return function_exists( 'as_schedule_single_action' ) && 
		       function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Handle output job processing via Action Scheduler
	 *
	 * @param int    $job_id Original job ID (content retrieved from database)
	 * @param array  $module_config Module configuration
	 * @param int    $user_id User ID
	 * @param array  $input_metadata Input metadata
	 * @return void
	 */
	public function handle_output_job( $job_id, $module_config, $user_id, $input_metadata ) {
		global $data_machine_container;
		
		// First, retrieve the final output content from the job record
		if ( ! isset( $data_machine_container['db_jobs'] ) ) {
			$this->logger?->error( 'Output Job Handler: Jobs database not available', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}
		
		$db_jobs = $data_machine_container['db_jobs'];
		$job = $db_jobs->get_job( $job_id );
		
		if ( ! $job || empty( $job->result_data ) ) {
			$this->logger?->error( 'Output Job Handler: No job data found or result_data empty', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}
		
		$job_result = json_decode( $job->result_data, true );
		if ( ! $job_result || ! isset( $job_result['final_output'] ) ) {
			$this->logger?->error( 'Output Job Handler: Invalid job result data or missing final_output', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}
		
		$final_output = $job_result['final_output'];
		
		if ( ! $data_machine_container || ! isset( $data_machine_container['handler_factory'] ) ) {
			$this->logger?->error( 'Output Job Handler: Handler factory not available', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}

		// CRITICAL FIX: Check if item has already been processed to prevent duplicate publishing
		if ( isset( $data_machine_container['db_processed_items'] ) ) {
			$db_processed_items = $data_machine_container['db_processed_items'];
			$module_id = $module_config['module_id'] ?? 0;
			$source_type = $module_config['data_source_type'] ?? '';
			$item_identifier = $input_metadata['item_identifier_to_log'] ?? null;

			if ( $module_id && $source_type && $item_identifier ) {
				if ( $db_processed_items->has_item_been_processed( $module_id, $source_type, $item_identifier ) ) {
					$this->logger?->info( 'Output Job Handler: Item already processed, skipping to prevent duplicate', array(
						'job_id' => $job_id,
						'module_id' => $module_id,
						'item_identifier' => $item_identifier
					) );

					// Mark job as complete since item was already processed
					if ( isset( $data_machine_container['db_jobs'] ) ) {
						$db_jobs = $data_machine_container['db_jobs'];
						$result_json = wp_json_encode( array( 'status' => 'skipped', 'message' => 'Item already processed' ) );
						$db_jobs->complete_job( $job_id, 'completed', $result_json );
					}
					return;
				}
			}
		}

		$handler_factory = $data_machine_container['handler_factory'];
		$output_type = $module_config['output_type'] ?? null;

		if ( empty( $output_type ) ) {
			$this->logger?->error( 'Output Job Handler: Output type not defined', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}

		try {
			// Create the output handler
			$output_handler = $handler_factory->create_handler( 'output', $output_type );

			if ( ! $output_handler instanceof Data_Machine_Output_Handler_Interface ) {
				$error_details = is_wp_error( $output_handler ) ? $output_handler->get_error_message() : 'Invalid handler type';
				throw new Exception( 'Could not create valid output handler: ' . $error_details );
			}

			// Execute the output handler
			$this->logger?->info( 'Output Job Handler: Processing output via Action Scheduler', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'output_type' => $output_type
			) );

			$result = $output_handler->handle( $final_output, $module_config, $user_id, $input_metadata );

			// Check for errors
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Update job status to complete
			if ( isset( $data_machine_container['db_jobs'] ) ) {
				$db_jobs = $data_machine_container['db_jobs'];
				$result_json = wp_json_encode( $result );
				$job_completed = $db_jobs->complete_job( $job_id, 'completed', $result_json );
				
				if ( $job_completed ) {
					$this->logger?->info( 'Output Job Handler: Parent job marked as completed', array(
						'job_id' => $job_id,
						'module_id' => $module_config['module_id'] ?? 'unknown'
					) );
				} else {
					$this->logger?->error( 'Output Job Handler: Failed to mark parent job as completed', array(
						'job_id' => $job_id,
						'module_id' => $module_config['module_id'] ?? 'unknown'
					) );
				}
			} else {
				$this->logger?->error( 'Output Job Handler: db_jobs not available in container', array(
					'job_id' => $job_id,
					'module_id' => $module_config['module_id'] ?? 'unknown'
				) );
			}

			// Mark item as processed AFTER successful output - this prevents duplicates
			$this->mark_item_as_processed( $module_config, $input_metadata, $job_id );

			$this->logger?->info( 'Output Job Handler: Output processed successfully', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'output_type' => $output_type
			) );

		} catch ( Exception $e ) {
			$this->logger?->error( 'Output Job Handler: Failed - no retries', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'output_type' => $output_type ?? 'unknown',
				'error' => $e->getMessage()
			) );

			// Mark as failed immediately - no retries
			if ( isset( $data_machine_container['db_jobs'] ) ) {
				$db_jobs = $data_machine_container['db_jobs'];
				$db_jobs->fail_job( $job_id, $e->getMessage() );
			}
		} catch ( Throwable $t ) {
			// CRITICAL: Catch ALL failures including fatal errors, type errors, etc.
			$this->logger?->error( 'Output Job Handler: Critical failure caught', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'error_type' => get_class( $t ),
				'error' => $t->getMessage(),
				'file' => $t->getFile(),
				'line' => $t->getLine()
			) );

			// ALWAYS mark job as failed to prevent ghost failures
			if ( isset( $data_machine_container['db_jobs'] ) ) {
				$db_jobs = $data_machine_container['db_jobs'];
				$db_jobs->complete_job( $job_id, 'failed', wp_json_encode( [
					'error' => $t->getMessage(),
					'error_type' => get_class( $t ),
					'file' => $t->getFile(),
					'line' => $t->getLine()
				] ) );
			}
		}
	}

	/**
	 * Get queue status information
	 *
	 * @return array Queue status data
	 */
	public function get_queue_status() {
		if ( ! $this->is_available() ) {
			return array(
				'available' => false,
				'error' => 'Action Scheduler not available'
			);
		}

		$status = array(
			'available' => true,
			'group' => Data_Machine_Constants::ACTION_GROUP,
			'max_concurrent' => Data_Machine_Constants::MAX_CONCURRENT_JOBS
		);

		// Get counts by status if functions exist
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$status['pending'] = count( as_get_scheduled_actions( array(
				'status' => 'pending',
				'group' => Data_Machine_Constants::ACTION_GROUP
			) ) );

			$status['running'] = count( as_get_scheduled_actions( array(
				'status' => 'in-progress',
				'group' => Data_Machine_Constants::ACTION_GROUP
			) ) );

			$status['completed'] = count( as_get_scheduled_actions( array(
				'status' => 'complete',
				'group' => Data_Machine_Constants::ACTION_GROUP,
				'per_page' => 10 // Limit to recent completions
			) ) );
		}

		return $status;
	}

	/**
	 * Mark an item as processed after successful output
	 *
	 * @param array $module_config Module configuration
	 * @param array $input_metadata Input metadata containing item identifier
	 * @param int   $job_id Job ID for logging
	 */
	private function mark_item_as_processed( $module_config, $input_metadata, $job_id ) {
		global $data_machine_container;

		if ( ! isset( $data_machine_container['db_processed_items'] ) ) {
			$this->logger?->error( 'Output Job Handler: Cannot mark item as processed - db_processed_items not available', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}

		$db_processed_items = $data_machine_container['db_processed_items'];

		$module_id = $module_config['module_id'] ?? null;
		$source_type = $module_config['data_source_type'] ?? null;
		$item_identifier = $input_metadata['item_identifier_to_log'] ?? null;

		if ( empty( $module_id ) || empty( $source_type ) || empty( $item_identifier ) ) {
			$this->logger?->error( 'Output Job Handler: Cannot mark item as processed - missing required data', array(
				'job_id' => $job_id,
				'module_id' => $module_id,
				'source_type' => $source_type,
				'item_identifier' => $item_identifier
			) );
			return;
		}

		$marked = $db_processed_items->add_processed_item( $module_id, $source_type, $item_identifier );
		
		if ( ! $marked ) {
			$this->logger?->error( 'Output Job Handler: Failed to mark item as processed', array(
				'job_id' => $job_id,
				'module_id' => $module_id,
				'source_type' => $source_type,
				'item_identifier' => $item_identifier
			) );
		} else {
			$this->logger?->info( 'Output Job Handler: Item marked as processed successfully', array(
				'job_id' => $job_id,
				'module_id' => $module_id,
				'item_identifier' => $item_identifier
			) );
		}
	}
}