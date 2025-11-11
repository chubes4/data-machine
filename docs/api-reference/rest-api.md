# REST API Reference

Data Machine provides a comprehensive REST API for programmatic access to pipelines, flows, file uploads, job monitoring, and user preferences via 10 endpoint files. All endpoints are implemented in the `inc/Api/` directory with automatic registration via `rest_api_init`.

**Implemented Endpoints**: Auth, Execute, Files, Flows, Jobs, Logs, Pipelines, ProcessedItems, Settings, Users

## Overview

**Base URL**: `/wp-json/datamachine/v1/`

**Authentication**: WordPress application password or cookie authentication

**Permission Model**:
- Most endpoints require `manage_options` capability (Administrator/Editor)
- `/users/me` endpoints require authentication only (any logged-in user)

**Implementation Location**: `inc/Api/` directory

## Execute Endpoint

**Endpoint**: `POST /datamachine/v1/execute`

**Implementation**: `inc/Api/Execute.php`

**Registration**: Automatic via `rest_api_init` WordPress action

**Capabilities**: Database flow execution (immediate, recurring, delayed) and ephemeral workflow execution

### Authentication

The endpoint requires WordPress authentication with `manage_options` capability. Two authentication methods are supported:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

### Request Format

**Method**: `POST`

**Content-Type**: `application/json`

**Parameters** (Database Flows)**:
- `flow_id` (integer, required): The ID of the flow to trigger
- `interval` (string, optional): Schedule interval for recurring execution (`manual`, `hourly`, `daily`, `weekly`, etc.)
- `timestamp` (integer, optional): Unix timestamp for one-time delayed execution (must be in future)

**Parameters** (Ephemeral Workflows)**:
- `workflow` (object, required): Workflow definition with steps array
- `timestamp` (integer, optional): Unix timestamp for delayed execution (one-time only, no recurring)

**Trigger Type Logic**:
- If `flow_id` is provided → **Database flow execution**
- If `workflow` is provided → **Ephemeral workflow execution**
- If `interval` is provided → **Recurring schedule** (database flows only)
- If `timestamp` is provided → **Delayed execution** (one-time)
- If neither interval nor timestamp → **Immediate execution**

**Example Requests**:

```bash
# Immediate execution
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'

# Recurring schedule (hourly)
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "interval": "hourly"}'

# Delayed execution (one-time at specific time)
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "timestamp": 1704153600}'

# Clear schedule (set to manual)
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "interval": "manual"}'

# Ephemeral workflow - immediate execution
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "workflow": {
      "steps": [
        {"type": "fetch", "handler": "rss", "config": {"feed_url": "https://example.com/feed/"}},
        {"type": "ai", "provider": "anthropic", "model": "claude-sonnet-4", "system_prompt": "Summarize this content"},
        {"type": "publish", "handler": "twitter", "config": {"max_length": 280}}
      ]
    }
  }'

# Ephemeral workflow - delayed execution
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "workflow": {
      "steps": [
        {"type": "fetch", "handler": "wordpress-local", "config": {"post_id": 123}},
        {"type": "ai", "provider": "openai", "model": "gpt-4", "system_prompt": "Enhance this content"},
        {"type": "update", "handler": "wordpress-update", "config": {}}
      ]
    },
    "timestamp": 1704153600
  }'
```

### Response Format

#### Success Responses (200 OK)

**Immediate Execution**:
```json
{
  "success": true,
  "trigger_type": "immediate",
  "flow_id": 123,
  "flow_name": "My Flow",
  "message": "Flow triggered successfully."
}
```

**Recurring Schedule**:
```json
{
  "success": true,
  "trigger_type": "recurring",
  "flow_id": 123,
  "flow_name": "My Flow",
  "interval": "hourly",
  "scheduled": true,
  "message": "Flow scheduled to run hourly."
}
```

**Recurring Schedule (Cleared)**:
```json
{
  "success": true,
  "trigger_type": "recurring",
  "flow_id": 123,
  "flow_name": "My Flow",
  "interval": "manual",
  "scheduled": false,
  "message": "Flow schedule cleared."
}
```

**Delayed Execution**:
```json
{
  "success": true,
  "trigger_type": "delayed",
  "flow_id": 123,
  "flow_name": "My Flow",
  "timestamp": 1704153600,
  "scheduled_time": "2024-01-02T00:00:00+00:00",
  "message": "Flow scheduled to run at Jan 2, 2024 12:00 AM."
}
```

**Ephemeral Workflow - Immediate**:
```json
{
  "success": true,
  "trigger_type": "ephemeral_immediate",
  "workflow_id": "temp_1234567890",
  "steps_count": 3,
  "message": "Ephemeral workflow executed successfully."
}
```

