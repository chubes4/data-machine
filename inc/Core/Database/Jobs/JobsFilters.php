<?php
/**
 * Jobs Database Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Jobs Database component's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each database component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Database\Jobs
 * @since 0.1.0
 */

namespace DataMachine\Core\Database\Jobs;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Jobs Database component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Jobs Database capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_jobs_database_filters() {
    
    // Database service registration - Pure discovery pattern (collection building)
    add_filter('dm_db', function($services) {
        if (!isset($services['jobs'])) {
            $services['jobs'] = new Jobs();
        }
        return $services;
    });

    // Cache clearing integration - respond to clear all cache action
    add_action('dm_clear_all_cache', function() {
        // Clear all job-related cache patterns (JOB_PATTERN, RECENT_JOBS_PATTERN, FLOW_JOBS_PATTERN)
        do_action('dm_clear_jobs_cache');
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_jobs_database_filters();