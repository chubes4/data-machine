=== Data Machine ===
Contributors: chubes4
Tags: ai, automation, content, workflow, pipeline
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.

## Architecture

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**:
- **Modern React Admin Interface**: Complete Pipelines page rebuild with 6,591 lines of React code using @wordpress/element and @wordpress/components
- **Zero jQuery/AJAX Architecture**: Modern React frontend with REST API integration
- **Tool-First AI**: Enhanced multi-turn conversation management with duplicate detection and temporal context
- **Visual Pipeline Builder**: Real-time updates with 50+ React components, custom hooks, and Context API state management
- **Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter with 5-tier directive system
- **Complete REST API**: 14 endpoints (Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users)
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
- Twitter: OAuth 1.0a
- Reddit/Facebook/Threads/Google Sheets: OAuth2
- Bluesky: App Password

Auth via `/datamachine-auth/{provider}/` popup flow.

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
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-5-mini'
], 'openai');
```

### REST API

Data Machine provides comprehensive REST API access via 14 endpoint files (Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users) for flow execution, pipeline management, and system monitoring.

**Unified Execute Endpoint** (`POST /datamachine/v1/execute`):

```bash
# Database Flow - Immediate
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123}'

# Database Flow - Recurring
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 123, "interval": "hourly"}'

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

**Implementation**: 14 endpoint files in `inc/Api/` directory (Auth.php, Execute.php, Files.php, Flows.php, Handlers.php, Jobs.php, Logs.php, Pipelines.php, ProcessedItems.php, Providers.php, Settings.php, StepTypes.php, Tools.php, Users.php) with automatic REST route registration

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

**General Tools**:
- Google Search, Local Search
- WebFetch (50K character limit)
- WordPress Post Reader

**Architecture Highlights**:
- **Centralized Engine Data**:
  - `datamachine_engine_data` filter provides unified access to source_url, image_url
  - Clean separation between AI data packets and handler engine parameters
- **Universal Handler Filters**:
  - Shared functionality (`datamachine_timeframe_limit`, `datamachine_keyword_search_match`, `datamachine_data_packet`)
  - Eliminates code duplication across multiple handlers
- **Tool-First AI Integration**:
  - Multi-turn conversation management with `AIStepConversationManager`
  - Unified parameter building via `AIStepToolParameters`
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

## Administration

**Pages**: Pipelines (React), Jobs, Logs

**Settings** (WordPress Settings → Data Machine):
- Engine Mode (headless), page controls, tool toggles
- Site Context toggle (WordPress info injection)
- Job data cleanup on failure toggle (debugging)
- File retention settings (1-90 days)
- **5-Tier AI Directive System**: Auto-registering directive classes with priority spacing for comprehensive AI context
- **AIStepConversationManager**: Multi-turn conversation state with turn tracking, chronological ordering, and duplicate detection
- **AIStepToolParameters**: Centralized parameter building with buildForHandlerTool() for unified tool execution
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
- **5-Tier AI Directive System**: Auto-registering directive classes with priority spacing from PluginCoreDirective to SiteContextDirective
- **Intelligent Tool Discovery**: UpdateStep and PublishStep with exact handler matching and partial name matching
- **Advanced Conversation Management**: AIStepConversationManager with turn tracking and AIStepToolParameters for unified execution
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
  - **14 Endpoints**: Auth, Execute, Files, Flows, Handlers, Jobs, Logs, Pipelines, ProcessedItems, Providers, Settings, StepTypes, Tools, Users
  - **Ephemeral Workflow Support**: Execute workflows without database persistence
  - **Unified Execute Endpoint**: Supports database flows, ephemeral workflows, immediate/delayed/recurring execution
  - **Complete Authentication**: WordPress application password or cookie authentication
  - **React Frontend Integration**: Pipelines page with REST API consumption

See `CLAUDE.md` for complete technical specifications.

## License

GPL v2+ - [License](https://www.gnu.org/licenses/gpl-2.0.html)  
**Developer**: [Chris Huber](https://chubes.net)  
**Documentation**: `CLAUDE.md`