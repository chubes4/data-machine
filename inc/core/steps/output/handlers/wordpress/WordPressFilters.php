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
    
    // Modal content registration - WordPress owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle wordpress handler (output version)
        if ($handler_slug !== 'wordpress') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'wordpress');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'wordpress',
                'handler_config' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Create and update WordPress posts and pages', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output',
                'flow_id' => $context['flow_id'] ?? '',
                'pipeline_id' => $context['pipeline_id'] ?? '',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        
        if ($template === 'handler-auth') {
            // Authentication modal template
            return apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'wordpress',
                'handler_config' => [
                    'label' => __('WordPress', 'data-machine'),
                    'description' => __('Create and update WordPress posts and pages', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output'
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_output_filters();