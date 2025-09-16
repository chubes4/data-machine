<?php
/**
 * Google Sheets Fetch Handler Component Filter Registration
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * This file serves as Google Sheets Fetch Handler's complete interface contract with the engine,
 * demonstrating complete self-containment and zero bootstrap dependencies.
 * Each handler component manages its own filter registration.
 * 
 * Reuses existing Google Sheets OAuth infrastructure from publish handler for
 * seamless bi-directional integration.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all Google Sheets Fetch Handler component filters
 * 
 * Complete self-registration pattern following "plugins within plugins" architecture.
 * Engine discovers Google Sheets Fetch Handler capabilities purely through filter-based discovery.
 * 
 * @since NEXT_VERSION
 */
function dm_register_googlesheets_fetch_filters() {
    
    // Handler registration - Google Sheets Fetch declares itself as fetch handler (pure discovery mode)
    add_filter('dm_handlers', function($handlers) {
        $handlers['googlesheets_fetch'] = [
            'type' => 'fetch',
            'class' => GoogleSheetsFetch::class,
            'label' => __('Google Sheets', 'data-machine'),
            'description' => __('Read data from Google Sheets spreadsheets', 'data-machine')
        ];
        return $handlers;
    });
    
    // Settings registration - parameter-matched to 'googlesheets_fetch' handler
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['googlesheets_fetch'] = new GoogleSheetsFetchSettings();
        return $all_settings;
    });
    
    // Google Sheets Fetch-specific parameter injection removed - now handled by engine-level extraction
    
    // Modal registrations removed - now handled by generic modal system via pure discovery
    
    // Authentication registration - pure discovery mode
    // This creates bi-directional Google Sheets integration by sharing auth with publish handler
    add_filter('dm_auth_providers', function($providers) {
        // Use shared authentication for both fetch and publish Google Sheets handlers
        $providers['googlesheets'] = new \DataMachine\Core\Steps\Publish\Handlers\GoogleSheets\GoogleSheetsAuth();
        return $providers;
    });
    
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_fetch_filters();