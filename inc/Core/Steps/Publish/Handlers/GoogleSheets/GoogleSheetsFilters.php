<?php
/**
 * @package DataMachine\Core\Steps\Publish\Handlers\GoogleSheets
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Sheets handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * with OAuth 2.0 authentication support and AI tool integration.
 *
 * @since 0.2.2
 */
class GoogleSheetsFilters {
    use HandlerRegistrationTrait;

    /**
     * Register Google Sheets publishing handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'googlesheets_output',
            'publish',
            GoogleSheets::class,
            __('Google Sheets', 'datamachine'),
            __('Append structured data to Google Sheets for analytics, reporting, and team collaboration', 'datamachine'),
            true,
            GoogleSheetsAuth::class,
            GoogleSheetsSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'googlesheets_output') {
                    $tools['googlesheets_append'] = datamachine_get_googlesheets_tool($handler_config);
                }
                return $tools;
            }
        );
    }
}

/**
 * Register Google Sheets publishing handler and authentication filters.
 *
 * @since 0.1.0
 */
function datamachine_register_googlesheets_filters() {
    GoogleSheetsFilters::register();
}

function datamachine_get_googlesheets_tool(array $handler_config = []): array {
    // handler_config is ALWAYS flat structure - no nesting

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

    $spreadsheet_id = $handler_config['googlesheets_spreadsheet_id'] ?? '';
    $worksheet_name = $handler_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';

    if (!empty($spreadsheet_id)) {
        $tool['description'] = "Append data to Google Sheets worksheet '{$worksheet_name}'";
    }

    return $tool;
}

function datamachine_register_googlesheets_success_message() {
    add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result, $tool_parameters) {
        if ($tool_name === 'google_sheets_publish' && !empty($tool_result['data']['sheet_url'])) {
            $worksheet = $tool_result['data']['worksheet_name'] ?? 'worksheet';
            return "Data added successfully to {$worksheet} at {$tool_result['data']['sheet_url']}.";
        }
        return $default_message;
    }, 10, 4);
}

datamachine_register_googlesheets_filters();
datamachine_register_googlesheets_success_message();