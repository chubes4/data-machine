# Data Machine Documentation

Complete user documentation for the Data Machine WordPress plugin - an AI-first content processing system with visual pipeline builder and multi-provider AI integration.

## Quick Navigation

### Getting Started
- [**Overview**](overview.md) - System architecture and core concepts
- [Core System Overview](core-system/engine-execution.md) - Engine execution cycle
- [Database Schema](core-system/database-schema.md) - Data storage and relationships
- [Changelog](CHANGELOG.md) - Version history and changes

### Universal Engine (v0.2.0)
- [Universal Engine Architecture](core-system/universal-engine.md) - Shared AI infrastructure layer
- [AI Conversation Loop](core-system/ai-conversation-loop.md) - Multi-turn conversation execution
- [Tool Execution Architecture](core-system/tool-execution.md) - Tool discovery and execution
- [Tool Manager](core-system/tool-manager.md) - Centralized tool management (v0.2.1)
- [RequestBuilder Pattern](core-system/request-builder.md) - Centralized AI request construction
- [ConversationManager](core-system/conversation-manager.md) - Message formatting and conversation utilities
- [Parameter Systems](api/parameter-systems.md) - Unified parameter architecture and tool building
- [ToolResultFinder](core-system/tool-result-finder.md) - Universal tool result search utility
- [OAuth Handlers](core-system/oauth-handlers.md) - Centralized OAuth 1.0a and 2.0 flow handlers
- [Handler Registration Trait](core-system/handler-registration-trait.md) - Standardized handler registration (v0.2.2)

### Handler Documentation

#### Fetch Handlers
- [WordPress Local](handlers/fetch/wordpress-local.md) - Local WordPress post/page content retrieval
- [WordPress Media](handlers/fetch/wordpress-media.md) - Media library attachments with parent post content
- [WordPress API](handlers/fetch/wordpress-api.md) - External WordPress sites via REST API
- [RSS Feed](handlers/fetch/rss.md) - RSS/Atom feed parsing with deduplication
- [Reddit](handlers/fetch/reddit.md) - OAuth2 subreddit fetching
- [Google Sheets Fetch](handlers/fetch/google-sheets-fetch.md) - Spreadsheet data extraction
- [Files](handlers/fetch/files.md) - Local/remote file processing

#### Publish Handlers  
- [Twitter](handlers/publish/twitter.md) - Twitter publishing with media support
- [Bluesky](handlers/publish/bluesky.md) - App password, AT Protocol
- [Facebook](handlers/publish/facebook.md) - OAuth2, Graph API
- [Threads](handlers/publish/threads.md) - OAuth2, Meta integration
- [WordPress Publish](handlers/publish/wordpress-publish.md) - Local post creation
- [Google Sheets Output](handlers/publish/google-sheets-output.md) - Row insertion

#### Update Handlers
- [WordPress Update](handlers/update/wordpress-update.md) - Modify existing WordPress content

### AI Tools
- [Tools Overview](ai-tools/tools-overview.md) - Complete AI tools system architecture
- [Google Search](ai-tools/google-search.md) - Web search with Custom Search API
- [Local Search](ai-tools/local-search.md) - WordPress internal search
- [WebFetch](ai-tools/web-fetch.md) - Web page content retrieval and processing (50K limit)
- [WordPress Post Reader](ai-tools/wordpress-post-reader.md) - Single WordPress post content retrieval by URL

### API Reference
- [**API Overview**](api/index.md) - Complete endpoint catalog with modular documentation structure
- [**Core Filters**](api-reference/core-filters.md) - All WordPress filters
- [**Core Actions**](api-reference/core-actions.md) - All WordPress actions
- [**Universal Engine Filters**](api-reference/engine-filters.md) - Directive and tool filters (v0.2.0)

### Admin Interface
- [Pipeline Builder](admin-interface/pipeline-builder.md) - Visual drag-and-drop interface
- [Settings Configuration](admin-interface/settings-configuration.md) - AI providers, tools, defaults
- [Jobs Management](admin-interface/jobs-management.md) - Execution monitoring and logs

## Documentation Structure