**Ephemeral Workflow - Delayed**:
```json
{
  "success": true,
  "trigger_type": "ephemeral_delayed",
  "workflow_id": "temp_1234567890",
  "steps_count": 3,
  "timestamp": 1704153600,
  "scheduled_time": "2024-01-02T00:00:00+00:00",
  "message": "Ephemeral workflow scheduled to run at Jan 2, 2024 12:00 AM."
}
```

**Common Response Fields**:
- `success` (boolean): Always `true` for successful requests
- `trigger_type` (string): `immediate`, `recurring`, `delayed`, `ephemeral_immediate`, or `ephemeral_delayed`
- `message` (string): Success confirmation message

**Database Flow Fields**:
- `flow_id` (integer): The triggered flow ID
- `flow_name` (string): The name of the triggered flow

**Ephemeral Workflow Fields**:
- `workflow_id` (string): Temporary identifier for the ephemeral workflow
- `steps_count` (integer): Number of steps in the workflow

**Type-Specific Fields**:
- Recurring: `interval` (string), `scheduled` (boolean)
- Delayed: `timestamp` (integer), `scheduled_time` (ISO 8601 string)

#### Error Responses

**403 Forbidden** - Insufficient permissions:
```json
{
  "code": "rest_forbidden",
  "message": "You do not have permission to trigger flows.",
  "data": {
    "status": 403
  }
}
```

**404 Not Found** - Invalid flow ID:
```json
{
  "code": "invalid_flow",
  "message": "Flow not found.",
  "data": {
    "status": 404
  }
}
```

**400 Bad Request** - Missing or invalid flow_id parameter:
```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): flow_id",
  "data": {
    "status": 400,
    "params": {
      "flow_id": "flow_id must be a positive integer"
    }
  }
}
```

## Execution Details

### Execution Context

Flows triggered via REST API execute with the context `'rest_api_trigger'`:

```php
do_action('datamachine_run_flow_now', $flow_id, 'rest_api_trigger');
```

This context identifier can be used in workflows to distinguish REST API triggers from manual or scheduled executions.

### Flow Compatibility

The endpoint works with any flow type:
- **Scheduled flows**: Executes immediately, bypassing schedule
- **Manual flows**: Executes as configured
- **Any handler combination**: Compatible with all fetch, AI, publish, and update handlers

### Logging

All REST API triggers are logged via the `datamachine_log` action:

```php
do_action('datamachine_log', 'info', 'Flow triggered via REST API', [
    'flow_id' => $flow_id,
    'flow_name' => $flow['flow_name'] ?? '',
    'user_id' => get_current_user_id(),
    'user_login' => wp_get_current_user()->user_login
]);
```

**Logged Information**:
- Flow ID and name
- User ID and login
- Execution timestamp
- Context identifier

## Integration Examples

### Python Integration

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/execute"
auth = HTTPBasicAuth("username", "application_password")
payload = {"flow_id": 123}

response = requests.post(url, json=payload, auth=auth)

if response.status_code == 200:
    data = response.json()
    print(f"Flow triggered: {data['flow_name']}")
else:
    print(f"Error: {response.json()['message']}")
```

### cURL with Application Password

```bash
# Create application password in WordPress user profile
# Format: username:application_password

curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u admin:xxxx-xxxx-xxxx-xxxx \
  -d '{"flow_id": 123}'
```

### JavaScript/Node.js Integration

```javascript
const axios = require('axios');

const triggerFlow = async (flowId) => {
  try {
    const response = await axios.post(
      'https://example.com/wp-json/datamachine/v1/execute',
      { flow_id: flowId },
      {
        auth: {
          username: 'admin',
          password: 'application_password'
        }
      }
    );

    console.log(`Flow triggered: ${response.data.flow_name}`);
    return response.data;
  } catch (error) {
    console.error(`Error: ${error.response.data.message}`);
    throw error;
  }
};

triggerFlow(123);
```

### PHP Integration (External Site)

```php
$url = 'https://example.com/wp-json/datamachine/v1/execute';
$data = ['flow_id' => 123];

$response = wp_remote_post($url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode('username:application_password'),
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode($data),
    'timeout' => 30
]);

