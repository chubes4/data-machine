# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI Architecture (OpenAI, Anthropic, Google, Grok, OpenRouter), Agentic Tool Calling, Visual Pipeline Builder, Google Search + Local Search Tools, Enhanced Social Publishing, Filter-Based Self-Registration, Centralized OAuth System, Settings Administration, Universal Files Repository, Engine Mode (Headless)

## Architecture

**Pipeline+Flow**: Pipelines are reusable step templates, Flows are configured handler instances

**Example**: RSS → AI Analysis → Publish to Twitter
- **Pipeline**: Template with 3 steps
- **Flow A**: TechCrunch RSS + GPT-4 + Twitter
- **Flow B**: Gaming RSS + Claude + Facebook

## Quick Start

### Installation

**Development Installation**:
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install` (installs dev dependencies)
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

**Production Installation**:
1. Run `./build.sh` to create production zip
2. Install `build/data-machine-v{version}.zip` via WordPress admin
3. Configure AI provider in Data Machine → Settings

### Google Search Setup (Optional)
1. Create Custom Search Engine at [Google Custom Search](https://cse.google.com/)
2. Get API key from [Google Cloud Console](https://console.cloud.google.com/) → APIs & Services → Credentials
3. Configure via **WordPress Settings → Data Machine → Tools**
   - **Requirements**: Google Custom Search Engine ID + API key  
   - **Tool Configuration**: Centralized via `dm_tool_configured` filter system
   - **Status Check**: `apply_filters('dm_tool_configured', false, 'google_search')`
   - **API Limits**: 100 queries/day (free tier), expandable with billing
   - **Local Search**: Always available (no configuration needed)

### Example: RSS to Twitter Bot
Create an automated content pipeline in 5 minutes:

1. **Create Pipeline**: "Tech News Bot"
2. **Add Fetch Step**: RSS handler → `https://techcrunch.com/feed/`
3. **Add AI Step**: OpenAI → "Summarize this article in one engaging tweet"
4. **Add Publish Step**: Twitter handler (with URL reply option)
5. **Schedule Flow**: Every 2 hours
6. **Monitor**: Check Data Machine → Logs for execution details
7. **Activate**: Your bot starts posting automatically

## Examples

### Content Automation
```php
// RSS → AI → Single Publisher (Recommended Pattern)
Pipeline: "Twitter Content Bot"
├── Fetch: RSS (TechCrunch)
├── AI: GPT-4 ("Create engaging Twitter content")
└── Publish: Twitter (280 chars + URL reply)

// Multi-Platform: Use Separate Flows or AI→Publish→AI→Publish Pattern
Pipeline: "Multi-Platform Content" (Advanced)
├── Fetch: RSS (TechCrunch)
├── AI: GPT-4 ("Analyze and prepare content")
├── Publish: Twitter (AI-guided)
├── AI: GPT-4 ("Create Facebook version")
└── Publish: Facebook (AI-guided)
```

> **Note**: AI steps discover **handler tools for the immediate next step** only. Multiple consecutive publish steps will execute without handler-specific AI guidance after the first one. For multi-platform publishing, use alternating AI→Publish→AI→Publish patterns or separate flows for each destination. General tools are available to all AI steps for enhanced capabilities.

### Enhanced AI Capabilities
AI steps discover tools for smarter publishing and research:

```php
Pipeline: "Research-Enhanced Publishing"
├── Fetch: RSS (news feed)  
├── AI: Claude ("Research topic and create optimized post")
│    → Tools: Google Search, Local Search + platform-specific publishing tools
└── Publish: Twitter/Facebook/etc. (AI-guided optimization)
```

### Reddit Monitor
```php
// Monitor subreddit → AI analysis → Slack notification
Pipeline: "Trend Detector"
├── Fetch: Reddit (/r/programming)
├── AI: Claude ("Identify trending topics")
└── Publish: Google Sheets (log trends)
```

### Content Transformation
```php
// WordPress posts → AI rewrite → Bluesky
Pipeline: "Content Repurposer"
├── Fetch: WordPress (your blog posts)
├── AI: Grok ("Convert to casual social media post")
└── Publish: Bluesky
```

### File Processing
```php
// PDF documents → AI extraction → Database
Pipeline: "Document Processor"
├── Fetch: Files (/uploads/docs/*.pdf)
├── AI: GPT-4 ("Extract key information")
└── Publish: Google Sheets (structured data)
```

## Programmatic Usage

