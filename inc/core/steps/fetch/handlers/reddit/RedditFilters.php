<?php
/**
 * Reddit Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Reddit Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Reddit
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Reddit Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Reddit Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_reddit_fetch_filters() {
    
    // Handler registration - Reddit declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['reddit'] = [
            'type' => 'fetch',
            'class' => Reddit::class,
            'label' => __('Reddit', 'data-machine'),
            'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['reddit'] = new RedditAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['reddit'] = new RedditSettings();
        return $all_settings;
    });
    
    // Metadata parameter injection - Reddit specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after reddit fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }
        
        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';
        
        // Only inject Reddit metadata
        if ($source_type === 'reddit') {
            // Add Reddit specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            $parameters['original_title'] = $metadata['original_title'] ?? '';
            $parameters['original_date_gmt'] = $metadata['original_date_gmt'] ?? '';
            $parameters['subreddit'] = $metadata['subreddit'] ?? '';
            $parameters['upvotes'] = $metadata['upvotes'] ?? 0;
            $parameters['comment_count'] = $metadata['comment_count'] ?? 0;
            $parameters['author'] = $metadata['author'] ?? '';
            $parameters['is_self_post'] = $metadata['is_self_post'] ?? false;
            
            do_action('dm_log', 'debug', 'Reddit: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'source_url' => $parameters['source_url'],
                'subreddit' => $parameters['subreddit'],
                'upvotes' => $parameters['upvotes']
            ]);
        }
        
        return $parameters;
    }, 10, 5);
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_reddit_fetch_filters();