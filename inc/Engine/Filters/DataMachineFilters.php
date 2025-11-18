<?php
/**
 * Backend processing filters for Data Machine engine.
 *
 * Core filter registration for handler services, HTTP requests, scheduling,
 * and step configuration discovery.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

if (!defined('WPINC')) {
    die;
}

function datamachine_register_importexport_filters() {
    

    
    add_filter('datamachine_importer', function($service) {
        if ($service === null) {
            require_once DATAMACHINE_PATH . 'inc/Engine/Actions/ImportExport.php';
            return new \DataMachine\Engine\Actions\ImportExport();
        }
        return $service;
    }, 10, 1);
}

datamachine_register_importexport_filters();

/**
 * Register backend processing filters for engine operations.
 *
 * Registers filters for service discovery, HTTP requests, scheduling,
 * and step configuration. Does not handle UI/admin logic.
 *
 * @since 0.1.0
 */
function datamachine_register_utility_filters() {
    
    add_filter('datamachine_auth_providers', function($providers) {
        return $providers;
    }, 5, 1);
    
    
    add_filter('datamachine_request', function($default, $method, $url, $args, $context) {
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            do_action('datamachine_log', 'error', 'HTTP Request: Invalid method', ['method' => $method, 'context' => $context]);
            return ['success' => false, 'error' => __('Invalid HTTP method', 'datamachine')];
        }

        $args = wp_parse_args($args, [
            'user-agent' => sprintf('DataMachine/%s (+%s)',
                defined('DATAMACHINE_VERSION') ? DATAMACHINE_VERSION : '1.0',
                home_url()),
            'timeout' => 120
        ]);

        if ($method !== 'GET') {
            $args['method'] = $method;
        }

        $response = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = sprintf(
                /* translators: %1$s: Service/context name, %2$s: Error message */
                __('Failed to connect to %1$s: %2$s', 'datamachine'),
                $context,
                $response->get_error_message()
            );
            
            do_action('datamachine_log', 'error', 'HTTP Request: Connection failed', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ]);
            
            return ['success' => false, 'error' => $error_message];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
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
                /* translators: %1$s: Service/context name, %2$s: HTTP method, %3$d: HTTP status code */
                __('%1$s %2$s returned HTTP %3$d', 'datamachine'),
                $context,
                $method,
                $status_code
            );
            $error_details = null;
            if (!empty($body)) {
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
                if (!$error_details) {
                    $first_line = strtok($body, "\n");
                    $error_details = strlen($first_line) > 100 ? substr($first_line, 0, 97) . '...' : $first_line;
                }
            }
            
            if ($error_details) {
                $error_message .= ': ' . $error_details;
            }

            do_action('datamachine_log', 'error', 'HTTP Request: Error response', [
                'context' => $context,
                'url' => $url,
                'method' => $method,
                'status_code' => $status_code,
                'body_preview' => substr($body, 0, 200)
            ]);
            
            return ['success' => false, 'error' => $error_message];
        }


        return [
            'success' => true,
            'data' => $body,
            'status_code' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'response' => $response
        ];
    }, 10, 5);
    add_filter('datamachine_scheduler_intervals', function($intervals) {
        return [
            'every_5_minutes' => [
                'label' => __('Every 5 Minutes', 'datamachine'),
                'seconds' => 300 // 5 * 60
            ],
            'hourly' => [
                'label' => __('Hourly', 'datamachine'),
                'seconds' => HOUR_IN_SECONDS
            ],
            'every_2_hours' => [
                'label' => __('Every 2 Hours', 'datamachine'),
                'seconds' => HOUR_IN_SECONDS * 2
            ],
            'every_4_hours' => [
                'label' => __('Every 4 Hours', 'datamachine'),
                'seconds' => HOUR_IN_SECONDS * 4
            ],
            'qtrdaily' => [
                'label' => __('Every 6 Hours', 'datamachine'),
                'seconds' => HOUR_IN_SECONDS * 6
            ],
            'twicedaily' => [
                'label' => __('Twice Daily', 'datamachine'),
                'seconds' => HOUR_IN_SECONDS * 12
            ],
            'daily' => [
                'label' => __('Daily', 'datamachine'),
                'seconds' => DAY_IN_SECONDS
            ],
            'weekly' => [
                'label' => __('Weekly', 'datamachine'),
                'seconds' => WEEK_IN_SECONDS
            ]
        ];
    }, 10);
    
    
    add_filter('datamachine_step_settings', function($configs) {
        return $configs;
    }, 5);
    add_filter('datamachine_generate_flow_step_id', function($existing_id, $pipeline_step_id, $flow_id) {
        if (empty($pipeline_step_id) || empty($flow_id)) {
            do_action('datamachine_log', 'error', 'Invalid flow step ID generation parameters', [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id
            ]);
            return '';
        }
        
        return $pipeline_step_id . '_' . $flow_id;
    }, 10, 3);

    add_filter('datamachine_split_pipeline_step_id', function($default, $pipeline_step_id) {
        if (empty($pipeline_step_id) || strpos($pipeline_step_id, '_') === false) {
            return null; // Old UUID4 format or invalid
        }

        $parts = explode('_', $pipeline_step_id, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return [
            'pipeline_id' => $parts[0],
            'uuid' => $parts[1]
        ];
    }, 10, 2);

    // Split composite flow_step_id: {pipeline_step_id}_{flow_id}
    add_filter('datamachine_split_flow_step_id', function($null, $flow_step_id) {
        if (empty($flow_step_id) || !is_string($flow_step_id)) {
            return null;
        }

        // Split on last underscore to handle UUIDs with dashes
        $last_underscore_pos = strrpos($flow_step_id, '_');
        if ($last_underscore_pos === false) {
            return null;
        }

        $pipeline_step_id = substr($flow_step_id, 0, $last_underscore_pos);
        $flow_id = substr($flow_step_id, $last_underscore_pos + 1);

        // Validate flow_id is numeric
        if (!is_numeric($flow_id)) {
            return null;
        }

        return [
            'pipeline_step_id' => $pipeline_step_id,
            'flow_id' => (int)$flow_id
        ];
    }, 10, 2);

    // Global execution context for directives




}
