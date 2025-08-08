<?php
/**
 * AI HTTP Client - Plugin Context Helper
 * 
 * STRICT validation with NO FALLBACKS or legacy support.
 * Enforces proper plugin context configuration - fails immediately if invalid.
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Plugin_Context_Helper {

    /**
     * Validate and sanitize plugin context - STRICT validation with NO FALLBACKS
     *
     * @param mixed $context Plugin context to validate
     * @return array Validation result
     * @throws \InvalidArgumentException If context is invalid
     */
    public static function validate_context($context) {
        if (empty($context)) {
            self::log_context_error('Plugin context is required but not provided - FAILING');
            throw new \InvalidArgumentException('Plugin context is required for AI HTTP Client operation');
        }

        // Sanitize and validate the context
        $sanitized_context = sanitize_key($context);
        
        if (empty($sanitized_context)) {
            self::log_context_error('Plugin context failed sanitization - FAILING');
            throw new \InvalidArgumentException('Plugin context failed sanitization - must be valid key string');
        }

        return array(
            'context' => $sanitized_context,
            'is_configured' => true,
            'error' => null
        );
    }

    /**
     * Check if a context validation result indicates proper configuration
     *
     * @param array $validation_result Result from validate_context()
     * @return bool True if properly configured, false otherwise
     */
    public static function is_configured($validation_result) {
        return isset($validation_result['is_configured']) && $validation_result['is_configured'];
    }

    /**
     * Get the context string from validation result
     *
     * @param array $validation_result Result from validate_context()
     * @return string Plugin context string
     * @throws \InvalidArgumentException If validation result is malformed
     */
    public static function get_context($validation_result) {
        if (!isset($validation_result['context'])) {
            throw new \InvalidArgumentException('Context validation result is malformed - context key missing');
        }
        
        return $validation_result['context'];
    }

    /**
     * Get error message from validation result
     *
     * @param array $validation_result Result from validate_context()
     * @return string|null Error message or null if no error
     */
    public static function get_error($validation_result) {
        return isset($validation_result['error']) ? $validation_result['error'] : null;
    }

    /**
     * Create a standardized error response for missing plugin context
     *
     * @param string $component_name Name of the component reporting the error
     * @return array Standardized error response
     */
    public static function create_context_error_response($component_name = 'AI HTTP Client') {
        return array(
            'success' => false,
            'data' => null,
            'error' => esc_html($component_name) . ' is not properly configured - plugin context is required',
            'provider' => 'none',
            'raw_response' => null
        );
    }

    /**
     * Create HTML error message for admin components
     *
     * @param string $component_name Name of the component
     * @param string $additional_info Additional information to display
     * @return string HTML error message
     */
    public static function create_admin_error_html($component_name = 'AI HTTP Client', $additional_info = '') {
        $message = esc_html($component_name) . ': Plugin context is required for proper configuration.';
        
        if (!empty($additional_info)) {
            $message .= ' ' . esc_html($additional_info);
        }

        return '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }

    /**
     * Log plugin context related errors safely
     *
     * @param string $message Error message to log
     * @param string $component Optional component name for context
     */
    public static function log_context_error($message, $component = 'AI HTTP Client') {
        if (function_exists('error_log')) {
            $log_message = esc_html($component) . ': ' . esc_html($message);
            error_log($log_message);
        }
    }

    /**
     * Validate plugin context for constructor usage - STRICT validation
     * 
     * @param mixed $plugin_context The plugin context to validate
     * @param string $class_name Name of the class for error reporting
     * @return array Validation result
     * @throws \InvalidArgumentException If context is invalid
     */
    public static function validate_for_constructor($plugin_context, $class_name = 'Unknown Class') {
        try {
            return self::validate_context($plugin_context);
        } catch (\InvalidArgumentException $e) {
            self::log_context_error(
                'Constructor failed in ' . $class_name . ': ' . $e->getMessage(),
                $class_name
            );
            throw $e;
        }
    }

    /**
     * Validate plugin context for static method usage - STRICT validation
     *
     * @param array $args Arguments array that should contain plugin_context
     * @param string $method_name Name of the method for error reporting
     * @return array Validation result
     * @throws \InvalidArgumentException If context is invalid
     */
    public static function validate_for_static_method($args, $method_name = 'Unknown Method') {
        $context = isset($args['plugin_context']) ? $args['plugin_context'] : null;
        
        try {
            return self::validate_context($context);
        } catch (\InvalidArgumentException $e) {
            self::log_context_error(
                'Static method ' . $method_name . ' failed: ' . $e->getMessage(),
                $method_name
            );
            throw $e;
        }
    }
}