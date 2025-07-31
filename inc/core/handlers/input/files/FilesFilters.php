<?php
/**
 * Files Input Handler Component Filter Registration
 * 
 * Modular Component System Implementation
 * 
 * This file serves as Files Input Handler's complete interface contract with the engine,
 * demonstrating comprehensive self-containment and systematic organization.
 * Each handler component manages its own filter registration for AI workflow integration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\Files
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Input\Files;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// DEBUG: Log that this file is being loaded
error_log('DEBUG: FilesFilters.php loaded');

/**
 * Register all Files Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Files Input Handler capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_files_input_filters() {
    // DEBUG: Log that this function is being called
    error_log('DEBUG: dm_register_files_input_filters() called');
    
    // Handler registration - Files declares itself as input handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        // DEBUG: Log when this filter gets called
        error_log("DEBUG: Files handler filter called with type: {$type}, handlers: " . print_r($handlers, true));
        
        if ($type === 'input') {
            // Initialize handlers array if null
            if ($handlers === null) {
                $handlers = [];
            }
            
            $handlers['files'] = [
                'class' => Files::class,
                'label' => __('Files', 'data-machine'),
                'description' => __('Process local files and uploads', 'data-machine')
            ];
            
            error_log("DEBUG: Files handler added to handlers array");
        }
        return $handlers;
    }, 10, 2);
    
    // DEBUG: Log that the filter was registered
    error_log('DEBUG: Files dm_get_handlers filter registered');
    
    // Settings registration - parameter-matched to 'files' handler
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'files') {
            return new FilesSettings();
        }
        return $settings;
    }, 10, 2);
    
    // DataPacket conversion registration - Files handler uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'files') {
            return FilesDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_files_input_filters();