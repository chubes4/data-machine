=== Data Machine ===
Contributors: extrachill
Tags: ai, automation, content, workflow, pipeline, chat
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with visual pipeline builder, conversational chat interface, REST API, and multi-provider AI integration.

## Architecture

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**:
- **Modern React Admin Interface**: Complete Pipelines page rebuild with 6,591 lines of React code using @wordpress/element and @wordpress/components
- **Zero jQuery/AJAX Architecture**: Modern React frontend with REST API integration
- **Tool-First AI**: Universal Engine architecture with multi-turn conversation loops, centralized tool execution, and filter-based directive system
- **Universal Engine Layer**: Shared AI infrastructure serving both Pipeline and Chat agents with AIConversationLoop, ToolExecutor, ToolParameters, ConversationManager, and RequestBuilder components
- **Visual Pipeline Builder**: Real-time updates with 50+ React components, custom hooks, and Context API state management
- **Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter with filter-based directive system (global, agent-specific, pipeline, chat)
- **Complete REST API**: 16 endpoints (Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users, Chat base, Chat/Chat)
- **Chat API**: Conversational interface for building and executing workflows through natural language
- **Ephemeral Workflows**: Execute workflows without database persistence via REST API
- **Centralized Engine Data**: Unified filter access pattern with clean AI data packets and structured engine parameters
- **Enhanced Handler System**: Universal filter patterns with shared functionality across all handlers
- **Performance Optimizations**: 50% query reduction in handler settings operations with metadata-based auth detection
- **Advanced Cache Management**: Granular WordPress action-based clearing with pattern-based invalidation

**Requirements**: WordPress 6.2+, PHP 8.0+, Action Scheduler (woocommerce/action-scheduler), Composer (for development)

**Pipeline+Flow**: Pipelines are reusable templates, Flows are configured instances

**Example**: WordPress Content Enhancement System
- **Pipeline Template**: Fetch → AI → Update (defines workflow structure with system prompt "You are a content optimizer. Analyze existing WordPress content and enhance it with better SEO, readability, and comprehensive information using research tools.")
- **Flow A**: WordPress Local (old blog posts) → AI + Google Search tool → WordPress Update (weekly)
- **Flow B**: WordPress Local (draft pages) → AI + Local Search tool → WordPress Update (daily)
- **Flow C**: WordPress Local (product pages) → AI + WebFetch tool → WordPress Update (bi-weekly)

## Quick Start

### Installation

**Development**:
1. Clone to `/wp-content/plugins/datamachine/`
2. Run `composer install`
3. Activate plugin
4. Configure AI provider at Settings → Data Machine

**Production**:
1. Run `./build.sh` to create `/dist/datamachine.zip`
2. Install via WordPress admin
3. Configure AI provider and tools

### Configuration

**Google Search** (optional):
1. Create Custom Search Engine + get API key
2. Add credentials at Settings → Data Machine → Tool Configuration
3. Free tier: 100 queries/day


**OAuth Providers**:
- Twitter: OAuth 1.0a (centralized OAuth1Handler at `/inc/Core/OAuth/`)
- Reddit/Facebook/Threads/Google Sheets: OAuth 2.0 (centralized OAuth2Handler at `/inc/Core/OAuth/`)
- Bluesky: App Password (direct authentication)

**OAuth Architecture**: Centralized handler services via `datamachine_get_oauth1_handler` and `datamachine_get_oauth2_handler` filters eliminate code duplication across providers. Auth via `/datamachine-auth/{provider}/` popup flow.

### Quick Example: Document Processing System

1. **Create Pipeline Template**: "Document Processing" (Fetch → AI → Publish)
2. **Add System Prompt**: "Extract key insights and create structured WordPress posts with proper headings, summaries, and tags"
3. **Create Flow Instance**: Files handler → AI → WordPress handler
4. **Configure Flow**: Upload PDFs, set scheduling, configure WordPress settings
5. **Result**: Automatic WordPress posts with clean formatting and taxonomy

