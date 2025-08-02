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

### Universal Modal System
100% filter-based modal architecture with zero hardcoded modal types enabling unlimited extensibility:
- **Pure Filter Discovery**: Any component can register modal content via `dm_get_modal_content` filter without touching core code
- **Template-Based Interface**: Modals identified by template names (e.g., "step-selection", "handler-selection") rather than component IDs
- **Dual-Mode Step Discovery**: `apply_filters('dm_get_steps', [])` discovers all step types dynamically for UI generation
- **Multi-Layer Security**: Nonce verification, capability checks, input sanitization following WordPress standards
- **Component Autonomy**: Each component registers its own modal content generators independently via *Filters.php files
- **Universal AJAX Handler**: Single `ModalAjax.php` processes all modal requests with comprehensive WordPress security
- **Template Organization**: Clean separation of modal and page templates with organized directory structure
- **Extension Pattern**: Custom step types can register configuration modals via appropriate template names
- **Performance Optimized**: Conditional asset loading and priority-based dependency management

### Advanced Pipeline Builder System  
Professional AJAX-driven interface with sophisticated modal system integration:
- **Dynamic Step Selection**: Real-time discovery of available step types through dual-mode filter system
- **Handler Auto-Discovery**: Automatically shows available handlers for each step type with parameter-based filter discovery
- **Professional Modal UX**: Seamless modal interactions with WordPress-native feel using universal modal infrastructure
- **Template Architecture**: Clean separation of modal and page templates in organized directory structure
- **AJAX Backend**: Comprehensive PipelineAjax class with WordPress security verification and dynamic content generation
- **Real-time Validation**: Immediate feedback on handler availability and configuration requirements
- **Filter-Based Content**: All modal content generated via filter system enabling unlimited extensibility without core modifications
- **WordPress Security**: Standard nonce verification, capability checks, and input sanitization

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
- **Google Sheets**: Read data from Google Sheets spreadsheets with OAuth 2.0 and range selection

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

### Universal Modal System Extension Example

The modal architecture allows any plugin to add custom modals without touching core code:

```php
// Register custom modal content via consistent 2-parameter filter pattern
add_filter('dm_get_modal_content', function($content, $template) {
    switch ($template) {
        case 'analytics-dashboard':
            // Access context via WordPress AJAX standard pattern
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            
            return '<div class="dm-analytics-modal">
                <h3>' . __('Analytics Dashboard', 'my-plugin') . '</h3>
                <div class="dm-metrics-grid">
                    <div class="dm-metric">
                        <strong>Total Pipelines:</strong> ' . esc_html($context['pipeline_count'] ?? 0) . '
                    </div>
                    <div class="dm-metric">
                        <strong>Success Rate:</strong> ' . esc_html($context['success_rate'] ?? '0%') . '
                    </div>
                    <div class="dm-metric">
                        <strong>Avg Processing Time:</strong> ' . esc_html($context['avg_time'] ?? '0s') . '
                    </div>
                </div>
                <div class="dm-actions">
                    <button class="button-primary" data-action="export">' . __('Export Data', 'my-plugin') . '</button>
                    <button class="button-secondary" data-action="refresh">' . __('Refresh', 'my-plugin') . '</button>
                </div>
            </div>';
            
        case 'configure-step':
            // Custom step configuration within universal configure-step template
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            $step_type = $context['step_type'] ?? 'unknown';
            
            if ($step_type === 'analytics_processor') {
                return '<div class="dm-step-config">
                    <h4>' . __('Analytics Processor Configuration', 'my-plugin') . '</h4>
                    <form class="dm-analytics-config-form">
                        <div class="dm-form-row">
                            <label>' . __('Data Source:', 'my-plugin') . '
                                <select name="data_source" required>
                                    <option value="">' . __('Select Source...', 'my-plugin') . '</option>
                                    <option value="google_analytics">Google Analytics</option>
                                    <option value="facebook_insights">Facebook Insights</option>
                                    <option value="custom_api">Custom API</option>
                                </select>
                            </label>
                        </div>
                        <div class="dm-form-row">
                            <label>' . __('Metrics to Track:', 'my-plugin') . '
                                <select name="metrics[]" multiple size="4">
                                    <option value="conversions">Conversions</option>
                                    <option value="engagement">Engagement Rate</option>
                                    <option value="retention">User Retention</option>
                                    <option value="revenue">Revenue</option>
                                </select>
                            </label>
                        </div>
                        <div class="dm-form-row">
                            <label>' . __('Report Frequency:', 'my-plugin') . '
                                <select name="frequency">
                                    <option value="hourly">Hourly</option>
                                    <option value="daily" selected>Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </label>
                        </div>
                        <div class="dm-form-actions">
                            <button type="submit" class="button-primary">' . __('Save Configuration', 'my-plugin') . '</button>
                            <button type="button" class="button-secondary dm-test-connection">' . __('Test Connection', 'my-plugin') . '</button>
                        </div>
                    </form>
                </div>';
            }
            break;
            
        case 'export-results':
            // Custom export modal with dynamic options
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            $job_id = $context['job_id'] ?? null;
            $pipeline_name = $context['pipeline_name'] ?? 'Unknown Pipeline';
            
            return '<div class="dm-export-modal">
                <h4>' . sprintf(__('Export Results - %s', 'my-plugin'), esc_html($pipeline_name)) . '</h4>
                <form class="dm-export-form">
                    <input type="hidden" name="job_id" value="' . esc_attr($job_id) . '">
                    <div class="dm-export-options">
                        <label><input type="checkbox" name="include_metadata" checked> ' . __('Include Metadata', 'my-plugin') . '</label>
                        <label><input type="checkbox" name="include_errors"> ' . __('Include Error Logs', 'my-plugin') . '</label>
                        <label><input type="checkbox" name="compress_output" checked> ' . __('Compress Output', 'my-plugin') . '</label>
                    </div>
                    <div class="dm-format-selection">
                        <label>' . __('Export Format:', 'my-plugin') . '
                            <select name="format">
                                <option value="json">JSON</option>
                                <option value="csv">CSV</option>
                                <option value="xml">XML</option>
                            </select>
                        </label>
                    </div>
                    <button type="submit" class="button-primary">' . __('Export Now', 'my-plugin') . '</button>
                </form>
            </div>';
    }
    return $content;
}, 10, 2);

// JavaScript usage - any page can trigger modals with consistent interface
jQuery(document).ready(function($) {
    // Open analytics dashboard modal with comprehensive context
    $('.analytics-button').on('click', function() {
        dmCoreModal.open('analytics-dashboard', {
            pipeline_count: 15,
            success_rate: '94.2%',
            avg_time: '2.3s',
            title: 'Pipeline Analytics Dashboard'
        });
    });
    
    // Open step configuration modal for custom step types
    $('.configure-analytics-step').on('click', function() {
        dmCoreModal.open('configure-step', {
            step_type: 'analytics_processor',
            step_position: 2,
            pipeline_id: $(this).data('pipeline-id'),
            title: 'Configure Analytics Processor'
        });
    });
    
    // Open export modal with job context
    $('.export-results').on('click', function() {
        dmCoreModal.open('export-results', {
            job_id: $(this).data('job-id'),
            pipeline_name: $(this).data('pipeline-name'),
            title: 'Export Pipeline Results'
        });
    });
    
    // Handle modal-specific actions
    $(document).on('click', '[data-action="export"]', function() {
        // Trigger secondary modal for export options
        dmCoreModal.open('export-results', {
            job_id: 'latest',
            pipeline_name: 'Analytics Pipeline'
        });
    });
});
```

