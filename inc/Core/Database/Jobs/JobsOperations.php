<?php
/**
 * Jobs Database CRUD Operations
 *
 * Core database operations with engine_data storage for centralized access.
 */

namespace DataMachine\Core\Database\Jobs;

use DataMachine\Engine\Actions\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class JobsOperations {

    private $table_name;

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
    }

    /**
     * Create job record with pipeline and flow association.
     */
    public function create_job(array $job_data): int|false {
        
        $pipeline_id = absint($job_data['pipeline_id'] ?? 0);
        $flow_id = absint($job_data['flow_id'] ?? 0);
        
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
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $this->wpdb->insert($this->table_name, $data, $format);
        
        if (false === $inserted) {
            do_action('dm_log', 'error', 'Failed to insert pipeline+flow-based job', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'db_error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        $job_id = $this->wpdb->insert_id;

        // Invalidate job caches
        do_action('dm_clear_jobs_cache');

        return $job_id;
    }

    public function get_job( int $job_id ): ?object {
        if ( empty( $job_id ) ) {
            return null;
        }
        $cache_key = Cache::JOB_CACHE_KEY . $job_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $job = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE job_id = %d", $this->table_name, $job_id ), OBJECT );
            do_action('dm_cache_set', $cache_key, $job, 300, 'jobs'); // 5 min cache for job data
            $cached_result = $job;
        } else {
            $job = $cached_result;
        }
        return $job;
    }

    /**
     * Get total jobs count for pagination.
     */
    public function get_jobs_count(): int {
        
        $cache_key = Cache::TOTAL_JOBS_COUNT_CACHE_KEY;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(job_id) FROM %i", $this->table_name ) );
            do_action('dm_cache_set', $cache_key, $count, 300, 'jobs'); // 5 min cache for counts
            $cached_result = $count;
        } else {
            $count = $cached_result;
        }
        return (int) $count;
    }

    /**
     * Get paginated jobs with pipeline and flow names.
     */
    public function get_jobs_for_list_table(array $args): array {
        
        $orderby = $args['orderby'] ?? 'j.job_id';
        $order = strtoupper($args['order'] ?? 'DESC');
        $per_page = (int) ($args['per_page'] ?? 20);
        $offset = (int) ($args['offset'] ?? 0);
        
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }
        
        $allowed_orderby = ['j.job_id', 'j.pipeline_id', 'j.flow_id', 'j.status', 'j.started_at', 'j.completed_at', 'p.pipeline_name', 'f.flow_name'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'j.job_id';
        }
        
        $pipelines_table = $this->wpdb->prefix . 'dm_pipelines';
        $flows_table = $this->wpdb->prefix . 'dm_flows';
        
        $cache_key = Cache::RECENT_JOBS_CACHE_KEY . md5($orderby . $order . $per_page . $offset);
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            $sql = "SELECT j.*, p.pipeline_name, f.flow_name FROM {$this->table_name} j LEFT JOIN {$pipelines_table} p ON j.pipeline_id = p.pipeline_id LEFT JOIN {$flows_table} f ON j.flow_id = f.flow_id ORDER BY $orderby $order";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $this->wpdb->get_results( $this->wpdb->prepare( $sql . " LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
            do_action('dm_cache_set', $cache_key, $results, 60, 'jobs'); // 1 min cache for recent jobs
            $cached_result = $results;
        } else {
            $results = $cached_result;
        }

        return $results;
    }

    /**
     * Get all jobs for pipeline deletion impact analysis.
     */
    public function get_jobs_for_pipeline( int $pipeline_id ): array {
        if ( $pipeline_id <= 0 ) {
            return [];
        }
        
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            return [];
        }
        
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        if (empty($flows)) {
            return [];
        }
        
        $all_jobs = [];
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_jobs = $this->get_jobs_for_flow($flow_id);
            $all_jobs = array_merge($all_jobs, $flow_jobs);
        }
        
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
     * Get all jobs for a flow.
     */
    public function get_jobs_for_flow(int $flow_id): array {
        
        if ($flow_id <= 0) {
            return [];
        }
        
        $cache_key = Cache::FLOW_JOBS_CACHE_KEY . $flow_id;
        $cached_result = get_transient( $cache_key );

        if ( false === $cached_result ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE flow_id = %d ORDER BY created_at DESC", $this->table_name, $flow_id ), ARRAY_A );
            do_action('dm_cache_set', $cache_key, $results, 300, 'jobs'); // 5 min cache for flow jobs
            $cached_result = $results;
        } else {
            $results = $cached_result;
        }
        
        return $results ?: [];
    }
    
    /**
     * Delete jobs by status criteria or all jobs.
     */
    public function delete_jobs(array $criteria = []): int|false {
        
        if (empty($criteria)) {
            do_action('dm_log', 'warning', 'No criteria provided for jobs deletion');
            return false;
        }
        
        if (!empty($criteria['failed'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i WHERE status = %s", $this->table_name, 'failed' ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i", $this->table_name ) );
        }
        
        do_action('dm_log', 'debug', 'Deleted jobs', [
            'criteria' => $criteria,
            'jobs_deleted' => $result !== false ? $result : 0,
            'success' => $result !== false
        ]);

        // Invalidate job caches
        if ($result !== false && $result > 0) {
            do_action('dm_clear_jobs_cache');
        }

        return $result;
    }

    /**
     * Store engine data for centralized access via dm_engine_data filter.
     */
    public function store_engine_data(int $job_id, array $data): bool {
        if ($job_id <= 0) {
            do_action('dm_log', 'error', 'Invalid job ID for engine_data storage', ['job_id' => $job_id]);
            return false;
        }

        // Serialize data for database storage
        $encoded = maybe_serialize($data);
        $result = $this->wpdb->update(
            $this->table_name,
            ['engine_data' => $encoded],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );

        if (false === $result) {
            do_action('dm_log', 'error', 'Failed to store engine_data', [
                'job_id' => $job_id,
                'db_error' => $this->wpdb->last_error
            ]);
            return false;
        }

        // Invalidate job cache after engine_data update
        $cache_key = Cache::JOB_CACHE_KEY . $job_id;
        delete_transient($cache_key);

        do_action('dm_log', 'debug', 'Stored engine_data successfully', [
            'job_id' => $job_id,
            'data_keys' => array_keys($data)
        ]);

        return true;
    }

    /**
     * Retrieve stored engine data for dm_engine_data filter access.
     */
    public function retrieve_engine_data(int $job_id): array {
        if ($job_id <= 0) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT engine_data FROM %i WHERE job_id = %d", $this->table_name, $job_id ) );

        if (null === $result) {
            return [];
        }

        // Try JSON decode first
        $json = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        // Fallback to WordPress unserialize
        $engine_data = maybe_unserialize($result);

        // Ensure array return type
        if (!is_array($engine_data)) {
            return [];
        }

        return $engine_data;
    }
}