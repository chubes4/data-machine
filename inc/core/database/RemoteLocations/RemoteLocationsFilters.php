<?php
/**
 * Remote Locations Database Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Remote Locations Database component's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each database component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Database\RemoteLocations
 * @since 0.1.0
 */

namespace DataMachine\Core\Database\RemoteLocations;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Remote Locations Database component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Remote Locations Database capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_remote_locations_database_filters() {
    
    // Database service registration - Pure discovery pattern (collection building)
    add_filter('dm_get_database_services', function($services) {
        if (!isset($services['remote_locations'])) {
            $services['remote_locations'] = new RemoteLocations();
        }
        return $services;
    });
    
    // Modal content registration - Remote Locations modal for pipeline integration
    add_filter('dm_get_modals', function($modals) {
        // Get Remote Locations service via pure discovery
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_remote_locations = $all_databases['remote_locations'] ?? null;
        
        $modals['remote-locations-manager'] = [
            'template' => 'modal/remote-locations-manager',
            'title' => __('Remote Locations Manager', 'data-machine'),
            'data' => [
                'remote_locations_service' => $db_remote_locations,
                'existing_locations' => $db_remote_locations ? $db_remote_locations->get_all_locations() : []
            ]
        ];
        
        return $modals;
    });
}

// Auto-register when file loads - achieving complete self-containment
dm_register_remote_locations_database_filters();