**Key Benefits of Universal Modal Architecture**:
- **Zero Core Modifications**: Add unlimited modal types without touching Data Machine code
- **WordPress Standards**: Uses familiar WordPress AJAX and filter patterns with proper security
- **Automatic Discovery**: New modal types appear immediately when registered via filter system
- **Professional UX**: Seamless integration with WordPress admin interface and native styling
- **Extensible Configuration**: Step types can register sophisticated configuration interfaces via template names
- **Template Flexibility**: Support for both universal templates (like 'configure-step') and custom templates
- **Context Preservation**: Rich context passing between modals and JavaScript components
- **WordPress Security**: Standard nonce verification and input sanitization automatically applied

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

**Universal Modal System Debugging**:
```javascript
// Monitor all modal AJAX calls and responses
$(document).on('dm-modal-content-loaded', function(event, title, content) {
    console.log('Modal loaded successfully:', title, content.length + ' characters');
});

// Debug modal failures with detailed error information
$(document).on('dm-modal-error', function(event, error) {
    console.error('Modal error occurred:', error);
    console.log('Error details:', {
        message: error.message,
        template: error.template,
        context: error.context
    });
});

// Check modal system availability and configuration
console.log('Modal system status:', {
    available: typeof window.dmCoreModal !== 'undefined',
    ajax_url: dmCoreModal?.ajax_url,
    nonce: dmCoreModal?.get_modal_content_nonce,
    strings: dmCoreModal?.strings
});

// Monitor filter discovery for step types
console.log('Available step types:', apply_filters('dm_get_steps', []));

// Test modal content generation for specific templates
dmCoreModal.open('step-selection', { pipeline_id: 1, debug: true });
```

**Monitoring**:
- **Jobs**: Data Machine → Jobs (with real-time status updates and comprehensive logging)
- **Pipelines**: Data Machine → Pipelines (AJAX-driven interface with universal modal system and dynamic step discovery)
- **Scheduler**: WordPress → Tools → Action Scheduler (for automated pipeline execution)
- **Database**: `wp_dm_jobs`, `wp_dm_pipelines`, `wp_dm_flows` tables (two-layer Pipeline+Flow architecture)
- **AJAX Debugging**: Browser network tab shows all pipeline builder and modal AJAX calls with security verification
- **Universal Modal Debugging**: Console logs show modal content generation, filter discovery, and template matching
- **Filter Discovery Monitoring**: `dm_get_steps`, `dm_get_modal_content`, `dm_get_handlers` filter calls visible in debug output
- **Template Architecture**: Modal templates in `/templates/modal/`, page templates in `/templates/page/` with organized structure
- **Security Verification**: Standard WordPress nonce verification and capability checks logged in debug mode
- **Performance Metrics**: Asset loading order, dependency resolution, and conditional loading visible in browser DevTools

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