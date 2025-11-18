# Data Machine REST API

Complete REST API reference for Data Machine v0.2.0

## Quick Start

**Base URL**: `/wp-json/datamachine/v1/`

**Authentication**: WordPress application password or cookie authentication

**Permissions**: Most endpoints require `manage_options` capability

**Implementation**: All endpoints in `/datamachine/inc/Api/` with automatic registration via `rest_api_init`

## Endpoint Categories

### Workflow Execution
- [Execute](execute.md) - Trigger flows and ephemeral workflows
- [Schedule](schedule.md) - Manage flow scheduling and automation
- [Intervals](intervals.md) - Available scheduling intervals

### Pipeline & Flow Management
- [Pipelines](pipelines.md) - Create, manage, and export pipeline templates
- [Flows](flows.md) - Create, duplicate, and delete flow instances
- [Jobs](jobs.md) - Monitor and manage job executions

### Content & Data
- [Files](files.md) - Upload files for pipeline processing
- [ProcessedItems](processed-items.md) - Manage deduplication tracking

### AI & Chat
- [Chat](chat.md) - Conversational AI workflow builder
- [Handlers](handlers.md) - Available fetch/publish/update handlers
- [Providers](providers.md) - AI provider configuration
- [Tools](tools.md) - AI tool availability

### Configuration
- [Settings](settings.md) - Tool configuration and cache management
- [Users](users.md) - User preferences
- [Auth](auth.md) - OAuth account management
- [StepTypes](step-types.md) - Available pipeline step types

### Monitoring
- [Logs](logs.md) - Log management and debugging

## Common Patterns

### Authentication

Data Machine supports two authentication methods:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See [Authentication Guide](authentication.md) for detailed setup instructions.

### Error Handling

All endpoints return standardized error responses following WordPress REST API conventions. Common error codes include:

- `rest_forbidden` (403) - Insufficient permissions
- `rest_invalid_param` (400) - Invalid parameters
- Resource-specific errors (404, 500)

See [Error Handling Reference](errors.md) for complete error code documentation.

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

For detailed implementation patterns, see:
- [Core Actions](../api-reference/core-actions.md)
- [Core Filters](../api-reference/core-filters.md)

## Related Documentation

- [REST API Authentication](authentication.md) - Detailed authentication guide
- [Error Handling](errors.md) - Complete error reference
- [Engine Execution](../core-system/engine-execution.md) - Understanding workflow execution
- [Settings Configuration](../admin-interface/settings-configuration.md) - Configure API access

---

**API Version**: v1
**Data Machine Version**: 0.2.1
**Last Updated**: 2025-11-15
