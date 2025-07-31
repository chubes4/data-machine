<?php
/**
 * Flows Database Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Flows Database component's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Database\Flows
 * @since 0.1.0
 */

namespace DataMachine\Core\Database\Flows;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Flows Database component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Flows Database capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_flows_database_filters() {
    
    // Database service registration - Flows declares itself as 'flows' database service
    add_filter('dm_get_database_service', function($service, $type) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if ($type === 'flows') {
            static $flows_instance = null;
            if ($flows_instance === null) {
                $flows_instance = new Flows();
            }
            return $flows_instance;
        }
        
        return $service;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_flows_database_filters();