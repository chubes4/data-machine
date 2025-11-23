# CLAUDE.md

Data Machine — WordPress plugin for automating content workflows with AI. Visual pipeline builder, chat agent, REST API, and extensibility via handlers and tools.

Version: 0.2.5

This file provides a concise, present-tense technical reference for contributors and automated agents. For user-focused docs see datamachine/docs/.

Engine & execution

- The engine executes flows by running steps in sequence. Key actions and filters govern run, step execution, and scheduling.
- Fetch handlers store engine parameters (for example `source_url`, `image_url`) for downstream publish/update handlers.

Core architecture

- Base classes for `Step`, `FetchHandler`, `PublishHandler`, `SettingsHandler`, and `DataPacket` provide consistent behavior and reduce duplication.
- Base authentication provider architecture (`BaseAuthProvider`, `BaseOAuth1Provider`, `BaseOAuth2Provider`, `BaseSimpleAuthProvider`) centralizes option storage and authentication validation across all providers (@since v0.2.6).
- FilesRepository is modular (storage, cleanup, validation, download, retrieval) and provides flow-isolated file handling.
- WordPress shared components centralize publishing concerns (featured image, taxonomy, source URL handling).
- Handler and Tool registration use standardized traits to auto-register services via WordPress filters.

REST API & Admin

- REST endpoints are available under `/wp-json/datamachine/v1/`. See datamachine/docs/api/index.md for current endpoint details and payloads (reference to the API index is provided as a filename, not an inline .md link).
- Admin UI is built with React and integrates with the REST API. The UI uses server-side caching and client-side state separation for performance.

AI integration

- Tool-first architecture: AI agents call registered tools; ToolManager centralizes discovery and validation.
- Prompt and directive management is centralized via a PromptBuilder with ordered directives (site, pipeline, flow, context).
- Providers are pluggable and configured by site administrators.

Database

- Core tables store pipelines, flows, jobs, processed items, and chat sessions. See datamachine/inc/Core/Database/ for schema definitions used in code.

Security & conventions

- Use capability checks for admin operations (e.g., `manage_options`).
- Sanitize inputs (`wp_unslash()` then `sanitize_*`).
- Follow PSR-4 autoloading and PSR coding conventions for PHP where applicable.
- Prefer REST API over admin-ajax.php and vanilla JS over jQuery in the admin UI.

Agent guidance (for automated editors)

- Code-first verification: always validate claims against the code before editing docs. Read the relevant implementation files under `datamachine/inc/`, `datamachine/src/`, and `datamachine/docs/`.
- Make minimal, targeted documentation edits; preserve accurate content and explain assumptions in changelogs.
- Use present-tense language and remove references to deleted functionality or historical counts.
- Do not modify source code when aligning documentation unless explicitly authorized.
- When updating documentation files, remove internal `.md` links between documentation files (for example replace `[Overview](overview.md)` with `Overview`). Preserve anchor links (for example `#section`) and external URLs.
- Do not create new top-level documentation directories. Creating or updating `.md` files is allowed only within existing directories.

Where to find more

- User docs: datamachine/docs/
- Code: datamachine/inc/
- Admin UI source: datamachine/src/
