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
function datamachine_register_wordpress_media_fetch_filters() {

    // Handler registration - WordPress Media declares itself as fetch handler (pure discovery mode)
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'fetch') {
            $handlers['wordpress_media'] = [
                'type' => 'fetch',
                'class' => WordPressMedia::class,
                'label' => __('WordPress Media', 'datamachine'),
                'description' => __('Source attached images and media from WordPress media library', 'datamachine')
            ];
        }
        return $handlers;
    }, 10, 2);


    // Settings registration - pure discovery mode
    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_media') {
            $all_settings['wordpress_media'] = new WordPressMediaSettings();
        }
        return $all_settings;
    }, 10, 2);

}

// Auto-register when file loads - achieving complete self-containment
datamachine_register_wordpress_media_fetch_filters();