if (is_wp_error($response)) {
    error_log('Flow trigger failed: ' . $response->get_error_message());
} else {
    $result = json_decode(wp_remote_retrieve_body($response), true);
    if ($result['success']) {
        error_log('Flow triggered: ' . $result['flow_name']);
    }
}
```

## Security Considerations

### Authentication Requirements

1. **WordPress User**: Must have `manage_options` capability (Administrator or Editor role)
2. **Application Password**: Generate in WordPress admin → Users → Your Profile → Application Passwords
3. **HTTPS**: Strongly recommended for production environments

### Permission Validation

The endpoint performs comprehensive permission checks:

```php
public static function check_permission($request) {
    if (!current_user_can('manage_options')) {
        return new \WP_Error(
            'rest_forbidden',
            __('You do not have permission to trigger flows.', 'data-machine'),
            ['status' => 403]
        );
    }
    return true;
}
```

### Flow Validation

The endpoint validates flow existence before execution:

```php
$flow = apply_filters('datamachine_get_flow', null, $flow_id);
if (!$flow) {
    return new \WP_Error(
        'invalid_flow',
        __('Flow not found.', 'data-machine'),
        ['status' => 404]
    );
}
```

## Common Use Cases

### Webhook Integration

Trigger flows from external webhooks (GitHub, Zapier, Make.com):

```bash
# GitHub webhook example
curl -X POST https://example.com/wp-json/dm/v1/trigger \
  -H "Content-Type: application/json" \
  -u admin:app_password \
  -d '{"flow_id": 456}'
```

### Scheduled External Triggers

Use cron jobs or task schedulers to trigger flows:

```bash
# Cron job (runs every hour)
0 * * * * curl -X POST https://example.com/wp-json/dm/v1/trigger \
  -H "Content-Type: application/json" \
  -u admin:app_password \
  -d '{"flow_id": 789}' > /dev/null 2>&1
```

### Automation Platform Integration

Integrate with platforms like n8n, Integromat, or custom automation tools to trigger flows based on external events or conditions.

## Troubleshooting

### Common Errors

**403 Forbidden**
- Verify user has `manage_options` capability
- Check application password is correct
- Ensure WordPress user is active

**404 Not Found**
- Verify flow ID exists in WordPress admin → Data Machine → Pipelines
- Check flow hasn't been deleted
- Confirm database connection

**400 Bad Request**
- Ensure `flow_id` is an integer
- Verify JSON payload is valid
- Check Content-Type header is `application/json`

### Debug Tips

1. **Test Authentication**: Use WordPress REST API discovery endpoint first
   ```bash
   curl https://example.com/wp-json/ -u username:app_password
   ```

2. **Verify Flow ID**: Check flow exists via WordPress admin interface

3. **Check Logs**: Review Data Machine logs (WordPress Admin → Data Machine → Logs) for execution details

4. **Enable Debug Logging**: Set `WP_DEBUG_LOG` to true in `wp-config.php` to capture detailed error information

## Flows Endpoints

**Implementation**: `inc/Api/Flows.php`

### Create Flow

**Endpoint**: `POST /datamachine/v1/flows`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, required): Parent pipeline ID
- `flow_name` (string, optional): Flow name (default: "Flow")
- `flow_config` (array, optional): Handler settings per step
- `scheduling_config` (array, optional): Scheduling configuration

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 5,
    "flow_name": "My Custom Flow",
    "scheduling_config": {"interval": "daily"}
  }'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "flow_id": 42,
  "flow_name": "My Custom Flow",
  "pipeline_id": 5,
  "synced_steps": 3,
  "flow_data": {
    "flow_id": 42,
    "pipeline_id": 5,
    "flow_name": "My Custom Flow",
    "flow_config": "{}",
    "scheduling_config": "{\"interval\":\"daily\"}"
  }
}
```

### Delete Flow

**Endpoint**: `DELETE /datamachine/v1/flows/{flow_id}`

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_id` (integer, required): Flow ID to delete (in URL path)

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/flows/42 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "flow_id": 42,
  "message": "Flow deleted successfully.",
  "deleted_jobs": 5
}
```

### Duplicate Flow

**Endpoint**: `POST /datamachine/v1/flows/{flow_id}/duplicate`

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_id` (integer, required): Source flow ID to duplicate (in URL path)

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows/42/duplicate \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "source_flow_id": 42,
  "new_flow_id": 43,
  "flow_name": "My Custom Flow (Copy)",
  "pipeline_id": 5,
  "flow_data": {...},
  "pipeline_steps": [...]
}
```

## Pipelines Endpoints

**Implementation**: `inc/Api/Pipelines.php`

### Get Pipelines

**Endpoint**: `GET /datamachine/v1/pipelines`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, optional): Specific pipeline ID to retrieve
- `fields` (string, optional): Comma-separated list of fields to return

**Example Requests**:
```bash
# Get all pipelines
curl https://example.com/wp-json/datamachine/v1/pipelines \
  -u username:application_password

# Get specific pipeline
curl https://example.com/wp-json/datamachine/v1/pipelines?pipeline_id=5 \
  -u username:application_password

# Get specific fields only
curl https://example.com/wp-json/datamachine/v1/pipelines?fields=pipeline_id,pipeline_name \
  -u username:application_password
```

**Success Response (All Pipelines)**:
```json
{
  "success": true,
  "pipelines": [
    {
      "pipeline_id": 5,
      "pipeline_name": "Content Pipeline",
      "pipeline_config": "{}",
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-02 14:30:00"
    }
  ],
  "total": 1
}
```

