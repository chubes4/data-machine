<?php
/**
 * REST API Schedule Endpoint
 *
 * Dedicated endpoint for flow scheduling operations.
 * Handles recurring and one-time scheduling management.
 *
 * @package DataMachine\Api\Schedule
 */

namespace DataMachine\Api\Schedule;

if (!defined('ABSPATH')) {
    exit;
}

class Schedule {

    /**
     * Register REST API routes
     */
    public static function register() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register schedule REST routes
     */
    public static function register_routes() {
        register_rest_route('datamachine/v1', '/schedule', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_schedule'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'flow_id' => [
                    'required' => false, // Not required for get_intervals
                    'type' => 'integer',
                    'description' => 'Flow ID to schedule (required for schedule/unschedule/update actions)'
                ],
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['schedule', 'unschedule', 'update', 'get_intervals'],
                    'description' => 'Scheduling action to perform'
                ],
                'interval' => [
                    'type' => 'string',
                    'description' => 'Interval for recurring schedules (hourly, daily, etc.)'
                ],
                'timestamp' => [
                    'type' => 'integer',
                    'description' => 'Unix timestamp for one-time schedules'
                ]
            ]
        ]);
    }

    /**
     * Handle schedule endpoint requests
     */
    public static function handle_schedule($request) {
        $flow_id = $request->get_param('flow_id');
        $action = $request->get_param('action');
        $interval = $request->get_param('interval');
        $timestamp = $request->get_param('timestamp');

        // For actions that require a flow_id, validate it exists
        if (in_array($action, ['schedule', 'unschedule', 'update'])) {
            if (!$flow_id) {
                return new \WP_Error(
                    'missing_flow_id',
                    'flow_id is required for this action',
                    ['status' => 400]
                );
            }

            $db_flows = new \DataMachine\Core\Database\Flows\Flows();
            $flow = $db_flows->get_flow($flow_id);
            if (!$flow) {
                return new \WP_Error(
                    'flow_not_found',
                    "Flow {$flow_id} not found",
                    ['status' => 404]
                );
            }
        } elseif ($action === 'get_intervals') {
            // get_intervals doesn't need flow validation
            $flow = null;
        } else {
            return new \WP_Error(
                'invalid_action',
                'Invalid action specified',
                ['status' => 400]
            );
        }

        switch ($action) {
            case 'schedule':
                return self::schedule_flow($flow_id, $interval, $timestamp, $flow);

            case 'unschedule':
                return self::unschedule_flow($flow_id);

            case 'update':
                return self::update_schedule($flow_id, $interval, $timestamp, $flow);

            case 'get_intervals':
                return self::get_intervals();

            default:
                // This should never be reached due to earlier validation
                return new \WP_Error(
                    'invalid_action',
                    'Invalid action specified',
                    ['status' => 400]
                );
        }
    }

    /**
     * Schedule a flow for execution
     */
    private static function schedule_flow($flow_id, $interval, $timestamp, $flow) {
        // Handle one-time execution at specific timestamp
        if ($timestamp) {
            if (!function_exists('as_schedule_single_action')) {
                return new \WP_Error(
                    'scheduler_unavailable',
                    'Action Scheduler not available',
                    ['status' => 500]
                );
            }

            $action_id = as_schedule_single_action(
                $timestamp,
                'datamachine_run_flow_now',
                [$flow_id],
                'datamachine'
            );

            do_action('datamachine_log', 'info', 'Flow scheduled for one-time execution via Schedule API', [
                'flow_id' => $flow_id,
                'timestamp' => $timestamp,
                'scheduled_time' => wp_date('c', $timestamp),
                'action_id' => $action_id
            ]);

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'action' => 'schedule',
                    'type' => 'one_time',
                    'flow_id' => $flow_id,
                    'flow_name' => $flow['flow_name'] ?? "Flow {$flow_id}",
                    'timestamp' => $timestamp,
                    'scheduled_time' => wp_date('c', $timestamp)
                ],
                'message' => 'Flow scheduled for one-time execution'
            ]);
        }

        // Handle recurring execution
        if ($interval) {
            if (!function_exists('as_schedule_recurring_action')) {
                return new \WP_Error(
                    'scheduler_unavailable',
                    'Action Scheduler not available',
                    ['status' => 500]
                );
            }

            // Get interval seconds
            $intervals = apply_filters('datamachine_scheduler_intervals', []);
            $interval_seconds = $intervals[$interval]['seconds'] ?? null;

            if (!$interval_seconds) {
                return new \WP_Error(
                    'invalid_interval',
                    "Invalid interval: {$interval}",
                    ['status' => 400]
                );
            }

            // Clear any existing schedule first
            if (function_exists('as_unschedule_action')) {
                as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'datamachine');
            }

            $action_id = as_schedule_recurring_action(
                time() + $interval_seconds,
                $interval_seconds,
                'datamachine_run_flow_now',
                [$flow_id],
                'datamachine'
            );

            do_action('datamachine_log', 'info', 'Flow scheduled for recurring execution via Schedule API', [
                'flow_id' => $flow_id,
                'interval' => $interval,
                'interval_seconds' => $interval_seconds,
                'first_run' => wp_date('c', time() + $interval_seconds),
                'action_id' => $action_id
            ]);

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'action' => 'schedule',
                    'type' => 'recurring',
                    'flow_id' => $flow_id,
                    'flow_name' => $flow['flow_name'] ?? "Flow {$flow_id}",
                    'interval' => $interval,
                    'interval_seconds' => $interval_seconds,
                    'first_run' => wp_date('c', time() + $interval_seconds)
                ],
                'message' => "Flow scheduled to run {$interval}"
            ]);
        }

        return new \WP_Error(
            'missing_schedule_params',
            'Must provide either timestamp or interval for scheduling',
            ['status' => 400]
        );
    }

    /**
     * Unschedule a flow
     */
    private static function unschedule_flow($flow_id) {
        if (!function_exists('as_unschedule_action')) {
            return new \WP_Error(
                'scheduler_unavailable',
                'Action Scheduler not available',
                ['status' => 500]
            );
        }

        as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'datamachine');

        do_action('datamachine_log', 'info', 'Flow schedule cleared via Schedule API', [
            'flow_id' => $flow_id
        ]);

        return rest_ensure_response([
            'success' => true,
            'data' => [
                'action' => 'unschedule',
                'flow_id' => $flow_id
            ],
            'message' => 'Flow schedule cleared'
        ]);
    }

    /**
     * Update an existing schedule
     */
    private static function update_schedule($flow_id, $interval, $timestamp, $flow) {
        // For updates, we unschedule first, then reschedule
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'datamachine');
        }

        // If setting to manual, just unschedule
        if ($interval === 'manual' || (!$interval && !$timestamp)) {
            do_action('datamachine_log', 'info', 'Flow schedule set to manual via Schedule API', [
                'flow_id' => $flow_id
            ]);

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'action' => 'update',
                    'type' => 'manual',
                    'flow_id' => $flow_id
                ],
                'message' => 'Flow schedule set to manual'
            ]);
        }

        // Otherwise, reschedule with new parameters
        return self::schedule_flow($flow_id, $interval, $timestamp, $flow);
    }

    /**
     * Get available scheduling intervals
     */
    private static function get_intervals() {
        $intervals = apply_filters('datamachine_scheduler_intervals', []);

        // Transform from PHP format to frontend format
        $frontend_intervals = [];

        // Add manual option first
        $frontend_intervals[] = [
            'value' => 'manual',
            'label' => __('Manual only', 'datamachine')
        ];

        // Add all PHP-defined intervals
        foreach ($intervals as $key => $interval_data) {
            $frontend_intervals[] = [
                'value' => $key,
                'label' => $interval_data['label']
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $frontend_intervals
        ]);
    }
}