### Core Operations
```php
// Pipeline management (filter-based creation)
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'My Pipeline']);
$step_id = apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
do_action('dm_run_flow_now', $flow_id, 'manual');

// AI integration  
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');

// Service discovery
$handlers = apply_filters('dm_handlers', []);
$auth_account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');

// Settings administration
$settings = apply_filters('dm_get_data_machine_settings', []);
$enabled_tools = apply_filters('dm_get_enabled_general_tools', []);

// Tool configuration management
$configured = apply_filters('dm_tool_configured', false, 'google_search');
$config = apply_filters('dm_get_tool_config', [], 'google_search');
do_action('dm_save_tool_config', 'google_search', ['api_key' => '...', 'search_engine_id' => '...']);
```

### Extension Development
```php
// Custom fetch handler
add_filter('dm_handlers', function($handlers) {
    $handlers['my_api'] = [
        'type' => 'fetch',
        'class' => 'MyAPIHandler',
        'label' => 'My API',
        'description' => 'Fetch data from custom API'
    ];
    return $handlers;
});

// Custom publish handler with AI tool integration
add_filter('dm_handlers', function($handlers) {
    $handlers['my_publisher'] = [
        'type' => 'publish',
        'class' => 'DataMachine\Core\Steps\Publish\Handlers\MyPublisher\MyPublisher',
        'label' => 'My Publisher',
        'description' => 'Publish to custom platform'
    ];
    return $handlers;
});

// Custom AI tool for enhanced publishing capabilities
add_filter('ai_tools', function($tools) {
    $tools['my_publish'] = [
        'class' => 'MyPublisher',
        'method' => 'handle_tool_call',
        'handler' => 'my_publisher',  // Available when next step = my_publisher
        'description' => 'Publish content with platform optimization',
        'parameters' => [
            'content' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => false]
        ]
    ];
    return $tools;
});
```

## Handlers

**Fetch**: Files, RSS, Reddit, WordPress (specific post IDs + query filtering), Google Sheets (OAuth2: Reddit, Google Sheets)
**Publish**: Twitter (280 chars), Bluesky (300 chars), Threads (500 chars), Facebook, WordPress, Google Sheets
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Enhanced Features**: URL replies (Twitter), comment mode (Facebook), taxonomy assignment (WordPress), media upload (Twitter/Bluesky/Threads)

### Configuration Options

**Platform-Specific Settings**: 
- **Twitter**: `twitter_url_as_reply` (separate reply tweets), `twitter_enable_images`
- **Facebook**: `link_handling` modes (append/replace/comment/none) 
- **WordPress**: Dynamic taxonomy assignment (category/tags/custom taxonomies)

**AI Tools**: Handler-specific tools (next step only) + General tools (all AI steps) with configuration-aware features

**Available Tools**: Google Search (web research), Local Search (site content), custom handler tools

## Use Cases

- **Content Marketing**: Auto-post across social platforms with platform-specific optimizations
- **News Monitoring**: Track trends and generate alerts with source attribution
- **Document Processing**: Extract and structure data from files
- **Social Media Management**: Automated posting with URL handling and media support
- **Content Repurposing**: Transform content for different platforms with taxonomy assignment
- **Research Automation**: Collect and analyze data sources with structured output
- **Workflow Integration**: Connect WordPress with external services using native patterns

## Administration

**Plugin Interface**: Data Machine provides a comprehensive admin interface:

1. **Pipelines**: Create and manage reusable workflow templates with drag & drop reordering and auto-save
2. **Flows**: Configure pipeline instances with handlers and scheduling  
3. **Jobs**: Monitor execution status, clear failed/completed jobs
4. **Logs**: Real-time log viewing with 100-entry display and file rotation
5. **Settings**: Engine mode, admin page control, tool management, global system prompt, AI provider configuration, WordPress-specific settings (post types, taxonomies, author defaults)

**Features**: Drag & drop reordering with debounced auto-save (750ms), visual status indicators, modal-based tool configuration with form validation, real-time logs monitoring

## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Development Setup**:
```bash
composer install
```

**Production Build**:
```bash
./build.sh  # Creates optimized zip for WordPress installation
```

**PSR-4 Architecture**: Uses lowercase directory structure (`inc/core/`, `inc/engine/`) with automatic filter registration via composer.json

**Debug**: `window.dmDebugMode = true;` (browser), `define('WP_DEBUG', true);` (PHP)

## License

**GPL v2+** - [License](https://www.gnu.org/licenses/gpl-2.0.html)

**Developer**: [Chris Huber](https://chubes.net)
**Documentation**: `CLAUDE.md`