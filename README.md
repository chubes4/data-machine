=== Data Machine ===
Contributors: chubes4
Tags: ai, automation, content, workflow, pipeline
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-first WordPress plugin for content processing workflows with visual pipeline builder and multi-provider AI integration.

## Architecture

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI, Visual Pipeline Builder, Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), 5-Tier AI Directive Priority System, AIStepConversationManager with Turn Tracking, AIStepToolParameters, Clean Content Processing, Centralized Cache Management, Modular WordPress Publish Handler, Universal Handler Settings Template, AutoSave System, Three-Layer Tool Management

**Requirements**: WordPress 6.2+, PHP 8.0+, Composer

**Pipeline+Flow**: Pipelines are reusable templates, Flows are configured instances

**Example**: Automated Tech News Twitter Feed
- **Pipeline Template**: Fetch → AI → Twitter with system prompt "You are a tech news curator. Extract key insights and create engaging tweets that highlight innovation and industry impact. Maintain a professional but accessible tone."
- **Flow A**: TechCrunch RSS → AI agent → user message "Focus on AI/ML breakthroughs and venture funding" → Twitter (every 2 hours)
- **Flow B**: Reddit r/technology → AI agent → user message "Focus on open-source projects and developer tools" → Twitter (every 4 hours)  
- **Flow C**: VentureBeat RSS → AI agent → user message "Focus on startup launches and product innovations" → Twitter (every 6 hours)

## Quick Start

### Installation

**Development**:
1. Clone to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin
4. Configure AI provider at Settings → Data Machine

**Production**:
1. Run `./build.sh` for production zip
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

### Quick Example: RSS to Twitter Bot

1. Create Pipeline: "Tech News Bot"
2. Add Steps: RSS → AI → Twitter
3. Configure: TechCrunch feed + Claude 3.5 Sonnet + Twitter auth
4. Schedule: Every 2 hours
5. Monitor: Data Machine → Logs

## Examples

### Workflow Patterns

**Single Platform**: RSS → AI → Twitter (recommended)
**Multi-Platform**: RSS → AI → Twitter → AI → Facebook  
**Content Updates**: WordPress Local → AI → WordPress Update
**Document Analysis**: Files → AI → WordPress

> **Note**: Multi-platform uses AI→Publish→AI→Publish pattern. Update steps require source_url from engine parameters (stored in database by fetch handlers, injected by Engine.php).

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

**Fetch Sources**: Local/remote files, RSS feeds, Reddit posts, WordPress Local, WordPress Media, WordPress API, Google Sheets  
**Publish Destinations**: Twitter, Bluesky, Threads, Facebook, WordPress, Google Sheets  
**Update Handlers**: WordPress Update (existing post/page modification via source_url from database storage + Engine.php injection)  
**AI Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)  
**General Tools**: Google Search, Local Search, WebFetch (50K character limit), WordPress Post Reader

**Recent Improvements**:
- **AIStepConversationManager**: Multi-turn conversation state management with chronological message ordering, turn tracking, and duplicate prevention for enhanced AI agent workflows
- **Modular WordPress Publish Handler**: Refactored into specialized components - `FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler` with configuration hierarchy (system defaults override handler config)
- **AutoSave System**: Complete pipeline auto-save with flow synchronization, execution_order updates, and cache invalidation via single `dm_auto_save` action
- **Enhanced Cache System**: WordPress action-based cache clearing with granular invalidation and pattern support via Actions/Cache.php
- **Database Storage + Filter Injection Architecture**: Fetch handlers store engine parameters in database via store_engine_data(); Engine.php retrieves and injects via dm_engine_parameters filter - eliminates URL pollution in AI content while maintaining structured access for handlers
- **Universal Handler Settings**: Template system eliminating modal code duplication across handler types with dynamic field rendering

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
- 5-Tier AI Directive Priority System: Plugin Core Directive → Global system prompt → Pipeline prompts → Tool definitions → Site context
- AIStepConversationManager for multi-turn conversation state management with turn tracking and chronological message ordering
- AIStepToolParameters flat parameter architecture with content extraction
- Tool configuration (API keys, OAuth)
- WordPress defaults (post types, taxonomies, author, status)
- Three-layer tool management (global → modal → validation)

**Features**: Drag & drop, auto-save, status indicators, real-time monitoring

## Development

```bash
composer install    # Development setup
./build.sh         # Production build
```

**Architecture**: PSR-4 autoloading, filter-based service discovery, hybrid database storage + filter injection architecture with clean AI data packets and structured engine parameters, centralized cache system via Actions/Cache.php with WordPress action-based clearing, 5-tier AI directive system with auto-registration (PluginCoreDirective, GlobalSystemPromptDirective, PipelineSystemPromptDirective, ToolDefinitionsDirective, SiteContextDirective), AIStepConversationManager for conversation state management with turn tracking, AIStepToolParameters class for unified tool execution, AutoSave system with complete pipeline persistence and flow synchronization, database storage by fetch handlers + Engine.php filter injection system, modular WordPress publish handler (`FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`) with configuration hierarchy, universal handler settings template system eliminating modal code duplication, Composer-managed ai-http-client dependency. See `CLAUDE.md` for complete technical specifications.

## License

GPL v2+ - [License](https://www.gnu.org/licenses/gpl-2.0.html)  
**Developer**: [Chris Huber](https://chubes.net)  
**Documentation**: `CLAUDE.md`