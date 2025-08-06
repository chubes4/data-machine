<?php
/**
 * RSS Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as RSS Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Rss
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Rss;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all RSS Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers RSS Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_rss_input_filters() {
    
    // Handler registration - RSS declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['rss'] = [
            'type' => 'input',
            'class' => Rss::class,
            'label' => __('RSS', 'data-machine'),
            'description' => __('Monitor and process RSS feeds', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'rss' handler
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['rss'] = new RssSettings();
        return $all_settings;
    });
    
    // Modal content registration - RSS owns its handler-settings modal content
    add_filter('dm_get_modal', function($content, $template) {
        if ($template === 'handler-settings') {
            // Return early if content already provided by another handler
            if ($content !== null) {
                return $content;
            }
            
            $context = $_POST['context'] ?? [];
            $handler_slug = $context['handler_slug'] ?? '';
            
            // Only handle rss handler
            if ($handler_slug !== 'rss') {
                return $content;
            }
            
            // Use proper filter-based template rendering
            $all_settings = apply_filters('dm_get_handler_settings', []);
            $settings_instance = $all_settings['rss'] ?? null;
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'rss',
                'handler_config' => [
                    'label' => __('RSS', 'data-machine'),
                    'description' => __('Read content from RSS feeds', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input',
                'flow_id' => $context['flow_id'] ?? '',
                'pipeline_id' => $context['pipeline_id'] ?? '',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        return $content;
    }, 10, 2);
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // RSS handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_rss_input_filters();