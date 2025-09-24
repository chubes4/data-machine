<?php
namespace DataMachine\Engine\Actions;


/**
 * Centralized update operations for jobs, flows, and pipelines.
 *
 * Provides intelligent method selection, service discovery, and consistent
 * error handling via action hooks.
 *
 * @package DataMachine\Engine\Actions
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Centralized update operations via action hooks.
 */
class Update {

    /**
     * Register update action hooks with intelligent method selection.
     */
    public static function register() {
        $instance = new self();
        
        // Central job status update hook - eliminates confusion about which method to use
        add_action('dm_update_job_status', [$instance, 'handle_job_status_update'], 10, 4);
        
        // Central flow scheduling hook - direct Action Scheduler integration
        add_action('dm_update_flow_schedule', [$instance, 'handle_flow_schedule_update'], 10, 3);
        
        
        // Flow handler management action hook - eliminates 50+ line handler update patterns
        add_action('dm_update_flow_handler', [$instance, 'handle_flow_handler_update'], 10, 3);
        
        // Flow step synchronization action hook - unifies single and bulk step sync operations
        add_action('dm_sync_steps_to_flow', [$instance, 'handle_flow_steps_sync'], 10, 3);
        
        // Flow user message management action hook - enables AI steps to run standalone
        add_action('dm_update_flow_user_message', [$instance, 'handle_flow_user_message_update'], 10, 2);

        // Pipeline system prompt management action hook - enables AI step template updates
        add_action('dm_update_system_prompt', [$instance, 'handle_system_prompt_update'], 10, 2);

        // Filter-based versions for AJAX validation
        add_filter('dm_update_flow_user_message_result', [$instance, 'handle_flow_user_message_update'], 10, 3);
        add_filter('dm_update_system_prompt_result', [$instance, 'handle_system_prompt_update'], 10, 3);
        
        // Explicit job failure action hook - simplified job failure interface
        add_action('dm_fail_job', [$instance, 'handle_job_failure'], 10, 3);
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
     * @since 1.0.0
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
        
        $success = false;
        $method_used = '';
        
        if ($context === 'start' || ($new_status === 'processing' && $old_status === 'pending')) {
            // Job is starting - use start_job for timestamp
            $success = $db_jobs->start_job($job_id, $new_status);
            $method_used = 'start_job';
            
        } elseif ($context === 'complete' || in_array($new_status, ['completed', 'failed', 'completed_no_items'])) {
            // Job is ending - use complete_job for timestamp
            $success = $db_jobs->complete_job($job_id, $new_status);
            $method_used = 'complete_job';
            
        } else {
            $success = $db_jobs->update_job_status($job_id, $new_status);
            $method_used = 'update_job_status';
        }
        
        // Clean up processed items if job failed (allows retry without processed item conflicts)
        if ($new_status === 'failed' && $success) {
            do_action('dm_delete', 'processed_items', $job_id, ['delete_by' => 'job_id']);
        }
        
        
        return $success;
    }

    /**
     * Handle flow schedule updates with Action Scheduler integration.
     *
     * Provides direct Action Scheduler integration eliminating wrapper patterns.
     * Uses interval-only logic: manual = run-now only, others = scheduled.
     *
     * @param int $flow_id Flow ID to update scheduling for
     * @param string $schedule_interval Schedule interval key
     * @param string $old_interval Previous schedule interval
     * @since 1.0.0
     */
    public function handle_flow_schedule_update($flow_id, $schedule_interval, $old_interval = '') {
        // Always unschedule first to prevent duplicates
        if (function_exists('as_unschedule_action')) {
            as_unschedule_action('dm_run_flow_now', [$flow_id], 'data-machine');
        }
        
        if ($schedule_interval !== 'manual') {
            $interval_seconds = $this->get_schedule_interval_seconds($schedule_interval);
            if (!$interval_seconds) {
                do_action('dm_log', 'error', 'Invalid schedule interval', [
                    'flow_id' => $flow_id,
                    'interval' => $schedule_interval
                ]);
                return;
            }
            
            // Direct Action Scheduler call using universal flow execution entry point
            if (function_exists('as_schedule_recurring_action')) {
                $action_id = as_schedule_recurring_action(
                    time() + $interval_seconds,
                    $interval_seconds,
                    'dm_run_flow_now',
                    [$flow_id],
                    'data-machine'
                );
                
            }
        } else {
        }
    }


