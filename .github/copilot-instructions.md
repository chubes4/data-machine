# Data Machine: AI Agent Working Instructions

Concise, project-specific rules so an AI agent can be productive fast. (Source of truth for architecture remains `README.md` + `CLAUDE.md`). Keep edits consistent with these patterns.

## 1. Core Mental Model
Pipeline (template) → Flow (instance) → Jobs (executions) → Steps (fetch|ai|publish|update) working over a DataPacket array (newest unshifted to front). AI steps sit between content ingress (Fetch) and egress (Publish/Update). All functionality is discovered/extended via WordPress filters declared in numerous `*Filters.php` files auto-loaded through Composer (see `composer.json` autoload.files). Engine code itself remains agnostic of concrete handlers.

## 2. Mandatory Conventions
- Flat parameter structure: Every step `execute(array $parameters)` or tool handler `handle_tool_call(array $parameters, array $tool_def=[])` receives a single flat associative array. NEVER introduce nested parameter objects unless unavoidable.
- Required base keys always expected when executing steps: `job_id`, `flow_step_id`, `data` (DataPacket array), `flow_step_config`.
- New engine/step parameters must pass through `apply_filters('dm_engine_parameters', ...)` – do not access globals directly.
- Register anything (handlers, tools, directives, db services, admin pages) ONLY via existing filters (`dm_handlers`, `ai_tools`, `ai_request`, `dm_db`, `dm_admin_pages`, etc.) – no manual inclusion chains.
- Cache or config invalidation: use provided actions (`dm_clear_pipeline_cache`, `dm_clear_flow_cache`, etc.) – never delete transients directly.
- Logging: ALWAYS route diagnostics through `do_action('dm_log', $level, $message, $contextArray)` (levels: debug|info|warning|error).

## 3. Data Structures & Flow
DataPacket element shape:
```
[
  'type' => 'fetch|ai|publish|update',
  'handler' => 'rss|twitter|wordpress|...', // optional for ai
  'content' => ['title' => string, 'body' => string],
  'metadata' => [...],
  'timestamp' => int
]
```
Engine prepends newest packet with `array_unshift`. Steps should preserve prior history.

## 4. AI Directive / Conversation System
Six ordered directive injectors (priorities 5→50): `PluginCoreDirective`, `GlobalSystemPromptDirective`, `PipelineSystemPromptDirective`, `ToolDefinitionsDirective`, `DataPacketStructureDirective`, `SiteContextDirective` all hook `ai_request`. Preserve spacing (5,10,20,30,40,50) if adding a new layer; choose an unused priority gap. Conversation state and tool messaging go through `AIStepConversationManager` (turn counter + chronological array). Tool success/failure messages may be filtered but keep existing semantic patterns.

## 5. Tools Architecture
`ai_tools` filter returns an associative map: key = tool_id. Universal (general) tools omit `handler`; handler tools include `handler` and only appear when the next step’s handler matches. Each tool entry minimally: `class`, `method`, `description`, optional `parameters[]`, `requires_config`, `handler_config`. Parameters are merged into flat structure by `AIStepToolParameters` (build* methods). When adding parameters, prefer primitive scalars or simple arrays; the system expects direct key access.

## 6. WordPress Publish Handler Pattern
See `inc/Core/Steps/Publish/Handlers/WordPress/WordPress.php` for canonical handler implementation: validate required parameters early, derive effective settings via global defaults override (post status, author, include source, images), sanitize block content (`wp_kses_post` + `parse_blocks`/`serialize_blocks`), process taxonomies (AI decided vs fixed ID vs skip), log at each decision branch, return uniform shape: `[ 'success'=>bool, 'data'=>[...], 'tool_name'=>'wordpress_publish']` (or `error`). Replicate this style for new handlers.

## 7. Taxonomy & Dynamic Term Creation
When AI supplies taxonomy terms (`ai_decides` path), create missing terms with `wp_insert_term`, accumulate IDs, then call `wp_set_object_terms`. Always sanitize term names and log failures at `warning` level. Skip system taxonomies: `post_format`, `nav_menu`, `link_category`.

## 8. Build & Test Workflow
Local dev: `composer install`. Run tests: `composer test` (unit + integration) or `composer test:unit`. Production build: `./build.sh` (runs prod install, rsync with `.buildignore`, validates required assets, repackages zip, restores dev deps). Never commit build artifacts except inside `build/` as produced by script. If adding dev-only files ensure they’re in `.buildignore`.

## 9. Database & IDs
Core tables: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items`. IDs: `pipeline_step_id` (UUID4, template level) → `flow_step_id` = `{pipeline_step_id}_{flow_id}` for instance. Update handlers require `source_url` in parameters; ensure preceding fetch supplies it. Deduplication uses `dm_mark_item_processed` / `dm_is_item_processed` filters.

## 10. Caching Strategy
Use cache actions (clear/set) rather than direct transient APIs. Key families: pipeline, flow, jobs. If code mutates pipeline/flow/job definitions, trigger the narrowest clearing action; only escalate to `dm_clear_all_cache` on structural migrations.

## 11. Security & Validation
All admin / AJAX operations require `manage_options` and nonce (`dm_ajax_actions`). Sanitize external input: `wp_unslash` then `sanitize_text_field` (or appropriate sanitizer). For block HTML use `wp_kses_post`. Validate URLs via `FILTER_VALIDATE_URL`. Never trust AI-supplied taxonomy/term names without sanitation.

## 12. OAuth & External Config
Tool enablement checks: `apply_filters('dm_tool_configured', false, $tool_id)`. Deny UI enablement when `requires_config` and not configured. OAuth endpoints follow `/dm-oauth/{provider}/`. Do not implement bespoke storage—use existing provider filters.

## 13. Extension Points (Minimal Contracts)
- Fetch handler: `get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id=null): array`
- Publish/Update handler: `handle_tool_call(array $parameters, array $tool_def=[]): array`
- Step: `execute(array $parameters): array`
Registration strictly through filters; no direct instantiation from engine code.

## 14. Logging Expectations (Examples)
Use structured context arrays; keep keys short yet descriptive:
```
do_action('dm_log','debug','WordPress Tool: Final post data',[ 'post_type'=>$post_data['post_type'],'content_length'=>strlen($post_data['post_content']) ]);
```
Avoid dumping full content bodies; include length instead.

## 15. Adding New Directive / Tool / Handler (Checklist)
1. Create class under correct PSR-4 path (`inc/Core/...` or `inc/Engine/...`).
2. Register via a new `*Filters.php` (added to composer.json autoload.files if new directory scope) OR extend existing file logically.
3. Hook filter with unique key; pick priority respecting existing ordering.
4. Implement method returning/accepting flat arrays; add logging at critical branches.
5. Write/update tests under `tests/Unit` (focus on parameter shaping + filter behavior).
6. Run `composer test`; ensure no build break. Update `.buildignore` if new dev assets.

## 16. Don’ts
- Don’t bypass filter system with direct global state edits.
- Don’t introduce nested parameter bags or objects where an array suffices.
- Don’t manually clear transients or write raw SQL—use provided abstractions.
- Don’t silence errors—always log with context.

## 17. Quick Reference Filters (Read Only Reminder)
`dm_handlers`, `ai_tools`, `ai_request`, `dm_db`, `dm_engine_parameters`, `dm_tool_configured`, `dm_get_tool_config`, `dm_clear_*`, `dm_mark_item_processed`, `dm_is_item_processed`.

---
Feedback welcome: identify unclear sections or new recurring patterns to append here.
