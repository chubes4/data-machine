<?php
/**
 * Google Sheets Input Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Google Sheets Input Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * Reuses existing Google Sheets OAuth infrastructure from output handler for
 * seamless bi-directional integration.
 * 
 * @package DataMachine
 * @subpackage Core\Handlers\Input\GoogleSheets
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\GoogleSheets;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Google Sheets Input Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Google Sheets Input Handler capabilities purely through filter-based discovery.
 * 
 * @since NEXT_VERSION
 */
function dm_register_googlesheets_input_filters() {
    
    // Handler registration - Google Sheets Input declares itself as input handler (pure discovery mode)
    add_filter('dm_get_handlers', function($handlers) {
        $handlers['googlesheets_input'] = [
            'type' => 'input',
            'class' => GoogleSheetsInput::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'googlesheets_input' handler
    add_filter('dm_get_handler_settings', function($all_settings) {
        $all_settings['googlesheets_input'] = new GoogleSheetsInputSettings();
        return $all_settings;
    });
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // Authentication registration - pure discovery mode
    // This creates bi-directional Google Sheets integration by sharing auth with output handler
    add_filter('dm_get_auth_providers', function($providers) {
        // Use shared authentication for both input and output Google Sheets handlers
        $providers['googlesheets'] = new \DataMachine\Core\Handlers\Output\GoogleSheets\GoogleSheetsAuth();
        return $providers;
    });
    
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // Google Sheets handler returns properly formatted data for direct constructor usage
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_input_filters();