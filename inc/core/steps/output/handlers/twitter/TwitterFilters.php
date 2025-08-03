<?php
/**
 * Twitter Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Twitter's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Twitter
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Twitter component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Twitter capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_twitter_filters() {
    
    // Handler registration - Twitter declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['twitter'] = [
                'class' => Twitter::class,
                'label' => __('Twitter', 'data-machine'),
                'description' => __('Post content to Twitter with media support', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'twitter' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'twitter') {
            return new TwitterAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'twitter' handler  
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'twitter') {
            return new TwitterSettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - Twitter owns its handler-settings and handler-auth modal content
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another handler
        if ($content !== null) {
            return $content;
        }
        
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        $handler_slug = $context['handler_slug'] ?? '';
        
        // Only handle twitter handler
        if ($handler_slug !== 'twitter') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'twitter');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'twitter',
                'handler_config' => [
                    'label' => __('Twitter', 'data-machine'),
                    'description' => __('Post content to Twitter with media support', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        
        if ($template === 'handler-auth') {
            // Authentication modal template
            return apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'twitter',
                'handler_config' => [
                    'label' => __('Twitter', 'data-machine'),
                    'description' => __('Post content to Twitter with media support', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'output'
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_twitter_filters();