**Success Response (Single Pipeline)**:
```json
{
  "success": true,
  "pipeline": {
    "pipeline_id": 5,
    "pipeline_name": "Content Pipeline",
    "pipeline_config": "{}",
    "created_at": "2024-01-01 12:00:00",
    "updated_at": "2024-01-02 14:30:00"
  },
  "flows": [...]
}
```

### Create Pipeline

**Endpoint**: `POST /datamachine/v1/pipelines`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_name` (string, optional): Pipeline name (default: "Pipeline")
- `steps` (array, optional): Complete pipeline steps configuration for complete mode
- `flow_config` (array, optional): Flow configuration

**Creation Modes**:
- **Simple Mode**: Only `pipeline_name` provided - creates empty pipeline
- **Complete Mode**: `steps` array provided - creates pipeline with configured steps

**Example Requests**:
```bash
# Simple mode - empty pipeline
curl -X POST https://example.com/wp-json/datamachine/v1/pipelines \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"pipeline_name": "My Pipeline"}'

# Complete mode - with steps
curl -X POST https://example.com/wp-json/datamachine/v1/pipelines \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_name": "Complete Pipeline",
    "steps": [
      {"step_type": "fetch"},
      {"step_type": "ai"},
      {"step_type": "publish"}
    ]
  }'
```

**Success Response**:
```json
{
  "success": true,
  "pipeline_id": 6,
  "pipeline_name": "My Pipeline",
  "pipeline_data": {...},
  "existing_flows": [],
  "creation_mode": "simple"
}
```

### Delete Pipeline

**Endpoint**: `DELETE /datamachine/v1/pipelines/{pipeline_id}`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, required): Pipeline ID to delete (in URL path)

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/pipelines/6 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "pipeline_id": 6,
  "message": "Pipeline deleted successfully.",
  "deleted_flows": 2,
  "deleted_jobs": 15
}
```

### Add Pipeline Step

**Endpoint**: `POST /datamachine/v1/pipelines/{pipeline_id}/steps`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, required): Pipeline ID (in URL path)
- `step_type` (string, required): Step type (`fetch`, `ai`, `publish`, `update`)

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/pipelines/6/steps \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"step_type": "fetch"}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Step \"Fetch\" added successfully",
  "step_type": "fetch",
  "step_config": {...},
  "pipeline_id": 6,
  "pipeline_step_id": "abc123-def456-789",
  "step_data": {...},
  "created_type": "step"
}
```

### Delete Pipeline Step

**Endpoint**: `DELETE /datamachine/v1/pipelines/{pipeline_id}/steps/{step_id}`

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, required): Pipeline ID (in URL path)
- `step_id` (string, required): Pipeline step ID (in URL path)

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/pipelines/6/steps/abc123-def456-789 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "pipeline_step_id": "abc123-def456-789",
  "pipeline_id": 6,
  "message": "Step deleted successfully."
}
```

### Export Pipelines (CSV)

**Endpoint**: `GET /datamachine/v1/pipelines?format=csv`

**Permission**: `manage_options` capability required

**Parameters**:
- `format` (string, required): Must be `csv` for export
- `ids` (string, optional): Comma-separated pipeline IDs to export
- `pipeline_id` (integer, optional): Single pipeline ID to export

**Export Behavior**:
- If `ids` provided → Export specified pipelines
- If `pipeline_id` provided → Export single pipeline
- If neither provided → Export all pipelines

**CSV Structure**:
The exported CSV includes 9 columns: `pipeline_id`, `pipeline_name`, `step_position`, `step_type`, `step_config`, `flow_id`, `flow_name`, `handler`, `settings`

**Example Requests**:
```bash
# Export all pipelines
curl https://example.com/wp-json/datamachine/v1/pipelines?format=csv \
  -u username:application_password \
  -o pipelines-export.csv

# Export specific pipelines
curl "https://example.com/wp-json/datamachine/v1/pipelines?format=csv&ids=5,6,7" \
  -u username:application_password \
  -o pipelines-export.csv

# Export single pipeline
curl https://example.com/wp-json/datamachine/v1/pipelines?format=csv&pipeline_id=5 \
  -u username:application_password \
  -o pipeline-5.csv
```

**Success Response (200 OK)**:
```csv
pipeline_id,pipeline_name,step_position,step_type,step_config,flow_id,flow_name,handler,settings
5,Content Pipeline,0,fetch,"{""pipeline_step_id"":""abc-123""}",,,""
5,Content Pipeline,1,ai,"{""pipeline_step_id"":""def-456""}",,,""
5,Content Pipeline,0,fetch,"{""pipeline_step_id"":""abc-123""}",42,My Flow,rss,"{""feed_url"":""https://example.com/feed""}"
```

