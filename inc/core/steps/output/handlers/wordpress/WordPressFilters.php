<?php
/**
 * WordPress Output Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Output Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Output Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Output Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_output_filters() {
    
    // Handler registration - WordPress declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['wordpress'] = [
                'class' => WordPress::class,
                'label' => __('WordPress', 'data-machine'),
                'description' => __('Create and update WordPress posts and pages', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'wordpress' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'wordpress') {
            return new WordPressAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'wordpress' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'wordpress') {
            return new WordPressSettings();
        }
        return $settings;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_output_filters();