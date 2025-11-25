# Execute Endpoint

**Implementation**: `inc/Api/Execute.php`

**Base URL**: `/wp-json/datamachine/v1/execute`

## Overview

The Execute endpoint provides workflow execution capabilities for both database flows and ephemeral workflows. Supports immediate execution and delayed (one-time) execution.

## Authentication

Requires WordPress authentication with `manage_options` capability. Two authentication methods supported:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See Authentication Guide for setup instructions.

## Capabilities

- **Database Flow Execution**: Trigger saved flows with immediate or delayed execution
- **Ephemeral Workflow Execution**: Execute temporary workflows without database persistence
- **Delayed Execution**: Schedule one-time future execution of flows
- **Execution Context**: Track execution source via `'rest_api_trigger'` context identifier

## Request Format

**Method**: `POST`

**Content-Type**: `application/json`

### Database Flow Parameters

- `flow_id` (integer, required): The ID of the flow to trigger
- `interval` (string, optional): Schedule interval for recurring execution (`manual`, `hourly`, `daily`, `weekly`, etc.)
- `timestamp` (integer, optional): Unix timestamp for one-time delayed execution (must be in future)

### Ephemeral Workflow Parameters

- `workflow` (object, required): Workflow definition with steps array
- `timestamp` (integer, optional): Unix timestamp for delayed execution (one-time only, no recurring)

### Trigger Type Logic

- If `flow_id` is provided → **Database flow execution**
- If `workflow` is provided → **Ephemeral workflow execution**
- If `interval` is provided → **Recurring schedule** (database flows only)
- If `timestamp` is provided → **Delayed execution** (one-time)
- If neither interval nor timestamp → **Immediate execution**

## Example Requests

### Immediate Execution

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'
```

### Delayed Execution (One-Time)

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "timestamp": 1704153600}'
```

### Ephemeral Workflow - Immediate

```bash
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
```

### Ephemeral Workflow - Delayed

```bash
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

## Response Format

### Success Responses (200 OK)

#### Immediate Execution

```json
{
  "success": true,
  "trigger_type": "immediate",
  "flow_id": 123,
  "flow_name": "My Flow",
  "message": "Flow triggered successfully."
}
```

#### Recurring Schedule

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

#### Recurring Schedule (Cleared)

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

#### Delayed Execution

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

#### Ephemeral Workflow - Immediate

```json
{
  "success": true,
  "trigger_type": "ephemeral_immediate",
  "workflow_id": "temp_1234567890",
  "steps_count": 3,
  "message": "Ephemeral workflow executed successfully."
}
```

#### Ephemeral Workflow - Delayed

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

### Response Fields

**Common Fields**:
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

## Error Responses

### 403 Forbidden - Insufficient Permissions

```json
{
  "code": "rest_forbidden",
  "message": "You do not have permission to trigger flows.",
  "data": {"status": 403}
}
```

### 404 Not Found - Invalid Flow ID

```json
{
  "code": "invalid_flow",
  "message": "Flow not found.",
  "data": {"status": 404}
}
```

### 400 Bad Request - Invalid Parameters

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

## Common Use Cases

### Webhook Integration

Trigger flows from external webhooks (GitHub, Zapier, Make.com):

```bash
# GitHub webhook example
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u admin:app_password \
  -d '{"flow_id": 456}'
```

### Scheduled External Triggers

Use cron jobs or task schedulers to trigger flows:

```bash
# Cron job (runs every hour)
0 * * * * curl -X POST https://example.com/wp-json/datamachine/v1/execute \
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

## Related Documentation

- Flows Endpoints - Flow management
- Jobs Endpoints - Job monitoring
- Authentication - Auth methods
- Errors - Error handling

---

**Endpoint**: `POST /datamachine/v1/execute`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Execute.php`
