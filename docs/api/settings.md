# Settings Endpoints

**Implementation**: `inc/Api/Settings.php`

**Base URL**: `/wp-json/datamachine/v1/settings`

## React Interface (@since v0.8.0)

The Settings interface is a React-based configuration dashboard providing centralized control over system behavior, agent configuration, and AI providers. It uses TanStack Query for data fetching and Zustand for UI state management, eliminating all legacy jQuery dependencies.

## Overview

Settings endpoints manage tool configuration for Data Machine.

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

## Handler Defaults

### GET /settings/handler-defaults

Retrieve all site-wide handler defaults, grouped by step type. Auto-populates from schema defaults on first access.

**Permission**: `manage_options` capability required

**Success Response (200 OK)**:

```json
{
  "success": true,
  "data": {
    "fetch": {
      "label": "Fetch Content",
      "uses_handler": true,
      "handlers": {
        "rss": {
          "label": "RSS Feed",
          "description": "Fetch content from RSS/Atom feeds",
          "defaults": {
            "max_items": 10
          },
          "fields": {
            "max_items": {
              "type": "number",
              "label": "Max Items",
              "default": 10
            }
          }
        }
      }
    }
  }
}
```

### PUT /settings/handler-defaults/{handler_slug}

Update site-wide defaults for a specific handler. These values are used for new flows when fields are not explicitly set.

**Permission**: `manage_options` capability required

**Parameters**:
- `handler_slug` (string, required): Handler identifier (in URL path)
- `defaults` (object, required): Default configuration values keyed by field ID

**Example Request**:

```bash
curl -X PUT https://example.com/wp-json/datamachine/v1/settings/handler-defaults/wordpress_publish \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "defaults": {
      "post_status": "publish",
      "post_author": 1
    }
  }'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "data": {
    "handler_slug": "wordpress_publish",
    "defaults": {
      "post_status": "publish",
      "post_author": 1
    },
    "message": "Defaults updated for handler \"wordpress_publish\"."
  }
}
```

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

## Related Documentation

- Tools Endpoint - Tool availability
- Authentication - Auth methods
- Errors - Error handling

---

**Base URL**: `/wp-json/datamachine/v1/settings`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Settings.php`
