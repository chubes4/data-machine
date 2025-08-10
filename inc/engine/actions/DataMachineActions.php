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
 * - dm_ajax_route: Universal AJAX handler routing eliminating 132 lines of duplication
 * - dm_send_request: Universal HTTP request handling with comprehensive error handling
 *
 * ORGANIZED ACTIONS (WordPress-native registration):
 * - dm_create, dm_delete: CRUD operations via organized action classes (Create.php, Delete.php)
 * - dm_update_job_status, dm_update_flow_schedule, dm_auto_save: Update operations (Update.php)
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
require_once __DIR__ . '/Update.php';

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
 * - dm_auto_save($pipeline_id): Central pipeline auto-save operations
 * - dm_mark_item_processed($flow_id, $source_type, $item_identifier): Universal processed item marking
 * - dm_update_flow_schedule($flow_id, $schedule_status, $schedule_interval, $old_status): Engine-level flow scheduling
 * - dm_schedule_next_step($job_id, $execution_order, $pipeline_id, $flow_id, $job_config, $data_packet): Central step scheduling
 * - dm_log($level, $message, $context): Central logging with automatic logger discovery and validation
 * 
 * Usage Examples:
 * do_action('dm_run_flow_now', $flow_id, 'run_now');
 * do_action('dm_update_job_status', $job_id, 'failed', 'complete');
 * do_action('dm_execute_step', $job_id, 0, $pipeline_id, $flow_id, $job_config, []);
 * do_action('dm_auto_save', $pipeline_id);
 * do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
 * do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly', 'inactive');
 * do_action('dm_schedule_next_step', $job_id, 1, $pipeline_id, $flow_id, $job_config, $data_packet);
 * do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
 * do_action('dm_ajax_route', 'dm_add_step', 'page');
 *
 * @since 0.1.0
 */
