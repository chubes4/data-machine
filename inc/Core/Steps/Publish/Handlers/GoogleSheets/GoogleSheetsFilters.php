<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\GoogleSheets
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_googlesheets_filters() {
    add_filter('dm_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $handlers['googlesheets_output'] = [
                'type' => 'publish',
                'class' => GoogleSheets::class,
                'label' => __('Google Sheets', 'data-machine'),
                'description' => __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'data-machine'),
                'requires_auth' => true
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('dm_auth_providers', function($providers, $step_type = null) {
        if ($step_type === null || $step_type === 'publish') {
            $providers['googlesheets_output'] = new GoogleSheetsAuth();
        }
        return $providers;
    }, 10, 2);

    add_filter('dm_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'googlesheets_output') {
            $all_settings['googlesheets_output'] = new GoogleSheetsSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'googlesheets_output') {
            $tools['googlesheets_append'] = dm_get_googlesheets_tool($handler_config);
        }
        return $tools;
    }, 10, 3);
}

function dm_get_googlesheets_tool(array $handler_config = []): array {
    $googlesheets_config = $handler_config['googlesheets_output'] ?? $handler_config;

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

    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }

    $spreadsheet_id = $googlesheets_config['googlesheets_spreadsheet_id'] ?? '';
    $worksheet_name = $googlesheets_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';

    if (!empty($spreadsheet_id)) {
        $tool['description'] = "Append data to Google Sheets worksheet '{$worksheet_name}'";
    }

    return $tool;
}

function dm_register_googlesheets_success_message() {
    add_filter('dm_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'google_sheets_publish' && !empty($tool_result['data']['sheet_url'])) {
            $worksheet = $tool_result['data']['worksheet_name'] ?? 'worksheet';
            return "Data added successfully to {$worksheet} at {$tool_result['data']['sheet_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

dm_register_googlesheets_filters();
dm_register_googlesheets_success_message();