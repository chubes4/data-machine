## Data Machine: Agent Guide

Purpose-built instructions for AI agents working in this repo. Read alongside: `README.md`, `docs/architecture.md`, `docs/overview.md`, `CLAUDE.md`.

### Architecture
- Pipeline → Flow → Job → Step (`fetch|ai|publish|update`). Steps pass a newest-first DataPacket array (use `apply_filters('dm_data_packet', ...)` which prepends; do not append or mutate history).
- Service discovery is filter-driven; never instantiate directly. Register via `*Filters.php` under `inc/**` (autoloaded by composer).

### Core Conventions
- Parameters into steps: `job_id`, `flow_step_id`, `flow_step_config`, `data`. Fetch handlers may store engine parameters (e.g., `source_url`, `image_url`) into DB; retrieve via `apply_filters('dm_engine_data', [], $job_id)`.
- Logging: `do_action('dm_log', $level, $message, $context)`. Log shapes/lengths, not raw bodies.
- Caching: clear via actions (`dm_clear_pipeline_cache`, `dm_clear_flow_cache`, `dm_clear_jobs_cache`, `dm_clear_all_cache`). Don’t write transients/options directly.
- IDs: `flow_step_id = {pipeline_step_id}_{flow_id}`; dedupe via `dm_mark_item_processed`/`dm_is_item_processed` (WordPress Media uses attachment ID; updates use `source_url`).

### Data Packet Shape (conceptual)
```php
[
  'type' => 'fetch|ai|publish|update',
  'handler' => 'rss|reddit|wordpress|...', // optional for ai
  'content' => ['title' => string, 'body' => string],
  'metadata' => [...],
  'attachments' => [...],
  'timestamp' => int
]
```

### Key Filters & Actions (entry points)
- Discovery: `dm_handlers`, `dm_steps`, `dm_db`, `dm_auth_providers`
- Flow config: `dm_get_flow_config`, `dm_get_flow_step_config`, `dm_get_next_flow_step_id`
- Execution: `dm_run_flow_now`, `dm_execute_step`, `dm_schedule_next_step`
- Data packets: `dm_data_packet`
- Engine data: `dm_engine_data` (fetch stores; publish/update read)
- Tools: `ai_tools`, `dm_tool_configured`, `dm_get_tool_config`

### Patterns That Matter Here
- Handlers return `['processed_items' => [$item, ...]]`. Each `$item` typically has `data` (clean content) and `metadata`. Example: WordPress Media populates `data.title` + `data.content` when `include_parent_content` is enabled, and stores `source_url`/`image_url` via DB for later use.
- Steps must not mutate prior packets. To add a packet: `$data = apply_filters('dm_data_packet', $data, $entry, $flow_step_id, 'fetch|ai|publish|update');`.
- Admin/AJAX: always `wp_unslash($_POST[...])` before `sanitize_text_field()` (see `PipelineModalAjax.php`, `ModalAjax.php`).

### Developer Workflows
- Install deps/tests: `composer install` → `composer test` (unit: `composer test:unit`, coverage: `composer test:coverage`).
- Build plugin zip: `./build.sh` → outputs to `build/`.
- VS Code tasks available: Install Dependencies, Run Tests, Build Data Machine Plugin.

### Where to Add/Change
- Steps/Handlers: `inc/Core/Steps/**` + register in matching `*Filters.php`; ensure composer autoload includes the file (then `composer dump-autoload`).
- Engine: `inc/Engine/Actions/*` (execution, scheduling), `inc/Engine/Filters/*` (config/db/access).
- Admin UI: `inc/Core/Admin/**` with template rendering via `dm_render_template` filter. Universal handler settings template lives at `inc/Core/Admin/Pages/Pipelines/templates/modal/handler-settings.php`.

### WordPress Publish/Update Specifics
- Publish/Update handler: `inc/Core/Steps/Publish/Handlers/WordPress/WordPress.php` (uses `FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`). Always sanitize (`wp_kses_post` + block parse/serialize). `source_url` is the canonical link for updates.

### Gotchas (from this repo)
- Don’t double-escape AJAX payloads; `wp_unslash()` then sanitize.
- Media fetches: keep `processed_items` flat; include parent post content when configured; store `source_url`/`image_url` to DB (not in the packet) and access via `dm_engine_data`.
- When adding packets, avoid direct `array_unshift`; use `dm_data_packet` filter to maintain standardized fields.

Feedback: If any part of this is unclear for a task, point to the file/handler and we’ll refine this guide.
