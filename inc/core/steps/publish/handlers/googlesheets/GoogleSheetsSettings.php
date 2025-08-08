<?php
/**
 * Google Sheets Publish Handler Settings
 *
 * Defines settings fields and sanitization for Google Sheets publish handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/publish/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\GoogleSheets;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class GoogleSheetsSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Google Sheets publish handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'googlesheets_spreadsheet_id' => [
                'type' => 'text',
                'label' => __('Spreadsheet ID', 'data-machine'),
                'description' => __('Google Sheets ID from the URL (e.g., 1abc...xyz from docs.google.com/spreadsheets/d/1abc...xyz/edit). The spreadsheet must be accessible by your authenticated Google account.', 'data-machine'),
                'placeholder' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                'required' => true
            ],
            'googlesheets_worksheet_name' => [
                'type' => 'text',
                'label' => __('Worksheet Name', 'data-machine'),
                'description' => __('Name of the specific worksheet/tab within the spreadsheet where data will be appended.', 'data-machine'),
                'placeholder' => 'Data Machine Output',
                'default' => 'Data Machine Output'
            ],
            'googlesheets_column_mapping' => [
                'type' => 'textarea',
                'label' => __('Column Mapping (JSON)', 'data-machine'),
                'description' => __('JSON configuration mapping data fields to spreadsheet columns. Default maps: A=timestamp, B=title, C=content, D=source_url, E=source_type, F=job_id', 'data-machine'),
                'placeholder' => '{"A": "timestamp", "B": "title", "C": "content", "D": "source_url", "E": "source_type", "F": "job_id"}',
                'rows' => 4
            ]
        ];
    }

    /**
     * Sanitize Google Sheets handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        
        // Sanitize spreadsheet ID (should be alphanumeric with hyphens/underscores)
        $spreadsheet_id = sanitize_text_field($raw_settings['googlesheets_spreadsheet_id'] ?? '');
        $sanitized['googlesheets_spreadsheet_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $spreadsheet_id);
        
        // Sanitize worksheet name
        $sanitized['googlesheets_worksheet_name'] = sanitize_text_field($raw_settings['googlesheets_worksheet_name'] ?? 'Data Machine Output');
        
        // Handle JSON column mapping with validation
        $column_mapping_raw = $raw_settings['googlesheets_column_mapping'] ?? '';
        if (!empty($column_mapping_raw)) {
            $decoded = json_decode($column_mapping_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Validate column mapping structure
                $valid_mapping = [];
                $valid_fields = ['timestamp', 'title', 'content', 'source_url', 'source_type', 'job_id', 'created_at'];
                
                foreach ($decoded as $column => $field) {
                    // Sanitize column (should be A-Z)
                    $clean_column = strtoupper(sanitize_text_field($column));
                    if (preg_match('/^[A-Z]+$/', $clean_column)) {
                        // Sanitize field name
                        $clean_field = sanitize_text_field($field);
                        if (in_array($clean_field, $valid_fields)) {
                            $valid_mapping[$clean_column] = $clean_field;
                        }
                    }
                }
                
                $sanitized['googlesheets_column_mapping'] = !empty($valid_mapping) ? $valid_mapping : self::get_default_column_mapping();
            } else {
                $sanitized['googlesheets_column_mapping'] = self::get_default_column_mapping();
            }
        } else {
            $sanitized['googlesheets_column_mapping'] = self::get_default_column_mapping();
        }
        
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'googlesheets_spreadsheet_id' => '',
            'googlesheets_worksheet_name' => 'Data Machine Output',
            'googlesheets_column_mapping' => self::get_default_column_mapping()
        ];
    }

    /**
     * Get default column mapping configuration.
     *
     * @return array Default column mapping.
     */
    private static function get_default_column_mapping(): array {
        return [
            'A' => 'timestamp',    // Column A: Timestamp
            'B' => 'title',        // Column B: Title/Headline
            'C' => 'content',      // Column C: Content/Body  
            'D' => 'source_url',   // Column D: Source URL
            'E' => 'source_type',  // Column E: Source Type (rss, reddit, etc.)
            'F' => 'job_id'        // Column F: Job ID for tracking
        ];
    }

    /**
     * Get available data fields for column mapping.
     *
     * @return array Available data fields with descriptions.
     */
    public static function get_available_fields(): array {
        return [
            'timestamp' => __('Current timestamp when data is processed', 'data-machine'),
            'title' => __('Parsed title/headline from AI output', 'data-machine'),
            'content' => __('Parsed content/body from AI output', 'data-machine'),
            'source_url' => __('Original source URL of the fetch data', 'data-machine'),
            'source_type' => __('Type of fetch source (rss, reddit, files, etc.)', 'data-machine'),
            'job_id' => __('Unique job ID for tracking and debugging', 'data-machine'),
            'created_at' => __('Original creation timestamp from source data', 'data-machine')
        ];
    }
}