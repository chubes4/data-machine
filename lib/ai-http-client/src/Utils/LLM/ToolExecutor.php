<?php
/**
 * AI HTTP Client - Tool Executor
 * 
 * Single Responsibility: Route tool calls to registered handlers
 * Provides clean extension points for plugins to register custom tools
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Tool_Executor {

    /**
     * Custom tool handlers registered by plugins
     */
    private static $custom_tools = array();

    /**
     * Execute a tool call
     *
     * @param string $tool_name Tool name
     * @param array $arguments Tool arguments
     * @param string $call_id Optional call ID for tracking
     * @return array Tool execution result
     */
    public static function execute_tool($tool_name, $arguments = array(), $call_id = null) {
        $start_time = microtime(true);
        
        try {
            // Validate arguments before execution
            if (!self::validate_tool_arguments($tool_name, $arguments)) {
                return array(
                    'success' => false,
                    'error' => 'Invalid arguments for tool: ' . $tool_name,
                    'call_id' => $call_id
                );
            }
            
            // Execute tool handler
            $result = self::execute_with_timeout($tool_name, $arguments, $call_id);
            
            // Add execution metadata
            $execution_time = microtime(true) - $start_time;
            if (is_array($result)) {
                $result['execution_time'] = round($execution_time, 3);
                $result['call_id'] = $call_id;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            // Debug logging in development mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("AI HTTP Client DEBUG: Tool execution failed for '{$tool_name}': " . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => 'Tool execution failed: ' . $e->getMessage(),
                'call_id' => $call_id,
                'execution_time' => round($execution_time, 3)
            );
        }
    }
    
    /**
     * Execute tool handler
     *
     * @param string $tool_name Tool name
     * @param array $arguments Tool arguments  
     * @param string $call_id Call ID
     * @return array Tool execution result
     * @throws Exception If execution fails
     */
    private static function execute_with_timeout($tool_name, $arguments, $call_id) {
        
        // Check registered custom tools
        if (isset(self::$custom_tools[$tool_name])) {
            $handler = self::$custom_tools[$tool_name];
            
            // Execute handler
            $result = call_user_func($handler, $arguments, $call_id);
            return $result;
        }
        
        // Allow WordPress plugins to handle tools via filter
        $result = apply_filters('ai_http_client_execute_tool', null, $tool_name, $arguments, $call_id);
        
        
        if ($result !== null) {
            return $result;
        }
        
        return array(
            'success' => false,
            'error' => 'Unknown tool: ' . $tool_name
        );
    }
    

    /**
     * Register a custom tool handler
     *
     * @param string $tool_name Tool name
     * @param callable $handler Handler function
     */
    public static function register_tool($tool_name, $handler) {
        if (!is_callable($handler)) {
            throw new Exception('Tool handler must be callable');
        }
        
        self::$custom_tools[$tool_name] = $handler;
    }

    /**
     * Unregister a custom tool handler
     *
     * @param string $tool_name Tool name
     */
    public static function unregister_tool($tool_name) {
        unset(self::$custom_tools[$tool_name]);
    }

    /**
     * Get all available tools (registered tools only)
     *
     * @return array Available tool names
     */
    public static function get_available_tools() {
        return array_keys(self::$custom_tools);
    }

    /**
     * Check if a tool is available
     *
     * @param string $tool_name Tool name
     * @return bool True if tool is available
     */
    public static function is_tool_available($tool_name) {
        return isset(self::$custom_tools[$tool_name]) ||
               has_filter('ai_http_client_execute_tool');
    }

    /**
     * Get tool definition for a specific tool
     *
     * @param string $tool_name Tool name
     * @return array|null Tool definition or null if not found
     */
    public static function get_tool_definition($tool_name) {
        // Allow plugins to provide tool definitions
        return apply_filters('ai_http_client_get_tool_definition', null, $tool_name);
    }

    /**
     * Get all available tool definitions
     *
     * @return array Tool definitions keyed by tool name
     */
    public static function get_all_tool_definitions() {
        $definitions = array();
        
        // Allow plugins to add their tool definitions
        return apply_filters('ai_http_client_get_all_tool_definitions', $definitions);
    }

    /**
     * Execute multiple tools in sequence
     *
     * @param array $tool_calls Array of tool calls
     * @param bool $continue_on_failure Whether to continue executing remaining tools if one fails
     * @return array Array of results
     */
    public static function execute_multiple_tools($tool_calls, $continue_on_failure = true) {
        $results = array();
        
        foreach ($tool_calls as $tool_call) {
            $tool_name = isset($tool_call['function']['name']) ? $tool_call['function']['name'] : '';
            $arguments = isset($tool_call['function']['arguments']) ? $tool_call['function']['arguments'] : array();
            $call_id = isset($tool_call['id']) ? $tool_call['id'] : null;
            
            // Parse arguments if they're JSON string
            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true) ?: array();
            }
            
            $result = self::execute_tool_with_retry($tool_name, $arguments, $call_id);
            $results[] = array(
                'tool_call_id' => $call_id,
                'tool_name' => $tool_name,
                'result' => $result
            );
            
            // Stop executing if this tool failed and continue_on_failure is false
            if (!$continue_on_failure && isset($result['success']) && !$result['success']) {
                // Debug logging in development mode
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("AI HTTP Client DEBUG: Stopping tool execution after failure in '{$tool_name}'");
                }
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Execute tool with retry logic
     *
     * @param string $tool_name Tool name
     * @param array $arguments Tool arguments
     * @param string $call_id Call ID
     * @param int $max_retries Maximum number of retries (default: 2)
     * @return array Tool execution result
     */
    public static function execute_tool_with_retry($tool_name, $arguments = array(), $call_id = null, $max_retries = 2) {
        $last_error = null;
        
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            try {
                $result = self::execute_tool($tool_name, $arguments, $call_id);
                
                // If successful, return immediately
                if (isset($result['success']) && $result['success']) {
                    if ($attempt > 0) {
                        // Debug logging in development mode
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("AI HTTP Client DEBUG: Tool '{$tool_name}' succeeded on attempt " . ($attempt + 1));
                        }
                    }
                    return $result;
                }
                
                // If failed but not due to timeout/exception, don't retry
                if (isset($result['error']) && !self::is_retryable_error($result['error'])) {
                    return $result;
                }
                
                $last_error = isset($result['error']) ? $result['error'] : 'Unknown error';
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                // Debug logging in development mode
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("AI HTTP Client DEBUG: Tool '{$tool_name}' attempt " . ($attempt + 1) . " failed: " . $last_error);
                }
            }
            
            // Wait before retry (exponential backoff)
            if ($attempt < $max_retries) {
                $wait_time = pow(2, $attempt); // 1s, 2s, 4s...
                sleep($wait_time);
            }
        }
        
        // All attempts failed
        return array(
            'success' => false,
            'error' => 'Tool execution failed after ' . ($max_retries + 1) . ' attempts. Last error: ' . $last_error,
            'call_id' => $call_id,
            'attempts' => $max_retries + 1
        );
    }
    
    /**
     * Check if an error is retryable
     *
     * @param string $error Error message
     * @return bool True if error is retryable
     */
    private static function is_retryable_error($error) {
        $retryable_patterns = array(
            'timeout',
            'connection',
            'network',
            'temporary',
            'busy',
            'unavailable'
        );
        
        $error_lower = strtolower($error);
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate tool arguments against tool definition
     *
     * @param string $tool_name Tool name
     * @param array $arguments Tool arguments
     * @return bool True if arguments are valid
     */
    public static function validate_tool_arguments($tool_name, $arguments) {
        $definition = self::get_tool_definition($tool_name);
        if (!$definition || !isset($definition['parameters'])) {
            return true; // If no definition, assume valid
        }
        
        $schema = $definition['parameters'];
        
        // Basic validation - check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $required_field) {
                if (!isset($arguments[$required_field])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Format tool result for AI consumption
     *
     * @param array $result Tool execution result
     * @return string Formatted result text
     */
    public static function format_tool_result($result) {
        if (!$result['success']) {
            return 'Error: ' . ($result['error'] ?? 'Tool execution failed');
        }
        
        if (isset($result['results'])) {
            return $result['results'];
        }
        
        if (isset($result['content'])) {
            return $result['content'];
        }
        
        return 'Tool executed successfully';
    }
}