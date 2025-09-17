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
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_posts'] = [
            'type' => 'fetch',
            'class' => WordPress::class,
            'label' => __('Local WordPress Posts', 'data-machine'),
            'description' => __('Fetch posts and pages from this WordPress installation', 'data-machine')
        ];
        return $handlers;
    });
    
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_posts'] = new WordPressSettings();
        return $all_settings;
    });
    
    // Metadata parameter injection - WordPress Local specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after wordpress_local fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }

        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';

        // Only inject WordPress Local metadata
        if ($source_type === 'wordpress_local') {
            // Add WordPress Local specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['image_url'] = $metadata['image_url'] ?? '';
            $parameters['original_date_gmt'] = $metadata['original_date_gmt'] ?? '';

            do_action('dm_log', 'debug', 'WordPress Local: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'source_url' => $parameters['source_url'],
                'original_id' => $parameters['original_id'],
                'image_url' => $parameters['image_url']
            ]);
        }

        return $parameters;
    }, 10, 5);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_fetch_filters();