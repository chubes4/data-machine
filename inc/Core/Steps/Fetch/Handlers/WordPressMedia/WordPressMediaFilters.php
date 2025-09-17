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
    
    // Metadata parameter injection - WordPress Media specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after wordpress_media fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }

        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';

        // Only inject WordPress Media metadata
        if ($source_type === 'wordpress_media') {
            // Add WordPress Media specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['image_url'] = $metadata['image_url'] ?? '';
            $parameters['file_path'] = $metadata['file_path'] ?? '';
            $parameters['mime_type'] = $metadata['mime_type'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['file_size'] = $metadata['file_size'] ?? 0;

            do_action('dm_log', 'debug', 'WordPress Media: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'source_url' => $parameters['source_url'],
                'image_url' => $parameters['image_url'],
                'file_path' => $parameters['file_path'],
                'mime_type' => $parameters['mime_type']
            ]);
        }

        return $parameters;
    }, 10, 5);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_media_fetch_filters();