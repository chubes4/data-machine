# CLAUDE.md

Data Machine — WordPress plugin for automating content workflows with AI. Visual pipeline builder, chat agent, REST API, and extensibility via handlers and tools.

Version: 0.10.2

This file provides a concise, present-tense technical reference for contributors and automated agents. For user-focused docs see datamachine/docs/.

Engine & execution

- The engine executes flows via a four-action cycle (@since v0.8.0): `datamachine_run_flow_now` → `datamachine_execute_step` → `datamachine_schedule_next_step`. `datamachine_run_flow_later` handles deferred/recurring scheduling.
- The system supports direct execution (@since v0.8.0) for ephemeral workflows that execute without database persistence. Use `flow_id='direct'` and/or `pipeline_id='direct'` to trigger direct execution mode. Configuration is stored dynamically in the job's engine snapshot.
- Scheduling is handled via WordPress Action Scheduler using the `data-machine` group.

Core architecture

- **Services Layer** (@since v0.4.0) - OOP service managers replace filter-based actions for 3x performance improvement:
  - `FlowManager` - Flow CRUD operations, duplication, step synchronization
  - `PipelineManager` - Pipeline CRUD operations with complete/simple creation modes
  - `JobManager` - Job execution monitoring and management
  - **LogsManager** - Centralized log access and filtering (DB-based: `wp_datamachine_logs`)
  - `ProcessedItemsManager` - Deduplication tracking across workflows
  - `FlowStepManager` - Individual flow step configuration and handler management
  - `PipelineStepManager` - Pipeline step template management
- Base classes for `Step`, `FetchHandler`, `PublishHandler`, `UpdateHandler`, `SettingsHandler`, and `DataPacket` provide consistent behavior and reduce duplication.
- Base authentication provider architecture (`BaseAuthProvider`, `BaseOAuth1Provider`, `BaseOAuth2Provider`) centralizes option storage and authentication validation across all providers (@since v0.2.6).
- FilesRepository is modular (storage, cleanup, validation, download, retrieval) and provides flow-isolated file handling.
- EngineData provides platform-agnostic data access (single source of truth for engine parameters).
- WordPressPublishHelper provides WordPress-specific publishing operations (image attachment, source attribution).
- WordPressSettingsResolver provides centralized settings resolution with system defaults override.
- Handler and Tool registration use standardized traits (`HandlerRegistrationTrait`, `StepTypeRegistrationTrait`, `ToolRegistrationTrait`) to auto-register services via WordPress filters.
- Cache Management: Centralized cache invalidation via `CacheManager` (handlers, step types, tools) and `SiteContext` (site metadata). Admin UI uses TanStack Query for client-side state.
- Prompt and directive management is centralized via a PromptBuilder with ordered directives (site, pipeline, flow, context).
- Providers are pluggable and configured by site administrators (OpenAI, Anthropic, Google, Grok, OpenRouter).
- Universal Engine architecture supports both Pipeline and Chat agents with shared AI infrastructure.
- Universal Web Scraper Architecture: A multi-layered system in `datamachine-events` that prioritizes structured data extraction (Schema.org JSON-LD/Microdata and 17+ specialized extractors) before falling back to AI-enhanced HTML section parsing. It coordinates fetching, pagination, and normalization via a centralized `StructuredDataProcessor`.
- Integrated Chat Sidebar: React-based context-aware chat interface in the Pipeline Builder that passes `selected_pipeline_id` for prioritized context.
- Specialized chat tools provide focused workflow management: AddPipelineStep, ApiQuery, AuthenticateHandler, ConfigureFlowSteps, ConfigurePipelineStep, CopyFlow, CreateFlow, CreatePipeline, CreateTaxonomyTerm, ExecuteWorkflowTool, GetHandlerDefaults, ManageLogs, ReadLogs, RunFlow, SearchTaxonomyTerms, SetHandlerDefaults, UpdateFlow.
- Focused Tools Strategy: Mutation operations (creation, deletion, duplication) are handled by specialized Focused Tools. The `ApiQuery` tool is strictly read-only for discovery and monitoring.
- Job Status Logic: Jobs use `completed_no_items` to distinguish between a successful execution that found no new items versus an actual `failed` execution. The jobs table is the single source of truth for execution status.
- Flow Monitoring: Problem flows are identified by computing consecutive failure/no-item counts from job history. Flows exceeding the `problem_flow_threshold` (default 3) are monitored via the `get_problem_flows` tool and `/flows/problems` endpoint.

Database

- Core tables store pipelines, flows, jobs, processed items, and chat sessions. See datamachine/inc/Core/Database/ for schema definitions used in code.

Security & conventions

- Use capability checks for admin operations (e.g., `manage_options`).
- Sanitize inputs (`wp_unslash()` then `sanitize_*`).
- Follow PSR-4 autoloading and PSR coding conventions for PHP where applicable.
- Prefer REST API over admin-ajax.php and vanilla JS over jQuery in the admin UI.

Agent guidance (for automated editors)

- Code-first verification: always validate claims against the code before editing docs. Read the relevant implementation files under `data-machine/inc/` and `data-machine/docs/`.
- Make minimal, targeted documentation edits; preserve accurate content and explain assumptions in changelogs.
- Use present-tense language and remove references to deleted functionality or historical counts.
- Do not modify source code when aligning documentation unless explicitly authorized.

- Do not create new top-level documentation directories. Creating or updating `.md` files is allowed only within existing directories.

Where to find more

- User docs: data-machine/docs/
- Code: data-machine/inc/
- Admin UI source: data-machine/inc/Core/Admin/Pages/
