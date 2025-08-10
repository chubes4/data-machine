# Data Machine

WordPress plugin for AI content processing workflows. Built with WordPress-native patterns, supports multiple AI providers through visual pipeline builder.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- **Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter support
- **Visual Pipeline Builder**: AJAX-driven workflow construction with modal system
- **Context Processing**: Multi-source data collection and processing with dynamic step discovery
- **Sequential Workflows**: Chain different AI models and providers with real-time configuration
- **Content Publishing**: Distribute to Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets
- **WordPress Integration**: Native WordPress patterns and admin interface
- **Filter Architecture**: Extensible system using WordPress filters
- **Modular Design**: Clean separation of concerns with organized template architecture

## Real-World Example: Pipeline+Flow Architecture

**Pipeline Template** (Reusable Workflow Definition):
```
Pipeline: "Multi-Source Content Processing"
Step 1: Fetch (RSS Feed Handler)     → Fetches RSS content
Step 2: Fetch (Reddit Handler)       → Adds Reddit posts to context  
Step 3: AI (Analysis)                → Analyzes ALL previous inputs
Step 4: AI (Summary)                 → Creates summary with full context
Step 5: Publish (Social Media)       → Publishes enhanced content
```

**Flow Instances** (Configured Executions of Pipeline):
```
Flow A: Tech News Processing (Daily)
├── RSS: TechCrunch handler
├── Reddit: r/technology handler  
├── AI: [pipeline-level config: GPT-4 analysis prompt]
├── AI: [pipeline-level config: Claude creative prompt]
└── Publish: Twitter handler

Flow B: Gaming Content (Weekly)
├── RSS: Gaming news handler
├── Reddit: r/gaming handler
├── AI: [same pipeline config: GPT-4 analysis prompt]
├── AI: [same pipeline config: Claude creative prompt]
└── Publish: Facebook handler

Flow C: Manual Content (On-demand)
├── RSS: Custom feed handler
├── Reddit: Custom subreddit handler
├── AI: [same pipeline config: GPT-4 analysis prompt]
├── AI: [same pipeline config: Claude creative prompt]
└── Publish: Multiple platforms handler
```

**Two-Layer Architecture**: 
- **Pipeline Level**: Step configuration (AI prompts, models, step behavior) - stable across all flows
- **Flow Level**: Handler configuration (which handlers to use, handler settings) - varies per flow
- **AI Steps**: Configured at pipeline level only (no handlers), same prompts/models for all flows
- **Fetch/Publish Steps**: Use handlers configured at flow level (RSS, Twitter, etc.)

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Your First Pipeline+Flow
1. **Create Pipeline Template**: Data Machine → Pipelines → Create New
   - Creates "Draft Flow" instance automatically
   - Click "Add Step" to open step selection modal
   - Select step type from interface (Fetch, AI, Publish, etc.)
   - Choose handler from available options
   - Configure step settings through AJAX interface
2. **Configure Your Draft Flow**: Customize the flow instance
   - RSS Feed URL: Choose source through handler settings form
   - AI Model: Select GPT-4, Claude, etc. from available providers
   - WordPress: Select target blog/site
   - Schedule: Set timing (daily, weekly, manual) with Action Scheduler
3. **Test & Deploy**: Run flow and monitor results

## Architecture: Pipeline+Flow System

**Two-Layer Architecture**: Pipelines are reusable templates, Flows are configured instances.

### Pipeline Layer (Templates)
- **Reusable Workflows**: Define step sequences once, use many times
- **Step Definitions**: Specify step types and positions (0-99)
- **No Configuration**: Pure workflow structure without handler specifics
- **Template Library**: Build library of common workflow patterns
- **Auto-Flow Creation**: Each pipeline generates a "Draft Flow" instance

### Flow Layer (Instances)
- **Pipeline Implementation**: Each flow uses a specific pipeline template
- **Handler Configuration**: Configure specific handlers for each step
- **Independent Scheduling**: Each flow has its own timing and triggers
- **User Settings**: Per-flow customization of AI models, accounts, etc.
- **Immediate Availability**: "Draft Flow" created automatically

### Linear Processing Within Each Flow
- **Position-Based Execution**: Steps run in order 0-99 within each flow
- **Context Accumulation**: Each step receives ALL previous step data
- **Sequential Flow**: Step N+1 can access data from steps 0 through N
- **Multi-Fetch Pattern**: Add multiple fetch steps in sequence, not parallel
- **No Parallel Processing**: Steps execute one after another, never simultaneously

### Multiple Workflow Example
```
Pipeline: "Social Media Content"
├── Flow A: r/technology → GPT-4 → Twitter (Daily)
├── Flow B: RSS feeds → Claude → Facebook (Weekly)  
└── Flow C: Manual content → Gemini → Multiple platforms (On-demand)

Pipeline: "Blog Publishing"
├── Flow D: Research sources → AI analysis → WordPress (Weekly)
└── Flow E: RSS aggregation → AI summary → WordPress (Daily)
```

### Uniform Array Processing Example
```php
// ALL steps receive array of DataPackets (most recent first)
public function execute(int $job_id, array $data, array $step_config): array {
    // AI steps process entire array for complete context
    foreach ($data as $packet) {
        $content = $packet->content['body'];
        // Process all packets for complete context
    }
    
    // Most other steps use latest only
    $latest_packet = $data[0] ?? null;
    if ($latest_packet) {
        $content = $latest_packet->content['body'];
        // Process only most recent data
    }
    
    return $data; // Return updated data packet array
}
```

## Architecture: Filter-Based System