```
docs/
├── overview.md                          # System overview and concepts
├── core-system/
│   ├── engine-execution.md             # Three-action execution cycle
│   ├── database-schema.md              # Database tables and relationships
│   ├── step.md                         # Step base class (v0.2.1)
│   ├── fetch-handler.md                # FetchHandler base class (v0.2.1)
│   ├── publish-handler.md              # PublishHandler base class (v0.2.1)
│   ├── settings-handler.md             # SettingsHandler base classes (v0.2.1)
│   ├── data-packet.md                  # DataPacket class (v0.2.1)
│   ├── files-repository.md             # FilesRepository components (v0.2.1)
│   ├── wordpress-components.md         # WordPress shared components (v0.2.1)
│   ├── step-navigator.md               # StepNavigator component (v0.2.1)
│   ├── universal-engine.md             # Universal Engine architecture (v0.2.0)
│   ├── ai-conversation-loop.md         # Multi-turn conversation execution (v0.2.0)
│   ├── tool-execution.md               # Tool discovery and execution (v0.2.0)
│   ├── request-builder.md              # Centralized AI request construction (v0.2.0)
│   ├── conversation-manager.md         # Message formatting and conversation utilities (v0.2.1)
│   ├── tool-registration-trait.md      # Agent-agnostic tool registration (v0.2.2)
│   ├── tool-result-finder.md           # Universal tool result search utility (v0.2.1)
│   ├── chat-database.md                # Session management and CRUD operations (v0.2.0)
│   ├── handler-registration-trait.md   # Standardized handler registration (v0.2.2)
│   └── oauth-handlers.md               # Centralized OAuth 1.0a and OAuth 2.0 handlers (v0.2.0)
├── handlers/
│   ├── fetch/
│   │   ├── wordpress-local.md          # Local WordPress content
│   │   ├── wordpress-media.md          # Media library attachments
│   │   ├── wordpress-api.md            # External WordPress sites
│   │   ├── rss.md                      # RSS feed parsing
│   │   ├── reddit.md                   # Reddit subreddit fetching
│   │   ├── google-sheets-fetch.md      # Spreadsheet data extraction
│   │   └── files.md                    # File processing
│   ├── publish/
│   │   ├── wordpress-publish.md        # Local post creation
│   │   ├── twitter.md                  # Twitter publishing
│   │   ├── facebook.md                 # Facebook Graph API
│   │   ├── threads.md                  # Meta Threads
│   │   ├── bluesky.md                  # Bluesky AT Protocol
│   │   └── google-sheets-output.md     # Spreadsheet output
│   └── update/
│       └── wordpress-update.md         # WordPress content updates
├── ai-tools/
│   ├── tools-overview.md                # AI tools system architecture
│   ├── wordpress-post-reader.md        # Single WordPress post content retrieval
│   ├── local-search.md                 # WordPress internal search
│   ├── google-search.md                # Web search tool
│   └── web-fetch.md                    # Web content retrieval (50K limit)
├── admin-interface/
│   ├── pipeline-builder.md             # Visual interface
│   ├── settings-configuration.md       # Configuration options
│   └── jobs-management.md              # Monitoring and logs
├── api/
│   ├── index.md                         # Complete endpoint catalog
│   ├── auth.md                          # OAuth account management
│   ├── authentication.md                # Authentication guide
│   ├── chat.md                          # Conversational AI endpoint
│   ├── errors.md                        # Error handling reference
│   ├── execute.md                       # Flow execution endpoint
│   ├── files.md                         # File upload and management
│   ├── flows.md                         # Flow CRUD operations
│   ├── handlers.md                      # Available handlers
│   ├── intervals.md                     # Scheduling intervals
│   ├── jobs.md                          # Job monitoring
│   ├── logs.md                          # Log management
│   ├── parameter-systems.md             # Unified parameter architecture
│   ├── pipelines.md                     # Pipeline CRUD operations
│   ├── processed-items.md               # Deduplication tracking
│   ├── providers.md                     # AI provider configuration
│   ├── schedule.md                      # Flow scheduling
│   ├── settings.md                      # System settings
│   ├── step-types.md                    # Available step types
│   ├── tools.md                         # AI tool availability
│   └── users.md                         # User preferences
└── api-reference/
    ├── core-filters.md                 # WordPress filters
    ├── core-actions.md                 # WordPress actions
    ├── engine-filters.md               # Universal Engine filters (v0.2.0)
    └── rest-api-extensions.md          # REST API extensions
```

