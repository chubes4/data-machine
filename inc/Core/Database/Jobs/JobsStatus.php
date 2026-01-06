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

use DataMachine\Core\JobStatus;

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
        $this->table_name = $this->wpdb->prefix . 'datamachine_jobs';
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
     * Accepts compound statuses like "agent_skipped - reason" via JobStatus validation.
     *
     * @param int    $job_id       The job ID.
     * @param string $status       The final status (any JobStatus final status, may be compound).
     * @return bool True on success, false on failure.
     */
    public function complete_job( int $job_id, string $status ): bool {
        // Validate using JobStatus - supports compound statuses like "agent_skipped - reason"
        if ( empty( $job_id ) || !JobStatus::isStatusFinal( $status ) ) {
            return false;
        }

        // Get job details once for both operations
        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();
        $job = $db_jobs->get_job($job_id);
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
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        if (!empty($job['flow_id'])) {
            // Update flow's last_run_at and last_run_status in scheduling configuration
            $flow_updated = $db_flows->update_flow_last_run($job['flow_id'], current_time('mysql', 1), $status);
            
            // Log flow update failure if logger available
            if (!$flow_updated) {
                do_action('datamachine_log', 'warning', 'Failed to update flow last_run_at', [
                    'job_id' => $job_id,
                    'flow_id' => $job['flow_id']
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
}