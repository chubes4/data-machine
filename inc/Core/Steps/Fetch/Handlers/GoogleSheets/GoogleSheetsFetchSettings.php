<?php
/**
 * Google Sheets Fetch Handler Settings
 *
 * Defines settings fields and sanitization for Google Sheets fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class GoogleSheetsFetchSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for Google Sheets fetch handler.
     *
    * @return array Associative array defining the settings fields.
    */
    public static function get_fields(): array {
        return [
            'googlesheets_fetch_spreadsheet_id' => [
                'type' => 'text',
                'label' => __('Spreadsheet ID', 'datamachine'),
                'description' => __('Google Sheets ID from the URL (e.g., 1abc...xyz from docs.google.com/spreadsheets/d/1abc...xyz/edit). The spreadsheet must be accessible by your authenticated Google account.', 'datamachine'),
                'placeholder' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                'required' => true
            ],
            'googlesheets_fetch_worksheet_name' => [
                'type' => 'text',
                'label' => __('Worksheet Name', 'datamachine'),
                'description' => __('Name of the specific worksheet/tab within the spreadsheet to read data from.', 'datamachine'),
                'placeholder' => 'Sheet1',
                'default' => 'Sheet1'
            ],
            'googlesheets_fetch_processing_mode' => [
                'type' => 'select',
                'label' => __('Processing Mode', 'datamachine'),
                'description' => __('How to process the spreadsheet data. By Row: Process one row at a time (ideal for lists). By Column: Process one column at a time. Full Spreadsheet: Process entire sheet at once.', 'datamachine'),
                'options' => [
                    'by_row' => __('By Row (Sequential)', 'datamachine'),
                    'by_column' => __('By Column (Sequential)', 'datamachine'),
                    'full_spreadsheet' => __('Full Spreadsheet (All at Once)', 'datamachine')
                ],
                'default' => 'by_row'
            ],
            'googlesheets_fetch_has_header_row' => [
                'type' => 'checkbox',
                'label' => __('First Row Contains Headers', 'datamachine'),
                'description' => __('Check if the first row contains column headers. Headers will be used as field names in the processed data.', 'datamachine'),
                'default' => true
            ]
        ];
    }

    /**
     * Sanitize Google Sheets fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        
        // Sanitize spreadsheet ID (should be alphanumeric with hyphens/underscores)
        $spreadsheet_id = sanitize_text_field($raw_settings['googlesheets_fetch_spreadsheet_id'] ?? '');
        $sanitized['googlesheets_fetch_spreadsheet_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $spreadsheet_id);
        
        // Sanitize worksheet name
        $sanitized['googlesheets_fetch_worksheet_name'] = sanitize_text_field($raw_settings['googlesheets_fetch_worksheet_name'] ?? 'Sheet1');
        
        // Processing mode validation
        $processing_mode = sanitize_text_field($raw_settings['googlesheets_fetch_processing_mode'] ?? 'by_row');
        $valid_modes = ['by_row', 'by_column', 'full_spreadsheet'];
        $sanitized['googlesheets_fetch_processing_mode'] = in_array($processing_mode, $valid_modes) ? $processing_mode : 'by_row';
        
        // Header row option
        $sanitized['googlesheets_fetch_has_header_row'] = !empty($raw_settings['googlesheets_fetch_has_header_row']);
        
        return $sanitized;
    }


    /**
     * Get help text explaining how to find spreadsheet ID.
     *
     * @return string Help text with HTML formatting.
     */
    public static function get_spreadsheet_id_help(): string {
        return sprintf(
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            __('To find your Spreadsheet ID: Open your Google Sheet, copy the ID from the URL between /d/ and /edit. Example: docs.google.com/spreadsheets/d/<strong>SPREADSHEET_ID_HERE</strong>/edit. %1$sLearn more about Google Sheets API%2$s', 'datamachine'),
            '<a href="https://developers.google.com/sheets/api/guides/concepts" target="_blank" rel="noopener">',
            '</a>'
        );
    }

    /**
     * Get help text explaining A1 notation for cell ranges.
     *
     * @return string Help text with HTML formatting.
     */
    public static function get_cell_range_help(): string {
        return sprintf(
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            __('Cell ranges use A1 notation. Examples: A1:D10 (columns A-D, rows 1-10), B2:Z1000 (columns B-Z starting from row 2). %1$sLearn more about A1 notation%2$s', 'datamachine'),
            '<a href="https://developers.google.com/sheets/api/guides/concepts#a1_notation" target="_blank" rel="noopener">',
            '</a>'
        );
    }

    /**
     * Validate that required authentication is available.
     *
     * @param int $user_id User ID to check authentication for.
     * @return bool|\WP_Error True if authenticated, WP_Error if not.
     */
    public static function validate_authentication(int $user_id) {
        $all_auth = apply_filters('datamachine_auth_providers', []);
        $auth_service = $all_auth['googlesheets'] ?? null;
        if (!$auth_service) {
            return new \WP_Error('googlesheets_auth_unavailable', __('Google Sheets authentication service not available.', 'datamachine'));
        }

        if (!$auth_service->is_authenticated()) {
            return new \WP_Error('googlesheets_not_authenticated', __('Google Sheets authentication required. Please authenticate in the API Keys settings.', 'datamachine'));
        }

        return true;
    }

    /**
     * Get available field types for data processing context.
     *
     * @return array Available field types with descriptions.
     */
    public static function get_data_context_info(): array {
        return [
            'row_data' => __('Individual row data as key-value pairs', 'datamachine'),
            'headers' => __('Column headers (if header row enabled)', 'datamachine'),
            'row_number' => __('Source row number in spreadsheet', 'datamachine'),
            'spreadsheet_id' => __('Google Sheets spreadsheet identifier', 'datamachine'),
            'worksheet_name' => __('Name of the worksheet/tab', 'datamachine'),
            'source_url' => __('Direct link to the Google Sheets document', 'datamachine')
        ];
    }
}