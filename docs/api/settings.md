# Settings Endpoints

**Implementation**: `inc/Api/Settings.php`

**Base URL**: `/wp-json/datamachine/v1/settings`

## Overview

Settings endpoints manage tool configuration and cache operations for Data Machine.

## Authentication

Requires `manage_options` capability. See Authentication Guide.

## Endpoints

### POST /settings/tools/{tool_id}

Save configuration for a specific tool.

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

**Response Fields**:
- `success` (boolean): Request success status
- `message` (string): Confirmation message
- `configured` (boolean): Tool configuration status after save

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

### DELETE /cache

Clear Data Machine caches for troubleshooting and forcing fresh data.

**Permission**: `manage_options` capability required

**Parameters**:
- `type` (string, optional): Cache type to clear - `flows`, `pipelines`, `settings`, `all` (default: `all`)

**Cache Clearing Scope**:
- `all`: All pipeline caches, flow caches, job caches, WordPress transients with `datamachine_*` pattern, object cache, AI HTTP client cache
- `flows`: Flow-specific caches only
- `pipelines`: Pipeline-specific caches only
- `settings`: Settings and configuration caches only

**Example Requests**:

```bash
# Clear all caches
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -u username:application_password

# Clear only flow caches
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "flows"}'

# Clear only pipeline caches
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "pipelines"}'

# Clear settings caches
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "settings"}'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "message": "Cache cleared successfully.",
  "type": "all"
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `message` (string): Confirmation message
- `type` (string): Cache type that was cleared

## Tool Configuration

### Google Search

Configure Google Custom Search API for web search functionality.

**Required Fields**:
- `api_key` (string): Google API key with Custom Search API enabled
- `search_engine_id` (string): Custom Search Engine ID

**Example Configuration**:

```bash
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

### Custom Tools

Tools can register configuration handlers via the `datamachine_save_tool_config` action:

```php
add_action('datamachine_save_tool_config', function($tool_id, $config_data) {
    if ($tool_id === 'my_custom_tool') {
        update_option('my_tool_config', $config_data);
    }
}, 10, 2);
```

## Cache Management

### Cache Types

**Pipelines**:
- Pipeline templates
- Pipeline steps
- Pipeline metadata

**Flows**:
- Flow configurations
- Flow scheduling
- Flow steps
- Flow metadata

**Settings**:
- Tool configurations
- Global settings

**All**:
- Complete cache reset including:
  - All pipeline caches
  - All flow caches
  - All job caches
  - WordPress transients (`datamachine_*`)
  - Object cache
  - AI HTTP client cache

### Use Cases

**Force Reload**:
```bash
# Force reload of pipeline configurations from database
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "pipelines"}'
```

**Troubleshoot Stale Data**:
```bash
# Clear all caches to reset system state
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -u username:application_password
```

**After Configuration Changes**:
```bash
# Clear settings cache after updating tool configuration
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "settings"}'
```

## Integration Examples

### Python Tool Configuration

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/settings/tools/google_search"
auth = HTTPBasicAuth("username", "application_password")

config = {
    "config_data": {
        "api_key": "AIzaSyC1234567890abcdef",
        "search_engine_id": "012345678901234567890:abcdefg"
    }
}

response = requests.post(url, json=config, auth=auth)

if response.status_code == 200:
    print("Tool configured successfully")
else:
    print(f"Error: {response.json()['message']}")
```

### JavaScript Cache Management

```javascript
const axios = require('axios');

const settingsAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Clear specific cache
async function clearCache(type = 'all') {
  const response = await axios.delete(
    `${settingsAPI.baseURL}/cache`,
    {
      data: { type },
      auth: settingsAPI.auth
    }
  );

  return response.data.success;
}

// Configure tool
async function configureTool(toolId, configData) {
  const response = await axios.post(
    `${settingsAPI.baseURL}/settings/tools/${toolId}`,
    { config_data: configData },
    { auth: settingsAPI.auth }
  );

  return response.data.configured;
}

// Usage
await clearCache('flows');
console.log('Flow caches cleared');

const configured = await configureTool('google_search', {
  api_key: 'AIzaSyC1234567890abcdef',
  search_engine_id: '012345678901234567890:abcdefg'
});
console.log(`Tool configured: ${configured}`);
```

## Common Workflows

### Tool Setup

```bash
# 1. Configure tool
curl -X POST https://example.com/wp-json/datamachine/v1/settings/tools/google_search \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"config_data": {"api_key": "...", "search_engine_id": "..."}}'

# 2. Clear settings cache
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "settings"}'
```

### System Reset

```bash
# Clear all caches to reset system
curl -X DELETE https://example.com/wp-json/datamachine/v1/cache \
  -u username:application_password
```

## Related Documentation

- Tools Endpoint - Tool availability
- Authentication - Auth methods
- Errors - Error handling

---

**Base URL**: `/wp-json/datamachine/v1/settings`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Settings.php`
