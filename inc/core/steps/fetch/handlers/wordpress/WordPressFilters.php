<?php
/**
 * WordPress Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Fetch\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Fetch\WordPress;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_wordpress_fetch_filters() {
    
    // Handler registration - WordPress declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_fetch'] = [
            'type' => 'fetch',
            'class' => WordPress::class,
            'label' => __('WordPress', 'data-machine'),
            'description' => __('Source content from WordPress posts and pages', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_fetch'] = new WordPressSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // WordPress handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_fetch_filters();