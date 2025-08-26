# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI, Visual Pipeline Builder, Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), Dynamic AI Directives, Site Context Integration, Three-Layer Tool Management, Social Publishing, OAuth System, Headless Mode

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

## Architecture

**Pipeline+Flow**: Pipelines are reusable templates, Flows are configured instances

**Example**: RSS → AI → Twitter
- Pipeline: 3-step template
- Flow A: TechCrunch + Claude 3.5 Sonnet + Twitter
- Flow B: Gaming RSS + GPT-4o + Facebook

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

### Content Automation
```
// Single Platform (Recommended)
RSS → AI → Twitter

// Multi-Platform (Advanced)
RSS → AI → Twitter → AI → Facebook
```

> **Note**: AI agents discover handler tools for the immediate next step only. For multi-platform, use AI→Publish→AI→Publish pattern.

**AI Enhancement**: 
- **Dynamic Directives**: Automatic tool-specific prompts
- **Site Context**: WordPress site information injection
- **Three-Layer Tools**: Global settings → per-step selection → configuration validation  
- **Tool Categories**: Handler tools (next step) + general tools (Google Search, Local Search)

**Additional Examples**:
- **Reddit Monitor**: Reddit → AI analysis → Google Sheets
- **Content Repurposer**: WordPress posts → AI rewrite → Bluesky  
- **Content Updater**: WordPress posts → AI enhancement → WordPress update
- **File Processor**: PDF files → AI extraction → Structured data

## Programmatic Usage

```php
// Pipeline creation
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'My Pipeline']);
$step_id = apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('dm_create_flow', null, ['pipeline_id' => $pipeline_id]);
do_action('dm_run_flow_now', $flow_id, 'manual');

// AI integration
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'claude-3-5-sonnet-20241022'
], 'anthropic');

// Service discovery
$handlers = apply_filters('dm_handlers', []);
$auth_providers = apply_filters('dm_auth_providers', []);
$auth_account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
$settings = dm_get_data_machine_settings();

// Tool management
$configured = apply_filters('dm_tool_configured', false, 'google_search');
do_action('dm_save_tool_config', 'google_search', $config_data);
$tools = apply_filters('ai_tools', []);
$enabled_tools = dm_get_enabled_general_tools();
```

### Extension Development

Complete extension system with LLM-powered development:
- **Types**: Fetch, Publish, Update handlers, AI tools, Admin pages
- **Discovery**: Filter-based auto-registration  
- **Templates**: `/extensions/` directory with LLM prompts (development builds)

*See `CLAUDE.md` for complete technical specifications*

## Available Handlers

**Fetch Sources**: Local/remote files, RSS feeds, Reddit posts, WordPress content, Google Sheets  
**Publish Destinations**: Twitter, Bluesky, Threads, Facebook, WordPress, Google Sheets  
**Update Handlers**: WordPress content updates (title, content, meta, taxonomy)  
**AI Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)  
**General Tools**: Google Search, Local WordPress Search

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
- Global system prompt + dynamic AI directives
- Tool configuration (API keys, OAuth)
- WordPress defaults (post types, taxonomies)
- Three-layer tool management (global → modal → validation)

**Features**: Drag & drop, auto-save, status indicators, real-time monitoring

## Development

```bash
composer install    # Development setup
./build.sh         # Production build
```

**Architecture**: See `CLAUDE.md` for complete technical details

## License

GPL v2+ - [License](https://www.gnu.org/licenses/gpl-2.0.html)  
**Developer**: [Chris Huber](https://chubes.net)  
**Documentation**: `CLAUDE.md`