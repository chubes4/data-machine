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
    
    // Handler registration - Facebook declares itself as output handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['facebook'] = [
            'type' => 'output',
            'class' => Facebook::class,
            'label' => __('Facebook', 'data-machine'),
            'description' => __('Post content to Facebook pages and profiles', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['facebook'] = new FacebookAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['facebook'] = new FacebookSettings();
        return $all_settings;
    });
    
    // Modal content registration - Pure discovery mode
    add_filter('dm_get_modals', function($modals) {
        // Get Facebook settings for modal content
        $all_settings = apply_filters('dm_get_handler_settings', []);
        $settings_instance = $all_settings['facebook'] ?? null;
        
        // Handler-specific modal removed - core modal handles generic 'handler-settings'
        
        // Handler authentication modal
        $modals['facebook-handler-auth'] = [
            'content' => apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'facebook',
                'handler_config' => [
                    'label' => __('Facebook', 'data-machine'),
                    'description' => __('Post content to Facebook pages and profiles', 'data-machine')
                ],
                'step_type' => 'output'
            ]),
            'title' => __('Facebook Authentication', 'data-machine')
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();