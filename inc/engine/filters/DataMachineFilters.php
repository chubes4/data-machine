<?php
/**
 * Data Machine Engine - Backend Processing Filters
 *
 * BACKEND-ONLY ARCHITECTURE: Pure backend processing filters with zero admin/UI logic.
 * All admin/template/modal filters have been moved to Admin.php for clean separation.
 * 
 * Engine Bootstrap Functions (Backend Processing Only):
 * - dm_register_utility_filters(): Core backend processing filters
 * 
 * Database functions moved to Database.php:
 * - dm_register_database_service_system(), dm_register_database_filters()
 * 
 * Backend Filter Categories:
 * - Handler Services: Authentication, settings, and directive discovery  
 * - Processing Services: HTTP requests, scheduling
 * - Step Services: Step configuration and discovery (engine-level)
 * 
 * Specialized filter files:
 * - Database filters: inc/engine/filters/Database.php
 * - Admin/UI filters: inc/engine/filters/Admin.php
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register Import/Export filters
 */
function dm_register_importexport_filters() {
    
    /**
     * Register import/export modal
     */
    add_filter('dm_modals', function($modals) {
        $modals['import-export'] = [
            'title' => __('Import / Export Pipelines', 'data-machine'),
            'template' => 'modal/import-export',
            'size' => 'large'
        ];
        return $modals;
    }, 10, 1);
    
    /**
     * Importer service discovery (returns action handler instance)
     */
    add_filter('dm_importer', function($service) {
        if ($service === null) {
            require_once DATA_MACHINE_PATH . 'inc/engine/actions/ImportExport.php';
            return new \DataMachine\Engine\Actions\ImportExportActions();
        }
        return $service;
    }, 10, 1);
}

dm_register_importexport_filters();



