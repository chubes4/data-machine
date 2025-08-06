<?php
/**
 * Google Sheets Input Handler
 *
 * Reads data from Google Sheets for the Data Machine pipeline.
 * This handler is responsible for fetching data from Google spreadsheets,
 * parsing the data, and converting it into standardized DataPackets for processing.
 *
 * Reuses the existing Google Sheets OAuth infrastructure from the output handler
 * to provide seamless bi-directional Google Sheets integration.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/input/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\GoogleSheets;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GoogleSheetsInput {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get service via filter system.
     *
     * @param string $service_name Service name.
     * @return mixed Service instance.
     */
    private function get_service(string $service_name) {
        return apply_filters('dm_get_' . $service_name, null);
    }

    /**
     * Fetches and prepares input data packets from a Google Sheets spreadsheet.
     *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler.
     * @return array Array containing 'processed_items' key with standardized data packets for sheet rows.
     * @throws Exception If data cannot be retrieved or is invalid.
     */
    public function get_input_data(object $module, array $source_config): array {
        // Direct filter-based validation
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        

        $logger = apply_filters('dm_get_logger', null);
        $logger?->debug('Google Sheets Input: Starting Google Sheets data processing.', ['module_id' => $module_id]);

        // Access config from nested structure
        $config = $source_config['googlesheets_input'] ?? [];
        
        // Configuration validation
        $spreadsheet_id = trim($config['spreadsheet_id'] ?? '');
        if (empty($spreadsheet_id)) {
            throw new Exception(esc_html__('Google Sheets spreadsheet ID is required.', 'data-machine'));
        }
        
        $worksheet_name = trim($config['worksheet_name'] ?? 'Sheet1');
        $cell_range = trim($config['cell_range'] ?? 'A1:Z1000');
        $has_header_row = !empty($config['has_header_row']);
        $process_limit = max(1, absint($config['row_limit'] ?? 100));

        // Get Google Sheets authentication service
        $all_auth = apply_filters('dm_get_auth_providers', []);
        $auth_service = $all_auth['googlesheets'] ?? null;
        if (!$auth_service) {
            throw new Exception(esc_html__('Google Sheets authentication service not available.', 'data-machine'));
        }

        // Get authenticated access token
        $access_token = $auth_service->get_service($user_id);
        if (is_wp_error($access_token)) {
            throw new Exception(sprintf(
                /* translators: %s: error message */
                esc_html__('Google Sheets authentication failed: %s', 'data-machine'),
                esc_html($access_token->get_error_message())
            ));
        }

        // Build Google Sheets API URL
        $range_param = urlencode($worksheet_name . '!' . $cell_range);
        $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range_param}";
        
        $logger?->debug('Google Sheets Input: Fetching spreadsheet data.', [
            'spreadsheet_id' => $spreadsheet_id,
            'worksheet_name' => $worksheet_name,
            'range' => $cell_range,
            'module_id' => $module_id
        ]);

        // Make API request
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
            'user-agent' => 'DataMachine WordPress Plugin/' . DATA_MACHINE_VERSION
        ]);

        if (is_wp_error($response)) {
            throw new Exception(sprintf(
                /* translators: %s: error message */
                esc_html__('Failed to fetch Google Sheets data: %s', 'data-machine'),
                esc_html($response->get_error_message())
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            
            throw new Exception(sprintf(
                /* translators: %1$d: HTTP status code, %2$s: error message */
                esc_html__('Google Sheets API request failed (HTTP %1$d): %2$s', 'data-machine'),
                $response_code,
                esc_html($error_message)
            ));
        }

        $sheet_data = json_decode($response_body, true);
        if (empty($sheet_data['values'])) {
            $logger?->debug('Google Sheets Input: No data found in specified range.', ['module_id' => $module_id]);
            return ['processed_items' => []];
        }

        $rows = $sheet_data['values'];
        $logger?->debug('Google Sheets Input: Retrieved spreadsheet data.', [
            'total_rows' => count($rows),
            'module_id' => $module_id
        ]);

        // Process header row if present
        $headers = [];
        $data_start_index = 0;
        
        if ($has_header_row && !empty($rows)) {
            $headers = array_map('trim', $rows[0]);
            $data_start_index = 1;
            $logger?->debug('Google Sheets Input: Using header row.', [
                'headers' => $headers,
                'module_id' => $module_id
            ]);
        }

        // Process data rows
        $eligible_items_packets = [];
        $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
        $rows_processed = 0;

        for ($i = $data_start_index; $i < count($rows) && $rows_processed < $process_limit; $i++) {
            $row = $rows[$i];
            
            // Skip empty rows
            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }

            // Create unique identifier for this row
            $row_identifier = $spreadsheet_id . '_' . $worksheet_name . '_row_' . ($i + 1);
            
            // Check if already processed
            if ($processed_items_manager && $processed_items_manager->is_processed($module_id, $row_identifier, 'googlesheets_input')) {
                $logger?->debug('Google Sheets Input: Skipping already processed row.', [
                    'row_identifier' => $row_identifier,
                    'module_id' => $module_id
                ]);
                continue;
            }

            // Build content string
            $content_parts = [];
            $content_parts[] = "Source: Google Sheets";
            $content_parts[] = "Spreadsheet: " . $spreadsheet_id;
            $content_parts[] = "Worksheet: " . $worksheet_name;
            $content_parts[] = "Row: " . ($i + 1);
            $content_parts[] = "";
            
            // Process row data
            $row_data = [];
            foreach ($row as $col_index => $cell_value) {
                $cell_value = trim($cell_value);
                if (empty($cell_value)) {
                    continue;
                }
                
                $column_key = isset($headers[$col_index]) ? $headers[$col_index] : 'Column_' . chr(65 + $col_index);
                $row_data[$column_key] = $cell_value;
                $content_parts[] = $column_key . ": " . $cell_value;
            }
            
            if (empty($row_data)) {
                continue; // Skip rows with no meaningful data
            }

            $content_string = implode("\n", $content_parts);

            // Create metadata
            $metadata = [
                'source_type' => 'googlesheets_input',
                'item_identifier_to_log' => $row_identifier,
                'original_id' => $row_identifier,
                'source_url' => "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/edit",
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'row_number' => $i + 1,
                'row_data' => $row_data,
                'headers' => $headers,
                'original_date_gmt' => gmdate('Y-m-d\TH:i:s\Z') // Current timestamp as creation time
            ];

            $input_data_packet = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => null // No file info for spreadsheet data
                ],
                'metadata' => $metadata
            ];
            
            $eligible_items_packets[] = $input_data_packet;
            $rows_processed++;
            
            $logger?->debug('Google Sheets Input: Processed spreadsheet row.', [
                'row_identifier' => $row_identifier,
                'row_number' => $i + 1,
                'module_id' => $module_id
            ]);
        }

        $found_count = count($eligible_items_packets);
        $logger?->debug('Google Sheets Input: Finished processing Google Sheets data.', [
            'found_count' => $found_count,
            'total_rows' => count($rows),
            'module_id' => $module_id
        ]);

        return ['processed_items' => $eligible_items_packets];
    }

    /**
     * Sanitize settings for the Google Sheets input handler.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        
        // Spreadsheet ID is required
        $spreadsheet_id = sanitize_text_field($raw_settings['spreadsheet_id'] ?? '');
        if (empty($spreadsheet_id)) {
            throw new \InvalidArgumentException(esc_html__('Google Sheets Spreadsheet ID is required.', 'data-machine'));
        }
        $sanitized['spreadsheet_id'] = $spreadsheet_id;
        
        // Worksheet name
        $sanitized['worksheet_name'] = sanitize_text_field($raw_settings['worksheet_name'] ?? 'Sheet1');
        
        // Cell range
        $cell_range = sanitize_text_field($raw_settings['cell_range'] ?? 'A1:Z1000');
        // Basic validation for A1 notation
        if (!preg_match('/^[A-Z]+\d+:[A-Z]+\d+$/', $cell_range)) {
            throw new \InvalidArgumentException(esc_html__('Invalid cell range format. Use A1 notation (e.g., A1:D100).', 'data-machine'));
        }
        $sanitized['cell_range'] = $cell_range;
        
        // Header row option
        $sanitized['has_header_row'] = !empty($raw_settings['has_header_row']);
        
        // Row limit
        $sanitized['row_limit'] = max(1, min(1000, absint($raw_settings['row_limit'] ?? 100)));
        
        return $sanitized;
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('Google Sheets Input', 'data-machine');
    }
}