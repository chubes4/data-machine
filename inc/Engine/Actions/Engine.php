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
      * Execute a single step in a Data Machine flow.
      *
      * @param int $job_id Job ID for the execution
      * @param string $flow_step_id Flow step ID to execute
      * @param array|null $dataPackets Input data packets for the step
      * @return bool True on success, false on failure
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

/**
 * Register execution engine actions and handlers.
 *
 * Sets up the core execution cycle actions:
 * - datamachine_run_flow_now: Initiates flow execution
 * - datamachine_execute_step: Executes individual steps
 * - datamachine_schedule_next_step: Schedules subsequent steps
 */
function datamachine_register_execution_engine() {

    /**
     * Execute flow immediately.
     *
     * Creates a job record, loads flow/pipeline configurations,
     * and schedules the first step for execution.
     *
     * @param int $flow_id Flow ID to execute
     * @return bool True on success, false on failure
     */
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

    /**
     * Execute individual step in a flow.
     *
     * Loads step configuration, instantiates step class, executes the step,
     * and schedules the next step in the flow.
     *
     * @param int $job_id Job ID for the execution
     * @param string $flow_step_id Flow step ID to execute
     * @param array|null $data Input data for the step
     * @return bool True on success, false on failure
     */
    add_action( 'datamachine_execute_step', function( $job_id, $flow_step_id, $dataPackets = null ) {

        try {
            // Convert Action Scheduler's stdClass to array
            $dataPackets = is_object($dataPackets) ? json_decode(json_encode($dataPackets), true) : $dataPackets;

            $storage = apply_filters('datamachine_get_file_storage', null);

            if ($storage && is_array($dataPackets) && isset($dataPackets['is_data_reference']) && $dataPackets['is_data_reference']) {
                $dataPackets = $storage->retrieve_data_packet($dataPackets);
                if ($dataPackets === null) {
                    do_action('datamachine_fail_job', $job_id, 'data_retrieval_failure', [
                        'flow_step_id' => $flow_step_id
                    ]);
                    return false;
                }
            }
            $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id, $job_id);
            if (!$flow_step_config) {
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

            $payload = [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => is_array($dataPackets) ? $dataPackets : [],
                'flow_step_config' => $flow_step_config,
                'engine_data' => is_array($engine_data) ? $engine_data : [],
            ];

            $dataPackets = $flow_step->execute($payload);

            if (!is_array($dataPackets)) {
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'class' => $step_class,
                    'reason' => 'non_array_payload_returned'
                ]);

                return false;
            }

            $payload['data'] = $dataPackets;

            $step_success = ! empty( $dataPackets );
            if ( $step_success ) {
                $next_flow_step_id = apply_filters('datamachine_get_next_flow_step_id', null, $flow_step_id, $payload);

                if ( $next_flow_step_id ) {
                    do_action('datamachine_schedule_next_step', $job_id, $next_flow_step_id, $dataPackets);
                } else {
                    do_action('datamachine_update_job_status', $job_id, 'completed', 'complete');
                    $cleanup = apply_filters('datamachine_get_file_cleanup', null);
                    if ($cleanup) {
                        $flow_id = $flow_step_config['flow_id'] ?? 0;
                        $context = datamachine_get_file_context($flow_id);
                        $cleanup->cleanup_job_data_packets($job_id, $context);
                    }
                    do_action('datamachine_log', 'info', 'Pipeline execution completed successfully', [
                        'job_id' => $job_id,
                        'flow_step_id' => $flow_step_id,
                        'final_packet_count' => count($dataPackets)
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
            do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
                'reason' => 'throwable_exception_in_step_execution'
            ]);
            return false;
        }
    }, 10, 3 );

     /**
      * Schedule next step in flow execution.
      *
      * Stores data packet in repository if needed, then schedules
      * the step execution via Action Scheduler.
      *
      * @param int $job_id Job ID for the execution
      * @param string $flow_step_id Flow step ID to schedule
      * @param array $dataPackets Data packets to pass to the next step
      * @return bool True on successful scheduling, false on failure
      */
    add_action('datamachine_schedule_next_step', function($job_id, $flow_step_id, $dataPackets = []) {
        if (!function_exists('as_schedule_single_action')) {
            return false;
        }

        $storage = apply_filters('datamachine_get_file_storage', null);

        if ($storage) {
            $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
            $flow_id = $flow_step_config['flow_id'] ?? 0;
            $context = datamachine_get_file_context($flow_id);
            $data_reference = $storage->store_data_packet($dataPackets, $job_id, $context);
        } else {
            $data_reference = ['data' => $dataPackets];
        }
        $action_id = as_schedule_single_action(
            time(),
            'datamachine_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'dataPackets' => $data_reference
            ],
            'datamachine'
        );
        if (!empty($dataPackets)) {
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

    /**
     * Schedule flow execution for later.
     *
     * Handles both one-time execution at specific timestamps and
     * recurring execution at defined intervals. Use 'manual' to
     * clear existing schedules.
     *
     * @param int $flow_id Flow ID to schedule
     * @param string|int $interval_or_timestamp Either 'manual', numeric timestamp, or interval key
     */
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
            $interval_seconds = $intervals[$interval_or_timestamp]['seconds'] ?? null;

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