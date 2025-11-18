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
 * - datamachine_run_flow_now: Central flow execution trigger for manual/scheduled runs
 * - datamachine_execute_step: Core step execution engine for Action Scheduler pipeline processing
 * - datamachine_schedule_next_step: Central pipeline step scheduling eliminating Action Scheduler duplication
 * - datamachine_mark_item_processed: Universal processed item marking across all handlers
 * - datamachine_log: Central logging operations eliminating logger service discovery
 *
 * ORGANIZED ACTIONS (WordPress-native registration):
 * - datamachine_create, datamachine_delete: CRUD operations via organized action classes
 * - datamachine_update_job_status, datamachine_update_flow_schedule, datamachine_auto_save, datamachine_update_flow_handler, datamachine_sync_steps_to_flow: Update operations (Update.php)
 * - datamachine_fail_job: Explicit job failure with configurable cleanup (Update.php)
 * - External Plugin Actions: Plugins register custom actions using standard add_action() patterns
 *
 * EXTENSIBILITY EXAMPLES:
 * External plugins can add: datamachine_transform, datamachine_validate, datamachine_backup, datamachine_migrate, datamachine_sync, datamachine_analyze
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
require_once __DIR__ . '/Cache.php';

/**
 * Register core Data Machine action hooks.
 *
 * @since 0.1.0
 */
function datamachine_register_core_actions() {
    
    add_action('datamachine_mark_item_processed', function($flow_step_id, $source_type, $item_identifier, $job_id) {
        if (!isset($flow_step_id) || !isset($source_type) || !isset($item_identifier)) {
            do_action('datamachine_log', 'error', 'datamachine_mark_item_processed called with missing required parameters', [
                'flow_step_id' => $flow_step_id,
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier ?? '', 0, 50) . '...',
                'job_id' => $job_id,
                'parameter_provided' => func_num_args() >= 4
            ]);
            return;
        }

        if (empty($job_id) || !is_numeric($job_id) || $job_id <= 0) {
            do_action('datamachine_log', 'error', 'datamachine_mark_item_processed called without valid job_id', [
                'flow_step_id' => $flow_step_id,
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...',
                'job_id' => $job_id,
                'job_id_type' => gettype($job_id),
                'parameter_provided' => func_num_args() >= 4
            ]);
            return;
        }

        $processed_items = new \DataMachine\Core\Database\ProcessedItems\ProcessedItems();

        if (empty($job_id) || !is_numeric($job_id) || $job_id <= 0) {
            do_action('datamachine_log', 'error', 'datamachine_mark_item_processed called without valid job_id', [
                'flow_step_id' => $flow_step_id,
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 50) . '...',
                'job_id' => $job_id,
                'job_id_type' => gettype($job_id),
                'parameter_provided' => func_num_args() >= 4
            ]);
            return;
        }
        
        $success = $processed_items->add_processed_item($flow_step_id, $source_type, $item_identifier, $job_id);
        
        
        return $success;
    }, 10, 4);
    
    
    // Central logging hook - eliminates logger service discovery across all components  
    add_action('datamachine_log', function($operation, $param2 = null, $param3 = null, &$result = null) {
        $management_operations = ['clear_all', 'cleanup', 'set_level'];
        if (in_array($operation, $management_operations)) {
            switch ($operation) {
                case 'clear_all':
                    $result = datamachine_clear_log_files();
                    return $result;

                case 'cleanup':
                    $max_size_mb = $param2 ?? 10;
                    $max_age_days = $param3 ?? 30;
                    $result = datamachine_cleanup_log_files($max_size_mb, $max_age_days);
                    return $result;

                case 'set_level':
                    return datamachine_set_log_level($param2);
            }
        }

        $context = $param3 ?? [];

        $valid_levels = ['debug', 'error', 'warning', 'info', 'critical'];
        if (!in_array($operation, $valid_levels)) {
            return false;
        }

        $function_name = 'datamachine_log_' . $operation;
        if (function_exists($function_name)) {
            $function_name($param2, $context);
            return true;
        }

        return false;
    }, 10, 4);

    \DataMachine\Engine\Actions\Delete::register();
    \DataMachine\Engine\Actions\Update::register();
    \DataMachine\Engine\Actions\AutoSave::register();
    \DataMachine\Engine\Actions\ImportExport::register();
    \DataMachine\Engine\Actions\Cache::register();
    
}
