<?php
/**
 * Four-action execution engine.
 *
 * Execution cycle: datamachine_run_flow_now → datamachine_execute_step → datamachine_schedule_next_step
 * Scheduling cycle: datamachine_run_flow_later → Action Scheduler → datamachine_run_flow_now
 *
 * @package DataMachine\Engine\Actions
 */

defined('ABSPATH') || exit;

/**
 * Get file context array from flow ID
 *
 * @param int $flow_id Flow ID
 * @return array Context array with pipeline/flow metadata
 */
function datamachine_get_file_context(int $flow_id): array {
    $flow = apply_filters('datamachine_get_flow_config', [], $flow_id);
    $pipeline_id = $flow['pipeline_id'] ?? 0;
    $pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);

    return [
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $pipeline['pipeline_name'] ?? "pipeline-{$pipeline_id}",
        'flow_id' => $flow_id,
        'flow_name' => $flow['flow_name'] ?? "flow-{$flow_id}"
    ];
}

function datamachine_register_execution_engine() {

    add_action('datamachine_run_flow_now', function($flow_id) {
        $all_databases = apply_filters('datamachine_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (!$db_flows || !$db_jobs) {
            do_action('datamachine_log', 'error', 'Flow execution failed - database services unavailable', [
                'flow_id' => $flow_id,
                'flows_db' => $db_flows ? 'available' : 'missing',
                'jobs_db' => $db_jobs ? 'available' : 'missing'
            ]);
            return false;
        }
        
        $flow = apply_filters('datamachine_get_flow', null, $flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow execution failed - flow not found', ['flow_id' => $flow_id]);
            return false;
        }
        
        $job_id = $db_jobs->create_job([
            'pipeline_id' => (int)$flow['pipeline_id'],
            'flow_id' => $flow_id
        ]);
        
        if (!$job_id) {
            do_action('datamachine_log', 'error', 'Flow execution failed - job creation failed', [
                'flow_id' => $flow_id,
                'pipeline_id' => $flow['pipeline_id']
            ]);
            return false;
        }

        // Load flow and pipeline configs once
        $flow = apply_filters('datamachine_get_flow', null, $flow_id);
        $flow_config = $flow['flow_config'] ?? [];
        $pipeline_id = (int)$flow['pipeline_id'];

        // Load pipeline config
        $pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);
        $pipeline_config = $pipeline['pipeline_config'] ?? [];

        // Store both in engine_data for execution
        $db_jobs->store_engine_data($job_id, [
            'flow_config' => $flow_config,
            'pipeline_config' => $pipeline_config
        ]);

        $first_flow_step_id = null;
        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === 0) {
                $first_flow_step_id = $flow_step_id;
                break;
            }
        }
        
        if (!$first_flow_step_id) {
            do_action('datamachine_log', 'error', 'Flow execution failed - no first step found', [
                'flow_id' => $flow_id,
                'job_id' => $job_id
            ]);
            return false;
        }

        do_action('datamachine_schedule_next_step', $job_id, $first_flow_step_id, []);

        do_action('datamachine_log', 'info', 'Flow execution started successfully', [
            'flow_id' => $flow_id,
            'job_id' => $job_id,
            'first_step' => $first_flow_step_id
        ]);
        
        return true;
    });


    add_action( 'datamachine_execute_step', function( $job_id, $flow_step_id, $data = null ) {

        // Set execution context for complete runtime state access
        \DataMachine\Engine\ExecutionContext::$job_id = $job_id;
        \DataMachine\Engine\ExecutionContext::$flow_step_id = $flow_step_id;
        \DataMachine\Engine\ExecutionContext::$data = $data ?: [];

        try {
            $repositories = apply_filters('datamachine_files_repository', []);
            $repository = $repositories['files'] ?? null;
            
            if ($repository && $repository->is_data_reference($data)) {
                $data = $repository->retrieve_data_packet($data);
                if ($data === null) {
                    do_action('datamachine_log', 'error', 'Failed to retrieve data from storage', [
                        'job_id' => $job_id,
                        'flow_step_id' => $flow_step_id
                    ]);
                    do_action('datamachine_fail_job', $job_id, 'data_retrieval_failure', [
                        'flow_step_id' => $flow_step_id
                    ]);
                    return false;
                }
            }
            $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id, $job_id);
            if (!$flow_step_config) {
                do_action('datamachine_log', 'error', 'Failed to load flow step configuration', [
                    'job_id' => $job_id,
                    'flow_step_id' => $flow_step_id
                ]);
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'reason' => 'failed_to_load_flow_step_configuration'
                ]);
                return false;
            }

            $step_type = $flow_step_config['step_type'] ?? '';
            $all_steps = apply_filters('datamachine_step_types', []);
            $step_definition = $all_steps[$step_type] ?? null;
            
            if ( ! $step_definition ) {
                do_action('datamachine_log', 'error', 'Step type not found in registry', [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type
                ]);
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'step_type' => $step_type,
                    'reason' => 'step_type_not_found_in_registry'
                ]);
                return false;
            }
            
            $step_class = $step_definition['class'] ?? '';
            $flow_step = new $step_class();

            // Get engine data for step execution
            $engine_data = apply_filters('datamachine_engine_data', [], $job_id);

            // Execute step with explicit parameters
            $data = $flow_step->execute($job_id, $flow_step_id, $data, $flow_step_config, $engine_data);

            // Update execution context with modified data
            \DataMachine\Engine\ExecutionContext::$data = $data;

            // Clear execution context
            \DataMachine\Engine\ExecutionContext::clear();

            $step_success = ! empty( $data );
            if ( $step_success ) {
                $next_flow_step_id = apply_filters('datamachine_get_next_flow_step_id', null, $flow_step_id);

                if ( $next_flow_step_id ) {
                    do_action('datamachine_schedule_next_step', $job_id, $next_flow_step_id, $data);
                } else {
                    do_action('datamachine_update_job_status', $job_id, 'completed', 'complete');
                    if ($repository) {
                        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
                        $context = datamachine_get_file_context($parts['flow_id']);
                        $repository->cleanup_job_data_packets($job_id, $context);
                    }
                    do_action('datamachine_log', 'info', 'Pipeline execution completed successfully', [
                        'job_id' => $job_id,
                        'flow_step_id' => $flow_step_id,
                        'final_data_count' => count($data)
                    ]);
                }
            } else {
                do_action('datamachine_log', 'error', 'Step execution failed - empty data packet', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class
                ]);
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class,
                    'reason' => 'empty_data_packet_returned'
                ]);
            }


            return $step_success;

        } catch ( \Throwable $e ) {
            do_action('datamachine_log', 'error', 'Error in pipeline step execution', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
                'reason' => 'throwable_exception_in_step_execution'
            ]);
            return false;
        }
    }, 10, 3 );

    add_action('datamachine_schedule_next_step', function($job_id, $flow_step_id, $data = []) {
        if (!function_exists('as_schedule_single_action')) {
            do_action('datamachine_log', 'error', 'Action Scheduler not available for step scheduling', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        $repositories = apply_filters('datamachine_files_repository', []);
        $repository = $repositories['files'] ?? null;

        if ($repository) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
            $context = datamachine_get_file_context($parts['flow_id']);
            $data_reference = $repository->store_data_packet($data, $job_id, $context);
        } else {
            $data_reference = ['data' => $data];
        }
        $action_id = as_schedule_single_action(
            time(),
            'datamachine_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => $data_reference
            ],
            'datamachine'
        );
        if (!empty($data)) {
            do_action('datamachine_log', 'debug', 'Next step scheduled via Action Scheduler', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'action_id' => $action_id,
                'success' => ($action_id !== false),
                'data_stored' => isset($data_reference['is_data_reference'])
            ]);
        }

        return $action_id !== false;
    }, 10, 3);

    add_action('datamachine_run_flow_later', function($flow_id, $interval_or_timestamp) {
        // 1. Always unschedule existing to prevent duplicates
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'datamachine');
        }

        // 2. Handle 'manual' case (just unscheduled, done)
        if ($interval_or_timestamp === 'manual') {
            do_action('datamachine_log', 'info', 'Flow schedule cleared (set to manual)', [
                'flow_id' => $flow_id
            ]);
            return;
        }

        // 3. Determine if timestamp (numeric) or interval string
        if (is_numeric($interval_or_timestamp)) {
            // One-time execution at specific timestamp
            if (function_exists('as_schedule_single_action')) {
                $action_id = as_schedule_single_action(
                    $interval_or_timestamp,
                    'datamachine_run_flow_now',
                    [$flow_id],
                    'datamachine'
                );

                do_action('datamachine_log', 'info', 'Flow scheduled for one-time execution', [
                    'flow_id' => $flow_id,
                    'timestamp' => $interval_or_timestamp,
                    'scheduled_time' => wp_date('c', $interval_or_timestamp),
                    'action_id' => $action_id
                ]);
            }
        } else {
            // Recurring execution
            $intervals = apply_filters('datamachine_scheduler_intervals', []);
            $interval_seconds = $intervals[$interval_or_timestamp] ?? null;

            if (!$interval_seconds) {
                do_action('datamachine_log', 'error', 'Invalid schedule interval', [
                    'flow_id' => $flow_id,
                    'interval' => $interval_or_timestamp,
                    'available_intervals' => array_keys($intervals)
                ]);
                return;
            }

            if (function_exists('as_schedule_recurring_action')) {
                $action_id = as_schedule_recurring_action(
                    time() + $interval_seconds,
                    $interval_seconds,
                    'datamachine_run_flow_now',
                    [$flow_id],
                    'datamachine'
                );

                do_action('datamachine_log', 'info', 'Flow scheduled for recurring execution', [
                    'flow_id' => $flow_id,
                    'interval' => $interval_or_timestamp,
                    'interval_seconds' => $interval_seconds,
                    'first_run' => wp_date('c', time() + $interval_seconds),
                    'action_id' => $action_id
                ]);
            }
        }
    }, 10, 2);

}