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
    
    // Metadata parameter injection - Google Sheets Fetch specific
    add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
        // Only process for steps that come after googlesheets_fetch
        if (empty($data) || !is_array($data)) {
            return $parameters;
        }
        
        $latest_entry = $data[0] ?? [];
        $metadata = $latest_entry['metadata'] ?? [];
        $source_type = $metadata['source_type'] ?? '';
        
        // Only inject Google Sheets Fetch metadata
        if ($source_type === 'googlesheets_fetch') {
            // Add Google Sheets Fetch specific parameters to flat structure
            $parameters['source_url'] = $metadata['source_url'] ?? '';
            $parameters['spreadsheet_id'] = $metadata['spreadsheet_id'] ?? '';
            $parameters['worksheet_name'] = $metadata['worksheet_name'] ?? '';
            $parameters['row_number'] = $metadata['row_number'] ?? 0;
            $parameters['row_data'] = $metadata['row_data'] ?? [];
            $parameters['headers'] = $metadata['headers'] ?? [];
            $parameters['original_id'] = $metadata['original_id'] ?? '';
            
            do_action('dm_log', 'debug', 'Google Sheets Fetch: Metadata injected into engine parameters', [
                'flow_step_id' => $flow_step_id,
                'spreadsheet_id' => $parameters['spreadsheet_id'],
                'worksheet_name' => $parameters['worksheet_name'],
                'row_number' => $parameters['row_number']
            ]);
        }
        
        return $parameters;
    }, 10, 5);
    
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