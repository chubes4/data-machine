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
- `wp_dm_jobs` - Job executions with status tracking
- `wp_dm_processed_items` - Deduplication tracking per execution

### Step Types
- **Fetch**: Data retrieval (Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API)
- **AI**: Content processing with multi-provider support (OpenAI, Anthropic, Google, Grok)
- **Publish**: Content distribution (Twitter, Facebook, Threads, Bluesky, WordPress)
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
  - Engine parameter merging for Update handlers (source_url)
- Three-layer tool enablement: Global settings → Modal selection → Runtime validation
- AIStepConversationManager for conversation state and tool result formatting with turn tracking

### Filter-Based Discovery
All components self-register via WordPress filters:
- `dm_handlers` - Register fetch/publish/update handlers
- `ai_tools` - Register AI tools and capabilities
- `dm_auth_providers` - Register authentication providers
- `dm_steps` - Register custom step types

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
- 6-tier AI directive priority system with standardized spacing for extensibility:
  - **Priority 5**: PluginCoreDirective (foundational AI agent identity)
  - **Priority 10**: GlobalSystemPromptDirective (foundational AI behavior)
  - **Priority 20**: PipelineSystemPromptDirective (workflow structure visualization)
  - **Priority 30**: ToolDefinitionsDirective (tool definitions + workflow context)
  - **Priority 40**: DataPacketStructureDirective (JSON structure explanation)
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
- DataPacket structure for consistent data flow with chronological ordering
- DataPacketStructureDirective explains JSON format to AI agents:
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