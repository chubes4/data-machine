# Data Machine Architecture

Data Machine is an AI-first WordPress plugin that uses a Pipeline+Flow architecture for automated content processing and publication. It provides multi-provider AI integration with tool-first design patterns.

## Core Components

### Pipeline+Flow System
- **Pipelines**: Reusable templates containing step configurations
- **Flows**: Configured instances of pipelines with scheduling
- **Jobs**: Individual executions of flows with status tracking

### Execution Engine
Three-action execution cycle:
1. `datamachine_run_flow_now` - Initiates flow execution
2. `datamachine_execute_step` - Processes individual steps
3. `datamachine_schedule_next_step` - Continues to next step or completes

### Database Schema
- `wp_datamachine_pipelines` - Pipeline templates (reusable)
- `wp_datamachine_flows` - Flow instances (scheduled + configured)
- `wp_datamachine_jobs` - Job executions with status tracking and engine_data storage (source_url, image_url)
- `wp_datamachine_processed_items` - Deduplication tracking per execution

### Engine Data Architecture

**Clean Data Separation**: AI agents receive clean data packets without URLs while handlers access engine parameters via centralized filter pattern.

**Enhanced Database Storage + Filter Access**: Fetch handlers store engine parameters (source_url, image_url) in database; steps retrieve via centralized `datamachine_engine_data` filter with storage/retrieval mode detection for unified access.

