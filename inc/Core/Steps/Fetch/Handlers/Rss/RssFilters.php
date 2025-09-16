<?php
/**
 * RSS Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as RSS Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Rss
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all RSS Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers RSS Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_rss_fetch_filters() {
    
    // Handler registration - RSS declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['rss'] = [
            'type' => 'fetch',
            'class' => Rss::class,
            'label' => __('RSS', 'data-machine'),
            'description' => __('Monitor and process RSS feeds', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'rss' handler
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['rss'] = new RssSettings();
        return $all_settings;
    });
    
    // RSS-specific parameter injection removed - now handled by engine-level extraction
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_rss_fetch_filters();