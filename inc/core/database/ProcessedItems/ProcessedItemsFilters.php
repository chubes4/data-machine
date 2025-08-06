<?php
/**
 * ProcessedItems Database Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as ProcessedItems Database component's "main plugin file" - the complete
 * interface contract with the engine, demonstrating complete self-containment
 * and zero bootstrap dependencies.
 * 
 * @package DataMachine
 * @subpackage Core\Database\ProcessedItems
 * @since 0.1.0
 */

namespace DataMachine\Core\Database\ProcessedItems;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all ProcessedItems Database component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers ProcessedItems Database capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_processed_items_database_filters() {
    
    // Database service registration - Pure discovery pattern (collection building)
    add_filter('dm_get_database_services', function($services) {
        if (!isset($services['processed_items'])) {
            $services['processed_items'] = new ProcessedItems();
        }
        return $services;
    });
    
    // Processed Items Manager service - handles duplicate tracking and item management
    add_filter('dm_get_processed_items_manager', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $manager_instance = null;
        if ($manager_instance === null) {
            $manager_instance = new ProcessedItemsManager();
        }
        return $manager_instance;
    }, 10);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_processed_items_database_filters();