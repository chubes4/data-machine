<?php
/**
 * Pipelines Database Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Pipelines Database component's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Database\Pipelines
 * @since 0.1.0
 */

namespace DataMachine\Core\Database\Pipelines;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Pipelines Database component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Pipelines Database capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_pipelines_database_filters() {
    
    // Database service registration - Pipelines declares itself as 'pipelines' database service
    add_filter('dm_get_database_service', function($service, $type) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if ($type === 'pipelines') {
            static $pipelines_instance = null;
            if ($pipelines_instance === null) {
                $pipelines_instance = new Pipelines();
            }
            return $pipelines_instance;
        }
        
        return $service;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_pipelines_database_filters();