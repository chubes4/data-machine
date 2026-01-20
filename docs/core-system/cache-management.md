# Cache Management

The `CacheManager` service provides centralized cache invalidation for all cached services in Data Machine. It ensures that when handlers, step types, or tools are dynamically registered, the associated caches are cleared to maintain system consistency.

## Overview

Data Machine uses various internal caches to optimize performance for service discovery and tool resolution. These caches are typically static properties within their respective service classes. The `CacheManager` acts as a single entry point to invalidate these caches.

## Centralized Invalidation

The system relies on WordPress actions to trigger cache clearing. When a new handler or step type is registered via the ecosystem, the `CacheManager` is called to clear the relevant caches.

### Invalidation Hooks

Cache invalidation is triggered by ecosystem registration actions (hooked by the plugin bootstrap):

- `datamachine_handler_registered`: Clears handler + auth provider caches (and related tool caches).
- `datamachine_step_type_registered`: Clears step type caches (and related tool caches).
- `datamachine_tool_registered`: Clears tool definition caches.

The cache manager clears caches via `HandlerAbilities::clearCache()`, `AuthProviderService::clearCache()`, `StepTypeAbilities::clearCache()`, and `ToolManager::clearCache()`.

> **Migration Note (@since v0.11.7):** `HandlerService` and `StepTypeService` have been deleted and replaced by `HandlerAbilities` and `StepTypeAbilities`. Cache clearing now calls the ability class static methods instead of service methods.

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

In the React-based admin UI (Pipelines, Logs, Settings, and Jobs), caching is handled by **TanStack Query**. Mutations (add, delete, update, clear) for pipelines, flows, steps, and jobs automatically trigger invalidations for the relevant query keys to ensure the UI stays in sync with the server state. The Jobs page also utilizes background refetching to maintain real-time status updates without manual page refreshes.

## Implementation Details

Caches are cleared by resetting static properties in the following classes:

- **HandlerAbilities**: `$handlers_cache`, `$settings_cache`, `$config_fields_cache` (replaces HandlerService @since v0.11.7).
- **StepTypeAbilities**: `$cache` (replaces StepTypeService @since v0.11.7).
- **ToolManager**: `$resolved_cache`.
- **HandlerDocumentation**: `$cached_all_handlers`, `$cached_by_step_type`, `$cached_handler_slugs`, and ability class instances.
