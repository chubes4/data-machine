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
    
    // Reddit-specific parameter injection removed - now handled by engine-level extraction
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_reddit_fetch_filters();