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
     * - Direct execution: pipeline_id=0, flow_id=0 (chat/API workflows without saved pipeline/flow)
     * - Database flow: pipeline_id>0, flow_id>0 (saved pipelines and flows)
     *
     * @param array $job_data Job data with pipeline_id and flow_id
     * @return int|false Job ID on success, false on failure
     */
    public function create_job(array $job_data): int|false {

        $pipeline_id = absint($job_data['pipeline_id'] ?? 0);
        $flow_id = absint($job_data['flow_id'] ?? 0);

        // Validate execution mode: allow direct execution (0,0) or database flow (>0,>0)
        $is_direct_execution = ($pipeline_id === 0 && $flow_id === 0);
        $is_database_flow = ($pipeline_id > 0 && $flow_id > 0);

        if (!$is_direct_execution && !$is_database_flow) {
            do_action('datamachine_log', 'error', 'Invalid job data: mixed or invalid IDs', [
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
            do_action('datamachine_log', 'error', 'Failed to insert pipeline+flow-based job', [
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

    public function get_jobs_count(): int {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(job_id) FROM %i", $this->table_name ) );

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
        
        $allowed_orderby = ['j.job_id', 'j.pipeline_id', 'j.flow_id', 'j.status', 'j.completed_at', 'p.pipeline_name', 'f.flow_name'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'j.job_id';
        }
        
        $pipelines_table = $this->wpdb->prefix . 'datamachine_pipelines';
        $flows_table = $this->wpdb->prefix . 'datamachine_flows';
        
        $sql = "SELECT j.*, p.pipeline_name, f.flow_name FROM {$this->table_name} j LEFT JOIN {$pipelines_table} p ON j.pipeline_id = p.pipeline_id LEFT JOIN {$flows_table} f ON j.flow_id = f.flow_id ORDER BY $orderby $order";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results( $this->wpdb->prepare( $sql . " LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

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
     */
    public function get_jobs_for_flow(int $flow_id): array {
        
        if ($flow_id <= 0) {
            return [];
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM %i WHERE flow_id = %d ORDER BY created_at DESC", $this->table_name, $flow_id ), ARRAY_A );
        
        return $results ?: [];
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
}