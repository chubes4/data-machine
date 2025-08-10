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
 * @subpackage Core\Handlers\Publish\Facebook
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\Facebook;

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
    
    // Handler registration - Facebook declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['facebook'] = [
            'type' => 'publish',
            'class' => Facebook::class,
            'label' => __('Facebook', 'data-machine'),
            'description' => __('Post content to Facebook pages and profiles', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['facebook'] = new FacebookAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['facebook'] = new FacebookSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();