## Examples

### Workflow Patterns

**Content Enhancement**: Pipeline (Fetch → AI → Update) + Flow (WordPress Local → AI + tools → WordPress Update)
- Template defines step structure, flow selects specific handlers and tools
- Uses `source_url` from engine data to target specific content

**Document Processing**: Pipeline (Fetch → AI → Publish) + Flow (Files → AI + tools → WordPress)
- Template provides workflow, flow configures file handling and publishing destination
- Flow-isolated file storage with automatic cleanup

**Research Workflows**: Pipeline (Fetch → AI → Publish) + Flow (Google Sheets → AI + WebFetch → WordPress)
- Template structures workflow, flow defines data source and research tools
- Multi-turn AI conversations for complex content creation

**Multi-Platform Publishing**: Pipeline (Fetch → AI → Publish → AI → Publish) + Flow Configuration
- Template structures sequential publishing workflow
- Flow configures RSS/Reddit → AI → Twitter → AI → Facebook publishing chain
- Engine data maintains source attribution throughout workflow

**WordPress Content Enhancement**: Pipeline (Fetch → AI → Update) + Multiple Enhancement Flows
- Pipeline: "Content Optimizer" (Fetch → AI → Update)
- Flow A: WordPress Local (old posts) → AI + Google Search tool → WordPress Update (weekly SEO refresh)
- Flow B: WordPress Local (draft content) → AI + WebFetch tool → WordPress Update (research enhancement)
- Flow C: WordPress Local (product pages) → AI + Local Search + WordPress Post Reader → WordPress Update (internal linking)

**Automated News Publishing**: Pipeline (Fetch → AI → Publish) + Multiple Source Flows
- Pipeline: "News Feed" (Fetch → AI → Publish)
- Flow A: TechCrunch RSS → AI → WordPress (hourly tech news)
- Flow B: Reddit r/webdev → AI → WordPress (daily development updates)
- Flow C: Industry Google Sheets → AI → WordPress (weekly reports)

> **Note**: Update workflows require `source_url` (provided by fetch handlers or AI tools like Local Search/WordPress Post Reader). AI tools enable multi-turn conversations for complex research and analysis tasks.

*For detailed examples and technical specifications, see `CLAUDE.md`*

## Programmatic Usage

```php
// Pipeline creation and execution
$pipeline_id = apply_filters('datamachine_create_pipeline', null, ['pipeline_name' => 'My Pipeline']);
$step_id = apply_filters('datamachine_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('datamachine_create_flow', null, ['pipeline_id' => $pipeline_id]);
do_action('datamachine_run_flow_now', $flow_id, 'manual');

// AI integration
$response = apply_filters('chubes_ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-5-mini'
], 'openai');
```

### REST API

Data Machine provides comprehensive REST API access via 16 endpoints for flow execution, pipeline management, and system monitoring:
- **Core**: Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users
- **Chat**: Chat (base), Chat/Chat (conversations)

**Unified Execute Endpoint** (`POST /datamachine/v1/execute`):

```bash
# Database Flow - Immediate
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'

# Database Flow - Recurring
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "action": "schedule", "interval": "hourly"}'

# Database Flow - Delayed (one-time)
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "timestamp": 1704153600}'

# Ephemeral Workflow - Immediate
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"workflow": {"steps": [{"type": "fetch", "handler": "rss"}, {"type": "ai"}, {"type": "publish", "handler": "twitter"}]}}'

# Success Response
{
  "success": true,
  "execution_type": "immediate",
  "flow_id": 123,
  "message": "Flow execution started successfully."
}
```

**Available Endpoints**:

*Execution:*
- `POST /datamachine/v1/execute` - Execute flows or ephemeral workflows (immediate, recurring, delayed)

*Chat Interface:*
- `POST /datamachine/v1/chat` - Conversational AI endpoint with session management

*Flow Management:*
- `POST /datamachine/v1/flows` - Create flows
- `DELETE /datamachine/v1/flows/{id}` - Delete flows
- `POST /datamachine/v1/flows/{id}/duplicate` - Duplicate flows

