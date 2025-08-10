<?php
/**
 * WordPress Publish Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Publish Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Publish\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Publish Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Publish Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_publish_filters() {
    
    // Handler registration - WordPress declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_publish'] = [
            'type' => 'publish',
            'class' => WordPress::class,
            'label' => __('WordPress', 'data-machine'),
            'description' => __('Create and update WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['wordpress_publish'] = new WordPressAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_publish'] = new WordPressSettings();
        return $all_settings;
    });
    
    // Handler directive registration - pure discovery mode
    add_filter('dm_handler_directives', function($directives) {
        $directives['wordpress_publish'] = 'When publishing to WordPress, format your response as:\nTITLE: [compelling post title]\nCATEGORY: [single category name]\nTAGS: [comma,separated,tags]\nCONTENT:\n[your content here]';
        return $directives;
    });
    
    // Modal content registration - Pure discovery mode
    add_filter('dm_modals', function($modals) {
        // Get WordPress settings for modal content
        $all_settings = apply_filters('dm_handler_settings', []);
        $handler_settings = $all_settings['wordpress_publish'] ?? null;
        
        // Handler-specific modal removed - core modal handles generic 'handler-settings'
        
        // Remote Locations Manager modal (WordPress uses Remote Locations, not OAuth)
        $modals['remote-locations-manager'] = [
            'content' => apply_filters('dm_render_template', '', 'modal/remote-locations-manager', [
                'handler_slug' => 'wordpress_publish',
                'handler_settings' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Create and update WordPress posts and pages', 'data-machine')
                ],
                'step_type' => 'publish'
            ]),
            'title' => __('WordPress Remote Locations', 'data-machine')
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_publish_filters();