Data Machine implements a pure discovery filter architecture enabling AI workflows through WordPress-native patterns. Every component uses collection-based discovery for complete replaceability and extensibility:

```php
// Core services - action hooks for operations
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
// ProcessingOrchestrator is core engine - direct instantiation
$orchestrator = new \DataMachine\Engine\ProcessingOrchestrator();

// AI HTTP Client - direct instantiation (bundled library)
$ai_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);

// Database services - pure discovery with filtering
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;
$db_pipelines = $all_databases['pipelines'] ?? null;
$db_analytics = $all_databases['analytics'] ?? null; // External

// Handler discovery - pure discovery with type filtering
$all_handlers = apply_filters('dm_handlers', []);
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');
$publish_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'publish');
$custom_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'my_custom_type');

// Step system - configuration arrays with implicit behavior
$steps = apply_filters('dm_steps', [], '');
$ai_config = apply_filters('dm_steps', null, 'ai');
```

### Architecture Separation

- **Pure Discovery**: All services accessed via collection-based filters
- **Collection-Based**: Components register in arrays, discovered through filtering
- **Zero Parameters**: No parameter-based filters - always pure discovery patterns
- **Universal Extensibility**: Add services, handlers, steps via collection registration

## Key Features

### Universal Modal System
Filter-based modal architecture:
- **Filter Discovery**: Components register modal content via `dm_modals` filter
- **Template-Based Interface**: Modals identified by template names rather than component IDs
- **Dynamic Step Discovery**: `apply_filters('dm_steps', [])` discovers all step types for UI generation
- **WordPress Security**: Nonce verification, capability checks, input sanitization
- **Component Independence**: Each component registers modal content via *Filters.php files
- **Universal AJAX Handler**: Single handler processes all modal requests with security verification
- **Template Organization**: Clean separation of modal and page templates
- **Extension Pattern**: Custom step types register configuration modals via template names
- **Performance Optimization**: Conditional asset loading and dependency management

### Pipeline Builder System
AJAX-driven interface with modal system integration:
- **Dynamic Step Selection**: Real-time discovery of available step types through filter system
- **Handler Discovery**: Shows available handlers for each step type
- **Modal Integration**: Seamless modal interactions with WordPress-native interface
- **Template Architecture**: Clean separation of modal and page templates with dynamic step cards
- **AJAX Architecture**: Universal routing with dm_ajax_route action hook for streamlined request handling
- **Real-time Validation**: Immediate feedback on handler availability and configuration
- **Filter-Based Content**: Modal content generated via filter system for extensibility
- **Auto-Flow Creation**: New pipelines create "Draft Flow" for execution
- **WordPress Security**: Standard nonce verification, capability checks, and input sanitization

### Pipeline+Flow Architecture
Two-layer system enabling template reuse and independent workflow execution:
- **Pipeline Templates**: Reusable workflow definitions with step sequences
- **Flow Instances**: Configured executions with specific handlers and scheduling
- **Template Library**: Build once, use multiple times with different configurations
- **Independent Scheduling**: Each flow runs on its own timing and triggers

### Multi-Source Context Collection
Collect data from multiple sources sequentially within each flow:
- **Sequential Fetch Steps**: RSS feeds → Reddit posts → WordPress content → Local files
- **Cumulative Context**: Each step builds on previous data for rich analysis
- **Cross-reference capabilities** across different data sources through context accumulation
- **Content correlation** via step-by-step processing

### Multi-AI Model Workflows
Chain different AI providers in sequential pipeline steps:
- **Sequential AI Steps**: Step 1 (GPT-4 analysis) → Step 2 (Claude summary) → Step 3 (Custom AI polish)
- **Step-specific models**: Use the best AI for each sequential processing task
- **Context preservation**: Each AI step receives data from ALL previous steps (input + AI)

### Core Handlers Included

**Fetch Handlers (Gather Data)** - Located in `/inc/core/steps/fetch/handlers/`:
- **Files**: Process local files and uploads with drag-and-drop support
- **Reddit**: Fetch posts from subreddits via Reddit API with OAuth authentication
- **RSS**: Monitor and process RSS feeds with automatic feed validation
- **WordPress**: Source content from WordPress posts/pages with query builder interface
- **Google Sheets**: Read data from Google Sheets spreadsheets with OAuth 2.0 and range selection

