# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI, Visual Pipeline Builder, Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), Dynamic AI Directives, Site Context Integration, Three-Layer Tool Management, Social Publishing, OAuth System, Headless Mode

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

## Architecture

**Pipeline+Flow**: Pipelines are reusable templates, Flows are configured instances

**Example**: Automated Tech News Twitter Feed
- **Pipeline Template**: Fetch → AI → Twitter with system prompt "You are a tech news curator. Extract key insights and create engaging tweets that highlight innovation and industry impact. Maintain a professional but accessible tone."
- **Flow A** (Independent Agent): TechCrunch + Hacker News RSS → AI agent instance → user message "Focus on AI/ML breakthroughs and venture funding" → Twitter (every 2 hours)
- **Flow B** (Independent Agent): Reddit r/technology + GitHub trending → AI agent instance → user message "Focus on open-source projects and developer tools" → Twitter (every 4 hours)
- **Flow C** (Independent Agent): VentureBeat + Product Hunt → AI agent instance → user message "Focus on startup launches and product innovations" → Twitter (every 6 hours)

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

**Google Search Console** (optional):
1. Create OAuth2 app with Google Console API access
2. Add credentials at Settings → Data Machine → Tool Configuration
3. Provides SEO analysis, keyword opportunities, internal linking suggestions

**OAuth Providers**:
- Twitter: OAuth 1.0a
- Reddit/Facebook/Threads/Google Sheets/Google Search Console: OAuth2
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
- **Dual-Layer Persistence**: Pipeline-level system prompts + flow-level user messages
- **Three-Layer Tools**: Global settings → per-step selection → configuration validation  
- **Tool Categories**: Handler tools (next step) + general tools (Google Search, Local Search, Google Search Console)
- **Standalone Execution**: AI steps can run independently using flow-specific user messages

**Advanced Examples**:

**Automated Health News WordPress Site**:
- **Pipeline Template**: Fetch → AI → WordPress with system prompt "You are a medical content specialist. Create accurate, well-researched articles with proper citations. Use clear, accessible language while maintaining scientific rigor."
- **Flow A** (Independent Agent): PubMed cardiology research + American Heart Association → AI agent instance → user message "Focus on cardiovascular breakthroughs and heart disease prevention" → WordPress (daily)
- **Flow B** (Independent Agent): FDA RSS + CDC health alerts → AI agent instance → user message "Focus on regulatory updates and public health emergencies" → WordPress (twice daily)  
- **Flow C** (Independent Agent): Nutrition journals + Mayo Clinic blog → AI agent instance → user message "Focus on evidence-based diet and lifestyle interventions" → WordPress (weekly)

**Multi-Platform Content Distribution**:
- **Pipeline Template**: Fetch → AI → Publish with system prompt "Transform technical content into platform-appropriate formats while preserving key insights and maintaining brand voice."
- **Flow A** (Independent Agent): RSS tech blogs → AI agent instance → user message "Create professional Twitter threads for developers" → Twitter (daily)
- **Flow B** (Independent Agent): Reddit r/programming discussions → AI agent instance → user message "Create engaging Facebook posts highlighting trends" → Facebook (twice daily)  
- **Flow C** (Independent Agent): WordPress tech sites → AI agent instance → user message "Create detailed analysis articles" → WordPress (weekly)

**Media Content Automation**:
- **Pipeline Template**: Fetch → AI → Publish with system prompt "You are a social media content curator. Create engaging posts that highlight visual content with compelling descriptions."
- **Flow A** (Independent Agent): WordPress Media (recent uploads) → AI agent instance → user message "Create Instagram-style posts with hashtags" → Twitter (daily)
- **Flow B** (Independent Agent): WordPress Media (product images) → AI agent instance → user message "Generate product showcases with features" → Facebook (twice weekly)

**Research Intelligence System**:
- **Pipeline Template**: Fetch → AI → Analysis with system prompt "You are a research analyst. Identify trends, synthesize insights, and flag significant developments across multiple data sources."
- **Flow A** (Independent Agent): Google Sheets industry data → AI agent instance → user message "Analyze competitive intelligence metrics" → Google Sheets (daily)
- **Flow B** (Independent Agent): Reddit discussions + RSS feeds → AI agent instance → user message "Track brand mentions and sentiment" → WordPress (brand monitoring posts)
- **Flow C** (Independent Agent): Files (PDF reports) → AI agent instance → user message "Extract key innovation insights" → WordPress (weekly research summaries)

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
$auth_account = apply_filters('dm_retrieve_oauth_account', [], 'twitter');
$settings = dm_get_data_machine_settings();

// Tool management
$configured = apply_filters('dm_tool_configured', false, 'google_search');
do_action('dm_save_tool_config', 'google_search', $config_data);
$tools = apply_filters('ai_tools', []);
$enabled_tools = dm_get_enabled_general_tools();

// AI step configuration
do_action('dm_update_system_prompt', $pipeline_step_id, $system_prompt);
do_action('dm_update_flow_user_message', $flow_step_id, $user_message);
$step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
```

### Extension Development

Complete extension system with LLM-powered development:
- **Types**: Fetch, Publish, Update handlers, AI tools, Admin pages
- **Discovery**: Filter-based auto-registration  
- **Templates**: `/extensions/` directory with LLM prompts (development builds)

*See `CLAUDE.md` for complete technical specifications*

## Available Handlers

**Fetch Sources**: Local/remote files, RSS feeds, Reddit posts, WordPress content, WordPress media, Google Sheets  
**Publish Destinations**: Twitter, Bluesky, Threads, Facebook, WordPress, Google Sheets  
**Update Handlers**: WordPress content updates (title, content, meta, taxonomy)  
**AI Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)  
**General Tools**: Google Search, Local WordPress Search, Google Search Console

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