*Pipeline Management:*
- `GET /datamachine/v1/pipelines` - Retrieve pipelines
- `POST /datamachine/v1/pipelines` - Create pipelines
- `DELETE /datamachine/v1/pipelines/{id}` - Delete pipelines
- `POST /datamachine/v1/pipelines/{id}/steps` - Add steps
- `PUT /datamachine/v1/pipelines/{id}/steps/reorder` - Reorder steps
- `DELETE /datamachine/v1/pipelines/{id}/steps/{step_id}` - Remove steps

*Files & Storage:*
- `POST /datamachine/v1/files` - Upload files
- `GET /datamachine/v1/files` - List files
- `DELETE /datamachine/v1/files/{filename}` - Delete files

*User Management:*
- `GET /datamachine/v1/users/{id}` - User preferences
- `POST /datamachine/v1/users/{id}` - Update preferences
- `GET /datamachine/v1/users/me` - Current user
- `POST /datamachine/v1/users/me` - Update current user

*System & Monitoring:*
- `GET /datamachine/v1/status` - Flow/pipeline status
- `GET /datamachine/v1/logs` - Retrieve logs
- `DELETE /datamachine/v1/logs` - Clear logs
- `GET /datamachine/v1/jobs` - Job history
- `DELETE /datamachine/v1/jobs` - Clear jobs
- `GET /datamachine/v1/processed-items` - Processed items
- `DELETE /datamachine/v1/processed-items` - Clear processed items

**Implementation**: 16 REST API endpoints with directory-based structure (@since v0.2.0):
- **Directory-based endpoints**:
  - **Pipelines** (`/inc/Api/Pipelines/`): Pipelines.php (main endpoint), PipelineSteps.php (steps CRUD), PipelineFlows.php (pipeline flows)
  - **Flows** (`/inc/Api/Flows/`): Flows.php (main endpoint), FlowSteps.php (flow steps CRUD)
  - **Chat** (`/inc/Api/Chat/`): Chat.php (endpoint handler), ChatAgentDirective.php (AI directive), ChatFilters.php (self-registration), Tools/MakeAPIRequest.php (chat-only tool)
- **Single-file endpoints**: Auth, Execute, Files, Handlers, Jobs, Logs, ProcessedItems, Providers, Settings, StepTypes, Tools, Users (at `/inc/Api/*.php`)
- **Nested endpoints**: Pipeline steps, flow configuration, chat sessions with structured URL routing

**Requirements**: WordPress application password or cookie authentication with `manage_options` capability (except `/users/me` which requires authentication only). Action Scheduler required for scheduled flow execution (woocommerce/action-scheduler via Composer).

**Frontend Integration**: React architecture with REST API integration across all admin pages.

*For complete REST API documentation, see `docs/api-reference/rest-api.md` | For technical specifications, see `CLAUDE.md`*

### Extension Development

Complete extension framework supporting Fetch, Publish, Update handlers, AI tools, and Database services with filter-based auto-discovery.

*See `CLAUDE.md` for development guides and technical specifications*

## Available Handlers

**Fetch Sources**:
- Local/remote files
- RSS feeds (timeframe/keyword filtering)
- Reddit posts (timeframe/keyword filtering)
- WordPress Local (timeframe/keyword filtering)
- WordPress Media (with parent post content integration, timeframe/keyword filtering)
- WordPress API (timeframe/keyword filtering)
- Google Sheets

**Publish Destinations**:
- Twitter, Bluesky, Threads, Facebook
- WordPress
- Google Sheets

**Update Handlers**:
- WordPress Update (existing post/page modification via source_url from engine data filter access)

**AI Providers**:
- OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Global Tools**:
- Google Search, Local Search
- WebFetch (50K character limit)
- WordPress Post Reader

