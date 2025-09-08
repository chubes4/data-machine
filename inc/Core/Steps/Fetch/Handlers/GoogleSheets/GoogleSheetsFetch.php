<?php
/**
 * Google Sheets Fetch Handler
 *
 * Reads data from Google Sheets for the Data Machine pipeline.
 * This handler is responsible for fetching data from Google spreadsheets,
 * parsing the data, and converting it into standardized DataPackets for processing.
 *
 * Reuses the existing Google Sheets OAuth infrastructure from the publish handler
 * to provide seamless bi-directional Google Sheets integration.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GoogleSheetsFetch {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }


    /**
     * Fetches and prepares fetch data packets from a Google Sheets spreadsheet.
     *
     * @param int $pipeline_id The pipeline ID for this execution context.
     * @param array  $handler_config Decoded handler configuration specific to this handler.
     * @param string|null $job_id The job ID for processed items tracking.
     * @return array Array containing 'processed_items' key with standardized data packets for sheet rows.
     * @throws Exception If data cannot be retrieved or is invalid.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        do_action('dm_log', 'debug', 'Google Sheets Fetch: Starting Google Sheets data processing.', ['pipeline_id' => $pipeline_id]);

        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'Google Sheets Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        // Extract flow_step_id from handler config for processed items tracking
        $flow_step_id = $handler_config['flow_step_id'] ?? null;

        // Access config from handler config structure
        $config = $handler_config['googlesheets_fetch'] ?? [];
        
        // Configuration validation
        $spreadsheet_id = trim($config['spreadsheet_id'] ?? '');
        if (empty($spreadsheet_id)) {
            do_action('dm_log', 'error', 'Google Sheets Input: Spreadsheet ID is required.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        $worksheet_name = trim($config['worksheet_name'] ?? 'Sheet1');
        $cell_range = trim($config['cell_range'] ?? 'A1:Z1000');
        $has_header_row = !empty($config['has_header_row']);
        $process_limit = max(1, absint($config['row_limit'] ?? 100));

        // Get Google Sheets authentication service
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_service = $all_auth['googlesheets'] ?? null;
        if (!$auth_service) {
            do_action('dm_log', 'error', 'Google Sheets Input: Authentication service not available.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        // Get authenticated access token
        $access_token = $auth_service->get_service();
        if (is_wp_error($access_token)) {
            do_action('dm_log', 'error', 'Google Sheets Input: Authentication failed.', [
                'pipeline_id' => $pipeline_id,
                'error' => $access_token->get_error_message()
            ]);
            return ['processed_items' => []];
        }

        // Build Google Sheets API URL
        $range_param = urlencode($worksheet_name . '!' . $cell_range);
        $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range_param}";
        
        do_action('dm_log', 'debug', 'Google Sheets Fetch: Fetching spreadsheet data.', [
            'spreadsheet_id' => $spreadsheet_id,
            'worksheet_name' => $worksheet_name,
            'range' => $cell_range,
            'pipeline_id' => $pipeline_id
        ]);

        // Make API request
        $result = apply_filters('dm_request', null, 'GET', $api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'user-agent' => 'DataMachine WordPress Plugin/' . DATA_MACHINE_VERSION
        ], 'Google Sheets API');

        if (!$result['success']) {
            do_action('dm_log', 'error', 'Google Sheets Input: Failed to fetch data.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'spreadsheet_id' => $spreadsheet_id
            ]);
            return ['processed_items' => []];
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            
            do_action('dm_log', 'error', 'Google Sheets Input: API request failed.', [
                'pipeline_id' => $pipeline_id,
                'status_code' => $response_code,
                'error_message' => $error_message,
                'spreadsheet_id' => $spreadsheet_id
            ]);
            return ['processed_items' => []];
        }

        $sheet_data = json_decode($response_body, true);
        if (empty($sheet_data['values'])) {
            do_action('dm_log', 'debug', 'Google Sheets Fetch: No data found in specified range.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        $rows = $sheet_data['values'];
        do_action('dm_log', 'debug', 'Google Sheets Fetch: Retrieved spreadsheet data.', [
            'total_rows' => count($rows),
            'pipeline_id' => $pipeline_id
        ]);

        // Process header row if present
        $headers = [];
        $data_start_index = 0;
        
        if ($has_header_row && !empty($rows)) {
            $headers = array_map('trim', $rows[0]);
            $data_start_index = 1;
            do_action('dm_log', 'debug', 'Google Sheets Fetch: Using header row.', [
                'headers' => $headers,
                'pipeline_id' => $pipeline_id
            ]);
        }

        // Process data rows
        $eligible_items_packets = [];
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
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'googlesheets_fetch', $row_identifier);
            if ($is_processed) {
                do_action('dm_log', 'debug', 'Google Sheets Fetch: Skipping already processed row.', [
                    'row_identifier' => $row_identifier,
                    'pipeline_id' => $pipeline_id
                ]);
                continue;
            }
            
            // Mark item as processed immediately after confirming eligibility
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'googlesheets_fetch', $row_identifier, $job_id);
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
                
                $column_key = $headers[$col_index] ?? 'Column_' . chr(65 + $col_index);
                $row_data[$column_key] = $cell_value;
                $content_parts[] = $column_key . ": " . $cell_value;
            }
            
            if (empty($row_data)) {
                continue; // Skip rows with no meaningful data
            }

            $content_string = implode("\n", $content_parts);

            // Create metadata
            $metadata = [
                'source_type' => 'googlesheets_fetch',
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

            $fetch_data = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => null // No file info for spreadsheet data
                ],
                'metadata' => $metadata
            ];
            
            $eligible_items_packets[] = $fetch_data;
            $rows_processed++;
            
            do_action('dm_log', 'debug', 'Google Sheets Fetch: Processed spreadsheet row.', [
                'row_identifier' => $row_identifier,
                'row_number' => $i + 1,
                'pipeline_id' => $pipeline_id
            ]);
        }

        $found_count = count($eligible_items_packets);
        do_action('dm_log', 'debug', 'Google Sheets Fetch: Finished processing Google Sheets data.', [
            'found_count' => $found_count,
            'total_rows' => count($rows),
            'pipeline_id' => $pipeline_id
        ]);

        return ['processed_items' => $eligible_items_packets];
    }

    /**
     * Sanitize settings for the Google Sheets fetch handler.
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
        return __('Google Sheets Fetch', 'data-machine');
    }
}