    /**
     * Handle flow handler updates with centralized database operations.
     *
     * Eliminates repetitive handler update patterns by providing centralized
     * handler addition/update functionality with consistent error handling.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $handler_slug Handler slug to add/update
     * @param array $handler_settings Handler configuration settings
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_handler_update($flow_step_id, $handler_slug, $handler_settings = []) {
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('dm_log', 'error', 'Invalid flow_step_id format for handler update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];
        
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow handler update failed - database service unavailable', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug
            ]);
            return false;
        }
        
        // Get current flow
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow handler update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        $flow_config = $flow['flow_config'] ?? [];
        
        // Initialize step configuration if it doesn't exist
        if (!isset($flow_config[$flow_step_id])) {
            $pipeline_step_id = $parts['pipeline_step_id'] ?? null; // Use parsed pipeline_step_id
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id,
                'handler' => null
            ];
        }
        
        // Check if handler already exists
        $handler_exists = isset($flow_config[$flow_step_id]['handler']) && 
                         ($flow_config[$flow_step_id]['handler']['handler_slug'] ?? '') === $handler_slug;
        
        // UPDATE existing handler settings OR ADD new handler (single handler per step)
        $nested_settings = [
            $handler_slug => $handler_settings
        ];
        $flow_config[$flow_step_id]['handler'] = [
            'handler_slug' => $handler_slug,
            'settings' => $nested_settings,
            'enabled' => true
         ];

         // Update flow with new configuration
         $success = apply_filters('dm_update_flow', false, $flow_id, [
             'flow_config' => wp_json_encode($flow_config)
         ]);
         
         if (!$success) {
             do_action('dm_log', 'error', 'Flow handler update failed - database update failed', [
                 'flow_id' => $flow_id,
                 'flow_step_id' => $flow_step_id,
                 'handler_slug' => $handler_slug
             ]);
             return false;
         }

         return true;
    }

    /**
     * Handle flow step synchronization for single or multiple steps.
     *
     * Unified logic for creating flow step configurations from pipeline steps.
     * Eliminates code duplication between single step sync and bulk step sync operations.
     *
     * @param int $flow_id Flow ID to sync steps to
     * @param array $steps Array of pipeline step data (single step = array with one element)
     * @param array $context Context information for logging and debugging
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_steps_sync($flow_id, $steps, $context = []) {
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow steps sync failed - flows database service unavailable', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }
        
        // Validate flow exists
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow steps sync failed - flow not found', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }
        $flow_config = $flow['flow_config'] ?? [];
        
        // Process each step
        foreach ($steps as $step) {
            $pipeline_step_id = $step['pipeline_step_id'] ?? null;
            
            if (!$pipeline_step_id) {
                do_action('dm_log', 'warning', 'Skipping step sync - missing pipeline_step_id', [
                    'flow_id' => $flow_id,
                    'step_data' => $step,
                    'context' => $context
                ]);
                continue;
            }
            
            // Generate flow step ID using existing filter pattern
            $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
            
            // Create flow step configuration
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'step_type' => $step['step_type'] ?? '',
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $flow['pipeline_id'],
                'flow_id' => $flow_id,
                'execution_order' => $step['execution_order'] ?? 0,
                'handler' => null
            ];
        }

        // Update flow configuration
        $success = apply_filters('dm_update_flow', false, $flow_id, [
            'flow_config' => wp_json_encode($flow_config)
        ]);
        
        if (!$success) {
            do_action('dm_log', 'error', 'Flow steps sync failed - database update failed', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }

        do_action('dm_log', 'debug', 'Flow steps sync completed successfully', [
            'flow_id' => $flow_id,
            'pipeline_id' => $flow['pipeline_id'],
            'steps_count' => count($steps),
            'context' => $context
        ]);

        return true;
    }

    /**
     * Handle flow user message updates for AI steps.
     *
     * Enables AI steps to run standalone by providing user message content
     * that gets converted to data packets when no fetch step precedes them.
     * 
     * Flow-scoped user messages allow different content per flow instance
     * while maintaining pipeline-level system prompt templates.
     *
     * @param string|bool $result Previous filter result (when used as filter)
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $user_message User message content
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_user_message_update($result, $flow_step_id, $user_message = null) {
        // Handle both action and filter usage
        if (is_string($result) && $user_message === null) {
            // Called as action - $result is actually $flow_step_id
            $user_message = $flow_step_id;
            $flow_step_id = $result;
        }
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('dm_log', 'error', 'Invalid flow_step_id format for user message update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];

        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow user message update failed - flows database service unavailable', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        // Get current flow
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow user message update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        // Get flow configuration
        $flow_config = $flow['flow_config'] ?? [];

        // Update user message in the specific flow step
        if (!isset($flow_config[$flow_step_id])) {
            do_action('dm_log', 'error', 'Flow user message update failed - flow step not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id,
                'existing_steps' => array_keys($flow_config)
            ]);
            return false;
        }

        // Update user message field
        $flow_config[$flow_step_id]['user_message'] = wp_unslash(sanitize_textarea_field($user_message));

        // Update flow with new configuration
        $success = apply_filters('dm_update_flow', false, $flow_id, [
            'flow_config' => wp_json_encode($flow_config)
        ]);

        if (!$success) {
            do_action('dm_log', 'error', 'Flow user message update failed - database update error', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle system prompt updates for AI pipeline steps.
     *
     * Updates the system_prompt field in pipeline step configuration
     * while preserving all other step configuration data.
     * 
     * Pipeline-scoped system prompts serve as templates that can be
     * inherited by flow instances while maintaining flow-specific customization.
     *
     * @param string|bool $result Previous filter result (when used as filter)
     * @param string $pipeline_step_id Pipeline step ID (UUID4)
     * @param string $system_prompt System prompt content
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_system_prompt_update($result, $pipeline_step_id, $system_prompt = null) {
        // Handle both action and filter usage
        if (is_string($result) && $system_prompt === null) {
            // Called as action - $result is actually $pipeline_step_id
            $system_prompt = $pipeline_step_id;
            $pipeline_step_id = $result;
        }
        // Get database services
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'System prompt update failed - pipelines database service unavailable', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        // Get step configuration using existing filter
        $step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);

        if (empty($step_config) || empty($step_config['pipeline_id'])) {
            do_action('dm_log', 'error', 'System prompt update failed - pipeline step not found', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        $pipeline_id = $step_config['pipeline_id'];

        // Get the complete pipeline data
        $target_pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$target_pipeline) {
            do_action('dm_log', 'error', 'System prompt update failed - pipeline not found', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        // Update step configuration
        $pipeline_config = $target_pipeline['pipeline_config'] ?? [];

        // Update system_prompt field
        if (!isset($pipeline_config[$pipeline_step_id])) {
            $pipeline_config[$pipeline_step_id] = [];
        }
        $pipeline_config[$pipeline_step_id]['system_prompt'] = wp_unslash($system_prompt);

        // Save updated pipeline configuration
        $success = $db_pipelines->update_pipeline($target_pipeline['pipeline_id'], [
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$success) {
            do_action('dm_log', 'error', 'System prompt update failed - database update error', [
                'pipeline_id' => $target_pipeline['pipeline_id'],
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle explicit job failure with cleanup and logging.
     * 
     * Simplified interface for failing jobs from any component.
     * Always marks job as failed, cleans up processed items, and logs failure details.
     *
     * @param int $job_id Job ID to mark as failed
     * @param string $reason Failure reason for logging
     * @param array $context_data Additional context data for debugging
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_job_failure($job_id, $reason, $context_data = []) {
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (!$db_jobs) {
            do_action('dm_log', 'error', 'Job failure failed - database service unavailable', [
                'job_id' => $job_id,
                'reason' => $reason,
                'context' => $context_data
            ]);
            return false;
        }
        
        // Always use complete_job method for failed status (sets completion timestamp)
        $success = $db_jobs->complete_job($job_id, 'failed');
        
        if (!$success) {
            do_action('dm_log', 'error', 'Failed to mark job as failed in database', [
                'job_id' => $job_id,
                'reason' => $reason
            ]);
            return false;
        }
        
        // Clean up processed items to allow retry (existing logic from handle_job_status_update)
        do_action('dm_delete', 'processed_items', $job_id, ['delete_by' => 'job_id']);
        
        // Conditional file cleanup based on settings
        $settings = dm_get_data_machine_settings();
        $cleanup_files = $settings['cleanup_job_data_on_failure'] ?? true;
        $files_cleaned = false;
        
        if ($cleanup_files) {
            $files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
            if ($files_repo) {
                $deleted_count = $files_repo->cleanup_job_data_packets($job_id);
                $files_cleaned = $deleted_count > 0;
            }
        }
        
        // Enhanced logging with failure details
        do_action('dm_log', 'error', 'Job marked as failed', [
            'job_id' => $job_id,
            'failure_reason' => $reason,
            'triggered_by' => 'dm_fail_job_action',
            'context_data' => $context_data,
            'processed_items_cleaned' => true,
            'files_cleanup_enabled' => $cleanup_files,
            'files_cleaned' => $files_cleaned
        ]);
        
        return true;
    }

    /**
     * Get schedule interval in seconds for Action Scheduler usage.
     *
     * Utility method for converting schedule interval keys to seconds
     * for direct Action Scheduler integration.
     *
     * @param string $interval Schedule interval key
     * @return int|false Interval in seconds or false if invalid
     * @since 1.0.0
     */
    private function get_schedule_interval_seconds($interval) {
        $intervals = apply_filters('dm_scheduler_intervals', []);
        return $intervals[$interval]['seconds'] ?? false;
    }
}