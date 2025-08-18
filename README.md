# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), Agentic Tool Calling, Visual Pipeline Builder, Sequential Processing, Content Publishing (Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets), Filter Architecture, Centralized OAuth System

## Architecture

**Pipeline+Flow**: Pipelines are reusable step templates, Flows are configured handler instances

**Example**: RSS → AI Analysis → Publish to Twitter
- **Pipeline**: Template with 3 steps
- **Flow A**: TechCrunch RSS + GPT-4 + Twitter
- **Flow B**: Gaming RSS + Claude + Facebook

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Example: RSS to Twitter Bot
Create an automated content pipeline in 5 minutes:

1. **Create Pipeline**: "Tech News Bot"
2. **Add Fetch Step**: RSS handler → `https://techcrunch.com/feed/`
3. **Add AI Step**: OpenAI → "Summarize this article in one engaging tweet"
4. **Add Publish Step**: Twitter handler (with URL reply option)
5. **Schedule Flow**: Every 2 hours
6. **Activate**: Your bot starts posting automatically

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

> **Note**: AI steps only discover tools for the **immediate next step**. Multiple consecutive publish steps will execute without AI guidance after the first one. For multi-platform publishing, use alternating AI→Publish→AI→Publish patterns or separate flows for each destination.

### Agentic Tool Calling
```php
// AI automatically discovers and uses publisher capabilities
Pipeline: "Smart Publishing"
├── Fetch: RSS (news feed)
└── AI: Claude + Tools ("Analyze content and publish to appropriate platforms")
    → AI discovers: wordpress_publish, twitter_publish, facebook_publish tools
    → AI executes: Creates WordPress post with categories/tags, tweets with URL reply, posts to Facebook with comment link
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

### Filter Integration
```php
// Pipeline execution and management
do_action('dm_run_flow_now', $flow_id, 'manual');
do_action('dm_create', 'pipeline', ['pipeline_name' => 'My Pipeline']);
do_action('dm_create', 'flow', ['flow_name' => 'My Flow', 'pipeline_id' => $id]);

// Data access
$pipelines = apply_filters('dm_get_pipelines', [], $pipeline_id);
$flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
$is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'rss', $item_id);

// AI integration with tool calling
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');

$available_tools = apply_filters('ai_tools', []);

// OAuth management
$auth_account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
$oauth_url = apply_filters('dm_get_oauth_url', '', 'twitter');

// Service discovery
$handlers = apply_filters('dm_handlers', []);
$steps = apply_filters('dm_steps', []);
$databases = apply_filters('dm_db', []);
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
        'class' => 'MyPublisher',
        'label' => 'My Publisher',
        'description' => 'Publish to custom platform'
    ];
    return $handlers;
});

// Register AI tool for agentic publishing
add_filter('ai_tools', function($tools) {
    $tools['my_publish'] = [
        'class' => 'MyPublisher',
        'method' => 'handle_tool_call',
        'handler' => 'my_publisher',
        'description' => 'Publish content to my platform',
        'parameters' => [
            'content' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => false]
        ]
    ];
    return $tools;
});
```

## Handlers

**Fetch**: Files, RSS, Reddit, WordPress, Google Sheets
**Publish**: Facebook (with comment mode), Threads, Twitter (with URL replies), WordPress (with taxonomies), Bluesky, Google Sheets  
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

### Authentication
- **OAuth2**: Reddit, Facebook, Threads, Google Sheets
- **OAuth 1.0a**: Twitter
- **App Password**: Bluesky
- **API Keys**: AI providers
- **None**: Files, RSS, WordPress

**OAuth System**: Public `/dm-oauth/{provider}/` URLs for external API callbacks with popup window authentication flow

### Enhanced Publishing Features

**WordPress Publishing**:
- Automatic taxonomy assignment (categories, tags, custom taxonomies)
- Gutenberg block creation from structured content
- Post date handling from source content
- Media integration with metadata

**Twitter Publishing**:
- Smart character limit handling (280 chars)
- URL reply functionality (post URLs as separate reply tweets)
- Image upload with alt text support
- Rate limit optimization

**Facebook Publishing**:
- Multiple link handling modes: `append`, `replace`, `comment`, `none`
- Comment mode posts URLs as Facebook comments
- Media upload support
- Page and profile posting

**Agentic Tool Calling**:
- AI models automatically discover available publisher tools
- Dynamic tool parameter generation based on handler configuration
- Context-aware publishing with platform-specific optimizations
- Error handling with graceful fallbacks

## Use Cases

- **Content Marketing**: Auto-post across social platforms with platform-specific optimizations
- **News Monitoring**: Track trends and generate alerts with source attribution
- **Document Processing**: Extract and structure data from files
- **Social Media Management**: Automated posting with URL handling and media support
- **Content Repurposing**: Transform content for different platforms with taxonomy assignment
- **Research Automation**: Collect and analyze data sources with structured output
- **Workflow Integration**: Connect WordPress with external services using native patterns

## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Setup**:
```bash
composer install && composer test
```

**Debug**: `window.dmDebugMode = true;` (browser), `define('WP_DEBUG', true);` (PHP)

## License

**GPL v2+** - [License](https://www.gnu.org/licenses/gpl-2.0.html)

**Developer**: [Chris Huber](https://chubes.net)
**Documentation**: `CLAUDE.md`