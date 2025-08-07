<?php
/**
 * Reddit Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Reddit Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Reddit
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Reddit;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Reddit Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Reddit Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_reddit_input_filters() {
    
    // Handler registration - Reddit declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['reddit'] = [
            'type' => 'input',
            'class' => Reddit::class,
            'label' => __('Reddit', 'data-machine'),
            'description' => __('Fetch posts from subreddits via Reddit API', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_get_auth_providers', function($providers) {
        $providers['reddit'] = new RedditAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['reddit'] = new RedditSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // Reddit handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_reddit_input_filters();