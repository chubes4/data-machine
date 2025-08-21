<?php
/**
 * General AI Tools - Filter Registration
 * 
 * Register general-purpose AI tools that are available to all AI steps
 * regardless of the next step's handler. These tools provide capabilities
 * like search, data processing, analysis, etc.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register general AI tools filter
 * 
 * General tools do NOT have a 'handler' property, making them available
 * to all AI steps regardless of what the next pipeline step is.
 * 
 * Example tool registration:
 * 
 * add_filter('ai_tools', function($tools) {
 *     $tools['my_general_tool'] = [
 *         'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\MyTool',
 *         'method' => 'handle_tool_call',
 *         'description' => 'Description of what this tool does',
 *         'parameters' => [
 *             'input' => [
 *                 'type' => 'string',
 *                 'required' => true,
 *                 'description' => 'Input parameter description'
 *             ]
 *         ]
 *         // NOTE: No 'handler' property - this makes it a general tool
 *     ];
 *     return $tools;
 * });
 */
/**
 * Register Google Search Tool
 */
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for current information and context',
        'requires_config' => true, // Flag for UI to show configure link
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query to find information about'
            ],
            'max_results' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of results to return (1-10, default: 5)'
            ],
            'site_restrict' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Restrict search to specific domain (e.g., "wikipedia.org")'
            ]
        ]
        // NOTE: No 'handler' property - this makes it available to all AI steps
    ];
    
    return $tools;
});

/**
 * Tool Configuration Detection Filter
 * 
 * Checks if a tool is properly configured and ready for use
 */
add_filter('dm_tool_configured', function($configured, $tool_id) {
    switch ($tool_id) {
        case 'google_search':
            $config = get_option('dm_search_config', []);
            $google_config = $config['google_search'] ?? [];
            return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
        
        default:
            return $configured;
    }
}, 10, 2);

/**
 * Tool Configuration Retrieval Filter
 * 
 * Retrieves stored configuration for a specific tool
 */
add_filter('dm_get_tool_config', function($config, $tool_id) {
    switch ($tool_id) {
        case 'google_search':
            $stored_config = get_option('dm_search_config', []);
            return $stored_config['google_search'] ?? [];
        
        default:
            return $config;
    }
}, 10, 2);

/**
 * Save Tool Configuration Action
 * 
 * Handles saving tool configuration data
 */
add_action('dm_save_tool_config', function($tool_id, $config_data) {
    switch ($tool_id) {
        case 'google_search':
            // Validate and sanitize Google Search configuration
            $api_key = sanitize_text_field($config_data['api_key'] ?? '');
            $search_engine_id = sanitize_text_field($config_data['search_engine_id'] ?? '');
            
            if (empty($api_key) || empty($search_engine_id)) {
                wp_send_json_error(['message' => __('API Key and Search Engine ID are required', 'data-machine')]);
                return;
            }
            
            // Get existing config and update Google Search section
            $stored_config = get_option('dm_search_config', []);
            $stored_config['google_search'] = [
                'api_key' => $api_key,
                'search_engine_id' => $search_engine_id
            ];
            
            // Save updated configuration
            if (update_option('dm_search_config', $stored_config)) {
                wp_send_json_success([
                    'message' => __('Google Search configuration saved successfully', 'data-machine'),
                    'configured' => true
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save configuration', 'data-machine')]);
            }
            break;
        
        default:
            wp_send_json_error(['message' => __('Unknown tool configuration', 'data-machine')]);
    }
}, 10, 2);