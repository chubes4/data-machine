# Data Machine User Documentation

**AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.**

## System Architecture

Data Machine uses a Pipeline+Flow architecture with standardized base classes for code reuse:

- **Pipelines** are reusable workflow templates containing step configurations
- **Flows** are scheduled instances of pipelines with specific settings
- **Jobs** are individual executions of flows
- **Steps** process data sequentially: Fetch → AI → Publish/Update

**Base Class Architecture** (@since v0.2.1) - Major Refactoring:

Data Machine underwent a comprehensive OOP refactoring introducing standardized inheritance patterns that dramatically reduce code duplication:

**Handler Registration Traits** - Code Reduction:

Standardized traits that reduce repetitive registration boilerplate across handler and tool registrations:

- **HandlerRegistrationTrait**: Single `registerHandler()` method for all handler types (fetch, publish, update)
- **ToolRegistrationTrait**: Agent-agnostic tool registration with dynamic filter creation for unlimited agent types

**Services Layer Architecture** (@since v0.4.0) - Performance Revolution:

Complete replacement of filter-based action system with OOP service managers for 3x performance improvement through direct method calls:

**Service Managers**:
- **FlowManager** - Flow CRUD operations, duplication, step synchronization
- **PipelineManager** - Pipeline CRUD operations with complete/simple creation modes  
- **JobManager** - Job execution monitoring and management
- **LogsManager** - Centralized log access and filtering
- **ProcessedItemsManager** - Deduplication tracking across workflows
- **FlowStepManager** - Individual flow step configuration and handler management
- **PipelineStepManager** - Pipeline step template management

**Modern React Architecture** (@since v0.2.3, enhanced v0.2.6) - Performance Optimization:

Complete modernization of the admin interface with TanStack Query + Zustand for optimal performance, enhanced with advanced state management patterns in v0.2.6:

**React Enhancements** (@since v0.2.6):
- **HandlerModel** - Abstract model layer for handler data operations
- **HandlerFactory** - Factory pattern for handler model instantiation
- **useHandlerModel** - Custom hook for handler model integration
- **ModalSwitch** - Centralized modal routing component
- **HandlerProvider** - React context for handler state management

**Step Hierarchy**:
- **Step** (`/inc/Core/Steps/Step.php`) - Abstract base for all step types
  - Unified payload handling across Fetch, AI, Publish, Update steps
  - Automatic validation and logging
  - Exception handling and error management

**Handler Base Classes**:
- **FetchHandler** (`/inc/Core/Steps/Fetch/Handlers/FetchHandler.php`)
  - Deduplication tracking (`isItemProcessed`, `markItemProcessed`)
  - Engine data storage for downstream handlers
  - Common filtering logic (timeframe, keywords)
  - Standardized response methods

- **PublishHandler** (`/inc/Core/Steps/Publish/Handlers/PublishHandler.php`)
  - Engine data retrieval (`getSourceUrl`, `getImageFilePath`)
  - Image validation and metadata extraction
  - Standardized response formatting

**Settings Architecture**:
- **SettingsHandler** (`/inc/Core/Steps/Settings/SettingsHandler.php`) - Base for all settings
  - Auto-sanitization based on field schema
  - Validation and error handling
- **SettingsDisplayService** (`/inc/Core/Steps/Settings/SettingsDisplayService.php`) - Settings display logic
  - Processes settings for UI presentation
  - Smart label generation and value formatting

- **FetchHandlerSettings** - Common fetch fields (timeframe_limit, search)
- **PublishHandlerSettings** - Common publish fields (status, author)

**Data Standardization**:
- **DataPacket** (`/inc/Core/DataPacket.php`) - Replaces scattered array construction
  - Consistent packet structure across entire system
  - Chronological ordering (newest first)
  - Type and timestamp enforcement

For implementation details, see the core-system documentation directory.

## Core Concepts

### Pipeline Steps

1. **Fetch Steps** - Retrieve content from various sources (Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API)
2. **AI Steps** - Process content using AI providers (OpenAI, Anthropic, Google, Grok, OpenRouter) with available tools (Google Search, Local Search, WebFetch, WordPress Post Reader, handler-specific tools)
3. **Publish Steps** - Distribute content to platforms (Twitter, Facebook, Bluesky, Threads, WordPress with modular handler architecture, Google Sheets)
4. **Update Steps** - Modify existing content (WordPress posts/pages)

### Data Flow

**Explicit Data Separation Architecture**: Fetch handlers generate clean data packets for AI processing while providing engine parameters separately for publish/update handlers:

