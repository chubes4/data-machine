# Chat Endpoint

**Implementation**: `inc/Api/Chat/Chat.php`

**Base URL**: `/wp-json/datamachine/v1/chat`

## Overview

The Chat endpoint provides a conversational AI interface for building and executing Data Machine workflows through natural language interaction with multi-turn conversation support.

## Authentication

Requires `manage_options` capability. See [Authentication Guide](authentication.md).

## Endpoints

### POST /chat

Send a message to the AI assistant.

**Permission**: `manage_options` capability required

**Purpose**: Natural language workflow building and Data Machine administration via conversational AI

**Parameters**:
- `message` (string, required): User message to send to AI
- `session_id` (string, optional): Session ID for conversation continuity (omit to create new session)
- `provider` (string, optional): AI provider (`openai`, `anthropic`, `google`, `grok`, `openrouter`) - uses default from settings if not provided
- `model` (string, optional): Model identifier (e.g., `gpt-4`, `claude-sonnet-4`) - uses default from settings if not provided

**Example Requests**:

```bash
# Start new conversation
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "message": "Create a pipeline that fetches from RSS and publishes to Twitter",
    "provider": "anthropic",
    "model": "claude-sonnet-4"
  }'

# Continue existing conversation
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "message": "Now add a flow for that pipeline",
    "session_id": "session_abc123",
    "provider": "anthropic",
    "model": "claude-sonnet-4"
  }'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "session_id": "session_abc123",
  "response": "I'll help you create a pipeline with RSS fetch and Twitter publish steps...",
  "tool_calls": [
    {
      "id": "call_abc123",
      "type": "function",
      "function": {
        "name": "make_api_request",
        "arguments": "{\"endpoint\":\"/datamachine/v1/pipelines\",\"method\":\"POST\"}"
      }
    }
  ],
  "conversation": [
    {"role": "user", "content": "Create a pipeline..."},
    {"role": "assistant", "content": "I'll help you...", "tool_calls": [...]}
  ],
  "metadata": {
    "last_activity": "2024-01-02 14:30:00",
    "message_count": 2
  }
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `session_id` (string): Session identifier for conversation continuity
- `response` (string): AI assistant response message
- `tool_calls` (array, optional): Array of tool calls made by AI
- `conversation` (array): Full conversation history
- `metadata` (object): Session metadata
  - `last_activity` (string): Last activity timestamp
  - `message_count` (integer): Number of messages in conversation

## Session Management

### Session Creation

First message automatically creates a new session:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"message": "Hello"}'
```

Returns `session_id` for subsequent messages.

### Session Continuity

Use `session_id` to continue existing conversation:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"message": "Continue previous task", "session_id": "session_abc123"}'
```

### Session Storage

**Implementation**: `inc/Core/Database/Chat/Chat.php`

**Database Table**: `wp_datamachine_chat_sessions`

**Features**:
- Persistent conversation history
- User-scoped sessions (users can only access their own)
- 24-hour session expiration
- Message count tracking
- Provider and model tracking

### Session Security

**User Isolation**:
- Sessions are user-scoped
- Users can only access their own sessions
- Invalid session returns 404 error
- Access to another user's session returns 403 error

**Error Response (404 Not Found)** - Invalid session:

```json
{
  "code": "session_not_found",
  "message": "Session not found or expired",
  "data": {"status": 404}
}
```

**Error Response (403 Forbidden)** - Access denied:

```json
{
  "code": "session_access_denied",
  "message": "Access denied to this session",
  "data": {"status": 403}
}
```

## Available Tools

### Global Tools

Available to all AI agents via `datamachine_global_tools` filter:

- **google_search** - Web search with site restriction
- **local_search** - WordPress content search
- **web_fetch** - Web page content retrieval
- **wordpress_post_reader** - Single post analysis

### Chat-Specific Tools

Available only to chat AI agents via `datamachine_chat_tools` filter:

- **make_api_request** - Execute Data Machine REST API operations

### Tool Execution

Tools are executed automatically by the AI during conversation:

```json
{
  "tool_calls": [
    {
      "id": "call_abc123",
      "type": "function",
      "function": {
        "name": "make_api_request",
        "arguments": "{\"endpoint\":\"/datamachine/v1/pipelines\",\"method\":\"POST\",\"data\":{\"pipeline_name\":\"My Pipeline\"}}"
      }
    }
  ]
}
```

## Filter-Based Architecture

### Global Directives

Applied to all AI agents via `datamachine_global_directives` filter:

```php
add_filter('datamachine_global_directives', function($directives) {
    $directives[] = "Always use clear, concise language.";
    return $directives;
});
```

### Chat Directives

Applied only to chat AI agents via `datamachine_chat_directives` filter:

```php
add_filter('datamachine_chat_directives', function($directives) {
    $directives[] = "Guide users through workflow creation step-by-step.";
    return $directives;
});
```

## Integration Examples

### Python Chat Integration

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/chat"
auth = HTTPBasicAuth("username", "application_password")

# Start conversation
initial_message = {
    "message": "Create a pipeline that fetches from RSS",
    "provider": "anthropic",
    "model": "claude-sonnet-4"
}

response = requests.post(url, json=initial_message, auth=auth)
data = response.json()

session_id = data['session_id']
print(f"AI: {data['response']}")

# Continue conversation
follow_up = {
    "message": "Add a Twitter publish step",
    "session_id": session_id
}

response2 = requests.post(url, json=follow_up, auth=auth)
data2 = response2.json()

print(f"AI: {data2['response']}")
```

