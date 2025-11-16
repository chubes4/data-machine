# Tools Endpoint

**Implementation**: `inc/Api/Tools.php`

**Base URL**: `/wp-json/datamachine/v1/tools`

## Overview

The Tools endpoint provides information about registered AI tools and their configuration status.

## Authentication

Requires `manage_options` capability. See [Authentication Guide](authentication.md).

## Endpoints

### GET /tools

Retrieve registered AI tools and configuration status.

**Permission**: `manage_options` capability required

**Purpose**: Discover available AI tools and check configuration requirements

**Parameters**: None

**Example Request**:

```bash
curl https://example.com/wp-json/datamachine/v1/tools \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "tools": {
    "google_search": {
      "label": "Google Search",
      "configured": true,
      "chat_enabled": false,
      "description": "Search the web using Google Custom Search API"
    },
    "local_search": {
      "label": "Local Search",
      "configured": true,
      "chat_enabled": false,
      "description": "Search WordPress content locally"
    },
    "web_fetch": {
      "label": "Web Fetch",
      "configured": true,
      "chat_enabled": false,
      "description": "Fetch content from web pages"
    },
    "wordpress_post_reader": {
      "label": "WordPress Post Reader",
      "configured": true,
      "chat_enabled": false,
      "description": "Read and analyze WordPress post content"
    },
    "make_api_request": {
      "label": "Make API Request",
      "configured": true,
      "chat_enabled": true,
      "description": "Execute Data Machine REST API operations"
    }
  }
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `tools` (object): Object of tool definitions keyed by tool ID

**Tool Definition Fields**:
- `label` (string): Human-readable tool name
- `configured` (boolean): Whether tool is properly configured
- `chat_enabled` (boolean): Whether tool is available in chat interface
- `description` (string): Tool description

## Available Tools

### Global Tools

Available to all AI agents via `datamachine_global_tools` filter.

#### google_search

**Tool ID**: `google_search`

**Configuration Required**: Yes (API key + Search Engine ID)

**Chat Enabled**: No (available to pipeline AI steps only)

**Purpose**: Search the web using Google Custom Search API

**Parameters**:
- `query` (string): Search query
- `num_results` (integer): Number of results (1-10)
- `site` (string, optional): Restrict search to specific site

**Use Cases**:
- Research web content
- Find related information
- Verify facts

#### local_search

**Tool ID**: `local_search`

**Configuration Required**: No

**Chat Enabled**: No (available to pipeline AI steps only)

**Purpose**: Search WordPress content locally

**Parameters**:
- `query` (string): Search query
- `num_results` (integer): Number of results (1-20)
- `post_type` (string, optional): Filter by post type

**Use Cases**:
- Find related WordPress content
- Discover similar posts
- Link to existing content

#### web_fetch

**Tool ID**: `web_fetch`

**Configuration Required**: No

**Chat Enabled**: No (available to pipeline AI steps only)

**Purpose**: Fetch and extract content from web pages

**Parameters**:
- `url` (string): URL to fetch
- `selector` (string, optional): CSS selector for content extraction

**Limits**:
- 50,000 character limit per fetch
- HTML processing and cleaning
- JavaScript-free content extraction

**Use Cases**:
- Extract article content
- Gather reference material
- Pull data from web pages

#### wordpress_post_reader

**Tool ID**: `wordpress_post_reader`

**Configuration Required**: No

**Chat Enabled**: No (available to pipeline AI steps only)

**Purpose**: Read and analyze single WordPress post content

**Parameters**:
- `url` (string): WordPress post URL

**Use Cases**:
- Analyze post structure
- Extract metadata
- Reference existing content

### Chat-Only Tools

Available only to chat AI agents via `datamachine_chat_tools` filter.

#### make_api_request

**Tool ID**: `make_api_request`

**Configuration Required**: No

**Chat Enabled**: Yes (available only in chat interface)

**Purpose**: Execute Data Machine REST API operations

**Parameters**:
- `endpoint` (string): API endpoint path
- `method` (string): HTTP method (GET, POST, PUT, DELETE)
- `data` (object, optional): Request payload

**Use Cases**:
- Create pipelines via chat
- Manage flows through conversation
- Execute workflows from chat
- Configure settings via natural language

## Tool Configuration

### Google Search Configuration

Google Search requires API credentials:

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

### Configuration Status

Tools show `"configured": false` if required configuration is missing:

```json
{
  "google_search": {
    "label": "Google Search",
    "configured": false,
    "chat_enabled": false
  }
}
```

## Tool Categories

### Global Tools

Registered via `datamachine_global_tools` filter:

```php
add_filter('datamachine_global_tools', function($tools) {
    $tools['my_tool'] = [
        'class' => 'MyNamespace\\MyTool',
        'method' => 'execute',
        'description' => 'Tool description',
        'parameters' => [
            'param1' => ['type' => 'string', 'required' => true]
        ]
    ];
    return $tools;
});
```

### Chat Tools

Registered via `datamachine_chat_tools` filter:

```php
add_filter('datamachine_chat_tools', function($tools) {
    $tools['chat_tool'] = [
        'class' => 'MyNamespace\\ChatTool',
        'method' => 'execute',
        'description' => 'Chat-specific tool',
        'parameters' => [/* ... */]
    ];
    return $tools;
});
```

## Integration Examples

### Python Tool Discovery

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/tools"
auth = HTTPBasicAuth("username", "application_password")

response = requests.get(url, auth=auth)

if response.status_code == 200:
    data = response.json()

    # List configured tools
    configured = {k: v for k, v in data['tools'].items() if v['configured']}
    print(f"Configured tools: {len(configured)}")

    # List chat-enabled tools
    chat_tools = {k: v for k, v in data['tools'].items() if v['chat_enabled']}
    print(f"Chat-enabled tools: {', '.join(chat_tools.keys())}")

    # List unconfigured tools
    unconfigured = [k for k, v in data['tools'].items() if not v['configured']]
    if unconfigured:
        print(f"Unconfigured tools: {', '.join(unconfigured)}")
```

