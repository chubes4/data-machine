<?php
/**
 * AI HTTP Client - Plugin Context Helper
 * 
 * Centralized handling of plugin context validation and graceful fallbacks.
 * Prevents fatal errors when plugin context is missing while maintaining
 * proper error logging and configuration state management.
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Plugin_Context_Helper {

    /**
     * Fallback context used when no plugin context is provided
     */
    const FALLBACK_CONTEXT = 'ai-http-client-fallback';

    /**
     * Validate and sanitize plugin context with graceful fallback
     *
     * @param mixed $context Plugin context to validate
     * @return array Validation result with context and configuration state
     */
    public static function validate_context($context) {
        if (empty($context)) {
            self::log_context_error('Plugin context is required but not provided. Using fallback context.');
            
            return array(
                'context' => self::FALLBACK_CONTEXT,
                'is_configured' => false,
                'is_fallback' => true,
                'error' => 'Plugin context is required for proper functionality'
            );
        }

        // Sanitize and validate the context
        $sanitized_context = sanitize_key($context);
        
        if (empty($sanitized_context)) {
            self::log_context_error('Plugin context failed sanitization. Using fallback context.');
            
            return array(
                'context' => self::FALLBACK_CONTEXT,
                'is_configured' => false,
                'is_fallback' => true,
                'error' => 'Plugin context failed sanitization'
            );
        }

        return array(
            'context' => $sanitized_context,
            'is_configured' => true,
            'is_fallback' => false,
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
     * Check if a context validation result is using fallback
     *
     * @param array $validation_result Result from validate_context()
     * @return bool True if using fallback context, false otherwise
     */
    public static function is_fallback($validation_result) {
        return isset($validation_result['is_fallback']) && $validation_result['is_fallback'];
    }

    /**
     * Get the context string from validation result
     *
     * @param array $validation_result Result from validate_context()
     * @return string Plugin context string
     */
    public static function get_context($validation_result) {
        return isset($validation_result['context']) ? $validation_result['context'] : self::FALLBACK_CONTEXT;
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
     * Validate plugin context for constructor usage
     * 
     * Provides a simple interface for classes that need plugin context
     * in their constructors without throwing fatal errors.
     *
     * @param mixed $plugin_context The plugin context to validate
     * @param string $class_name Name of the class for error reporting
     * @return array Validation result
     */
    public static function validate_for_constructor($plugin_context, $class_name = 'Unknown Class') {
        $validation = self::validate_context($plugin_context);
        
        if (!self::is_configured($validation)) {
            self::log_context_error(
                'Constructor called without valid plugin context in ' . $class_name,
                $class_name
            );
        }

        return $validation;
    }

    /**
     * Validate plugin context for static method usage
     *
     * Provides validation specifically for static methods that receive
     * plugin context in their arguments.
     *
     * @param array $args Arguments array that should contain plugin_context
     * @param string $method_name Name of the method for error reporting
     * @return array Validation result
     */
    public static function validate_for_static_method($args, $method_name = 'Unknown Method') {
        $context = isset($args['plugin_context']) ? $args['plugin_context'] : null;
        $validation = self::validate_context($context);
        
        if (!self::is_configured($validation)) {
            self::log_context_error(
                'Static method ' . $method_name . ' called without valid plugin context',
                $method_name
            );
        }

        return $validation;
    }

    /**
     * Get fallback context string
     *
     * @return string The fallback context identifier
     */
    public static function get_fallback_context() {
        return self::FALLBACK_CONTEXT;
    }

    /**
     * Check if a given context string is the fallback context
     *
     * @param string $context Context string to check
     * @return bool True if it's the fallback context
     */
    public static function is_fallback_context($context) {
        return $context === self::FALLBACK_CONTEXT;
    }
}