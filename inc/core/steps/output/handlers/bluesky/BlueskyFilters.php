<?php
/**
 * Bluesky Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Bluesky's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Bluesky
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\Bluesky;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Bluesky component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Bluesky capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_bluesky_filters() {
    
    // Handler registration - Bluesky declares itself as output handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['bluesky'] = [
            'type' => 'output',
            'class' => Bluesky::class,
            'label' => __('Bluesky', 'data-machine'),
            'description' => __('Post content to Bluesky with media support and AT Protocol integration', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['bluesky'] = new BlueskyAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['bluesky'] = new BlueskySettings();
        return $all_settings;
    });
    
    // Modal content registration - Pure discovery mode
    add_filter('dm_get_modals', function($modals) {
        // Get Bluesky settings for modal content
        $all_settings = apply_filters('dm_get_handler_settings', []);
        $settings_instance = $all_settings['bluesky'] ?? null;
        
        // Handler-specific modal removed - core modal handles generic 'handler-settings'
        
        // Handler authentication modal
        $modals['bluesky-handler-auth'] = [
            'content' => apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'bluesky',
                'handler_config' => [
                    'label' => __('Bluesky', 'data-machine'),
                    'description' => __('Post content to Bluesky using AT Protocol', 'data-machine')
                ],
                'step_type' => 'output'
            ]),
            'title' => __('Bluesky Authentication', 'data-machine')
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_bluesky_filters();