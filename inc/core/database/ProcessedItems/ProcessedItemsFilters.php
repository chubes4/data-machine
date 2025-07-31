<?php
/**
 * ProcessedItems Database Component Filter Registration
 * 
 * Revolutionary "Plugins Within Plugins" Architecture Implementation
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
    
    // Database service registration - ProcessedItems declares itself as 'processed_items' database service
    add_filter('dm_get_database_service', function($service, $type) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        if ($type === 'processed_items') {
            static $processed_items_instance = null;
            if ($processed_items_instance === null) {
                $processed_items_instance = new ProcessedItems();
            }
            return $processed_items_instance;
        }
        
        return $service;
    }, 10, 2);
    
    // Processed Items Manager service - handles duplicate tracking and item management
    add_filter('dm_get_processed_items_manager', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $manager_instance = null;
        if ($manager_instance === null) {
            $manager_instance = new \DataMachine\Engine\ProcessedItemsManager();
        }
        return $manager_instance;
    }, 10);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_processed_items_database_filters();