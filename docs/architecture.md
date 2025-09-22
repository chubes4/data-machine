# Data Machine Architecture

Data Machine is an AI-first WordPress plugin that uses a Pipeline+Flow architecture for automated content processing and publication. It provides multi-provider AI integration with tool-first design patterns.

## Core Components

### Pipeline+Flow System
- **Pipelines**: Reusable templates containing step configurations
- **Flows**: Configured instances of pipelines with scheduling
- **Jobs**: Individual executions of flows with status tracking

### Execution Engine
Three-action execution cycle:
1. `dm_run_flow_now` - Initiates flow execution
2. `dm_execute_step` - Processes individual steps
3. `dm_schedule_next_step` - Continues to next step or completes

### Database Schema
- `wp_dm_pipelines` - Pipeline templates (reusable)
- `wp_dm_flows` - Flow instances (scheduled + configured)
- `wp_dm_jobs` - Job executions with status tracking and engine_data storage (source_url, image_url)
- `wp_dm_processed_items` - Deduplication tracking per execution

### Engine Data Architecture

**Clean Data Separation**: AI agents receive clean data packets without URLs while handlers access engine parameters via centralized filter pattern.

**Database Storage + Filter Access**: Fetch handlers store engine parameters (source_url, image_url) in database; steps retrieve via centralized `dm_engine_data` filter for unified access.

**Core Pattern**:
```php
// Fetch handlers store via centralized filter
if ($job_id) {
    apply_filters('dm_engine_data', null, $job_id, $source_url, $image_url);
}

// Steps retrieve via centralized filter (EngineData.php)
$engine_data = apply_filters('dm_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

**Benefits**:
- **Clean AI Data**: AI processes content without URLs for better model performance
- **Centralized Access**: Single filter interface for all engine data retrieval
- **Filter Consistency**: Maintains architectural pattern of filter-based service discovery
- **Flexible Storage**: Steps access only what they need via filter call

### Cache Management System
**Centralized Architecture**: Actions/Cache.php provides WordPress action-based cache clearing system for comprehensive cache management.

**Cache Operations**:
- `dm_clear_pipeline_cache($pipeline_id)` - Clear pipeline + flows + jobs
- `dm_clear_flow_cache($flow_id)` - Clear flow-specific caches
- `dm_clear_jobs_cache()` - Clear all job-related caches
- `dm_clear_all_cache()` - Complete cache reset
- `dm_cache_set($key, $data, $timeout, $group)` - Standardized cache storage

**Key Features**:
- Pattern-based clearing with wildcard support (dm_pipeline_*, dm_flow_*, dm_job_*)
- WordPress transient integration for native storage
- Comprehensive logging for all cache operations
- Granular cache invalidation for optimal performance

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
- AIStepConversationManager for conversation state and tool result formatting with turn tracking

### Filter-Based Discovery
All components self-register via WordPress filters:
- `dm_handlers` - Register fetch/publish/update handlers
- `ai_tools` - Register AI tools and capabilities
- `dm_auth_providers` - Register authentication providers
- `dm_steps` - Register custom step types

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
$cutoff_timestamp = apply_filters('dm_timeframe_limit', null, '24_hours');
$date_query = $cutoff_timestamp ? ['after' => gmdate('Y-m-d H:i:s', $cutoff_timestamp)] : [];

// Keyword matching example
$matches = apply_filters('dm_keyword_search_match', true, $content, $search_keywords);
if (!$matches) continue; // Skip non-matching items

// Data packet creation example
$data = apply_filters('dm_data_packet', $data, $packet_data, $flow_step_id, $step_type);
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
- Real-time status indicators  
- Modal-based configuration
- Auto-save functionality
- Import/export capabilities

### Extension Framework
Complete extension system for custom handlers and tools:
- Filter-based registration
- Template-driven development
- Automatic discovery and validation
- LLM-assisted development support

## Key Features

### AI Integration
- Multiple provider support (200+ models via OpenRouter)
- 5-tier AI directive priority system with standardized spacing for extensibility:
  - **Priority 10**: PluginCoreDirective (foundational AI agent identity)
  - **Priority 20**: GlobalSystemPromptDirective (foundational AI behavior)
  - **Priority 30**: PipelineSystemPromptDirective (workflow structure visualization)
  - **Priority 40**: ToolDefinitionsDirective (tool definitions + workflow context)
  - **Priority 50**: SiteContextDirective (WordPress environment info)
- AIStepConversationManager for centralized conversation state management:
  - Turn-based conversation loops with chronological message ordering
  - AI tool calls recorded before execution with turn number tracking
  - Enhanced tool result messaging with temporal context ("Turn X")
  - Conversation completion with natural AI agent termination
  - Data packet synchronization via `updateDataPacketMessages()`
- AIStepToolParameters class for unified tool execution:
  - `buildParameters()` for standard AI tools
  - `buildForHandlerTool()` for handler tools with engine parameters
  - Flat parameter structure with content/title extraction
- Clear tool result messaging enabling natural AI agent conversation termination
- Site context injection with automatic cache invalidation
- Tool result formatting with success/failure messages

### Data Processing
- **Explicit Data Separation Architecture**: Clean data packets for AI processing vs engine parameters for handlers
- **Engine Data Filter Architecture**: Fetch handlers store engine_data (source_url, image_url) in database; steps retrieve via centralized `dm_engine_data` filter
- DataPacket structure for consistent data flow with chronological ordering
- Clear data packet structure for AI agents with chronological ordering:
  - Root wrapper with data_packets array
  - Index 0 = newest packet (chronological ordering)
  - Type-specific fields (handler, attachments, tool_name)
  - Workflow dynamics and turn-based updates
- Deduplication tracking
- Status detection system
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