<?php
/**
 * Modular Google Sheets publish handler.
 *
 * Appends structured data to specified Google Sheets for analytics, reporting,
 * and data collection workflows. This modular approach separates concerns
 * between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\GoogleSheets;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GoogleSheets {

    /**
     * @var GoogleSheetsAuth Authentication handler instance
     */
    private $auth;

    /**
     * Constructor - direct auth initialization for security
     */
    public function __construct() {
        // Use filter-based auth access following pure discovery architectural standards
        $all_auth = apply_filters('dm_auth_providers', []);
        $this->auth = $all_auth['googlesheets_output'] ?? null;
    }

    /**
     * Get Google Sheets auth handler - internal implementation.
     * 
     * @return GoogleSheetsAuth
     */
    private function get_auth() {
        return $this->auth;
    }

    /**
     * Handles appending the AI output to Google Sheets.
     *
     * @param object $data Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_publish($data): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $title = $data->content->title ?? '';
        $content = $data->content->body ?? '';
        
        // Get publish config from DataPacket (set by PublishStep)
        $publish_config = $data->publish_config ?? [];
        
        // Extract metadata from DataPacket
        $input_metadata = [
            'source_url' => $data->metadata->source_url ?? null,
            'source_type' => $data->metadata->source_type ?? 'unknown',
            'created_at' => $data->metadata->created_at ?? current_time('c'),
            'job_id' => $data->metadata->job_id ?? null
        ];
        
        do_action('dm_log', 'debug', 'Starting Google Sheets output handling.');
        
        // 1. Get configuration - publish_config is the handler_settings directly
        $spreadsheet_id = $publish_config['googlesheets_spreadsheet_id'] ?? '';
        $worksheet_name = $publish_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';
        $column_mapping = $publish_config['googlesheets_column_mapping'] ?? $this->get_default_column_mapping();

        // 2. Validate required configuration
        if (empty($spreadsheet_id)) {
            do_action('dm_log', 'error', 'Google Sheets Output: Spreadsheet ID is required.');
            return [
                'success' => false,
                'error' => __('Google Sheets spreadsheet ID is required in configuration.', 'data-machine')
            ];
        }

        // 3. Get authenticated Google Sheets service
        $sheets_service = $this->auth->get_service();

        // 4. Handle authentication errors
        if (is_wp_error($sheets_service)) {
             do_action('dm_log', 'error', 'Google Sheets Output Error: Failed to get authenticated service.', [
                'error_code' => $sheets_service->get_error_code(),
                'error_message' => $sheets_service->get_error_message(),
             ]);
             return [
                 'success' => false,
                 'error' => $sheets_service->get_error_message()
             ];
        }

        // 5. Validate content from DataPacket
        if (empty($title) && empty($content)) {
            do_action('dm_log', 'warning', 'Google Sheets Output: DataPacket content is empty.');
            return [
                'success' => false,
                'error' => __('Cannot append empty content to Google Sheets.', 'data-machine')
            ];
        }

        // 6. Prepare row data based on column mapping
        $row_data = $this->prepare_row_data($title, $content, $input_metadata, $column_mapping);

        if (empty($row_data)) {
            do_action('dm_log', 'error', 'Google Sheets Output: Failed to prepare row data.');
            return [
                'success' => false,
                'error' => __('Failed to prepare data for Google Sheets.', 'data-machine')
            ];
        }

        // 7. Append data to Google Sheets
        try {
            $result = $this->append_to_sheet($sheets_service, $spreadsheet_id, $worksheet_name, $row_data);

            if (is_wp_error($result)) {
                do_action('dm_log', 'error', 'Failed to append data to Google Sheets.', [
                    'spreadsheet_id' => $spreadsheet_id,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message()
                ]);
                return [
                    'success' => false,
                    'error' => $result->get_error_message()
                ];
            }

            $sheet_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}";
            do_action('dm_log', 'debug', 'Successfully appended data to Google Sheets.', [
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet' => $worksheet_name
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'output_url' => $sheet_url,
                'message' => sprintf(__('Successfully added data to Google Sheets: %s', 'data-machine'), $worksheet_name),
                'raw_response' => $result
            ];

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Google Sheets Output Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Returns the user-friendly label for this publish handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Append to Google Sheets', 'data-machine');
    }

    /**
     * Sanitizes the settings specific to the Google Sheets publish handler.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $sanitized['googlesheets_spreadsheet_id'] = sanitize_text_field($raw_settings['googlesheets_spreadsheet_id'] ?? '');
        $sanitized['googlesheets_worksheet_name'] = sanitize_text_field($raw_settings['googlesheets_worksheet_name'] ?? 'Data Machine Output');
        
        // Handle JSON column mapping
        $column_mapping = $raw_settings['googlesheets_column_mapping'] ?? '';
        if (!empty($column_mapping)) {
            $decoded = json_decode($column_mapping, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $sanitized['googlesheets_column_mapping'] = $decoded;
            } else {
                $sanitized['googlesheets_column_mapping'] = $this->get_default_column_mapping();
            }
        } else {
            $sanitized['googlesheets_column_mapping'] = $this->get_default_column_mapping();
        }
        
        return $sanitized;
    }

    /**
     * Get default column mapping for Google Sheets output.
     *
     * @return array Default column mapping configuration.
     */
    private function get_default_column_mapping(): array {
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
     * Prepare row data based on column mapping configuration.
     *
     * @param string $title Parsed title from AI output.
     * @param string $content Parsed content from AI output.
     * @param array $metadata Input metadata from DataPacket.
     * @param array $column_mapping Column mapping configuration.
     * @return array Prepared row data for Google Sheets.
     */
    private function prepare_row_data(string $title, string $content, array $metadata, array $column_mapping): array {
        $data_fields = [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'title' => $title,
            'content' => $content,
            'source_url' => $metadata['source_url'] ?? '',
            'source_type' => $metadata['source_type'] ?? '',
            'job_id' => $metadata['job_id'] ?? '',
            'created_at' => $metadata['created_at'] ?? current_time('c')
        ];

        // Map data fields to columns based on configuration
        $row_data = [];
        
        // Sort columns to ensure proper order (A, B, C, etc.)
        ksort($column_mapping);
        
        foreach ($column_mapping as $column => $field_name) {
            $row_data[] = $data_fields[$field_name] ?? '';
        }

        return $row_data;
    }

    /**
     * Append data to Google Sheets using the Sheets API.
     *
     * @param object $sheets_service Authenticated Google Sheets service.
     * @param string $spreadsheet_id Google Sheets spreadsheet ID.
     * @param string $worksheet_name Worksheet name to append to.
     * @param array $row_data Row data to append.
     * @return array|\WP_Error Result array on success, WP_Error on failure.
     */
    private function append_to_sheet($sheets_service, string $spreadsheet_id, string $worksheet_name, array $row_data) {
        
        try {
            // Prepare the range (worksheet name + starting cell)
            $range = $worksheet_name . '!A:Z'; // Full range to allow auto-detection
            
            // Prepare the request body for appending data
            $body = [
                'values' => [$row_data] // Single row of data
            ];

            // Use WordPress HTTP API to make the request to Google Sheets API
            $access_token = $sheets_service; // In a real implementation, this would be the access token
            
            $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append";
            
            $result = apply_filters('dm_request', null, 'POST', $api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($body),
            ], 'Google Sheets API');

            if (!$result['success']) {
                do_action('dm_log', 'error', 'Google Sheets API request failed.', [
                    'error' => $result['error'],
                    'spreadsheet_id' => $spreadsheet_id
                ]);
                return new \WP_Error('googlesheets_api_request_failed', $result['error']);
            }

            $response_code = $result['status_code'];
            $response_body = $result['data'];

            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = $error_data['error']['message'] ?? 'Unknown Google Sheets API error';
                
                do_action('dm_log', 'error', 'Google Sheets API error.', [
                    'response_code' => $response_code,
                    'error_message' => $error_message,
                    'spreadsheet_id' => $spreadsheet_id
                ]);
                
                return new \WP_Error('googlesheets_api_error', 
                    sprintf(__('Google Sheets API error: %s (Code: %d)', 'data-machine'), $error_message, $response_code));
            }

            $result = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                do_action('dm_log', 'error', 'Failed to decode Google Sheets API response.');
                return new \WP_Error('googlesheets_decode_error', __('Invalid response from Google Sheets API.', 'data-machine'));
            }

            return $result;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Exception during Google Sheets append operation: ' . $e->getMessage());
            return new \WP_Error('googlesheets_exception', $e->getMessage());
        }
    }
}