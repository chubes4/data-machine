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
    
    // Modal content registration - Bluesky owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        // Properly sanitize context data following WordPress security standards
        $raw_context = wp_unslash($_POST['context'] ?? '');
        $context = is_string($raw_context) ? json_decode($raw_context, true) : [];
        $context = is_array($context) ? $context : [];
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        
        // Only handle bluesky handler
        if ($handler_slug !== 'bluesky') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings['bluesky'] ?? null;
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'bluesky',
                'handler_config' => [
                    'label' => __('Bluesky', 'data-machine'),
                    'description' => __('Post content to Bluesky using AT Protocol', 'data-machine')
                ],
                'step_type' => sanitize_text_field($context['step_type'] ?? 'output'),
                'flow_id' => sanitize_text_field($context['flow_id'] ?? ''),
                'pipeline_id' => sanitize_text_field($context['pipeline_id'] ?? ''),
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
                'step_type' => sanitize_text_field($context['step_type'] ?? 'output')
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_bluesky_filters();