**Response Headers**:
```
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="pipelines-export-2024-01-02-14-30-00.csv"
```

**Use Cases**:
- Backup pipeline configurations
- Share workflows between Data Machine installations
- Version control pipeline templates
- Migrate pipelines from development to production

### Import Pipelines (CSV)

**Endpoint**: `POST /datamachine/v1/pipelines`

**Permission**: `manage_options` capability required

**Parameters**:
- `batch_import` (boolean, required): Must be `true` to enable import mode
- `format` (string, required): Must be `csv` for CSV import
- `data` (string, required): CSV content to import

**Import Behavior**:
- Creates pipelines if they don't exist (matched by name)
- Adds steps with configuration from CSV
- Reuses existing pipelines with matching names
- Returns array of imported pipeline IDs

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/pipelines \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "batch_import": true,
    "format": "csv",
    "data": "pipeline_id,pipeline_name,step_position,step_type,step_config,flow_id,flow_name,handler,settings\n5,Content Pipeline,0,fetch,\"{}\",,,\"\"\n5,Content Pipeline,1,ai,\"{}\",,,\"\""
  }'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "imported_pipeline_ids": [5, 6, 7],
  "count": 3,
  "message": "Successfully imported 3 pipeline(s)"
}
```

**Error Response (500 Internal Server Error)**:
```json
{
  "code": "import_failed",
  "message": "Failed to import pipelines from CSV.",
  "data": {"status": 500}
}
```

**CSV Format Requirements**:
- Header row required with 9 columns
- Pipeline rows: Include pipeline structure (empty flow columns)
- Flow rows: Include flow configuration (populated flow columns)
- JSON-encoded fields must be properly escaped
- Steps sorted by execution_order for consistency

**Integration Example (Python)**:
```python
import requests
from requests.auth import HTTPBasicAuth

# Read CSV file
with open('pipelines-export.csv', 'r') as f:
    csv_content = f.read()

# Import to Data Machine
url = "https://example.com/wp-json/datamachine/v1/pipelines"
auth = HTTPBasicAuth("username", "application_password")
payload = {
    "batch_import": True,
    "format": "csv",
    "data": csv_content
}

response = requests.post(url, json=payload, auth=auth)

if response.status_code == 200:
    result = response.json()
    print(f"Imported {result['count']} pipeline(s)")
    print(f"Pipeline IDs: {result['imported_pipeline_ids']}")
else:
    print(f"Import failed: {response.json()['message']}")
```

## Status Detection (Legacy - Removed)

**Note**: The Status.php endpoint has been removed. Legacy status detection system has been deprecated pending replacement with new health check indicators.

**Migration**: Status checks previously handled via this endpoint should transition to alternative monitoring approaches using Jobs and Logs endpoints.

## Files Endpoint

**Implementation**: `inc/Api/Files.php`

**Endpoint**: `POST /datamachine/v1/files`

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_step_id` (string, required): Flow step ID to associate with upload
- `file` (file, required): File to upload (multipart/form-data)

**File Restrictions**:
- Maximum size: 32MB
- Blocked extensions: php, exe, bat, js, sh, and other executable types
- Path traversal protection
- MIME type validation

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/files \
  -u username:application_password \
  -F "flow_step_id=abc-123_42" \
  -F "file=@/path/to/document.pdf"
```

**Success Response (201 Created)**:
```json
{
  "success": true,
  "file_info": {
    "filename": "document_1234567890.pdf",
    "size": 1048576,
    "modified": 1704153600,
    "url": "https://example.com/wp-content/uploads/data-machine-files/abc-123_42/document_1234567890.pdf"
  },
  "message": "File \"document.pdf\" uploaded successfully."
}
```

**Error Responses**:

**400 Bad Request - File too large**:
```json
{
  "code": "file_validation_failed",
  "message": "File too large: 50 MB. Maximum allowed size: 32 MB",
  "data": {"status": 400}
}
```

**400 Bad Request - Invalid file type**:
```json
{
  "code": "file_validation_failed",
  "message": "File type not allowed for security reasons.",
  "data": {"status": 400}
}
```

## Users Endpoints

**Implementation**: `inc/Api/Users.php`

### Get User Preferences

**Endpoint**: `GET /datamachine/v1/users/{id}`

**Permission**: User must be logged in AND (has `manage_options` OR is the target user)

**Parameters**:
- `id` (integer, required): User ID (in URL path)

**Example Request**:
```bash
curl https://example.com/wp-json/datamachine/v1/users/5 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "user_id": 5,
  "selected_pipeline_id": 42
}
```

### Update User Preferences

**Endpoint**: `POST /datamachine/v1/users/{id}`

**Permission**: User must be logged in AND (has `manage_options` OR is the target user)

**Parameters**:
- `id` (integer, required): User ID (in URL path)
- `selected_pipeline_id` (integer|null, optional): Pipeline ID preference (null to clear)

**Example Requests**:
```bash
# Set preference
curl -X POST https://example.com/wp-json/datamachine/v1/users/5 \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"selected_pipeline_id": 42}'

