# Data Machine

WordPress plugin for AI content processing workflows. Built with WordPress-native patterns, supports multiple AI providers through visual pipeline builder.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- **🤖 Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter support
- **🎨 Advanced Pipeline Builder**: AJAX-driven visual workflow construction with professional modal system
- **🧠 Context Processing**: Multi-source data collection and processing with dynamic step discovery
- **🔄 Sequential Workflows**: Chain different AI models and providers with real-time configuration
- **📤 Content Publishing**: Distribute to Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets
- **🌐 WordPress Integration**: Uses familiar WordPress patterns and interfaces with native admin UX
- **🔌 Filter Architecture**: Extensible system using WordPress filters with dynamic content generation
- **🚀 Modular Design**: Clean separation of concerns with organized template architecture

## Real-World Example: Pipeline+Flow Architecture

**Pipeline Template** (Reusable Workflow Definition):
```
Pipeline: "Multi-Source Content Processing"
Step 1: Input (RSS Feed Handler)     → Fetches RSS content
Step 2: Input (Reddit Handler)       → Adds Reddit posts to context  
Step 3: AI (Analysis)                → Analyzes ALL previous inputs
Step 4: AI (Summary)                 → Creates summary with full context
Step 5: Output (Social Media)        → Publishes enhanced content
```

**Flow Instances** (Configured Executions of Pipeline):
```
Flow A: Tech News Processing (Daily)
├── RSS: TechCrunch feed
├── Reddit: r/technology posts
├── AI: GPT-4 analysis
├── AI: Claude creative writing
└── Output: Twitter @tech_account

Flow B: Gaming Content (Weekly)
├── RSS: Gaming news feeds
├── Reddit: r/gaming posts
├── AI: Gemini analysis
├── AI: GPT-4 summary
└── Output: Facebook gaming page

Flow C: Manual Content (On-demand)
├── RSS: Custom feed URLs
├── Reddit: User-selected subreddits
├── AI: User-selected models
├── AI: Custom prompts
└── Output: Multiple platforms
```

**Two-Layer Architecture**: Pipelines define step sequences, Flows configure specific handlers and scheduling for each pipeline instance.

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Your First Pipeline+Flow
1. **Create Pipeline Template**: Data Machine → Pipelines → Create New
   - Click "Add Step" to open the dynamic step selection modal
   - Select step type from the visual interface (Input, AI, Output, etc.)
   - Choose specific handler from automatically discovered options
   - Configure step settings through the AJAX-driven interface
2. **Create Flow Instance**: Configure specific settings
   - RSS Feed URL: Choose your source through the handler settings form
   - AI Model: Select GPT-4, Claude, etc. from available providers
   - WordPress: Select target blog/site with real-time validation
   - Schedule: Set timing (daily, weekly, manual) with Action Scheduler integration
3. **Test & Deploy**: Run flow and monitor results with comprehensive logging

## Architecture: Pipeline+Flow System

**Two-Layer Architecture**: Pipelines are reusable templates, Flows are configured instances.

### Pipeline Layer (Templates)
- **Reusable Workflows**: Define step sequences once, use many times
- **Step Definitions**: Specify step types and positions (0-99)
- **No Configuration**: Pure workflow structure without handler specifics
- **Template Library**: Build library of common workflow patterns

### Flow Layer (Instances)
- **Pipeline Implementation**: Each flow uses a specific pipeline template
- **Handler Configuration**: Configure specific handlers for each step
- **Independent Scheduling**: Each flow has its own timing and triggers
- **User Settings**: Per-flow customization of AI models, accounts, etc.

### Linear Processing Within Each Flow
- **Position-Based Execution**: Steps run in order 0-99 within each flow
- **Context Accumulation**: Each step receives ALL previous step data
- **Sequential Flow**: Step N+1 can access data from steps 0 through N
- **Multi-Input Pattern**: Add multiple input steps in sequence, not parallel
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

### Advanced Pipeline Builder System
Professional AJAX-driven interface with sophisticated modal system integration:
- **Dynamic Step Selection**: Real-time discovery of available step types through filter system
- **Handler Auto-Discovery**: Automatically shows available handlers for each step type
- **Professional Modal UX**: Seamless modal interactions with WordPress-native feel
- **Template Organization**: Clean separation of modal and page templates with organized structure
- **AJAX Backend**: Comprehensive PipelineAjax class with security verification and content generation
- **Real-time Validation**: Immediate feedback on handler availability and configuration requirements

### Pipeline+Flow Architecture
Two-layer system enabling template reuse and independent workflow execution:
- **Pipeline Templates**: Reusable workflow definitions with step sequences
- **Flow Instances**: Configured executions with specific handlers and scheduling
- **Template Library**: Build once, deploy multiple times with different configurations
- **Independent Scheduling**: Each flow runs on its own timing and triggers

