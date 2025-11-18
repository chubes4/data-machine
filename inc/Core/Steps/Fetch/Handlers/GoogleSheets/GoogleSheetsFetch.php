<?php
/**
 * Google Sheets fetch handler with OAuth2 authentication.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\GoogleSheets
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\GoogleSheets;

use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GoogleSheetsFetch extends FetchHandler {

	public function __construct() {
		parent::__construct( 'googlesheets_fetch' );
	}

	/**
	 * Fetch Google Sheets data as structured rows.
	 * No engine data stored (no URLs for spreadsheet data).
	 */
	protected function executeFetch(
		int $pipeline_id,
		array $config,
		?string $flow_step_id,
		int $flow_id,
		?string $job_id
	): array {
		if (empty($pipeline_id)) {
			$this->log('error', 'Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
			return $this->emptyResponse();
		}
        
        // Configuration validation
        $spreadsheet_id = trim($config['spreadsheet_id'] ?? '');
        if (empty($spreadsheet_id)) {
            $this->log('error', 'Spreadsheet ID is required.', ['pipeline_id' => $pipeline_id]);
            return $this->emptyResponse();
        }

        $worksheet_name = trim($config['worksheet_name'] ?? 'Sheet1');
        $processing_mode = $config['processing_mode'] ?? 'by_row';
        $has_header_row = !empty($config['has_header_row']);

        // Get Google Sheets authentication service
        $auth_service = $this->getAuthProvider('googlesheets');
        if (!$auth_service) {
            $this->log('error', 'Authentication service not available.', ['pipeline_id' => $pipeline_id]);
            return $this->emptyResponse();
        }

        // Get authenticated access token
        $access_token = $auth_service->get_service();
        if (is_wp_error($access_token)) {
            $this->log('error', 'Authentication failed.', [
                'pipeline_id' => $pipeline_id,
                'error' => $access_token->get_error_message()
            ]);
            return $this->emptyResponse();
        }

        // Build Google Sheets API URL - get entire worksheet
        $range_param = urlencode($worksheet_name);
        $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range_param}";

        $this->log('debug', 'Fetching spreadsheet data.', [
            'spreadsheet_id' => $spreadsheet_id,
            'worksheet_name' => $worksheet_name,
            'processing_mode' => $processing_mode,
            'pipeline_id' => $pipeline_id
        ]);

        // Make API request
        $result = apply_filters('datamachine_request', null, 'GET', $api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'user-agent' => 'DataMachine WordPress Plugin/' . DATAMACHINE_VERSION
        ], 'Google Sheets API');

        if (!$result['success']) {
            $this->log('error', 'Failed to fetch data.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'spreadsheet_id' => $spreadsheet_id
            ]);
            return $this->emptyResponse();
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';

            $this->log('error', 'API request failed.', [
                'pipeline_id' => $pipeline_id,
                'status_code' => $response_code,
                'error_message' => $error_message,
                'spreadsheet_id' => $spreadsheet_id
            ]);
            return $this->emptyResponse();
        }

        $sheet_data = json_decode($response_body, true);
        if (empty($sheet_data['values'])) {
            $this->log('debug', 'No data found in specified range.', ['pipeline_id' => $pipeline_id]);
            return $this->emptyResponse();
        }

        $rows = $sheet_data['values'];
        $this->log('debug', 'Retrieved spreadsheet data.', [
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
            $this->log('debug', 'Using header row.', [
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
        if ($this->isItemProcessed($sheet_identifier, $flow_step_id)) {
            $this->log('debug', 'Full spreadsheet already processed.', [
                'sheet_identifier' => $sheet_identifier,
                'pipeline_id' => $pipeline_id
            ]);
            return $this->emptyResponse();
        }

        // Mark as processed
        $this->markItemProcessed($sheet_identifier, $flow_step_id, $job_id);

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

        // Prepare raw data for DataPacket creation
        $raw_data = [
            'title' => 'Google Sheets Data: ' . $worksheet_name,
            'content' => json_encode($all_data, JSON_PRETTY_PRINT),
            'metadata' => $metadata
        ];

        // Store empty engine data for downstream handlers
        $this->storeEngineData($job_id, [
            'source_url' => '',
            'image_url' => ''
        ]);

        $this->log('debug', 'Processed full spreadsheet.', [
            'total_rows' => count($all_data),
            'pipeline_id' => $pipeline_id
        ]);

        return $raw_data;
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
            if ($this->isItemProcessed($row_identifier, $flow_step_id)) {
                continue;
            }

            // Mark as processed
            $this->markItemProcessed($row_identifier, $flow_step_id, $job_id);

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

            // Prepare raw data for DataPacket creation
            $raw_data = [
                'title' => 'Row ' . ($i + 1) . ' Data',
                'content' => json_encode($row_data, JSON_PRETTY_PRINT),
                'metadata' => $metadata
            ];

            // Store empty engine data via centralized filter
            $this->storeEngineData($job_id, [
                'source_url' => '',
                'image_url' => ''
            ]);

            $this->log('debug', 'Processed row.', [
                'row_number' => $i + 1,
                'pipeline_id' => $pipeline_id
            ]);

            return $raw_data;
        }

        // No unprocessed rows found
        $this->log('debug', 'No unprocessed rows found.', ['pipeline_id' => $pipeline_id]);
        return $this->emptyResponse();
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
            if ($this->isItemProcessed($column_identifier, $flow_step_id)) {
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
            $this->markItemProcessed($column_identifier, $flow_step_id, $job_id);

            $metadata = [
                'source_type' => 'googlesheets_fetch',
                'processing_mode' => 'by_column',
                'spreadsheet_id' => $spreadsheet_id,
                'worksheet_name' => $worksheet_name,
                'column_letter' => $column_letter,
                'column_header' => $column_header,
                'headers' => $headers
            ];

            // Prepare raw data for DataPacket creation
            $raw_data = [
                'title' => 'Column: ' . $column_header,
                'content' => json_encode([$column_header => $column_data], JSON_PRETTY_PRINT),
                'metadata' => $metadata
            ];

            // Store empty engine data via centralized filter
            $this->storeEngineData($job_id, [
                'source_url' => '',
                'image_url' => ''
            ]);

            $this->log('debug', 'Processed column.', [
                'column_letter' => $column_letter,
                'column_header' => $column_header,
                'pipeline_id' => $pipeline_id
            ]);

            return $raw_data;
        }

        // No unprocessed columns found
        $this->log('debug', 'No unprocessed columns found.', ['pipeline_id' => $pipeline_id]);
        return $this->emptyResponse();
    }

    /**
     * Get handler display label.
     *
     * @return string Handler label
     */
    public static function get_label(): string {
        return __('Google Sheets Fetch', 'datamachine');
    }
}