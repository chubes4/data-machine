<?php
/**
 * Dashboard database aggregation class for Data Machine plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Database_Dashboard {
    /**
     * Get recent successful jobs.
     *
     * @param int $limit Number of jobs to retrieve.
     * @param int|null $project_id Optional project ID to filter by.
     * @return array
     */
    public function get_recent_successful_jobs( $limit = 10, $project_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dm_jobs';
        $statuses = [ 'completed', 'completed_with_errors', 'completed_no_items' ];
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = "SELECT * FROM $table WHERE status IN ($status_placeholders)";
        $params = $statuses;
        if ( $project_id ) {
            $sql .= " AND project_id = %d";
            $params[] = $project_id;
        }
        $sql .= " ORDER BY completed_at DESC LIMIT %d";
        $params[] = $limit;
        $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return $results ? $results : [];
    }

    /**
     * Get recent failed jobs.
     *
     * @param int $limit Number of jobs to retrieve.
     * @param int|null $project_id Optional project ID to filter by.
     * @return array
     */
    public function get_recent_failed_jobs( $limit = 10, $project_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dm_jobs';
        $sql = "SELECT * FROM $table WHERE status = %s";
        $params = [ 'failed' ];
        if ( $project_id ) {
            $sql .= " AND project_id = %d";
            $params[] = $project_id;
        }
        $sql .= " ORDER BY completed_at DESC LIMIT %d";
        $params[] = $limit;
        $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return $results ? $results : [];
    }

    /**
     * Get total completed job count.
     *
     * @param int|null $project_id Optional project ID to filter by.
     * @return int
     */
    public function get_total_completed_job_count( $project_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dm_jobs';
        $statuses = [ 'completed', 'completed_with_errors', 'completed_no_items' ];
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = "SELECT COUNT(*) FROM $table WHERE status IN ($status_placeholders)";
        $params = $statuses;
        if ( $project_id ) {
            $sql .= " AND project_id = %d";
            $params[] = $project_id;
        }
        $count = $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
        return $count ? intval($count) : 0;
    }

    /**
     * Get upcoming scheduled runs.
     *
     * @param int $limit Number of scheduled runs to retrieve.
     * @param int|null $project_id Optional project ID to filter by.
     * @return array
     */
    public function get_upcoming_scheduled_runs( $limit = 10, $project_id = null ) {
        // This implementation assumes scheduled jobs are stored in dm_jobs with a status like 'scheduled' or 'pending'.
        global $wpdb;
        $table = $wpdb->prefix . 'dm_jobs';
        $sql = "SELECT * FROM $table WHERE status = %s";
        $params = [ 'scheduled' ];
        if ( $project_id ) {
            $sql .= " AND project_id = %d";
            $params[] = $project_id;
        }
        $sql .= " ORDER BY scheduled_for ASC LIMIT %d";
        $params[] = $limit;
        $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return $results ? $results : [];
    }
} 