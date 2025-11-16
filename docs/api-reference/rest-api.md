# REST API Reference

> **Note**: The REST API documentation has been reorganized into modular files for better navigation and maintainability.

**New Location**: [/docs/api/](../api/)

## Quick Links

- **[API Overview](../api/index.md)** - Complete endpoint catalog and getting started guide
- **[Authentication](../api/authentication.md)** - Authentication methods and security best practices
- **[Error Handling](../api/errors.md)** - Error codes, status codes, and troubleshooting

## Endpoint Categories

### Workflow Execution
- **[Execute](../api/execute.md)** - Trigger flows and ephemeral workflows with immediate, recurring, or delayed execution

### Pipeline & Flow Management
- **[Pipelines](../api/pipelines.md)** - Create, manage, export, and import pipeline templates
- **[Flows](../api/flows.md)** - Create, duplicate, and delete flow instances
- **[Jobs](../api/jobs.md)** - Monitor and manage job executions with filtering and pagination

### Content & Data
- **[Files](../api/files.md)** - Upload files for pipeline processing with security validation
- **[ProcessedItems](../api/processed-items.md)** - Manage deduplication tracking records

### AI & Chat
- **[Chat](../api/chat.md)** - Conversational AI workflow builder with session management
- **[Handlers](../api/handlers.md)** - Available fetch, publish, and update handlers
- **[Providers](../api/providers.md)** - AI provider configuration and model availability
- **[Tools](../api/tools.md)** - AI tool availability and configuration status

### Configuration
- **[Settings](../api/settings.md)** - Tool configuration and cache management
- **[Users](../api/users.md)** - User preferences and pipeline selection
- **[Auth](../api/auth.md)** - OAuth account management for social platforms
- **[StepTypes](../api/step-types.md)** - Available pipeline step types

### Monitoring
- **[Logs](../api/logs.md)** - Log management, level control, and debugging

## Why the Change?

The REST API documentation has been split into modular files to provide:

1. **Better Navigation** - Find specific endpoint documentation quickly
2. **Improved Maintainability** - Update individual endpoints without affecting others
3. **Enhanced Readability** - Focused documentation per endpoint category
4. **Comprehensive Examples** - More integration examples and use cases per endpoint
5. **Cross-References** - Better linking between related endpoints

## Quick Start

**Base URL**: `/wp-json/datamachine/v1/`

**Authentication**: WordPress application password or cookie authentication

**Permissions**: Most endpoints require `manage_options` capability

### Example Request

```bash
# Trigger a flow
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'
```

For detailed examples and complete endpoint documentation, see the [API Overview](../api/index.md).

## Implementation Notes

All endpoints are implemented in `/datamachine/inc/Api/` with automatic registration via `rest_api_init`:

```php
// Example endpoint registration
register_rest_route('datamachine/v1', '/pipelines', [
    'methods' => 'GET',
    'callback' => [Pipelines::class, 'get_pipelines'],
    'permission_callback' => [Pipelines::class, 'check_permission']
]);
```

**Implemented Endpoints**: Auth, Chat, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users

## Related Documentation

- **[Core Actions](core-actions.md)** - WordPress action hooks used by Data Machine
- **[Core Filters](core-filters.md)** - WordPress filter hooks for data processing
- **[Engine Execution](../core-system/engine-execution.md)** - Understanding the execution cycle
- **[Settings Configuration](../admin-interface/settings-configuration.md)** - Configure authentication and permissions

## Migration Guide

If you have bookmarked specific sections of the old documentation:

| Old Section | New Location |
|-------------|--------------|
| Execute Endpoint (lines 19-474) | [execute.md](../api/execute.md) |
| Flows Endpoints (lines 475-573) | [flows.md](../api/flows.md) |
| Pipelines Endpoints (lines 574-956) | [pipelines.md](../api/pipelines.md) |
| Files Endpoint (lines 957-1016) | [files.md](../api/files.md) |
| Users Endpoints (lines 1017-1134) | [users.md](../api/users.md) |
| Settings Endpoints (lines 1135-1249) | [settings.md](../api/settings.md) |
| Logs Endpoints (lines 1250-1382) | [logs.md](../api/logs.md) |
| Jobs Endpoints (lines 1383-1470) | [jobs.md](../api/jobs.md) |
| ProcessedItems Endpoints (lines 1471-1589) | [processed-items.md](../api/processed-items.md) |
| Chat Endpoints (lines 1590-1708) | [chat.md](../api/chat.md) |
| Handlers Endpoint (lines 1709-1756) | [handlers.md](../api/handlers.md) |
| Providers Endpoint (lines 1757-1792) | [providers.md](../api/providers.md) |
| StepTypes Endpoint (lines 1793-1839) | [step-types.md](../api/step-types.md) |
| Tools Endpoint (lines 1840-1876) | [tools.md](../api/tools.md) |
| Auth Endpoints (lines 1877-2033) | [auth.md](../api/auth.md) |
| Error Handling (lines 2035-2111) | [errors.md](../api/errors.md) |

---

**This file is maintained for backward compatibility. All detailed documentation has moved to the `/docs/api/` directory.**

**Last Updated**: 2025-11-15
**New Structure Created**: 2025-11-15
**Data Machine Version**: 0.2.0
