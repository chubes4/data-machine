<?php

/**
 * Class ActionScheduler
 *
 * Wrapper service for Action Scheduler integration with Data Machine.
 * Provides queue management, concurrency controls, and unified scheduling interface.
 *
 * @package Data_Machine
 * @subpackage Helpers
 */

namespace DataMachine\Helpers;

use DataMachine\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ActionScheduler {


	/**
	 * Logger instance
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Logger $logger Logger instance
	 */
	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger;
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
				Constants::ACTION_GROUP 
			);

			// Validate that scheduling actually succeeded (0 = failure)
			if ($action_id === false || $action_id === 0) {
				$this->logger?->error( 'Action Scheduler failed to schedule job', array(
					'action_id' => $action_id,
					'hook' => $hook,
					'args' => $args,
					'timestamp' => $timestamp
				) );
				return false;
			}

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
				Constants::ACTION_GROUP 
			);

			// Validate that scheduling actually succeeded (0 = failure)
			if ($action_id === false || $action_id === 0) {
				$this->logger?->error( 'Action Scheduler failed to schedule recurring job', array(
					'action_id' => $action_id,
					'hook' => $hook,
					'args' => $args,
					'timestamp' => $timestamp,
					'interval' => $interval_in_seconds
				) );
				return false;
			}

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
			as_unschedule_all_actions( $hook, $args, Constants::ACTION_GROUP );

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
			'group' => Constants::ACTION_GROUP
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
	 * @param int|array $job_id Job ID or array containing job_id for backwards compatibility
	 * @return void
	 */
	public function handle_output_job( $job_id = null ) {
		// Debug: Log exactly what we receive from Action Scheduler
		$this->logger?->info('Output handler called', [
			'job_id_received' => $job_id,
			'job_id_type' => gettype($job_id),
			'args_count' => func_num_args(),
			'all_args' => func_get_args()
		]);
		
		// Handle both direct job_id parameter and array format for backwards compatibility
		if ( is_array( $job_id ) ) {
			// Legacy format: first argument is an array containing job_id
			$job_id = $job_id['job_id'] ?? null;
			$this->logger?->info('Converted array format to job_id', ['extracted_job_id' => $job_id]);
		}
		global $data_machine_container;
		
		if ( empty( $job_id ) ) {
			$this->logger?->error( 'Output Job Handler: No job ID provided' );
			return;
		}
		
		// Get database dependencies
		$db_jobs = $data_machine_container['db_jobs'] ?? null;
		$job_status_manager = $data_machine_container['job_status_manager'] ?? null;
		
		if ( ! $db_jobs || ! $job_status_manager ) {
			$this->logger?->error( 'Output Job Handler: Missing database dependencies', ['job_id' => $job_id] );
			return;
		}
		
		// Get job and finalized data from database
		$job = $db_jobs->get_job( $job_id );
		if ( ! $job ) {
			$this->logger?->error( 'Output Job Handler: Job not found', ['job_id' => $job_id] );
			return;
		}
		
		$finalized_data_json = $db_jobs->get_step_data( $job_id, 4 );
		if ( empty( $finalized_data_json ) ) {
			$this->logger?->error( 'Output Job Handler: No finalized data available', ['job_id' => $job_id] );
			$job_status_manager->fail( $job_id, 'Output job failed: No finalized data available' );
			return;
		}
		
		$finalized_data = json_decode( $finalized_data_json, true );
		$final_output = $finalized_data['final_output_string'] ?? '';
		
		if ( empty( $final_output ) ) {
			$this->logger?->error( 'Output Job Handler: Empty final output', ['job_id' => $job_id] );
			$job_status_manager->fail( $job_id, 'Output job failed: Empty final output' );
			return;
		}
		
		// Get input metadata from database
		$input_data_json = $db_jobs->get_step_data( $job_id, 1 );
		$input_data_packet = json_decode( $input_data_json, true );
		$input_metadata = $input_data_packet['metadata'] ?? [];
		
		// Debug metadata flow issue
		$this->logger?->info('Output handler metadata debug', [
			'job_id' => $job_id,
			'input_data_json_length' => strlen($input_data_json ?: ''),
			'input_data_packet_keys' => is_array($input_data_packet) ? array_keys($input_data_packet) : 'not_array',
			'metadata_keys' => is_array($input_metadata) ? array_keys($input_metadata) : 'not_array',
			'item_identifier_raw' => $input_metadata['item_identifier_to_log'] ?? 'NOT_FOUND'
		]);
		
		// Get module config and user from job
		$module_config = json_decode( $job->module_config, true );
		$user_id = $job->user_id;
		
		if ( ! $data_machine_container || ! isset( $data_machine_container['handler_factory'] ) ) {
			$this->logger?->error( 'Output Job Handler: Handler factory not available', array(
				'job_id' => $job_id,
				'module_id' => $module_config['module_id'] ?? 'unknown'
			) );
			return;
		}

		// CRITICAL FIX: Check if item has already been processed to prevent duplicate publishing
		if ( isset( $data_machine_container['processed_items_manager'] ) ) {
			$processed_items_manager = $data_machine_container['processed_items_manager'];
			$module_id = $module_config['module_id'] ?? 0;
			$source_type = $module_config['data_source_type'] ?? '';
			$item_identifier = $input_metadata['item_identifier_to_log'] ?? null;

			if ( $module_id && $source_type && $item_identifier ) {
				if ( $processed_items_manager->is_item_processed( $module_id, $source_type, $item_identifier ) ) {
					$this->logger?->info( 'Output Job Handler: Item already processed, skipping to prevent duplicate', array(
						'job_id' => $job_id,
						'module_id' => $module_id,
						'item_identifier' => $item_identifier
					) );

					// Mark job as complete since item was already processed
					if ( isset( $data_machine_container['job_status_manager'] ) ) {
						$job_status_manager = $data_machine_container['job_status_manager'];
						$result_data = array( 'status' => 'skipped', 'message' => 'Item already processed' );
						$job_status_manager->complete( $job_id, 'completed', $result_data, 'Item already processed' );
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

			if ( is_wp_error( $output_handler ) ) {
				throw new Exception( 'Could not create valid output handler: ' . $output_handler->get_error_message() );
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

			// Mark main job complete after successful output processing
			if ( isset( $data_machine_container['job_status_manager'] ) && $job_id ) {
				$job_status_manager = $data_machine_container['job_status_manager'];
				$success_message = $result['message'] ?? 'Job completed: Output processed successfully';
				$job_completed = $job_status_manager->complete( $job_id, 'completed', $result, $success_message );
				
				if ( $job_completed ) {
					// Create handler-specific log data
					$log_data = [
						'job_id' => $job_id,
						'module_id' => $module_config['module_id'] ?? 'unknown',
						'output_type' => $output_type,
						'status' => $result['status'] ?? 'unknown'
					];
					
					// Add relevant ID field based on what the handler returned
					if ( isset( $result['local_post_id'] ) ) {
						$log_data['local_post_id'] = $result['local_post_id'];
					} elseif ( isset( $result['remote_post_id'] ) ) {
						$log_data['remote_post_id'] = $result['remote_post_id'];
					} elseif ( isset( $result['tweet_id'] ) ) {
						$log_data['tweet_id'] = $result['tweet_id'];
					} elseif ( isset( $result['post_id'] ) ) {
						$log_data['post_id'] = $result['post_id'];
					}
					
					$this->logger?->info( 'Output Job Handler: Job completed successfully', $log_data );
					
					// Schedule cleanup of large data fields after successful completion
					if ( $db_jobs ) {
						$db_jobs->schedule_cleanup( $job_id );
						$this->logger?->debug( 'Scheduled data cleanup for completed job', ['job_id' => $job_id] );
					}
				} else {
					$this->logger?->error( 'Output Job Handler: Failed to mark main job as completed', array(
						'job_id' => $job_id,
						'module_id' => $module_config['module_id'] ?? 'unknown'
					) );
				}
			}

			// Mark item as processed AFTER successful output - this prevents duplicates
			if ( isset( $data_machine_container['processed_items_manager'] ) ) {
				$processed_items_manager = $data_machine_container['processed_items_manager'];
				$module_id = $module_config['module_id'] ?? 0;
				$source_type = $module_config['data_source_type'] ?? '';
				$item_identifier = $input_metadata['item_identifier_to_log'] ?? null;
				$processed_items_manager->mark_item_processed( $module_id, $source_type, $item_identifier, $job_id );
			}

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

			// Mark main job as failed immediately - no retries
			if ( isset( $data_machine_container['job_status_manager'] ) && $job_id ) {
				$job_status_manager = $data_machine_container['job_status_manager'];
				$job_status_manager->fail( $job_id, 'Output job failed: ' . $e->getMessage() );
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

			// ALWAYS mark main job as failed to prevent ghost failures
			if ( isset( $data_machine_container['job_status_manager'] ) && $job_id ) {
				$job_status_manager = $data_machine_container['job_status_manager'];
				$error_message = sprintf( 'Output job critical failure: %s in %s:%d', $t->getMessage(), $t->getFile(), $t->getLine() );
				$job_status_manager->fail( $job_id, $error_message );
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
			'group' => Constants::ACTION_GROUP
		);

		// Get counts by status if functions exist
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$status['pending'] = count( as_get_scheduled_actions( array(
				'status' => 'pending',
				'group' => Constants::ACTION_GROUP
			) ) );

			$status['running'] = count( as_get_scheduled_actions( array(
				'status' => 'in-progress',
				'group' => Constants::ACTION_GROUP
			) ) );

			$status['completed'] = count( as_get_scheduled_actions( array(
				'status' => 'complete',
				'group' => Constants::ACTION_GROUP,
				'per_page' => 10 // Limit to recent completions
			) ) );
		}

		return $status;
	}

}