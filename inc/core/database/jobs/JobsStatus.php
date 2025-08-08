<?php
/**
 * Jobs database status management component.
 *
 * Handles job status transitions, validation, and state machine logic.
 * Part of the modular Jobs architecture following single responsibility principle.
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

class JobsStatus {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the status component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
    }

    /**
     * Update the status and started_at time for a job.
     *
     * @param int    $job_id The job ID.
     * @param string $status The new status (e.g., 'processing').
     * @return bool True on success, false on failure.
     */
    public function start_job( int $job_id, string $status = 'processing' ): bool {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return false;
        }
        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'started_at' => current_time( 'mysql', 1 ), // GMT time
            ],
            ['job_id' => $job_id],
            ['%s', '%s'], // Format for data
            ['%d']  // Format for WHERE
        );
        return $updated !== false;
    }

    /**
     * Update the status and completed_at time for a job.
     * Also updates the last_run_at field in the related flow if possible.
     *
     * @param int    $job_id       The job ID.
     * @param string $status       The final status ('complete' or 'failed').
     * @return bool True on success, false on failure.
     */
    public function complete_job( int $job_id, string $status ): bool {
        global $wpdb;
        // Update validation to include all final statuses
        $valid_statuses = ['completed', 'failed', 'completed_with_errors', 'completed_no_items'];

        if ( empty( $job_id ) || !in_array( $status, $valid_statuses ) ) {
            return false;
        }

        // Get job details once for both operations
        $job = $this->get_job($job_id);
        if (!$job) {
            return false;
        }

        // Update job status
        $update_data = [
            'status' => $status,
            'completed_at' => current_time( 'mysql', 1 ), // GMT time
        ];
        $format = ['%s', '%s'];
        
        $updated = $wpdb->update(
            $this->table_name,
            $update_data,
            ['job_id' => $job_id],
            $format, // Format for data
            ['%d']  // Format for WHERE
        );

        if ( false === $updated ) {
            return false;
        }

        // Update flow last_run_at using existing services if available
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        if ($db_flows && !empty($job->flow_id)) {
            // Update flow's last_run_at in scheduling configuration
            $flow_updated = $db_flows->update_flow_last_run($job->flow_id, current_time('mysql', 1));
            
            // Log flow update failure if logger available
            if (!$flow_updated) {
                do_action('dm_log', 'warning', 'Failed to update flow last_run_at', [
                    'job_id' => $job_id,
                    'flow_id' => $job->flow_id
                ]);
            }
        }

        return true;
    }

    /**
     * Update job status.
     *
     * @param int $job_id The job ID.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function update_job_status(int $job_id, string $status): bool {
        global $wpdb;
        
        if (empty($job_id)) {
            return false;
        }
        
        $update_data = ['status' => $status];
        $format = ['%s'];
        
        $updated = $wpdb->update(
            $this->table_name,
            $update_data,
            ['job_id' => $job_id],
            $format,
            ['%d']
        );
        
        return $updated !== false;
    }

    /**
     * Check if there are any active (pending or running) jobs for a specific flow.
     *
     * @param int $flow_id The ID of the flow to check.
     * @param int $exclude_job_id Optional job ID to exclude from the check.
     * @return bool True if there are active jobs, false otherwise.
     */
    public function has_active_jobs_for_flow( int $flow_id, ?int $exclude_job_id = null ): bool {
        global $wpdb;

        if ( $exclude_job_id ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE flow_id = %d AND job_id != %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $flow_id ),
                absint( $exclude_job_id )
            ) );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE flow_id = %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $flow_id )
            ) );
        }

        return $count > 0;
    }

    /**
     * Check if there are any active (pending or running) jobs for a specific pipeline.
     *
     * @param int $pipeline_id The ID of the pipeline to check.
     * @param int $exclude_job_id Optional job ID to exclude from the check.
     * @return bool True if there are active jobs, false otherwise.
     */
    public function has_active_jobs_for_pipeline( int $pipeline_id, ?int $exclude_job_id = null ): bool {
        global $wpdb;

        if ( $exclude_job_id ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE pipeline_id = %d AND job_id != %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $pipeline_id ),
                absint( $exclude_job_id )
            ) );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE pipeline_id = %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $pipeline_id )
            ) );
        }

        return $count > 0;
    }

    /**
     * Get a specific job record by its ID.
     * Helper method for status management operations.
     *
     * @param int $job_id The job ID.
     * @return object|null The job data as an object, or null if not found.
     */
    private function get_job( int $job_id ): ?object {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return null;
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        );
        $job = $wpdb->get_row( $sql );
        return $job;
    }
}