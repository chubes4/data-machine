<?php
/**
 * Jobs database status management component.
 *
 * Handles job status transitions, validation, and state machine logic.
 * Part of the modular Jobs architecture following single responsibility principle.
 *
 * Pipeline → Flow architecture implementation.
 *
 * @package    Data_Machine
 * @subpackage Core\Database\Jobs
 * @since      0.15.0
 */

namespace DataMachine\Core\Database\Jobs;

use DataMachine\Engine\Actions\Cache;

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
     * @var \wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * Initialize the status component.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'dm_jobs';
    }

    /**
     * Update the status for a job.
     *
     * @param int    $job_id The job ID.
     * @param string $status The new status (e.g., 'processing').
     * @return bool True on success, false on failure.
     */
    public function start_job( int $job_id, string $status = 'processing' ): bool {
        if ( empty( $job_id ) ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $this->wpdb->update(
            $this->table_name,
            [
                'status' => $status,
            ],
            ['job_id' => $job_id],
            ['%s'], // Format for data
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
        // Update validation to include all final statuses
        $valid_statuses = ['completed', 'failed', 'completed_no_items'];

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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $this->wpdb->update(
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
        $all_databases = apply_filters('dm_db', []);
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
        
        if (empty($job_id)) {
            return false;
        }
        
        $update_data = ['status' => $status];
        $format = ['%s'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['job_id' => $job_id],
            $format,
            ['%d']
        );
        
        return $updated !== false;
    }



    private function get_job( int $job_id ): ?object {
        if ( empty( $job_id ) ) {
            return null;
        }
        $cache_key = Cache::JOB_STATUS_CACHE_KEY . $job_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $job = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE job_id = %d", $this->table_name, $job_id ), OBJECT );
            do_action('dm_cache_set', $cache_key, $job, 30, 'jobs'); // Very short 30s cache for job status
            $cached_result = $job;
        } else {
            $job = $cached_result;
        }
        return $job;
    }
}