# Handler Defaults System

**Implementation**: `inc/Services/HandlerService.php`
**Since**: v0.6.25

## Overview

The Handler Defaults system provides a hierarchical configuration management layer that ensures consistent settings across the Data Machine ecosystem while maintaining maximum flexibility. It allows administrators to establish site-wide standards that automatically apply to all workflows.

## Configuration Hierarchy

Data Machine applies configuration values using a three-tier priority system (highest to lowest):

1.  **Explicit Configuration**
    - Values specifically set for an individual Flow Step.
    - Defined in the Pipeline Builder or Flow configuration.
    - Always overrides site-level or schema defaults.

2.  **Site-wide Defaults**
    - Global standards established for the entire site.
    - Managed via **Settings → Handler Defaults** in the Admin UI.
    - Stored in the `datamachine_handler_defaults` WordPress option.
    - Applied when a value is not explicitly provided in the Flow Step.

3.  **Schema Defaults**
    - Built-in fallbacks defined in the code.
    - Located in the handler's `Settings` class via the `get_fields()` method.
    - Applied only when no explicit or site-wide value is found.

## Implementation Details

### HandlerService::applyDefaults()

The `HandlerService` is the central engine for configuration merging. It follows this logic:

```php
public function applyDefaults(string $handler_slug, array $config): array {
    $fields = $this->getConfigFields($handler_slug);
    $site_defaults = $this->getSiteDefaults();
    $handler_site_defaults = $site_defaults[$handler_slug] ?? [];

    $complete_config = [];

    foreach ($fields as $key => $field_config) {
        if (array_key_exists($key, $config)) {
            // Priority 1: Explicit value
            $complete_config[$key] = $config[$key];
        } elseif (array_key_exists($key, $handler_site_defaults)) {
            // Priority 2: Site-wide default
            $complete_config[$key] = $handler_site_defaults[$key];
        } elseif (isset($field_config['default'])) {
            // Priority 3: Schema default
            $complete_config[$key] = $field_config['default'];
        }
    }

    // Preserve unknown keys for forward compatibility
    return array_merge($complete_config, array_diff_key($config, $fields));
}
```

## AI Agent Integration

AI agents (Chat and Pipeline) can interact with this system through specialized tools:

- `get_handler_defaults`: Allows the agent to query current site standards.
- `set_handler_defaults`: Enables the agent to establish new site-wide standards based on user instructions.

## API Endpoints

The system is exposed via the following REST API endpoints:

- `GET /settings/handler-defaults`: Retrieve all defaults grouped by step type.
- `PUT /settings/handler-defaults/{handler_slug}`: Update defaults for a specific handler.

## Benefits

- **Consistency**: Ensures all social media posts or content imports follow site-wide branding and architectural standards.
- **Maintainability**: Update a single site-wide default instead of editing dozens of individual flows.
- **Safety**: Schema-level defaults prevent execution failures due to missing configuration.
- **Developer Experience**: Simplifies handler development by providing a unified way to handle configuration fallbacks.

## Related Documentation

- [Settings API](../api/settings.md) - REST API reference
- [Services Layer](services-layer.md) - Architectural overview
- [Parameter Systems](../api/parameter-systems.md) - Data flow patterns
