<?php
/**
 * Data Machine Update Actions
 *
 * Centralized update operations for jobs, flows, and pipelines.
 * Provides intelligent method selection, service discovery, and consistent
 * error handling for all update operations.
 *
 * SUPPORTED UPDATE TYPES:
 * - job_status: Intelligent job status updates with automatic method selection
 * - flow_schedule: Flow scheduling operations with Action Scheduler integration
 * - pipeline_auto_save: Central pipeline auto-save operations
 *
 * ARCHITECTURAL BENEFITS:
 * - Intelligent method routing for job status updates
 * - Direct Action Scheduler integration for flow scheduling
 * - Filter-based service discovery for database operations
 * - Centralized logging and error handling
 *
 * @package DataMachine
 * @since NEXT_VERSION
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Data Machine Update Actions Class
 *
 * Handles centralized update operations through multiple action hooks.
 * Provides consistent validation, service discovery, and error handling
 * patterns for all update types.
 *
 * @since NEXT_VERSION
 */
class DataMachine_Update_Actions {

    /**
     * Register update action hooks.
     *
     * Registers update action hooks that provide intelligent method selection
     * and consistent service discovery patterns.
     *
     * @since NEXT_VERSION
     */
    public function register_actions() {
        // Central job status update hook - eliminates confusion about which method to use
        add_action('dm_update_job_status', [$this, 'handle_job_status_update'], 10, 4);
        
        // Central flow scheduling hook - direct Action Scheduler integration
        add_action('dm_update_flow_schedule', [$this, 'handle_flow_schedule_update'], 10, 4);
        
        // Central pipeline auto-save hook - eliminates database service discovery duplication
        add_action('dm_auto_save', [$this, 'handle_pipeline_auto_save'], 10, 1);
    }

    /**
     * Handle job status updates with intelligent method selection.
     *
     * Eliminates confusion about which method to use (start_job vs complete_job vs update_job_status)
     * by automatically selecting the appropriate method based on context and status transitions.
     *
     * @param int $job_id Job ID to update
     * @param string $new_status New job status
     * @param string $context Update context ('start', 'complete', 'update')
     * @param string|null $old_status Previous job status for transition logic
     * @return bool Success status
     * @since NEXT_VERSION
     */
    public function handle_job_status_update($job_id, $new_status, $context = 'update', $old_status = null) {
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (!$db_jobs) {
            do_action('dm_log', 'error', 'Job status update failed - database service unavailable', [
                'job_id' => $job_id, 'new_status' => $new_status
            ]);
            return false;
        }
        
        // Intelligent method selection - removes confusion from call sites
        $success = false;
        $method_used = '';
        
        if ($context === 'start' || ($new_status === 'processing' && $old_status === 'pending')) {
            // Job is starting - use start_job for timestamp
            $success = $db_jobs->start_job($job_id, $new_status);
            $method_used = 'start_job';
            
        } elseif ($context === 'complete' || in_array($new_status, ['completed', 'failed', 'completed_with_errors', 'completed_no_items'])) {
            // Job is ending - use complete_job for timestamp
            $success = $db_jobs->complete_job($job_id, $new_status);
            $method_used = 'complete_job';
            
        } else {
            // Intermediate status change - use simple update
            $success = $db_jobs->update_job_status($job_id, $new_status);
            $method_used = 'update_job_status';
        }
        
        // Centralized logging
        do_action('dm_log', 'debug', 'Job status updated via hook', [
            'job_id' => $job_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'context' => $context,
            'method_used' => $method_used,
            'success' => $success
        ]);
        
        return $success;
    }

    /**
     * Handle flow schedule updates with Action Scheduler integration.
     *
     * Provides direct Action Scheduler integration eliminating wrapper patterns.
     * Handles both schedule activation and deactivation with comprehensive logging.
     *
     * @param int $flow_id Flow ID to update scheduling for
     * @param string $schedule_status New schedule status ('active', 'inactive')
     * @param string $schedule_interval Schedule interval key
     * @param string $old_status Previous schedule status
     * @since NEXT_VERSION
     */
    public function handle_flow_schedule_update($flow_id, $schedule_status, $schedule_interval, $old_status) {
        if ($schedule_status === 'active' && $schedule_interval !== 'manual') {
            // Get interval seconds using simple conversion
            $interval_seconds = $this->get_schedule_interval_seconds($schedule_interval);
            if (!$interval_seconds) {
                do_action('dm_log', 'error', 'Invalid schedule interval', [
                    'flow_id' => $flow_id,
                    'interval' => $schedule_interval
                ]);
                return;
            }
            
            // Direct Action Scheduler call - no wrapper layer
            if (function_exists('as_schedule_recurring_action')) {
                $action_id = as_schedule_recurring_action(
                    time(),
                    $interval_seconds,
                    "dm_execute_flow_{$flow_id}",
                    ['flow_id' => $flow_id],
                    'data-machine'
                );
                
                do_action('dm_log', 'debug', 'Flow scheduling activated via direct Action Scheduler', [
                    'flow_id' => $flow_id, 
                    'interval' => $schedule_interval,
                    'action_id' => $action_id,
                    'success' => ($action_id !== false)
                ]);
            }
        } elseif ($old_status === 'active') {
            // Direct Action Scheduler deactivation
            if (function_exists('as_unschedule_action')) {
                $result = as_unschedule_action(
                    "dm_execute_flow_{$flow_id}",
                    ['flow_id' => $flow_id],
                    'data-machine'
                );
                
                do_action('dm_log', 'debug', 'Flow scheduling deactivated via direct Action Scheduler', [
                    'flow_id' => $flow_id,
                    'previous_status' => $old_status,
                    'success' => ($result !== false)
                ]);
            }
        }
    }

    /**
     * Handle pipeline auto-save operations.
     *
     * Eliminates database service discovery duplication by providing centralized
     * pipeline auto-save operations with comprehensive error handling and logging.
     *
     * @param int $pipeline_id Pipeline ID to auto-save
     * @return bool Success status
     * @since NEXT_VERSION
     */
    public function handle_pipeline_auto_save($pipeline_id) {
        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error','Database service unavailable for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }
        
        // Get current pipeline data
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            do_action('dm_log', 'error','Pipeline not found for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }
        
        // Always do full save - get current name and steps
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $step_configuration = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        
        // Full pipeline save (always save everything)
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_name,
            'step_configuration' => json_encode($step_configuration)
        ]);
        
        // Log auto-save results
        if ($success) {
            do_action('dm_log', 'debug','Pipeline auto-saved successfully', [
                'pipeline_id' => $pipeline_id
            ]);
        } else {
            do_action('dm_log', 'error','Pipeline auto-save failed', [
                'pipeline_id' => $pipeline_id
            ]);
        }
        
        return $success;
    }

    /**
     * Get schedule interval in seconds for Action Scheduler usage.
     *
     * Utility method for converting schedule interval keys to seconds
     * for direct Action Scheduler integration.
     *
     * @param string $interval Schedule interval key
     * @return int|false Interval in seconds or false if invalid
     * @since NEXT_VERSION
     */
    private function get_schedule_interval_seconds($interval) {
        $intervals = apply_filters('dm_scheduler_intervals', []);
        return $intervals[$interval]['seconds'] ?? false;
    }
}