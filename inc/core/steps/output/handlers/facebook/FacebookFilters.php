<?php
/**
 * Facebook Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Facebook's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Facebook
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\Facebook;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Facebook component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Facebook capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_facebook_filters() {
    
    // Handler registration - Facebook declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['facebook'] = [
                'class' => Facebook::class,
                'label' => __('Facebook', 'data-machine'),
                'description' => __('Post content to Facebook pages and profiles', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'facebook' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'facebook') {
            return new FacebookAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'facebook' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'facebook') {
            return new FacebookSettings();
        }
        return $settings;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();