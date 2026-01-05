# Data Machine REST API

Complete REST API reference for Data Machine

## Overview

**Base URL**: `/wp-json/datamachine/v1/`

**Authentication**: WordPress application password or cookie authentication

**Permissions**: Most endpoints require `manage_options` capability

**Implementation**: All endpoints in `/datamachine/inc/Api/` using services layer for direct method calls, with automatic registration via `rest_api_init`

## Endpoint Categories

### Workflow Execution
- [Execute](execute.md): Trigger flows and ephemeral workflows
- [Scheduling Intervals](intervals.md): Available scheduling intervals and configuration

### Pipeline & Flow Management
- [Pipelines](pipelines.md)
- [Flows](flows.md)
- [Jobs](jobs.md)

### Content & Data
- [Files](files.md)
- [Processed Items](processed-items.md)

### AI & Chat
- [Chat](chat.md)
- [Handlers](handlers.md)
- [Providers](providers.md)
- [Tools](tools.md)

### Configuration
- [Settings](settings.md)
- [Users](users.md)
- [Auth](auth.md)
- [Step Types](step-types.md)

### Monitoring
- [Logs](logs.md)
- [AI Directives](../core-system/ai-directives.md)
- [Jobs](jobs.md)

## Common Patterns

### Authentication

Data Machine supports two authentication methods:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See [Authentication](authentication.md).

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

All endpoints are implemented in `/datamachine/inc/Api/` using the services layer architecture for direct method calls, with automatic registration via `rest_api_init`:

```php
// Example endpoint registration using services layer
register_rest_route('datamachine/v1', '/pipelines', [
    'methods' => 'GET',
    'callback' => [Pipelines::class, 'get_pipelines'],
    'permission_callback' => [Pipelines::class, 'check_permission']
]);

// Services layer usage in endpoint callbacks
public function create_pipeline($request) {
    $pipeline_manager = new \DataMachine\Services\PipelineManager();
    return $pipeline_manager->create($request['name'], $request['options'] ?? []);
}
```

For detailed implementation patterns, see Core Actions and Core Filters documentation in the api-reference directory.

## Related Documentation

- [Authentication](authentication.md)
- [Errors](errors.md)
- [Engine Execution](../core-system/engine-execution.md)
- [Settings](settings.md)

---

**API Version**: v1
**Last Updated**: 2026-01-03
