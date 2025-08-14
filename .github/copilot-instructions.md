# Copilot Instructions for Data Machine Plugin

## Project Overview
- **Data Machine** is a WordPress plugin for building AI-powered content processing workflows using a visual pipeline/flow architecture.
- **Major Components:**
  - `inc/core/`: Core logic, including admin UI, database, and step/engine logic.
  - `engine/`: Pipeline execution, actions, and filters.
  - `lib/ai-http-client/`: Unified AI provider client (OpenAI, Anthropic, Google, etc.).
  - `inc/core/steps/`: Step types (ai, fetch, publish, receiver) with handler subfolders.
- **Architecture:**
  - **Pipeline Templates** define reusable step sequences (see README for diagrams).
  - **Flow Instances** are configured runs of a pipeline (e.g., "Daily Tech News").
  - **Steps** are modular and registered via WordPress filters (see `dm_steps`).
  - **Data flows** from fetchers → AI steps → publishers, with each step able to modify or enrich the data.

## Key Patterns & Conventions
- **Strict Parameter Validation:** All modal templates and step logic validate required parameters and fail fast (see `confirm-delete.php`).
- **Filter-Based Extensibility:** New steps, handlers, and database services are registered via WordPress filters (e.g., `dm_steps`, `dm_db`).
- **No Hardcoded Defaults:** AI provider config and step settings must be explicitly set; errors are thrown if missing.
- **Two-Layer Design:** Always distinguish between pipeline templates (structure) and flow instances (runtime config).
- **Handler Organization:** Each step type (ai, fetch, publish, receiver) has its own handler directory and registration pattern.
- **AI HTTP Client:** Use the unified client in `lib/ai-http-client/` for all AI provider calls; do not call providers directly.

## Developer Workflows
- **Install:** `composer install` (from plugin root)
- **Activate:** Via WordPress admin
- **Test:** PHPUnit config in `phpunit.xml`; run with `vendor/bin/phpunit`
- **Update AI Client:** Use `git subtree pull --prefix=lib/ai-http-client ...` as described in its README

## Examples
- **Registering a Step:**
  - Add a class in `inc/core/steps/{type}/` and register via `add_filter('dm_steps', ...)`
- **Adding a Modal:**
  - Place template in `inc/core/admin/modal/templates/`, validate all required params, and use WordPress i18n functions.
- **Fetching Pipeline Flows:**
  - Use `apply_filters('dm_get_pipeline_flows', [], $pipeline_id)`

## Integration Points
- **AI Providers:** All communication via `lib/ai-http-client/`
- **External Publishing:** Handlers in `inc/core/steps/publish/`
- **Webhooks/APIs:** Use the receiver step framework (`inc/core/steps/receiver/`)

## References
- See main `README.md` for architecture diagrams and quickstart.
- See `lib/ai-http-client/README.md` for AI client usage and update instructions.
- See `inc/core/steps/receiver/README.md` for webhook/API integration patterns.

---

If you are unsure about a pattern, search for usages of the relevant filter or class in the codebase.