### JavaScript Chat Interface

```javascript
const axios = require('axios');

class ChatClient {
  constructor(baseURL, auth) {
    this.baseURL = baseURL;
    this.auth = auth;
    this.sessionId = null;
  }

  async sendMessage(message, provider = 'anthropic', model = 'claude-sonnet-4') {
    const payload = {
      message,
      provider,
      model
    };

    if (this.sessionId) {
      payload.session_id = this.sessionId;
    }

    const response = await axios.post(this.baseURL, payload, {
      auth: this.auth
    });

    // Store session ID from first message
    if (!this.sessionId) {
      this.sessionId = response.data.session_id;
    }

    return response.data;
  }
}

// Usage
const chat = new ChatClient(
  'https://example.com/wp-json/datamachine/v1/chat',
  { username: 'admin', password: 'application_password' }
);

const response1 = await chat.sendMessage('Create an RSS to Twitter pipeline');
console.log(`AI: ${response1.response}`);

const response2 = await chat.sendMessage('Execute the pipeline now');
console.log(`AI: ${response2.response}`);
```

## Common Workflows

### Create Pipeline via Chat

```bash
# 1. Start conversation
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "message": "Create a pipeline with these steps: fetch from RSS, process with AI, publish to Twitter"
  }'

# AI will create pipeline using make_api_request tool
```

### Monitor Jobs via Chat

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "message": "Show me failed jobs from the last hour",
    "session_id": "session_abc123"
  }'
```

### Configure Handler via Chat

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/chat \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "message": "Configure the Twitter handler for flow 42 with max length 280",
    "session_id": "session_abc123"
  }'
```

## Use Cases

### Conversational Pipeline Builder

Build complex workflows through natural language conversation:

```
User: "Create a pipeline that imports recipes from an RSS feed"
AI: [Creates pipeline with RSS fetch step]

User: "Add AI processing to extract ingredients"
AI: [Adds AI step with custom prompt]

User: "Publish to WordPress"
AI: [Adds WordPress publish step]
```

### Natural Language Debugging

Debug workflows through conversational interface:

```
User: "Why did flow 42 fail?"
AI: [Checks logs, identifies error, suggests fix]

User: "Clear the failed jobs"
AI: [Executes DELETE /jobs with type=failed]
```

### Interactive Configuration

Configure Data Machine settings through chat:

```
User: "Set up Google Search API"
AI: [Guides through configuration, saves settings]
```

## Related Documentation

- [Execute Endpoint](execute.md) - Workflow execution
- [Tools Endpoint](tools.md) - Available tools
- [Handlers Endpoint](handlers.md) - Handler information
- [Authentication](authentication.md) - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/chat`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Chat/Chat.php`
**Session Storage**: `wp_datamachine_chat_sessions` table
**Session Expiration**: 24 hours