### JavaScript Tool Validation

```javascript
const axios = require('axios');

const toolsAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/tools',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Get configured tools
async function getConfiguredTools() {
  const response = await axios.get(toolsAPI.baseURL, {
    auth: toolsAPI.auth
  });

  const tools = response.data.tools;
  return Object.entries(tools)
    .filter(([_, tool]) => tool.configured)
    .reduce((obj, [id, tool]) => {
      obj[id] = tool;
      return obj;
    }, {});
}

// Get chat-enabled tools
async function getChatTools() {
  const response = await axios.get(toolsAPI.baseURL, {
    auth: toolsAPI.auth
  });

  const tools = response.data.tools;
  return Object.entries(tools)
    .filter(([_, tool]) => tool.chat_enabled)
    .map(([id, _]) => id);
}

// Usage
const configured = await getConfiguredTools();
console.log('Configured tools:', Object.keys(configured));

const chatTools = await getChatTools();
console.log('Chat tools:', chatTools);
```

## Common Workflows

### Check Tool Availability

```bash
# Get all tools and their status
curl https://example.com/wp-json/datamachine/v1/tools \
  -u username:application_password
```

### Validate Tool Configuration

```bash
# Check which tools need configuration
curl https://example.com/wp-json/datamachine/v1/tools \
  -u username:application_password | jq '.tools | to_entries | map(select(.value.configured == false))'
```

### List Chat Tools

```bash
# Get chat-enabled tools
curl https://example.com/wp-json/datamachine/v1/tools \
  -u username:application_password | jq '.tools | to_entries | map(select(.value.chat_enabled == true)) | map(.key)'
```

## Use Cases

### Tool Configuration UI

Build configuration interface based on tool status:

```javascript
const tools = await getConfiguredTools();

const unconfigured = Object.entries(tools)
  .filter(([_, tool]) => !tool.configured)
  .map(([id, tool]) => ({
    id,
    label: tool.label,
    needsConfig: true
  }));
```

### Chat Interface Initialization

Determine available tools for chat interface:

```javascript
const chatTools = await getChatTools();

if (chatTools.includes('make_api_request')) {
  enableAdvancedChatFeatures();
}
```

### Tool Availability Check

Verify tool configuration before use:

```javascript
async function canUseGoogleSearch() {
  const tools = await getConfiguredTools();
  return tools['google_search']?.configured || false;
}
```

## Related Documentation

- [Chat Endpoint](chat.md) - Conversational AI with tools
- [Settings Endpoints](settings.md) - Tool configuration
- [Providers Endpoint](providers.md) - AI provider information
- [Authentication](authentication.md) - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/tools`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Tools.php`
**Tool Registration**: Via `datamachine_global_tools` and `datamachine_chat_tools` filters
