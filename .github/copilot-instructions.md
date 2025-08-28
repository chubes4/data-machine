# Copilot Instructions for Data Machine (WordPress plugin)

Quick context for AI coding agents: what matters, where to look, and how to work productively in this repo.

Big picture
- Visual pipelines: reusable pipeline templates + flow instances. Typical flow: Fetch → AI → Publish/Update.
- Runtime engine cycle: do_action('dm_run_flow_now') → do_action('dm_execute_step') → do_action('dm_schedule_next_step'). See `inc/Engine/Actions/Engine.php`.
- Two IDs: `pipeline_step_id` (UUID4, template-level) and `flow_step_id` (`{pipeline_step_id}_{flow_id}`, runtime). Dedup lives in `wp_dm_processed_items`.

Where things live
- Engine: `inc/Engine/Actions/*` (Engine.php, Update.php, Delete.php) and `inc/Engine/Filters/*` (Admin.php, OAuth.php, Logger.php, FilesRepository.php).
- Steps: `inc/Core/Steps/{AI|Fetch|Publish|Update}/**` (handlers, settings, Filters files).
- Admin UI: `inc/Core/Admin/**` (modal templates under `Modal/templates/`).
- AI client: `lib/ai-http-client/**` (all provider calls via `apply_filters('ai_request', ...)`).

Core runtime contracts (filters/actions)
- Discovery: `dm_steps`, `dm_handlers`, `dm_db`, `dm_admin_pages`.
- Pipeline data: `dm_get_pipelines`, `dm_get_pipeline_steps`, `dm_get_pipeline_flows`, `dm_get_flow_config`.
- Execution: `dm_run_flow_now`, `dm_execute_step`, `dm_schedule_next_step`, `dm_generate_flow_step_id`, `dm_get_next_flow_step_id`.
- Items/dedup: `dm_mark_item_processed`, `dm_is_item_processed`.
- AI & tools: `ai_request`, `ai_tools`, `dm_tool_configured`, `dm_get_tool_config`, `dm_apply_global_defaults`.
- OAuth & HTTP: `dm_retrieve_oauth_account`, `dm_store_oauth_account`, `dm_clear_oauth_account`, `dm_retrieve_oauth_keys`, `dm_store_oauth_keys`, `dm_get_oauth_url`, `dm_auth_providers`, `dm_request` (HTTP wrapper).
- Files repo & status: `dm_files_repository`, `dm_detect_status`, `dm_log`.

Project-specific conventions
- Fail fast: no hardcoded AI defaults. Provider, model, and tool config must be explicit or handlers return errors. See `inc/Core/Steps/AI/AIStep.php` and `inc/Engine/Filters/AI.php`.
- Tool-first AI: AI step exposes tools for the immediate next handler only. For multi-platform, use AI→Publish→AI→Publish. See root `README.md`.
- Self-registration: new capabilities are added in `*Filters.php` and auto-loaded via Composer; avoid global state. Example: `inc/Core/Steps/AI/AIStepFilters.php`.
- Security & UX: `manage_options` for admin actions, `wp_unslash()` before `sanitize_text_field()`, and WordPress i18n in templates.
- Provider access: never call vendor SDKs directly; always use `lib/ai-http-client` via `ai_request`. Models and keys flow through that library.

Common tasks (snippets)
- Register a step: add under `inc/Core/Steps/{type}/` and `add_filter('dm_steps', fn($s)=>$s+['my_step'=>['class'=>MyStep::class,'name'=>__('My Step','data-machine')]]);`
- Publish tool for a handler: hook `ai_tools` conditionally by `$handler_slug` and route to `handle_tool_call()` on the handler class.
- Run a flow now: `do_action('dm_run_flow_now', $flow_id, 'manual');`

Dev workflows
- Install: `composer install` (root). Run tests: `vendor/bin/phpunit` (uses `phpunit.xml`).
- Build production zip: `./build.sh` (outputs under `/build/`). Activate via WordPress admin.
- Update AI client subtree: see `lib/ai-http-client/README.md` (git subtree add/pull commands).
- Debug: Logs available via `do_action('dm_log', ...)` and WP debug log; status checks via `dm_detect_status`.

Search tips to change behavior fast
- Grep for: `apply_filters('dm_`, `do_action('dm_`, `ai_request`, `dm_steps`, `dm_handlers`.
- Start with: `inc/Engine/Actions/Engine.php`, `inc/Engine/Actions/Update.php`, `inc/Engine/Filters/*`, and the relevant handler directory.

Primary docs
- Root `CLAUDE.md` (full architecture, filter index), root `README.md` (quickstart, examples), and `lib/ai-http-client/README.md`.

Keep compatibility
- Follow existing filter names and data shapes; don’t change DB schemas or public filters without a shim.
