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
    return \DataMachine\Api\Files::get_file_context($flow_id);
}

/**
 * Register execution engine action hooks.
 *
 * Registers the four core execution actions:
 * - datamachine_run_flow_now
 * - datamachine_execute_step
 * - datamachine_schedule_next_step
 * - datamachine_run_flow_later
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
    $db_flows = new \DataMachine\Core\Database\Flows\Flows();
    $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

    $flow = $db_flows->get_flow($flow_id);
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
    $flow_config = $flow['flow_config'] ?? [];
    $pipeline_id = (int)$flow['pipeline_id'];

    // Load pipeline config
    $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
    $pipeline = $db_pipelines->get_pipeline($pipeline_id);
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
}, 10, 1);

/**
 * Execute a single step in a pipeline flow.
 *
 * @param int $job_id Job ID for the execution
 * @param string $flow_step_id Flow step ID to execute
 * @param array|null $data Input data for the step
 * @return bool True on success, false on failure
 */
add_action( 'datamachine_execute_step', function( int $job_id, string $flow_step_id, ?array $dataPackets = null ) {

        try {
            do_action('datamachine_log', 'debug', 'EXECUTE STEP START', [
                'job_id' => $job_id,
                'job_id_type' => gettype($job_id),
                'flow_step_id' => $flow_step_id,
                'flow_step_id_type' => gettype($flow_step_id),
                'dataPackets_type' => gettype($dataPackets)
            ]);

            // Retrieve data by job_id
            $db_flows = new \DataMachine\Core\Database\Flows\Flows();
            /** @var array $flow_step_config */
            $flow_step_config = $db_flows->get_flow_step_config( $flow_step_id, $job_id, true );

            if (!isset($flow_step_config['flow_id']) || empty($flow_step_config['flow_id'])) {
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'reason' => 'missing_flow_id_in_step_config'
                ]);
                return false;
            }

            $flow_id = $flow_step_config['flow_id'];

            do_action('datamachine_log', 'debug', 'FLOW STEP CONFIG RETRIEVED', [
                'flow_step_config_type' => gettype($flow_step_config),
                'is_array' => is_array($flow_step_config),
                'is_object' => is_object($flow_step_config),
                'flow_id' => $flow_id
            ]);
            /** @var array $context */
            $context = datamachine_get_file_context($flow_id);

            $retrieval = new \DataMachine\Core\FilesRepository\FileRetrieval();
            /** @var array $dataPackets */
            $dataPackets = $retrieval->retrieve_data_by_job_id($job_id, $context);

            if (!$flow_step_config) {
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'reason' => 'failed_to_load_flow_step_configuration'
                ]);
                return false;
            }

            if (!isset($flow_step_config['step_type']) || empty($flow_step_config['step_type'])) {
                do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
                    'flow_step_id' => $flow_step_id,
                    'reason' => 'missing_step_type_in_flow_step_config'
                ]);
                return false;
            }

            $step_type = $flow_step_config['step_type'];
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
            $engine_data = datamachine_get_engine_data($job_id);

            do_action('datamachine_log', 'debug', 'ENGINE DATA RETRIEVED', [
                'engine_data_type' => gettype($engine_data),
                'is_array' => is_array($engine_data),
                'is_object' => is_object($engine_data),
                'keys' => is_array($engine_data) ? array_keys($engine_data) : 'NOT ARRAY'
            ]);

            $payload = [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data' => is_array($dataPackets) ? $dataPackets : [],
                'flow_step_config' => $flow_step_config,
                'engine_data' => is_array($engine_data) ? $engine_data : [],
            ];

            do_action('datamachine_log', 'debug', 'PAYLOAD CONSTRUCTED', [
                'payload_keys' => array_keys($payload),
                'engine_data_in_payload_type' => gettype($payload['engine_data']),
                'engine_data_is_array' => is_array($payload['engine_data'])
            ]);

            $dataPackets = $flow_step->execute($payload);

            do_action('datamachine_log', 'debug', 'STEP EXECUTED', [
                'dataPackets_returned_type' => gettype($dataPackets),
                'is_array' => is_array($dataPackets)
            ]);

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
                $navigator = new \DataMachine\Engine\StepNavigator();
                $next_flow_step_id = $navigator->get_next_flow_step_id($flow_step_id, $payload);

                if ( $next_flow_step_id ) {
                    do_action('datamachine_schedule_next_step', $job_id, $next_flow_step_id, $dataPackets);
                } else {
                    do_action('datamachine_update_job_status', $job_id, 'completed', 'complete');
                    $cleanup = new \DataMachine\Core\FilesRepository\FileCleanup();
                    if (!isset($flow_step_config['flow_id']) || empty($flow_step_config['flow_id'])) {
                        do_action('datamachine_log', 'error', 'Flow ID missing during cleanup', [
                            'job_id' => $job_id,
                            'flow_step_id' => $flow_step_id
                        ]);
                        return false;
                    }
                    $flow_id = $flow_step_config['flow_id'];
                    $context = datamachine_get_file_context($flow_id);
                    $cleanup->cleanup_job_data_packets($job_id, $context);
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

        // Store data by job_id (if present)
        if (!empty($dataPackets)) {
            $db_flows = new \DataMachine\Core\Database\Flows\Flows();
            $flow_step_config = $db_flows->get_flow_step_config( $flow_step_id, $job_id, true );
            if (!isset($flow_step_config['flow_id']) || empty($flow_step_config['flow_id'])) {
                do_action('datamachine_log', 'error', 'Flow ID missing during data storage', [
                    'job_id' => $job_id,
                    'flow_step_id' => $flow_step_id
                ]);
                return false;
            }
            $flow_id = $flow_step_config['flow_id'];
            $context = datamachine_get_file_context($flow_id);

            $storage = new \DataMachine\Core\FilesRepository\FileStorage();
            $storage->store_data_packet($dataPackets, $job_id, $context);
        }

        // Action Scheduler only receives IDs
        $action_id = as_schedule_single_action(
            time(),
            'datamachine_execute_step',
            [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id
            ],
            'datamachine'
        );

        if (!empty($dataPackets)) {
            do_action('datamachine_log', 'debug', 'Next step scheduled via Action Scheduler', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'action_id' => $action_id,
                'success' => ($action_id !== false)
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

} // End datamachine_register_execution_engine()