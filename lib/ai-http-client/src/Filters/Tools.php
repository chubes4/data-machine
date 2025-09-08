<?php
/**
 * AI HTTP Client - Tool Filters
 * 
 * Centralized tool registration and management via WordPress filter system.
 * All tool-related filters and helper functions organized in this file.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

// Register AI tools filter for plugin-scoped tool registration
// Usage: $all_tools = apply_filters('ai_tools', []);
add_filter('ai_tools', function($tools) {
    // Tools self-register in their own files following the same pattern as providers
    // This enables any plugin to register tools that other plugins can discover and use
    return $tools;
});

/**
 * Convert tool name to tool definition
 *
 * @param string $tool_name Tool name  
 * @return array Tool definition
 */
function ai_http_convert_tool_name_to_definition($tool_name) {
    // Map common tool names to definitions
    $tool_definitions = array(
        'web_search_preview' => array(
            'type' => 'web_search_preview',
            'search_context_size' => 'low'
        ),
        'web_search' => array(
            'type' => 'web_search_preview',
            'search_context_size' => 'medium'
        )
    );
    
    return $tool_definitions[$tool_name] ?? array('type' => $tool_name);
}

/**
 * Get all registered AI tools with optional filtering
 *
 * @param string $category Optional category filter  
 * @return array Filtered tools array
 * @since 1.2.0
 */
function ai_http_get_tools($category = null) {
    $all_tools = apply_filters('ai_tools', []);
    
    // Filter by category
    if ($category) {
        $all_tools = array_filter($all_tools, function($tool) use ($category) {
            return isset($tool['category']) && $tool['category'] === $category;
        });
    }
    
    return $all_tools;
}


