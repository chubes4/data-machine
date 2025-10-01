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
 * @subpackage Core\Steps\Publish\Handlers\GoogleSheets
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;

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

    // Handler registration - Google Sheets declares itself as publish handler (pure discovery mode)
    add_filter('dm_handlers_uncached', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['googlesheets_output'] = [
                'type' => 'publish',
                'class' => GoogleSheets::class,
                'label' => __('Google Sheets', 'data-machine'),
                'description' => __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);

    // Authentication registration - pure discovery mode
    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['googlesheets_output'] = new GoogleSheetsAuth();
        }
        return $providers;
    }, 10, 2);

    // Settings registration - pure discovery mode
    add_filter('dm_handler_settings', function($all_settings, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $all_settings['googlesheets_output'] = new GoogleSheetsSettings();
        }
        return $all_settings;
    }, 10, 2);
    
    // Google Sheets tool registration with AI HTTP Client library
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        // Only generate Google Sheets tool when it's the target handler
        if ($handler_slug === 'googlesheets_output') {
            $tools['googlesheets_append'] = dm_get_googlesheets_tool($handler_config);
        }
        return $tools;
    }, 10, 3);

    
    // Modal registrations removed - now handled by generic modal system via pure discovery
}

/**
 * Get Google Sheets tool definition with dynamic parameters based on configuration.
 *
 * @param array $handler_config Optional handler configuration for dynamic parameters.
 * @return array Google Sheets tool configuration.
 */
function dm_get_googlesheets_tool(array $handler_config = []): array {
    // Extract Google Sheets-specific config from nested structure
    $googlesheets_config = $handler_config['googlesheets_output'] ?? $handler_config;
    
    // Debug logging for tool generation
    if (!empty($handler_config)) {
        do_action('dm_log', 'debug', 'Google Sheets Tool: Generating with configuration', [
            'handler_config_keys' => array_keys($handler_config),
            'googlesheets_config_keys' => array_keys($googlesheets_config),
            'spreadsheet_id' => !empty($googlesheets_config['googlesheets_spreadsheet_id']) ? 'present' : 'missing'
        ]);
    }
    
    // Base tool definition
    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\GoogleSheets\\GoogleSheets',
        'method' => 'handle_tool_call',
        'handler' => 'googlesheets_output',
        'description' => 'Append data to Google Sheets spreadsheet',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Content to append to the spreadsheet'
            ],
            'title' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional title/headline for the entry'
            ],
            'source_url' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional source URL for reference'
            ],
            'source_type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Type of source (e.g., rss, reddit, manual)'
            ],
            'job_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional job ID for tracking'
            ]
        ]
    ];
    
    // Store handler configuration for execution time
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values for description from extracted config
    $spreadsheet_id = $googlesheets_config['googlesheets_spreadsheet_id'] ?? '';
    $worksheet_name = $googlesheets_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';
    
    // Update description based on configuration
    if (!empty($spreadsheet_id)) {
        $tool['description'] = "Append data to Google Sheets worksheet '{$worksheet_name}'";
    }
    
    do_action('dm_log', 'debug', 'Google Sheets Tool: Generation complete', [
        'parameter_count' => count($tool['parameters']),
        'parameter_names' => array_keys($tool['parameters']),
        'has_spreadsheet_id' => !empty($spreadsheet_id),
        'worksheet_name' => $worksheet_name
    ]);
    
    return $tool;
}

/**
 * Register Google Sheets-specific success message formatter.
 */
function dm_register_googlesheets_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'google_sheets_publish' && !empty($tool_result['data']['sheet_url'])) {
            $worksheet = $tool_result['data']['worksheet_name'] ?? 'worksheet';
            return "Data added successfully to {$worksheet} at {$tool_result['data']['sheet_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

// Auto-register when file loads - achieving complete self-containment
dm_register_googlesheets_filters();
dm_register_googlesheets_success_message();