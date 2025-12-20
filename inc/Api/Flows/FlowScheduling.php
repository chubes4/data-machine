<?php
/**
 * Flow Scheduling Logic
 *
 * Dedicated class for handling flow scheduling operations.
 * Extracted from Flows.php for better maintainability.
 *
 * @package DataMachine\Api\Flows
 */

namespace DataMachine\Api\Flows;

if (!defined('ABSPATH')) {
    exit;
}

class FlowScheduling {

    /**
     * Handle scheduling configuration updates for a flow
     *
     * Preserves last_run_at and last_run_status when updating schedule configuration.
     *
     * @param int $flow_id Flow ID
     * @param array $scheduling_config Scheduling configuration
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public static function handle_scheduling_update($flow_id, $scheduling_config) {
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        // Get current flow to validate it exists
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            return new \WP_Error(
                'flow_not_found',
                "Flow {$flow_id} not found",
                ['status' => 404]
            );
        }

        // Preserve existing last_run data before updating schedule
        $existing_config = $db_flows->get_flow_scheduling($flow_id);
        $preserved_data = [];
        if (!empty($existing_config['last_run_at'])) {
            $preserved_data['last_run_at'] = $existing_config['last_run_at'];
        }
        if (!empty($existing_config['last_run_status'])) {
            $preserved_data['last_run_status'] = $existing_config['last_run_status'];
        }

        $interval = $scheduling_config['interval'] ?? null;

        // Handle manual scheduling (unschedule)
        if ($interval === 'manual' || $interval === null) {
            if (function_exists('as_unschedule_action')) {
                as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'data-machine');
            }

            $scheduling_data = array_merge(['interval' => 'manual'], $preserved_data);
            $db_flows->update_flow_scheduling($flow_id, $scheduling_data);
            return true;
        }

        // Handle one-time scheduling
        if ($interval === 'one_time') {
            $timestamp = $scheduling_config['timestamp'] ?? null;
            if (!$timestamp) {
                return new \WP_Error(
                    'missing_timestamp',
                    'Timestamp required for one-time scheduling',
                    ['status' => 400]
                );
            }

            if (!function_exists('as_schedule_single_action')) {
                return new \WP_Error(
                    'scheduler_unavailable',
                    'Action Scheduler not available',
                    ['status' => 500]
                );
            }

            as_schedule_single_action(
                $timestamp,
                'datamachine_run_flow_now',
                [$flow_id],
                'data-machine'
            );

            $scheduling_data = array_merge([
                'interval' => 'one_time',
                'timestamp' => $timestamp,
                'scheduled_time' => wp_date('c', $timestamp)
            ], $preserved_data);
            $db_flows->update_flow_scheduling($flow_id, $scheduling_data);
            return true;
        }

        // Handle recurring scheduling
        $intervals = apply_filters('datamachine_scheduler_intervals', []);
        $interval_seconds = $intervals[$interval]['seconds'] ?? null;

        if (!$interval_seconds) {
            return new \WP_Error(
                'invalid_interval',
                "Invalid interval: {$interval}",
                ['status' => 400]
            );
        }

        if (!function_exists('as_schedule_recurring_action')) {
            return new \WP_Error(
                'scheduler_unavailable',
                'Action Scheduler not available',
                ['status' => 500]
            );
        }

        // Clear any existing schedule first
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('datamachine_run_flow_now', [$flow_id], 'data-machine');
        }

        as_schedule_recurring_action(
            time() + $interval_seconds,
            $interval_seconds,
            'datamachine_run_flow_now',
            [$flow_id],
            'data-machine'
        );

        $scheduling_data = array_merge([
            'interval' => $interval,
            'interval_seconds' => $interval_seconds,
            'first_run' => wp_date('c', time() + $interval_seconds)
        ], $preserved_data);
        $db_flows->update_flow_scheduling($flow_id, $scheduling_data);
        return true;
    }
}