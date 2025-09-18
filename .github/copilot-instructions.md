# Data Machine: AI Agent Working Instructions

Focused, codebase-specific rules (20–50 lines) for rapid productive AI edits. Full context: `README.md`, `docs/architecture.md`, `docs/overview.md`, `CLAUDE.md`.

## Core Model
Pipeline (template) → Flow (instance) → Job (execution) → Steps (fetch|ai|publish|update) operating on a chronological DataPacket array (index 0 = newest; prepend via `array_unshift`). Engine discovers everything via filters; never hard-wire class loading.

## Universal Conventions
1. Flat parameters only. Every `execute()` / `handle_tool_call()` gets one associative array built through `dm_engine_parameters` filter. Required keys: `job_id`, `flow_step_id`, `flow_step_config`, `data`.
2. Add engine/step data ONLY by filtering `dm_engine_parameters` (no globals, no singletons).
3. Registration is filter-only: `dm_steps`, `dm_handlers`, `ai_tools`, `ai_request`, `dm_db`, `dm_admin_pages`, `dm_auth_providers`.
4. Log everything operational via `do_action('dm_log', level, message, context)`; keep bodies summarized with `content_length` not raw text.
5. Cache operations use actions (`dm_clear_pipeline_cache`, `dm_clear_flow_cache`, `dm_clear_jobs_cache`, `dm_clear_all_cache`, `dm_cache_set`) – never touch transients directly.

## DataPacket Shape
```php
[
  'type' => 'fetch|ai|publish|update',
  'handler' => 'rss|twitter|wordpress|...', // optional for ai
  'content' => ['title' => string, 'body' => string],
  'metadata' => [...],
  'timestamp' => int
]
```
Steps append new context by unshifting; never mutate historical entries except adding safe metadata keys.

## AI Directive Stack (Priorities)
10 PluginCoreDirective → 20 GlobalSystemPrompt → 30 PipelineSystemPrompt → 40 ToolDefinitions → 50 SiteContext. Preserve spacing; pick an unused gap if inserting.

## Tools Architecture
`ai_tools` returns `[ tool_id => [class, method, description, parameters?, handler?, requires_config?, handler_config?] ]`. General tools omit `handler`. Handler tools only surface when the NEXT step’s handler matches. Parameter shaping goes through `AIStepToolParameters` (`buildParameters`, `buildForHandlerTool`) producing flat keys (e.g. `content`, `title`, `tool_name`). Avoid nested arrays except when externally required.

## WordPress Publish / Update Pattern
Canonical reference: `inc/Core/Steps/Publish/Handlers/WordPress/WordPress.php` plus modular components (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`). Process: validate → merge system defaults overriding handler config → sanitize (`wp_kses_post`, block parse/serialize) → taxonomy resolution (skip | fixed IDs | ai_decides with `wp_insert_term`) → log decisions → return uniform result `[ 'success'=>bool, 'data'=>[...], 'tool_name'=>'wordpress_publish' ]` or `[ 'success'=>false, 'error'=>...]`.

## IDs & Relationships
`pipeline_step_id` (UUID4) + `flow_id` => `flow_step_id` (`{pipeline_step_id}_{flow_id}`). Update tools REQUIRE `source_url`; fetch handlers store engine-only `source_url` / `image_url` in DB (not in DataPackets) which are injected via `dm_engine_parameters`.

## Database Tables
`wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs` (stores engine_data), `wp_dm_processed_items` (dedupe). Use provided DB services; never raw SQL in new code unless adding a migration hook.

## Caching & Autosave
Autosave (`dm_auto_save`) persists pipeline + flows + syncs `execution_order` then clears pipeline cache. Choose narrowest cache clear action required after structural changes. Only escalate to `dm_clear_all_cache` for cross-cutting schema or directive layer changes.

## Security & Validation
All admin + AJAX paths require `manage_options` + nonce. Sanitize input (`wp_unslash` then `sanitize_text_field` or specific). For HTML content use `wp_kses_post`. Validate URLs (`FILTER_VALIDATE_URL`). Sanitize AI-supplied taxonomy terms; skip system taxonomies (`post_format`, `nav_menu`, `link_category`).

## Adding New Step / Tool / Directive (Checklist)
1 Place class under `inc/Core/...` or `inc/Engine/...` using existing namespace patterns.
2 Register via appropriate filter file (create `*Filters.php` if needed and add to composer autoload `files`).
3 Keep constructor empty; all runtime data from flat parameters.
4 Implement logic; prepend new DataPacket; avoid mutating prior ones.
5 Add focused unit test (parameter shaping, filter registration) under `tests/Unit`.
6 Run `composer test`; then if production-related change, run `./build.sh` (build artifacts live in `build/`).

## Don’ts
No nested parameter bags; no direct transient / option deletions; no global state mutation; no raw SQL for operational paths; no silent failures (always log); no large content dumps in logs.

## Quick Filter Reference
`dm_handlers`, `dm_steps`, `ai_tools`, `ai_request`, `dm_engine_parameters`, `dm_db`, `dm_tool_configured`, `dm_get_tool_config`, `dm_clear_*`, `dm_mark_item_processed`, `dm_is_item_processed`.

---
Feedback welcome: What is missing or unclear for fast agent contributions?
