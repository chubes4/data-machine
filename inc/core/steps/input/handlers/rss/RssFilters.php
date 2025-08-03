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
    
    // Handler registration - RSS declares itself as input handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'input') {
            $handlers['rss'] = [
                'class' => Rss::class,
                'label' => __('RSS', 'data-machine'),
                'description' => __('Monitor and process RSS feeds', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'rss' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'rss') {
            return new RssSettings();
        }
        return $settings;
    }, 10, 2);
    
    // Modal content registration - RSS owns its handler-settings modal content
    add_filter('dm_get_modal', function($content, $template) {
        if ($template === 'handler-settings') {
            // Return early if content already provided by another handler
            if ($content !== null) {
                return $content;
            }
            
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            $handler_slug = $context['handler_slug'] ?? '';
            
            // Only handle rss handler
            if ($handler_slug !== 'rss') {
                return $content;
            }
            
            // Use proper filter-based template rendering
            $settings_instance = apply_filters('dm_get_handler_settings', null, 'rss');
            
            return apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
                'handler_slug' => 'rss',
                'handler_config' => [
                    'label' => __('RSS', 'data-machine'),
                    'description' => __('Read content from RSS feeds', 'data-machine')
                ],
                'step_type' => $context['step_type'] ?? 'input',
                'settings_available' => ($settings_instance !== null),
                'handler_settings' => $settings_instance
            ]);
        }
        return $content;
    }, 10, 2);
    
    // DataPacket conversion registration - RSS handler uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'rss') {
            return RssDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_rss_input_filters();