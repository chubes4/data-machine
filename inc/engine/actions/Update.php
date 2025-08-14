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
 * - flow_handler: Central flow handler management eliminating 50+ line update patterns
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
     * Register update action hooks using static method.
     *
     * Registers update action hooks that provide intelligent method selection
     * and consistent service discovery patterns.
     *
     * @since NEXT_VERSION
     */
    public static function register() {
        $instance = new self();
        
        // Central job status update hook - eliminates confusion about which method to use
        add_action('dm_update_job_status', [$instance, 'handle_job_status_update'], 10, 4);
        
        // Central flow scheduling hook - direct Action Scheduler integration
        add_action('dm_update_flow_schedule', [$instance, 'handle_flow_schedule_update'], 10, 4);
        
        // Central pipeline auto-save hook - eliminates database service discovery duplication
        add_action('dm_auto_save', [$instance, 'handle_pipeline_auto_save'], 10, 1);
        
        // Flow handler management action hook - eliminates 50+ line handler update patterns
        add_action('dm_update_flow_handler', [$instance, 'handle_flow_handler_update'], 10, 3);
        
        // Flow step synchronization action hook - unifies single and bulk step sync operations
        add_action('dm_sync_steps_to_flow', [$instance, 'handle_flow_steps_sync'], 10, 3);
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
            
            // Clean up processed items if job failed (allows retry without processed item conflicts)
            if ($new_status === 'failed' && $success) {
                do_action('dm_delete', 'processed_items', $job_id, ['delete_by' => 'job_id']);
            }
            
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
            
            // Direct Action Scheduler call using universal flow execution entry point
            if (function_exists('as_schedule_recurring_action')) {
                $action_id = as_schedule_recurring_action(
                    time(),
                    $interval_seconds,
                    'dm_run_flow_now',
                    [$flow_id],
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
            // Direct Action Scheduler deactivation using universal action
            if (function_exists('as_unschedule_action')) {
                $result = as_unschedule_action(
                    'dm_run_flow_now',
                    [$flow_id],
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
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            do_action('dm_log', 'error','Pipeline not found for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }
        
        // Always do full save - get current name and steps
        $pipeline_name = $pipeline['pipeline_name'];
        $pipeline_config = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        
        // Full pipeline save (always save everything)
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => json_encode($pipeline_config)
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
     * Handle flow handler updates with centralized database operations.
     *
     * Eliminates repetitive handler update patterns by providing centralized
     * handler addition/update functionality with consistent error handling.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $handler_slug Handler slug to add/update
     * @param array $handler_settings Handler configuration settings
     * @return bool Success status
     * @since NEXT_VERSION
     */
    public function handle_flow_handler_update($flow_step_id, $handler_slug, $handler_settings = []) {
        // Extract flow_id from flow_step_id using universal filter
        $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('dm_log', 'error', 'Invalid flow_step_id format for handler update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];
        
        // Get database service using filter-based discovery
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
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow handler update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        
        // Get current flow configuration using centralized filter
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
        // Initialize step configuration if it doesn't exist
        if (!isset($flow_config[$flow_step_id])) {
            $pipeline_step_id = implode('_', $parts); // Reconstruct pipeline_step_id
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
        $flow_config[$flow_step_id]['handler'] = [
            'handler_slug' => $handler_slug,
            'settings' => $handler_settings,
            'enabled' => true
        ];
        
        // Update flow with new configuration
        $success = $db_flows->update_flow($flow_id, [
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
        
        // Log the action
        $action_type = $handler_exists ? 'updated' : 'added';
        do_action('dm_log', 'debug', "Handler '{$handler_slug}' {$action_type} for flow_step_id {$flow_step_id} in flow {$flow_id}");
        
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
     * @since NEXT_VERSION
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
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow steps sync failed - flow not found', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }
        
        // Get current flow configuration
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
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
        $success = $db_flows->update_flow($flow_id, [
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
        
        // Log successful sync
        $context_desc = !empty($context['context']) ? $context['context'] : 'unknown';
        do_action('dm_log', 'debug', "Synced " . count($steps) . " step(s) to flow {$flow_id} via {$context_desc}");
        
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
     * @since NEXT_VERSION
     */
    private function get_schedule_interval_seconds($interval) {
        $intervals = apply_filters('dm_scheduler_intervals', []);
        return $intervals[$interval]['seconds'] ?? false;
    }
}