## Component Coverage

### ✅ Core System Components

**Base Class Architecture** (@since v0.2.1) - NEW ARCHITECTURE
- [Step Base Class](core-system/step.md) - Unified payload handling for all step types
- [FetchHandler Base Class](core-system/fetch-handler.md) - Deduplication, engine data storage, filtering, logging
- [PublishHandler Base Class](core-system/publish-handler.md) - Engine data retrieval, image validation, response formatting
- [SettingsHandler Base Classes](core-system/settings-handler.md) - Auto-sanitization for all handler settings
- [DataPacket Class](core-system/data-packet.md) - Standardized data packet creation

**Modular Components** (@since v0.2.1) - NEW ARCHITECTURE
- [FilesRepository Components](core-system/files-repository.md) - 6 specialized components for file management
- [WordPress Shared Components](core-system/wordpress-components.md) - Centralized WordPress functionality
- [StepNavigator](core-system/step-navigator.md) - Centralized step navigation logic

**Engine Execution**
- Three-action cycle (datamachine_run_flow_now → datamachine_execute_step → datamachine_schedule_next_step)
- Action Scheduler integration for scheduled flow execution
- Centralized engine data architecture - clean AI data packets with structured engine parameters via database storage and filter retrieval
- Job management and status tracking
- Error handling and failure recovery
- AutoSave system with complete pipeline persistence, flow synchronization, and cache invalidation

**Database Operations**
- Pipelines (reusable templates)
- Flows (scheduled instances)
- Jobs (individual executions)
- Processed Items (deduplication tracking)

**Advanced Cache Management**
- Enhanced centralized cache system via Actions/Cache.php with comprehensive WordPress action-based clearing and database component integration
- Granular WordPress action-based cache clearing (datamachine_clear_pipeline_cache, datamachine_clear_flow_cache, datamachine_clear_flow_config_cache, datamachine_clear_flow_scheduling_cache, datamachine_clear_flow_steps_cache, datamachine_clear_jobs_cache, datamachine_clear_all_cache)
- Advanced pattern-based cache invalidation with wildcard support and extensible action-based architecture
- Standardized cache storage via datamachine_cache_set action with validation, logging, and performance optimization
- Comprehensive logging for cache operations and AI HTTP Client integration
- Database query optimization for improved pipeline page performance

### ✅ Fetch Handlers (7 handlers)

**Local Sources**
- WordPress Local - Posts/pages from local installation with timeframe filtering and keyword search
- WordPress Media - Media library attachments with parent post content integration, timeframe filtering, and keyword search
- Files - Local and remote file processing with flow-isolated storage

**External Sources**
- RSS - Feed parsing with deduplication, timeframe filtering, keyword search, and centralized engine data storage (source_url, image_url via datamachine_engine_data filter)
- Reddit - OAuth2 subreddit fetching with timeframe filtering, keyword search, and centralized engine data storage (source_url, image_url via datamachine_engine_data filter)
- Google Sheets - OAuth2 spreadsheet data extraction
- WordPress API - External WordPress sites via REST API with timeframe filtering, keyword search, and centralized engine data storage

### ✅ Publish Handlers (6 handlers)

**Social Media**
- Twitter - OAuth 1.0a, 280 char limit, media upload
- Bluesky - App password, 300 char limit, AT Protocol
- Facebook - OAuth2, no limit, Graph API
- Threads - OAuth2, 500 char limit, Meta integration