# Clear preference
curl -X POST https://example.com/wp-json/datamachine/v1/users/5 \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"selected_pipeline_id": null}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "user_id": 5,
  "selected_pipeline_id": 42
}
```

**Error Response (404 Not Found)**:
```json
{
  "code": "pipeline_not_found",
  "message": "Pipeline not found.",
  "data": {"status": 404}
}
```

### Get Current User Preferences

**Endpoint**: `GET /datamachine/v1/users/me`

**Permission**: User must be logged in (any authenticated user)

**Example Request**:
```bash
curl https://example.com/wp-json/datamachine/v1/users/me \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "user_id": 5,
  "selected_pipeline_id": 42
}
```

### Update Current User Preferences

**Endpoint**: `POST /datamachine/v1/users/me`

**Permission**: User must be logged in (any authenticated user)

**Parameters**:
- `selected_pipeline_id` (integer|null, optional): Pipeline ID preference

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/datamachine/v1/users/me \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"selected_pipeline_id": 42}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "user_id": 5,
  "selected_pipeline_id": 42
}
```

## Settings Endpoints

**Implementation**: `inc/Api/Settings.php`

### Save Tool Configuration

**Endpoint**: `POST /datamachine/v1/settings/tools/{tool_id}`

**Permission**: `manage_options` capability required

**Parameters**:
- `tool_id` (string, required): Tool identifier (in URL path) - e.g., `google_search`
- `config_data` (object, required): Tool configuration fields as key-value pairs

**Tool Configuration Storage**:
- Delegates to `datamachine_save_tool_config` action for tool-specific handlers
- Each tool implements its own configuration storage mechanism
- Example: Google Search stores in `datamachine_search_config` site option

**Example Request**:
```bash
# Save Google Search configuration
curl -X POST https://example.com/wp-json/datamachine/v1/settings/tools/google_search \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "config_data": {
      "api_key": "AIzaSyC1234567890abcdef",
      "search_engine_id": "012345678901234567890:abcdefg"
    }
  }'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Configuration saved successfully",
  "configured": true
}
```

**Error Response (400 Bad Request)**:
```json
{
  "code": "invalid_config_data",
  "message": "Valid configuration data is required.",
  "data": {"status": 400}
}
```

**Error Response (500 Internal Server Error)**:
```json
{
  "code": "no_tool_handler",
  "message": "No configuration handler found for tool: invalid_tool",
  "data": {"status": 500}
}
```

**Supported Tools**:
- `google_search` - Google Search API configuration (api_key, search_engine_id)
- Additional tools can register handlers via `datamachine_save_tool_config` action

### Clear Cache

**Endpoint**: `DELETE /datamachine/v1/cache`

**Permission**: `manage_options` capability required

**Parameters**: None

**Cache Clearing Scope**:
- All pipeline caches
- All flow caches
- All job caches
- WordPress transients with `datamachine_*` pattern
- Object cache (if enabled)
- AI HTTP client cache

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "All cache has been cleared successfully."
}
```

**Use Cases**:
- Force reload of pipeline configurations from database
- Clear cached flow data after manual database updates
- Reset AI tool caches after configuration changes
- Troubleshoot stale data issues

## Logs Endpoints

**Implementation**: `inc/Api/Logs.php`

### Get Log Metadata

**Endpoint**: `GET /datamachine/v1/logs`

**Permission**: `manage_options` capability required

**Parameters**: None

**Returns**: Log file information, size, current log level, and available log levels

**Example Request**:
```bash
curl https://example.com/wp-json/datamachine/v1/logs \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "log_file": {
    "path": "/var/www/html/wp-content/uploads/data-machine.log",
    "exists": true,
    "size": 1048576,
    "size_formatted": "1 MB"
  },
  "configuration": {
    "current_level": "debug",
    "available_levels": {
      "debug": "Debug",
      "info": "Info",
      "warning": "Warning",
      "error": "Error"
    }
  }
}
```

### Get Log Content

**Endpoint**: `GET /datamachine/v1/logs/content`

**Permission**: `manage_options` capability required

**Parameters**:
- `mode` (string, optional): Content mode - `full` (default) or `recent`
- `limit` (integer, optional): Number of recent entries when mode=recent (default: 200, max: 10000)

**Returns**: Log file content with newest entries first

**Example Requests**:
```bash
# Get full log content
curl https://example.com/wp-json/datamachine/v1/logs/content \
  -u username:application_password

