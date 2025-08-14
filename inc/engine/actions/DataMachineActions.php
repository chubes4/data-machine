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
 * - dm_ajax_route: Universal AJAX handler routing eliminating duplicated security validation
 *
 * ORGANIZED ACTIONS (WordPress-native registration):
 * - dm_create, dm_delete: CRUD operations via organized action classes (Create.php, Delete.php)
 * - dm_update_job_status, dm_update_flow_schedule, dm_auto_save, dm_update_flow_handler, dm_sync_steps_to_flow: Update operations (Update.php)
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
require_once __DIR__ . '/Create.php';
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
 * - dm_execute_step($job_id, $execution_order, $pipeline_id, $flow_id, $pipeline_config, $previous_datas): Core step execution
 * - dm_auto_save($pipeline_id): Central pipeline auto-save operations
 * - dm_mark_item_processed($flow_id, $source_type, $item_identifier): Universal processed item marking
 * - dm_update_flow_handler($flow_step_id, $handler_slug, $handler_settings): Central flow handler management (Update.php)
 * - dm_update_flow_schedule($flow_id, $schedule_status, $schedule_interval, $old_status): Engine-level flow scheduling
 * - dm_schedule_next_step($job_id, $execution_order, $pipeline_id, $flow_id, $job_config, $data): Central step scheduling
 * - dm_log($level, $message, $context): Central logging with automatic logger discovery and validation
 * 
 * Usage Examples:
 * do_action('dm_run_flow_now', $flow_id, 'run_now');
 * do_action('dm_update_job_status', $job_id, 'failed', 'complete');
 * do_action('dm_execute_step', $job_id, 0, $pipeline_id, $flow_id, $job_config, []);
 * do_action('dm_auto_save', $pipeline_id);
 * do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
 * do_action('dm_update_flow_handler', $flow_step_id, 'twitter', $handler_settings);
 * do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly', 'inactive');
 * do_action('dm_sync_steps_to_flow', $flow_id, [$step_data], ['context' => 'add_step']);
 * do_action('dm_schedule_next_step', $job_id, 1, $pipeline_id, $flow_id, $job_config, $data);
 * do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
 * do_action('dm_ajax_route', 'dm_add_step', 'page');
 *
 * @since 0.1.0
 */
function dm_register_core_actions() {
    
    // Central processed items marking hook - eliminates service discovery duplication across all handlers
    add_action('dm_mark_item_processed', function($flow_step_id, $source_type, $item_identifier, $job_id = 0) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
                do_action('dm_log', 'error','ProcessedItems service unavailable for item marking', [
                'flow_step_id' => $flow_step_id, 
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...',
                'job_id' => $job_id
            ]);
            return false;
        }
        
        $success = $processed_items->add_processed_item($flow_step_id, $source_type, $item_identifier, $job_id);
        
        // Centralized logging for processed item tracking
        do_action('dm_log', 'debug', 'Item marked as processed via hook', [
            'flow_step_id' => $flow_step_id,
            'job_id' => $job_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'success' => $success
        ]);
        
        return $success;
    }, 10, 3);
    
    
    // Central logging hook - eliminates logger service discovery across all components  
    add_action('dm_log', function($operation, $param2 = null, $param3 = null, &$result = null) {
        // Handle management operations that modify state
        $management_operations = ['clear_all', 'cleanup', 'set_level'];
        if (in_array($operation, $management_operations)) {
            switch ($operation) {
                case 'clear_all':
                    error_log('DM Debug: DataMachineActions clear_all case reached');
                    $result = dm_clear_log_files();
                    error_log('DM Debug: dm_clear_log_files() returned: ' . ($result ? 'TRUE' : 'FALSE'));
                    return $result;
                    
                case 'cleanup':
                    $max_size_mb = $param2 ?? 10;
                    $max_age_days = $param3 ?? 30;
                    $result = dm_cleanup_log_files($max_size_mb, $max_age_days);
                    return $result;
                    
                case 'set_level':
                    $new_level = $param2;
                    $result = dm_set_log_level($new_level);
                    return $result;
            }
        }
        
        // Handle regular logging operations (backward compatibility)
        $level = $operation;
        $message = $param2;
        $context = $param3 ?? [];
        
        // Valid log levels for the 3-level system: debug, error, warning
        $valid_levels = ['debug', 'error', 'warning', 'info', 'critical'];
        if (!in_array($level, $valid_levels)) {
            return false;
        }
        
        // Execute logging function dynamically
        $function_name = 'dm_log_' . $level;
        if (function_exists($function_name)) {
            $function_name($message, $context);
            return true;
        }
        
        return false;
    }, 10, 4);
    
    // Universal AJAX routing action hook - eliminates 132 lines of duplication in PipelinesFilters.php
    add_action('dm_ajax_route', function($ajax_action, $handler_type = 'page') {
        // WordPress-native security: capability check + nonce verification
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed: insufficient permissions.', 'data-machine')]);
            return;
        }
        
        // Verify nonce with standard 'dm_pipeline_ajax' action
        $nonce = wp_unslash($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'dm_pipeline_ajax')) {
            wp_send_json_error(['message' => __('Security check failed: invalid nonce.', 'data-machine')]);
            return;
        }
        
        $all_pages = apply_filters('dm_admin_pages', []);
        $ajax_handlers = $all_pages['pipelines']['ajax_handlers'] ?? [];
        $handler = $ajax_handlers[$handler_type] ?? null;
        
        // Convert AJAX action to method name: dm_add_step → handle_add_step
        $method_name = 'handle_' . str_replace('dm_', '', $ajax_action);
        
        if ($handler && method_exists($handler, $method_name)) {
            $handler->$method_name();
        } else {
            wp_send_json_error(['message' => __('Handler not available', 'data-machine')]);
        }
    }, 10, 2);
    
    
    // Register core pipeline execution engine
    dm_register_execution_engine();
    
    // Register organized action classes - static WordPress-native pattern
    DataMachine_Create_Actions::register();
    DataMachine_Delete_Actions::register();
    DataMachine_Update_Actions::register();
    DataMachine_ImportExport_Actions::register();
    
}