```php
// Clean data packet (AI-visible)
[
    'data' => [
        'content_string' => $content,  // Clean content without URLs
        'file_info' => $file_info      // File metadata when applicable
    ],
    'metadata' => [
        'source_type' => $type,
        'item_identifier_to_log' => $id,
        'original_id' => $id,
        'original_title' => $title,
        'original_date_gmt' => $date
        // Clean data packet for AI processing
    ]
]

// Engine parameters stored in database by fetch handlers via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}

// Engine parameters retrieved by handlers via centralized filter
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

**Data Packet Structure**: AI agents work with clean data packet structure including:
- Root wrapper with data_packets array
- Chronological ordering (index 0 = newest)
- Type-specific fields and workflow dynamics
- Turn-based data updates for multi-turn conversations

### AI Integration

- **Multi-Provider Support** - OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)
- **Tool-First Architecture** - AI agents can call tools to interact with publish handlers
- **Filter-Based Directive System** - Structured system messages via auto-registering directive classes with separate global and pipeline directives:

  **Unified Directive System** (since v0.2.5):
  - `datamachine_directives` filter for centralized directive registration with priority-based ordering
  - **Priority 10**: Core agent identity and foundational instructions
  - **Priority 20**: Global system prompts and universal behavior
  - **Priority 30**: Agent-specific system prompts and context
  - **Priority 40**: Workflow and execution context directives
  - **Priority 50**: Environmental and site-specific directives
  - **Priority 30**: Pipeline System Prompt - Workflow structure visualization (pipeline agents only)
  - **Priority 40**: Pipeline Context Directive - Workflow context and execution state (pipeline agents only)
  - **Priority 40**: Tool Definitions Directive - Usage instructions and workflow context (pipeline agents only)

- **Universal Engine Architecture** - Shared AI infrastructure serving Pipeline and Chat agents:
  - **AIConversationLoop** - Multi-turn conversation execution with automatic tool calling
  - **ToolExecutor** - Universal tool discovery and execution infrastructure
  - **ToolParameters** - Centralized parameter building for AI tools
    - `buildParameters()` for standard AI tools
    - `buildForHandlerTool()` for handler tools with engine parameters
    - Content/title extraction from data packets
  - **ConversationManager** - Message formatting and conversation utilities
  - **RequestBuilder** - Centralized AI request construction with directive application
- **Global AI Tools** - Available to all AI agents (pipeline + chat), located in `/inc/Engine/AI/Tools/`:
  - Google Search - Web search with site restriction
  - Local Search - WordPress content search
  - WebFetch - Web page content retrieval (50K limit)
  - WordPress Post Reader - Single post analysis
  - Registered via `datamachine_global_tools` filter
- **Chat-Specific Tools** - Available only to chat AI agents:
  - ExecuteWorkflow (@since v0.3.0) - Execute complete multi-step workflows with modular architecture (DefaultsInjector, DocumentationBuilder, WorkflowValidator, ExecuteWorkflowTool)
  - MakeAPIRequest - Execute Data Machine REST API operations for pipeline/flow management
  - Registered via `datamachine_chat_tools` filter
- **Handler-Specific Tools** - Available when next step matches handler type, registered via `chubes_ai_tools` filter
- **Context-Aware** - Automatic WordPress site context injection (toggleable)
- **Clear Tool Result Messaging** - Human-readable success messages enabling natural conversation termination

### Authentication

- **OAuth 2.0** - Reddit, Google Sheets, Facebook, Threads
- **OAuth 1.0a** - Twitter
- **App Passwords** - Bluesky
- **API Keys** - Google Search, AI providers

## Quick Start

1. **Install Requirements** - PHP 8.0+, WordPress 6.2+
2. **Configure AI Provider** - Add API keys in WordPress Settings → Data Machine
3. **Create Pipeline** - Use visual builder with drag-and-drop steps
4. **Configure Authentication** - Set up OAuth for external services
5. **Create Flow** - Schedule pipeline with specific settings
6. **Run Manual Execution** - Test workflow before automation

## Key Features

- **Visual Pipeline Builder** - Drag-and-drop interface with auto-save
- **Multi-Platform Publishing** - Single pipeline can publish to multiple platforms
- **Content Deduplication** - Automatic tracking prevents duplicate processing
- **Error Handling** - Comprehensive logging and failure recovery
- **Extension System** - Custom handlers and tools via WordPress filters
- **Action Scheduler Integration** - Asynchronous processing with WordPress cron

## Directory Structure

```
docs/
├── core-system/          # Engine, database, execution
├── handlers/
│   ├── fetch/           # Data retrieval handlers
│   ├── publish/         # Publishing platforms
│   └── update/          # Content modification
├── ai-tools/            # General and handler-specific tools
├── admin-interface/     # WordPress admin pages
└── api-reference/       # Filters, actions, functions
```

## Requirements

- **PHP** 8.0 or higher
- **WordPress** 6.2 or higher
- **Composer Dependencies** - Automatically managed including ai-http-client
- **Admin Capabilities** - `manage_options` required for all operations