# Get recent 100 entries
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=100 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "content": "[2024-01-02 14:30:00] INFO: Flow triggered via REST API...\n[2024-01-02 14:25:00] DEBUG: Pipeline loaded from cache...",
  "total_lines": 5420,
  "mode": "recent",
  "message": "Loaded 100 recent log entries."
}
```

**Error Response (404 Not Found)**:
```json
{
  "code": "log_file_not_found",
  "message": "Log file does not exist.",
  "data": {"status": 404}
}
```

### Update Log Level

**Endpoint**: `PUT /datamachine/v1/logs/level` or `POST /datamachine/v1/logs/level`

**Permission**: `manage_options` capability required

**Parameters**:
- `level` (string, required): Log level to set (`debug`, `info`, `warning`, `error`)

**Example Request**:
```bash
curl -X PUT https://example.com/wp-json/datamachine/v1/logs/level \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"level": "info"}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "level": "info",
  "message": "Log level updated to Info."
}
```

### Clear Logs

**Endpoint**: `DELETE /datamachine/v1/logs`

**Permission**: `manage_options` capability required

**Parameters**: None

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/logs \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Logs cleared successfully."
}
```

## Jobs Endpoints

**Implementation**: `inc/Api/Jobs.php`

### Get Jobs

**Endpoint**: `GET /datamachine/v1/jobs`

**Permission**: `manage_options` capability required

**Parameters**:
- `orderby` (string, optional): Order jobs by field (default: `job_id`)
- `order` (string, optional): Sort order - `ASC` or `DESC` (default: `DESC`)
- `per_page` (integer, optional): Number of jobs per page (default: 50, max: 100)
- `offset` (integer, optional): Offset for pagination (default: 0)
- `pipeline_id` (integer, optional): Filter by pipeline ID
- `flow_id` (integer, optional): Filter by flow ID
- `status` (string, optional): Filter by job status (`completed`, `failed`, `running`, etc.)

**Example Requests**:
```bash
# Get all jobs (recent first)
curl https://example.com/wp-json/datamachine/v1/jobs \
  -u username:application_password

# Get failed jobs only
curl https://example.com/wp-json/datamachine/v1/jobs?status=failed \
  -u username:application_password

# Get jobs for specific flow with pagination
curl https://example.com/wp-json/datamachine/v1/jobs?flow_id=42&per_page=25&offset=0 \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "jobs": [
    {
      "job_id": 1523,
      "flow_id": 42,
      "pipeline_id": 5,
      "status": "completed",
      "started_at": "2024-01-02 14:30:00",
      "completed_at": "2024-01-02 14:30:15",
      "error_message": null
    }
  ],
  "total": 1523,
  "per_page": 50,
  "offset": 0
}
```

### Clear Jobs

**Endpoint**: `DELETE /datamachine/v1/jobs`

**Permission**: `manage_options` capability required

**Parameters**:
- `type` (string, required): Which jobs to clear - `all` or `failed`
- `cleanup_processed` (boolean, optional): Also clear processed items tracking (default: false)

**Example Requests**:
```bash
# Clear all jobs
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "all"}'

# Clear failed jobs and processed items
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "failed", "cleanup_processed": true}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Jobs cleared successfully."
}
```

## ProcessedItems Endpoints

**Implementation**: `inc/Api/ProcessedItems.php`

### Clear Processed Items

**Endpoint**: `DELETE /datamachine/v1/processed-items`

**Permission**: `manage_options` capability required

**Purpose**: Clear deduplication tracking to allow items to be processed again

**Parameters**:
- `clear_type` (string, required): Clear scope - `pipeline` or `flow`
- `target_id` (integer, required): Pipeline ID or Flow ID depending on clear_type

**Example Requests**:
```bash
# Clear processed items for entire pipeline
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "pipeline", "target_id": 5}'

# Clear processed items for specific flow
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Processed items cleared successfully."
}
```

**Use Cases**:
- Reprocess RSS feed items that were previously skipped
- Reset deduplication tracking after fixing handler configuration
- Force re-import of content from fetch handlers
- Debug workflow behavior by clearing processed item history

## Auth Endpoints

**Implementation**: `inc/Api/Auth.php`

### Check OAuth Status

**Endpoint**: `GET /datamachine/v1/auth/{handler_slug}/status`

**Permission**: `manage_options` capability required

**Parameters**:
- `handler_slug` (string, required): Handler identifier (e.g., `twitter`, `facebook`, `reddit`)

**Returns**: Authentication status, account details, and OAuth error/success states

**Example Request**:
```bash
curl https://example.com/wp-json/datamachine/v1/auth/twitter/status \
  -u username:application_password
```

**Success Response - Authenticated (200 OK)**:
```json
{
  "success": true,
  "authenticated": true,
  "account_details": {
    "username": "exampleuser",
    "id": "1234567890"
  },
  "handler_slug": "twitter"
}
```

