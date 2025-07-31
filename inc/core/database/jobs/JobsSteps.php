<?php
/**
 * Jobs database step sequence management component.
 *
 * Handles step data, sequence management, and pipeline progression logic.
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

class JobsSteps {

    /**
     * The name of the jobs database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the steps component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
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
            $logger = apply_filters('dm_get_logger', null);
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
}