**Core Pattern**:
```php
// Fetch handlers store via centralized filter (array storage)
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}

// Steps retrieve via centralized filter (EngineData.php)
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

**Benefits**:
- **Clean AI Data**: AI processes content without URLs for better model performance
- **Centralized Access**: Single filter interface for all engine data retrieval
- **Filter Consistency**: Maintains architectural pattern of filter-based service discovery
- **Flexible Storage**: Steps access only what they need via filter call

### Advanced Cache Management System
**Enhanced Centralized Architecture**: Actions/Cache.php provides comprehensive WordPress action-based cache clearing system with database component integration and extensible architecture.

**Granular Cache Operations**:
- `datamachine_clear_pipeline_cache($pipeline_id)` - Clear pipeline + flows + jobs with comprehensive invalidation
- `datamachine_clear_flow_cache($flow_id)` - Clear flow-specific caches with step integration
- `datamachine_clear_flow_config_cache($flow_id)` - Clear flow configuration cache for targeted updates
- `datamachine_clear_flow_scheduling_cache($flow_id)` - Clear flow scheduling cache for targeted updates
- `datamachine_clear_flow_steps_cache($flow_id)` - Clear flow steps cache for targeted updates
- `datamachine_clear_jobs_cache()` - Clear all job-related caches with pattern matching
- `datamachine_clear_all_cache()` - Complete cache reset with database component integration
- `datamachine_cache_set($key, $data, $timeout, $group)` - Standardized cache storage with validation

**Advanced Features**:
- Enhanced pattern-based clearing with wildcard support and extensible action-based architecture
- WordPress transient integration with performance optimization
- Comprehensive logging for all cache operations and AI HTTP Client integration
- Granular cache invalidation with targeted methods for optimal performance
- Action-based database component integration for extensible cache management

### Step Types
- **Fetch**: Data retrieval with clean content processing (Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API)
- **AI**: Content processing with multi-provider support (OpenAI, Anthropic, Google, Grok)
- **Publish**: Content distribution with modular handler architecture (Twitter, Facebook, Threads, Bluesky, WordPress with specialized components)
- **Update**: Content modification (WordPress posts/pages)

### Authentication System

**Base Authentication Provider Architecture** (@since v0.2.6): Complete inheritance system with centralized option storage and validation across all authentication providers.

**Base Classes**:
- **BaseAuthProvider** (`/inc/Core/OAuth/BaseAuthProvider.php`): Abstract base for all authentication providers with unified option storage, callback URL generation, and authentication state checking
- **BaseOAuth1Provider** (`/inc/Core/OAuth/BaseOAuth1Provider.php`): OAuth 1.0a providers (TwitterAuth) extending BaseAuthProvider
- **BaseOAuth2Provider** (`/inc/Core/OAuth/BaseOAuth2Provider.php`): OAuth 2.0 providers (RedditAuth, FacebookAuth, ThreadsAuth, GoogleSheetsAuth) extending BaseAuthProvider

**OAuth Handlers**:
- **OAuth1Handler** (`/inc/Core/OAuth/OAuth1Handler.php`): Three-legged OAuth 1.0a flow implementation
- **OAuth2Handler** (`/inc/Core/OAuth/OAuth2Handler.php`): Authorization code flow implementation

**Authentication Providers**:
- **OAuth 1.0a**: TwitterAuth extends BaseOAuth1Provider
- **OAuth 2.0**: RedditAuth, FacebookAuth, ThreadsAuth, GoogleSheetsAuth extend BaseOAuth2Provider
- **Direct**: BlueskyAuth extends BaseAuthProvider (app password authentication)

**OAuth2 Flow**:
1. Create state nonce for CSRF protection
2. Build authorization URL with parameters
3. Handle callback: verify state, exchange code for token, retrieve account details, store credentials

**OAuth1 Flow**:
1. Get request token
2. Build authorization URL
3. Handle callback: validate parameters, exchange for access token, store credentials

**Benefits**:
- Eliminates duplicated storage logic across all providers (~60% code reduction per provider)
- Standardized error handling and logging
- Unified security implementation
- Easy integration of new providers via base class extension

### Universal Engine Architecture

Data Machine v0.2.0 introduced a universal Engine layer (`/inc/Engine/AI/`) that serves both Pipeline and Chat agents with shared AI infrastructure:

**Core Engine Components**:

- **AIConversationLoop** (`/inc/Engine/AI/AIConversationLoop.php`): Multi-turn conversation execution with tool calling support, automatic conversation completion detection, turn-based state management with chronological ordering, and duplicate message prevention

- **ToolExecutor** (`/inc/Engine/AI/ToolExecutor.php`): Universal tool discovery via `getAvailableTools()` method, filter-based tool enablement per agent type, handler tool and global tool integration, and tool configuration validation

- **ToolParameters** (`/inc/Engine/AI/ToolParameters.php`): Centralized parameter building for all AI tools, content/title extraction from data packets, tool metadata integration (tool_definition, tool_name, handler_config), and engine parameter merging for handlers (source_url, image_url)

- **ConversationManager** (`/inc/Engine/AI/ConversationManager.php`): Message formatting utilities for AI requests, tool call recording and tracking, conversation message normalization, and chronological message ordering

- **RequestBuilder** (`/inc/Engine/AI/RequestBuilder.php`): Centralized AI request construction for all agents, directive application system (global, agent-specific, pipeline, chat), tool restructuring for AI provider compatibility, and integration with ai-http-client library

- **ToolResultFinder** (`/inc/Engine/AI/ToolResultFinder.php`): Universal utility for finding AI tool execution results in data packets, handler-specific result search by slug matching, centralized search logic eliminating code duplication across update handlers

**Tool Categories**:
- Handler-specific tools for publish/update operations (twitter_publish, wordpress_update)
- Global tools in `/inc/Engine/AI/Tools/` for search and analysis (GoogleSearch, LocalSearch, WebFetch, WordPressPostReader)
- Chat-only tools for workflow building:
  - ExecuteWorkflow (@since v0.3.0) - Direct workflow execution with modular architecture at `/inc/Api/Chat/Tools/ExecuteWorkflow/`
  - MakeAPIRequest - REST API operations for pipeline/flow management
- Automatic tool discovery and configuration via filter-based system
- Three-layer tool enablement: Global settings → Modal selection → Runtime validation

### Filter-Based Discovery
All components self-register via WordPress filters:
- `datamachine_handlers` - Register fetch/publish/update handlers
- `chubes_ai_tools` - Register AI tools and capabilities
- `datamachine_auth_providers` - Register authentication providers
- `datamachine_step_types` - Register custom step types
- `datamachine_get_oauth1_handler` - OAuth 1.0a handler service discovery
- `datamachine_get_oauth2_handler` - OAuth 2.0 handler service discovery

### Modular Component Architecture (@since v0.2.1)

Data Machine v0.2.1 introduced modular component systems for enhanced code organization and maintainability:

**FilesRepository Components** (`/inc/Core/FilesRepository/`):
- **DirectoryManager** - Directory creation and path management
- **FileStorage** - File operations and flow-isolated storage
- **FileCleanup** - Retention policy enforcement and cleanup
- **ImageValidator** - Image validation and metadata extraction
- **RemoteFileDownloader** - Remote file downloading with validation
- **FileRetrieval** - Data retrieval from file storage

**WordPress Shared Components** (`/inc/Core/WordPress/`):
- **TaxonomyHandler** - Taxonomy selection and term creation (skip, AI-decided, pre-selected modes)
- **WordPressSettingsHandler** - Shared WordPress settings fields
- **WordPressFilters** - Service discovery registration

**EngineData** (`/inc/Core/EngineData.php`):
- **Consolidated Operations** - Featured image attachment, source URL attribution, and engine data access (@since v0.2.1, enhanced v0.2.6)
- **Unified Interface** - Single class for all engine data operations (replaces FeaturedImageHandler and SourceUrlHandler in v0.2.6)

**Engine Components** (`/inc/Engine/`):
- **StepNavigator** - Centralized step navigation logic for execution flow

**Benefits**:
- **Code Deduplication**: Eliminates repetitive functionality across handlers
- **Single Responsibility**: Each component has focused purpose
- **Maintainability**: Centralized logic simplifies updates
- **Extensibility**: Easy to add new functionality via composition

For detailed documentation:
- FilesRepository Components
- WordPress Shared Components
- EngineData
- StepNavigator

### Centralized Handler Filter System

**Unified Cross-Cutting Functionality**: The engine provides centralized filters for shared functionality across multiple handlers, eliminating code duplication and ensuring consistency.

**Core Centralized Filters**:
- **`datamachine_timeframe_limit`**: Shared timeframe parsing with discovery/conversion modes
  - Discovery mode: Returns available timeframe options for UI dropdowns
  - Conversion mode: Returns Unix timestamp for specified timeframe
  - Used by: RSS, Reddit, WordPress Local, WordPress Media, WordPress API
- **`datamachine_keyword_search_match`**: Universal keyword matching with OR logic
  - Case-insensitive Unicode-safe matching
  - Comma-separated keyword support
  - Used by: RSS, Reddit, WordPress Local, WordPress Media, WordPress API
- **`datamachine_data_packet`**: Standardized data packet creation and structure
  - Ensures type and timestamp fields are present
  - Maintains chronological ordering via array_unshift()
  - Used by: All step types for consistent data flow

**Implementation**:
```php
// Timeframe parsing example
$cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, '24_hours');
$date_query = $cutoff_timestamp ? ['after' => gmdate('Y-m-d H:i:s', $cutoff_timestamp)] : [];

