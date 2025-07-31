<?php
/**
 * Logger Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Logger component's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * The logger component manages its own service registration and admin hooks.
 * 
 * @package DataMachine
 * @subpackage Core\Logger
 * @since 0.1.0
 */

namespace DataMachine\Core\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Logger component filters and hooks
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Logger capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_logger_filters() {
    
    // Logger service registration - Logger declares itself as the logging service
    add_filter('dm_get_logger', function($logger) {
        if ($logger !== null) {
            return $logger; // External override provided
        }
        
        static $logger_instance = null;
        if ($logger_instance === null) {
            $logger_instance = new Logger();
        }
        return $logger_instance;
    }, 10);
    
    // Admin notices hook registration - Logger manages its own admin hooks
    // This eliminates bootstrap dependencies and maintains component autonomy
    if (is_admin()) {
        add_action('admin_notices', function() {
            $logger = apply_filters('dm_get_logger', null);
            if ($logger && method_exists($logger, 'display_admin_notices')) {
                $logger->display_admin_notices();
            }
        });
    }
    
    // Additional logger extensibility hooks for external plugins
    
    // Allow external plugins to register custom log handlers
    add_filter('dm_logger_add_handler', function($handlers) {
        // External plugins can add custom Monolog handlers via this filter
        return $handlers;
    }, 10);
    
    // Allow external plugins to customize log formatting
    add_filter('dm_logger_format_message', function($formatted_message, $level, $message, $context) {
        // External plugins can modify log message formatting
        return $formatted_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_logger_filters();