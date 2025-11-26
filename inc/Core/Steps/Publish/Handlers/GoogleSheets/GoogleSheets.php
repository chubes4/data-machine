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
 * @since      0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;

use DataMachine\Core\EngineData;
use DataMachine\Core\Steps\HandlerRegistrationTrait;
use DataMachine\Core\Steps\Publish\Handlers\PublishHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GoogleSheets extends PublishHandler {

    use HandlerRegistrationTrait;

    /**
     * @var GoogleSheetsAuth Authentication handler instance
     */
    private $auth;

    public function __construct() {
        parent::__construct('googlesheets');

        // Self-register with filters
        self::registerHandler(
            'googlesheets_publish',
            'publish',
            self::class,
            'Google Sheets',
            'Append data to Google Sheets for analytics and reporting',
            true,
            \DataMachine\Core\OAuth\Providers\GoogleSheetsAuth::class,
            GoogleSheetsSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'googlesheets_publish') {
                    $tools['googlesheets_publish'] = [
                        'class' => self::class,
                        'method' => 'handle_tool_call',
                        'handler' => 'googlesheets_publish',
                        'description' => 'Append structured data to a Google Sheet. Supports custom headers and data formatting.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'type' => 'array',
                                    'description' => 'Array of data rows to append to the sheet'
                                ]
                            ],
                            'required' => ['data']
                        ]
                    ];
                }
                return $tools;
            },
            'googlesheets'
        );

        // Use shared auth provider via base class method
        $this->auth = $this->getAuthProvider('googlesheets');
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
     * Execute Google Sheets publishing.
     *
     * @param array $parameters Structured parameters from AI tool call.
     * @param array $handler_config Handler configuration.
     * @return array Tool execution result.
     */
    protected function executePublish(array $parameters, array $handler_config): array {
        $this->log('debug', 'Google Sheets Tool: Handling tool call', [
            'parameters' => $parameters,
            'parameter_keys' => array_keys($parameters),
            'has_handler_config' => !empty($handler_config),
            'handler_config_keys' => array_keys($handler_config)
        ]);

        if (empty($parameters['content'])) {
            return $this->errorResponse(
                'Google Sheets tool call missing required content parameter',
                [
                    'provided_parameters' => array_keys($parameters),
                    'required_parameters' => ['content']
                ]
            );
        }

        $this->log('debug', 'Google Sheets Tool: Using handler configuration', [
            'spreadsheet_id' => !empty($handler_config['googlesheets_spreadsheet_id']) ? 'present' : 'missing',
            'worksheet_name' => $handler_config['googlesheets_worksheet_name'] ?? 'Sheet1'
        ]);

            $job_id = (int) $parameters['job_id'];
            $engine = $parameters['engine'] ?? new EngineData([], $job_id);
            if (!$engine instanceof EngineData) {
                $engine = new EngineData([], $job_id);
            }

        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $engine->getSourceUrl();
        $source_type = $parameters['source_type'] ?? 'ai_tool';
        
        // Get config from handler settings
        $spreadsheet_id = $handler_config['googlesheets_spreadsheet_id'] ?? '';
        $worksheet_name = $handler_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';
        $column_mapping = $handler_config['googlesheets_column_mapping'] ?? $this->get_default_column_mapping();

        // Validate spreadsheet ID
        if (empty($spreadsheet_id)) {
            return $this->errorResponse('Google Sheets spreadsheet ID is required');
        }

        // Get authenticated service
        $sheets_service = $this->auth->get_service();
        if (is_wp_error($sheets_service)) {
            return $this->errorResponse(
                'Google Sheets authentication failed: ' . $sheets_service->get_error_message(),
                ['error_code' => $sheets_service->get_error_code()],
                'critical'
            );
        }

        try {
                // Prepare metadata for row data, keeping job context consistent
            $metadata = [
                'source_url' => $source_url,
                'source_type' => $source_type,
                'created_at' => current_time('c'),
                'job_id' => $job_id
            ];

            // Prepare row data based on column mapping
            $row_data = $this->prepare_row_data($title, $content, $metadata, $column_mapping);

            if (empty($row_data)) {
                return $this->errorResponse('Failed to prepare data for Google Sheets');
            }

            // Append data to Google Sheets
            $result = $this->append_to_sheet($sheets_service, $spreadsheet_id, $worksheet_name, $row_data);

            if (is_wp_error($result)) {
                return $this->errorResponse(
                    'Google Sheets API error: ' . $result->get_error_message(),
                    [
                        'error_code' => $result->get_error_code(),
                        'spreadsheet_id' => $spreadsheet_id
                    ]
                );
            }

            $sheet_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}";

            $this->log('debug', 'Google Sheets Tool: Data appended successfully', [
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'sheet_url' => $sheet_url
            ]);

            return $this->successResponse([
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'sheet_url' => $sheet_url,
                'row_data' => $row_data
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }


    /**
     * Returns the user-friendly label for this publish handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('Append to Google Sheets', 'datamachine');
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
            
            $result = apply_filters('datamachine_request', null, 'POST', $api_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($body),
            ], 'Google Sheets API');

            if (!$result['success']) {
                $this->log('error', 'Google Sheets API request failed.', [
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

                $this->log('error', 'Google Sheets API error.', [
                    'response_code' => $response_code,
                    'error_message' => $error_message,
                    'spreadsheet_id' => $spreadsheet_id
                ]);

                return new \WP_Error('googlesheets_api_error',
                    /* translators: %1$s: Error message, %2$d: HTTP response code */
                    sprintf(__('Google Sheets API error: %1$s (Code: %2$d)', 'datamachine'), $error_message, $response_code));
            }

            $result = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('error', 'Failed to decode Google Sheets API response.');
                return new \WP_Error('googlesheets_decode_error', __('Invalid response from Google Sheets API.', 'datamachine'));
            }

            return $result;

        } catch (\Exception $e) {
            $this->log('error', 'Exception during Google Sheets append operation: ' . $e->getMessage());
            return new \WP_Error('googlesheets_exception', $e->getMessage());
        }
    }
}