<?php
/**
 * Threads Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Threads' complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Threads
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\Threads;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Threads component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Threads capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_threads_filters() {
    
    // Handler registration - Threads declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['threads'] = [
                'class' => Threads::class,
                'label' => __('Threads', 'data-machine'),
                'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'threads' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'threads') {
            return new ThreadsAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'threads' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'threads') {
            return new ThreadsSettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - Threads owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle threads handler
        if ($handler_slug !== 'threads') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'threads');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'threads',
                'handler_config' => [
                    'label' => __('Threads', 'data-machine'),
                    'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine')
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
                'handler_slug' => 'threads',
                'handler_config' => [
                    'label' => __('Threads', 'data-machine'),
                    'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output'
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_threads_filters();