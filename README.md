=== Data Machine ===

Contributors: extrachill
Tags: ai, automation, content, workflow, pipeline, chat
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.6.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with a visual pipeline builder, conversational chat agent, REST API, and handler/tool extensibility.

## Overview

- **Pipeline + Flow architecture** that separates reusable pipeline templates from scheduled flow instances and job executions.
- **Modern React admin** that relies exclusively on the REST API, TanStack Query, and Zustand for caching, optimistic updates, and client state isolation.
- **Tool-first AI agents** that discover enabling providers, call contextual tools, and persist conversations via a universal engine shared by chat and pipelines.
- **Services layer** (FlowManager, PipelineManager, JobManager, LogsManager, ProcessedItemsManager, FlowStepManager, PipelineStepManager) that replaces filter indirection with direct method calls for predictable behavior and easier testing.
- **Global handler tooling** with modular fetch, publish, and update adapters backed by centralized registration traits and field schemas.

## Key Features

- **Clean execution pipeline**: Fetch → AI → Publish/Update handlers process normalized data packets while engine parameters remain accessible through centralized filters (`datamachine_engine_data`).
- **Deduplication tracking** via processed items and job-scoped logging.
- **Multi-provider AI** support (OpenAI, Anthropic, Google, Grok, OpenRouter) with tool orchestration and directive management through PromptBuilder and RequestBuilder.
- **Unified tool architecture** covering global search/fetch tools and chat-specific workflow tools such as ApiQuery, CreatePipeline, CreateFlow, ConfigureFlowSteps, ConfigurePipelineStep, RunFlow, UpdateFlow, AddPipelineStep, and ExecuteWorkflow.
- **HTTP client standardization** with HttpClient for consistent headers, browser simulation, timeout handling, and logging integrations.
- **Extension-ready systems** using WordPress filters for handlers, tools, authentication providers, and step types.

## Requirements

- WordPress 6.2 or higher and PHP 8.0 or higher.
- Composer for dependency management and vendor autoloading.
- Action Scheduler for scheduled flow execution when jobs rely on cron.

## Architecture highlights

- **EngineData** serves as the platform-agnostic data source, streaming normalized packets and metadata to handlers and tools while shared helpers (WordPressPublishHelper, TaxonomyHandler, WordPressSettingsResolver) address WordPress-specific needs.
- **FilesRepository** modules (DirectoryManager, FileStorage, FileCleanup, ImageValidator, RemoteFileDownloader, FileRetrieval) ensure flow-scoped file handling, validation, and cleanup.
- **Step Navigator** and **Tool Result Finder** keep executions ordered and AI interactions traceable.
- **Prompt/Directive system** centralizes system prompts and directives via `datamachine_directives`, with priorities that layer workflow context, tool definitions, and site information to every request.

## REST API surface

All data flows through `/wp-json/datamachine/v1/`, exposing endpoints for auth, execute, files, flows, pipelines, jobs, logs, processed items, handlers, providers, settings, step types, tools, and other infrastructure needs. Service managers handle validation, sanitation, and error handling before responses reach the REST layer.

## Handler & tool coverage

- **Fetch handlers**: Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API.
- **Publish handlers**: Twitter, Threads, Bluesky, Facebook, WordPress Publish, Google Sheets Output.
- **Update handlers**: WordPress Update with engine parameter support for existing posts.
- **Global AI tools**: Google Search, Local Search, Web Fetch, WordPress Post Reader.
- **Chat/workflow tools**: ExecuteWorkflow, AddPipelineStep, ApiQuery, ConfigureFlowSteps, ConfigurePipelineStep, CreateFlow, CreatePipeline, RunFlow, UpdateFlow.

## Development notes

- Composer scripts define PHPUnit suites (`test`, `test:unit`, `test:integration`, `test:coverage`, `test:verbose`).
- `build.sh` bundles production artifacts into a distributable ZIP with production dependencies.

## Additional resources

- [AGENTS.md](AGENTS.md) provides a concise reference for contributors.
- `/docs/` houses user-facing documentation covering architecture, handlers, AI tools, admin interface guidance, and API references.
