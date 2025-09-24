<?php
/**
 * Flows Database Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
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
    
    // Database service registration - Pure discovery pattern (collection building)
    add_filter('dm_db', function($services) {
        if (!isset($services['flows'])) {
            $services['flows'] = new Flows();
        }
        return $services;
    });

    // Cache clearing integration - respond to clear all cache action
    add_action('dm_clear_all_cache', function() {
        // Clear FLOW_PATTERN cache using new bulk clearing action
        do_action('dm_clear_all_flows_cache');
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_flows_database_filters();