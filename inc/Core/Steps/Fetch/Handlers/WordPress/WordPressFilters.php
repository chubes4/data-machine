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
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

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

    // Handler registration - WordPress Posts declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['wordpress_posts'] = [
                'type' => 'fetch',
                'class' => WordPress::class,
                'label' => __('Local WordPress Posts', 'data-machine'),
                'description' => __('Fetch posts and pages from this WordPress installation', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);


    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_posts') {
            $all_settings['wordpress_posts'] = new WordPressSettings();
        }
        return $all_settings;
    }, 10, 2);

    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_fetch_filters();