<?php
/**
 * WordPress Media Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress Media Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressMedia
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressMedia;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress Media Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress Media Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 1.0.0
 */
function dm_register_wordpress_media_fetch_filters() {
    
    // Handler registration - WordPress Media declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_media'] = [
            'type' => 'fetch',
            'class' => WordPressMedia::class,
            'label' => __('WordPress Media', 'data-machine'),
            'description' => __('Source attached images and media from WordPress media library', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_media'] = new WordPressMediaSettings();
        return $all_settings;
    });
    
    // WordPress Media-specific parameter injection removed - now handled by engine-level extraction
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_media_fetch_filters();