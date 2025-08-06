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
    
    // Modal content registration - Facebook owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = $_POST['context'] ?? [];
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle facebook handler
        if ($handler_slug !== 'facebook') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'facebook');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'facebook',
                'handler_config' => [
                    'label' => __('Facebook', 'data-machine'),
                    'description' => __('Post content to Facebook pages and profiles', 'data-machine')
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
                'handler_slug' => 'facebook',
                'handler_config' => [
                    'label' => __('Facebook', 'data-machine'),
                    'description' => __('Post content to Facebook pages and profiles', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output'
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_facebook_filters();