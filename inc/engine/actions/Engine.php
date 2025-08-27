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
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (!$db_flows || !$db_jobs) {
            do_action('dm_log', 'error', 'Flow execution failed - database services unavailable', [
                'flow_id' => $flow_id,
                'flows_db' => $db_flows ? 'available' : 'missing',
                'jobs_db' => $db_jobs ? 'available' : 'missing'
            ]);
            return false;
        }
        
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow execution failed - flow not found', ['flow_id' => $flow_id]);
            return false;
        }
        
        // Create job directly to get job_id for execution chain
        $job_id = $db_jobs->create_job([
            'pipeline_id' => (int)$flow['pipeline_id'],
            'flow_id' => $flow_id
        ]);
        
        if (!$job_id) {
            do_action('dm_log', 'error', 'Flow execution failed - job creation failed', [
                'flow_id' => $flow_id,
                'pipeline_id' => $flow['pipeline_id']
            ]);
            return false;
        }
        
        // Find first step (execution_order = 0) for scheduling
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
        $first_flow_step_id = null;
        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === 0) {
                $first_flow_step_id = $flow_step_id;
                break;
            }
        }
        
        if (!$first_flow_step_id) {
            do_action('dm_log', 'error', 'Flow execution failed - no first step found', [
                'flow_id' => $flow_id,
                'job_id' => $job_id
            ]);
            return false;
        }
        
        // Schedule execution starting with first step
        do_action('dm_schedule_next_step', $job_id, $first_flow_step_id, []);
        
        do_action('dm_log', 'info', 'Flow execution started successfully', [
            'flow_id' => $flow_id,
            'job_id' => $job_id,
            'first_step' => $first_flow_step_id
        ]);
        
        return true;
    });


    // 2. CORE STEP EXECUTION - Pure functional pipeline orchestration (the heart)
    add_action( 'dm_execute_step', function( $job_id, $flow_step_id, $data = null ) {
        
        try {
            // Retrieve data from storage if it's a reference, otherwise use data directly
            $repositories = apply_filters('dm_files_repository', []);
            $repository = $repositories['files'] ?? null;
            
            if ($repository && $repository->is_data_reference($data)) {
                $data = $repository->retrieve_data_packet($data);
                if ($data === null) {
                    do_action('dm_log', 'error', 'Failed to retrieve data from storage', [
                        'job_id' => $job_id,
                        'flow_step_id' => $flow_step_id
                    ]);
                    do_action('dm_fail_job', $job_id, 'data_retrieval_failure', [
                        'flow_step_id' => $flow_step_id
                    ]);
                    return false;
                }
            }
            // Load complete flow step configuration using flow_step_id
            $flow_step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
            if (!$flow_step_config) {
                do_action('dm_log', 'error', 'Failed to load flow step configuration', [
                    'job_id' => $job_id,
                    'flow_step_id' => $flow_step_id
                ]);
                do_action('dm_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'reason' => 'failed_to_load_flow_step_configuration'
                ]);
                return false;
            }

            // Get step class via direct discovery
            $step_type = $flow_step_config['step_type'] ?? '';
            $all_steps = apply_filters('dm_steps', []);
            $step_definition = $all_steps[$step_type] ?? null;
            
            if ( ! $step_definition ) {
                do_action('dm_log', 'error', 'Step type not found in registry', [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type
                ]);
                do_action('dm_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type,
                    'reason' => 'step_type_not_found_in_registry'
                ]);
                return false;
            }
            
            $step_class = $step_definition['class'] ?? '';

            // Create step instance (parameter-less constructor)
            $flow_step = new $step_class();
            
            // Execute step with explicit job_id parameter
            $data = $flow_step->execute( $job_id, $flow_step_id, $data ?: [], $flow_step_config );
            
            // Success = non-empty data packet array, failure = empty array
            $step_success = ! empty( $data );
            
            // Handle pipeline flow based on step success
            if ( $step_success ) {
                // Find next step using execution_order
                $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
                
                if ( $next_flow_step_id ) {
                    // Schedule next step with updated data packet
                    do_action('dm_schedule_next_step', $job_id, $next_flow_step_id, $data);
                } else {
                    // Pipeline completed - mark job as completed and clean up data
                    do_action('dm_update_job_status', $job_id, 'completed', 'complete');
                    if ($repository) {
                        $repository->cleanup_job_data_packets($job_id);
                    }
                    do_action('dm_log', 'info', 'Pipeline execution completed successfully', [
                        'job_id' => $job_id,
                        'flow_step_id' => $flow_step_id,
                        'final_data_count' => count($data)
                    ]);
                }
            } else {
                do_action('dm_log', 'error', 'Step execution failed - empty data packet', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class
                ]);
                do_action('dm_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class,
                    'reason' => 'empty_data_packet_returned'
                ]);
            }


            return $step_success;

        } catch ( \Throwable $e ) {
            do_action('dm_log', 'error', 'Error in pipeline step execution', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            do_action('dm_fail_job', $job_id, 'step_execution_failure', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
                'reason' => 'throwable_exception_in_step_execution'
            ]);
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
        
        // Always store data in files repository and use reference
        $repositories = apply_filters('dm_files_repository', []);
        $repository = $repositories['files'] ?? null;
        
        if ($repository) {
            $data_reference = $repository->store_data_packet($data, $job_id, $flow_step_id);
        } else {
            // Fallback to direct data passing if repository unavailable
            $data_reference = ['data' => $data];
        }
        
        // Schedule Action Scheduler action with lightweight reference
        $action_id = as_schedule_single_action(
            time(), // Immediate execution
            'dm_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => $data_reference
            ],
            'data-machine'
        );
        
        // Only log for step transitions, not initial job scheduling (covered by job creation log)
        if (!empty($data)) {
            do_action('dm_log', 'debug', 'Next step scheduled via centralized action hook', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'action_id' => $action_id,
                'success' => ($action_id !== false),
                'data_stored' => isset($data_reference['is_data_reference'])
            ]);
        }
        
        return $action_id !== false;
    }, 10, 3);

}