### Multi-Source Context Collection
Collect data from multiple sources sequentially within each flow:
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

**Input Handlers (Gather Data)** - Located in `/inc/core/steps/input/handlers/`:
- **Files**: Process local files and uploads with drag-and-drop support
- **Reddit**: Fetch posts from subreddits via Reddit API with OAuth authentication
- **RSS**: Monitor and process RSS feeds with automatic feed validation
- **WordPress**: Source content from WordPress posts/pages with query builder interface

**Output Handlers (Publish Content)** - Located in `/inc/core/steps/output/handlers/`:
- **Facebook**: Post to Facebook pages/profiles with media attachment support
- **Threads**: Publish to Threads (Meta's Twitter alternative) with automatic formatting
- **Twitter**: Tweet content with media support and thread creation capabilities
- **WordPress**: Create/update WordPress posts/pages with custom field mapping
- **Bluesky**: Publish to Bluesky (AT Protocol) with rich text formatting
- **Google Sheets**: Export data to spreadsheets for business intelligence with OAuth 2.0

**Receiver Step Framework** - Located in `/inc/core/steps/receiver/`:
- **Webhook Reception**: Fully integrated stub implementation visible in step selection modal
- **Extension Pattern**: Demonstrates dynamic step discovery and handler integration
- **Coming Soon Status**: Professional presentation indicating future webhook capabilities

**AI Integration**:
- **Multi-Provider AI HTTP Client**: OpenAI, Anthropic, Google, Grok, OpenRouter
- **Features**: Streaming, tool calling, function execution with provider-specific optimizations
- **Dynamic Configuration**: Real-time model selection and parameter adjustment

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

### Example 1: Pipeline+Flow Content Processing

**Pipeline Template**: "RSS to Social Media"
```php
// Pipeline Definition (reusable template):
// Step 1: Input Handler (position 0) - data collection
// Step 2: AI Handler (position 1) - content enhancement  
// Step 3: Output Handler (position 2) - content publishing
```

**Flow Implementations**:
```php
// Flow A: Tech News to Twitter (Daily)
// - RSS: TechCrunch feed
// - AI: GPT-4 analysis
// - Output: @tech_twitter

// Flow B: Gaming News to Facebook (Weekly)
// - RSS: Gaming feeds
// - AI: Claude creative writing
// - Output: Gaming Facebook page

// Step execution within each flow:
// Position-based sequential processing (0 → 1 → 2)
// Context accumulation at each step:

// At Step 2 (AI processing) - uses entire array for context:
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($data_packets as $packet) {
        // Process all previous data for complete context
    }
}

// At Step 3 (Output) - uses latest packet:
public function execute(int $job_id, array $data_packets = []): bool {
    // Output steps use latest packet (data_packets[0])
    $latest_packet = $data_packets[0] ?? null;
    // Publish AI-enhanced content from Step 2
}
```

### Example 2: Multi-Source Content Pipeline+Flows

**Pipeline Template**: "Multi-Source Analysis and Publishing"
```php
// Pipeline Structure (reusable across flows):
// Step 1: Input Handler (position 0) - source data collection
// Step 2: Input Handler (position 1) - additional context  
// Step 3: AI Handler (position 2) - cross-source analysis
// Step 4: Output Handler (position 3) - content distribution
// Step 5: Output Handler (position 4) - secondary distribution
```

**Flow Configurations**:
```php
// Flow A: Tech Analysis (Daily)
// - Input 1: Reddit r/technology
// - Input 2: WordPress tech blog posts
// - AI: Claude correlation analysis
// - Output 1: Facebook tech page
// - Output 2: Twitter @tech_updates

// Flow B: News Aggregation (Hourly)
// - Input 1: RSS news feeds
// - Input 2: Reddit r/worldnews
// - AI: GPT-4 summarization
// - Output 1: Threads news account
// - Output 2: WordPress news blog

// Sequential execution within each flow:
// Context accumulation through position-based processing:

// At Step 3 (AI processing) - uses entire array for multi-source analysis:
public function execute(int $job_id, array $data_packets = []): bool {
    // AI steps consume all packets (most recent first)
    foreach ($data_packets as $packet) {
        $source_type = $packet->metadata['source_type'];
        // Analyze all input sources together
    }
}

// At Steps 4-5 (outputs) - use latest processed packet:
// Most output handlers use latest packet (data_packets[0]) by default
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
window.dmDebugMode = true;  // Enable detailed AJAX and modal debugging
```

**Monitoring**:
- **Jobs**: Data Machine → Jobs (with real-time status updates)
- **Pipelines**: Data Machine → Pipelines (AJAX-driven interface with dynamic content)
- **Scheduler**: WordPress → Tools → Action Scheduler
- **Database**: `wp_dm_jobs`, `wp_dm_pipelines`, `wp_dm_flows` tables
- **AJAX Debugging**: Browser network tab shows all pipeline builder AJAX calls

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