**Content Platforms**
- WordPress - Modular post creation with specialized components (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`) and configuration hierarchy
- Google Sheets - OAuth2 row insertion and data management

### ✅ Update Handlers (1 handler)

**Content Modification**
- WordPress Update - Modify existing posts/pages using source_url from centralized engine data via datamachine_engine_data filter with advanced tool discovery (exact handler matching and partial name matching for flexible tool result detection)

### ✅ AI Tools (4 general + handler tools)

**General Tools (Universal)**
- Google Search - Web search with Custom Search API
- Local Search - WordPress internal content search
- WebFetch - Web page content retrieval (50K character limit)
- WordPress Post Reader - Single WordPress post content retrieval by URL

**Handler Tools** (Generated per handler)
- Publishing tools for each platform (twitter_publish, facebook_publish, etc.)
- Update tools for content modification

**REST API Architecture**: 17 REST API endpoints implemented
- Core endpoints: Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Schedule, Settings, StepTypes, Tools, Users (individual files at `inc/Api/*.php`)
- Directory-based endpoints: Chat (`inc/Api/Chat/`), Schedule (`inc/Api/Schedule/`)
- Admin interface: React 18 with complete REST API integration, zero AJAX dependencies

### ✅ Authentication Systems

**OAuth 2.0** - Reddit, Google Sheets, Facebook, Threads
**OAuth 1.0a** - Twitter
**App Passwords** - Bluesky
**API Keys** - Google Search, AI providers

### ✅ Admin Interface

**Pipeline Builder (React)**
- 6,591 lines of React code using @wordpress/element and @wordpress/components
- 50+ specialized components (cards, modals, shared utilities)
- Modern state management with TanStack Query (server state) + Zustand (client state)
- Complete REST API integration for all data operations
- Zero AJAX dependencies
- Real-time updates without page reloads
- Optimistic UI updates for instant feedback

**Settings Pages** (REST API)
- AI provider configuration
- Tool enablement and configuration
- WordPress defaults
- OAuth management

**Jobs Management** (REST API)
- Execution monitoring
- Status tracking
- Error logs and debugging

## Key Features Documented

### Pipeline Architecture
- Pipeline+Flow separation (templates vs instances)
- Step execution with parameter passing
- Data packet structure and flow
- Deduplication and processed items tracking

### AI Integration
- Multi-provider support (OpenAI, Anthropic, Google, Grok, OpenRouter)
- **Universal Engine Architecture**: Shared AI infrastructure at `/inc/Engine/AI/` for Pipeline and Chat agents
- **AIConversationLoop**: Multi-turn conversation execution with automatic tool execution and completion detection
- **RequestBuilder**: Centralized AI request construction with hierarchical directive application
- **ToolExecutor**: Universal tool discovery, enablement validation, and execution with error handling
- **ToolParameters**: Unified parameter building for standard tools and handler tools with engine data integration
- **ConversationManager**: Message formatting and validation utilities for standardized conversation management
- **AI Directive System**: Filter-based directive architecture (global → agent → type-specific) with automatic registration
- **Tool-First Architecture**: Agentic execution with intelligent tool discovery and enhanced tool result detection
- **Context-Aware Processing**: WordPress site context integration with metadata and clear tool result messaging

### Extension System
- Filter-based service discovery
- Custom handler development patterns
- Tool registration and configuration
- OAuth integration examples

### Performance Features
- Action Scheduler asynchronous processing
- Files repository for large data handling
- Single-item processing strategy
- Memory optimization and cleanup
- Database query optimization for improved pipeline page performance
- Centralized cache management with pattern-based invalidation

## Usage Patterns

### Single Platform Publishing
Fetch → AI → Publish workflow for focused content distribution

### Multi-Platform Publishing  
Fetch → AI → Publish → AI → Publish for platform-specific optimization

### Content Enhancement
Fetch → AI → Update workflow for improving existing content

### Research and Analysis
AI with Google Search and Local Search tools for comprehensive content analysis

## Integration Notes

### WordPress Compatibility
- Requires PHP 8.0+, WordPress 6.2+
- Uses WordPress core functions and standards  
- Integrates with WordPress admin, users, and permissions
- Compatible with multisite installations

### Security
- `manage_options` capability required for all operations
- WordPress REST API authentication and nonce validation
- Input sanitization and validation
- Secure OAuth credential storage

### Performance
- Asynchronous processing via Action Scheduler
- Optimized database queries with proper indexing
- Memory-efficient data processing
- Automatic cleanup and maintenance

This documentation provides complete coverage of all Data Machine components, from core execution engine through specific handler implementations, AI tools, and administrative interfaces. Each component is documented with essential information for users to understand and effectively utilize the system.