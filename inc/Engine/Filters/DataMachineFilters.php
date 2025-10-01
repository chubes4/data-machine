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

function dm_register_importexport_filters() {
    
    add_filter('dm_modals', function($modals) {
        $modals['import-export'] = [
            'title' => __('Import / Export Pipelines', 'data-machine'),
            'template' => 'modal/import-export',
            'size' => 'large'
        ];
        return $modals;
    }, 10, 1);
    
    add_filter('dm_importer', function($service) {
        if ($service === null) {
            require_once DATA_MACHINE_PATH . 'inc/Engine/Actions/ImportExport.php';
            return new \DataMachine\Engine\Actions\ImportExport();
        }
        return $service;
    }, 10, 1);
}

dm_register_importexport_filters();

/**
 * Register backend processing filters for engine operations.
 *
 * Registers filters for service discovery, HTTP requests, scheduling,
 * and step configuration. Does not handle UI/admin logic.
 *
 * @since 0.1.0
 */
function dm_register_utility_filters() {
    
    add_filter('dm_auth_providers', function($providers) {
        return $providers;
    }, 5, 1);
    
    
    add_filter('dm_request', function($default, $method, $url, $args, $context) {
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            do_action('dm_log', 'error', 'HTTP Request: Invalid method', ['method' => $method, 'context' => $context]);
            return ['success' => false, 'error' => __('Invalid HTTP method', 'data-machine')];
        }

        $args = wp_parse_args($args, [
            'user-agent' => sprintf('DataMachine/%s (+%s)', 
                defined('DATA_MACHINE_VERSION') ? DATA_MACHINE_VERSION : '1.0', 
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
                __('%1$s %2$s returned HTTP %3$d', 'data-machine'),
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

            do_action('dm_log', 'error', 'HTTP Request: Error response', [
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
    
    
    add_filter('dm_step_settings', function($configs) {
        return $configs;
    }, 5);
    add_filter('dm_generate_flow_step_id', function($existing_id, $pipeline_step_id, $flow_id) {
        if (empty($pipeline_step_id) || empty($flow_id)) {
            do_action('dm_log', 'error', 'Invalid flow step ID generation parameters', [
                'pipeline_step_id' => $pipeline_step_id,
                'flow_id' => $flow_id
            ]);
            return '';
        }
        
        return $pipeline_step_id . '_' . $flow_id;
    }, 10, 3);

    add_filter('dm_split_pipeline_step_id', function($default, $pipeline_step_id) {
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
    
    add_action('ai_api_error', function($error_data) {
        do_action('dm_log', 'error', $error_data['message'], $error_data);
    });
    
    // Global execution context for directives
    add_filter('dm_current_flow_step_id', function($default) {
        return $default;
    }, 5, 1);
    
    add_filter('dm_current_job_id', function($default) {
        return $default;
    }, 5, 1);

    
    
}
