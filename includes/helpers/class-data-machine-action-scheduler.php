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
		$this->init_hooks();
	}

	/**
	 * Initialize Action Scheduler hooks and settings
	 */
	private function init_hooks() {
		// Only initialize if Action Scheduler is available
		if ( ! $this->is_available() ) {
			$this->logger?->warning( 'Action Scheduler not available, skipping hook initialization' );
			return;
		}

		// Set concurrent queue limit
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', array( $this, 'set_concurrent_limit' ) );
		
		// Add our action group to allowed groups (if needed)
		add_filter( 'action_scheduler_store_class', array( $this, 'maybe_configure_store' ) );
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