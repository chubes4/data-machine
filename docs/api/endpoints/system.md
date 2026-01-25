# System Endpoint

**Implementation**: `inc/Api/System/System.php`

**Base URL**: `/wp-json/datamachine/v1/system/`

## Overview

The System endpoint provides infrastructure operations and monitoring capabilities for Data Machine. System operations are primarily handled through the WordPress Abilities API rather than direct REST endpoints.

## Authentication

Requires `manage_options` capability. See Authentication Guide.

## Endpoints

### GET /system/status

Get system status and operational information.

**Permission**: `manage_options` capability required

**Purpose**: Monitor system health and version information

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "data": {
    "status": "operational",
    "version": "0.13.6",
    "timestamp": "2026-01-25T10:30:00Z"
  }
}
```

**Example Request**:
```bash
curl -X GET https://example.com/wp-json/datamachine/v1/system/status \
  -u username:application_password
```

## System Abilities

System operations are exposed through the WordPress Abilities API for programmatic access:

### datamachine/generate-session-title

**Purpose**: Generate a title for a chat session using AI or fallback methods

**Implementation**: `inc/Api/System/SessionTitleGenerator.php`

**Parameters**:
- `session_id` (string, required): UUID of the chat session
- `force` (boolean, optional): Force regeneration even if title exists (default: false)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "session_id": {
      "type": "string",
      "description": "Chat session UUID"
    },
    "force": {
      "type": "boolean",
      "description": "Force regeneration of existing title",
      "default": false
    }
  },
  "required": ["session_id"]
}
```

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "success": {
      "type": "boolean",
      "description": "Whether title generation succeeded"
    },
    "title": {
      "type": "string",
      "description": "Generated session title"
    },
    "method": {
      "type": "string",
      "enum": ["ai", "truncated"],
      "description": "Method used to generate title"
    }
  }
}
```

**Usage Example**:
```php
$ability = wp_get_ability('datamachine/generate-session-title');
$result = $ability->execute([
  'session_id' => '550e8400-e29b-41d4-a716-446655440000'
]);

if ($result['success']) {
  echo "Title: " . $result['title'] . " (method: " . $result['method'] . ")";
}
```

## Automatic Title Generation

Chat session titles are automatically generated when:

1. **AI Titles Enabled**: Uses the configured AI provider to generate descriptive titles from conversation content
2. **Fallback**: Uses truncated first user message when AI generation fails or is disabled
3. **Trigger**: Automatically triggered after each AI response via `datamachine_ai_response_received` hook

**Configuration**:
- `chat_ai_titles_enabled` setting controls whether AI generation is used (default: true)
- Falls back gracefully when AI services are unavailable
- Titles are limited to 100 characters maximum

## Related Documentation

- [Chat Endpoint](chat.md) - Main chat API
- [Chat Sessions](chat-sessions.md) - Session management
- [Abilities API](../core-system/abilities-api.md) - WordPress Abilities API usage
- [AI Directives](../core-system/ai-directives.md) - AI integration patterns

---

**Since**: v0.13.7
**Last Updated**: 2026-01-25</content>
<parameter name="filePath">docs/api/endpoints/system.md