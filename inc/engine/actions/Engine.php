<?php
/**
 * Data Machine Core Execution Engine
 *
 * Three-action execution engine providing complete pipeline orchestration through
 * Action Scheduler integration, filter-based service discovery, and functional
 * step execution. KISS compliance with zero overhead architecture.
 * 
 * EXECUTION FLOW:
 * 1. dm_run_flow_now: Pipeline initiation via organized dm_create action
 * 2. dm_execute_step: Core functional step execution with DataPacket flow
 * 3. dm_schedule_next_step: Action Scheduler step transitions
 * 
 * EXECUTION CYCLE:
 * dm_run_flow_now → [creates job] → dm_schedule_next_step → dm_execute_step → dm_schedule_next_step → dm_execute_step...
 *
 * @package DataMachine
 * @since NEXT_VERSION
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register core pipeline execution engine actions.
 * 
 * Registers the 3 fundamental actions that drive all pipeline execution:
 * - dm_run_flow_now: Entry point for pipeline execution
 * - dm_execute_step: Pure functional step orchestration  
 * - dm_schedule_next_step: Action Scheduler step transitions
 * 
 * @since NEXT_VERSION
 */
function dm_register_execution_engine() {

    // 1. PIPELINE INITIATION - Entry point for all pipeline execution
    add_action('dm_run_flow_now', function($flow_id) {
        // Get flow data to determine pipeline_id
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow execution failed - database service unavailable', ['flow_id' => $flow_id]);
            return false;
        }
        
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow execution failed - flow not found', ['flow_id' => $flow_id]);
            return false;
        }
        
        // Use organized dm_create action for consistent job creation
        do_action('dm_create', 'job', [
            'pipeline_id' => (int)$flow['pipeline_id'],
            'flow_id' => $flow_id
        ]);
        
        return true;
    });


    // 2. CORE STEP EXECUTION - Pure functional pipeline orchestration (the heart)
    add_action( 'dm_execute_step', function( $job_id, $flow_step_id, $data = null ) {
        try {
            // Load complete step configuration using flow_step_id
            $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
            if (!$step_config) {
                do_action('dm_log', 'error', 'Failed to load step configuration', [
                    'job_id' => $job_id,
                    'flow_step_id' => $flow_step_id
                ]);
                do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
                return false;
            }
            
            // Add job_id directly to step_config - no more extraction needed!
            $step_config['job_id'] = $job_id;

            // Get step class via direct discovery
            $step_type = $step_config['step_type'] ?? '';
            $all_steps = apply_filters('dm_steps', []);
            $step_definition = $all_steps[$step_type] ?? null;
            
            if ( ! $step_definition ) {
                do_action('dm_log', 'error', 'Step type not found in registry', [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type
                ]);
                do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
                return false;
            }
            
            $step_class = $step_definition['class'] ?? '';

            // Create step instance (parameter-less constructor)
            $flow_step = new $step_class();
            
            // Execute step with complete configuration
            $data = $flow_step->execute( $flow_step_id, $data ?: [], $step_config );
            
            // Success = non-empty data packet array, failure = empty array
            $step_success = ! empty( $data );
            
            // Handle pipeline flow based on step success
            if ( $step_success ) {
                // Find next step using execution_order
                $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
                
                if ( $next_flow_step_id ) {
                    // Schedule next step with updated data packet
                    do_action('dm_schedule_next_step', $job_id, $next_flow_step_id, $data);
                }
            } else {
                do_action('dm_log', 'error', 'Step execution failed - empty data packet', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class
                ]);
                do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
            }

            return $step_success;

        } catch ( \Exception $e ) {
            do_action('dm_log', 'error', 'Exception in pipeline step execution', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
            return false;
        } catch ( \Throwable $e ) {
            do_action('dm_log', 'error', 'Fatal error in pipeline step execution', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
            return false;
        }
    }, 10, 3 );

    
    // 3. STEP SCHEDULING - Action Scheduler pipeline step transitions
    add_action('dm_schedule_next_step', function($job_id, $flow_step_id, $data = []) {
        if (!function_exists('as_schedule_single_action')) {
            do_action('dm_log', 'error', 'Action Scheduler not available for step scheduling', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        
        // Direct Action Scheduler call with job_id and flow_step_id
        $action_id = as_schedule_single_action(
            time(), // Immediate execution
            'dm_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => $data
            ],
            'data-machine'
        );
        
        // Only log for step transitions, not initial job scheduling (covered by job creation log)
        if (!empty($data)) {
            do_action('dm_log', 'debug', 'Next step scheduled via centralized action hook', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'action_id' => $action_id,
                'success' => ($action_id !== false)
            ]);
        }
        
        return $action_id !== false;
    }, 10, 3);

}