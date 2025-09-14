# Data Machine Documentation

Complete user documentation for the Data Machine WordPress plugin - an AI-first content processing system with visual pipeline builder and multi-provider AI integration.

## Quick Navigation

### Getting Started
- [**Overview**](overview.md) - System architecture and core concepts
- [Core System Overview](core-system/engine-execution.md) - Engine execution cycle
- [Database Schema](core-system/database-schema.md) - Data storage and relationships

### Handler Documentation

#### Fetch Handlers
- [RSS Feed](handlers/fetch/rss.md) - RSS/Atom feed parsing with deduplication
- [Reddit](handlers/fetch/reddit.md) - OAuth2 subreddit fetching  
- [Google Sheets Fetch](handlers/fetch/google-sheets-fetch.md) - Spreadsheet data extraction
- [Files](handlers/fetch/files.md) - Local/remote file processing
- [WordPress Media](handlers/fetch/wordpress-media.md) - Media library attachments
- [WordPress API](handlers/fetch/wordpress-api.md) - External WordPress REST API

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

### API Reference
- [**Core Filters**](api-reference/core-filters.md) - All WordPress filters
- [**Core Actions**](api-reference/core-actions.md) - All WordPress actions

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
│   └── database-schema.md              # Database tables and relationships
├── handlers/
│   ├── fetch/
│   │   ├── rss.md                      # RSS feed parsing
│   │   ├── reddit.md                   # Reddit subreddit fetching
│   │   ├── google-sheets-fetch.md      # Spreadsheet data extraction
│   │   ├── files.md                    # File processing
│   │   ├── wordpress-media.md          # Media library attachments
│   │   └── wordpress-api.md            # External WordPress sites
│   ├── publish/
│   │   ├── twitter.md                  # Twitter publishing
│   │   ├── bluesky.md                  # Bluesky AT Protocol
│   │   ├── facebook.md                 # Facebook Graph API
│   │   ├── threads.md                  # Meta Threads
│   │   ├── wordpress-publish.md        # Local post creation
│   │   └── google-sheets-output.md     # Spreadsheet output
│   └── update/
│       └── wordpress-update.md         # WordPress content updates
├── ai-tools/
│   ├── tools-overview.md                # AI tools system architecture
│   ├── google-search.md                # Web search tool
│   ├── local-search.md                 # WordPress internal search
│   └── web-fetch.md                     # Web content retrieval (50K limit)
├── admin-interface/
│   ├── pipeline-builder.md             # Visual interface
│   ├── settings-configuration.md       # Configuration options
│   └── jobs-management.md              # Monitoring and logs
└── api-reference/
    ├── core-filters.md                 # WordPress filters
    └── core-actions.md                 # WordPress actions
```

## Component Coverage

### ✅ Core System Components

**Engine Execution**
- Three-action cycle (run_flow_now → execute_step → schedule_next_step)
- Action Scheduler integration
- Data packet processing
- Job management and status tracking
- Error handling and failure recovery

**Database Operations**  
- Pipelines (reusable templates)
- Flows (scheduled instances)
- Jobs (individual executions)
- Processed Items (deduplication tracking)

### ✅ Fetch Handlers (7 handlers)

**Local Sources**
- WordPress - Posts/pages from local installation
- WordPress Media - Media library attachments  
- Files - Local and remote file processing

**External Sources**
- RSS - Feed parsing with deduplication
- Reddit - OAuth2 subreddit fetching
- Google Sheets - OAuth2 spreadsheet data extraction
- WordPress API - External WordPress sites via REST API

### ✅ Publish Handlers (6 handlers)

**Social Media**
- Twitter - OAuth 1.0a, 280 char limit, media upload
- Bluesky - App password, 300 char limit, AT Protocol
- Facebook - OAuth2, no limit, Graph API
- Threads - OAuth2, 500 char limit, Meta integration

**Content Platforms**
- WordPress - Local post creation with taxonomy support
- Google Sheets - OAuth2 row insertion and data management

### ✅ Update Handlers (1 handler)

**Content Modification**
- WordPress Update - Modify existing posts/pages using source_url

### ✅ AI Tools (4 general + handler tools)

**General Tools (Universal)**
- Google Search - Web search with Custom Search API
- Local Search - WordPress internal content search
- WebFetch - Web page content retrieval (50K character limit)
- WordPress Post Reader - Single WordPress post content retrieval by URL

**Handler Tools** (Generated per handler)
- Publishing tools for each platform (twitter_publish, facebook_publish, etc.)
- Update tools for content modification

### ✅ Authentication Systems

**OAuth 2.0** - Reddit, Google Sheets, Facebook, Threads
**OAuth 1.0a** - Twitter
**App Passwords** - Bluesky
**API Keys** - Google Search, AI providers

### ✅ Admin Interface

**Pipeline Builder**
- Visual drag-and-drop interface
- Step configuration modals
- Handler selection and settings
- Auto-save functionality

**Settings Pages**
- AI provider configuration
- Tool enablement and configuration  
- WordPress defaults
- OAuth management

**Jobs Management**
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
- 5-tier AI directive system with auto-registration:
  - GlobalSystemPromptDirective (Priority 10 - foundational behavior)
  - PipelineSystemPromptDirective (Priority 20 - workflow visualization)
  - ToolDefinitionsDirective (Priority 30 - usage instructions)
  - DataPacketStructureDirective (Priority 40 - JSON format explanation)
  - SiteContextDirective (Priority 50 - WordPress environment)
- AIStepConversationManager for centralized conversation state management:
  - Turn-based conversation loops with chronological message ordering
  - AI tool calls recorded before execution with turn number tracking
  - Enhanced tool result messaging with temporal context
  - Data packet synchronization via updateDataPacketMessages()
- Tool-first architecture for agentic execution
- AIStepToolParameters for unified flat parameter building:
  - buildParameters() for standard AI tools
  - buildForHandlerTool() for handler tools with engine parameters
  - Content/title extraction from data packets
- Context-aware processing with WordPress site context
- Clear tool result messaging enabling natural conversation termination

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
- Requires PHP 8.0+, WordPress 6.0+
- Uses WordPress core functions and standards  
- Integrates with WordPress admin, users, and permissions
- Compatible with multisite installations

### Security
- `manage_options` capability required for all operations
- Nonce validation for AJAX requests
- Input sanitization and validation
- Secure OAuth credential storage

### Performance
- Asynchronous processing via Action Scheduler
- Optimized database queries with proper indexing
- Memory-efficient data processing
- Automatic cleanup and maintenance

This documentation provides complete coverage of all Data Machine components, from core execution engine through specific handler implementations, AI tools, and administrative interfaces. Each component is documented with essential information for users to understand and effectively utilize the system.