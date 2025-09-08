# Google Sheets Output Handler

Appends structured data to Google Sheets spreadsheets using OAuth2 authentication with configurable column mapping for data collection and reporting workflows.

## Authentication

**OAuth2 Required**: Uses Google Sheets API v4 with client_id/client_secret authentication.

**Service Integration**: Reuses Google Sheets OAuth infrastructure with dedicated output-specific configuration.

## Configuration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `googlesheets_spreadsheet_id` | string | Yes | Target Google Sheets spreadsheet ID |
| `googlesheets_worksheet_name` | string | No | Target worksheet name (default: "Data Machine Output") |
| `googlesheets_column_mapping` | object | No | Column mapping configuration (uses defaults if not provided) |

## Usage Examples

**Basic Tool Call**:
```php
$parameters = [
    'content' => 'Data to append to spreadsheet'
];

$tool_def = [
    'handler_config' => [
        'googlesheets_spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
        'googlesheets_worksheet_name' => 'Data Log'
    ]
];

$result = $handler->handle_tool_call($parameters, $tool_def);
```

**With Structured Data**:
```php
$parameters = [
    'title' => 'Data Entry Title',
    'content' => 'Detailed content for the entry',
    'source_url' => 'https://example.com/source',
    'source_type' => 'rss',
    'job_id' => 'job_12345'
];
```

## Column Mapping

**Default Mapping**: Uses predefined column structure for consistent data organization:
- Title column
- Content column  
- Source URL column
- Source Type column
- Created At timestamp
- Job ID reference

**Custom Mapping**: Allows configuration of custom column mappings to match existing spreadsheet structures.

## Data Structure

**Row Data Preparation**: Converts tool parameters into structured row data based on column mapping configuration.

**Metadata Integration**: Automatically adds metadata fields:
- `created_at`: Current timestamp in ISO format
- `source_type`: Data source identifier
- `job_id`: Pipeline execution reference
- `source_url`: Original content URL

## Tool Call Response

**Success Response**:
```php
[
    'success' => true,
    'data' => [
        'spreadsheet_id' => 'spreadsheet_id',
        'worksheet_name' => 'worksheet_name',
        'sheet_url' => 'https://docs.google.com/spreadsheets/d/{id}',
        'row_data' => ['column1_value', 'column2_value', ...]
    ],
    'tool_name' => 'googlesheets_append'
]
```

**Error Response**:
```php
[
    'success' => false,
    'error' => 'Error description',
    'tool_name' => 'googlesheets_append'
]
```

## Append Operation

**API Integration**: Uses Google Sheets API `values:append` endpoint for adding new rows.

**Range Detection**: Automatically determines target range based on existing data.

**Row Insertion**: Adds new row at the bottom of existing data in specified worksheet.

## Data Processing Flow

1. **Parameter Validation**: Validates required content parameter and configuration
2. **Authentication Check**: Verifies Google Sheets service availability
3. **Metadata Preparation**: Creates metadata object with timestamps and references
4. **Row Data Preparation**: Maps parameters to column structure based on configuration
5. **Append Operation**: Uses Google Sheets API to add row to spreadsheet
6. **Response Generation**: Returns spreadsheet URL and inserted data

## Error Handling

**Configuration Errors**:
- Missing spreadsheet ID
- Invalid spreadsheet or worksheet references
- Malformed column mapping configuration

**Authentication Errors**:
- OAuth token failures
- Service account access issues
- API permission problems

**API Errors**:
- Spreadsheet access permissions
- Worksheet not found
- API quota or rate limiting

**Data Errors**:
- Failed row data preparation
- Invalid data formats
- Column mapping mismatches

## Use Cases

**Data Collection**: Systematically collect and organize processed content from various sources.

**Analytics Tracking**: Track pipeline performance with job IDs, timestamps, and source attribution.

**Content Logging**: Maintain searchable logs of processed content with metadata.

**Reporting**: Build datasets for analysis and reporting from automated content processing.

## Column Mapping Flexibility

**Standard Fields**: Supports common fields like title, content, URL, timestamp.

**Custom Fields**: Allows mapping of additional parameters to custom spreadsheet columns.

**Metadata Preservation**: Ensures important workflow metadata is captured in structured format.

**Logging**: Uses `dm_log` action with debug/error levels for API operations, data preparation, and error conditions.