/**
 * Register core backend processing filters.
 * 
 * BACKEND-ONLY FILTERS: Provides discovery infrastructure for backend processing only.
 * All admin/UI filters (templates, modals, pages) are in Admin.php.
 * Action hooks are in DataMachineActions.php for architectural separation.
 * 
 * Backend Filters Registered:
 * - dm_auth_providers: Authentication provider discovery
 * - dm_handler_settings: Handler configuration discovery  
 * - dm_handler_directives: AI directive discovery for handlers
 * - dm_oauth: Central OAuth operations for all handlers
 * - dm_request: WordPress HTTP request wrapper with logging
 * - dm_scheduler_intervals: Scheduler interval definitions
 * - dm_step_settings: Step configuration discovery (engine-level)
 * - dm_files_repository: Universal files repository discovery (allows custom storage)
 * 
 * Database filters moved to Database.php:
 * - dm_db, dm_get_*, dm_is_item_processed
 * 
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    // Pure discovery authentication system - consistent with handler discovery patterns
    // Usage: $all_auth = apply_filters('dm_auth_providers', []); $twitter_auth = $all_auth['twitter'] ?? null;
    add_filter('dm_auth_providers', function($providers) {
        // Auto-register hooks for all auth providers
        static $registered_hooks = [];
        
        foreach ($providers as $auth_instance) {
            if (is_object($auth_instance) && method_exists($auth_instance, 'register_hooks')) {
                $auth_class = get_class($auth_instance);
                
                // Only register hooks once per auth class
                if (!isset($registered_hooks[$auth_class])) {
                    $auth_instance->register_hooks();
                    $registered_hooks[$auth_class] = true;
                }
            }
        }
        
        return $providers; // Return collection unchanged - components self-register via this same filter
    }, 5, 1);
    
    // Pure discovery handler settings system - consistent with all other discovery filters
    // Usage: $all_settings = apply_filters('dm_handler_settings', []); $settings = $all_settings[$handler_slug] ?? null;
    add_filter('dm_handler_settings', function($all_settings) {
        // Components self-register via *Filters.php files following established patterns
        // Bootstrap provides only pure filter hook - components add their own logic
        return $all_settings; // Components self-register via filters
    }, 10, 1);
    
    // Pure discovery handler directives system - cross-component AI directive discovery
    // Usage: $all_directives = apply_filters('dm_handler_directives', []); $directive = $all_directives[$handler_slug] ?? '';
    add_filter('dm_handler_directives', '__return_empty_array');
    
    
    // WordPress HTTP request wrapper system - centralized HTTP handling with context logging
    // Usage: $result = apply_filters('dm_request', null, 'POST', $url, $args, 'Context Description');
    add_filter('dm_request', function($default, $method, $url, $args, $context) {
        // Input validation
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            do_action('dm_log', 'error', 'HTTP Request: Invalid method', ['method' => $method, 'context' => $context]);
            return ['success' => false, 'error' => __('Invalid HTTP method', 'data-machine')];
        }

        // Default args with Data Machine user agent and timeout
        $args = wp_parse_args($args, [
            'user-agent' => sprintf('DataMachine/%s (+%s)', 
                defined('DATA_MACHINE_VERSION') ? DATA_MACHINE_VERSION : '1.0', 
                home_url()),
            'timeout' => 60  // 60-second timeout for external API calls
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
            
            do_action('dm_log', 'error', 'HTTP Request: Connection failed', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ]);
            
            return ['success' => false, 'error' => $error_message];
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

            do_action('dm_log', 'error', 'HTTP Request: Error response', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);
            
            return ['success' => false, 'error' => $error_message];
        }

        // Success - return structured response
        do_action('dm_log', 'debug', "HTTP Request: Successful {$method} to {$context}", [
            'url' => $url,
            'method' => $method,
            'status_code' => $status_code,
            'content_length' => strlen($body)
        ]);

        return [
            'success' => true,
            'data' => $body,
            'status_code' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'response' => $response
        ];
    }, 10, 5);

    // Extensible scheduler intervals system - allows external plugins to add custom intervals
    // Usage: $all_intervals = apply_filters('dm_scheduler_intervals', []); $hourly = $all_intervals['hourly'] ?? null;
    add_filter('dm_scheduler_intervals', function($intervals) {
        return [
            'every_5_minutes' => [
                'label' => __('Every 5 Minutes', 'data-machine'),
                'seconds' => 300 // 5 * 60
            ],
            'hourly' => [
                'label' => __('Hourly', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS
            ],
            'every_2_hours' => [
                'label' => __('Every 2 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 2
            ],
            'every_4_hours' => [
                'label' => __('Every 4 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 4
            ],
            'qtrdaily' => [
                'label' => __('Every 6 Hours', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 6
            ],
            'twicedaily' => [
                'label' => __('Twice Daily', 'data-machine'),
                'seconds' => HOUR_IN_SECONDS * 12
            ],
            'daily' => [
                'label' => __('Daily', 'data-machine'),
                'seconds' => DAY_IN_SECONDS
            ],
            'weekly' => [
                'label' => __('Weekly', 'data-machine'),
                'seconds' => WEEK_IN_SECONDS
            ]
        ];
    }, 10);
    
    
    // Step configurations collection filter - infrastructure hook for step components
    // Usage: $all_step_settings = apply_filters('dm_step_settings', []); $settings = $all_step_settings[$step_type] ?? null;
    add_filter('dm_step_settings', function($configs) {
        // Engine provides the filter hook infrastructure
        // Step components self-register their configuration capabilities via this same filter
        return $configs;
    }, 5);

    // Universal files repository discovery system - allows external plugins to implement custom storage
    // Usage: $all_repositories = apply_filters('dm_files_repository', []); $repository = $all_repositories['files'] ?? null;
    add_filter('dm_files_repository', function($repositories) {
        // Engine provides the filter hook infrastructure
        // File storage components self-register their repository implementations via this same filter
        return $repositories;
    }, 5);
    
    // Central Flow Step ID Generation Utility
    // Provides consistent flow_step_id generation across all system components
    // Flow step IDs use the pattern: {pipeline_step_id}_{flow_id}
    // Usage: $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
    add_filter('dm_generate_flow_step_id', function($existing_id, $pipeline_step_id, $flow_id) {
        // Validate required parameters
        if (empty($pipeline_step_id) || empty($flow_id)) {
            do_action('dm_log', 'error', 'Invalid flow step ID generation parameters', [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id
            ]);
            return '';
        }
        
        // Generate consistent flow_step_id using established pattern
        return $pipeline_step_id . '_' . $flow_id;
    }, 10, 3);
    
    // Central OAuth operations filter - eliminates handler-specific OAuth code duplication
    // Account data: apply_filters('dm_oauth', [], 'retrieve', 'twitter'); apply_filters('dm_oauth', null, 'store', 'twitter', $data);
    // Config data: apply_filters('dm_oauth', [], 'get_config', 'twitter'); apply_filters('dm_oauth', null, 'store_config', 'twitter', $config);
    add_filter('dm_oauth', function($result, $operation, $handler, $data = null) {
        switch ($operation) {
            case 'store':
                // Store account data (access tokens, etc.)
                $current = get_option("{$handler}_auth_data", []);
                $current['account'] = $data;
                return update_option("{$handler}_auth_data", $current);
            case 'retrieve':
                // Retrieve account data
                $auth_data = get_option("{$handler}_auth_data", []);
                return $auth_data['account'] ?? [];
            case 'clear':
                // Clear account data only
                $current = get_option("{$handler}_auth_data", []);
                unset($current['account']);
                return update_option("{$handler}_auth_data", $current);
            case 'store_config':
                // Store configuration data (API keys, client secrets, etc.)
                $current = get_option("{$handler}_auth_data", []);
                $current['config'] = $data;
                return update_option("{$handler}_auth_data", $current);
            case 'get_config':
                // Retrieve configuration data
                $auth_data = get_option("{$handler}_auth_data", []);
                return $auth_data['config'] ?? [];
            case 'clear_config':
                // Clear configuration data only
                $current = get_option("{$handler}_auth_data", []);
                unset($current['config']);
                return update_option("{$handler}_auth_data", $current);
            case 'clear_all':
                // Clear both config and account data
                return delete_option("{$handler}_auth_data");
            default:
                do_action('dm_log', 'error', 'Invalid OAuth operation', [
                    'operation' => $operation,
                    'handler' => $handler
                ]);
                return false;
        }
    }, 10, 4);
    
    /**
     * AI API Error Logging Hook
     * Listens for AI HTTP Client errors and logs them via dm_log
     */
    add_action('ai_api_error', function($error_data) {
        // Log AI API errors through Data Machine's logging system
        do_action('dm_log', 'error', $error_data['message'], $error_data);
    });
    
    
}


