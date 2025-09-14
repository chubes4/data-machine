<?php
/**
 * General AI Tools Registration
 * 
 * Registers universal AI tools available to all AI steps regardless of next step handler.
 * General tools lack 'handler' property, distinguishing them from handler-specific tools.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * General tools lack 'handler' property, making them available to all AI steps.
 * Handler-specific tools include 'handler' property and appear only for matching next steps.
 */
/**
 * Google Search Tool - Web search with configurable restrictions
 */
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google and return structured JSON results with titles, links, and snippets from external websites. Use for external information, current events, and fact-checking. Returns complete web search data in JSON format with title, link, snippet for each result.',
        'requires_config' => true,
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query for external web information. Returns JSON with "results" array containing web search results.'
            ],
            'max_results' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of results to return (1-10, default: 5). Limit to reduce response size.'
            ],
            'site_restrict' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Restrict search to specific domain (e.g., "wikipedia.org" for Wikipedia only)'
            ]
        ]
    ];
    
    return $tools;
});

/**
 * Local Search Tool - WordPress content discovery
 */
add_filter('ai_tools', function($tools) {
    $tools['local_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\LocalSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search this WordPress site and return structured JSON results with post titles, excerpts, permalinks, and metadata. Use ONCE to find existing content before creating new content. Returns complete search data in JSON format - avoid calling multiple times for the same query.',
        'requires_config' => false,
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search terms to find relevant posts. Returns JSON with "results" array containing title, link, excerpt, post_type, publish_date, author for each match.'
            ],
            'max_results' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum results to return (1-20, default: 10). Limit to reduce response size.'
            ],
            'post_types' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Post types to search (default: ["post", "page"]). Available types depend on site configuration.'
            ]
        ]
    ];
    
    return $tools;
});

/**
 * Web Fetch Tool - Retrieve and process web page content
 */
add_filter('ai_tools', function($tools) {
    $tools['web_fetch'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\WebFetch',
        'method' => 'handle_tool_call',
        'description' => 'Fetch and extract readable content from web pages. Use after Google Search to retrieve full article content. Returns page title and cleaned text content from any HTTP/HTTPS URL.',
        'requires_config' => false,
        'parameters' => [
            'url' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Full HTTP/HTTPS URL to fetch content from. Must be a valid web address.'
            ]
        ]
    ];

    return $tools;
});

/**
 * WordPress Post Reader Tool - Read specific WordPress posts by URL for detailed content analysis
 */
add_filter('ai_tools', function($tools) {
    $tools['wordpress_post_reader'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\WordPressPostReader',
        'method' => 'handle_tool_call',
        'name' => 'WordPress Post Reader',
        'description' => 'Read full content from specific WordPress posts by URL for detailed analysis. Use after Local Search to get complete post content instead of excerpts. Perfect for content analysis before WordPress Update operations.',
        'requires_config' => false,
        'parameters' => [
            'source_url' => [
                'type' => 'string',
                'required' => true,
                'description' => 'WordPress post URL to retrieve content from (use URLs from Local Search results)'
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
 * Check tool configuration status for UI enablement
 */
add_filter('dm_tool_configured', function($configured, $tool_id) {
    switch ($tool_id) {
        case 'google_search':
            $config = get_option('dm_search_config', []);
            $google_config = $config['google_search'] ?? [];
            return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
        
        case 'local_search':
            return true; // Always configured - no setup required

        case 'web_fetch':
            return true; // Always configured - no setup required

        case 'wordpress_post_reader':
            return true; // Always configured - no setup required

        default:
            return $configured;
    }
}, 10, 2);

/**
 * Retrieve stored tool configuration
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
 * Save tool configuration with validation
 */
add_action('dm_save_tool_config', function($tool_id, $config_data) {
    switch ($tool_id) {
        case 'google_search':
            // Validate required Google Search fields
            $api_key = sanitize_text_field($config_data['api_key'] ?? '');
            $search_engine_id = sanitize_text_field($config_data['search_engine_id'] ?? '');
            
            if (empty($api_key) || empty($search_engine_id)) {
                wp_send_json_error(['message' => __('API Key and Search Engine ID are required', 'data-machine')]);
                return;
            }
            
            // Update Google Search configuration
            $stored_config = get_option('dm_search_config', []);
            $stored_config['google_search'] = [
                'api_key' => $api_key,
                'search_engine_id' => $search_engine_id
            ];
            
            // Persist configuration
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