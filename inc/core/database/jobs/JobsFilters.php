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
    add_filter('dm_get_database_services', function($services) {
        if (!isset($services['jobs'])) {
            $services['jobs'] = new Jobs();
        }
        return $services;
    });
    
    // Job Status Manager service - handles job state transitions
    add_filter('dm_get_job_status_manager', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $status_manager_instance = null;
        if ($status_manager_instance === null) {
            $status_manager_instance = new \DataMachine\Engine\JobStatusManager();
        }
        return $status_manager_instance;
    }, 10);
    
    // Job Creator service - handles job creation logic
    add_filter('dm_get_job_creator', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $job_creator_instance = null;
        if ($job_creator_instance === null) {
            $job_creator_instance = new \DataMachine\Engine\JobCreator();
        }
        return $job_creator_instance;
    }, 10);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_jobs_database_filters();