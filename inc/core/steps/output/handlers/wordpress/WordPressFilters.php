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
    
    // Handler registration - WordPress declares itself as output handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['wordpress_output'] = [
            'type' => 'output',
            'class' => WordPress::class,
            'label' => __('WordPress', 'data-machine'),
            'description' => __('Create and update WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['wordpress_output'] = new WordPressAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['wordpress_output'] = new WordPressSettings();
        return $all_settings;
    });
    
    // Modal content registration - Pure discovery mode
    add_filter('dm_get_modals', function($modals) {
        // Get WordPress settings for modal content
        $all_settings = apply_filters('dm_get_handler_settings', []);
        $settings_instance = $all_settings['wordpress_output'] ?? null;
        
        // Handler-specific modal removed - core modal handles generic 'handler-settings'
        
        // Remote Locations Manager modal (WordPress uses Remote Locations, not OAuth)
        $modals['remote-locations-manager'] = [
            'content' => apply_filters('dm_render_template', '', 'modal/remote-locations-manager', [
                'handler_slug' => 'wordpress_output',
                'handler_config' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Create and update WordPress posts and pages', 'data-machine')
                ],
                'step_type' => 'output'
            ]),
            'title' => __('WordPress Remote Locations', 'data-machine')
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_output_filters();