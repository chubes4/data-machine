# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

**Features**: Tool-First AI Architecture (OpenAI, Anthropic, Google, Grok, OpenRouter), Agentic Tool Calling, Visual Pipeline Builder, Sequential Processing, Enhanced Social Publishing (Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets), Filter-Based Discovery, Centralized OAuth System

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

> **Note**: AI steps discover **handler tools for the immediate next step** only. Multiple consecutive publish steps will execute without handler-specific AI guidance after the first one. For multi-platform publishing, use alternating AI→Publish→AI→Publish patterns or separate flows for each destination. General tools system is architecturally ready but no tools are currently implemented.

### Agentic Tool Calling
```php
// AI automatically discovers handler tools for the immediate next step
Pipeline: "Smart Publishing"
├── Fetch: RSS (news feed)
├── AI: Claude + Handler Tools ("Analyze content and create optimized social posts")
│    → Handler tools: wordpress_publish, twitter_publish (next step only)
└── Publish: Multiple platforms (AI-guided with dynamic tool discovery)
    → AI executes: Creates WordPress post with taxonomies, tweets with URL reply, Facebook post with comment mode
    → Platform-specific optimizations: 280 chars (Twitter), 500 chars (Threads), taxonomy assignment (WordPress)

// File processing pipeline
Pipeline: "Document Processor"
├── Fetch: Files (documents)
├── AI: GPT-4 ("Extract insights and analyze content")
└── Publish: Google Sheets (structured analysis)
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

// Dynamic tool generation for configuration-aware tools
$tool = apply_filters('dm_generate_handler_tool', [], 'twitter', $handler_config);

// Tool execution with configuration
$result = $handler->handle_tool_call($parameters, ['handler_config' => $config]);

// Centralized OAuth management  
$auth_account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration management
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);
apply_filters('dm_oauth', false, 'clear_config', 'twitter');

// URL generation for external callbacks
$callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');
$auth_url = apply_filters('dm_get_oauth_auth_url', '', 'twitter');

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

// Register handler tool for agentic publishing (next step only)
add_filter('ai_tools', function($tools) {
    $tools['my_publish'] = [
        'class' => 'MyPublisher',
        'method' => 'handle_tool_call',
        'handler' => 'my_publisher',  // Handler property = next step only
        'description' => 'Publish content to my platform',
        'parameters' => [
            'content' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => false]
        ]
    ];
    return $tools;
});

// General tools system is ready but no tools are currently implemented
// Future general tool registration example:
// add_filter('ai_tools', function($tools) {
//     $tools['my_analysis'] = [
//         'class' => 'MyAnalyzer',
//         'method' => 'handle_tool_call',
//         // NOTE: No 'handler' property = available to all AI steps
//         'description' => 'Analyze data and provide insights',
//         'parameters' => [
//             'data' => ['type' => 'string', 'required' => true],
//             'analysis_type' => ['type' => 'string', 'required' => false]
//         ]
//     ];
//     return $tools;
// });

// Dynamic tool generation with configuration
add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
    if ($handler_slug === 'my_publisher') {
        $tool = [
            'class' => 'MyPublisher',
            'method' => 'handle_tool_call',
            'description' => 'Publish to my platform with dynamic features',
            'parameters' => ['content' => ['type' => 'string', 'required' => true]],
            'handler_config' => $handler_config
        ];
        
        // Add conditional parameters based on configuration
        if ($handler_config['enable_media'] ?? false) {
            $tool['parameters']['image_url'] = ['type' => 'string', 'required' => false];
        }
    }
    return $tool;
}, 10, 3);
```

## Handlers

**Fetch**: Files, RSS, Reddit, WordPress, Google Sheets
**Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets  
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

### Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing |
| RSS | None | Feed parsing, deduplication |
| Reddit | OAuth2 | Subreddit posts, comments |
| Google Sheets | OAuth2 | Spreadsheet data extraction |
| WordPress | None | Post/page content retrieval |

| **Publish** | **Auth** | **Character Limit** | **Enhanced Features** |
|-------------|----------|--------------------|-----------------------|
| Twitter | OAuth 1.0a | 280 chars | URL replies, media upload |
| Bluesky | App Password | 300 chars | Media upload with alt text |
| Threads | OAuth2 | 500 chars | Configurable character limits |
| Facebook | OAuth2 | No limit | Comment mode, multiple link handling |
| WordPress | None | No limit | Taxonomy assignment, post types |
| Google Sheets | OAuth2 | No limit | Row insertion, data logging |

**Centralized OAuth System**: 
- Public `/dm-oauth/{provider}/` URLs for external API callbacks
- Unified `dm_oauth` filter for all authentication operations
- Popup window authentication flow with automatic parent communication
- Configuration and account data separation

### Enhanced Social Publishing Features

**Twitter Publishing**:
```php
// Tool configuration with dynamic parameters
'twitter_include_source' => true,    // Enable URL parameter access
'twitter_enable_images' => true,     // Enable image upload capability  
'twitter_url_as_reply' => false,     // Post URLs as reply tweets (default: inline)

// URL reply mode creates separate reply tweet
return [
    'success' => true,
    'data' => [
        'tweet_id' => $tweet_id,
        'tweet_url' => $tweet_url,
        'reply_tweet_id' => $reply_tweet_id,     // Only when reply mode
        'reply_tweet_url' => $reply_tweet_url,   // Only when reply mode
        'content' => $tweet_text
    ]
];
```

**Facebook Publishing**:
```php
// Link handling modes
'link_handling' => 'append',    // Add URL to post content (default)
'link_handling' => 'replace',   // Replace post content with URL only
'link_handling' => 'comment',   // Post URL as Facebook comment
'link_handling' => 'none',      // No URL inclusion

// Comment mode creates separate Facebook comment
return [
    'success' => true,
    'data' => [
        'post_id' => $post_id,
        'post_url' => $post_url,
        'comment_id' => $comment_id,         // Only when comment mode
        'comment_url' => $comment_url,       // Only when comment mode
        'content' => $post_text
    ]
];
```

**WordPress Publishing**:
```php
// Dynamic taxonomy assignment via AI tool parameters
'parameters' => [
    'title' => ['type' => 'string', 'required' => true],
    'content' => ['type' => 'string', 'required' => true],
    'category' => ['type' => 'string', 'required' => false],
    'tags' => ['type' => 'array', 'required' => false],
    // Custom taxonomies dynamically added based on post type
];

// Result includes taxonomy assignment details
return [
    'success' => true,
    'data' => [
        'post_id' => $post_id,
        'post_url' => $post_url,
        'taxonomy_results' => [
            'category' => ['success' => true, 'category_id' => 5],
            'tags' => ['success' => true, 'tag_count' => 3],
            'custom_taxonomy' => ['success' => true, 'term_count' => 1]
        ]
    ]
];
```

**Tool-First Architecture**:
- **Handler Tools**: Platform-specific publishing capabilities filtered by immediate next step
- **General Tools**: Architecture ready but no tools currently implemented
- Dynamic tool parameter generation based on handler configuration
- Platform-specific character limits enforced (Twitter: 280, Bluesky: 300, Threads: 500)
- Configuration-aware tool definitions via `dm_generate_handler_tool` filter
- Pure tool calling execution with `handle_tool_call()` methods
- Tool detection logic: `handler` property = next step only, no `handler` property = universal (when implemented)

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