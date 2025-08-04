<?php

namespace DataMachine\Engine;

/**
 * ActionScheduler Service
 * 
 * Filter-based wrapper for Action Scheduler functionality to maintain architectural consistency.
 * Provides centralized scheduling operations with consistent error handling and logging integration.
 */
class ActionSchedulerService {

    /**
     * Schedule a single action to run at a specific time
     *
     * @param int    $timestamp Unix timestamp when the action should run
     * @param string $hook      Action hook to schedule
     * @param array  $args      Arguments to pass to the action
     * @param string $group     Group identifier for the action
     * @return int|false Action ID on success, false on failure
     */
    public function schedule_single_action($timestamp, $hook, $args = [], $group = '') {
        if (!function_exists('as_schedule_single_action')) {
            $this->log_error('Action Scheduler not available for schedule_single_action');
            return false;
        }

        try {
            $action_id = as_schedule_single_action($timestamp, $hook, $args, $group);
            $this->log_debug("Scheduled single action: hook={$hook}, timestamp={$timestamp}, group={$group}, action_id={$action_id}");
            return $action_id;
        } catch (\Exception $e) {
            $this->log_error("Failed to schedule single action: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Schedule a recurring action
     *
     * @param int    $timestamp        Unix timestamp for first occurrence
     * @param int    $interval_seconds Interval between occurrences in seconds
     * @param string $hook             Action hook to schedule
     * @param array  $args             Arguments to pass to the action
     * @param string $group            Group identifier for the action
     * @return int|false Action ID on success, false on failure
     */
    public function schedule_recurring_action($timestamp, $interval_seconds, $hook, $args = [], $group = '') {
        if (!function_exists('as_schedule_recurring_action')) {
            $this->log_error('Action Scheduler not available for schedule_recurring_action');
            return false;
        }

        try {
            $action_id = as_schedule_recurring_action($timestamp, $interval_seconds, $hook, $args, $group);
            $this->log_debug("Scheduled recurring action: hook={$hook}, interval={$interval_seconds}s, group={$group}, action_id={$action_id}");
            return $action_id;
        } catch (\Exception $e) {
            $this->log_error("Failed to schedule recurring action: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Unschedule a specific action
     *
     * @param string $hook  Action hook to unschedule
     * @param array  $args  Arguments that were passed to the action
     * @param string $group Group identifier for the action
     * @return int|false Number of actions unscheduled, false on failure
     */
    public function unschedule_action($hook, $args = [], $group = '') {
        if (!function_exists('as_unschedule_action')) {
            $this->log_error('Action Scheduler not available for unschedule_action');
            return false;
        }

        try {
            $result = as_unschedule_action($hook, $args, $group);
            $this->log_debug("Unscheduled action: hook={$hook}, group={$group}, count={$result}");
            return $result;
        } catch (\Exception $e) {
            $this->log_error("Failed to unschedule action: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Unschedule all actions for a given hook
     *
     * @param string $hook  Action hook to unschedule
     * @param array  $args  Arguments that were passed to the action (optional)
     * @param string $group Group identifier for the action (optional)
     * @return int|false Number of actions unscheduled, false on failure
     */
    public function unschedule_all_actions($hook, $args = [], $group = '') {
        if (!function_exists('as_unschedule_all_actions')) {
            $this->log_error('Action Scheduler not available for unschedule_all_actions');
            return false;
        }

        try {
            $result = as_unschedule_all_actions($hook, $args, $group);
            $this->log_debug("Unscheduled all actions: hook={$hook}, group={$group}, count={$result}");
            return $result;
        } catch (\Exception $e) {
            $this->log_error("Failed to unschedule all actions: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get the next scheduled time for an action
     *
     * @param string $hook  Action hook to check
     * @param array  $args  Arguments that were passed to the action
     * @param string $group Group identifier for the action
     * @return int|false Unix timestamp of next scheduled action, false if none scheduled
     */
    public function get_next_scheduled_action($hook, $args = [], $group = '') {
        if (!function_exists('as_next_scheduled_action')) {
            $this->log_error('Action Scheduler not available for get_next_scheduled_action');
            return false;
        }

        try {
            $next_time = as_next_scheduled_action($hook, $args, $group);
            $this->log_debug("Checked next scheduled action: hook={$hook}, group={$group}, next_time={$next_time}");
            return $next_time;
        } catch (\Exception $e) {
            $this->log_error("Failed to get next scheduled action: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Log debug message using the plugin's logger service
     *
     * @param string $message Debug message to log
     */
    private function log_debug($message) {
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug("[ActionSchedulerService] {$message}");
        }
    }

    /**
     * Log error message using the plugin's logger service
     *
     * @param string $message Error message to log
     */
    private function log_error($message) {
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->error("[ActionSchedulerService] {$message}");
        }
    }
}