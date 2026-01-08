<?php
/**
 * Jobs Database CRUD Operations
 */

namespace DataMachine\Core\Database\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class JobsOperations {

    private $table_name;

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'datamachine_jobs';
    }

    /**
     * Create a new job record.
     *
     * Supports two execution modes:
     * - Direct execution: pipeline_id='direct', flow_id='direct' (chat/API workflows without saved pipeline/flow)
     * - Database flow: pipeline_id and flow_id are numeric strings (saved pipelines and flows)
     *
     * Validation is strict: null values are rejected. Callers must explicitly pass 'direct'
     * for ephemeral workflows or valid numeric IDs for database flows.
     *
     * @param array $job_data Job data with pipeline_id and flow_id
     * @return int|false Job ID on success, false on failure
     */
    public function create_job(array $job_data): int|false {

        $pipeline_id = $job_data['pipeline_id'] ?? null;
        $flow_id = $job_data['flow_id'] ?? null;

        // Direct execution: both must be explicitly 'direct'
        $is_direct_execution = ($pipeline_id === 'direct' && $flow_id === 'direct');
        
        // Database flow: both must be valid numeric IDs > 0
        $is_database_flow = (is_numeric($pipeline_id) && (int) $pipeline_id > 0 && is_numeric($flow_id) && (int) $flow_id > 0);

        if (!$is_direct_execution && !$is_database_flow) {
            do_action('datamachine_log', 'error', 'Invalid job data: must provide both IDs as "direct" or both as valid numeric IDs', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id
            ]);
            return false;
        }

        // Normalize to string for database storage
        $pipeline_id = $is_direct_execution ? 'direct' : (string) absint($pipeline_id);
        $flow_id = $is_direct_execution ? 'direct' : (string) absint($flow_id);
        
        $data = [
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id,
            'status' => 'pending'
        ];
        
        $format = ['%s', '%s', '%s'];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $this->wpdb->insert($this->table_name, $data, $format);
        
        if (false === $inserted) {
            do_action('datamachine_log', 'error', 'Failed to insert job', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'db_error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        $job_id = $this->wpdb->insert_id;

        return $job_id;
    }

    public function get_job( int $job_id ): ?array {
        if ( empty( $job_id ) ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $job = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM %i WHERE job_id = %d", $this->table_name, $job_id ), ARRAY_A );
        
        if ( $job && isset( $job['engine_data'] ) && is_string( $job['engine_data'] ) ) {
            $decoded = json_decode( $job['engine_data'], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $job['engine_data'] = $decoded;
            }
        }

        return $job;
    }

    /**
     * Get jobs count with optional filtering.
     *
     * @param array $args Filter arguments:
     *                    - flow_id: Filter by flow ID or 'direct' (optional)
     *                    - pipeline_id: Filter by pipeline ID or 'direct' (optional)
     *                    - status: Filter by status (optional)
     * @return int Total count
     */
    public function get_jobs_count(array $args = []): int {
        // Build WHERE clauses for filtering
        $where_clauses = [];
        $where_values = [];

        if (!empty($args['flow_id'])) {
            $where_clauses[] = 'flow_id = %s';
            $where_values[] = (string) $args['flow_id'];
        }

        if (!empty($args['pipeline_id'])) {
            $where_clauses[] = 'pipeline_id = %s';
            $where_values[] = (string) $args['pipeline_id'];
        }

        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $this->wpdb->prepare(
            "SELECT COUNT(job_id) FROM %i {$where_sql}",
            array_merge([$this->table_name], $where_values)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var($query);

        return (int) $count;
    }

    /**
     * Get paginated jobs with pipeline and flow names.
     *
     * Supports filtering by flow_id, pipeline_id, and status.
     *
     * @param array $args Query arguments:
     *                    - orderby: Column to order by (default: 'j.job_id')
     *                    - order: ASC or DESC (default: 'DESC')
     *                    - per_page: Results per page (default: 20)
     *                    - offset: Pagination offset (default: 0)
     *                    - flow_id: Filter by flow ID (optional)
     *                    - pipeline_id: Filter by pipeline ID (optional)
     *                    - status: Filter by status (optional)
     * @return array Jobs with pipeline and flow names
     */
    public function get_jobs_for_list_table(array $args): array {
        $orderby = $args['orderby'] ?? 'j.job_id';
        $order = strtoupper($args['order'] ?? 'DESC');
        $per_page = (int) ($args['per_page'] ?? 20);
        $offset = (int) ($args['offset'] ?? 0);

        $pipelines_table = $this->wpdb->prefix . 'datamachine_pipelines';
        $flows_table = $this->wpdb->prefix . 'datamachine_flows';

        // Validate order direction
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        // Validate orderby column (whitelist approach)
        $valid_orderby = [
            'j.job_id', 'j.pipeline_id', 'j.flow_id', 'j.status',
            'j.created_at', 'j.completed_at', 'p.pipeline_name', 'f.flow_name'
        ];
        if (!in_array($orderby, $valid_orderby, true)) {
            $orderby = 'j.job_id';
        }

        // Build WHERE clauses for filtering
        $where_clauses = [];
        $where_values = [];

        if (!empty($args['flow_id'])) {
            $where_clauses[] = 'j.flow_id = %s';
            $where_values[] = (string) $args['flow_id'];
        }

        if (!empty($args['pipeline_id'])) {
            $where_clauses[] = 'j.pipeline_id = %s';
            $where_values[] = (string) $args['pipeline_id'];
        }

        if (!empty($args['status'])) {
            $where_clauses[] = 'j.status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Build the full query
        // Note: orderby is validated above, so safe to interpolate
        // For direct execution jobs, LEFT JOINs will return NULL for pipeline_name/flow_name
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $this->wpdb->prepare(
            "SELECT j.*, p.pipeline_name, f.flow_name
             FROM %i j
             LEFT JOIN %i p ON j.pipeline_id = CAST(p.pipeline_id AS CHAR)
             LEFT JOIN %i f ON j.flow_id = CAST(f.flow_id AS CHAR)
             {$where_sql}
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
            array_merge(
                [$this->table_name, $pipelines_table, $flows_table],
                $where_values,
                [$per_page, $offset]
            )
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results($query, ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get all jobs for pipeline deletion impact analysis.
     */
    public function get_jobs_for_pipeline( int $pipeline_id ): array {
        if ( $pipeline_id <= 0 ) {
            return [];
        }

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
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
     *
     * @param int|string $flow_id Flow ID or 'direct'
     * @return array Jobs for the flow
     */
    public function get_jobs_for_flow(int|string $flow_id): array {
        
        if (empty($flow_id)) {
            return [];
        }

        // Skip if numeric and <= 0 (but allow 'direct' string)
        if (is_numeric($flow_id) && (int) $flow_id <= 0) {
            return [];
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE flow_id = %s ORDER BY created_at DESC", $this->table_name, (string) $flow_id ), ARRAY_A );
        
        return $results ?: [];
    }

    /**
     * Get the latest job for each flow in a batch.
     *
     * Uses a subquery to efficiently get the most recent job per flow_id.
     *
     * @param array $flow_ids Array of flow IDs to query (numeric IDs only, not 'direct')
     * @return array Map of [flow_id => job_row] for flows that have jobs
     */
    public function get_latest_jobs_by_flow_ids(array $flow_ids): array {
        if (empty($flow_ids)) {
            return [];
        }

        // Filter to numeric IDs only (this method is for database flows, not direct execution)
        $flow_ids = array_filter($flow_ids, fn($id) => is_numeric($id) && (int) $id > 0);
        $flow_ids = array_map(fn($id) => (string) $id, $flow_ids);

        if (empty($flow_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($flow_ids), '%s'));

        // Subquery to get max job_id per flow, then join to get full row
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->prepare(
            "SELECT j.* FROM %i j
             INNER JOIN (
                 SELECT flow_id, MAX(job_id) as max_job_id
                 FROM %i
                 WHERE flow_id IN ($placeholders)
                 GROUP BY flow_id
             ) latest ON j.job_id = latest.max_job_id",
            array_merge([$this->table_name, $this->table_name], $flow_ids)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results($query, ARRAY_A);

        if (!$results) {
            return [];
        }

        // Key by flow_id for easy lookup (keep as string for consistency)
        $jobs_by_flow = [];
        foreach ($results as $job) {
            $jobs_by_flow[$job['flow_id']] = $job;
        }

        return $jobs_by_flow;
    }
    
    /**
     * Delete jobs by status criteria or all jobs.
     */
    public function delete_jobs(array $criteria = []): int|false {
        
        if (empty($criteria)) {
            do_action('datamachine_log', 'warning', 'No criteria provided for jobs deletion');
            return false;
        }
        
        if (!empty($criteria['failed'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i WHERE status = %s", $this->table_name, 'failed' ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i", $this->table_name ) );
        }
        
        do_action('datamachine_log', 'debug', 'Deleted jobs', [
            'criteria' => $criteria,
            'jobs_deleted' => $result !== false ? $result : 0,
            'success' => $result !== false
        ]);

        return $result;
    }

    /**
     * Store engine data for centralized access via datamachine_engine_data filter.
     */
    public function store_engine_data(int $job_id, array $data): bool {
        if ($job_id <= 0) {
            do_action('datamachine_log', 'error', 'Invalid job ID for engine_data storage', ['job_id' => $job_id]);
            return false;
        }

        // Encode data as JSON for database storage
        $encoded = wp_json_encode($data);
        $result = $this->wpdb->update(
            $this->table_name,
            ['engine_data' => $encoded],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );

        if (false === $result) {
            do_action('datamachine_log', 'error', 'Failed to store engine_data', [
                'job_id' => $job_id,
                'db_error' => $this->wpdb->last_error
            ]);
            return false;
        }

        do_action('datamachine_log', 'debug', 'Stored engine_data successfully', [
            'job_id' => $job_id,
            'data_keys' => array_keys($data)
        ]);

        return true;
    }

    /**
     * Retrieve stored engine data for datamachine_engine_data filter access.
     */
    public function retrieve_engine_data(int $job_id): array {
        $job = $this->get_job($job_id);

        if ( $job && isset( $job['engine_data'] ) && is_array( $job['engine_data'] ) ) {
            return $job['engine_data'];
        }

        return [];
    }

    /**
     * Get consecutive status counts for a flow from job history.
     *
     * Counts consecutive failures and consecutive no_items from the most recent jobs.
     *
     * @param int|string $flow_id Flow ID to analyze (numeric or 'direct')
     * @return array ['consecutive_failures' => int, 'consecutive_no_items' => int, 'latest_job' => array|null]
     */
    public function get_consecutive_counts_for_flow(int|string $flow_id): array {
        $jobs = $this->get_jobs_for_flow($flow_id);

        $result = [
            'consecutive_failures' => 0,
            'consecutive_no_items' => 0,
            'latest_job' => $jobs[0] ?? null,
        ];

        if (empty($jobs)) {
            return $result;
        }

        // Count consecutive failures from most recent
        foreach ($jobs as $job) {
            if ($job['status'] === 'failed') {
                $result['consecutive_failures']++;
            } else {
                break;
            }
        }

        // Count consecutive no_items from most recent (reset count)
        foreach ($jobs as $job) {
            if ($job['status'] === 'completed_no_items') {
                $result['consecutive_no_items']++;
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Get flows with consecutive failures or no_items above threshold.
     *
     * This is the authoritative source for problem flow detection,
     * computing counts directly from job history.
     * Only checks database flows (numeric IDs), not direct execution jobs.
     *
     * @param int $threshold Minimum consecutive count to flag as problem
     * @return array Array of [flow_id => counts_array] for problem flows
     */
    public function get_problem_flow_ids(int $threshold = 3): array {
        // Get all numeric flow IDs that have jobs (excludes 'direct')
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $query = $this->wpdb->prepare(
            "SELECT DISTINCT flow_id FROM %i WHERE flow_id != 'direct' AND flow_id REGEXP '^[0-9]+$'",
            $this->table_name
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $flow_ids = $this->wpdb->get_col($query);

        if (empty($flow_ids)) {
            return [];
        }

        $problem_flows = [];

        foreach ($flow_ids as $flow_id) {
            $counts = $this->get_consecutive_counts_for_flow($flow_id);

            if ($counts['consecutive_failures'] >= $threshold || $counts['consecutive_no_items'] >= $threshold) {
                $problem_flows[$flow_id] = $counts;
            }
        }

        return $problem_flows;
    }
}