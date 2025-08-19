<?php
/**
 * Jobs database CRUD operations component.
 *
 * Handles basic database operations for jobs: create, read, update, delete.
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

class JobsOperations {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the operations component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
    }

    /**
     * Create a new pipeline+flow-based job record.
     *
     * @param array $job_data Job data with pipeline_id, flow_id.
     * @return int|false The job ID on success, false on failure.
     */
    public function create_job(array $job_data): int|false {
        global $wpdb;
        
        $pipeline_id = absint($job_data['pipeline_id'] ?? 0);
        $flow_id = absint($job_data['flow_id'] ?? 0);
        
        // Validate required fields
        if (empty($pipeline_id) || empty($flow_id)) {
            do_action('dm_log', 'error', 'Invalid pipeline+flow-based job data', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id
            ]);
            return false;
        }
        
        $data = [
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id,
            'status' => 'pending'
        ];
        
        $format = ['%d', '%d', '%s'];
        
        $inserted = $wpdb->insert($this->table_name, $data, $format);
        
        if (false === $inserted) {
            do_action('dm_log', 'error', 'Failed to insert pipeline+flow-based job', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'db_error' => $wpdb->last_error
            ]);
            return false;
        }
        
        $job_id = $wpdb->insert_id;
        
        return $job_id;
    }

    /**
     * Get a specific job record by its ID.
     *
     * @param int $job_id The job ID.
     * @return object|null The job data as an object, or null if not found.
     */
    public function get_job( int $job_id ): ?object {
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

    /**
     * Get jobs count for list table pagination.
     * 
     * Used by JobsListTable for filter-based architecture compliance.
     *
     * @return int Total number of jobs
     */
    public function get_jobs_count(): int {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(job_id) FROM {$this->table_name}");
        return (int) $count;
    }

    /**
     * Get jobs for list table display.
     * 
     * Used by JobsListTable for filter-based architecture compliance.
     *
     * @param array $args Arguments including orderby, order, per_page, offset
     * @return array Array of job records with pipeline and flow names
     */
    public function get_jobs_for_list_table(array $args): array {
        global $wpdb;
        
        $orderby = $args['orderby'] ?? 'j.job_id';
        $order = strtoupper($args['order'] ?? 'DESC');
        $per_page = (int) ($args['per_page'] ?? 20);
        $offset = (int) ($args['offset'] ?? 0);
        
        // Validate order direction
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }
        
        $pipelines_table = $wpdb->prefix . 'dm_pipelines';
        $flows_table = $wpdb->prefix . 'dm_flows';
        
        $sql = $wpdb->prepare(
            "SELECT j.*, p.pipeline_name, f.flow_name
             FROM {$this->table_name} j
             LEFT JOIN {$pipelines_table} p ON j.pipeline_id = p.pipeline_id
             LEFT JOIN {$flows_table} f ON j.flow_id = f.flow_id
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get all jobs for a specific pipeline (for deletion impact analysis).
     *
     * @param int $pipeline_id The pipeline ID.
     * @return array Array of job records.
     */
    public function get_jobs_for_pipeline( int $pipeline_id ): array {
        if ( $pipeline_id <= 0 ) {
            return [];
        }
        
        // Get flows service using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            return [];
        }
        
        // Get all flows for this pipeline
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        if (empty($flows)) {
            return [];
        }
        
        // Aggregate jobs from all flows
        $all_jobs = [];
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_jobs = $this->get_jobs_for_flow($flow_id);
            $all_jobs = array_merge($all_jobs, $flow_jobs);
        }
        
        // Sort by created_at DESC (most recent first)
        if (!empty($all_jobs)) {
            usort($all_jobs, function($a, $b) {
                $time_a = is_array($a) ? $a['created_at'] : $a->created_at;
                $time_b = is_array($b) ? $b['created_at'] : $b->created_at;
                return strcmp($time_b, $time_a); // DESC order
            });
        }
        
        return $all_jobs;
    }

    /**
     * Get all jobs for a specific flow.
     *
     * @param int $flow_id The ID of the flow.
     * @return array Array of job records.
     */
    public function get_jobs_for_flow(int $flow_id): array {
        global $wpdb;
        
        if ($flow_id <= 0) {
            return [];
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE flow_id = %d ORDER BY created_at DESC",
            $flow_id
        ), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Delete jobs based on criteria.
     * 
     * Provides flexible deletion of jobs by status or all jobs.
     * Used for cleanup operations and maintenance tasks.
     *
     * @param array $criteria Deletion criteria with keys:
     *                        - 'all': Delete all jobs
     *                        - 'failed': Delete only failed jobs
     * @return int|false Number of rows deleted or false on error
     */
    public function delete_jobs(array $criteria = []): int|false {
        global $wpdb;
        
        if (empty($criteria)) {
            do_action('dm_log', 'warning', 'No criteria provided for jobs deletion');
            return false;
        }
        
        $where = '';
        $where_args = [];
        
        if (!empty($criteria['failed'])) {
            $where = "WHERE status = 'failed'";
        }
        // For 'all', no WHERE clause needed
        
        $sql = "DELETE FROM {$this->table_name} {$where}";
        
        $result = $wpdb->query($sql);
        
        do_action('dm_log', 'debug', 'Deleted jobs', [
            'criteria' => $criteria,
            'jobs_deleted' => $result !== false ? $result : 0,
            'success' => $result !== false
        ]);
        
        return $result;
    }
}