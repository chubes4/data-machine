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
	const ACTION_GROUP = 'data-machine';

	/**
	 * Maximum concurrent jobs allowed
	 */
	const MAX_CONCURRENT_JOBS = 2;

	/**
	 * Maximum retry attempts for failed output jobs
	 */
	const MAX_OUTPUT_RETRIES = 3;

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
		add_action( 'dm_output_job_event', array( $this, 'handle_output_job' ), 10, 6 );
	}

	/**
	 * Set concurrent batch limit for Action Scheduler
	 *
	 * @param int $concurrent_batches Default concurrent batches
	 * @return int Modified concurrent batches
	 */
	public function set_concurrent_limit( $concurrent_batches ) {
		return self::MAX_CONCURRENT_JOBS;
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
				self::ACTION_GROUP 
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
				self::ACTION_GROUP 
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
			as_unschedule_all_actions( $hook, $args, self::ACTION_GROUP );

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
			'group' => self::ACTION_GROUP
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
	 * @param string $final_output Final processed output string
	 * @param array  $module_config Module configuration
	 * @param int    $user_id User ID
	 * @param array  $input_metadata Input metadata
	 * @param int    $job_id Original job ID
	 * @param int    $retry_count Current retry attempt (default 0)
	 * @return void
	 */
	public function handle_output_job( $final_output, $module_config, $user_id, $input_metadata, $job_id, $retry_count = 0 ) {
		global $data_machine_container;
		
		if ( ! $data_machine_container || ! isset( $data_machine_container['handler_factory'] ) ) {
			$this->logger?->error( 'Output Job Handler: Handler factory not available', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
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
				$db_jobs->complete_job( $job_id, 'complete', $result_json );
			}

			$this->logger?->info( 'Output Job Handler: Output processed successfully', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'output_type' => $output_type
			) );

		} catch ( Exception $e ) {
			$this->logger?->error( 'Output Job Handler: Exception during processing', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown',
				'output_type' => $output_type,
				'retry_count' => $retry_count,
				'error' => $e->getMessage()
			) );

			// Attempt retry if under max retry limit
			if ( $retry_count < self::MAX_OUTPUT_RETRIES ) {
				$next_retry = $retry_count + 1;
				$delay = pow( 2, $retry_count ) * 60; // Exponential backoff: 1min, 2min, 4min
				
				$this->logger?->info( 'Output Job Handler: Scheduling retry', array(
					'job_id' => $job_id,
					'module_id' => $module_config['module_id'] ?? 'unknown',
					'retry_count' => $next_retry,
					'delay_seconds' => $delay
				) );

				// Schedule retry
				$retry_action_id = $this->schedule_single_job(
					'dm_output_job_event',
					array(
						$final_output,
						$module_config,
						$user_id,
						$input_metadata,
						$job_id,
						$next_retry
					),
					time() + $delay
				);

				if ( $retry_action_id === false ) {
					$this->logger?->error( 'Output Job Handler: Failed to schedule retry', array(
						'job_id' => $job_id,
						'retry_count' => $next_retry
					) );
					
					// Mark as failed since we couldn't schedule retry
					if ( isset( $data_machine_container['db_jobs'] ) ) {
						$db_jobs = $data_machine_container['db_jobs'];
						$db_jobs->fail_job( $job_id, 'Failed to schedule retry: ' . $e->getMessage() );
					}
				}
			} else {
				// Max retries exceeded, mark as failed
				$this->logger?->error( 'Output Job Handler: Max retries exceeded', array(
					'job_id' => $job_id,
					'module_id' => $module_config['module_id'] ?? 'unknown',
					'retry_count' => $retry_count,
					'max_retries' => self::MAX_OUTPUT_RETRIES
				) );

				if ( isset( $data_machine_container['db_jobs'] ) ) {
					$db_jobs = $data_machine_container['db_jobs'];
					$db_jobs->fail_job( $job_id, 'Max retries exceeded: ' . $e->getMessage() );
				}
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
			'group' => self::ACTION_GROUP,
			'max_concurrent' => self::MAX_CONCURRENT_JOBS
		);

		// Get counts by status if functions exist
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$status['pending'] = count( as_get_scheduled_actions( array(
				'status' => 'pending',
				'group' => self::ACTION_GROUP
			) ) );

			$status['running'] = count( as_get_scheduled_actions( array(
				'status' => 'in-progress',
				'group' => self::ACTION_GROUP
			) ) );

			$status['completed'] = count( as_get_scheduled_actions( array(
				'status' => 'complete',
				'group' => self::ACTION_GROUP,
				'per_page' => 10 // Limit to recent completions
			) ) );
		}

		return $status;
	}
}