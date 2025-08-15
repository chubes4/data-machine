# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Multi-Provider AI (OpenAI, Anthropic, Google, Grok, OpenRouter), Visual Pipeline Builder, Sequential Processing, Content Publishing (Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets), Filter Architecture, Two-Layer Design

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
4. **Add Publish Step**: Twitter handler
5. **Schedule Flow**: Every 2 hours
6. **Activate**: Your bot starts posting automatically

## Examples

### Content Automation
```php
// RSS → AI → Multiple Publishers
Pipeline: "Multi-Platform Content"
├── Fetch: RSS (TechCrunch)
├── AI: GPT-4 ("Create platform-specific content")
├── Publish: Twitter (short version)
├── Publish: Facebook (detailed post)
└── Publish: WordPress (full article)
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
// Custom pipeline execution
do_action('dm_run_flow_now', $flow_id, 'api_trigger');

// Custom data processing
$results = apply_filters('dm_get_pipelines', [], $pipeline_id);

// AI request integration
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]]
], 'openrouter');
```

### Extension Development
```php
// Custom fetch handler
add_filter('dm_handlers', function($handlers) {
    $handlers['my_api'] = [
        'name' => 'My API',
        'steps' => ['fetch'],
        'class' => 'MyAPIHandler'
    ];
    return $handlers;
});

// Custom AI step
add_filter('dm_steps', function($steps) {
    $steps['custom_ai'] = [
        'name' => 'Custom AI Processing',
        'class' => 'CustomAIStep',
        'position' => 50
    ];
    return $steps;
});
```

## Handlers

**Fetch**: Files, RSS, Reddit, WordPress, Google Sheets
**Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets  
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

### Authentication
- **OAuth2**: Reddit, Facebook, Threads, Google Sheets
- **OAuth 1.0a**: Twitter
- **App Password**: Bluesky
- **API Keys**: AI providers
- **None**: Files, RSS, WordPress

## Use Cases

- **Content Marketing**: Auto-post across social platforms
- **News Monitoring**: Track trends and generate alerts
- **Document Processing**: Extract data from files
- **Social Media Management**: Automated posting and engagement
- **Content Repurposing**: Transform content for different platforms
- **Research Automation**: Collect and analyze data sources
- **Workflow Integration**: Connect WordPress with external services

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