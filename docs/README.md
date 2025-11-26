# Data Machine Documentation

Complete user documentation for the Data Machine WordPress plugin (v0.3.0) - an AI-first content processing system with visual pipeline builder and multi-provider AI integration.

## Quick Navigation

### Getting Started
- **Overview**: System architecture and core concepts
- **Core System Overview**: Engine execution cycle
- **Database Schema**: Data storage and relationships
- **Changelog**: Version history and changes

### Universal Engine
- **Universal Engine Architecture**: Shared AI infrastructure layer
- **AI Conversation Loop**: Multi-turn conversation execution
- **Tool Execution Architecture**: Tool discovery and execution
- **Tool Manager**: Centralized tool management
- **RequestBuilder Pattern**: Centralized AI request construction
- **ConversationManager**: Message formatting and conversation utilities
- **Parameter Systems**: Unified parameter architecture and tool building
- **ToolResultFinder**: Universal tool result search utility
- **OAuth Handlers**: Base authentication provider architecture
- **Handler Registration Trait**: Standardized handler registration

### Core System Components
- **Logger**: Centralized logging with Monolog integration
- **Cache Management**: Granular cache invalidation with pattern support

### WordPress Components
- **WordPressPublishHelper**: WordPress-specific publishing operations (media attachment, source attribution)
- **WordPressSettingsResolver**: Centralized post status/author resolution
- **TaxonomyHandler**: Taxonomy selection and dynamic term creation
- **EngineData**: Platform-agnostic data access layer

### React Architecture
- **HandlerModel**: Abstract model layer for handler data operations
- **HandlerFactory**: Factory pattern for handler model instantiation
- **useHandlerModel**: Custom hook for handler model integration
- **ModalSwitch**: Centralized modal routing component
- **HandlerProvider**: React context for handler state management

### Handler Documentation

#### Fetch Handlers
- **WordPress Local**: Local WordPress post/page content retrieval
- **WordPress Media**: Media library attachments with parent post content
- **WordPress API**: External WordPress sites via REST API
- **RSS Feed**: RSS/Atom feed parsing with deduplication
- **Reddit**: OAuth2 subreddit fetching
- **Google Sheets Fetch**: Spreadsheet data extraction
- **Files**: Local/remote file processing

#### Publish Handlers
- **Twitter**: Twitter publishing with media support
- **Bluesky**: App password, AT Protocol
- **Facebook**: OAuth2, Graph API
- **Threads**: OAuth2, Meta integration
- **WordPress Publish**: Local post creation with WordPressPublishHelper integration
- **Google Sheets Output**: Spreadsheet output

#### Update Handlers
- **WordPress Update**: Modify existing WordPress content

### AI Tools
- **Tools Overview**: Complete AI tools system architecture
- **Google Search**: Web search with Custom Search API
- **Local Search**: WordPress internal search
- **WebFetch**: Web page content retrieval and processing (50K limit)
- **WordPress Post Reader**: Single WordPress post content retrieval by URL

### API Reference
- **API Overview**: Complete endpoint catalog with modular documentation structure
- **Core Filters**: All WordPress filters
- **Core Actions**: All WordPress actions
- **Universal Engine Filters**: Directive and tool filters

### Admin Interface
- **Pipeline Builder**: Visual drag-and-drop interface
- **Settings Configuration**: AI providers, tools, defaults
- **Jobs Management**: Execution monitoring and logs

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
│   ├── oauth-handlers.md               # Centralized OAuth 1.0a and OAuth 2.0 handlers (v0.2.0)
│   ├── logger.md                      # Centralized logging with Monolog integration
│   └── cache-management.md            # Granular cache invalidation with pattern support
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
```

## Component Coverage

(Documentation continues in individual files; refer to the file names above for topics.)
