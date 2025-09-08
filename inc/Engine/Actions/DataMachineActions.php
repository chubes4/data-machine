<?php
/**
 * Data Machine Core Action Hooks
 *
 * Central registration for "button press" style action hooks that simplify
 * repetitive behaviors throughout the Data Machine plugin. These actions
 * provide consistent trigger points for common operations.
 *
 * ACTION HOOK PATTERNS:
 * - "Button Press" Style: Actions that multiple pathways can trigger
 * - Centralized Logic: Complex operations consolidated into single handlers
 * - Consistent Error Handling: Unified logging and validation patterns
 * - Service Discovery: Filter-based service access for architectural consistency
 *
 * Core Workflow and Utility Actions Registered:
 * - dm_run_flow_now: Central flow execution trigger for manual/scheduled runs
 * - dm_execute_step: Core step execution engine for Action Scheduler pipeline processing
 * - dm_schedule_next_step: Central pipeline step scheduling eliminating Action Scheduler duplication
 * - dm_mark_item_processed: Universal processed item marking across all handlers
 * - dm_log: Central logging operations eliminating logger service discovery
 *
 * ORGANIZED ACTIONS (WordPress-native registration):
 * - dm_create, dm_delete: CRUD operations via organized action classes
 * - dm_update_job_status, dm_update_flow_schedule, dm_auto_save, dm_update_flow_handler, dm_sync_steps_to_flow: Update operations (Update.php)
 * - dm_fail_job: Explicit job failure with configurable cleanup (Update.php)
 * - External Plugin Actions: Plugins register custom actions using standard add_action() patterns
 *
 * EXTENSIBILITY EXAMPLES:
 * External plugins can add: dm_transform, dm_validate, dm_backup, dm_migrate, dm_sync, dm_analyze
 *
 * ARCHITECTURAL BENEFITS:
 * - WordPress-native action registration: Direct add_action() calls, zero overhead
 * - Clean code organization: Separate files for Create, Delete, and Update operations
 * - External plugin extensibility: Standard WordPress action registration patterns
 * - Eliminates code duplication across multiple trigger points
 * - Provides single source of truth for complex operations  
 * - Simplifies call sites from 40+ lines to single action calls
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include organized action classes
require_once __DIR__ . '/Delete.php';
require_once __DIR__ . '/ImportExport.php';
require_once __DIR__ . '/Update.php';
require_once __DIR__ . '/Engine.php';

/**
 * Register core Data Machine action hooks.
 * 
 * Registers "button press" style action hooks that centralize common operations
 * and eliminate code duplication throughout the plugin. These actions follow
 * the established filter-based service discovery patterns.
 * 
 * Actions registered:
 * - dm_run_flow_now($flow_id, $context): Central flow execution trigger
 * - dm_update_job_status($job_id, $new_status, $context, $old_status): Intelligent status updates
 * - dm_fail_job($job_id, $reason, $context_data): Explicit job failure with configurable cleanup
 * - dm_execute_step($job_id, $execution_order, $pipeline_id, $flow_id, $pipeline_config, $previous_datas): Core step execution
 * - dm_auto_save($pipeline_id): Central pipeline auto-save operations
 * - dm_mark_item_processed($flow_step_id, $source_type, $item_identifier, $job_id): Universal processed item marking
 * - dm_update_flow_handler($flow_step_id, $handler_slug, $handler_settings): Central flow handler management (Update.php)
 * - dm_update_flow_schedule($flow_id, $schedule_interval, $old_interval): Engine-level flow scheduling
 * - dm_schedule_next_step($job_id, $execution_order, $pipeline_id, $flow_id, $job_config, $data): Central step scheduling
 * - dm_log($level, $message, $context): Central logging with automatic logger discovery and validation
 * 
 * Usage Examples:
 * do_action('dm_run_flow_now', $flow_id, 'run_now');
 * do_action('dm_update_job_status', $job_id, 'completed', 'complete');
 * do_action('dm_fail_job', $job_id, 'handler_failed', ['error' => 'Connection timeout']);
 * do_action('dm_execute_step', $job_id, 0, $pipeline_id, $flow_id, $job_config, []);
 * do_action('dm_auto_save', $pipeline_id);
 * do_action('dm_mark_item_processed', $flow_step_id, 'rss', $item_guid, $job_id);
 * do_action('dm_update_flow_handler', $flow_step_id, 'twitter', $handler_settings);
 * do_action('dm_update_flow_schedule', $flow_id, 'hourly', 'manual');
 * do_action('dm_sync_steps_to_flow', $flow_id, [$step_data], ['context' => 'add_step']);
 * do_action('dm_schedule_next_step', $job_id, 1, $pipeline_id, $flow_id, $job_config, $data);
 * do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
 *
 * @since 0.1.0
 */
function dm_register_core_actions() {
    
    // Central processed items marking hook - eliminates service discovery duplication across all handlers
    add_action('dm_mark_item_processed', function($flow_step_id, $source_type, $item_identifier, $job_id) {
        
        // Validate required job_id
        if (empty($job_id) || !is_numeric($job_id) || $job_id <= 0) {
            do_action('dm_log', 'error', 'dm_mark_item_processed called without valid job_id', [
                'flow_step_id' => $flow_step_id,
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...',
                'job_id' => $job_id,
                'job_id_type' => gettype($job_id),
                'parameter_provided' => func_num_args() >= 4
            ]);
            return;
        }
        
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
                do_action('dm_log', 'error', 'ProcessedItems service unavailable for item marking', [
                'flow_step_id' => $flow_step_id, 
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...',
                'job_id' => $job_id
            ]);
            return false;
        }
        
        $success = $processed_items->add_processed_item($flow_step_id, $source_type, $item_identifier, $job_id);
        
        
        return $success;
    }, 10, 4);
    
    
    // Central logging hook - eliminates logger service discovery across all components  
    add_action('dm_log', function($operation, $param2 = null, $param3 = null, &$result = null) {
        // Handle management operations that modify state
        $management_operations = ['clear_all', 'cleanup', 'set_level'];
        if (in_array($operation, $management_operations)) {
            switch ($operation) {
                case 'clear_all':
                    $result = dm_clear_log_files();
                    return $result;
                    
                case 'cleanup':
                    $max_size_mb = $param2 ?? 10;
                    $max_age_days = $param3 ?? 30;
                    $result = dm_cleanup_log_files($max_size_mb, $max_age_days);
                    return $result;
                    
                case 'set_level':
                    return dm_set_log_level($param2);
            }
        }
        
        // Handle regular logging operations  
        $context = $param3 ?? [];
        
        // Valid log levels for the 3-level system: debug, error, warning
        $valid_levels = ['debug', 'error', 'warning', 'info', 'critical'];
        if (!in_array($operation, $valid_levels)) {
            return false;
        }
        
        // Execute logging function dynamically
        $function_name = 'dm_log_' . $operation;
        if (function_exists($function_name)) {
            $function_name($param2, $context);
            return true;
        }
        
        return false;
    }, 10, 4);
    
    
    
    // Register core pipeline execution engine
    dm_register_execution_engine();
    
    // Register organized action classes - static WordPress-native pattern
    \DataMachine\Engine\Actions\Delete::register();
    \DataMachine\Engine\Actions\Update::register();
    \DataMachine\Engine\Actions\ImportExport::register();
    
}