// Keyword matching example
$matches = apply_filters('datamachine_keyword_search_match', true, $content, $search_keywords);
if (!$matches) continue; // Skip non-matching items

// Data packet creation example
$data = apply_filters('datamachine_data_packet', $data, $packet_data, $flow_step_id, $step_type);
```

**Benefits**:
- **Code Consistency**: Identical behavior across all handlers using shared filters
- **Maintainability**: Single implementation location for shared functionality
- **Extensibility**: New handlers automatically inherit shared capabilities
- **Performance**: Optimized implementations used across all handlers

### WordPress Publish Handler Architecture
**Modular Component System**: The WordPress publish handler uses specialized processing modules for enhanced maintainability and extensibility.

**Core Components**:
- **EngineData**: Consolidated featured image attachment and source URL attribution with configuration hierarchy (system defaults override handler config) (@since v0.2.1, enhanced v0.2.6)
- **TaxonomyHandler**: Configuration-based taxonomy processing with three selection modes (skip, AI-decided, pre-selected)
- **Direct Integration**: WordPress handlers use EngineData and TaxonomyHandler directly for single source of truth data access

**Configuration Hierarchy**: System-wide defaults ALWAYS override handler-specific configuration when set, providing consistent behavior across all WordPress publish operations.

**Features**:
- Specialized component isolation for maintainability
- Configuration validation and error handling per component
- WordPress native function integration for optimal performance
- Comprehensive logging throughout all components
- Unified engine data operations via EngineData class

### File Management
Flow-isolated UUID storage with automatic cleanup:
- Files organized by flow instance
- Automatic purging on job completion
- Support for local and remote file processing

### Cache Management
Centralized WordPress transient-based cache system:
- Automatic cache invalidation on pipeline changes
- Pattern-based cache clearing for efficient management
- Pipeline-specific and global cache operations
- Integration with auto-save functionality

### Admin Interface

**Modern React Architecture**: The Pipelines admin page uses complete React implementation with zero AJAX dependencies, providing a modern, maintainable frontend.

**React Implementation**:
- A substantial React-based admin UI built with WordPress components
- Multiple specialized components organized by responsibility
- Modern state management using TanStack Query + Zustand
- Complete REST API integration for all data operations
- Real-time updates without page reloads
- Optimistic UI updates for instant feedback

**Component Architecture**:
- **Core**: PipelinesApp (root), Zustand stores for state management
- **Cards**: PipelineCard, FlowCard, PipelineStepCard, FlowStepCard
- **Modals**: ConfigureStepModal, HandlerSettingsModal, OAuthAuthenticationModal, StepSelectionModal, HandlerSelectionModal, FlowScheduleModal, ImportExportModal
- **Hooks**: usePipelines, useFlows, useStepTypes, useHandlers, useStepSettings, useModal



**Complete REST API Integration**:
All admin pages now use REST API architecture with zero AJAX dependencies.

**Security Model**: All admin operations require `manage_options` capability with WordPress nonce validation.

### Extension Framework
Complete extension system for custom handlers and tools:
- Filter-based registration
- Template-driven development
- Automatic discovery and validation
- LLM-assisted development support

## Key Features

### AI Integration
- Support for multiple AI providers (OpenAI, Anthropic, Google, and others)
- **Unified Directive System**: Priority-based directive management via PromptBuilder:
  - `datamachine_directives` - Centralized filter with priority ordering and agent targeting
- **Universal Engine Architecture**: Shared AI infrastructure via `/inc/Engine/AI/` components:
  - AIConversationLoop for multi-turn conversation execution with automatic tool calling
  - ToolExecutor for universal tool discovery and execution
  - ToolParameters for centralized parameter building (`buildParameters()` for standard tools, `buildForHandlerTool()` for handler tools with engine data)
  - ConversationManager for message formatting and conversation utilities
  - RequestBuilder for centralized AI request construction with directive application
  - ToolResultFinder for universal tool result search in data packets
- Site context injection with automatic cache invalidation (SiteContextDirective in global directives)
- Tool result formatting with success/failure messages
- Clear tool result messaging enabling natural AI agent conversation termination

### Data Processing
- **Explicit Data Separation Architecture**: Clean data packets for AI processing vs engine parameters for handlers
- **Engine Data Filter Architecture**: Fetch handlers store engine_data (source_url, image_url) in database; steps retrieve via centralized `datamachine_engine_data` filter
- DataPacket structure for consistent data flow with chronological ordering
- Clear data packet structure for AI agents with chronological ordering:
  - Root wrapper with data_packets array
  - Index 0 = newest packet (chronological ordering)
  - Type-specific fields (handler, attachments, tool_name)
  - Workflow dynamics and turn-based updates
- Deduplication tracking
- Comprehensive logging

### Scheduling
- WordPress Action Scheduler integration
- Configurable intervals
- Manual execution support
- Job failure handling

### Security
- Admin-only access (`manage_options` capability)
- CSRF protection via WordPress nonces
- Input sanitization and validation
- Secure OAuth implementation