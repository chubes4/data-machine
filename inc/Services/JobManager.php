<?php
/**
 * Job Manager Service
 *
 * Centralized business logic for job lifecycle management.
 * Handles job creation, status transitions, and cleanup operations.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

use DataMachine\Core\JobStatus;
use DataMachine\Core\PluginSettings;

defined( 'ABSPATH' ) || exit;

class JobManager {

	private \DataMachine\Core\Database\Jobs\Jobs $db_jobs;
	private ProcessedItemsManager $processed_items_manager;

	public function __construct() {
		$this->db_jobs                 = new \DataMachine\Core\Database\Jobs\Jobs();
		$this->processed_items_manager = new ProcessedItemsManager();
	}

	/**
	 * Create a new job for a flow execution.
	 *
	 * @param int $flow_id Flow ID to execute
	 * @param int $pipeline_id Pipeline ID (optional, will be looked up if not provided)
	 * @return int|null Job ID on success, null on failure
	 */
	public function create( int $flow_id, int $pipeline_id = 0 ): ?int {
		if ( $pipeline_id <= 0 ) {
			$db_flows = new \DataMachine\Core\Database\Flows\Flows();
			$flow     = $db_flows->get_flow( $flow_id );
			if ( ! $flow ) {
				do_action( 'datamachine_log', 'error', 'Job creation failed - flow not found', array( 'flow_id' => $flow_id ) );
				return null;
			}
			$pipeline_id = (int) $flow['pipeline_id'];
		}

		$job_id = $this->db_jobs->create_job(
			array(
				'pipeline_id' => $pipeline_id,
				'flow_id'     => $flow_id,
			)
		);

		if ( ! $job_id ) {
			do_action(
				'datamachine_log',
				'error',
				'Job creation failed - database insert failed',
				array(
					'flow_id'     => $flow_id,
					'pipeline_id' => $pipeline_id,
				)
			);
			return null;
		}

		do_action(
			'datamachine_log',
			'debug',
			'Job created',
			array(
				'job_id'      => $job_id,
				'flow_id'     => $flow_id,
				'pipeline_id' => $pipeline_id,
			)
		);

		return $job_id;
	}

	/**
	 * Get a job by ID.
	 *
	 * @param int $job_id Job ID
	 * @return array|null Job data or null if not found
	 */
	public function get( int $job_id ): ?array {
		return $this->db_jobs->get_job( $job_id );
	}

	/**
	 * Get all jobs for a flow.
	 *
	 * @param int $flow_id Flow ID
	 * @return array Jobs for the flow
	 */
	public function getForFlow( int $flow_id ): array {
		return $this->db_jobs->get_jobs_for_flow( $flow_id );
	}

	/**
	 * Get all jobs for a pipeline.
	 *
	 * @param int $pipeline_id Pipeline ID
	 * @return array Jobs for the pipeline
	 */
	public function getForPipeline( int $pipeline_id ): array {
		return $this->db_jobs->get_jobs_for_pipeline( $pipeline_id );
	}

	/**
	 * Update job status with intelligent method selection.
	 *
	 * Automatically selects the appropriate database method based on
	 * context and status transitions (start_job, complete_job, or update_job_status).
	 *
	 * Supports compound statuses like "agent_skipped - reason" via JobStatus.
	 *
	 * @param int         $job_id Job ID to update
	 * @param string      $new_status New job status (may be compound like "agent_skipped - reason")
	 * @param string      $context Update context ('start', 'complete', 'update')
	 * @param string|null $old_status Previous job status for transition logic
	 * @return bool Success status
	 */
	public function updateStatus( int $job_id, string $new_status, string $context = 'update', ?string $old_status = null ): bool {
		$success = false;
		$status  = JobStatus::fromString( $new_status );

		if ( 'start' === $context || ( JobStatus::PROCESSING === $new_status && JobStatus::PENDING === $old_status ) ) {
			$success = $this->db_jobs->start_job( $job_id, $new_status );
		} elseif ( 'complete' === $context || $status->isFinal() ) {
			$success = $this->db_jobs->complete_job( $job_id, $new_status );
		} else {
			$success = $this->db_jobs->update_job_status( $job_id, $new_status );
		}

		if ( $status->shouldCleanupProcessedItems() && $success ) {
			$this->processed_items_manager->deleteForJob( $job_id );
		}

		return $success;
	}

	/**
	 * Start a job (sets start timestamp).
	 *
	 * @param int    $job_id Job ID
	 * @param string $status Status to set (default: 'processing')
	 * @return bool Success status
	 */
	public function start( int $job_id, string $status = 'processing' ): bool {
		return $this->db_jobs->start_job( $job_id, $status );
	}

	/**
	 * Complete a job (sets completion timestamp).
	 *
	 * @param int    $job_id Job ID
	 * @param string $status Final status (any JobStatus final status, may be compound)
	 * @return bool Success status
	 */
	public function complete( int $job_id, string $status ): bool {
		return $this->db_jobs->complete_job( $job_id, $status );
	}

	/**
	 * Fail a job with cleanup and logging.
	 *
	 * Marks job as failed, cleans up processed items, and optionally
	 * cleans up job data files based on plugin settings.
	 *
	 * @param int    $job_id Job ID to mark as failed
	 * @param string $reason Failure reason identifier
	 * @param array  $context_data Additional context for logging
	 * @return bool True on success, false on database failure
	 */
	public function fail( int $job_id, string $reason, array $context_data = array() ): bool {
		$success = $this->db_jobs->complete_job( $job_id, JobStatus::FAILED );

		if ( ! $success ) {
			do_action(
				'datamachine_log',
				'error',
				'Failed to mark job as failed in database',
				array(
					'job_id' => $job_id,
					'reason' => $reason,
				)
			);
			return false;
		}

		$this->processed_items_manager->deleteForJob( $job_id );

		$cleanup_files = PluginSettings::get( 'cleanup_job_data_on_failure', true );
		$files_cleaned = false;

		if ( $cleanup_files ) {
			$job = $this->db_jobs->get_job( $job_id );
			if ( $job && function_exists( 'datamachine_get_file_context' ) ) {
				$cleanup       = new \DataMachine\Core\FilesRepository\FileCleanup();
				$context       = datamachine_get_file_context( $job['flow_id'] );
				$deleted_count = $cleanup->cleanup_job_data_packets( $job_id, $context );
				$files_cleaned = $deleted_count > 0;
			}
		}

		do_action(
			'datamachine_log',
			'error',
			'Job marked as failed',
			array(
				'job_id'                  => $job_id,
				'failure_reason'          => $reason,
				'triggered_by'            => 'JobManager::fail',
				'context_data'            => $context_data,
				'processed_items_cleaned' => true,
				'files_cleanup_enabled'   => $cleanup_files,
				'files_cleaned'           => $files_cleaned,
			)
		);

		return true;
	}

	/**
	 * Delete jobs based on criteria.
	 *
	 * @param array $criteria Deletion criteria ('all' => true or 'failed' => true)
	 * @param bool  $cleanup_processed Whether to cleanup associated processed items
	 * @return array Result with deleted count and cleanup info
	 */
	public function delete( array $criteria, bool $cleanup_processed = false ): array {
		$job_ids_to_delete = array();

		if ( $cleanup_processed ) {
			global $wpdb;
			$jobs_table = $wpdb->prefix . 'datamachine_jobs';

			if ( ! empty( $criteria['failed'] ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$job_ids_to_delete = $wpdb->get_col( $wpdb->prepare( 'SELECT job_id FROM %i WHERE status = %s', $jobs_table, 'failed' ) );
			} elseif ( ! empty( $criteria['all'] ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$job_ids_to_delete = $wpdb->get_col( $wpdb->prepare( 'SELECT job_id FROM %i', $jobs_table ) );
			}
		}

		$deleted_count = $this->db_jobs->delete_jobs( $criteria );

		if ( false === $deleted_count ) {
			return array(
				'success'                 => false,
				'jobs_deleted'            => 0,
				'processed_items_cleaned' => 0,
			);
		}

		if ( $cleanup_processed && ! empty( $job_ids_to_delete ) ) {
			foreach ( $job_ids_to_delete as $job_id ) {
				$this->processed_items_manager->deleteForJob( (int) $job_id );
			}
		}

		return array(
			'success'                 => true,
			'jobs_deleted'            => $deleted_count,
			'processed_items_cleaned' => $cleanup_processed ? count( $job_ids_to_delete ) : 0,
		);
	}

	/**
	 * Store engine data for a job.
	 *
	 * @param int   $job_id Job ID
	 * @param array $data Engine data to store
	 * @return bool Success status
	 */
	public function storeEngineData( int $job_id, array $data ): bool {
		return $this->db_jobs->store_engine_data( $job_id, $data );
	}

	/**
	 * Retrieve engine data for a job.
	 *
	 * @param int $job_id Job ID
	 * @return array Engine data
	 */
	public function retrieveEngineData( int $job_id ): array {
		return $this->db_jobs->retrieve_engine_data( $job_id );
	}
}
