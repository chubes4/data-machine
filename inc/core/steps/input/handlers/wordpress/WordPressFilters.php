<?php
/**
 * WordPress Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_input_filters() {
    
    // Handler registration - WordPress declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['wordpress_input'] = [
            'type' => 'input',
            'class' => WordPress::class,
            'label' => __('WordPress', 'data-machine'),
            'description' => __('Source content from WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['wordpress_input'] = new WordPressAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['wordpress_input'] = new WordPressSettings();
        return $all_settings;
    });
    
    // Modal content registration - WordPress Input owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = $_POST['context'] ?? [];
        $handler_slug = $context['handler_slug'] ?? '';
        $step_type = $context['step_type'] ?? '';
        
        // Only handle wordpress_input handler in input context
        if ($handler_slug !== 'wordpress_input' || $step_type !== 'input') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings['wordpress_input'] ?? null;
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'wordpress_input',
                'handler_config' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Source content from WordPress posts and pages', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input',
                'flow_id' => $context['flow_id'] ?? '',
                'pipeline_id' => $context['pipeline_id'] ?? '',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        
        if ($template === 'handler-auth') {
            // Authentication modal template
            return apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'wordpress_input',
                'handler_config' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Source content from WordPress posts and pages', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input'
            ]);
        }
        
        return $content;
    }, 10, 2);
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // WordPress handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_input_filters();