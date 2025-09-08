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
    
    // Metadata parameter injection - RSS specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after RSS fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }
        
        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';
        
        // Only inject RSS metadata
        if ($source_type === 'rss') {
            // Add RSS specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['original_date_gmt'] = $metadata['original_date_gmt'] ?? '';
            $parameters['author'] = $metadata['author'] ?? '';
            $parameters['categories'] = $metadata['categories'] ?? [];
            $parameters['feed_url'] = $metadata['feed_url'] ?? '';
            $parameters['image_urls'] = $metadata['image_urls'] ?? [];
            
            do_action('dm_log', 'debug', 'RSS: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'source_url' => $parameters['source_url'],
                'feed_url' => $parameters['feed_url'],
                'image_count' => count($parameters['image_urls'])
            ]);
        }
        
        return $parameters;
    }, 10, 5);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_rss_fetch_filters();