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
- `wp_dm_pipelines` - Pipeline templates (reusable)
- `wp_dm_flows` - Flow instances (scheduled + configured)
- `wp_dm_jobs` - Job executions with status tracking and engine_data storage (source_url, image_url)
- `wp_dm_processed_items` - Deduplication tracking per execution

### Engine Data Architecture

**Clean Data Separation**: AI agents receive clean data packets without URLs while handlers access engine parameters via centralized filter pattern.

**Enhanced Database Storage + Filter Access**: Fetch handlers store engine parameters (source_url, image_url) in database; steps retrieve via centralized `datamachine_engine_data` filter with storage/retrieval mode detection for unified access.

**Core Pattern**:
```php
// Fetch handlers store via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, $source_url, $image_url);
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
- `dm_clear_pipeline_cache($pipeline_id)` - Clear pipeline + flows + jobs with comprehensive invalidation
- `dm_clear_flow_cache($flow_id)` - Clear flow-specific caches with step integration
- `dm_clear_flow_config_cache($flow_id)` - Clear flow configuration cache for targeted updates
- `dm_clear_flow_scheduling_cache($flow_id)` - Clear flow scheduling cache for targeted updates
- `dm_clear_flow_steps_cache($flow_id)` - Clear flow steps cache for targeted updates
- `dm_clear_jobs_cache()` - Clear all job-related caches with pattern matching
- `dm_clear_all_cache()` - Complete cache reset with database component integration
- `dm_cache_set($key, $data, $timeout, $group)` - Standardized cache storage with validation

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
Unified OAuth2 and API key management:
- OAuth providers: Twitter, Reddit, Facebook, Google services
- API key providers: Google Search, AI services
- Centralized configuration validation

### Tool-First AI Architecture
AI agents use tools to interact with handlers:
- Handler-specific tools for publish/update operations (twitter_publish, wordpress_update)
- General tools for search and analysis (Google Search, Local Search, WebFetch)
- Automatic tool discovery and configuration
- AIStepToolParameters class provides unified flat parameter building:
  - Content/title extraction from data packets
  - Tool metadata integration (tool_definition, tool_name, handler_config)
  - Engine parameter merging for handlers (source_url for link attribution and post identification)
- Three-layer tool enablement: Global settings → Modal selection → Runtime validation
- Enhanced AIStepConversationManager for conversation state management with turn tracking, temporal context, duplicate detection, and conversation validation

### Filter-Based Discovery
All components self-register via WordPress filters:
- `datamachine_handlers` - Register fetch/publish/update handlers
- `ai_tools` - Register AI tools and capabilities
- `dm_auth_providers` - Register authentication providers
- `datamachine_step_types` - Register custom step types

### Centralized Handler Filter System

**Unified Cross-Cutting Functionality**: The engine provides centralized filters for shared functionality across multiple handlers, eliminating code duplication and ensuring consistency.

**Core Centralized Filters**:
- **`dm_timeframe_limit`**: Shared timeframe parsing with discovery/conversion modes
  - Discovery mode: Returns available timeframe options for UI dropdowns
  - Conversion mode: Returns Unix timestamp for specified timeframe
  - Used by: RSS, Reddit, WordPress Local, WordPress Media, WordPress API
- **`dm_keyword_search_match`**: Universal keyword matching with OR logic
  - Case-insensitive Unicode-safe matching
  - Comma-separated keyword support
  - Used by: RSS, Reddit, WordPress Local, WordPress Media, WordPress API
- **`dm_data_packet`**: Standardized data packet creation and structure
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
**Modular Component System**: The WordPress publish handler is refactored into specialized processing modules for enhanced maintainability and extensibility.

**Core Components**:
- **FeaturedImageHandler**: Centralized featured image processing with configuration hierarchy (system defaults override handler config)
- **TaxonomyHandler**: Configuration-based taxonomy processing with three selection modes (skip, AI-decided, pre-selected)
- **SourceUrlHandler**: Source URL attribution with Gutenberg block generation and configuration hierarchy

**Configuration Hierarchy**: System-wide defaults ALWAYS override handler-specific configuration when set, providing consistent behavior across all WordPress publish operations.

**Features**:
- Specialized component isolation for maintainability
- Configuration validation and error handling per component
- WordPress native function integration for optimal performance
- Comprehensive logging throughout all components

### File Management
Flow-isolated UUID storage with automatic cleanup:
- Files organized by flow instance
- Automatic purging on job completion
- Support for local and remote file processing

### AutoSave System
**Complete Pipeline Persistence**: Centralized auto-save operations handle all pipeline-related data in a single action.

**Features**:
- **Single Action Interface**: `dm_auto_save` action handles everything
- **Complete Data Management**: Saves pipeline data, all flows, flow configurations, and scheduling
- **Execution Order Synchronization**: Updates flow step execution_order to match pipeline steps
- **Cache Integration**: Automatic cache clearing after successful auto-save operations
- **Database Service Integration**: Uses filter-based database service discovery
- **Error Handling**: Validates services and data before processing

### Cache Management
Centralized WordPress transient-based cache system:
- Automatic cache invalidation on pipeline changes
- Pattern-based cache clearing for efficient management
- Pipeline-specific and global cache operations
- Integration with auto-save functionality

### Admin Interface
WordPress admin integration with `manage_options` security:
- Drag & drop pipeline builder
- Legacy status indicators removed pending replacement health checks
- Modal-based configuration
- Auto-save functionality
- Import/export capabilities

**Status System**: Retired. Future iterations will reintroduce health indicators once the new telemetry pipeline lands.

### Extension Framework
Complete extension system for custom handlers and tools:
- Filter-based registration
- Template-driven development
- Automatic discovery and validation
- LLM-assisted development support

## Key Features

### AI Integration
- Multiple provider support (200+ models via OpenRouter)
- Enhanced 5-tier AI directive priority system with standardized spacing and auto-registration:
  - **Priority 10**: PluginCoreDirective (foundational AI agent identity with workflow termination logic and data packet structure guidance)
  - **Priority 20**: GlobalSystemPromptDirective (user-configured foundational AI behavior)
  - **Priority 30**: PipelineSystemPromptDirective (pipeline instructions and workflow visualization)
  - **Priority 40**: ToolDefinitionsDirective (dynamic tool prompts and workflow context)
  - **Priority 50**: SiteContextDirective (WordPress environment info, toggleable)
- Advanced AIStepConversationManager for centralized conversation state management:
  - Turn-based conversation loops with chronological message ordering and temporal context
  - AI tool calls recorded before execution with turn number tracking and duplicate detection
  - Enhanced tool result messaging with temporal context and conversation validation
  - Conversation completion with natural AI agent termination and success/failure tracking
  - Data packet synchronization via `updateDataPacketMessages()` with JSON synchronization
  - Duplicate tool call detection with parameter comparison and corrective messaging
- Enhanced AIStepToolParameters class for unified tool execution:
  - `buildParameters()` for standard AI tools with centralized parameter management
  - `buildForHandlerTool()` for handler tools with engine parameters and unified execution patterns
  - Flat parameter structure with content/title extraction and structured processing
- Clear tool result messaging enabling natural AI agent conversation termination with enhanced validation
- Site context injection with automatic cache invalidation
- Tool result formatting with success/failure messages

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