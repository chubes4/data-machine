# Data Machine

WordPress plugin for AI content processing workflows. Built with WordPress-native patterns, supports multiple AI providers through visual pipeline builder.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- **🤖 Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter support
- **🎨 Visual Pipeline Builder**: Drag-and-drop workflow construction
- **🧠 Context Processing**: Multi-source data collection and processing
- **🔄 Sequential Workflows**: Chain different AI models and providers
- **📤 Content Publishing**: Distribute to Facebook, Twitter, Threads, WordPress
- **🌐 WordPress Integration**: Uses familiar WordPress patterns and interfaces
- **🔌 Filter Architecture**: Extensible system using WordPress filters
- **🚀 Modular Design**: Clean separation of concerns

## Real-World Example: Core Content Workflow

**Linear Step-by-Step Processing:**
```
Step 1: Input (RSS Feed Handler)     → Fetches latest RSS content
Step 2: Input (Reddit Handler)       → Adds Reddit posts to context  
Step 3: Input (WordPress Handler)    → Includes existing blog posts
Step 4: AI (GPT-4 Analysis)          → Analyzes ALL previous inputs
Step 5: AI (Claude Summary)          → Creates summary with full context
Step 6: Output (Twitter Handler)     → Posts enhanced content
Step 7: Output (Facebook Handler)    → Publishes to Facebook
Step 8: Output (WordPress Handler)   → Creates new blog post
```

**Context Accumulation**: Each step receives ALL previous step data, enabling cross-referencing and analysis across multiple data sources in sequential processing.

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Your First Pipeline
1. **Data Machine → Pipelines → Create New**
2. **Add Input Step**: Choose RSS Feed
3. **Add AI Step**: Configure GPT-4 for content analysis  
4. **Add Output Step**: Select WordPress post creation
5. **Save & Run**: Watch automated content processing

## Pipeline Architecture: Linear Sequential Processing

**CRITICAL UNDERSTANDING**: Data Machine pipelines execute **step-by-step in linear sequence**, not in parallel.

### How Multi-Input Works

**CORRECT Pattern** (Sequential Steps):
```
Step 1: Input (RSS Feed Handler)    → Position 0
Step 2: Input (Reddit Handler)      → Position 1  
Step 3: Input (WordPress Handler)   → Position 2
Step 4: AI (GPT-4 Analysis)         → Position 3
Step 5: Output (Twitter Handler)    → Position 4
```

**INCORRECT Understanding** (This does NOT happen):
```
❌ RSS + Reddit + WordPress → AI → Output  (Parallel - NOT how it works)
```

### Key Linear Processing Features
- **Position-Based Execution**: Steps run in order 0-99
- **Context Accumulation**: Each step receives ALL previous step data
- **Sequential Flow**: Step N+1 can access data from steps 0 through N
- **Multi-Input Pattern**: Add multiple input steps in sequence, not parallel
- **No Parallel Processing**: Steps execute one after another, never simultaneously

### Uniform Array Processing Example
```php
// ALL steps receive array of DataPackets (most recent first)
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps (consume_all_packets: true) - use entire array
    foreach ($data_packets as $packet) {
        $content = $packet->content['body'];
        // Process all packets for complete context
    }
    
    // Most other steps (consume_all_packets: false) - use latest only
    $latest_packet = $data_packets[0] ?? null;
    if ($latest_packet) {
        $content = $latest_packet->content['body'];
        // Process only most recent data
    }
    
    return true;
}
```

## Architecture: Filter-Based System

Data Machine implements a filter-based architecture enabling AI workflows through WordPress-native patterns. Every component is replaceable, extensible, and organized:

```php
// Core services - completely replaceable
$logger = apply_filters('dm_get_logger', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);

// Database services - pure filter discovery (no switch statements)
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
$db_analytics = apply_filters('dm_get_database_service', null, 'analytics'); // External

// Handler system - object-based with auto-linking
$input_handlers = apply_filters('dm_get_handlers', null, 'input');
$output_handlers = apply_filters('dm_get_handlers', null, 'output');
$custom_handlers = apply_filters('dm_get_handlers', null, 'my_custom_type');

// Step system - configuration arrays with implicit behavior
$steps = apply_filters('dm_get_steps', [], '');
$ai_config = apply_filters('dm_get_steps', null, 'ai');
```

### Frontend/Backend Separation

- **Frontend**: Replaceable via filter overrides
- **Backend**: Engine accepts any components following filter contracts
- **Extensions**: Add services, handlers, steps via filters
- **Modularity**: Replace core functionality without touching engine code

## Key Features

### Multi-Source Context Collection
Collect data from multiple sources sequentially - each step receives ALL previous step data:
- **Sequential Input Steps**: RSS feeds → Reddit posts → WordPress content → Local files
- **Cumulative Context**: Each step builds on previous data for rich analysis
- **Cross-reference capabilities** across different data sources through context accumulation
- **Content correlation** via step-by-step processing

