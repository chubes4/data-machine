# Copilot Instructions for Data Machine Plugin

This file is a concise, practical reference for AI coding agents working in the Data Machine WordPress plugin. Focus on discoverable, actionable patterns and files that make a contributor productive immediately.

Core idea (big picture)
- Data Machine implements visual pipelines: reusable pipeline templates (structure) + flow instances (runtime configuration).
- Data flows: fetchers → AI steps → publishers. Each step can transform/enrich the payload.
- Major areas:
  - `inc/Core/` — admin UI, modal templates, step registration, DB abstractions.
  - `inc/Engine/` — pipeline execution, actions, filters (see `inc/Engine/Actions/Engine.php`, `inc/Engine/Actions/`).
  - `lib/ai-http-client/` — unified AI provider client. All provider calls MUST go through this library.
  - `inc/Core/Steps/` — step types and their handlers (ai, fetch, publish, receiver).

Key, actionable patterns (code locations + examples)
- Step registration: add a class under `inc/Core/Steps/{type}/` and register via `add_filter('dm_steps', ...)`. Example: search for `dm_steps` to find registered handlers.
- Fetch pipeline flows: use `apply_filters('dm_get_pipeline_flows', [], $pipeline_id)` to retrieve flows for a pipeline.
- Modal templates: UI templates live in `inc/Core/Admin/Modal/templates/`. Always validate required params and use WordPress i18n functions (see existing templates for patterns).
- Database services and extensibility are exposed via filters such as `dm_db` — look for registrations and implementations under `inc/Core/Database/`.
- Engine actions and lifecycle: inspect `inc/Engine/Actions/Engine.php` and `inc/Engine/Actions/*` to understand how steps are executed and how jobs are enqueued/updated.

Project-specific conventions (non-generic)
- Strict parameter validation: handlers and modal templates validate inputs and fail fast. Mirror that style in new code.
- No hardcoded AI defaults: AI provider and step settings must be explicitly supplied; code throws on missing config.
- Two-layer model (pipeline template vs flow instance): always keep template vs runtime config responsibilities separate.
- Handler organization: each step type has a directory and may include multiple handler classes; keep registration via filters rather than global side effects.
- Use the packaged AI client: all provider calls must go through `lib/ai-http-client/` (see `lib/ai-http-client/README.md` and `lib/ai-http-client/ai-http-client.php`).

Developer workflows & commands (discoverable in repo)
- Install dependencies: from plugin root run `composer install`.
- Run tests: `vendor/bin/phpunit` (project has `phpunit.xml`).
- Activate plugin: via WordPress admin UI (standard WP activation).
- Update bundled AI client: documented in `lib/ai-http-client/README.md` — typically updated via `git subtree pull --prefix=lib/ai-http-client ...`.

Integration points & cross-component communication
- AI providers: centralised in `lib/ai-http-client/`. Do not call providers directly from step handlers.
- Filters: most extensibility and wiring uses WordPress filters (e.g., `dm_steps`, `dm_db`, `dm_get_pipeline_flows`). Grep for `apply_filters`/`add_filter` to trace behavior.
- External publishing & webhooks: publishing handlers live in `inc/Core/Steps/Publish/`; receiver/webhook handlers in `inc/Core/Steps/Receiver/`.

What to search first when changing behavior
- `dm_steps`, `dm_db`, `dm_get_pipeline_flows`, `apply_filters('dm_` (to find filter points)
- `inc/Engine/Actions/Engine.php` and `inc/Engine/Actions/` to understand execution lifecycle
- `lib/ai-http-client/` to confirm provider API usage and credential flow
- `inc/Core/Admin/Modal/templates/` for UI and validation patterns

Documentation to consult
- Root `CLAUDE.md` (primary codebase documentation — always consult before major changes).
- `README.md` (architecture quickstart and diagrams).
- `lib/ai-http-client/README.md` (provider client usage and update instructions).
- `inc/Core/Steps/Receiver/README.md` (webhook/receiver patterns).

Final notes
- Be concrete: prefer following existing filter registration and handler patterns rather than introducing global state.
- Preserve backward compatibility for registered filters and DB schemas where possible.

If anything in this summary is unclear or you want more examples (specific files or call sequences), tell me which area to expand and I will iterate.
