<?php
/**
 * Reddit Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Reddit Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Reddit
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Reddit;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Reddit Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Reddit Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_reddit_input_filters() {
    
    // Handler registration - Reddit declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['reddit'] = [
            'type' => 'input',
            'class' => Reddit::class,
            'label' => __('Reddit', 'data-machine'),
            'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['reddit'] = new RedditAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['reddit'] = new RedditSettings();
        return $all_settings;
    });
    
    // Modal content registration - Reddit owns its handler-settings and handler-auth modal content
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
        
        // Only handle reddit handler
        if ($handler_slug !== 'reddit') {
            return $content;
        }
        
        if ($template === 'handler-settings') {
            // Settings modal template
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings['reddit'] ?? null;
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'reddit',
                'handler_config' => [
                    'label' => __('Reddit', 'data-machine'),
                    'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
                ],
                'step_type' => sanitize_text_field($context['step_type'] ?? 'input'),
                'flow_id' => sanitize_text_field($context['flow_id'] ?? ''),
                'pipeline_id' => sanitize_text_field($context['pipeline_id'] ?? ''),
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        
        if ($template === 'handler-auth') {
            // Authentication modal template
            return apply_filters('dm_render_template', '', 'modal/handler-auth-form', [
                'handler_slug' => 'reddit',
                'handler_config' => [
                    'label' => __('Reddit', 'data-machine'),
                    'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
                ],
                'step_type' => sanitize_text_field($context['step_type'] ?? 'input')
            ]);
        }
        
        return $content;
    }, 10, 2);
    
    // DataPacket conversion registration - Reddit handler uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'reddit') {
            return RedditDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_reddit_input_filters();