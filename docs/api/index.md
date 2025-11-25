# Data Machine REST API

Complete REST API reference for Data Machine

## Quick Start

**Base URL**: `/wp-json/datamachine/v1/`

**Authentication**: WordPress application password or cookie authentication

**Permissions**: Most endpoints require `manage_options` capability

**Implementation**: All endpoints in `/datamachine/inc/Api/` with automatic registration via `rest_api_init`

## Endpoint Categories

### Workflow Execution
- Execute: Trigger flows and ephemeral workflows
- Flow Scheduling: Flow scheduling integrated into Flows API

### Pipeline & Flow Management
- Pipelines: Create, manage, and export pipeline templates
- Flows: Create, duplicate, and delete flow instances
- Jobs: Monitor and manage job executions

### Content & Data
- Files: Upload files for pipeline processing
- ProcessedItems: Manage deduplication tracking

### AI & Chat
- Chat: Conversational AI workflow builder
- Handlers: Available fetch/publish/update handlers
- Providers: AI provider configuration
- Tools: AI tool availability

### Configuration
- Settings: Tool configuration and cache management
- Users: User preferences
- Auth: OAuth account management
- StepTypes: Available pipeline step types

### Monitoring
- Logs: Log management and debugging

## Common Patterns

### Authentication

Data Machine supports two authentication methods:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See Authentication Guide documentation for detailed setup instructions.

### Error Handling

All endpoints return standardized error responses following WordPress REST API conventions. Common error codes include:

- `rest_forbidden` (403) - Insufficient permissions
- `rest_invalid_param` (400) - Invalid parameters
- Resource-specific errors (404, 500)

See Error Handling Reference documentation for complete error code documentation.

### Pagination

Endpoints returning lists support pagination parameters:
- `per_page` - Number of items per page
- `offset` or `page` - Pagination offset

## Implementation Guide

All endpoints are implemented in `/datamachine/inc/Api/` with automatic registration via `rest_api_init`:

```php
// Example endpoint registration
register_rest_route('datamachine/v1', '/pipelines', [
    'methods' => 'GET',
    'callback' => [Pipelines::class, 'get_pipelines'],
    'permission_callback' => [Pipelines::class, 'check_permission']
]);
```

For detailed implementation patterns, see Core Actions and Core Filters documentation in the api-reference directory.

## Related Documentation

- REST API Authentication: Detailed authentication guide
- Error Handling: Complete error reference
- Engine Execution: Understanding workflow execution
- Settings Configuration: Configure API access

---

**API Version**: v1
**Last Updated**: 2025-11-20
