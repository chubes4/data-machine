# Google Sheets Fetch Handler

Reads data from Google Sheets spreadsheets using OAuth2 authentication with configurable cell ranges, header detection, and row processing limits.

## Authentication

**OAuth2 Required**: Uses Google Sheets API v4 with client_id/client_secret authentication.

**Service Integration**: Reuses existing Google Sheets OAuth infrastructure from publish handler for seamless bi-directional integration.

## Configuration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `spreadsheet_id` | string | Yes | Google Sheets spreadsheet ID from URL |
| `worksheet_name` | string | No | Target worksheet name (default: "Sheet1") |
| `cell_range` | string | No | A1 notation range (default: "A1:Z1000") |
| `has_header_row` | boolean | No | Whether first row contains headers (default: false) |
| `row_limit` | integer | No | Maximum rows to process (1-1000, default: 100) |

## Usage Examples

**Basic Spreadsheet Read**:
```php
$handler_config = [
    'googlesheets_fetch' => [
        'spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms'
    ]
];
```

**Advanced Configuration**:
```php
$handler_config = [
    'googlesheets_fetch' => [
        'spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        'worksheet_name' => 'Data',
        'cell_range' => 'A1:F200',
        'has_header_row' => true,
        'row_limit' => 50
    ]
];
```

## Data Processing

**Row Selection**: Processes eligible rows up to the specified limit, skipping empty rows and previously processed entries.

**Header Handling**: When `has_header_row` is true, uses first row values as column names. Otherwise generates column names as "Column_A", "Column_B", etc.

**Deduplication**: Uses unique identifier format: `{spreadsheet_id}_{worksheet_name}_row_{row_number}` for tracking processed rows.

## Content Format

**Row Content**:
```
Source: Google Sheets
Spreadsheet: {spreadsheet_id}
Worksheet: {worksheet_name}
Row: {row_number}

{Header_or_Column_Name}: {cell_value}
{Header_or_Column_Name}: {cell_value}
```

## Output Structure

### Database Storage + Filter Injection Architecture

The Google Sheets fetch handler generates clean data packets for AI processing while storing empty engine parameters in database (spreadsheet data has no URLs).

### Clean Data Packet (AI-Visible)

```php
[
    'data' => [
        'content_string' => '...',     // Formatted row data
        'file_info' => null            // No file info for spreadsheet data
    ],
    'metadata' => [
        'source_type' => 'googlesheets_fetch',
        'original_id' => 'unique_row_identifier',
        'spreadsheet_id' => 'spreadsheet_id',
        'worksheet_name' => 'worksheet_name',
        'row_number' => 'row_index',
        'row_data' => {                // Key-value pairs of row data
            'column_name': 'cell_value'
        },
        'headers' => ['col1', 'col2'], // Column headers if present
        'original_date_gmt' => 'current_timestamp'
        // URLs removed from AI-visible metadata
    ]
]
```

### Engine Parameters Storage

```php
// Stored in database via centralized dm_engine_data filter
if ($job_id) {
    apply_filters('dm_engine_data', null, $job_id, '', '');  // Empty URLs for spreadsheet data
}
```

### Return Structure

```php
return [
    'processed_items' => [$clean_data_packet]
    // Engine parameters stored separately in database
];
```

## Cell Range Validation

**A1 Notation**: Supports standard Google Sheets A1 notation (e.g., `A1:D100`, `B2:Z1000`).

**Range Limits**: Validates cell range format using regex pattern for proper column letters and row numbers.

**Processing Order**: Processes rows sequentially from top to bottom within specified range.

## Error Handling

**Configuration Errors**:
- Missing or invalid spreadsheet ID
- Malformed cell range notation
- Invalid worksheet names

**API Errors**:
- Authentication failures
- Spreadsheet access permissions
- API rate limiting
- Invalid spreadsheet or worksheet references

**Data Errors**:
- Empty spreadsheet or range
- Malformed row data

**Logging**: Uses `dm_log` action with debug/error levels for API calls, authentication, and data processing status.

## Row Processing

**Empty Row Handling**: Automatically skips rows with no meaningful content (all empty cells).

**Data Extraction**: Extracts only non-empty cells from each row, associating with appropriate column headers or generated column names.

**Batch Processing**: Returns all eligible rows (up to limit) as separate DataPackets for downstream processing.