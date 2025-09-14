<?php
/**
 * Pipeline Execution Engine - Three-action execution cycle for Data Machine workflows.
 *
 * Core execution cycle:
 * 1. dm_run_flow_now - Initialize flow execution with job creation
 * 2. dm_execute_step - Execute individual steps with parameter building
 * 3. dm_schedule_next_step - Schedule next step via Action Scheduler
 *
 * Features:
 * - Job-based flow execution tracking
 * - Data packet storage and retrieval
 * - Global execution context for AI directives
 * - Comprehensive error handling and logging
 * - Action Scheduler integration for async execution
 *
 * @package DataMachine\Engine\Actions
 * @since 1.0.0
 */

defined('ABSPATH') || exit;


/**
 * Register Data Machine execution engine actions.
 *
 * Registers three core actions for pipeline execution:
 * - dm_run_flow_now: Flow initialization with job creation
 * - dm_execute_step: Individual step execution with error handling
 * - dm_schedule_next_step: Next step scheduling via Action Scheduler
 */
function dm_register_execution_engine() {

    // Flow initialization action
    add_action('dm_run_flow_now', function($flow_id) {
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
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);

        // Find first step in execution order (execution_order = 0)
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
        
        do_action('dm_schedule_next_step', $job_id, $first_flow_step_id, []);
        
        do_action('dm_log', 'info', 'Flow execution started successfully', [
            'flow_id' => $flow_id,
            'job_id' => $job_id,
            'first_step' => $first_flow_step_id
        ]);
        
        return true;
    });


    // Step execution action with comprehensive error handling
    add_action( 'dm_execute_step', function( $job_id, $flow_step_id, $data = null ) {

        try {
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
            $flow_step = new $step_class();
            
            $parameters = apply_filters('dm_engine_parameters', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'flow_step_config' => $flow_step_config,
                'data' => $data ?: []
            ], $data ?: [], $flow_step_config, $step_type, $flow_step_id);
            
            // Set global execution context for AI directives
            add_filter('dm_current_flow_step_id', function() use ($flow_step_id) { return $flow_step_id; }, 100);
            add_filter('dm_current_job_id', function() use ($job_id) { return $job_id; }, 100);

            $data = $flow_step->execute($parameters);

            // Clear global execution context
            remove_all_filters('dm_current_flow_step_id', 100);
            remove_all_filters('dm_current_job_id', 100);
            
            $step_success = ! empty( $data );
            if ( $step_success ) {
                $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
                
                if ( $next_flow_step_id ) {
                    do_action('dm_schedule_next_step', $job_id, $next_flow_step_id, $data);
                } else {
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

    // Next step scheduling action with Action Scheduler integration
    add_action('dm_schedule_next_step', function($job_id, $flow_step_id, $data = []) {
        if (!function_exists('as_schedule_single_action')) {
            do_action('dm_log', 'error', 'Action Scheduler not available for step scheduling', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        
        $repositories = apply_filters('dm_files_repository', []);
        $repository = $repositories['files'] ?? null;
        
        // Store data packet or use direct reference
        if ($repository) {
            $data_reference = $repository->store_data_packet($data, $job_id, $flow_step_id);
        } else {
            $data_reference = ['data' => $data];
        }
        $action_id = as_schedule_single_action(
            time(),
            'dm_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => $data_reference
            ],
            'data-machine'
        );
        if (!empty($data)) {
            do_action('dm_log', 'debug', 'Next step scheduled via Action Scheduler', [
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