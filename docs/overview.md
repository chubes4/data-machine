# Data Machine User Documentation

**AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.**

## System Architecture

Data Machine uses a Pipeline+Flow architecture where:

- **Pipelines** are reusable workflow templates containing step configurations
- **Flows** are scheduled instances of pipelines with specific settings  
- **Jobs** are individual executions of flows
- **Steps** process data sequentially: Fetch → AI → Publish/Update

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

  **Global Directives** (apply to ALL AI agents - pipeline + chat):
  - `datamachine_global_directives` filter for universal directive registration
  - **Priority 20**: Global System Prompt - User-configured foundational AI behavior
  - **Priority 50**: WordPress Site Context - WordPress environment info (toggleable)

  **Pipeline-Specific Directives** (apply ONLY to pipeline AI steps):
  - `datamachine_pipeline_directives` filter for pipeline-only directive registration
  - **Priority 10**: Pipeline Core Directive - Foundational pipeline agent identity
  - **Priority 30**: Pipeline System Prompt - Workflow structure visualization
  - **Priority 40**: Pipeline Context Directive - Workflow context and execution state
  - **Priority 40**: Tool Definitions Directive - Usage instructions and workflow context

  **Chat-Specific Directives** (apply ONLY to chat AI agents):
  - `datamachine_chat_directives` filter for chat-only directive registration
  - **Priority 15**: Chat Agent Directive - Chat agent identity, REST API documentation, conversation guidelines

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
  - MakeAPIRequest - Execute Data Machine REST API operations
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

## System Features

### Cache Management
- **Centralized Cache System** - Actions/Cache.php provides WordPress action-based cache clearing
- **Granular Invalidation** - Separate actions for pipeline, flow, and job cache clearing (datamachine_clear_pipeline_cache, datamachine_clear_flow_cache, datamachine_clear_jobs_cache, datamachine_clear_all_cache)
- **Pattern-Based Clearing** - Supports wildcard patterns for efficient bulk operations (datamachine_pipeline_*, datamachine_flow_*, datamachine_job_*)
- **WordPress Transients** - Native WordPress caching integration with comprehensive logging
- **Standardized Storage** - datamachine_cache_set action for consistent cache management

### AutoSave System
- **Complete Pipeline Persistence** - AutoSave system handles all pipeline-related data via single `datamachine_auto_save` action
- **Flow Synchronization** - Synchronizes execution_order between pipeline and flow steps automatically
- **Comprehensive Data Management** - Saves pipeline data, all flows, flow configurations, scheduling, and handler settings
- **Cache Integration** - Automatic cache clearing after successful auto-save for data consistency

## Requirements

- **PHP** 8.0 or higher
- **WordPress** 6.2 or higher
- **Composer Dependencies** - Automatically managed including ai-http-client
- **Admin Capabilities** - `manage_options` required for all operations