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
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // RSS handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_rss_input_filters();