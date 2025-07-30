# Data Machine

Transform WordPress into a **Universal Content Processing Platform** with AI-powered workflows and visual pipeline construction. Built with pure WordPress filter architecture for maximum extensibility.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Core Capabilities

- **🎨 Visual Pipeline Builder**: Drag-and-drop workflow construction with real-time configuration
- **🔄 Multi-Input Context Collection**: Gather data from RSS, Reddit, Google Sheets, APIs simultaneously
- **🤖 Multi-AI Model Workflows**: Chain different AI providers (GPT-4 → Claude → Gemini) in single pipelines
- **📧 Custom Step Types**: Email automation, custom processing, agentic content workflows
- **🌐 Bidirectional Handlers**: Google Sheets, WordPress as both input and output destinations
- **🔌 100% Filter-Based**: Pure WordPress architecture with zero constructor dependencies
- **⚡ Future-Ready**: Built for agentic AI workflows and WordSurf integration

## Real-World Example: Comprehensive Content Workflow

```
RSS Feed Input        →  AI Analysis (GPT-4)     →  Content Enhancement  →  Remote WordPress
     ↓                        ↓                         ↓                      ↓
Reddit Posts          →  AI Summary (Claude)     →  Custom Validation   →  Email Notification
     ↓                        ↓                         ↓                      ↓
Google Sheets Data    →  Context Collection      →  Agentic Updates     →  Success Tracking
```

**Context Collection Power**: Each AI step receives ALL previous inputs and processing results, enabling sophisticated cross-referencing and analysis across multiple data sources.

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

## Architecture: Pure WordPress Filter System

Data Machine uses 100% WordPress filters for service access and extensibility:

```php
// Core service access
$logger = apply_filters('dm_get_logger', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);

// Database services with parameters
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');

// Handler system returns instantiated objects
$input_handlers = apply_filters('dm_get_handlers', null, 'input');
$output_handlers = apply_filters('dm_get_handlers', null, 'output');
```

## Key Features

### Multi-Input Context Collection
Collect data from multiple sources simultaneously - each AI step receives ALL previous inputs:
- **RSS feeds** + **Reddit posts** + **Google Sheets data** = Rich context for analysis
- **Cross-reference capabilities** across different data sources
- **Intelligent deduplication** and content correlation

### Multi-AI Model Workflows
Chain different AI providers in single pipelines:
- **GPT-4** for initial analysis → **Claude** for summary → **Custom AI** for final polish
- **Step-specific models**: Use the best AI for each task
- **Context preservation**: Each step builds on previous AI analysis

### Custom Step Types
Extend beyond input/output with specialized processing:
- **Email Steps**: AWS SES automation, campaign management
- **Custom Processing**: Sentiment analysis, data validation, content enhancement
- **Agentic Workflows**: Future WordSurf integration for intelligent content updates

### Bidirectional Handlers
Handlers that work as both input and output:
- **Google Sheets**: Read data for processing, write results back
- **WordPress**: Source content from posts, publish processed content
- **Database Systems**: Query for inputs, store processed results

## Practical Examples

### Example 1: Email Campaign Automation

**Workflow**: Contact List → Content Analysis → Personalized Email

```php
// Custom Email Step Type
add_filter('dm_get_steps', function($steps) {
    $steps['aws_ses_email'] = new \MyPlugin\Steps\AWSEmailStep();
    return $steps;
});

class AWSEmailStep {
    public function execute(int $job_id, ?\DataMachine\Engine\DataPacket $data_packet = null): bool {
        $logger = apply_filters('dm_get_logger', null);
        
        // Access all previous context for personalization
        $context = apply_filters('dm_get_context', null);
        $all_data = $context['all_previous_packets'] ?? [];
        
        // Send personalized email using AWS SES
        return $this->send_personalized_email($data_packet, $all_data);
    }
}
```

### Example 2: Multi-AI Content Analysis Pipeline

**Workflow**: RSS + Reddit → GPT-4 Analysis → Claude Summary → Google Sheets Output

