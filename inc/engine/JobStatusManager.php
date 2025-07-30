<?php
/**
 * Centralized job status management for Data Machine.
 *
 * Handles all job status transitions with validation, logging, and business logic.
 * Replaces scattered status update calls throughout the codebase.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.17.0
 */

namespace DataMachine\Engine;

use DataMachine\Core\Database\{Jobs, Projects};
use DataMachine\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class JobStatusManager {

	/**
	 * Valid job statuses and their allowed transitions.
	 */
	private const VALID_STATUSES = [
		'pending' => ['running', 'failed'],
		'running' => ['completed', 'failed', 'completed_with_errors'],
	];

	/**
	 * Final statuses that cannot transition to other states.
	 */
	private const FINAL_STATUSES = ['completed', 'failed', 'completed_with_errors'];

	/**
	 * Constructor - parameter-less for pure filter-based architecture.
	 * Services accessed via ultra-direct filters.
	 */
	public function __construct() {
		// All services accessed via filters - no constructor dependencies
	}

	/**
	 * Start a job by setting status to 'running'.
	 *
	 * @param int $job_id The job ID to start.
	 * @return bool True on success, false on failure.
	 */
	public function start(int $job_id): bool {
		return $this->transition_status($job_id, 'running', 'Job started');
	}


	/**
	 * Complete a job with a specific final status.
	 *
	 * @param int $job_id The job ID to complete.
	 * @param string $status Final status: 'completed' or 'completed_with_errors'.
	 * @param string|null $error_details Optional error details for logging.
	 * @param string|null $message Optional completion message.
	 * @return bool True on success, false on failure.
	 */
	public function complete(int $job_id, string $status = 'completed', ?string $error_details = null, ?string $message = null): bool {
		$logger = apply_filters('dm_get_logger', null);
		$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
		
		// Validate it's a valid completion status
		if (!in_array($status, ['completed', 'completed_with_errors'], true)) {
			$logger?->error("Invalid completion status: {$status}", ['job_id' => $job_id]);
			return false;
		}

		$completion_message = $message ?: "Job completed with status: {$status}";
		
		// Use the existing complete_job method which handles timestamps and project updates
		$success = $db_jobs->complete_job($job_id, $status, $error_details);
		
		if ($success) {
			$logger?->info($completion_message, [
				'job_id' => $job_id,
				'final_status' => $status,
				'has_error_details' => $error_details !== null
			]);
		} else {
			$logger?->error("Failed to complete job", [
				'job_id' => $job_id,
				'attempted_status' => $status
			]);
		}

		return $success;
	}

	/**
	 * Fail a job with an error message.
	 *
	 * @param int $job_id The job ID to fail.
	 * @param string $error_message Error message describing the failure.
	 * @return bool True on success, false on failure.
	 */
	public function fail(int $job_id, string $error_message): bool {
		$logger = apply_filters('dm_get_logger', null);
		$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
		
		$error_data = wp_json_encode([
			'error' => $error_message,
			'timestamp' => current_time('mysql', true)
		]);

		// Use the existing complete_job method for final status
		$success = $db_jobs->complete_job($job_id, 'failed', $error_data);
		
		if ($success) {
			$logger?->error("Job failed: {$error_message}", [
				'job_id' => $job_id,
				'final_status' => 'failed'
			]);
		} else {
			$logger?->error("Failed to update job status to failed", [
				'job_id' => $job_id,
				'attempted_status' => 'failed',
				'original_error' => $error_message
			]);
		}

		return $success;
	}

	/**
	 * Get the current status of a job.
	 *
	 * @param int $job_id The job ID.
	 * @return string|null The current status, or null if job not found.
	 */
	public function get_status(int $job_id): ?string {
		$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
		$job = $db_jobs->get_job($job_id);
		return $job ? $job->status : null;
	}

	/**
	 * Check if a status transition is valid.
	 *
	 * @param string $current_status Current job status.
	 * @param string $new_status Proposed new status.
	 * @return bool True if transition is valid, false otherwise.
	 */
	public function is_valid_transition(string $current_status, string $new_status): bool {
		// Allow any transition from null/empty (new jobs)
		if (empty($current_status)) {
			return true;
		}

		// Final statuses cannot transition
		if (in_array($current_status, self::FINAL_STATUSES, true)) {
			return false;
		}

		// Check if transition is allowed
		$allowed_transitions = self::VALID_STATUSES[$current_status] ?? [];
		return in_array($new_status, $allowed_transitions, true);
	}

	/**
	 * Internal method to handle status transitions with validation.
	 *
	 * @param int $job_id The job ID.
	 * @param string $new_status The new status to set.
	 * @param string $log_message Message to log.
	 * @return bool True on success, false on failure.
	 */
	private function transition_status(int $job_id, string $new_status, string $log_message): bool {
		$logger = apply_filters('dm_get_logger', null);
		$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
		
		// Get current status
		$current_status = $this->get_status($job_id);
		
		if ($current_status === null) {
			$logger?->error("Job not found for status transition", ['job_id' => $job_id]);
			return false;
		}

		// Validate transition
		if (!$this->is_valid_transition($current_status, $new_status)) {
			$logger?->error("Invalid status transition attempted", [
				'job_id' => $job_id,
				'current_status' => $current_status,
				'new_status' => $new_status
			]);
			return false;
		}

		// Perform the transition using appropriate database method
		$success = false;
		
		if ($new_status === 'running') {
			$success = $db_jobs->start_job($job_id, $new_status);
		} else {
			// For other non-final statuses, we need to use the raw update
			// Since there's no specific method for processing_output, etc.
			global $wpdb;
			$table_name = $wpdb->prefix . 'dm_jobs';
			
			$result = $wpdb->update(
				$table_name,
				['status' => $new_status, 'updated_at' => current_time('mysql', 1)],
				['job_id' => $job_id],
				['%s', '%s'],
				['%d']
			);
			
			$success = $result !== false;
		}

		if ($success) {
			$logger?->info($log_message, [
				'job_id' => $job_id,
				'previous_status' => $current_status,
				'new_status' => $new_status
			]);
		} else {
			$logger?->error("Failed to transition job status", [
				'job_id' => $job_id,
				'current_status' => $current_status,
				'attempted_status' => $new_status
			]);
		}

		return $success;
	}

	/**
	 * Get human-readable status description.
	 *
	 * @param string $status The status code.
	 * @return string Human-readable description.
	 */
	public function get_status_description(string $status): string {
		$descriptions = [
			'pending' => 'Waiting to start',
			'running' => 'Processing',
			'completed' => 'Completed successfully',
			'completed_with_errors' => 'Completed with some errors',
			'failed' => 'Failed'
		];

		return $descriptions[$status] ?? "Unknown status: {$status}";
	}

	/**
	 * Check if a job status is final (cannot be changed).
	 *
	 * @param string $status The status to check.
	 * @return bool True if status is final, false otherwise.
	 */
	public function is_final_status(string $status): bool {
		return in_array($status, self::FINAL_STATUSES, true);
	}
}