# Data Machine: AI Agent Working Instructions

Focused, codebase-specific rules for fast, correct edits. Full context: `README.md`, `docs/architecture.md`, `docs/overview.md`, `CLAUDE.md`.

## Architecture Brief
- Pipeline → Flow → Job → Step (fetch|ai|publish|update). Steps operate on a newest-first DataPacket array; prepend via `array_unshift` only.
- Discovery is filter-driven; never new-up or hard-wire classes. Registration lives in composer `autoload.files` entries under `inc/**/ *Filters.php`.

## Conventions
- Parameters: Core parameters (`job_id`, `flow_step_id`, `flow_step_config`, `data`) always provided. Engine data accessed via `dm_engine_data` filter as needed.
- Engine Data: Access via `dm_engine_data` filter for centralized retrieval (no globals/singletons).
- Logging: `do_action('dm_log', level, message, context)`. Summarize bodies with `content_length`; avoid dumping raw content.
- Caching: Use actions (`dm_clear_pipeline_cache`, `dm_clear_flow_cache`, `dm_clear_jobs_cache`, `dm_clear_all_cache`, `dm_cache_set`). Do not touch transients/options directly.
- History: Never mutate older DataPackets; safe metadata append is allowed.

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

## Tools + Parameters
- `ai_tools` returns `[ id => [class, method, description, parameters?, handler?, requires_config?, handler_config?] ]`.
- Handler tools surface only when the NEXT step’s handler matches.
- Build flat params with `inc/Core/Steps/AI/AIStepToolParameters.php` (`buildParameters`, `buildForHandlerTool`).
- Example paths: general tools `inc/Core/Steps/AI/Tools/*`, WordPress post reader `Tools/WordPressPostReader.php`.

## WordPress Publish/Update
- Canonical handler: `inc/Core/Steps/Publish/Handlers/WordPress/WordPress.php` (uses `FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`).
- Flow: validate → merge system defaults overriding handler config → sanitize (`wp_kses_post`, block parse/serialize) → taxonomy resolution (skip|fixed IDs|AI-decides with `wp_insert_term`) → log → return `[ 'success'=>bool, 'data'=>..., 'tool_name'=>'wordpress_publish' ]`.
- Update tools require `source_url`; fetch handlers persist `source_url`/`image_url` in DB; steps retrieve via `dm_engine_data` filter.

## IDs, DB, and Autosave
- `pipeline_step_id` + `flow_id` → `flow_step_id` (`{pipeline_step_id}_{flow_id}`).
- Tables: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs` (stores `engine_data`), `wp_dm_processed_items` (dedupe). Use DB services via filters; avoid raw SQL except migrations.
- `dm_auto_save` persists pipelines/flows, syncs `execution_order`, then clears pipeline cache.

## Dev Workflows
- Build plugin zip to `build/`: `./build.sh` (requires `rsync`, `composer`).
- Run tests: `composer test` | unit: `composer test:unit` | coverage: `composer test:coverage`.
- VS Code tasks: "Install Dependencies", "Run Tests", "Build Data Machine Plugin".

## Where To Add Things
- Steps/Handlers: `inc/Core/Steps/**`; register in matching `*Filters.php` and add to composer `autoload.files` (then `composer dump-autoload`).
- Engine actions/filters: `inc/Engine/Actions/*`, `inc/Engine/Filters/*`.
- Admin UI: `inc/Core/Admin/**` (pages, settings, modals) with filter registration.

## Quick Hooks Reference
Filters: `dm_steps`, `dm_handlers`, `ai_tools`, `ai_request`, `dm_engine_data`, `dm_db`, `dm_tool_configured`, `dm_get_tool_config`.
Actions: `dm_auto_save`, `dm_clear_*`, `dm_mark_item_processed`, `dm_log`.

Feedback welcome: If any pattern here is unclear or missing, point me at the file/feature and I’ll tighten this guide.
