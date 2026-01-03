# Cache Management

The `CacheManager` service provides centralized cache invalidation for all cached services in Data Machine. It ensures that when handlers, step types, or tools are dynamically registered, the associated caches are cleared to maintain system consistency.

## Overview

Data Machine uses various internal caches to optimize performance for service discovery and tool resolution. These caches are typically static properties within their respective service classes. The `CacheManager` acts as a single entry point to invalidate these caches.

## Centralized Invalidation

The system relies on WordPress actions to trigger cache clearing. When a new handler or step type is registered via the ecosystem, the `CacheManager` is called to clear the relevant caches.

### Invalidation Hooks

The following hooks are integrated in `data-machine.php`:

- `datamachine_handler_registered`: Clears handler-related caches. Triggered by `HandlerRegistrationTrait::register_handler()`.
- `datamachine_step_type_registered`: Clears step type-related caches. Triggered by `StepTypeRegistrationTrait::register_step_type()`.
- `datamachine_tool_registered`: Clears tool-related caches. Triggered by `ToolRegistrationTrait::register_tool()`.

## CacheManager Methods

### `clearAll()`
Clears all service caches, including step types, handlers, and tools. Call when major changes occur that could affect multiple cached systems.

### `clearHandlerCaches()`
Clears:
- `HandlerService` cache.
- `HandlerDocumentation` chat tool cache (if class exists).
- Tool caches via `clearToolCaches()` (since tools often depend on handlers).

### `clearStepTypeCaches()`
Clears:
- `StepTypeService` cache.
- `HandlerDocumentation` chat tool cache (if class exists).
- Tool caches via `clearToolCaches()` (since tools often depend on step types).

### `clearToolCaches()`
Clears the `ToolManager` resolved tool cache (if class exists). Rebuilds tool definitions on next access to ensure new handlers/step types are reflected in tool capabilities.

## Site Context Caching

The `SiteContext` directive provides cached WordPress site metadata for AI context injection. This cache is separate from `CacheManager` and is automatically invalidated when posts, terms, users, or site settings change.

- **Cache Key**: `datamachine_site_context_data` (WordPress transient)
- **Automatic Invalidation**: Hooks into `save_post`, `delete_post`, `create_term`, `update_option_blogname`, etc.
- **Manual Invalidation**: `SiteContext::clear_cache()`.

## TanStack Query Caching

In the admin UI, caching is handled by **TanStack Query**. Mutations (add, delete, reorder) for pipelines, flows, and steps automatically trigger invalidations for the relevant query keys to ensure the UI stays in sync with the server state.

## Implementation Details

Caches are cleared by resetting static properties in the following services:

- **HandlerService**: `$handlers_cache`, `$settings_cache`, `$config_fields_cache`.
- **StepTypeService**: `$cache`.
- **ToolManager**: `$resolved_cache`.
- **HandlerDocumentation**: `$cached_all_handlers`, `$cached_by_step_type`, `$cached_handler_slugs`, and service instances.
