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
     * @param array $job_data Job data with pipeline_id, flow_id, user_id, flow_config.
     * @return int|false The job ID on success, false on failure.
     */
    public function create_job(array $job_data): int|false {
        global $wpdb;
        $logger = apply_filters('dm_get_logger', null);
        
        $pipeline_id = absint($job_data['pipeline_id'] ?? 0);
        $flow_id = absint($job_data['flow_id'] ?? 0);
        $user_id = absint($job_data['user_id'] ?? 0);
        $flow_config_json = $job_data['flow_config'] ?? '{}';
        
        // Validate required fields
        if (empty($pipeline_id) || empty($flow_id) || !is_string($flow_config_json)) {
            $logger && $logger->error('Invalid pipeline+flow-based job data', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'user_id' => $user_id,
                'flow_config_type' => gettype($flow_config_json)
            ]);
            return false;
        }
        
        // Build step sequence from pipeline configuration
        $step_sequence = $this->build_step_sequence_for_pipeline($pipeline_id);
        if (empty($step_sequence)) {
            $logger && $logger->warning('Cannot create pipeline+flow-based job - no pipeline configuration found', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'user_id' => $user_id
            ]);
            return false;
        }
        
        $data = [
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id,
            'user_id' => $user_id,
            'status' => 'pending',
            'flow_config' => $flow_config_json,
            'step_sequence' => wp_json_encode($step_sequence),
            'current_step_name' => $step_sequence[0],
            'created_at' => current_time('mysql', 1)
        ];
        
        $format = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'];
        
        $inserted = $wpdb->insert($this->table_name, $data, $format);
        
        if (false === $inserted) {
            $logger && $logger->error('Failed to insert pipeline+flow-based job', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'user_id' => $user_id,
                'db_error' => $wpdb->last_error
            ]);
            return false;
        }
        
        $job_id = $wpdb->insert_id;
        $logger && $logger->info('Successfully created pipeline+flow-based job', [
            'job_id' => $job_id,
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id,
            'user_id' => $user_id
        ]);
        
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
     * @return array Array of job records with module names
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
     * Build step sequence for a pipeline based on its step configuration.
     *
     * @param int|null $pipeline_id The pipeline ID.
     * @return array Array of step names in execution order.
     */
    private function build_step_sequence_for_pipeline( ?int $pipeline_id ): array {
        if ( ! $pipeline_id ) {
            return [];
        }
        
        // Get direct database access to pipelines
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if ( ! $db_pipelines ) {
            return [];
        }
        
        try {
            $step_configuration = $db_pipelines->get_pipeline_step_configuration( $pipeline_id );
            
            if ( empty( $step_configuration ) ) {
                return [];
            }
            
            // Build sequence from pipeline step configuration
            $sequence = [];
            foreach ( $step_configuration as $step ) {
                if ( isset( $step['type'] ) && isset( $step['position'] ) ) {
                    $sequence[ $step['position'] ] = $step['type'];
                }
            }
            
            // Sort by position and extract step names
            ksort( $sequence );
            $sequence = array_values( $sequence );
            
            return $sequence;
            
        } catch ( \Exception $e ) {
            // Log error and return empty array
            $logger = apply_filters('dm_get_logger', null);
            if ( $logger ) {
                $logger->warning( 'Error building step sequence for pipeline', [
                    'pipeline_id' => $pipeline_id,
                    'error' => $e->getMessage()
                ] );
            }
            return [];
        }
    }
}