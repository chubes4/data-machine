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

/**
 * Check if a specific tool is available
 *
 * @param string $tool_name Tool name to check
 * @return bool True if tool is available
 * @since 1.2.0
 */
function ai_http_has_tool($tool_name) {
    $tools = ai_http_get_tools();
    return isset($tools[$tool_name]);
}

/**
 * Get tool definition by name
 *
 * @param string $tool_name Tool name
 * @return array|null Tool definition or null if not found
 * @since 1.2.0  
 */
function ai_http_get_tool_definition($tool_name) {
    $tools = ai_http_get_tools();
    return $tools[$tool_name] ?? null;
}

/**
 * Execute a registered tool by name
 *
 * @param string $tool_name Tool name to execute
 * @param array $parameters Tool parameters
 * @return array Tool execution result
 * @since 1.2.0
 */
function ai_http_execute_tool($tool_name, $parameters = []) {
    // Get tool definition
    $tool_def = ai_http_get_tool_definition($tool_name);
    if (!$tool_def) {
        return [
            'success' => false,
            'error' => "Tool '{$tool_name}' not found",
            'tool_name' => $tool_name
        ];
    }
    
    // Validate required parameters
    if (isset($tool_def['parameters'])) {
        foreach ($tool_def['parameters'] as $param_name => $param_config) {
            if (isset($param_config['required']) && $param_config['required']) {
                if (!isset($parameters[$param_name])) {
                    return [
                        'success' => false,
                        'error' => "Required parameter '{$param_name}' missing for tool '{$tool_name}'",
                        'tool_name' => $tool_name
                    ];
                }
            }
        }
    }
    
    // Execute tool via class method
    if (isset($tool_def['class']) && class_exists($tool_def['class'])) {
        $tool_class = $tool_def['class'];
        $method = isset($tool_def['method']) ? $tool_def['method'] : 'execute';
        
        if (method_exists($tool_class, $method)) {
            try {
                $tool_instance = new $tool_class();
                // Pass both parameters and tool definition (for handler_config access)
                $result = call_user_func([$tool_instance, $method], $parameters, $tool_def);
                
                return [
                    'success' => true,
                    'data' => $result,
                    'tool_name' => $tool_name
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => "Tool execution failed: " . $e->getMessage(),
                    'tool_name' => $tool_name
                ];
            }
        }
    }
    
    // Execute tool via WordPress action (fallback)
    $result = [];
    do_action("ai_tool_{$tool_name}", $parameters, $result);
    
    if (!empty($result)) {
        return [
            'success' => true,
            'data' => $result,
            'tool_name' => $tool_name
        ];
    }
    
    return [
        'success' => false,
        'error' => "Tool '{$tool_name}' has no executable method or action handler",
        'tool_name' => $tool_name
    ];
}