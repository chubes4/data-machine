<?php
/**
 * Threads Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Threads' complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\Threads
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Publish\Threads;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Threads component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Threads capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_threads_filters() {
    
    // Handler registration - Threads declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['threads'] = [
            'type' => 'publish',
            'class' => Threads::class,
            'label' => __('Threads', 'data-machine'),
            'description' => __('Publish content to Threads (Meta\'s Twitter alternative)', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers) {
        $providers['threads'] = new ThreadsAuth();
        return $providers;
    });
    
    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['threads'] = new ThreadsSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

// Auto-register when file loads - achieving complete self-containment
dm_register_threads_filters();