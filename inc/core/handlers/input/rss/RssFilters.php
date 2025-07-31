<?php
/**
 * RSS Input Handler Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
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