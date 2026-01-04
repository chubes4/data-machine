# Data Machine Documentation

Complete user documentation for Data Machine (v0.6.3), the AI-first WordPress plugin that combines a visual pipeline builder, conversational chat agent, REST API, and handler/tool extensibility under a single workflow engine.

## Quick Navigation

### Core Concepts
- **Overview**: System goals, Pipeline+Flow architecture, and high-level data separation patterns.
- **Architecture**: End-to-end breakdown of execution engine, services layer, and handler infrastructure.
- **Database Schema**: Tables that persist pipelines, flows, jobs, and processed items.
- **Changelog**: Historical summary of notable releases and architectural changes.

### Engine & Services
- **Universal Engine**: Shared AI infrastructure for pipeline and chat agents.
- **AI Conversation Loop**: Turn-based conversation execution with directive orchestration.
- **AI Directives System**: Hierarchical directive injection for contextual AI behavior.
- **Tool Execution**: Centralized discovery, validation, and execution of AI tools.
- **Tool Manager**: Runtime tool enablement, provider checks, and contextual metadata.
- **Request Builder**: Directive-aware construction of provider requests.
- **Conversation Manager**: Message normalization, logging, and tool call tracking.
- **Prompt Builder**: Priority-based directive registration via filters.
- **Parameter Systems**: Unified parameter handling across tools and handlers.
- **Tool Result Finder**: Utility for interpreting tool responses inside data packets.
- **OAuth Handlers**: Base classes for OAuth1/OAuth2 providers and app-password flows.
- **Handler Registration Trait**: Centralized registration pattern for fetch, publish, and update handlers.
- **HTTP Client**: Standardized outbound request flow for handlers with structured logging and browser-mode header support.
- **Import/Export System**: Pipeline configuration backup, migration, and sharing functionality.

### Handler Documentation
- **Fetch Handlers**: Source-specific data retrieval with deduplication, filtering, and engine data storage.
- **Publish Handlers**: Modular destination integrations with consistent response formatting and logging.
- **Update Handlers**: Idempotent WordPress updates that respect engine parameters.

### AI Tools
- **Tools Overview**: Global and context-aware tools available to AI agents.
- **Execute Workflow**: Modular execution of multi-step workflows from the chat toolset.
- **Global Tools**: Google Search, Local Search, Web Fetch, WordPress Post Reader, and others used across agents.
- **Chat Tools**: AddPipelineStep, ApiQuery, ConfigureFlowSteps, ConfigurePipelineStep, CreateFlow, CreatePipeline, RunFlow, UpdateFlow, and other workflow management tools.

### API Reference
- **API Overview**: Catalog of REST endpoints backed by the services layer.
- **Auth, Execute, Files, Flows, Jobs, Logs**: Resource-specific reference pages.
- **Handlers, Providers, Settings, Tools**: Metadata and configuration endpoints for admin UI consumption.

### Admin Interface
- **Pipeline Builder**: React-based page for creating pipelines, configuring steps, and enabling tools.
- **Settings Configuration**: Provider credentials, tool defaults, and global behavior settings.
- **Jobs Management**: React-based job history, log streaming, and failure analysis.

## Documentation Structure
```
docs/
├── overview.md                        # System overview, data flow, and key concepts
├── architecture.md                    # Execution engine, architecture principles, and shared components
├── CHANGELOG.md                       # Semantic changelog for releases
├── core-system/                       # Engine, services, and core infrastructure pieces
│   ├── ai-directives.md               # AI directive system and priority hierarchy
│   ├── http-client.md                 # Centralized HTTP client architecture
│   ├── import-export.md               # Pipeline import/export functionality
│   └── [other core system docs...]
├── handlers/                          # Fetch, publish, and update handler specifics
├── ai-tools/                          # AI agent tools, workflows, and tool usage
├── admin-interface/                   # User guidance for admin pages
├── api/                               # REST API usage and parameter documents
├── api-reference/                     # Filters, actions, and extension hook reference
└── README.md                          # This navigation and orientation page
```

## Component Coverage
Refer to the individual files listed above for implementation details, operational guidance, and API references.