**Architecture Highlights**:
- **Centralized OAuth Handlers**:
  - `OAuth1Handler` and `OAuth2Handler` classes at `/inc/Core/OAuth/` eliminate duplication
  - Service discovery via `datamachine_get_oauth1_handler` and `datamachine_get_oauth2_handler` filters
  - Single implementation for 6 providers (Twitter, Reddit, Facebook, Threads, Google Sheets, future providers)
- **Centralized Engine Data**:
  - `datamachine_engine_data` filter provides unified access to source_url, image_url
  - Clean separation between AI data packets and handler engine parameters
- **Universal Handler Filters**:
  - Shared functionality (`datamachine_timeframe_limit`, `datamachine_keyword_search_match`, `datamachine_data_packet`)
  - Eliminates code duplication across multiple handlers
- **Universal Engine Architecture**:
  - Shared AI infrastructure (`AIConversationLoop`, `RequestBuilder`, `ToolExecutor`, `ToolParameters`, `ConversationManager`, `ToolResultFinder`)
  - Multi-turn conversation execution with automatic tool handling
  - Centralized parameter building and request construction
  - Universal tool result search utility for data packet interpretation
- **Modular WordPress Publisher**:
  - Specialized components (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`)
  - Configuration hierarchy system
- **Complete AutoSave System**:
  - Single `datamachine_auto_save` action handles pipeline persistence, flow synchronization, and cache invalidation
- **Filter-Based Discovery**:
  - All components self-register via WordPress filters maintaining consistent architectural patterns


*All handlers are fully functional with OAuth authentication where required and comprehensive error handling*

*For detailed specifications, see `CLAUDE.md`*


## Use Cases

- Content marketing automation
- News monitoring and alerts
- Document processing and extraction
- Social media management
- Content repurposing
- Research automation
- WordPress workflow integration

## External Services

This plugin connects to third-party services for AI processing, content fetching, and publishing. All external connections are **user-initiated** through workflow configuration. No data is sent to external services without explicit user setup and workflow execution.

### AI Providers (User Configured, Optional)

Users must configure at least one AI provider to use Data Machine's workflow automation features. API keys and configuration are stored locally in your WordPress database.

**OpenAI** - https://openai.com/
- **Purpose**: AI text processing and content generation for workflow automation
- **Data Sent**: User-configured prompts, content for processing, selected AI model preferences
- **When**: During flow execution when OpenAI is selected as the AI provider
- **Terms of Service**: https://openai.com/policies/terms-of-use
- **Privacy Policy**: https://openai.com/policies/privacy-policy

**Anthropic (Claude)** - https://anthropic.com/
- **Purpose**: AI text processing and content generation for workflow automation
- **Data Sent**: User-configured prompts, content for processing, selected AI model preferences
- **When**: During flow execution when Anthropic is selected as the AI provider
- **Terms of Service**: https://www.anthropic.com/legal/consumer-terms
- **Privacy Policy**: https://www.anthropic.com/legal/privacy

**Google Gemini** - https://ai.google.dev/
- **Purpose**: AI text processing and content generation for workflow automation
- **Data Sent**: User-configured prompts, content for processing, selected AI model preferences
- **When**: During flow execution when Google is selected as the AI provider
- **Terms of Service**: https://ai.google.dev/gemini-api/terms
- **Privacy Policy**: https://policies.google.com/privacy

**Grok (X.AI)** - https://x.ai/
- **Purpose**: AI text processing and content generation for workflow automation
- **Data Sent**: User-configured prompts, content for processing, selected AI model preferences
- **When**: During flow execution when Grok is selected as the AI provider
- **Terms of Service**: https://x.ai/legal/terms-of-service
- **Privacy Policy**: https://x.ai/legal/privacy-policy

**OpenRouter** - https://openrouter.ai/
- **Purpose**: AI model routing and processing (provides access to 200+ AI models from multiple providers)
- **Data Sent**: User-configured prompts, content for processing, selected AI model preferences
- **When**: During flow execution when OpenRouter is selected as the AI provider
- **Terms of Service**: https://openrouter.ai/terms
- **Privacy Policy**: https://openrouter.ai/privacy

### Content Fetch Handlers (User Configured, Optional)

**Google Sheets API** - https://developers.google.com/sheets/api
- **Purpose**: Read data from Google Sheets spreadsheets for content workflows
- **Data Sent**: OAuth2 credentials, spreadsheet IDs, range specifications
- **When**: During flow execution with Google Sheets fetch handler enabled
- **Terms of Service**: https://developers.google.com/terms
- **Privacy Policy**: https://policies.google.com/privacy

**Reddit API** - https://www.reddit.com/dev/api
- **Purpose**: Fetch posts and comments from specified subreddits for content workflows
- **Data Sent**: OAuth2 credentials, subreddit names, search queries, timeframe filters
- **When**: During flow execution with Reddit fetch handler enabled
- **Terms of Service**: https://www.redditinc.com/policies/user-agreement
- **Privacy Policy**: https://www.reddit.com/policies/privacy-policy

### Content Publish Handlers (User Configured, Optional)

**Twitter API** - https://developer.twitter.com/
- **Purpose**: Post tweets with media support as part of content publishing workflows
- **Data Sent**: OAuth 1.0a credentials, tweet content (max 280 characters), media files
- **When**: During flow execution with Twitter publish handler enabled
- **Terms of Service**: https://developer.twitter.com/en/developer-terms/agreement-and-policy
- **Privacy Policy**: https://twitter.com/en/privacy

**Facebook Graph API** - https://developers.facebook.com/
- **Purpose**: Post content to Facebook pages as part of content publishing workflows
- **Data Sent**: OAuth2 credentials, post content, media files, page access tokens
- **When**: During flow execution with Facebook publish handler enabled
- **Terms of Service**: https://developers.facebook.com/terms
- **Privacy Policy**: https://www.facebook.com/privacy/policy

**Threads API** - https://developers.facebook.com/docs/threads
- **Purpose**: Post content to Instagram Threads as part of content publishing workflows
- **Data Sent**: OAuth2 credentials (via Facebook authentication), post content (max 500 characters), media files
- **When**: During flow execution with Threads publish handler enabled
- **Terms of Service**: https://developers.facebook.com/terms
- **Privacy Policy**: https://help.instagram.com/privacy/

**Bluesky (AT Protocol)** - https://bsky.app/
- **Purpose**: Post content to Bluesky social network as part of content publishing workflows
- **Data Sent**: App password credentials, post content (max 300 characters), media files
- **When**: During flow execution with Bluesky publish handler enabled
- **Terms of Service**: https://bsky.social/about/support/tos
- **Privacy Policy**: https://bsky.social/about/support/privacy-policy

**Google Sheets API** (Publishing)
- **Purpose**: Write data to Google Sheets spreadsheets as part of content publishing workflows
- **Data Sent**: OAuth2 credentials, spreadsheet IDs, row data for insertion
- **When**: During flow execution with Google Sheets publish handler enabled
- **Terms of Service**: https://developers.google.com/terms
- **Privacy Policy**: https://policies.google.com/privacy

### AI Tools (User Configured, Optional)

**Google Custom Search API** - https://developers.google.com/custom-search
- **Purpose**: Provide web search functionality for AI agents during content research and generation
- **Data Sent**: API key, Custom Search Engine ID, search queries initiated by AI
- **When**: When AI uses the Google Search tool during workflow execution (requires user-configured API credentials)
- **Terms of Service**: https://developers.google.com/terms
- **Privacy Policy**: https://policies.google.com/privacy
- **Free Tier**: 100 queries per day

### Data Handling and Privacy

- **Local Storage**: All API keys, OAuth credentials, and configuration data are stored locally in your WordPress database
- **User Control**: Users have complete control over which services are configured and when workflows execute
- **No Automatic Connections**: The plugin makes no external connections until a user explicitly creates and executes a workflow
- **Data Transmission**: Only user-configured content and prompts are sent to external services during workflow execution
- **Opt-In Only**: All external service integrations require explicit user configuration and are disabled by default

## Administration

**Pages**: Pipelines (React), Jobs, Logs

**Settings** (WordPress Settings → Data Machine):
- Engine Mode (headless), page controls, tool toggles
- Site Context toggle (WordPress info injection)
- Job data cleanup on failure toggle (debugging)
- File retention settings (1-90 days)
- **Filter-Based AI Directive System**: Auto-registering directive classes via `datamachine_global_directives` and `datamachine_agent_directives` filters
- **Universal Engine Components**: AIConversationLoop for multi-turn conversations, ToolExecutor for tool discovery, ToolParameters for parameter building
- **Dual-Agent Architecture**: Shared engine infrastructure for Pipeline and Chat agents with agent-specific behaviors
- Tool configuration (API keys, OAuth)
- WordPress defaults (post types, taxonomies, author, status)
- Three-layer tool management (global → modal → validation)

**Features**: React interface with real-time updates, zero page reloads, auto-save, status indicators, modern WordPress components

## Development

```bash
composer install    # Development setup
composer test       # Run tests (PHPUnit configured, test files not yet implemented)
./build.sh          # Production build to /dist/datamachine.zip
```

**Architecture**:
- **React Frontend Architecture**:
  - Pipelines page: 6,591 lines of React code (50+ components)
  - Modern state management with custom hooks (usePipelines, useFlows, useStepTypes, useHandlers)
  - Context API for global state (PipelineContext)
  - Complete REST API integration for all data operations
  - Zero jQuery/AJAX dependencies
- **PSR-4 Autoloading**: Composer-managed dependency structure
- **Filter-Based Service Discovery**: WordPress hooks for component registration
- **Unified Handler Filter System**:
  - Centralized cross-cutting filters (`datamachine_timeframe_limit`, `datamachine_keyword_search_match`, `datamachine_data_packet`)
- **Centralized Engine Data**: `EngineData.php` filter providing unified `datamachine_engine_data` access with clean AI data packets
- **Centralized Cache System**: Actions/Cache.php with comprehensive WordPress action-based clearing and granular methods
- **Filter-Based AI Directive System**: Auto-registering directive classes with hierarchical application (global → agent → type-specific)
- **Intelligent Tool Discovery**: ToolExecutor with handler matching, enablement validation, and configuration checks
- **Universal Engine Architecture**: Shared AI infrastructure (AIConversationLoop, RequestBuilder, ToolExecutor, ToolParameters, ConversationManager)
- **AutoSave System**:
  - Complete pipeline persistence and flow synchronization
- **Modular WordPress Publisher**:
  - (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`) with configuration hierarchy
- **Universal Handler Settings**:
  - Template system with metadata-based auth detection (`requires_auth` flag)
  - Eliminates auth provider instantiation overhead
- **Performance Optimizations**:
  - Handler settings modal load: 50% query reduction (single flow config query, metadata-based auth check)
  - Handler settings save: 50% query reduction (memory-based config building)
  - Status system: Unified REST endpoint (`GET /datamachine/v1/status`) serving flow and pipeline requests via query batching

  - Composer-managed ai-http-client dependency
- **REST API Integration**:
  - **16 Endpoints**: Core (Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users) + Chat (base, conversations)
  - **Chat API**: Conversational interface with session management for multi-turn natural language workflow building
  - **Ephemeral Workflow Support**: Execute workflows without database persistence
  - **Unified Execute Endpoint**: Supports database flows, ephemeral workflows, immediate/delayed/recurring execution
  - **Complete Authentication**: WordPress application password or cookie authentication
  - **React Frontend Integration**: Zero AJAX dependencies, complete REST API consumption

See `CLAUDE.md` for complete technical specifications.

## License

GPL v2+ - [License](https://www.gnu.org/licenses/gpl-2.0.html)  
**Developer**: [Chris Huber](https://chubes.net)  
**Documentation**: `CLAUDE.md`