**Publish Handlers (Publish Content)** - Located in `/inc/core/steps/publish/handlers/`:
- **Facebook**: Post to Facebook pages/profiles with media attachment support
- **Threads**: Publish to Threads (Meta's Twitter alternative) with automatic formatting
- **Twitter**: Tweet content with media support and thread creation capabilities
- **WordPress**: Create/update WordPress posts/pages with custom field mapping
- **Bluesky**: Publish to Bluesky (AT Protocol) with rich text formatting
- **Google Sheets**: Export data to spreadsheets for business intelligence with OAuth 2.0

**Receiver Step Framework** - Located in `/inc/core/steps/receiver/`:
- **Webhook Reception**: Framework structure for webhook reception (stub implementation)
- **Extension Pattern**: Demonstrates dynamic step discovery and handler integration
- **Development Status**: Framework prepared for future webhook capabilities

**AI Integration**:
- **Multi-Provider AI HTTP Client**: OpenAI, Anthropic, Google, Grok, OpenRouter
- **Features**: Streaming, tool calling, function execution with provider-specific optimizations
- **Dynamic Configuration**: Real-time model selection and parameter adjustment

### Extension Examples

The filter-based architecture supports custom handlers. Common extension patterns:

**Database & Business Intelligence**:
- **Airtable**: Database operations with flexible schema
- **MySQL/PostgreSQL**: Custom database handlers for enterprise data
- **CSV/Excel Import**: Advanced spreadsheet processing beyond Google Sheets

**Communication**:
- **AWS SES**: Email automation and campaigns
- **Slack/Discord**: Team notifications
- **SMS/WhatsApp**: Mobile messaging

**Advanced Processing**:
- **Contact List Management**: CRM integration
- **Image Processing**: Visual content workflows
- **Custom APIs**: Any REST/GraphQL endpoint


## Comprehensive Examples

### 1. Filter-Based Service Usage

**Core Services Discovery**:
```php
// Services use appropriate access patterns - zero constructor injection
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
$ai_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
// ProcessingOrchestrator is core engine - direct instantiation
$orchestrator = new \DataMachine\Engine\ProcessingOrchestrator();

// Pure discovery with filtering
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;
$db_pipelines = $all_databases['pipelines'] ?? null;
$db_flows = $all_databases['flows'] ?? null;

// Handler discovery - pure discovery with filtering
$all_handlers = apply_filters('dm_handlers', []);
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');
$publish_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'publish');
$all_auth = apply_filters('dm_auth_providers', []);
$twitter_auth = $all_auth['twitter'] ?? null;

// Step discovery (dual-mode)
$all_steps = apply_filters('dm_steps', []);              // All step types
$ai_config = apply_filters('dm_steps', null, 'ai');      // Specific type
```

**Simple Credential Storage Pattern**:
```php
// Direct credential storage for custom handlers
class MyCustomHandler {
    public function save_credentials($api_key, $api_secret) {
        // Store credentials directly in WordPress options
        update_option('my_handler_api_key', sanitize_text_field($api_key));
        update_option('my_handler_api_secret', sanitize_text_field($api_secret));
    }
    
    public function get_credentials() {
        // Retrieve stored credentials
        $api_key = get_option('my_handler_api_key', '');
        $api_secret = get_option('my_handler_api_secret', '');
        
        return [
            'api_key' => $api_key,
            'api_secret' => $api_secret
        ];
    }
    
    public function is_configured() {
        $credentials = $this->get_credentials();
        return !empty($credentials['api_key']) && !empty($credentials['api_secret']);
    }
}
```

### 2. Pipeline+Flow Architecture Examples

**Multi-Source News Analysis Pipeline**:
```php
// Pipeline Template: "Comprehensive News Analysis"
// Step 0: RSS Feed Fetch
// Step 1: Reddit Posts Fetch 
// Step 2: WordPress Content Fetch
// Step 3: AI Cross-Reference Analysis
// Step 4: AI Summary Generation
// Step 5: Social Media Publish
// Step 6: WordPress Blog Publish

// Flow A: Daily Tech News (Automated)
$flow_config_a = [
    'schedule' => 'daily',
    'steps' => [
        0 => ['handler' => 'rss', 'config' => ['feed_url' => 'https://techcrunch.com/feed/']],
        1 => ['handler' => 'reddit', 'config' => ['subreddit' => 'technology', 'limit' => 10]],
        2 => ['handler' => 'wordpress', 'config' => ['post_type' => 'post', 'category' => 'tech']],
        3 => ['step_type' => 'ai'], // AI step configured at pipeline level
        4 => ['step_type' => 'ai'], // AI step configured at pipeline level
        5 => ['handler' => 'twitter', 'config' => ['account' => '@tech_insights']],
        6 => ['handler' => 'wordpress', 'config' => ['post_type' => 'post', 'status' => 'publish']]
    ]
];

// Flow B: Weekly Industry Report (Manual)
$flow_config_b = [
    'schedule' => 'manual',
    'steps' => [
        0 => ['handler' => 'rss', 'config' => ['feed_url' => 'https://feeds.feedburner.com/oreilly/radar']],
        1 => ['handler' => 'reddit', 'config' => ['subreddit' => 'programming', 'limit' => 20]],
        2 => ['handler' => 'wordpress', 'config' => ['post_type' => 'case_study']],
        3 => ['step_type' => 'ai'], // AI step configured at pipeline level  
        4 => ['step_type' => 'ai'], // AI step configured at pipeline level
        5 => ['handler' => 'facebook', 'config' => ['page_id' => 'industry_reports']],
        6 => ['handler' => 'google_sheets', 'config' => ['sheet_id' => 'analytics_data']]
    ]
];
```

**E-commerce Product Analysis Pipeline**:
```php
// Pipeline Template: "Product Research & Marketing"
// Step 0: Google Sheets Product Data Fetch
// Step 1: Reddit Market Research Fetch
// Step 2: AI Competitive Analysis
// Step 3: AI Marketing Copy Generation
// Step 4: Multi-Platform Publishing

// Implementation showing DataPacket flow
class ProductAnalysisStep {
    public function execute(int $job_id, array $datas = []): bool {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // AI steps consume all packets for complete context
        foreach ($datas as $index => $packet) {
            $content = $packet->content['body'];
            $source = $packet->metadata['source'] ?? "Step $index";
            
            $logger->debug("Processing packet from: $source");
            
            // Build comprehensive analysis from:
            // - Product specifications (Google Sheets)
            // - Market sentiment (Reddit)
            // - Competitive landscape (Previous AI analysis)
        }
        
        return true;
    }
}
```

### 3. Handler Diversity Examples

**Fetch Handlers - Data Collection**:
```php
// RSS Feed Handler
class RSSContentPipeline {
    public function setup_rss_fetch() {
        return [
            'handler' => 'rss',
            'config' => [
                'feed_url' => 'https://blog.example.com/feed/',
                'max_items' => 5,
                'filter_keywords' => ['AI', 'automation', 'workflow']
            ]
        ];
    }
}

// Reddit Handler with OAuth
class RedditResearchPipeline {
    public function setup_reddit_fetch() {
        return [
            'handler' => 'reddit',
            'config' => [
                'subreddit' => 'MachineLearning',
                'sort' => 'hot',
                'limit' => 15,
                'time_filter' => 'week'
            ]
        ];
    }
}

// Google Sheets Handler
class SheetsDataPipeline {
    public function setup_sheets_fetch() {
        return [
            'handler' => 'google_sheets',
            'config' => [
                'sheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                'range' => 'Class Data!A2:F',
                'include_headers' => true
            ]
        ];
    }
}

// WordPress Content Handler
class WordPressContentPipeline {
    public function setup_wp_fetch() {
        return [
            'handler' => 'wordpress',
            'config' => [
                'post_type' => 'product',
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'featured_product',
                        'value' => 'yes'
                    ]
                ],
                'posts_per_page' => 10
            ]
        ];
    }
}

// File Upload Handler
class FileProcessingPipeline {
    public function setup_file_fetch() {
        return [
            'handler' => 'files',
            'config' => [
                'allowed_types' => ['pdf', 'docx', 'txt'],
                'max_file_size' => '10MB',
                'process_archives' => true
            ]
        ];
    }
}
```

**Publish Handlers - Content Distribution**:
```php
// Social Media Distribution
class SocialMediaPipeline {
    public function setup_twitter_publish() {
        return [
            'handler' => 'twitter',
            'config' => [
                'account' => '@company_updates',
                'include_media' => true,
                'hashtags' => ['#AI', '#automation'],
                'thread_if_long' => true
            ]
        ];
    }
    
    public function setup_facebook_publish() {
        return [
            'handler' => 'facebook',
            'config' => [
                'page_id' => 'your-facebook-page',
                'include_link_preview' => true,
                'target_audience' => 'tech_professionals'
            ]
        ];
    }
    
    public function setup_threads_publish() {
        return [
            'handler' => 'threads',
            'config' => [
                'profile' => '@company_threads',
                'formatting' => 'markdown',
                'include_alt_text' => true
            ]
        ];
    }
    
    public function setup_bluesky_publish() {
        return [
            'handler' => 'bluesky',
            'config' => [
                'handle' => 'company.bsky.social',
                'rich_text' => true,
                'reply_to_mentions' => false
            ]
        ];
    }
}

// Content Management Publish
class ContentManagementPipeline {
    public function setup_wordpress_publish() {
        return [
            'handler' => 'wordpress',
            'config' => [
                'post_type' => 'ai_generated_content',
                'post_status' => 'draft',
                'category' => 'automated-content',
                'custom_fields' => [
                    'ai_model_used' => 'gpt-4',
                    'generation_timestamp' => date('Y-m-d H:i:s')
                ]
            ]
        ];
    }
    
    public function setup_sheets_publish() {
        return [
            'handler' => 'google_sheets',
            'config' => [
                'sheet_id' => 'analytics_tracking_sheet',
                'worksheet' => 'Content Performance',
                'append_mode' => true,
                'include_timestamp' => true
            ]
        ];
    }
}
```

### 4. Advanced Step Types Examples

**Custom Fetch Step**:
```php
class DatabaseFetchStep {
    public function execute(int $job_id, array $datas = []): bool {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // Custom database connection
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}custom_data WHERE status = 'active'"
        );
        
        // Create DataPacket for next step
        $data = [
            'content' => ['body' => json_encode($results), 'title' => 'Database Export'],
            'metadata' => ['source' => 'custom_database', 'record_count' => count($results)],
            'context' => ['job_id' => $job_id, 'step_position' => 0]
        ];
        
        $logger->info("Processed " . count($results) . " database records");
        return true;
    }
}

// Pure discovery step registration
add_filter('dm_steps', function($steps) {
    $steps['database_fetch'] = [
        'label' => __('Database Fetch', 'my-plugin'),
        'description' => __('Read data from custom database tables', 'my-plugin'),
        'class' => '\MyPlugin\Steps\DatabaseFetchStep',
        'type' => 'fetch'
    ];
    return $steps;
});

// Access through pure discovery
$all_steps = apply_filters('dm_steps', []);
$database_step = $all_steps['database_fetch'] ?? null;
```

**Custom AI Processing Step**:
```php
class SentimentAnalysisStep {
    public function execute(int $job_id, array $datas = []): bool {
        $ai_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // AI steps consume all packets for complete context
        $combined_content = '';
        foreach ($datas as $packet) {
            $combined_content .= $packet->content['body'] . "\n\n";
        }
        
        // Custom AI prompt for sentiment analysis
        $response = $ai_client->chat([
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Analyze the sentiment of the following content and provide a detailed breakdown with scores.'
                ],
                [
                    'role' => 'user',
                    'content' => $combined_content
                ]
            ],
            'temperature' => 0.3
        ]);
        
        // Create enhanced DataPacket with sentiment data
        $sentiment_data = [
            'content' => [
                'body' => $response['choices'][0]['message']['content'],
                'title' => 'Sentiment Analysis Results'
            ],
            'metadata' => [
                'source' => 'sentiment_analysis_ai',
                'model_used' => 'gpt-4',
                'analysis_type' => 'sentiment',
                'input_length' => strlen($combined_content)
            ],
            'context' => ['job_id' => $job_id, 'step_position' => 2]
        ];
        
        $logger->debug('Sentiment analysis completed for ' . strlen($combined_content) . ' characters');
        return true;
    }
}
```

**Custom Publish Step**:
```php
class SlackNotificationStep {
    public function execute(int $job_id, array $datas = []): bool {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // Publish steps typically use latest packet
        $latest_packet = $datas[0] ?? null;
        if (!$latest_packet) {
            $logger->error('No data packet available for Slack notification');
            return false;
        }
        
        // Send to Slack webhook
        $webhook_url = get_option('slack_webhook_url');
        $message = [
            'text' => 'Data Machine Pipeline Completed',
            'attachments' => [
                [
                    'color' => 'good',
                    'title' => $latest_packet->content['title'] ?? 'Pipeline Result',
                    'text' => substr($latest_packet->content['body'], 0, 500) . '...',
                    'fields' => [
                        [
                            'title' => 'Job ID',
                            'value' => (string)$job_id,
                            'short' => true
                        ],
                        [
                            'title' => 'Source',
                            'value' => $latest_packet->metadata['source'] ?? 'Unknown',
                            'short' => true
                        ]
                    ]
                ]
            ]
        ];
        
        $response = wp_remote_post($webhook_url, [
            'body' => json_encode($message),
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            $logger->error('Slack notification failed: ' . $response->get_error_message());
            return false;
        }
        
        $logger->info('Slack notification sent successfully');
        return true;
    }
}
```

### 5. Universal Modal System Examples

**Custom Modal Registration**:
```php
// Register custom modals via pure discovery
add_filter('dm_modals', function($modals) {
    $modals['analytics-dashboard'] = [
        'template' => 'modal/analytics-dashboard',
        'title' => __('Analytics Dashboard', 'my-plugin')
    ];
    $modals['bulk-operations'] = [
        'template' => 'modal/bulk-operations', 
        'title' => __('Bulk Operations', 'my-plugin')
    ];
    $modals['advanced-settings'] = [
        'template' => 'modal/advanced-settings',
        'title' => __('Advanced Settings', 'my-plugin')
    ];
    return $modals;
});
```

**Modal Trigger Templates**:
```php
<!-- Analytics Dashboard Modal Trigger -->
<button type="button" class="button button-primary dm-modal-open" 
        data-template="analytics-dashboard"
        data-context='{"pipeline_count":"<?php echo esc_attr($pipeline_count); ?>","success_rate":"<?php echo esc_attr($success_rate); ?>"}'>
    <?php esc_html_e('View Analytics', 'my-plugin'); ?>
</button>

<!-- Bulk Operations Modal Trigger -->
<button type="button" class="button dm-modal-open" 
        data-template="bulk-operations"
        data-context='{"selected_items":[<?php echo esc_attr(implode(',', $selected_ids)); ?>]}'>
    <?php esc_html_e('Bulk Operations', 'my-plugin'); ?>
</button>

<!-- Advanced Settings Modal Trigger -->
<button type="button" class="button button-secondary dm-modal-open" 
        data-template="advanced-settings"
        data-context='{"pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","context":"pipeline_edit"}'>
    <?php esc_html_e('Advanced Settings', 'my-plugin'); ?>
</button>
```

**Modal Content Templates** (`/templates/modal/analytics-dashboard.php`):
```php
<div class="dm-analytics-modal">
    <h3><?php esc_html_e('Pipeline Analytics Dashboard', 'my-plugin'); ?></h3>
    
    <div class="dm-metrics-grid">
        <div class="dm-metric-card">
            <div class="dm-metric-value"><?php echo esc_html($pipeline_count ?? '0'); ?></div>
            <div class="dm-metric-label"><?php esc_html_e('Total Pipelines', 'my-plugin'); ?></div>
        </div>
        
        <div class="dm-metric-card">
            <div class="dm-metric-value"><?php echo esc_html($success_rate ?? '0%'); ?></div>
            <div class="dm-metric-label"><?php esc_html_e('Success Rate', 'my-plugin'); ?></div>
        </div>
        
        <div class="dm-metric-card">
            <div class="dm-metric-value"><?php echo esc_html($avg_processing_time ?? '0s'); ?></div>
            <div class="dm-metric-label"><?php esc_html_e('Avg Processing Time', 'my-plugin'); ?></div>
        </div>
    </div>
    
    <div class="dm-chart-container">
        <canvas id="dm-performance-chart" width="400" height="200"></canvas>
    </div>
    
    <div class="dm-modal-actions">
        <button type="button" class="button button-primary dm-modal-close" 
                data-template="export-analytics"
                data-context='{"export_type":"full","date_range":"30_days"}'>
            <?php esc_html_e('Export Analytics', 'my-plugin'); ?>
        </button>
        
        <button type="button" class="button button-secondary" id="dm-refresh-analytics">
            <?php esc_html_e('Refresh Data', 'my-plugin'); ?>
        </button>
    </div>
</div>
```

### 6. Universal Template System Examples

**Template Registration**:
```php
// Register admin page with template directory
add_filter('dm_admin_pages', function($pages) {
    $pages['my_custom_page'] = [
        'page_title' => __('My Custom Page', 'my-plugin'),
        'menu_title' => __('Custom Page', 'my-plugin'),
        'capability' => 'manage_options',
        'templates' => __DIR__ . '/templates/',  // Template directory registration
        'assets' => [
            'css' => [
                'my-custom-css' => [
                    'file' => plugin_dir_url(__FILE__) . 'assets/css/custom-page.css',
                    'deps' => ['dm-admin-core']
                ]
            ],
            'js' => [
                'my-custom-js' => [
                    'file' => plugin_dir_url(__FILE__) . 'assets/js/custom-page.js',
                    'deps' => ['jquery', 'dm-core-modal']
                ]
            ]
        ]
    ];
    return $pages;
});
```

**Universal Template Rendering**:
```php
// Use templates from any registered admin page
class MyCustomComponent {
    public function render_dashboard() {
        // Template discovery searches all registered admin page template directories
        $dashboard_content = apply_filters('dm_render_template', '', 'page/dashboard', [
            'stats' => $this->get_stats(),
            'recent_items' => $this->get_recent_items(10)
        ]);
        
        $modal_content = apply_filters('dm_render_template', '', 'modal/item-settings', [
            'item_id' => 123,
            'available_options' => $this->get_available_options()
        ]);
        
        return $dashboard_content;
    }
    
    public function render_dynamic_content() {
        // Template rendering with dynamic data
        $items = $this->get_items();
        $template_data = [];
        
        foreach ($items as $item) {
            $template_data[] = apply_filters('dm_render_template', '', 'component/item-card', [
                'item' => $item,
                'context' => 'dashboard',
                'actions' => ['edit', 'delete', 'duplicate']
            ]);
        }
        
        return implode('', $template_data);
    }
}
```

### 7. AJAX Integration & Template Requesting

**JavaScript Template Requesting**:
```javascript
class CustomPageManager {
    constructor() {
        this.ajax_url = ajaxurl;
        this.nonce = dmCustomPage.nonce;
        this.init();
    }
    
    init() {
        // Data-attribute action handlers
        $(document).on('click', '[data-template="add-item-action"]', this.handleAddItem.bind(this));
        $(document).on('click', '[data-template="delete-action"]', this.handleDeleteItem.bind(this));
        $(document).on('click', '[data-template="bulk-action"]', this.handleBulkAction.bind(this));
    }
    
    // Universal template requesting method
    requestTemplate(templateName, templateData) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'dm_pipeline_ajax',
                pipeline_action: 'get_template',
                    template: templateName,
                    template_data: JSON.stringify(templateData),
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        resolve(response.data.html);
                    } else {
                        reject(response.data.message || 'Template request failed');
                    }
                },
                error: (xhr, status, error) => {
                    reject(`AJAX Error: ${error}`);
                }
            });
        });
    }
    
    handleAddItem(e) {
        const $button = $(e.currentTarget);
        const context = $button.data('context') || {};
        
        // First, make AJAX call to add item (returns data only)
        $.ajax({
            url: this.ajax_url,
            method: 'POST',
            data: {
                action: 'dm_add_custom_item',
                item_data: context,
                nonce: this.nonce
            }
        }).then(response => {
            if (response.success) {
                // Then request template with response data
                return this.requestTemplate('component/item-card', {
                    item: response.data.item,
                    context: 'newly_added',
                    is_first_item: $('.dm-items-container .dm-item-card').length === 0
                });
            }
            throw new Error(response.data.message);
        }).then(itemHtml => {
            // Insert rendered template
            $('.dm-items-container').append(itemHtml);
            this.showNotification('Item added successfully', 'success');
        }).catch(error => {
            this.showNotification(`Error: ${error.message}`, 'error');
        });
    }
    
    handleBulkAction(e) {
        const $button = $(e.currentTarget);
        const action = $button.data('action');
        const selectedItems = $('.dm-item-checkbox:checked').map((i, el) => $(el).val()).get();
        
        if (selectedItems.length === 0) {
            this.showNotification('Please select items first', 'warning');
            return;
        }
        
        // Bulk operation AJAX call
        $.ajax({
            url: this.ajax_url,
            method: 'POST',
            data: {
                action: 'dm_bulk_operation',
                operation: action,
                item_ids: selectedItems,
                nonce: this.nonce
            }
        }).then(response => {
            if (response.success) {
                // Request updated template for each affected item
                const templatePromises = response.data.updated_items.map(item => 
                    this.requestTemplate('component/item-card', {
                        item: item,
                        context: 'bulk_updated',
                        highlight: true
                    })
                );
                
                return Promise.all(templatePromises);
            }
            throw new Error(response.data.message);
        }).then(itemHtmlArray => {
            // Replace affected items with updated templates
            itemHtmlArray.forEach((html, index) => {
                const itemId = response.data.updated_items[index].id;
                $(`.dm-item-card[data-item-id="${itemId}"]`).replaceWith(html);
            });
            
            this.showNotification(`Bulk ${action} completed successfully`, 'success');
        }).catch(error => {
            this.showNotification(`Error: ${error.message}`, 'error');
        });
    }
    
    showNotification(message, type) {
        // Request notification template
        this.requestTemplate('component/notification', {
            message: message,
            type: type,
            dismissible: true
        }).then(notificationHtml => {
            $('.dm-notifications-container').append(notificationHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $('.dm-notification:last').fadeOut(() => {
                    $(this).remove();
                });
            }, 5000);
        });
    }
}

// Initialize when DOM ready
$(document).ready(() => {
    if (typeof dmCustomPage !== 'undefined') {
        new CustomPageManager();
    }
});
```

**AJAX Handler Pattern** (Returns data only, never HTML):
```php
class CustomPageAjax {
    public function add_custom_item() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'dm_custom_page_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed', 'my-plugin')]);
        }
        
        // Sanitize input data
        $item_data = json_decode(wp_unslash($_POST['item_data']), true);
        $item_data = array_map('sanitize_text_field', $item_data);
        
        // Process business logic
        $new_item = $this->create_item($item_data);
        
        if ($new_item) {
            // Return structured data only - NO HTML
            wp_send_json_success([
                'item' => $new_item,
                'message' => __('Item created successfully', 'my-plugin')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to create item', 'my-plugin')]);
        }
    }
    
    public function bulk_operation() {
        // Security checks
        if (!wp_verify_nonce($_POST['nonce'], 'dm_custom_page_nonce') || 
            !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed', 'my-plugin')]);
        }
        
        // Sanitize input
        $operation = sanitize_text_field($_POST['operation']);
        $item_ids = array_map('intval', $_POST['item_ids']);
        
        // Perform bulk operation
        $updated_items = [];
        foreach ($item_ids as $item_id) {
            $result = $this->perform_operation($operation, $item_id);
            if ($result) {
                $updated_items[] = $this->get_item($item_id);
            }
        }
        
        // Return data only - JavaScript will request templates
        wp_send_json_success([
            'updated_items' => $updated_items,
            'operation' => $operation,
            'message' => sprintf(
                __('%d items updated with %s operation', 'my-plugin'),
                count($updated_items),
                $operation
            )
        ]);
    }
    
    // Template endpoint (universal across all admin pages)
    public function get_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'dm_template_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $template = sanitize_text_field($_POST['template']);
        $data = json_decode(wp_unslash($_POST['template_data']), true);
        
        // Use universal template rendering system
        $html = apply_filters('dm_render_template', '', $template, $data);
        
        if (!empty($html)) {
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => "Template '{$template}' not found"]);
        }
    }
}
```

## Extension Development

### Adding Custom Handlers

**Configuration Array Registration** (matches core handler pattern):

```php
// Fetch Handler Implementation
class MyFetchHandler {
    public function __construct() {
        // Filter-based service access only
    }
    
    // Required method for fetch handlers
    public function fetch_data(array $step_config): array {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // Process fetch data and return array of data packets
        $datas = [];
        
        // Your custom fetch logic here
        
        return $datas;
    }
}

// Publish Handler Implementation
class MyPublishHandler {
    public function __construct() {
        // Filter-based service access only
    }
    
    // Required method for publish handlers
    public function publish_data(array $data, array $step_config): bool {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        
        // Process publish data
        // Your custom publish logic here
        
        return true;
    }
}

// Pure discovery registration - collection-based
add_filter('dm_handlers', function($handlers) {
    $handlers['my_handler'] = [
        'type' => 'fetch',
        'class' => \MyPlugin\Handlers\MyFetchHandler::class,
        'label' => __('My Handler', 'my-plugin'),
        'description' => __('Custom handler description', 'my-plugin')
    ];
    return $handlers;
});

// Authentication component (optional) - collection-based registration
add_filter('dm_auth_providers', function($providers) {
    $providers['my_handler'] = new \MyPlugin\Handlers\MyHandlerAuth();
    return $providers;
});

// Settings component (optional) - collection-based registration
add_filter('dm_handler_settings', function($settings) {
    $settings['my_handler'] = new \MyPlugin\Handlers\MyHandlerSettings();
    return $settings;
});

// Modal content registration for handler settings - Collection-based pattern
add_filter('dm_modals', function($modals) {
    $modals['my-handler-settings'] = [
        'template' => 'modal/handler-settings-form',
        'title' => __('My Handler Settings', 'my-plugin'),
        'data' => [
            'handler_slug' => 'my_handler',
            'handler_config' => [
                'label' => __('My Handler', 'my-plugin'),
                'description' => __('Custom handler description', 'my-plugin')
            ]
        ]
    ];
    return $modals;
});
```

### Adding Custom Steps

```php
// Register custom pipeline step via pure discovery
add_filter('dm_steps', function($steps) {
    $steps['custom_processing'] = [
        'label' => __('Custom Processing', 'my-plugin'),
        'description' => __('Custom data processing step', 'my-plugin'),
        'class' => '\MyPlugin\Steps\CustomProcessingStep'
    ];
    return $steps;
});

class CustomProcessingStep {
    public function execute(int $job_id, array $data, array $step_config): array {
        // Access all services via filters
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        $ai_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
        
        // ALL steps receive uniform array of DataPackets (most recent first)
        // Steps self-select data processing approach:
        // - Most steps: use data[0] only
        // - AI steps: use entire data array
        
        $latest_packet = $data[0] ?? null;
        if ($latest_packet) {
            $content = $latest_packet->content['body'];
            // Process latest data for most steps
        }
        
        // Your custom processing logic here
        return $data; // Return updated data packet array
    }
}
```

## AI Integration

### Multi-Provider AI Support
- **OpenAI**: GPT-4, GPT-3.5-turbo with function calling
- **Anthropic**: Claude 3.5 Sonnet, Claude 3 Haiku
- **Google**: Gemini Pro, Gemini Flash
- **OpenRouter**: Access to 100+ AI models
- **Custom Providers**: Easy integration via filter system

### Step-Specific AI Configuration
```php
// Sequential AI processing with different models per step
// Step 1: Fetch (RSS Handler) - position 0
// Step 2: AI (GPT-4 Analysis) - position 1 - complex analysis of RSS data
// Step 3: AI (Claude Writing) - position 2 - creative writing using GPT-4 + RSS data
// Step 4: AI (Gemini Translation) - position 3 - multilingual using all previous data
// Step 5: Publish (WordPress Handler) - position 4 - publish using complete context

// At Step 4 (Gemini AI) - uses entire array for multi-model context:
public function execute(int $job_id, array $datas = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($datas as $index => $packet) {
        $step_name = $packet->metadata['step_name'] ?? "Step $index";
        // Process all previous AI outputs for analysis
    }
}
```

### Service Override System
```php
// Override any core service
add_action('dm_log', function($level, $message, $context = []) {
    // Custom logging implementation
    MyCustomLogger::log($level, $message, $context);
}, 10, 3);

// Add custom database service via collection registration
add_filter('dm_db', function($services) {
    $services['analytics'] = new MyPlugin\Database\Analytics();
    return $services;
});
```

## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Setup**:
```bash
composer install && composer dump-autoload

# Run tests
composer test                # Main plugin PHPUnit tests
cd lib/ai-http-client/ && composer test  # AI HTTP Client tests
```

**Debugging**:
```javascript
// Browser console - Enable comprehensive debugging
window.dmDebugMode = true;  // Enable detailed AJAX and modal debugging

// PHP debugging - WordPress constants  
define('WP_DEBUG', true);   // Enable conditional error_log output throughout codebase
define('WP_DEBUG', false);  // Production mode - clean deployment with essential error handling
```

**Logger Configuration**:
```php
// Central logging system - all components use dm_log action
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
do_action('dm_log', 'error', 'Process failed', ['error' => $error_details]);
do_action('dm_log', 'warning', 'Non-critical issue', ['context' => 'data']);

// Runtime logger configuration (3-level system)
$all_databases = apply_filters('dm_db', []);
$logger = new \DataMachine\Engine\Logger(); // Direct access if needed

// Set log level: 'debug' (full), 'error' (problems only), 'none' (disabled)
$logger->set_level('debug');  // Enable full logging for development
$logger->set_level('error');  // Production setting (default)
$logger->set_level('none');   // Disable logging completely

// Log management
$logger->clear_logs();                    // Clear all log files
$logger->cleanup_log_files(10, 30);      // Auto-cleanup: 10MB max, 30 days max
$recent_entries = $logger->get_recent_logs(100); // Get last 100 log entries
```

**Universal Modal System Debugging**:
```javascript
// Monitor modal triggers via data attributes
$(document).on('click', '.dm-modal-open', function(e) {
    console.log('Modal trigger clicked:', {
        template: $(this).data('template'),
        context: $(this).data('context'),
        button: this
    });
});

// Monitor AJAX content loading
$(document).ajaxSuccess(function(event, xhr, settings) {
    if (settings.data && settings.data.includes('action=dm_get_modal_content')) {
        console.log('Modal content loaded via AJAX:', {
            url: settings.url,
            response_length: xhr.responseText.length
        });
    }
});

// Debug modal AJAX failures
$(document).ajaxError(function(event, xhr, settings, error) {
    if (settings.data && settings.data.includes('action=dm_get_modal_content')) {
        console.error('Modal AJAX error:', {
            status: xhr.status,
            error: error,
            response: xhr.responseText
        });
    }
});

// Test modal trigger programmatically
function testModalTrigger(template, context) {
    var testButton = $('<button class="dm-modal-open" data-template="' + template + '" data-context=\'' + JSON.stringify(context) + '\'></button>');
    $('body').append(testButton);
    testButton.trigger('click');
    testButton.remove();
}

// Example: Test step selection modal
testModalTrigger('step-selection', { pipeline_id: 1, debug: true });
```

**Database Schema**:
- **wp_dm_jobs**: job_id, pipeline_id, flow_id, status, flow_config (longtext NULL), error_details (longtext NULL), created_at, started_at, completed_at
- **wp_dm_pipelines**: pipeline_id, pipeline_name, step_configuration (longtext NULL), created_at, updated_at
- **wp_dm_flows**: flow_id, pipeline_id, flow_name, flow_config (longtext NOT NULL), scheduling_config (longtext NOT NULL), created_at, updated_at
- **wp_dm_processed_items**: id, flow_id, source_type, item_identifier, processed_timestamp
- **wp_dm_remote_locations**: location_id, location_name, target_site_url, target_username, password, synced_site_info (JSON), enabled_post_types (JSON), enabled_taxonomies (JSON), last_sync_time, created_at, updated_at

**Monitoring**:
- **Jobs**: Data Machine → Jobs (real-time status updates and logging)
- **Pipelines**: Data Machine → Pipelines (AJAX interface with modal system and step discovery)
- **Scheduler**: WordPress → Tools → Action Scheduler (automated pipeline execution)
- **AJAX Debugging**: Browser network tab shows pipeline builder and modal AJAX calls
- **Modal Debugging**: Console logs show modal content generation and filter discovery
- **Filter Monitoring**: `dm_steps`, `dm_modals`, `dm_handlers` filter calls in debug output
- **Template Architecture**: Modal templates in `/templates/modal/`, page templates in `/templates/page/`
- **Security Verification**: WordPress nonce verification and capability checks in debug mode
- **Performance Metrics**: Asset loading and dependency resolution in browser DevTools

### Code Standards
- **WordPress Filters**: All service access via `apply_filters()`
- **Configuration Arrays**: Handlers registered with configuration arrays containing class, label, description
- **PSR-4 Namespacing**: `DataMachine\Core\`, `DataMachine\Engine\`
- **Filter-Based Dependencies**: Services retrieved via filters with parameter-based discovery
- **WordPress Security**: Native escaping, sanitization, nonce verification, capability checks

## License & Links

**License**: GPL v2+ - [View License](https://www.gnu.org/licenses/gpl-2.0.html)

**Resources**:
- **Documentation**: `CLAUDE.md` for detailed development guidance
- **Issues**: [GitHub Issues](https://github.com/chubes4/data-machine/issues)
- **Developer**: [Chris Huber](https://chubes.net)

---

*Data Machine: WordPress plugin for AI content processing workflows with visual pipeline construction.*