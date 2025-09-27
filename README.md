=== Data Machine ===
Contributors: chubes4
Tags: ai, automation, content, workflow, pipeline
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.

## Architecture

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**:
- Tool-First AI with enhanced multi-turn conversation management and duplicate detection
- Visual Pipeline Builder with drag & drop interface and status detection
- Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter) with 5-tier directive system
- Centralized Engine Data Architecture with unified filter access pattern
- Enhanced Unified Handler Filter System with shared functionality patterns
- AIStepConversationManager with Turn Tracking, temporal context, and validation
- AIStepToolParameters with buildForHandlerTool() and centralized parameter building
- Clean Content Processing with structured data packet architecture
- Modular WordPress Publish Handler with specialized processing components
- Universal Handler Settings Template eliminating code duplication
- Complete AutoSave System with cache invalidation
- Database Query Optimization with centralized access patterns
- Advanced Centralized Cache Management with granular controls and action-based architecture

**Requirements**: WordPress 6.2+, PHP 8.0+, Composer

**Pipeline+Flow**: Pipelines are reusable templates, Flows are configured instances

**Example**: WordPress Content Enhancement System
- **Pipeline Template**: Fetch → AI → Update (defines workflow structure with system prompt "You are a content optimizer. Analyze existing WordPress content and enhance it with better SEO, readability, and comprehensive information using research tools.")
- **Flow A**: WordPress Local (old blog posts) → AI + Google Search tool → WordPress Update (weekly)
- **Flow B**: WordPress Local (draft pages) → AI + Local Search tool → WordPress Update (daily)
- **Flow C**: WordPress Local (product pages) → AI + WebFetch tool → WordPress Update (bi-weekly)

## Quick Start

### Installation

**Development**:
1. Clone to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin
4. Configure AI provider at Settings → Data Machine

**Production**:
1. Run `./build.sh` to create `/dist/data-machine.zip`
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

Auth via `/dm-oauth/{provider}/` popup flow.

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
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'My Pipeline']);
$step_id = apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('dm_create_flow', null, ['pipeline_id' => $pipeline_id]);
do_action('dm_run_flow_now', $flow_id, 'manual');

// AI integration
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-5-mini'
], 'openai');
```

*For complete API documentation, see `CLAUDE.md`*

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
  - `dm_engine_data` filter provides unified access to source_url, image_url
  - Clean separation between AI data packets and handler engine parameters
- **Universal Handler Filters**:
  - Shared functionality (`dm_timeframe_limit`, `dm_keyword_search_match`, `dm_data_packet`)
  - Eliminates code duplication across multiple handlers
- **Tool-First AI Integration**:
  - Multi-turn conversation management with `AIStepConversationManager`
  - Unified parameter building via `AIStepToolParameters`
- **Modular WordPress Publisher**:
  - Specialized components (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`)
  - Configuration hierarchy system
- **Complete AutoSave System**:
  - Single `dm_auto_save` action handles pipeline persistence, flow synchronization, and cache invalidation
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

**Pages**: Pipelines, Flows, Jobs, Logs

**Settings** (WordPress Settings → Data Machine):
- Engine Mode (headless), page controls, tool toggles
- Site Context toggle (WordPress info injection)
- Job data cleanup on failure toggle (debugging)
- File retention settings (1-90 days)
- **Enhanced 5-Tier AI Directive Priority System**:
  - PluginCoreDirective (foundational identity with workflow termination logic) → GlobalSystemPromptDirective → PipelineSystemPromptDirective → ToolDefinitionsDirective → SiteContextDirective
- **Advanced AIStepConversationManager**:
  - Multi-turn conversation state management with turn tracking, chronological message ordering, duplicate detection, and parameter validation
- **Enhanced AIStepToolParameters**:
  - Centralized flat parameter architecture with buildForHandlerTool() and unified tool execution patterns
- Tool configuration (API keys, OAuth)
- WordPress defaults (post types, taxonomies, author, status)
- Three-layer tool management (global → modal → validation)

**Features**: Drag & drop, auto-save, status indicators, real-time monitoring

## Development

```bash
composer install    # Development setup
composer test       # Run tests (PHPUnit configured, test files not yet implemented)
./build.sh          # Production build to /dist/data-machine.zip
```

**Architecture**:
- **PSR-4 Autoloading**: Composer-managed dependency structure
- **Filter-Based Service Discovery**: WordPress hooks for component registration
- **Unified Handler Filter System**:
  - Centralized cross-cutting filters (`dm_timeframe_limit`, `dm_keyword_search_match`, `dm_data_packet`)
- **Enhanced Centralized Engine Data**:
  - `EngineData.php` filter providing unified `dm_engine_data` access with storage/retrieval mode detection
  - Clean AI data packets with structured engine parameters and database separation
- **Advanced Centralized Cache System**:
  - Actions/Cache.php with comprehensive WordPress action-based clearing and database component integration
  - Granular cache methods: `dm_clear_flow_config_cache`, `dm_clear_flow_scheduling_cache`, `dm_clear_flow_steps_cache`
  - Action-based architecture for extensible cache management across components
- **Enhanced 5-Tier AI Directive System**:
  - Auto-registration with priority spacing: PluginCoreDirective (foundational identity) → GlobalSystemPromptDirective → PipelineSystemPromptDirective → ToolDefinitionsDirective → SiteContextDirective
- **Advanced Tool Discovery**:
  - UpdateStep and PublishStep with intelligent tool result detection, exact handler matching, and partial name matching
- **Enhanced Conversation Management**:
  - AIStepConversationManager with turn tracking, temporal context, duplicate detection, and conversation validation
  - AIStepToolParameters with centralized parameter building and unified tool execution patterns
- **AutoSave System**:
  - Complete pipeline persistence and flow synchronization
- **Modular WordPress Publisher**:
  - (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`) with configuration hierarchy
- **Universal Handler Settings**:
  - Template system eliminating modal code duplication
- **Performance Optimizations**:
  - Database query optimization for improved performance
  - Composer-managed ai-http-client dependency

See `CLAUDE.md` for complete technical specifications.

## License

GPL v2+ - [License](https://www.gnu.org/licenses/gpl-2.0.html)  
**Developer**: [Chris Huber](https://chubes.net)  
**Documentation**: `CLAUDE.md`