### Multi-AI Model Workflows
Chain different AI providers in sequential pipeline steps:
- **Sequential AI Steps**: Step 1 (GPT-4 analysis) → Step 2 (Claude summary) → Step 3 (Custom AI polish)
- **Step-specific models**: Use the best AI for each sequential processing task
- **Context preservation**: Each AI step receives data from ALL previous steps (input + AI)

### Core Handlers Included

**Input Handlers (Gather Data)**:
- **Files**: Process local files and uploads
- **Reddit**: Fetch posts from subreddits via Reddit API
- **RSS**: Monitor and process RSS feeds
- **WordPress**: Source content from WordPress posts/pages

**Output Handlers (Publish Content)**:
- **Facebook**: Post to Facebook pages/profiles
- **Threads**: Publish to Threads (Meta's Twitter alternative)
- **Twitter**: Tweet content with media support
- **WordPress**: Create/update WordPress posts/pages

**AI Integration**:
- **Multi-Provider AI HTTP Client**: OpenAI, Anthropic, Google, Grok, OpenRouter
- **Features**: Streaming, tool calling, function execution

### Extension Examples (Not Included)

The filter-based architecture makes adding custom handlers straightforward. Common extensions:

**Database & Sheets**:
- **Google Sheets**: Read/write spreadsheet data
- **Airtable**: Database operations
- **MySQL/PostgreSQL**: Custom database handlers

**Communication**:
- **AWS SES**: Email automation and campaigns
- **Slack/Discord**: Team notifications
- **SMS/WhatsApp**: Mobile messaging

**Advanced Processing**:
- **Contact List Management**: CRM integration
- **Image Processing**: Visual content workflows
- **Custom APIs**: Any REST/GraphQL endpoint

## Practical Examples

### Example 1: Core Content Processing Pipeline

**Sequential Linear Workflow**: Step 1 → Step 2 → Step 3

```php
// Linear step-by-step processing using core handlers
// Pipeline Configuration:
// Step 1: RSS Input Handler (position 0) - fetches latest posts
// Step 2: AI Step Handler (position 1) - GPT-4 content enhancement  
// Step 3: Twitter Output Handler (position 2) - publishes enhanced content

// Step execution order: 0 → 1 → 2 (position-based sequential processing)
// Context accumulation at each step:

// At Step 2 (AI processing) - uses entire array for context:
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($data_packets as $packet) {
        // Process all previous data for complete context
    }
}

// At Step 3 (Twitter output) - uses latest packet only:
public function execute(int $job_id, array $data_packets = []): bool {
    // Output steps use latest packet (data_packets[0])
    $latest_packet = $data_packets[0] ?? null;
    // Publish AI-enhanced content from Step 2
}
```

### Example 2: Multi-Source Social Media Publishing

**Sequential Multi-Input Workflow**: Multiple Input Steps → AI Processing → Multiple Output Steps

```php
// Linear sequential processing with multiple inputs and outputs
// Sequential Step Configuration (position-based execution 0-99):
// Step 1: Reddit Input Handler (position 0) - fetch r/technology posts
// Step 2: WordPress Input Handler (position 1) - gather existing blog posts  
// Step 3: AI Step Handler (position 2) - Claude content correlation and summary
// Step 4: Facebook Output Handler (position 3) - publish summary to Facebook
// Step 5: Threads Output Handler (position 4) - post alternative summary
// Step 6: Twitter Output Handler (position 5) - publish condensed version

// Context accumulation through sequential execution:

// At Step 3 (AI processing) - uses entire array for multi-source analysis:
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($data_packets as $packet) {
        $source_type = $packet->metadata['source_type'];
        // Analyze all input sources together
    }
}

// At Step 6 (final output) - uses latest packet:
// Note: Most output handlers use latest packet (data_packets[0]) by default
```

### Example 3: Extension - Email Campaign Automation

**Extension Workflow**: Contact List → Content Analysis → Personalized Email

```php
// Extension example - AWS SES Email Handler (not included in core)
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'output') {
        $handlers['aws_ses'] = new \MyPlugin\Handlers\AWSEmailHandler();
    }
    return $handlers;
}, 10, 2);

class AWSEmailHandler {
    public function execute(int $job_id, array $data_packets = []): bool {
        // Output handlers use latest packet (data_packets[0])
        $latest_packet = $data_packets[0] ?? null;
        
        // Send personalized email using latest processed data
        return $this->send_personalized_email($latest_packet);
    }
}
```

### Example 4: Extension - Google Sheets Integration

**Extension Workflow**: Google Sheets Input → AI Processing → Google Sheets Output

```php
// Extension example - Google Sheets Handler (not included in core)
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input' || $type === 'output') {
        $handlers['google_sheets'] = new \MyPlugin\Handlers\GoogleSheetsHandler();
    }
    return $handlers;
}, 10, 2);

class GoogleSheetsHandler {
    // INPUT: Read data from sheets
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        $customer_data = $this->fetch_sheets_data(
            $source_config['sheet_id'], 
            $source_config['input_range']
        );
        return ['processed_items' => $customer_data];
    }
    
    // OUTPUT: Write processed results back
    public function execute(int $job_id, array $data_packets = []): bool {
        // Output handlers use latest packet (data_packets[0])
        $latest_packet = $data_packets[0] ?? null;
        if (!$latest_packet) return false;
        
        return $this->update_sheets_data(
            $latest_packet->metadata['sheet_id'] ?? '',
            $latest_packet->metadata['output_range'] ?? '', 
            $latest_packet->content
        );
    }
}
```

## Extension Development

### Adding Custom Handlers

**Object-Based Registration** (matches core handler pattern):

```php
// Register handler as instantiated object
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input') {
        $handlers['my_handler'] = new \MyPlugin\Handlers\MyHandler();
    }
    return $handlers;
}, 10, 2);

// Authentication component (optional)
add_filter('dm_get_auth', function($auth, $handler_slug) {
    if ($handler_slug === 'my_handler') {
        return new \MyPlugin\Handlers\MyHandlerAuth();
    }
    return $auth;
}, 10, 2);

// Settings component (optional)
add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
    if ($handler_slug === 'my_handler') {
        return new \MyPlugin\Handlers\MyHandlerSettings();
    }
    return $settings;
}, 10, 2);
```

### Adding Custom Steps

```php
// Register custom pipeline step - returns configuration arrays
add_filter('dm_get_steps', function($step_config, $step_type) {
    if ($step_type === 'custom_processing') {
        return [
            'label' => __('Custom Processing', 'my-plugin'),
            'has_handlers' => false,
            'description' => __('Custom data processing step', 'my-plugin'),
            'class' => '\MyPlugin\Steps\CustomProcessingStep'
        ];
    }
    return $step_config;
}, 10, 2);

class CustomProcessingStep {
    public function execute(int $job_id, array $data_packets = []): bool {
        // Access all services via filters
        $logger = apply_filters('dm_get_logger', null);
        $ai_client = apply_filters('dm_get_ai_http_client', null);
        
        // ALL steps receive uniform array of DataPackets (most recent first)
        // Steps self-select based on their consume_all_packets flag:
        // - false (default): use data_packets[0] only
        // - true: use entire data_packets array
        
        $latest_packet = $data_packets[0] ?? null;
        if ($latest_packet) {
            $content = $latest_packet->content['body'];
            // Process latest data for most steps
        }
        
        // Your custom processing logic here
        return true;
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
// Step 1: Input (RSS Handler) - position 0
// Step 2: AI (GPT-4 Analysis) - position 1 - complex analysis of RSS data
// Step 3: AI (Claude Writing) - position 2 - creative writing using GPT-4 + RSS data
// Step 4: AI (Gemini Translation) - position 3 - multilingual using all previous data
// Step 5: Output (WordPress Handler) - position 4 - publish using complete context

// At Step 4 (Gemini AI) - uses entire array for multi-model context:
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($data_packets as $index => $packet) {
        $step_name = $packet->metadata['step_name'] ?? "Step $index";
        // Process all previous AI outputs for analysis
    }
}
```

### Service Override System
```php
// Override any core service
add_filter('dm_get_logger', function($service) {
    return new MyCustomLogger();
}, 20); // Higher priority = override

// Add custom database service
add_filter('dm_get_database_service', function($service, $type) {
    if ($type === 'analytics') {
        return new MyPlugin\Database\Analytics();
    }
    return $service;
}, 10, 2);
```

## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Setup**:
```bash
composer install && composer dump-autoload
cd lib/ai-http-client/ && composer test
```

**Debugging**:
```javascript
// Browser console
window.dmDebugMode = true;
```

**Monitoring**:
- **Jobs**: Data Machine → Jobs
- **Scheduler**: WordPress → Tools → Action Scheduler
- **Database**: `wp_dm_jobs` table

### Code Standards
- **100% WordPress Filters**: All service access via `apply_filters()`
- **Object Registration**: Handlers registered as instantiated objects
- **PSR-4 Namespacing**: `DataMachine\Core\`, `DataMachine\Engine\`
- **Zero Constructor Dependencies**: Services retrieved via filters
- **WordPress Security**: Native escaping and sanitization

## License & Links

**License**: GPL v2+ - [View License](https://www.gnu.org/licenses/gpl-2.0.html)

**Resources**:
- **Documentation**: `CLAUDE.md` for detailed development guidance
- **Issues**: [GitHub Issues](https://github.com/chubes4/data-machine/issues)
- **Developer**: [Chris Huber](https://chubes.net)

---

*Data Machine: WordPress plugin for AI content processing workflows with visual pipeline construction.*