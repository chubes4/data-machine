# REST API Reference

Data Machine provides a REST API for triggering flow execution programmatically. This enables external systems, automation tools, and third-party integrations to initiate workflows.

## Flow Trigger Endpoint

**Endpoint**: `POST /wp-json/dm/v1/trigger`

**Implementation**: `inc/Engine/Rest/Trigger.php`

**Registration**: Automatic via `rest_api_init` WordPress action

### Authentication

The endpoint requires WordPress authentication with `manage_options` capability. Two authentication methods are supported:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

### Request Format

**Method**: `POST`

**Content-Type**: `application/json`

**Required Parameters**:
- `flow_id` (integer): The ID of the flow to trigger

**Example Request**:
```bash
curl -X POST https://example.com/wp-json/dm/v1/trigger \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'
```

### Response Format

#### Success Response (200 OK)

```json
{
  "success": true,
  "flow_id": 123,
  "flow_name": "My Flow",
  "message": "Flow triggered successfully."
}
```

**Response Fields**:
- `success` (boolean): Always `true` for successful requests
- `flow_id` (integer): The triggered flow ID
- `flow_name` (string): The name of the triggered flow
- `message` (string): Success confirmation message

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
do_action('dm_run_flow_now', $flow_id, 'rest_api_trigger');
```

This context identifier can be used in workflows to distinguish REST API triggers from manual or scheduled executions.

### Flow Compatibility

The endpoint works with any flow type:
- **Scheduled flows**: Executes immediately, bypassing schedule
- **Manual flows**: Executes as configured
- **Any handler combination**: Compatible with all fetch, AI, publish, and update handlers

### Logging

All REST API triggers are logged via the `dm_log` action:

```php
do_action('dm_log', 'info', 'Flow triggered via REST API', [
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

url = "https://example.com/wp-json/dm/v1/trigger"
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

curl -X POST https://example.com/wp-json/dm/v1/trigger \
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
      'https://example.com/wp-json/dm/v1/trigger',
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
$url = 'https://example.com/wp-json/dm/v1/trigger';
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
$flow = apply_filters('dm_get_flow', null, $flow_id);
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

## Related Documentation

- [Core Actions](core-actions.md) - WordPress action hooks used by Data Machine
- [Core Filters](core-filters.md) - WordPress filter hooks for data processing
- [Engine Execution](../core-system/engine-execution.md) - Understanding the execution cycle
- [Settings Configuration](../admin-interface/settings-configuration.md) - Configure authentication and permissions
