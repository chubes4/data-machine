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
 * Core Actions Registered:
 * - dm_run_flow_now: Central flow execution trigger for manual/scheduled runs
 * - dm_update_job_status: Intelligent job status updates with method selection
 * - dm_execute_step: Core step execution engine for Action Scheduler pipeline processing
 * - dm_pipeline_auto_save: Central pipeline auto-save operations
 * - dm_mark_item_processed: Universal processed item marking across all handlers
 * - dm_is_item_processed: Universal processed item checking across all handlers
 * - dm_log: Central logging operations eliminating logger service discovery
 *
 * ARCHITECTURAL BENEFITS:
 * - Eliminates code duplication across multiple trigger points
 * - Provides single source of truth for complex operations  
 * - Simplifies call sites from 40+ lines to single action calls
 * - Maintains filter-based architecture consistency
 *
 * @package DataMachine
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

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
 * - dm_execute_step($job_id, $execution_order, $pipeline_id, $flow_id, $pipeline_config, $previous_data_packets): Core step execution
 * - dm_pipeline_auto_save($pipeline_id): Central pipeline auto-save operations
 * - dm_mark_item_processed($flow_id, $source_type, $item_identifier): Universal processed item marking
 * - dm_is_item_processed($flow_id, $source_type, $item_identifier, &$result): Universal processed item checking
 * - dm_log($level, $message, $context): Central logging with automatic logger discovery and validation
 * 
 * Usage Examples:
 * do_action('dm_run_flow_now', $flow_id, 'run_now');
 * do_action('dm_update_job_status', $job_id, 'failed', 'complete');
 * do_action('dm_execute_step', $job_id, 0, $pipeline_id, $flow_id, $job_config, []);
 * do_action('dm_pipeline_auto_save', $pipeline_id);
 * do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
 * $is_processed = false; do_action('dm_is_item_processed', $flow_id, 'files', $file_path, &$is_processed);
 * do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
 *
 * @since 0.1.0
 */
function dm_register_core_actions() {
    
    // Central flow execution hook - "button press" trigger for all flow execution
    add_action('dm_run_flow_now', function($flow_id, $context = 'unknown') {
        // Get flow data to determine pipeline_id
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Flow execution failed - database service unavailable', ['flow_id' => $flow_id]);
            return false;
        }
        
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Flow execution failed - flow not found', ['flow_id' => $flow_id]);
            return false;
        }
        
        // Call JobCreator with discovered pipeline_id
        $job_creator = apply_filters('dm_get_job_creator', null);
        if (!$job_creator) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Flow execution failed - job creator unavailable', ['flow_id' => $flow_id]);
            return false;
        }
        
        $result = $job_creator->create_and_schedule_job(
            (int)$flow['pipeline_id'],
            $flow_id,
            $context
        );
        
        return $result['success'] ?? false;
    });
    
    // Central job status update hook - eliminates confusion about which method to use
    add_action('dm_update_job_status', function($job_id, $new_status, $context = 'update', $old_status = null) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        
        if (!$db_jobs) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Job status update failed - database service unavailable', [
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
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug('Job status updated via hook', [
                'job_id' => $job_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'context' => $context,
                'method_used' => $method_used,
                'success' => $success
            ]);
        }
        
        return $success;
    });
    
    // Core step execution hook - foundation of the entire pipeline execution system
    add_action( 'dm_execute_step', function( $job_id, $execution_order, $pipeline_id = null, $flow_id = null, $pipeline_config = null, $previous_data_packets = null ) {
        $logger = apply_filters('dm_get_logger', null);
        
        try {
            // Call static method directly - ProcessingOrchestrator::execute_step_callback is static
            $result = \DataMachine\Engine\ProcessingOrchestrator::execute_step_callback( $job_id, $execution_order, $pipeline_id, $flow_id, $pipeline_config, $previous_data_packets );
            
            $logger && $logger->debug('Action Scheduler hook executed step', [
                'job_id' => $job_id,
                'execution_order' => $execution_order,
                'result' => $result ? 'success' : 'failed'
            ]);
                
                // If step execution failed, mark job as failed
                if (!$result) {
                    do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');
                }
                
                return $result;
        } catch (Exception $e) {
            // Mark job as failed on any exception
            do_action('dm_update_job_status', $job_id, 'failed', 'exception_failure');
            
            $logger && $logger->error('Job failed due to exception in Action Scheduler hook', [
                'job_id' => $job_id,
                'execution_order' => $execution_order,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        } catch (Throwable $e) {
            // Catch any fatal errors or other throwables
            do_action('dm_update_job_status', $job_id, 'failed', 'fatal_error');
            
            $logger && $logger->error('Job failed due to fatal error in Action Scheduler hook', [
                'job_id' => $job_id,
                'execution_order' => $execution_order,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }, 10, 6 );
    
    // Central pipeline auto-save hook - eliminates database service discovery duplication
    add_action('dm_pipeline_auto_save', function($pipeline_id) {
        // Get logger for debugging auto-save operations
        $logger = apply_filters('dm_get_logger', null);
        
        // Get database service
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            $logger && $logger->error('Database service unavailable for auto-save', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }
        
        // Get current pipeline data
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            $logger && $logger->error('Pipeline not found for auto-save', [
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
            $logger && $logger->debug('Pipeline auto-saved successfully', [
                'pipeline_id' => $pipeline_id
            ]);
        } else {
            $logger && $logger->error('Pipeline auto-save failed', [
                'pipeline_id' => $pipeline_id
            ]);
        }
        
        return $success;
    }, 10, 1);
    
    // Central processed items marking hook - eliminates service discovery duplication across all handlers
    add_action('dm_mark_item_processed', function($flow_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('ProcessedItems service unavailable for item marking', [
                'flow_id' => $flow_id, 
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $success = $processed_items->add_processed_item($flow_id, $source_type, $item_identifier);
        
        // Centralized logging for processed item tracking
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug('Item marked as processed via hook', [
                'flow_id' => $flow_id,
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...',
                'success' => $success
            ]);
        }
        
        return $success;
    }, 10, 3);
    
    // Central logging hook - eliminates logger service discovery across all components  
    add_action('dm_log', function($level, $message, $context = []) {
        $logger = apply_filters('dm_get_logger', null);
        
        // Validate log level and logger availability
        if (!$logger || !method_exists($logger, $level)) {
            return false;
        }
        
        // Valid log levels for the 3-level system: debug, error, warning
        $valid_levels = ['debug', 'error', 'warning', 'info', 'critical'];
        if (!in_array($level, $valid_levels)) {
            return false;
        }
        
        // Execute logging method dynamically
        $logger->$level($message, $context);
        return true;
    }, 10, 3);
    
    // Central processed items checking hook - eliminates service discovery duplication across all handlers
    add_action('dm_is_item_processed', function($flow_id, $source_type, $item_identifier, &$result) {
        $all_databases = apply_filters('dm_get_database_services', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
            $result = false;
            do_action('dm_log', 'warning', 'ProcessedItems service unavailable for item check', [
                'flow_id' => $flow_id, 
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return;
        }
        
        $result = $processed_items->has_item_been_processed($flow_id, $source_type, $item_identifier);
        
        // Optional debug logging for processed item checks
        do_action('dm_log', 'debug', 'Processed item check via hook', [
            'flow_id' => $flow_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'is_processed' => $result
        ]);
    }, 10, 4);
    
}