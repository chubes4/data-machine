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
 * *     ];
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
    ];
    
    return $tools;
});

/**
 * Register Local Search Tool
 */
add_filter('ai_tools', function($tools) {
    $tools['local_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\LocalSearch',
        'method' => 'handle_tool_call',
        'description' => 'Find existing posts on this WordPress site to create accurate internal links. Use this tool whenever you want to link to site content instead of guessing URLs. Returns real permalinks in the "link" field.',
        'requires_config' => false, // No configuration needed - uses WordPress core
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search terms to find relevant posts for internal linking. Use the returned "link" field for accurate URLs.'
            ]
        ]
    ];
    
    return $tools;
});

/**
 * Register Read Post Tool
 */
add_filter('ai_tools', function($tools) {
    $tools['read_post'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\ReadPost',
        'method' => 'handle_tool_call',
        'description' => 'Read full content of existing WordPress posts and pages by ID. Use this after finding posts with local_search to get complete content for analysis or modification.',
        'requires_config' => false, // No configuration needed - uses WordPress core
        'parameters' => [
            'post_id' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'WordPress post ID to retrieve content from'
            ],
            'include_meta' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Include custom fields in response (default: false)'
            ]
        ]
    ];
    
    return $tools;
});

/**
 * Register Google Search Console Tool
 */
add_filter('ai_tools', function($tools) {
    $tools['google_search_console'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearchConsole',
        'method' => 'handle_tool_call',
        'description' => 'Analyze Google Search Console data for SEO optimization. Get keyword performance, find content opportunities, and suggest internal links based on search data.',
        'requires_config' => true, // Requires OAuth authentication
        'parameters' => [
            'page_url' => [
                'type' => 'string',
                'required' => true,
                'description' => 'URL of the page to analyze (must match a page in your Search Console account)'
            ],
            'analysis_type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Type of analysis: performance, keywords, opportunities, or internal_links (default: performance)'
            ],
            'date_range' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Date range for analysis: 7d, 30d, or 90d (default: 30d)'
            ],
            'include_internal_links' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether to include internal linking suggestions (default: false)'
            ]
        ]
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
        
        case 'local_search':
            return true; // Always configured - no setup required
        
        case 'read_post':
            return true; // Always configured - no setup required
        
        case 'google_search_console':
            // GSC tool configuration is handled by GoogleSearchConsoleFilters.php
            // Check if OAuth configuration exists and user is authenticated
            $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
            $has_config = !empty($config['client_id']) && !empty($config['client_secret']);
            
            $account = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
            $has_tokens = !empty($account['access_token']);
            
            return $has_config && $has_tokens;
        
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