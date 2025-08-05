<?php
/**
 * Jobs database coordinator class - maintains public API while delegating to focused components.
 *
 * Follows handler-style modular architecture where the main class coordinates
 * between focused internal components for single responsibility compliance.
 *
 * Updated for Pipeline → Flow architecture (clean break from legacy project/module system).
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/jobs
 * @since      0.15.0
 */

namespace DataMachine\Core\Database\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Jobs {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Internal components for focused responsibilities.
     */
    private $operations;
    private $status;
    private $steps;
    private $cleanup;

    /**
     * Initialize the coordinator and internal components.
     * Uses direct instantiation - no caching complexity.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
        
        // Initialize focused components directly
        $this->operations = new JobsOperations();
        $this->status = new JobsStatus();
        $this->steps = new JobsSteps();
        $this->cleanup = new JobsCleanup();
    }

    // ========================================
    // CRUD Operations (delegated to JobsOperations)
    // ========================================

    /**
     * Create a new pipeline+flow-based job record.
     */
    public function create_job(array $job_data): int|false {
        return $this->operations->create_job($job_data);
    }

    /**
     * Get a specific job record by its ID.
     */
    public function get_job( int $job_id ): ?object {
        return $this->operations->get_job($job_id);
    }

    /**
     * Get jobs count for list table pagination.
     */
    public function get_jobs_count(): int {
        return $this->operations->get_jobs_count();
    }

    /**
     * Get jobs for list table display.
     */
    public function get_jobs_for_list_table(array $args): array {
        return $this->operations->get_jobs_for_list_table($args);
    }

    // ========================================
    // Status Management (delegated to JobsStatus)
    // ========================================

    /**
     * Update the status and started_at time for a job.
     */
    public function start_job( int $job_id, string $status = 'processing' ): bool {
        return $this->status->start_job($job_id, $status);
    }

    /**
     * Update the status and completed_at time for a job.
     */
    public function complete_job( int $job_id, string $status, ?string $error_details = null ): bool {
        return $this->status->complete_job($job_id, $status, $error_details);
    }

    /**
     * Update job status.
     */
    public function update_job_status(int $job_id, string $status, ?string $error_details = null): bool {
        return $this->status->update_job_status($job_id, $status, $error_details);
    }

    /**
     * Check if there are any active jobs for a specific flow.
     */
    public function has_active_jobs_for_flow( int $flow_id, int $exclude_job_id = null ): bool {
        return $this->status->has_active_jobs_for_flow($flow_id, $exclude_job_id);
    }

    /**
     * Check if there are any active jobs for a specific pipeline.
     */
    public function has_active_jobs_for_pipeline( int $pipeline_id, int $exclude_job_id = null ): bool {
        return $this->status->has_active_jobs_for_pipeline($pipeline_id, $exclude_job_id);
    }

    /**
     * Get all jobs for a specific pipeline (for deletion impact analysis).
     */
    public function get_jobs_for_pipeline( int $pipeline_id ): array {
        return $this->operations->get_jobs_for_pipeline($pipeline_id);
    }

    /**
     * Get all jobs for a specific flow.
     */
    public function get_jobs_for_flow( int $flow_id ): array {
        return $this->operations->get_jobs_for_flow($flow_id);
    }

    // ========================================
    // Step Management (delegated to JobsSteps)
    // ========================================

    /**
     * Update step data for a specific step by name.
     */
    public function update_step_data_by_name( int $job_id, string $step_name, string $data ): bool {
        return $this->steps->update_step_data_by_name($job_id, $step_name, $data);
    }

    /**
     * Get step data for a specific step by name.
     */
    public function get_step_data_by_name( int $job_id, string $step_name ): ?string {
        return $this->steps->get_step_data_by_name($job_id, $step_name);
    }

    /**
     * Get all step data for a job as an associative array.
     */
    public function get_job_step_data( int $job_id ): array {
        return $this->steps->get_job_step_data($job_id);
    }

    /**
     * Get step sequence for a job.
     */
    public function get_job_step_sequence( int $job_id ): array {
        return $this->steps->get_job_step_sequence($job_id);
    }

    /**
     * Set step sequence for a job.
     */
    public function set_job_step_sequence( int $job_id, array $step_sequence ): bool {
        return $this->steps->set_job_step_sequence($job_id, $step_sequence);
    }

    /**
     * Get current step name for a job.
     */
    public function get_current_step_name( int $job_id ): ?string {
        return $this->steps->get_current_step_name($job_id);
    }

    /**
     * Update current step name for a job.
     */
    public function update_current_step_name( int $job_id, string $step_name ): bool {
        return $this->steps->update_current_step_name($job_id, $step_name);
    }

    /**
     * Advance job to next step in sequence.
     */
    public function advance_job_to_next_step( int $job_id ): bool {
        return $this->steps->advance_job_to_next_step($job_id);
    }

    // ========================================
    // Cleanup Operations (delegated to JobsCleanup)
    // ========================================

    /**
     * Clean up stuck jobs that have been running/pending for too long.
     */
    public function cleanup_stuck_jobs( $timeout_hours = null ) {
        return $this->cleanup->cleanup_stuck_jobs($timeout_hours);
    }

    /**
     * Get summary of job statuses for monitoring.
     */
    public function get_job_status_summary() {
        return $this->cleanup->get_job_status_summary();
    }

    /**
     * Delete old completed/failed jobs to keep table size manageable.
     */
    public function cleanup_old_jobs( $days_to_keep = null ) {
        return $this->cleanup->cleanup_old_jobs($days_to_keep);
    }

    /**
     * Schedule job data cleanup after completion.
     */
    public function schedule_cleanup( int $job_id, int $cleanup_delay_hours = 24 ): bool {
        return $this->cleanup->schedule_cleanup($job_id, $cleanup_delay_hours);
    }

    // ========================================
    // Static Methods (table creation)
    // ========================================

    /**
     * Create the jobs database table on plugin activation.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE $table_name (
            job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pipeline_id bigint(20) unsigned NOT NULL,
            flow_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL,
            current_step_name varchar(50) NULL DEFAULT NULL,
            step_sequence longtext NULL,
            flow_config longtext NULL,
            step_data longtext NULL,
            cleanup_scheduled datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime NULL DEFAULT NULL,
            completed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (job_id),
            KEY status (status),
            KEY current_step_name (current_step_name),
            KEY pipeline_id (pipeline_id),
            KEY flow_id (flow_id),
            KEY cleanup_scheduled (cleanup_scheduled)
        ) $charset_collate;";

        dbDelta( $sql );

        // Log table creation
        $logger = apply_filters('dm_get_logger', null);
        if ( $logger ) {
            $logger->debug( 'Created jobs database table with pipeline+flow architecture', [
                'table_name' => $table_name,
                'action' => 'create_table'
            ] );
        }
    }

}