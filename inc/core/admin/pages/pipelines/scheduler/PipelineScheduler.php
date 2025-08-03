<?php
/**
 * Pipeline Scheduler Component
 *
 * Simple flow scheduling service that interfaces directly with Action Scheduler.
 * No queue wrapper - leverages Action Scheduler's native scheduling capabilities.
 * Each flow schedules itself when activated; no master scheduler needed.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Scheduler
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Scheduler;

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class PipelineScheduler
{
    /**
     * Constructor - parameter-less for filter-based architecture
     */
    public function __construct()
    {
        // All services accessed via filters
    }

    /**
     * Activate scheduling for a specific flow
     * Schedules flow directly in Action Scheduler
     *
     * @param int $flow_id Flow ID to activate
     * @return bool True on success, false on failure
     */
    public function activate_flow(int $flow_id): bool
    {
        // Get flow data via filters
        $flows_db = apply_filters('dm_get_database_service', null, 'flows');
        if (!$flows_db) {
            return false;
        }

        $flow = $flows_db->get_flow($flow_id);
        if (!$flow) {
            return false;
        }

        // Parse scheduling config
        $scheduling_config = json_decode($flow['scheduling_config'] ?? '{}', true);
        if (($scheduling_config['status'] ?? 'inactive') !== 'active') {
            return false;
        }

        $interval = $scheduling_config['interval'] ?? 'manual';
        if ($interval === 'manual') {
            return true; // Manual flows don't need scheduling
        }

        // Get interval seconds
        $interval_seconds = $this->get_interval_seconds($interval);
        if (!$interval_seconds) {
            return false;
        }

        // Schedule directly in Action Scheduler - no wrapper needed
        $action_id = as_schedule_recurring_action(
            time(),
            $interval_seconds,
            "dm_execute_flow_{$flow_id}",
            ['flow_id' => $flow_id],
            'data-machine'
        );

        if ($action_id) {
            // Log successful scheduling
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->info('Flow scheduled successfully', [
                    'flow_id' => $flow_id,
                    'interval' => $interval,
                    'action_id' => $action_id
                ]);
            }
        }

        return $action_id !== false;
    }

    /**
     * Deactivate scheduling for a specific flow
     * Unschedules flow from Action Scheduler
     *
     * @param int $flow_id Flow ID to deactivate
     * @return bool True on success, false on failure
     */
    public function deactivate_flow(int $flow_id): bool
    {
        $result = as_unschedule_action(
            "dm_execute_flow_{$flow_id}",
            ['flow_id' => $flow_id],
            'data-machine'
        );

        if ($result) {
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->info('Flow unscheduled successfully', [
                    'flow_id' => $flow_id
                ]);
            }
        }

        return $result !== false;
    }

    /**
     * Update flow schedule (reschedule with new interval)
     *
     * @param int $flow_id Flow ID to update
     * @param string $new_interval New interval slug
     * @return bool True on success, false on failure
     */
    public function update_flow_schedule(int $flow_id, string $new_interval): bool
    {
        // Unschedule existing
        $this->deactivate_flow($flow_id);

        // Update database
        $flows_db = apply_filters('dm_get_database_service', null, 'flows');
        if (!$flows_db) {
            return false;
        }

        $flow = $flows_db->get_flow($flow_id);
        if (!$flow) {
            return false;
        }

        $scheduling_config = json_decode($flow['scheduling_config'] ?? '{}', true);
        $scheduling_config['interval'] = $new_interval;

        $result = $flows_db->update_flow($flow_id, [
            'scheduling_config' => wp_json_encode($scheduling_config)
        ]);

        if ($result && $scheduling_config['status'] === 'active') {
            // Reschedule with new interval
            return $this->activate_flow($flow_id);
        }

        return $result;
    }

    /**
     * Get next scheduled execution time for a flow
     *
     * @param int $flow_id Flow ID
     * @return string|null Next execution time or null if not scheduled
     */
    public function get_next_run_time(int $flow_id): ?string
    {
        $action = as_next_scheduled_action("dm_execute_flow_{$flow_id}", ['flow_id' => $flow_id], 'data-machine');
        
        if ($action) {
            return date('Y-m-d H:i:s', $action);
        }

        return null;
    }

    /**
     * Check if a flow is currently scheduled
     *
     * @param int $flow_id Flow ID
     * @return bool True if scheduled, false otherwise
     */
    public function is_flow_scheduled(int $flow_id): bool
    {
        return as_next_scheduled_action("dm_execute_flow_{$flow_id}", ['flow_id' => $flow_id], 'data-machine') !== false;
    }

    /**
     * Get interval seconds from schedule slug
     *
     * @param string $interval Interval slug
     * @return int|false Interval in seconds or false if invalid
     */
    private function get_interval_seconds(string $interval)
    {
        // Get schedule intervals via filter
        $schedules = apply_filters('dm_get_scheduler', null, 'intervals');
        if (!$schedules || !isset($schedules[$interval])) {
            return false;
        }

        return $schedules[$interval]['interval'] ?? false;
    }

    /**
     * Execute a flow (called by Action Scheduler hook)
     * Uses existing JobCreator to create and schedule job
     *
     * @param int $flow_id Flow ID to execute
     * @return bool True on success, false on failure
     */
    public function execute_flow(int $flow_id): bool
    {
        // Get flow data
        $flows_db = apply_filters('dm_get_database_service', null, 'flows');
        if (!$flows_db) {
            return false;
        }

        $flow = $flows_db->get_flow($flow_id);
        if (!$flow) {
            return false;
        }

        // Use existing JobCreator - no wrapper needed
        $job_creator = apply_filters('dm_get_job_creator', null);
        if (!$job_creator) {
            return false;
        }

        $result = $job_creator->create_and_schedule_job(
            (int)$flow['pipeline_id'],
            $flow_id,
            (int)$flow['user_id'],
            'scheduled'
        );

        // Update last run timestamp
        if ($result['success'] ?? false) {
            $scheduling_config = json_decode($flow['scheduling_config'] ?? '{}', true);
            $scheduling_config['last_run_at'] = current_time('mysql');
            
            $flows_db->update_flow($flow_id, [
                'scheduling_config' => wp_json_encode($scheduling_config)
            ]);
        }

        return $result['success'] ?? false;
    }
}