```php
// Multi-input context collection automatically available
// Each AI step receives ALL previous inputs and AI analysis

// Pipeline Configuration:
// Step 1: RSS Input (tech news)
// Step 2: Reddit Input (r/technology posts) 
// Step 3: GPT-4 Analysis (trend identification)
// Step 4: Claude Summary (executive summary)
// Step 5: Google Sheets Output (tracking sheet)

// Each AI step has access to:
$context = apply_filters('dm_get_context', null);
$rss_data = $context['all_previous_packets'][0];     // RSS content
$reddit_data = $context['all_previous_packets'][1];   // Reddit posts
$gpt4_analysis = $context['all_previous_packets'][2]; // GPT-4 insights
```

### Example 3: Bidirectional Google Sheets Handler

**Use Case**: Read customer data, process with AI, write results back

```php
// Bidirectional handler - works as both input and output
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input' || $type === 'output') {
        $handlers['google_sheets'] = new \MyPlugin\Handlers\GoogleSheetsHandler();
    }
    return $handlers;
}, 10, 2);

class GoogleSheetsHandler {
    // INPUT: Read data from sheets
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        $logger = apply_filters('dm_get_logger', null);
        
        // Read customer data from Google Sheets
        $customer_data = $this->fetch_sheets_data(
            $source_config['sheet_id'], 
            $source_config['input_range']
        );
        
        return ['processed_items' => $customer_data];
    }
    
    // OUTPUT: Write processed results back
    public function execute(int $job_id, ?\DataMachine\Engine\DataPacket $data_packet = null): bool {
        if (!$data_packet) return false;
        
        // Access context for complete analysis history
        $context = apply_filters('dm_get_context', null);
        $ai_analysis = $context['all_previous_packets'] ?? [];
        
        // Write AI analysis results back to sheets
        return $this->update_sheets_data(
            $data_packet->metadata['sheet_id'],
            $data_packet->metadata['output_range'], 
            $ai_analysis
        );
    }
}
```

### Example 4: Future WordSurf Integration

**Agentic Content Workflows**: RSS/Reddit → AI Analysis → Intelligent Content Updates

```php
// Future integration pattern for agentic content workflows
add_filter('dm_get_steps', function($steps) {
    $steps['wordsurf_agentic'] = new \WordSurf\Integration\AgenticContentStep();
    return $steps;
});

class AgenticContentStep {
    public function execute(int $job_id, ?\DataMachine\Engine\DataPacket $data_packet = null): bool {
        // Access complete pipeline context
        $context = apply_filters('dm_get_context', null);
        $rss_data = $context['all_previous_packets'][0] ?? null;
        $reddit_data = $context['all_previous_packets'][1] ?? null;
        $ai_analysis = $context['all_previous_packets'][2] ?? null;
        
        // Agentic decision making:
        // - Should we update existing content?
        // - Create new content?
        // - Merge insights from multiple sources?
        
        return $this->make_intelligent_content_decisions([
            'sources' => [$rss_data, $reddit_data],
            'analysis' => $ai_analysis,
            'current_content' => $data_packet
        ]);
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
// Register custom pipeline step
add_filter('dm_get_steps', function($steps) {
    $steps['custom_processing'] = new \MyPlugin\Steps\CustomProcessingStep();
    return $steps;
});

class CustomProcessingStep {
    public function execute(int $job_id, ?\DataMachine\Engine\DataPacket $data_packet = null): bool {
        // Access all services via filters
        $logger = apply_filters('dm_get_logger', null);
        $ai_client = apply_filters('dm_get_ai_http_client', null);
        
        // Access complete pipeline context
        $context = apply_filters('dm_get_context', null);
        $all_previous_data = $context['all_previous_packets'] ?? [];
        
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
// Different AI models per pipeline step
// Step 1: GPT-4 for complex analysis
// Step 2: Claude for creative writing  
// Step 3: Gemini for multilingual content

// Each AI step receives complete context:
$context = apply_filters('dm_get_context', null);
$previous_ai_responses = $context['all_previous_packets'];
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

*Data Machine: Transforming WordPress into a Universal Content Processing Platform with AI-powered workflows and visual pipeline construction.*