<?php
/**
 * WordPress REST API Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as WordPress REST API Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\WordPressAPI
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPressAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all WordPress REST API Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers WordPress REST API Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 1.0.0
 */
function dm_register_wordpress_api_fetch_filters() {

    // Handler registration - WordPress REST API declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers_uncached', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['wordpress_api'] = [
                'type' => 'fetch',
                'class' => WordPressAPI::class,
                'label' => __('WordPress REST API', 'data-machine'),
                'description' => __('Fetch content from public WordPress sites via REST API', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_api') {
            $all_settings['wordpress_api'] = new WordPressAPISettings();
        }
        return $all_settings;
    }, 10, 2);

}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_api_fetch_filters();