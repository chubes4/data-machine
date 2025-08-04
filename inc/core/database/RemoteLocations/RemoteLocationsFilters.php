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
    
    // Database service registration - Remote Locations declares itself as 'remote_locations' database service
    add_filter('dm_get_database_service', function($service, $type) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if ($type === 'remote_locations') {
            static $remote_locations_instance = null;
            if ($remote_locations_instance === null) {
                $remote_locations_instance = new RemoteLocations();
            }
            return $remote_locations_instance;
        }
        
        return $service;
    }, 10, 2);
    
    // Modal content registration - Remote Locations modal for pipeline integration
    add_filter('dm_get_modal', function($content, $template) {
        // Return early if content already provided by another component
        if ($content !== null) {
            return $content;
        }
        
        if ($template === 'remote-locations-manager') {
            // Get Remote Locations service
            $db_remote_locations = apply_filters('dm_get_database_service', null, 'remote_locations');
            
            return apply_filters('dm_render_template', '', 'modal/remote-locations-manager', [
                'remote_locations_service' => $db_remote_locations,
                'existing_locations' => $db_remote_locations ? $db_remote_locations->get_locations_for_current_user() : []
            ]);
        }
        
        return $content;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_remote_locations_database_filters();