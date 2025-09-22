<?php
/**
 * Google Sheets Fetch Handler - OAuth2-authenticated spreadsheet data retrieval
 *
 * Retrieves clean structured data from Google Sheets without URL pollution.
 * Engine data stored in database for centralized access via dm_engine_data filter.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GoogleSheetsFetch {

    public function __construct() {
        // Filter-based service discovery only
    }


    /**
     * Fetch Google Sheets data with clean content for AI processing.
     * Returns structured data while storing empty engine data in database.
     *
     * @param int $pipeline_id Pipeline ID for logging context
     * @param array $handler_config Handler configuration with flow_step_id and sheet settings
     * @param string|null $job_id Job ID for deduplication tracking
     * @return array Array with 'processed_items' containing clean structured data.
     *               Empty engine data stored in database (no URLs for spreadsheet data).
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
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
        $processing_mode = $config['processing_mode'] ?? 'by_row';
        $has_header_row = !empty($config['has_header_row']);

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

        // Build Google Sheets API URL - get entire worksheet
        $range_param = urlencode($worksheet_name);
        $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range_param}";
        
        do_action('dm_log', 'debug', 'Google Sheets Fetch: Fetching spreadsheet data.', [
            'spreadsheet_id' => $spreadsheet_id,
            'worksheet_name' => $worksheet_name,
            'processing_mode' => $processing_mode,
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
            'processing_mode' => $processing_mode,
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

        // Process based on mode
        switch ($processing_mode) {
            case 'full_spreadsheet':
                return $this->process_full_spreadsheet($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id);
            
            case 'by_column':
                return $this->process_by_column($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id);
            
            case 'by_row':
            default:
                return $this->process_by_row($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id);
        }
    }

    /**
     * Process entire spreadsheet as single data packet.
     */
    private function process_full_spreadsheet($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id) {
        $sheet_identifier = $spreadsheet_id . '_' . $worksheet_name . '_full';
        
        // Check if already processed
        $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'googlesheets_fetch', $sheet_identifier);
        if ($is_processed) {
            do_action('dm_log', 'debug', 'Google Sheets Fetch: Full spreadsheet already processed.', [
                'sheet_identifier' => $sheet_identifier,
                'pipeline_id' => $pipeline_id
            ]);
            return ['processed_items' => []];
        }
        
        // Mark as processed
        if ($flow_step_id) {
            do_action('dm_mark_item_processed', $flow_step_id, 'googlesheets_fetch', $sheet_identifier, $job_id);
        }

        // Build data for all rows
        $all_data = [];
        for ($i = $data_start_index; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }
            
            $row_data = [];
            foreach ($row as $col_index => $cell_value) {
                $cell_value = trim($cell_value);
                if (!empty($cell_value)) {
                    $column_key = $headers[$col_index] ?? 'Column_' . chr(65 + $col_index);
                    $row_data[$column_key] = $cell_value;
                }
            }
            
            if (!empty($row_data)) {
                $all_data[] = $row_data;
            }
        }

        $metadata = [
            'source_type' => 'googlesheets_fetch',
            'processing_mode' => 'full_spreadsheet',
            'spreadsheet_id' => $spreadsheet_id,
            'worksheet_name' => $worksheet_name,
            'headers' => $headers,
            'total_rows' => count($all_data)
        ];

        $content_data = [
            'title' => 'Google Sheets Data: ' . $worksheet_name,
            'content' => json_encode($all_data, JSON_PRETTY_PRINT)
        ];
        $fetch_data = [
            'data' => array_merge($content_data, ['file_info' => null]),
            'metadata' => $metadata
        ];

        // Store empty engine data for downstream handlers
        if ($job_id) {
            $all_databases = apply_filters('dm_db', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $db_jobs->store_engine_data($job_id, [
                    'source_url' => '',
                    'image_url' => ''
                ]);
            }
        }

        do_action('dm_log', 'debug', 'Google Sheets Fetch: Processed full spreadsheet.', [
            'total_rows' => count($all_data),
            'pipeline_id' => $pipeline_id
        ]);

        return ['processed_items' => [$fetch_data]];
    }

    /**
     * Process spreadsheet rows individually with deduplication.
     */
    private function process_by_row($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id) {
        // Find next unprocessed row
        for ($i = $data_start_index; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Skip empty rows
            if (empty(array_filter($row, 'strlen'))) {
                continue;
            }

            $row_identifier = $spreadsheet_id . '_' . $worksheet_name . '_row_' . ($i + 1);
            
            // Check if already processed
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'googlesheets_fetch', $row_identifier);
            if ($is_processed) {
                continue;
            }
            
            // Mark as processed
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'googlesheets_fetch', $row_identifier, $job_id);
            }

            // Build row data
            $row_data = [];
            foreach ($row as $col_index => $cell_value) {
                $cell_value = trim($cell_value);
                if (!empty($cell_value)) {
                    $column_key = $headers[$col_index] ?? 'Column_' . chr(65 + $col_index);
                    $row_data[$column_key] = $cell_value;
                }
            }
            
            if (empty($row_data)) {
                continue;
            }

            $metadata = [
                'source_type' => 'googlesheets_fetch',
                'processing_mode' => 'by_row',
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'row_number' => $i + 1,
                'headers' => $headers
            ];

            $content_data = [
                'title' => 'Row ' . ($i + 1) . ' Data',
                'content' => json_encode($row_data, JSON_PRETTY_PRINT)
            ];
            $fetch_data = [
                'data' => array_merge($content_data, ['file_info' => null]),
                'metadata' => $metadata
            ];
            
            // Store empty engine data for downstream handlers
            if ($job_id) {
                $all_databases = apply_filters('dm_db', []);
                $db_jobs = $all_databases['jobs'] ?? null;
                if ($db_jobs) {
                    $db_jobs->store_engine_data($job_id, [
                        'source_url' => '',
                        'image_url' => ''
                    ]);
                }
            }

            do_action('dm_log', 'debug', 'Google Sheets Fetch: Processed row.', [
                'row_number' => $i + 1,
                'pipeline_id' => $pipeline_id
            ]);

            return ['processed_items' => [$fetch_data]];
        }

        // No unprocessed rows found
        do_action('dm_log', 'debug', 'Google Sheets Fetch: No unprocessed rows found.', ['pipeline_id' => $pipeline_id]);
        return ['processed_items' => []];
    }

    /**
     * Process spreadsheet columns individually with deduplication.
     */
    private function process_by_column($rows, $headers, $data_start_index, $spreadsheet_id, $worksheet_name, $flow_step_id, $job_id, $pipeline_id) {
        if (empty($rows)) {
            return ['processed_items' => []];
        }

        // Determine max columns
        $max_cols = 0;
        foreach ($rows as $row) {
            $max_cols = max($max_cols, count($row));
        }

        // Find next unprocessed column
        for ($col_index = 0; $col_index < $max_cols; $col_index++) {
            $column_letter = chr(65 + $col_index);
            $column_identifier = $spreadsheet_id . '_' . $worksheet_name . '_col_' . $column_letter;
            
            // Check if already processed
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'googlesheets_fetch', $column_identifier);
            if ($is_processed) {
                continue;
            }
            
            // Build column data
            $column_data = [];
            $column_header = $headers[$col_index] ?? 'Column_' . $column_letter;
            
            for ($i = $data_start_index; $i < count($rows); $i++) {
                $cell_value = trim($rows[$i][$col_index] ?? '');
                if (!empty($cell_value)) {
                    $column_data[] = $cell_value;
                }
            }
            
            if (empty($column_data)) {
                continue;
            }
            
            // Mark as processed
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'googlesheets_fetch', $column_identifier, $job_id);
            }

            $metadata = [
                'source_type' => 'googlesheets_fetch',
                'processing_mode' => 'by_column',
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'column_letter' => $column_letter,
                'column_header' => $column_header,
                'headers' => $headers
            ];

            $content_data = [
                'title' => 'Column: ' . $column_header,
                'content' => json_encode([$column_header => $column_data], JSON_PRETTY_PRINT)
            ];
            $fetch_data = [
                'data' => array_merge($content_data, ['file_info' => null]),
                'metadata' => $metadata
            ];

            // Store empty engine data for downstream handlers
            if ($job_id) {
                $all_databases = apply_filters('dm_db', []);
                $db_jobs = $all_databases['jobs'] ?? null;
                if ($db_jobs) {
                    $db_jobs->store_engine_data($job_id, [
                        'source_url' => '',
                        'image_url' => ''
                    ]);
                }
            }

            do_action('dm_log', 'debug', 'Google Sheets Fetch: Processed column.', [
                'column_letter' => $column_letter,
                'column_header' => $column_header,
                'pipeline_id' => $pipeline_id
            ]);

            return [
                'processed_items' => [$fetch_data]
            ];
        }

        // No unprocessed columns found
        do_action('dm_log', 'debug', 'Google Sheets Fetch: No unprocessed columns found.', ['pipeline_id' => $pipeline_id]);
        return ['processed_items' => []];
    }

    /**
     * Sanitize Google Sheets fetch configuration.
     *
     * @param array $raw_settings Raw configuration array
     * @return array Sanitized configuration
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
        
        // Processing mode validation
        $processing_mode = sanitize_text_field($raw_settings['processing_mode'] ?? 'by_row');
        $valid_modes = ['by_row', 'by_column', 'full_spreadsheet'];
        $sanitized['processing_mode'] = in_array($processing_mode, $valid_modes) ? $processing_mode : 'by_row';
        
        // Header row option
        $sanitized['has_header_row'] = !empty($raw_settings['has_header_row']);
        
        return $sanitized;
    }

    /**
     * Get handler display label.
     *
     * @return string Handler label
     */
    public static function get_label(): string {
        return __('Google Sheets Fetch', 'data-machine');
    }
}