function dm_register_core_actions() {
    
    // Central flow execution hook - "button press" trigger for all flow execution
    add_action('dm_run_flow_now', function($flow_id) {
        // Get flow data to determine pipeline_id
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_flows) {
            do_action('dm_log', 'error', 'Flow execution failed - database service unavailable', ['flow_id' => $flow_id]);
            return false;
        }
        
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('dm_log', 'error', 'Flow execution failed - flow not found', ['flow_id' => $flow_id]);
            return false;
        }
        
        // Use organized dm_create action for consistent job creation
        do_action('dm_create', 'job', [
            'pipeline_id' => (int)$flow['pipeline_id'],
            'flow_id' => $flow_id
        ]);
        
        return true;
    });
    
    
    // Core step execution hook - ultra-simple flow_step_id based execution
    add_action( 'dm_execute_step', function( $flow_step_id, $data_packet = null ) {
        try {
            // Call static method with ultra-simple signature
            $result = \DataMachine\Engine\ProcessingOrchestrator::execute_step_callback( $flow_step_id, $data_packet );
            
            do_action('dm_log', 'debug', 'Action Scheduler hook executed step', [
                'flow_step_id' => $flow_step_id,
                'result' => $result ? 'success' : 'failed'
            ]);
                
                // If step execution failed, mark job as failed (extract flow_id for job tracking)
                if (!$result) {
                    $parts = apply_filters('dm_split_flow_step_id', [], $flow_step_id);
                    $flow_id = $parts['flow_id'] ?? null;
                    if ($flow_id) {
                        // Find job_id from flow_id - we need this for job status updates
                        $all_databases = apply_filters('dm_db', []);
                        $db_jobs = $all_databases['jobs'] ?? null;
                        if ($db_jobs) {
                            $jobs = $db_jobs->get_active_jobs_for_flow($flow_id);
                            foreach ($jobs as $job) {
                                do_action('dm_update_job_status', $job['job_id'], 'failed', 'step_execution_failure');
                            }
                        }
                    }
                }
                
                return $result;
        } catch (Exception $e) {
            do_action('dm_log', 'error', 'Job failed due to exception in Action Scheduler hook', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        } catch (Throwable $e) {
            do_action('dm_log', 'error','Job failed due to fatal error in Action Scheduler hook', [
                'flow_step_id' => $flow_step_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }, 10, 2 );
    
    
    // Central processed items marking hook - eliminates service discovery duplication across all handlers
    add_action('dm_mark_item_processed', function($flow_id, $source_type, $item_identifier) {
        $all_databases = apply_filters('dm_db', []);
        $processed_items = $all_databases['processed_items'] ?? null;
        
        if (!$processed_items) {
                do_action('dm_log', 'error','ProcessedItems service unavailable for item marking', [
                'flow_id' => $flow_id, 
                'source_type' => $source_type,
                'identifier' => substr($item_identifier, 0, 50) . '...'
            ]);
            return false;
        }
        
        $success = $processed_items->add_processed_item($flow_id, $source_type, $item_identifier);
        
        // Centralized logging for processed item tracking
        do_action('dm_log', 'debug', 'Item marked as processed via hook', [
            'flow_id' => $flow_id,
            'source_type' => $source_type,
            'identifier' => substr($item_identifier, 0, 50) . '...',
            'success' => $success
        ]);
        
        return $success;
    }, 10, 3);
    
    
    // Central step scheduling hook - ultra-simple flow_step_id based scheduling
    add_action('dm_schedule_next_step', function($flow_step_id, $data_packet = []) {
        if (!function_exists('as_schedule_single_action')) {
            do_action('dm_log', 'error', 'Action Scheduler not available for step scheduling', [
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        
        // Direct Action Scheduler call with ultra-simple parameters
        $action_id = as_schedule_single_action(
            time(), // Immediate execution
            'dm_execute_step',
            [
                'flow_step_id' => $flow_step_id,
                'data_packet' => $data_packet
            ],
            'data-machine'
        );
        
        do_action('dm_log', 'debug', 'Next step scheduled via centralized action hook', [
            'flow_step_id' => $flow_step_id,
            'action_id' => $action_id,
            'success' => ($action_id !== false)
        ]);
        
        return $action_id !== false;
    }, 10, 2);
    
    // Central logging hook - eliminates logger service discovery across all components  
    add_action('dm_log', function($level, $message, $context = []) {
        // Get logger instance for actual logging operations
        $logger = new \DataMachine\Engine\Logger();
        
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
    
    // Universal AJAX routing action hook - eliminates 132 lines of duplication in PipelinesFilters.php
    add_action('dm_ajax_route', function($ajax_action, $handler_type = 'page') {
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
    
    // Universal HTTP request action hook
    // Provides direct access to WordPress HTTP API with standardized error handling and logging
    add_action('dm_send_request', function($method, $url, $args, $context, &$result) {
        // Input validation
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            $result = ['success' => false, 'error' => __('Invalid HTTP method', 'data-machine')];
            do_action('dm_log', 'error', 'HTTP Request: Invalid method', ['method' => $method]);
            return;
        }

        // Default args with Data Machine user agent
        $args = wp_parse_args($args, [
            'user-agent' => sprintf('DataMachine/%s (+%s)', 
                defined('DATA_MACHINE_VERSION') ? DATA_MACHINE_VERSION : '1.0', 
                home_url())
        ]);

        // Set method for non-GET requests
        if ($method !== 'GET') {
            $args['method'] = $method;
        }

        // Log request initiation
        do_action('dm_log', 'debug', "HTTP Request: {$method} to {$context}", [
            'url' => $url, 
            'method' => $method
        ]);

        // Make the request using appropriate WordPress function
        $response = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_request($url, $args);

        // Handle WordPress HTTP errors (network issues, timeouts, etc.)
        if (is_wp_error($response)) {
            $error_message = sprintf(
                /* translators: %1$s: context/service name, %2$s: error message */
                __('Failed to connect to %1$s: %2$s', 'data-machine'),
                $context,
                $response->get_error_message()
            );
            
            $result = ['success' => false, 'error' => $error_message];
            
            do_action('dm_log', 'error', 'HTTP Request: Connection failed', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ]);
            return;
        }

        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Determine success status codes based on HTTP method
        $success_codes = [];
        switch ($method) {
            case 'GET':
                $success_codes = [200];
                break;
            case 'POST':
                $success_codes = [200, 201, 202];
                break;
            case 'PUT':
                $success_codes = [200, 201, 204];
                break;
            case 'PATCH':
                $success_codes = [200, 204];
                break;
            case 'DELETE':
                $success_codes = [200, 202, 204];
                break;
        }
        
        if (!in_array($status_code, $success_codes)) {
            $error_message = sprintf(
                /* translators: %1$s: context/service name, %2$s: HTTP method, %3$d: HTTP status code */
                __('%1$s %2$s returned HTTP %3$d', 'data-machine'),
                $context,
                $method,
                $status_code
            );

            // Try to extract error details from response body
            $error_details = null;
            if (!empty($body)) {
                // Try to parse as JSON first for structured error messages
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $error_keys = ['message', 'error', 'error_description', 'detail'];
                    foreach ($error_keys as $key) {
                        if (isset($decoded[$key]) && is_string($decoded[$key])) {
                            $error_details = $decoded[$key];
                            break;
                        }
                    }
                }
                // If not JSON, use first line of body
                if (!$error_details) {
                    $first_line = strtok($body, "\n");
                    $error_details = strlen($first_line) > 100 ? substr($first_line, 0, 97) . '...' : $first_line;
                }
            }
            
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            $result = ['success' => false, 'error' => $error_message];

            do_action('dm_log', 'error', 'HTTP Request: Error response', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);
            return;
        }

        // Success - return structured response
        $result = [
            'success' => true,
            'data' => [
                'body' => $body,
                'status_code' => $status_code,
                'headers' => wp_remote_retrieve_headers($response),
                'response' => $response
            ]
        ];

        do_action('dm_log', 'debug', "HTTP Request: Successful {$method} to {$context}", [
            'url' => $url,
            'method' => $method,
            'status_code' => $status_code,
            'content_length' => strlen($body)
        ]);
    }, 10, 5);
    
    // Register organized action classes - simple WordPress-native pattern
    $create_actions = new DataMachine_Create_Actions();
    $create_actions->register_actions();
    
    $delete_actions = new DataMachine_Delete_Actions();
    $delete_actions->register_actions();
    
    $update_actions = new DataMachine_Update_Actions();
    $update_actions->register_actions();
    
}














