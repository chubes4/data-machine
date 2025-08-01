<?php
/**
 * Twitter Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Twitter's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Twitter
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Twitter component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Twitter capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_twitter_filters() {
    
    // Handler registration - Twitter declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['twitter'] = [
                'class' => Twitter::class,
                'label' => __('Twitter', 'data-machine'),
                'description' => __('Post content to Twitter with media support', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'twitter' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'twitter') {
            return new TwitterAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'twitter' handler  
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'twitter') {
            return new TwitterSettings();
        }
        return $settings;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_twitter_filters();