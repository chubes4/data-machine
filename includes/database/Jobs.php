<?php
/**
 * Manages database operations for processing jobs.
 *
 * Handles creating and potentially updating/fetching job records.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.13.0 // Or appropriate version
 */

namespace DataMachine\Database;

use DataMachine\Helpers\Logger;
use DataMachine\Constants;
use DataMachine\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Jobs {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;


    /**
     * Initialize the class.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
    }

    /**
     * Create a new job record in the database.
     *
     * @param array $job_data The job data array containing module_id, user_id, module_config, and input_data.
     * @return int|false The job ID on success, false on failure.
     */
    public function create_job(array $job_data): int|false {
        // Extract data from standardized array format
        $module_id = $job_data['module_id'] ?? 0;
        $user_id = $job_data['user_id'] ?? 0;
        $module_config_json = $job_data['module_config'] ?? '{}';
        $input_data_json = $job_data['input_data'] ?? null;
        global $wpdb;

        // Basic validation
        if ( empty( $module_id ) || empty( $user_id ) || ! is_string( $module_config_json ) ) {
            return false;
        }

        // Get project_id from module_id to set up pipeline sequence
        $project_id = $this->get_project_id_from_module( $module_id );
        $step_sequence = $this->build_step_sequence_for_project( $project_id );

        $data = array(
            'module_id' => absint( $module_id ),
            'user_id' => absint( $user_id ),
            'status' => 'pending',
            'module_config' => $module_config_json,
            'input_data' => is_string( $input_data_json ) ? $input_data_json : null, // Ensure input data is string or null
            'step_sequence' => wp_json_encode( $step_sequence ), // Add step sequence
            'current_step_name' => $step_sequence[0] ?? 'input', // Set first step as current
            'created_at' => current_time( 'mysql', 1 ), // GMT time
        );

        $format = array(
            '%d', // module_id
            '%d', // user_id
            '%s', // status
            '%s', // module_config
            '%s', // input_data
            '%s', // step_sequence
            '%s', // current_step_name
            '%s', // created_at
        );

        $inserted = $wpdb->insert( $this->table_name, $data, $format );

        if ( false === $inserted ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Check if there are any active (pending or running) jobs for a specific module.
     *
     * @param int $module_id The ID of the module to check.
     * @param int $exclude_job_id Optional job ID to exclude from the check.
     * @return bool True if there are active jobs, false otherwise.
     */
    public function has_active_jobs_for_module( $module_id, $exclude_job_id = null ) {
        global $wpdb;

        if ( $exclude_job_id ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE module_id = %d AND job_id != %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $module_id ),
                absint( $exclude_job_id )
            ) );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE module_id = %d AND status IN ('pending', 'running', 'processing_output')",
                absint( $module_id )
            ) );
        }

        return $count > 0;
    }

    /**
     * Create the jobs database table on plugin activation.
     *
     * @since 0.13.0 // Match the class version
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        // We need dbDelta()
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE $table_name (
            job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending', /* pending, running, completed, failed, completed_with_errors */
            current_step_name varchar(50) NULL DEFAULT NULL, /* Dynamic step name (e.g., 'input', 'process', 'custom_analyze') */
            step_sequence longtext NULL, /* JSON array of step names in execution order */
            module_config longtext NULL, /* JSON config used for this specific job run */
            step_data longtext NULL, /* JSON object with dynamic step data */
            input_data longtext NULL, /* DEPRECATED: Legacy field for migration compatibility */
            processed_data longtext NULL, /* DEPRECATED: Legacy field for migration compatibility */
            fact_checked_data longtext NULL, /* DEPRECATED: Legacy field for migration compatibility */
            finalized_data longtext NULL, /* DEPRECATED: Legacy field for migration compatibility */
            result_data longtext NULL, /* DEPRECATED: Legacy field for migration compatibility */
            cleanup_scheduled datetime NULL DEFAULT NULL, /* When data cleanup should occur */
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime NULL DEFAULT NULL,
            completed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (job_id),
            KEY status (status),
            KEY current_step_name (current_step_name),
            KEY module_id (module_id),
            KEY user_id (user_id),
            KEY cleanup_scheduled (cleanup_scheduled)
        ) $charset_collate;";

        dbDelta( $sql );

        // Note: We might not need to add default jobs here unlike default modules/projects.
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
        $job = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $this->table_name is safe
        return $job;
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
     * Update the status, result_data, and completed_at time for a job.
     * Also updates the last_run_at field in the related project if possible.
     *
     * @param int    $job_id       The job ID.
     * @param string $status       The final status ('complete' or 'failed').
     * @param string|null $result_data JSON string of the result or error details.
     * @return bool True on success, false on failure.
     */
    public function complete_job( int $job_id, string $status, ?string $result_data ): bool {
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
        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'result_data' => $result_data,
                'completed_at' => current_time( 'mysql', 1 ), // GMT time
            ],
            ['job_id' => $job_id],
            ['%s', '%s', '%s'], // Format for data
            ['%d']  // Format for WHERE
        );

        if ( false === $updated ) {
            return false;
        }

        // Update project last_run_at using existing services if available
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        if ($db_projects && !empty($job->module_id)) {
            // Get project_id from modules table using single query
            $project_id = $wpdb->get_var($wpdb->prepare(
                "SELECT project_id FROM {$wpdb->prefix}dm_modules WHERE module_id = %d", 
                $job->module_id
            ));
            
            if ($project_id) {
                // Use project service if available, otherwise direct update
                $project_updated = $wpdb->update(
                    "{$wpdb->prefix}dm_projects",
                    ['last_run_at' => current_time('mysql', 1)],
                    ['project_id' => $project_id],
                    ['%s'],
                    ['%d']
                );
                
                // Log project update failure if logger available
                if (false === $project_updated) {
                    $logger = apply_filters('dm_get_service', null, 'logger');
                    if ($logger) {
                        $logger->warning('Failed to update project last_run_at', [
                            'job_id' => $job_id,
                            'project_id' => $project_id
                        ]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Updates the input_data for a specific job.
     * Used after fetching data for config-based jobs.
     *
     * @param int    $job_id          The job ID.
     * @param string $input_data_json JSON string of the processed input data.
     * @return bool True on success, false on failure.
     */
    public function update_job_input_data( int $job_id, string $input_data_json ): bool {
        global $wpdb;
        if ( empty( $job_id ) ) {
            return false;
        }
        $updated = $wpdb->update(
            $this->table_name,
            [
                'input_data' => $input_data_json,
            ],
            ['job_id' => $job_id],
            ['%s'], // Format for data
            ['%d']  // Format for WHERE
        );
        if ( false === $updated ) {
        }
        return $updated !== false;
    }

    /**
     * Clean up stuck jobs that have been running/pending for too long.
     * 
     * @param int $timeout_hours Hours after which jobs are considered stuck (uses constant default)
     * @return int Number of jobs cleaned up
     */
    public function cleanup_stuck_jobs( $timeout_hours = null ) {
        if ( $timeout_hours === null ) {
            $timeout_hours = Constants::JOB_STUCK_TIMEOUT_HOURS;
        }
        global $wpdb;
        
        $timeout_minutes = $timeout_hours * 60;
        
        // Find stuck jobs
        $stuck_jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT job_id, module_id, status, created_at 
             FROM {$this->table_name} 
             WHERE status IN ('pending', 'running', 'processing_output') 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $timeout_minutes
        ) );
        
        if ( empty( $stuck_jobs ) ) {
            return 0;
        }
        
        // Log each stuck job being cleaned up
        $logger = apply_filters('dm_get_service', null, 'logger');
        if ( $logger ) {
            foreach ( $stuck_jobs as $job ) {
                $hours_stuck = round( ( time() - strtotime( $job->created_at ) ) / 3600, 1 );
                $logger->warning( "Cleaning up stuck job - marking as failed", [
                    'job_id' => $job->job_id,
                    'module_id' => $job->module_id,
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
            $days_to_keep = Constants::JOB_CLEANUP_OLD_DAYS;
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
     * Update job step data and advance to next step.
     *
     * @param int    $job_id The job ID.
     * @param int    $step   The step number (1-5).
     * @param string $data   JSON data for this step.
     * @return bool True on success, false on failure.
     */
    public function update_step_data( int $job_id, int $step, string $data ): bool {
        global $wpdb;
        
        if ( empty( $job_id ) || $step < 1 || $step > 5 ) {
            return false;
        }
        
        $field_map = [
            1 => 'input_data',
            2 => 'processed_data', 
            3 => 'fact_checked_data',
            4 => 'finalized_data',
            5 => 'result_data'
        ];
        
        $field = $field_map[$step];
        
        $updated = $wpdb->update(
            $this->table_name,
            [
                $field => $data
            ],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        if ( $updated === false ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ($logger) {
                $logger->error( 'Database update failed', [
                    'job_id' => $job_id,
                    'step' => $step,
                    'field' => $field,
                    'data_length' => strlen($data),
                    'wpdb_error' => $wpdb->last_error
                ]);
            }
        }
        
        return $updated !== false;
    }
    
    /**
     * Get job data for a specific step.
     *
     * @param int $job_id The job ID.
     * @param int $step   The step number (1-5).
     * @return string|null Step data as JSON string or null if not found.
     */
    public function get_step_data( int $job_id, int $step ): ?string {
        global $wpdb;
        
        if ( empty( $job_id ) || $step < 1 || $step > 5 ) {
            return null;
        }
        
        $field_map = [
            1 => 'input_data',
            2 => 'processed_data',
            3 => 'fact_checked_data', 
            4 => 'finalized_data',
            5 => 'result_data'
        ];
        
        $field = $field_map[$step];
        
        $data = $wpdb->get_var( $wpdb->prepare(
            "SELECT {$field} FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        ) );
        
        return $data;
    }
    
    /**
     * Update step data for a specific step by name (NEW DYNAMIC METHOD).
     *
     * @param int    $job_id    The job ID.
     * @param string $step_name The step name (e.g., 'input', 'process', 'custom_analyze').
     * @param string $data      JSON data for this step.
     * @return bool True on success, false on failure.
     */
    public function update_step_data_by_name( int $job_id, string $step_name, string $data ): bool {
        global $wpdb;
        
        if ( empty( $job_id ) || empty( $step_name ) ) {
            return false;
        }
        
        // Get current step data
        $current_step_data = $this->get_job_step_data( $job_id );
        
        // Update the specific step
        $current_step_data[$step_name] = json_decode( $data, true );
        
        // Save back to database
        $updated_json = wp_json_encode( $current_step_data );
        
        $updated = $wpdb->update(
            $this->table_name,
            [
                'step_data' => $updated_json
            ],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        if ( $updated === false ) {
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->error( 'Database step data update failed', [
                    'job_id' => $job_id,
                    'step_name' => $step_name,
                    'data_length' => strlen($data),
                    'wpdb_error' => $wpdb->last_error
                ]);
            }
        }
        
        return $updated !== false;
    }
    
    /**
     * Get step data for a specific step by name (NEW DYNAMIC METHOD).
     *
     * @param int    $job_id    The job ID.
     * @param string $step_name The step name (e.g., 'input', 'process', 'custom_analyze').
     * @return string|null Step data as JSON string or null if not found.
     */
    public function get_step_data_by_name( int $job_id, string $step_name ): ?string {
        global $wpdb;
        
        if ( empty( $job_id ) || empty( $step_name ) ) {
            return null;
        }
        
        // Get all step data
        $all_step_data = $this->get_job_step_data( $job_id );
        
        if ( isset( $all_step_data[$step_name] ) ) {
            return wp_json_encode( $all_step_data[$step_name] );
        }
        
        return null;
    }
    
    /**
     * Get all step data for a job as an associative array.
     *
     * @param int $job_id The job ID.
     * @return array Array of step data keyed by step name.
     */
    public function get_job_step_data( int $job_id ): array {
        global $wpdb;
        
        if ( empty( $job_id ) ) {
            return [];
        }
        
        $step_data_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT step_data FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        ) );
        
        if ( empty( $step_data_json ) ) {
            return [];
        }
        
        $step_data = json_decode( $step_data_json, true );
        return is_array( $step_data ) ? $step_data : [];
    }
    
    /**
     * Get step sequence for a job.
     *
     * @param int $job_id The job ID.
     * @return array Array of step names in execution order.
     */
    public function get_job_step_sequence( int $job_id ): array {
        global $wpdb;
        
        if ( empty( $job_id ) ) {
            return [];
        }
        
        $step_sequence_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT step_sequence FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        ) );
        
        if ( empty( $step_sequence_json ) ) {
            return [];
        }
        
        $step_sequence = json_decode( $step_sequence_json, true );
        return is_array( $step_sequence ) ? $step_sequence : [];
    }
    
    /**
     * Set step sequence for a job.
     *
     * @param int   $job_id       The job ID.
     * @param array $step_sequence Array of step names in execution order.
     * @return bool True on success, false on failure.
     */
    public function set_job_step_sequence( int $job_id, array $step_sequence ): bool {
        global $wpdb;
        
        if ( empty( $job_id ) || empty( $step_sequence ) ) {
            return false;
        }
        
        $step_sequence_json = wp_json_encode( $step_sequence );
        
        $updated = $wpdb->update(
            $this->table_name,
            [
                'step_sequence' => $step_sequence_json
            ],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }
    
    /**
     * Get current step name for a job.
     *
     * @param int $job_id The job ID.
     * @return string|null Current step name or null if not found.
     */
    public function get_current_step_name( int $job_id ): ?string {
        global $wpdb;
        
        if ( empty( $job_id ) ) {
            return null;
        }
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT current_step_name FROM {$this->table_name} WHERE job_id = %d",
            $job_id
        ) );
    }
    
    /**
     * Update current step name for a job.
     *
     * @param int    $job_id    The job ID.
     * @param string $step_name The current step name.
     * @return bool True on success, false on failure.
     */
    public function update_current_step_name( int $job_id, string $step_name ): bool {
        global $wpdb;
        
        if ( empty( $job_id ) || empty( $step_name ) ) {
            return false;
        }
        
        $updated = $wpdb->update(
            $this->table_name,
            [
                'current_step_name' => $step_name
            ],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }
    
    /**
     * Advance job to next step in sequence.
     *
     * @param int $job_id The job ID.
     * @return bool True on success, false on failure.
     */
    public function advance_job_to_next_step( int $job_id ): bool {
        $current_step = $this->get_current_step_name( $job_id );
        $step_sequence = $this->get_job_step_sequence( $job_id );
        
        if ( empty( $current_step ) || empty( $step_sequence ) ) {
            return false;
        }
        
        $current_index = array_search( $current_step, $step_sequence );
        if ( $current_index === false ) {
            return false;
        }
        
        $next_index = $current_index + 1;
        if ( $next_index >= count( $step_sequence ) ) {
            // No more steps - job should be marked complete
            return false;
        }
        
        $next_step = $step_sequence[$next_index];
        return $this->update_current_step_name( $job_id, $next_step );
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
        $cleanup_time = date( 'Y-m-d H:i:s', strtotime( $cleanup_time . " +{$cleanup_delay_hours} hours" ) );
        
        $updated = $wpdb->update(
            $this->table_name,
            ['cleanup_scheduled' => $cleanup_time],
            ['job_id' => $job_id],
            ['%s'],
            ['%d']
        );
        
        return $updated !== false;
    }

    /**
     * Update job status.
     *
     * @param int $job_id The job ID.
     * @param string $status The new status.
     * @param string|null $error_details Optional error details.
     * @return bool True on success, false on failure.
     */
    public function update_job_status(int $job_id, string $status, ?string $error_details = null): bool {
        global $wpdb;
        
        if (empty($job_id)) {
            return false;
        }
        
        $update_data = ['status' => $status];
        $format = ['%s'];
        
        if ($error_details !== null) {
            $update_data['error_details'] = $error_details;
            $format[] = '%s';
        }
        
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
     * Get project_id from module_id.
     *
     * @param int $module_id The module ID.
     * @return int|null The project ID or null if not found.
     */
    private function get_project_id_from_module( int $module_id ): ?int {
        global $wpdb;
        
        if ( empty( $module_id ) ) {
            return null;
        }
        
        $project_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT project_id FROM {$wpdb->prefix}dm_modules WHERE module_id = %d",
            $module_id
        ) );
        
        return $project_id ? (int) $project_id : null;
    }

    /**
     * Build step sequence for a project based on its pipeline configuration.
     *
     * @param int|null $project_id The project ID.
     * @return array Array of step names in execution order.
     */
    private function build_step_sequence_for_project( ?int $project_id ): array {
        // Default 3-step sequence
        $default_sequence = ['input', 'ai', 'output'];
        
        if ( ! $project_id ) {
            return $default_sequence;
        }
        
        // Get project pipeline configuration service
        $project_pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ( ! $project_pipeline_service ) {
            return $default_sequence;
        }
        
        try {
            $pipeline_config = $project_pipeline_service->get_project_pipeline_config( $project_id );
            
            // Validate configuration
            $validation = $project_pipeline_service->validate_pipeline_config( $pipeline_config );
            if ( ! $validation['valid'] ) {
                return $default_sequence;
            }
            
            // Build sequence from project configuration
            $sequence = [];
            foreach ( $pipeline_config as $step ) {
                if ( isset( $step['type'] ) && isset( $step['order'] ) ) {
                    $sequence[ $step['order'] ] = $step['type'];
                }
            }
            
            // Sort by order and extract step names
            ksort( $sequence );
            $sequence = array_values( $sequence );
            
            return ! empty( $sequence ) ? $sequence : $default_sequence;
            
        } catch ( \Exception $e ) {
            // Log error and fall back to default
            $logger = apply_filters('dm_get_service', null, 'logger');
            if ( $logger ) {
                $logger->warning( 'Error building step sequence for project, using default', [
                    'project_id' => $project_id,
                    'error' => $e->getMessage()
                ] );
            }
            return $default_sequence;
        }
    }

} 