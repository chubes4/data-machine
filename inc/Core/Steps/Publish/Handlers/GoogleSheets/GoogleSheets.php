<?php
/**
 * Modular Google Sheets publish handler.
 *
 * Appends structured data to specified Google Sheets for analytics, reporting,
 * and data collection workflows. This modular approach separates concerns
 * between main handler logic and authentication functionality.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\GoogleSheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;


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
     * Handle AI tool call for Google Sheets publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $tool_def Tool definition including handler configuration.
     * @return array Tool execution result.
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        do_action('dm_log', 'debug', 'Google Sheets Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($tool_def['handler_config']),
            'handler_config_keys' => array_keys($tool_def['handler_config'] ?? [])
        ]);

        if (empty($parameters['content'])) {
            $error_msg = 'Google Sheets tool call missing required content parameter';
            do_action('dm_log', 'error', $error_msg, [
                'provided_parameters' => array_keys($parameters),
                'required_parameters' => ['content']
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'googlesheets_append'
            ];
        }

        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        do_action('dm_log', 'debug', 'Google Sheets Tool: Using handler configuration', [
            'spreadsheet_id' => !empty($handler_config['googlesheets_spreadsheet_id']) ? 'present' : 'missing',
            'worksheet_name' => $handler_config['googlesheets_worksheet_name'] ?? 'Sheet1'
        ]);

        // Access engine_data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('dm_engine_data', [], $job_id);

        // Extract parameters
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine_data['source_url'] ?? null;
        $source_type = $parameters['source_type'] ?? 'ai_tool';
        
        // Get config from handler settings
        $spreadsheet_id = $handler_config['googlesheets_spreadsheet_id'] ?? '';
        $worksheet_name = $handler_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';
        $column_mapping = $handler_config['googlesheets_column_mapping'] ?? $this->get_default_column_mapping();

        // Validate spreadsheet ID
        if (empty($spreadsheet_id)) {
            return [
                'success' => false,
                'error' => 'Google Sheets spreadsheet ID is required',
                'tool_name' => 'googlesheets_append'
            ];
        }

        // Get authenticated service
        $sheets_service = $this->auth->get_service();
        if (is_wp_error($sheets_service)) {
            $error_msg = 'Google Sheets authentication failed: ' . $sheets_service->get_error_message();
            do_action('dm_log', 'error', $error_msg, [
                'error_code' => $sheets_service->get_error_code()
            ]);
            
            return [
                'success' => false,
                'error' => $error_msg,
                'tool_name' => 'googlesheets_append'
            ];
        }

        try {
            // Prepare metadata for row data
            $metadata = [
                'source_url' => $source_url,
                'source_type' => $source_type,
                'created_at' => current_time('c'),
                'job_id' => $job_id
            ];

            // Prepare row data based on column mapping
            $row_data = $this->prepare_row_data($title, $content, $metadata, $column_mapping);

            if (empty($row_data)) {
                return [
                    'success' => false,
                    'error' => 'Failed to prepare data for Google Sheets',
                    'tool_name' => 'googlesheets_append'
                ];
            }

            // Append data to Google Sheets
            $result = $this->append_to_sheet($sheets_service, $spreadsheet_id, $worksheet_name, $row_data);

            if (is_wp_error($result)) {
                $error_msg = 'Google Sheets API error: ' . $result->get_error_message();
                do_action('dm_log', 'error', $error_msg, [
                    'error_code' => $result->get_error_code(),
                    'spreadsheet_id' => $spreadsheet_id
                ]);

                return [
                    'success' => false,
                    'error' => $error_msg,
                    'tool_name' => 'googlesheets_append'
                ];
            }

            $sheet_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}";
            
            do_action('dm_log', 'debug', 'Google Sheets Tool: Data appended successfully', [
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'sheet_url' => $sheet_url
            ]);

            return [
                'success' => true,
                'data' => [
                    'spreadsheet_id' => $spreadsheet_id,
                    'worksheet_name' => $worksheet_name,
                    'sheet_url' => $sheet_url,
                    'row_data' => $row_data
                ],
                'tool_name' => 'googlesheets_append'
            ];
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Google Sheets Tool: Exception during append operation', [
                'exception' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool_name' => 'googlesheets_append'
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
                    /* translators: %1$s: Error message, %2$d: HTTP response code */
                    sprintf(__('Google Sheets API error: %1$s (Code: %2$d)', 'data-machine'), $error_message, $response_code));
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