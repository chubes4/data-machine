<?php
/**
 * Google Sheets Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Google Sheets's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Output\GoogleSheets
 * @since 0.1.0
 */

namespace DataMachine\Core\Handlers\Output\GoogleSheets;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Google Sheets component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Google Sheets capabilities purely through filter-based discovery.
 * 
 * @since 0.1.0
 */
function dm_register_googlesheets_filters() {
    
    // Handler registration - Google Sheets declares itself as output handler
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            // Initialize handlers array if null
            if ($handlers === null) {
                $handlers = [];
            }
            
            $handlers['googlesheets'] = [
                'class' => GoogleSheets::class,
                'label' => __('Google Sheets', 'data-machine'),
                'description' => __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    // Authentication registration - parameter-matched to 'googlesheets' handler
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        if ($handler_slug === 'googlesheets') {
            return new GoogleSheetsAuth();
        }
        return $auth;
    }, 10, 2);
    
    // Settings registration - parameter-matched to 'googlesheets' handler  
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        if ($handler_slug === 'googlesheets') {
            return new GoogleSheetsSettings();
        }
        return $settings;
    }, 10, 2);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_filters();