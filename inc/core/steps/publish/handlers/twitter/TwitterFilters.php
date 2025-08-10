<?php
/**
 * Twitter Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Twitter's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Publish\Twitter
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\Twitter;

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
    
    // Handler registration - Twitter declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => Twitter::class,
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['twitter'] = new TwitterSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

// Auto-register when file loads - achieving complete self-containment
dm_register_twitter_filters();