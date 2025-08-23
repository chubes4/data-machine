# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI Architecture (OpenAI, Anthropic, Google, Grok, OpenRouter), Agentic Tool Calling, Visual Pipeline Builder, Google Search + Local Search Tools, Enhanced Social Publishing, Filter-Based Self-Registration, Centralized OAuth System, Settings Administration, Universal Files Repository, Engine Mode (Headless)

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

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
2. Run `composer install` (installs dev dependencies including Action Scheduler)
3. Activate plugin in WordPress admin
4. Navigate to WordPress Settings → Data Machine
5. Configure AI provider (OpenAI/Anthropic/Google/Grok/OpenRouter)

**Production Installation**:
1. Run `./build.sh` to create production zip with optimized autoloader
2. Install `build/data-machine-v{version}.zip` via WordPress admin
3. Navigate to WordPress Settings → Data Machine
4. Configure AI provider and optional tools

### Tool Configuration (Optional)

**Google Search Setup**:
1. Create Custom Search Engine at [Google Custom Search](https://cse.google.com/)
2. Get API key from [Google Cloud Console](https://console.cloud.google.com/)
   - Enable "Custom Search JSON API"
   - Create credentials (API Key)
3. Navigate to **WordPress Settings → Data Machine → Tool Configuration**
4. Enter API Key and Search Engine ID
   - **Free tier**: 100 queries/day
   - **Local Search**: Always available (no setup needed)

**OAuth Authentication**:
- **Twitter**: OAuth 1.0a (consumer_key/consumer_secret)
- **Reddit**: OAuth2 (client_id/client_secret)
- **Facebook**: OAuth2 (app_id/app_secret)
- **Threads**: OAuth2 (same app as Facebook)
- **Google Sheets**: OAuth2 (client_id/client_secret)
- **Bluesky**: App Password (username/app_password)

Authentication handled via `/dm-oauth/{provider}/` URLs with popup flow.

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
AI steps discover both handler tools (next step) and general tools (Google Search, Local Search) for research-enhanced publishing with fact-checking and context gathering.

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
$flow_id = apply_filters('dm_create_flow', null, ['pipeline_id' => $pipeline_id, 'flow_name' => 'My Flow']);
do_action('dm_run_flow_now', $flow_id, 'manual');

// AI integration with tool-first architecture
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4',
    'tools' => apply_filters('ai_tools', [], $handler_slug, $handler_config)
], 'openrouter');

// Service discovery
$handlers = apply_filters('dm_handlers', []);
$databases = apply_filters('dm_db', []);
$auth_account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');

// Settings administration
$settings = apply_filters('dm_get_data_machine_settings', []);
$enabled_pages = apply_filters('dm_get_enabled_admin_pages', []);
$enabled_tools = apply_filters('dm_get_enabled_general_tools', []);

// Tool configuration management
$configured = apply_filters('dm_tool_configured', false, 'google_search');
$config = apply_filters('dm_get_tool_config', [], 'google_search');
do_action('dm_save_tool_config', 'google_search', ['api_key' => '...', 'search_engine_id' => '...']);
```

### Extension Development

Create a `MyHandlerFilters.php` file following the self-registration pattern:

```php
<?php
namespace DataMachine\Core\Steps\Publish\Handlers\MyHandler;

function dm_register_my_handler_filters() {
    // Handler registration
    add_filter('dm_handlers', function($handlers) {
        $handlers['my_handler'] = [
            'type' => 'publish',
            'class' => MyHandler::class,
            'label' => __('My Handler', 'data-machine'),
            'description' => __('Publish to my custom platform', 'data-machine')
        ];
        return $handlers;
    });
    
    // Authentication registration
    add_filter('dm_auth_providers', function($providers) {
        $providers['my_handler'] = new MyHandlerAuth();
        return $providers;
    });
    
    // Settings registration
    add_filter('dm_handler_settings', function($all_settings) {
        $all_settings['my_handler'] = new MyHandlerSettings();
        return $all_settings;
    });
    
    // Tool registration (handler-specific)
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'my_handler') {
            $tools['my_handler_publish'] = [
                'class' => 'DataMachine\\Core\\Handlers\\Publish\\MyHandler\\MyHandler',
                'method' => 'handle_tool_call',
                'handler' => 'my_handler',
                'description' => 'Publish to my platform',
                'parameters' => [
                    'content' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Content to publish'
                    ]
                ],
                'handler_config' => $handler_config
            ];
        }
        return $tools;
    }, 10, 3);
}

// Auto-register when file loads
dm_register_my_handler_filters();
```

Then add the file to `composer.json` "files" array for automatic loading.

## Handlers

**Fetch**: Files, RSS, Reddit, WordPress (specific post IDs + query filtering), Google Sheets
**Publish**: Twitter (280 chars), Bluesky (300 chars), Threads (500 chars), Facebook, WordPress, Google Sheets
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)
**General Tools**: Google Search, Local Search

**Enhanced Features**: 
- **Twitter**: URL replies, media upload, t.co link handling
- **Bluesky**: Media upload, AT Protocol integration
- **Threads**: Media upload
- **Facebook**: Comment mode, link handling
- **WordPress**: Taxonomy assignment
- **Google Sheets**: Row insertion


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

**Settings** (WordPress Settings → Data Machine):
- **Admin Interface Control**: Engine Mode (headless deployment), individual page controls, general tool controls
- **Global System Prompt**: Consistent AI behavior across all pipelines
- **Tool Configuration**: Modal-based setup for Google Search and other tools requiring API keys
- **WordPress Global Defaults**: Site-wide settings for post types, taxonomies, author, and post status

**Features**: Drag & drop reordering with auto-save, visual status indicators, modal-based tool configuration, real-time log monitoring

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

**PSR-4 Architecture**: Uses proper case directory structure (`inc/Core/`, `inc/Engine/`) with automatic filter registration via composer.json

**Debug**: WordPress debug mode recommended for development

## License

**GPL v2+** - [License](https://www.gnu.org/licenses/gpl-2.0.html)

**Developer**: [Chris Huber](https://chubes.net)
**Documentation**: `CLAUDE.md`