**Success Response - Not Authenticated (200 OK)**:
```json
{
  "success": true,
  "authenticated": false,
  "error": false,
  "handler_slug": "twitter"
}
```

**Success Response - OAuth Error (200 OK)**:
```json
{
  "success": true,
  "authenticated": false,
  "error": true,
  "error_code": "oauth_failed",
  "error_message": "User denied authorization",
  "handler_slug": "twitter"
}
```

**Error Response (404 Not Found)**:
```json
{
  "code": "auth_provider_not_found",
  "message": "Authentication provider not found",
  "data": {"status": 404}
}
```

### Save Auth Configuration

**Endpoint**: `PUT /datamachine/v1/auth/{handler_slug}`

**Permission**: `manage_options` capability required

**Parameters**:
- `handler_slug` (string, required): Handler identifier (in URL path)
- Additional parameters vary by handler (e.g., `api_key`, `api_secret`, `client_id`, `client_secret`)

**Storage Behavior**:
- OAuth providers (Twitter, Reddit, Facebook, Threads, Google Sheets): Stored to `oauth_keys`
- Simple auth providers (Bluesky): Stored to `oauth_account`

**Example Request**:
```bash
# Save Twitter API keys
curl -X PUT https://example.com/wp-json/datamachine/v1/auth/twitter \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "consumer_key": "your_consumer_key",
    "consumer_secret": "your_consumer_secret"
  }'

# Save Bluesky credentials
curl -X PUT https://example.com/wp-json/datamachine/v1/auth/bluesky \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "username": "user.bsky.social",
    "app_password": "your_app_password"
  }'
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Configuration saved successfully"
}
```

**Success Response - No Changes (200 OK)**:
```json
{
  "success": true,
  "message": "Configuration is already up to date - no changes detected"
}
```

**Error Response (400 Bad Request)**:
```json
{
  "code": "required_field_missing",
  "message": "API Key is required",
  "data": {"status": 400}
}
```

### Disconnect Account

**Endpoint**: `DELETE /datamachine/v1/auth/{handler_slug}`

**Permission**: `manage_options` capability required

**Parameters**:
- `handler_slug` (string, required): Handler identifier (in URL path)

**Example Request**:
```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/auth/twitter \
  -u username:application_password
```

**Success Response (200 OK)**:
```json
{
  "success": true,
  "message": "Twitter account disconnected successfully"
}
```

**Error Response (500 Internal Server Error)**:
```json
{
  "code": "disconnect_failed",
  "message": "Failed to disconnect account",
  "data": {"status": 500}
}
```

## Error Handling

### Common Error Codes

**Authentication Errors**:
- `rest_forbidden` (403): User lacks `manage_options` capability
- `rest_invalid_param` (400): Invalid or missing required parameters

**Resource Errors**:
- `invalid_flow` (404): Flow ID not found
- `pipeline_not_found` (404): Pipeline ID not found
- `auth_provider_not_found` (404): Authentication provider not found
- `log_file_not_found` (404): Log file does not exist

**Validation Errors**:
- `file_validation_failed` (400): File upload validation failed (size, type)
- `required_field_missing` (400): Required configuration field missing
- `invalid_config_data` (400): Configuration data format invalid

**Operation Errors**:
- `import_failed` (500): Pipeline import operation failed
- `disconnect_failed` (500): Account disconnection failed
- `no_tool_handler` (500): No configuration handler for specified tool
- `database_unavailable` (500): Database service unavailable
- `log_file_read_error` (500): Unable to read log file

### Standard Error Response Format

All errors follow WordPress REST API error format:

```json
{
  "code": "error_code",
  "message": "Human-readable error description",
  "data": {
    "status": 400
  }
}
```

### Error Handling Best Practices

**Client-Side Handling**:
```javascript
try {
  const response = await fetch('/wp-json/datamachine/v1/flows', {
    method: 'POST',
    headers: {
      'Authorization': 'Basic ' + btoa('username:app_password'),
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({pipeline_id: 5})
  });

  const data = await response.json();

  if (!response.ok) {
    // Handle error
    console.error(`Error ${data.code}: ${data.message}`);
    return;
  }

  // Handle success
  console.log('Flow created:', data.flow_id);
} catch (error) {
  console.error('Network error:', error);
}
```

**HTTP Status Code Reference**:
- `200 OK`: Successful operation
- `201 Created`: Resource created successfully (file uploads)
- `400 Bad Request`: Invalid parameters or validation failure
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `500 Internal Server Error`: Server-side operation failure

## Related Documentation

- [Core Actions](core-actions.md) - WordPress action hooks used by Data Machine
- [Core Filters](core-filters.md) - WordPress filter hooks for data processing
- [Engine Execution](../core-system/engine-execution.md) - Understanding the execution cycle
- [Settings Configuration](../admin-interface/settings-configuration.md) - Configure authentication and permissions
