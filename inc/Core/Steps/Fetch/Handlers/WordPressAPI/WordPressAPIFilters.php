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
    add_filter('dm_handlers', function($handlers) {
        $handlers['wordpress_api'] = [
            'type' => 'fetch',
            'class' => WordPressAPI::class,
            'label' => __('WordPress REST API', 'data-machine'),
            'description' => __('Fetch content from public WordPress sites via REST API', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['wordpress_api'] = new WordPressAPISettings();
        return $all_settings;
    });
    
    // Metadata parameter injection - WordPress API specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after wordpress_api fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }
        
        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';
        
        // Only inject WordPress API metadata
        if ($source_type === 'wordpress_api') {
            // Add WordPress API specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['image_url'] = $metadata['image_url'] ?? '';
            $parameters['original_date_gmt'] = $metadata['original_date_gmt'] ?? '';
            $parameters['site_url'] = $metadata['site_url'] ?? '';
            $parameters['excerpt'] = $metadata['excerpt'] ?? '';
            
            do_action('dm_log', 'debug', 'WordPress API: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'source_url' => $parameters['source_url'],
                'site_url' => $parameters['site_url'],
                'image_url' => $parameters['image_url']
            ]);
        }
        
        return $parameters;
    }, 10, 5);
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_wordpress_api_fetch_filters();