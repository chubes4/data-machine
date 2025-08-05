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
    
    // Handler registration - Bluesky declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            // Initialize handlers array if null
            if ($handlers === null) {
                $handlers = [];
            }
            
            $handlers['bluesky'] = [
                'class' => Bluesky::class,
                'label' => __('Bluesky', 'data-machine'),
                'description' => __('Post content to Bluesky with media support and AT Protocol integration', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'bluesky' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'bluesky') {
            return new BlueskyAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'bluesky' handler  
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'bluesky') {
            return new BlueskySettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - Bluesky owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = $_POST['context'] ?? [];
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle bluesky handler
        if ($handler_slug !== 'bluesky') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'bluesky');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'bluesky',
                'handler_config' => [
                    'label' => __('Bluesky', 'data-machine'),
                    'description' => __('Post content to Bluesky using AT Protocol', 'data-machine')
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
                'handler_slug' => 'bluesky',
                'handler_config' => [
                    'label' => __('Bluesky', 'data-machine'),
                    'description' => __('Post content to Bluesky using AT Protocol', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output'
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_bluesky_filters();