<?php
/**
 * Jobs database cleanup and maintenance component.
 *
 * Handles cleanup operations, monitoring, and maintenance tasks.
 * Part of the modular Jobs architecture following single responsibility principle.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/jobs
 * @since      0.14.0
 */

namespace DataMachine\Core\Database\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class JobsCleanup {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the cleanup component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
    }

    /**
     * Clean up stuck jobs that have been running/pending for too long.
     * 
     * @param int $timeout_hours Hours after which jobs are considered stuck (uses constant default)
     * @return int Number of jobs cleaned up
     */
    public function cleanup_stuck_jobs( $timeout_hours = null ) {
        if ( $timeout_hours === null ) {
            $constants = apply_filters('dm_get_constants', null);
            $timeout_hours = $constants::JOB_STUCK_TIMEOUT_HOURS;
        }
        global $wpdb;
        
        $timeout_minutes = $timeout_hours * 60;
        
        // Find stuck jobs
        $stuck_jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT job_id, project_id, status, created_at 
             FROM {$this->table_name} 
             WHERE status IN ('pending', 'running', 'processing_output') 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $timeout_minutes
        ) );
        
        if ( empty( $stuck_jobs ) ) {
            return 0;
        }
        
        // Log each stuck job being cleaned up
        $logger = apply_filters('dm_get_logger', null);
        if ( $logger ) {
            foreach ( $stuck_jobs as $job ) {
                $hours_stuck = round( ( time() - strtotime( $job->created_at ) ) / 3600, 1 );
                $logger->warning( "Cleaning up stuck job - marking as failed", [
                    'job_id' => $job->job_id,
                    'project_id' => $job->project_id,
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'hours_stuck' => $hours_stuck,
                    'timeout_hours' => $timeout_hours
                ] );
            }
        }
        
        // Mark stuck jobs as failed with detailed result data
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'failed', 
                 result_data = %s,
                 completed_at = NOW()
             WHERE status IN ('pending', 'running', 'processing_output') 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            wp_json_encode( [ 'error' => 'Job stuck for more than ' . $timeout_hours . ' hours - automatically marked as failed' ] ),
            $timeout_minutes
        ) );
        
        return $updated !== false ? count( $stuck_jobs ) : 0;
    }
    
    /**
     * Get summary of job statuses for monitoring.
     * 
     * @return array Job status counts
     */
    public function get_job_status_summary() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY status"
        );
        
        $summary = [];
        foreach ( $results as $row ) {
            $summary[ $row->status ] = (int) $row->count;
        }
        
        return $summary;
    }
    
    /**
     * Delete old completed/failed jobs to keep table size manageable.
     * 
     * @param int $days_to_keep Days to keep job records (default 30)
     * @return int Number of jobs deleted
     */
    public function cleanup_old_jobs( $days_to_keep = null ) {
        if ( $days_to_keep === null ) {
            $constants = apply_filters('dm_get_constants', null);
            $days_to_keep = $constants::JOB_CLEANUP_OLD_DAYS;
        }
        global $wpdb;
        
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('completed', 'failed', 'completed_no_items', 'completed_with_errors') 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ) );
        
        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Schedule job data cleanup after completion.
     *
     * @param int $job_id The job ID.
     * @param int $cleanup_delay_hours Hours to delay cleanup (default 24).
     * @return bool True on success, false on failure.
     */
    public function schedule_cleanup( int $job_id, int $cleanup_delay_hours = 24 ): bool {
        global $wpdb;
        
        if ( empty( $job_id ) ) {
            return false;
        }
        
        $cleanup_time = current_time( 'mysql', 1 );
        $cleanup_time = gmdate( 'Y-m-d H:i:s', strtotime( $cleanup_time . " +{$cleanup_delay_hours} hours" ) );
        
        $updated = $wpdb->update(
            $this->table_name,
            ['cleanup_scheduled' => $cleanup_time],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }
}