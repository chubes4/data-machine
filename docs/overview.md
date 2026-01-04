# Data Machine User Documentation

**AI-first WordPress plugin for automating and orchestrating content workflows with a visual pipeline builder, conversational chat agent, REST API, and extensibility through handlers and tools.**

## System Architecture

- **Pipelines** are reusable workflow templates that store handler order, tool selections, and AI settings.
- **Flows** instantiate pipelines with schedule metadata, flow-level overrides, and runtime configuration values stored per flow.
- **Jobs** track individual flow executions, persist engine parameters, and power the fully React-based Jobs dashboard for real-time monitoring.
- **Steps** execute sequentially (Fetch → AI → Publish/Update) with shared base classes that enforce validation, logging, and engine data synchronization.

## Services Layer

The services layer (DataMachine\Services) provides direct method calls for core operations:

- `FlowManager`, `PipelineManager`, `FlowStepManager`, and `PipelineStepManager` handle creation, duplication, synchronization, and ordering.
- `JobManager` monitors execution outcomes and updates statuses.
- `LogsManager` aggregates log entries in the `wp_datamachine_logs` table for filtering in the admin UI.
- `ProcessedItemsManager` deduplicates content across executions by tracking previously processed identifiers.
- `CacheManager` provides centralized cache invalidation to ensure dynamic handler and step type registrations are immediately reflected across the system.

Services are the single source of truth for REST endpoints, ensuring validation and sanitization before persisting data or enqueuing jobs.

## Data Flow

- **DataPacket** standardizes the payload (content, metadata, attachments) that AI agents receive, keeping packets chronological and clean of URLs when not needed.
- **EngineData** stores engine-specific parameters such as `source_url`, `image_url`, and flow context, which fetch handlers persist via the `datamachine_engine_data` filter for downstream handlers.
- **FilesRepository modules** (DirectoryManager, FileStorage, RemoteFileDownloader, ImageValidator, FileCleanup, FileRetrieval) isolate file storage per flow, validate uploads, and enforce automatic cleanup after jobs complete.

## AI Integration

- **Tool-first architecture** enables AI agents (pipeline and chat) to call tools that interact with handlers, external APIs, or workflow metadata.
- **PromptBuilder + RequestBuilder** apply layered directives via the `datamachine_directives` filter so every request includes identity, context, and site-specific instructions.
- **Global tools** (Google Search, Local Search, Web Fetch, WordPress Post Reader) are registered under `/inc/Engine/AI/Tools/` and available to all agents.
- **Chat-specific tools** (AddPipelineStep, ApiQuery, AuthenticateHandler, ConfigureFlowSteps, ConfigurePipelineStep, CopyFlow, CreateFlow, CreatePipeline, CreateTaxonomyTerm, ExecuteWorkflowTool, GetHandlerDefaults, ManageLogs, ReadLogs, RunFlow, SearchTaxonomyTerms, SetHandlerDefaults, UpdateFlow) orchestrate pipeline and flow management within conversations.
- **ToolParameters + ToolResultFinder** gather parameter metadata for tools and interpret results inside data packets to keep conversations consistent.

## Authentication & Security

- **Authentication providers** extend BaseAuthProvider, BaseOAuth1Provider, or BaseOAuth2Provider under `/inc/Core/OAuth/`, covering Twitter, Reddit, Facebook, Threads, Google Sheets, and Bluesky (app passwords).
- **OAuth handlers** (`OAuth1Handler`, `OAuth2Handler`) standardize callback handling, nonce validation, and credential storage.
- **Capability checks** (`manage_options`) and WordPress nonces guard REST endpoints; inputs run through `sanitize_*` helpers before hitting services.
- **HttpClient** centralizes outbound HTTP requests with consistent headers, browser-mode simulation, timeout control, and logging via `datamachine_log`.

## Scheduling & Jobs

- **Action Scheduler** drives scheduled flow execution while REST endpoints handle immediate runs.
- **Flow schedules** support manual runs, single-execution jobs, recurring intervals (hourly/daily/weekly/monthly/custom), and job metadata such as `last_run_status` and `last_run_at`.
- **JobManager** updates statuses, emits extensibility actions (`datamachine_update_job_status`), and links jobs to logs and processed items for auditing.

## Admin Interface

- **React-First Architecture**: The entire admin interface is built with React and `@wordpress/components`, utilizing TanStack Query for server state and Zustand for client state management.
- **Pipeline Builder**: Drag-and-drop workflow configuration, real-time step validation, and integrated tool management.
- **Job Management**: Fully React-based dashboard for monitoring job history, status progression, and execution metrics with automatic background refetching.
- **Logs Interface**: Centralized, real-time log streaming and filtering for deep-dive troubleshooting.
- **Integrated Chat**: Collapsible sidebar for context-aware pipeline automation and AI-driven workflow assistance, using specialized tools to manage the entire ecosystem.

## Key Capabilities

- **Multi-platform publishing** via dedicated fetch/publish/update handlers for files, RSS, Reddit, Google Sheets, WordPress, Twitter, Threads, Bluesky, Facebook, and Google Sheets output.
- **Extension points** through filters such as `datamachine_handlers`, `chubes_ai_tools`, `datamachine_step_types`, `datamachine_auth_providers`, and `datamachine_engine_data`.
- **Directive orchestration** ensures every AI request is context-aware, tool-enabled, and consistent with site policies.
- **Chartable logging, deduplication, and error handling** keep operators informed about job outcomes and prevent duplicate processing.
