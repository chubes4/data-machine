# Data Machine

A highly extensible WordPress plugin that transforms your site into a Universal Content Processing Platform. Featuring a **pure filter-based dependency architecture** and **intuitive horizontal pipeline builder**, Data Machine provides infinitely extensible content automation with visual workflow construction through WordPress-native patterns.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Overview

Data Machine features an **innovative architecture** that takes WordPress-native patterns to their logical conclusion. With its **95%+ pure filter-based dependency system** and **intuitive visual pipeline builder**, it provides a highly extensible content automation platform built entirely on WordPress's core architectural principles.

### Key Features

- **🎨 Intuitive Pipeline Builder**: Drag-and-drop visual pipeline construction with position-based execution
- **🔄 Pure Filter-Based Architecture**: 95%+ WordPress-native dependency system eliminating constructor injection complexity
- **⚡ Ultra-Direct Service Access**: All 32+ services accessible via `apply_filters('dm_get_service', null)` pattern
- **🎯 Universal Modal Configuration**: All step configuration through contextual modals, eliminating complex navigation
- **🧠 Fluid Context System**: AI steps automatically receive ALL previous pipeline DataPackets for enhanced context
- **🤖 Multi-Model Workflows**: Different AI providers/models per step (GPT-4 → Claude → Gemini chains)
- **🔌 Zero Constructor Dependencies**: Maximum WordPress compatibility with parameter-less constructors
- **📡 Self-Registering Handlers**: "Plugins within plugins" pattern with auto-loading and filter registration
- **🚀 Position-Based Execution**: Linear pipeline execution using user-controlled ordering (0-99 positions)
- **🎯 External Override Capability**: Any service can be overridden by external plugins via filter priority

## Architecture Overview

### Universal Content Processing Platform
Data Machine's **Extensible Pipeline Architecture** transforms WordPress into a universal content processing platform where any workflow can be implemented through pure WordPress hooks and filters.

```
┌─────────────────────────────────────────────────────────────────────┐
│                    EXTENSIBLE PIPELINE SYSTEM                      │
├─────────────────────────────────────────────────────────────────────┤
│  Input Step  →  Process Step  →  Custom Steps  →  Finalize  →  Output │
│      ↓              ↓               ↓              ↓           ↓     │
│  Any Source    Multi-AI       Plugin-Defined    Content    Any Platform│
│  (Filterable)  Processing     Steps (Unlimited)  Polish    (Filterable)│
└─────────────────────────────────────────────────────────────────────┘

EXTENSIBLE VIA WORDPRESS FILTERS:
• dm_register_pipeline_steps    - Add/modify pipeline steps
• dm_register_input_handlers   - Add input handlers
• dm_register_output_handlers  - Add output handlers  
• dm_get_service              - Access/override any service
```

### Filter-Based Service Architecture
Every component uses pure WordPress filters for maximum extensibility:

```php
// Universal service access pattern
$service = apply_filters('dm_get_service', null, 'service_name');

// Pipeline step registration
add_filter('dm_register_pipeline_steps', function($steps) {
    $steps['custom_analysis'] = [
        'class' => 'MyPlugin\CustomStep',
        'next' => 'finalize'
    ];
    return $steps;
});

// Input handler registration  
add_filter('dm_register_input_handlers', function($handlers) {
    $handlers['shopify'] = [
        'class' => 'MyPlugin\\ShopifyHandler',
        'label' => 'Shopify Integration',
        'auth_class' => 'MyPlugin\\ShopifyAuth',
        'settings_class' => 'MyPlugin\\ShopifySettings'
    ];
    return $handlers;
});

// Output handler registration
add_filter('dm_register_output_handlers', function($handlers) {
    $handlers['custom_api'] = [
        'class' => 'MyPlugin\\CustomApiHandler',
        'label' => 'Custom API Output',
        'auth_class' => 'MyPlugin\\CustomApiAuth',
        'settings_class' => 'MyPlugin\\CustomApiSettings'
    ];
    return $handlers;
});
```

### Technical Stack
- **Architecture**: Pure WordPress filter-based dependency system
- **Extensibility**: Universal plugin integration via WordPress hooks
- **Backend**: PHP 8.0+ with PSR-4 namespacing  
- **Service System**: Filter-based service registry (`dm_get_service`)
- **Pipeline**: Dynamic step registration via `dm_register_pipeline_steps`
- **Handlers**: Direct registration with `dm_register_input_handlers` and `dm_register_output_handlers` filters
- **Database**: Custom WordPress tables (`wp_dm_*`) with JSON step data
- **Job Processing**: Dynamic Action Scheduler hooks from pipeline config
- **AI Integration**: Multi-provider library with step-aware configuration
- **Security**: WordPress-native patterns with encrypted credential storage

### Directory Structure
```
data-machine/
├── admin/                         # Admin UI with programmatic forms
├── includes/
│   ├── Contracts/                # Type-safe interfaces
│   ├── engine/                   # Extensible pipeline system
│   │   ├── interfaces/           # Pipeline step contracts
│   │   └── steps/               # Core pipeline step implementations
│   ├── handlers/                # Extensible input/output handlers
│   ├── database/               # WordPress table abstractions
│   ├── helpers/                # Filter-based utility services
│   └── DataMachine.php         # Filter-based service bootstrap
├── lib/ai-http-client/           # Multi-provider AI library
├── assets/                      # Frontend assets
├── vendor/                      # Composer dependencies
└── data-machine.php            # WordPress filter registration
```

## Development Setup

### Prerequisites
- WordPress 5.0+
- PHP 8.0+
- MySQL 5.6+
- Composer
- AI provider API key (OpenAI, Anthropic, etc.)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/chubes4/data-machine.git
   cd data-machine
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **WordPress setup**
   - Place in `/wp-content/plugins/data-machine/`
   - Activate through WordPress admin
   - Configure AI provider settings in Data Machine → API Keys

### Development Commands

```bash
# Install/update dependencies
composer install
composer dump-autoload    # After adding new classes

# No build process - changes take effect immediately
# Database schema recreated on plugin activation/deactivation
```

### Development Workflow

1. Edit handler files (changes are immediate)
2. Run `composer dump-autoload` if you added new files
3. Test via WordPress admin interface
4. Monitor background jobs in WordPress → Tools → Action Scheduler
5. Check job data in `wp_dm_jobs` table

### Debugging

Enable verbose logging in browser console:
```javascript
window.dmDebugMode = true
```

Monitor jobs:
- **WordPress Admin**: Data Machine → Jobs
- **Action Scheduler**: WordPress → Tools → Action Scheduler  
- **Database**: Check `wp_dm_jobs` table for step progression

## Extensible Development

### Universal Plugin Integration
Data Machine uses **pure WordPress patterns** - any plugin can extend functionality without touching core code.

### Adding Custom Pipeline Steps

```php
// Register custom pipeline step via WordPress filter
add_filter('dm_register_pipeline_steps', function($steps) {
    // Insert custom step between process and factcheck
    $steps['sentiment_analysis'] = [
        'class' => 'MyPlugin\SentimentAnalysisStep',
        'next' => 'factcheck'
    ];
    $steps['process']['next'] = 'sentiment_analysis';
    
    return $steps;
}, 10);

// Implement the step
namespace MyPlugin;

use DataMachine\Engine\{Interfaces\PipelineStepInterface, Steps\BasePipelineStep};

class SentimentAnalysisStep extends BasePipelineStep implements PipelineStepInterface {
    public function execute(int $job_id): bool {
        // Access services via pure WordPress filters - no constructor needed
        $logger = apply_filters('dm_get_service', null, 'logger');
        $ai_client = apply_filters('dm_get_service', null, 'ai_http_client');
        
        // Get data from previous step
        $processed_data = $this->get_step_data($job_id, 2);
        
        // Perform sentiment analysis
        $sentiment = $this->analyze_sentiment($processed_data);
        
        // Store result for next step
        return $this->store_step_data($job_id, 'sentiment_data', $sentiment);
    }
}
```

### Adding Custom Input Handlers

```php
// Single registration with integrated auth and settings
add_filter('dm_register_input_handlers', function($handlers) {
    $handlers['shopify_orders'] = [
        'class' => 'MyPlugin\\ShopifyOrdersHandler',
        'label' => 'Shopify Orders',
        'auth_class' => 'MyPlugin\\ShopifyAuth',
        'settings_class' => 'MyPlugin\\ShopifySettings'
    ];
    return $handlers;
});

// Implement the handler
namespace MyPlugin;

use DataMachine\Handlers\Input\BaseInputHandler;

class ShopifyOrdersHandler extends BaseInputHandler {
    // No constructor needed - services accessed via filters
    
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Access services dynamically via WordPress filters
        $logger = apply_filters('dm_get_service', null, 'logger');
        $http_service = apply_filters('dm_get_service', null, 'http_service');
        $processed_items_manager = apply_filters('dm_get_service', null, 'processed_items_manager');
        
        // Fetch orders from Shopify API
        $orders = $this->fetch_shopify_orders($source_config['api_key'], $source_config['store_url']);
        
        // Filter out already processed items
        $filtered_orders = $processed_items_manager->filter_processed_items($orders, $module->id);
        
        return ['processed_items' => $filtered_orders];
    }
    
    // CRITICAL: Settings fields method must be static
    public static function get_settings_fields(array $current_config = []): array {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'Shopify API Key',
                'required' => true
            ],
            'store_url' => [
                'type' => 'url',
                'label' => 'Store URL', 
                'required' => true
            ]
        ];
    }
}
```

### Adding Custom Output Handlers

```php
// Register output handler
add_filter('dm_register_output_handlers', function($handlers) {
    $handlers['discord'] = [
        'class' => 'MyPlugin\\DiscordHandler',
        'label' => 'Discord Webhook',
        'settings_class' => 'MyPlugin\\DiscordSettings'
    ];
    return $handlers;
});

// Implement handler with filter-based service access
namespace MyPlugin;

use DataMachine\Handlers\Output\BaseOutputHandler;

class DiscordHandler extends BaseOutputHandler {
    public function handle_output(array $finalized_data, object $module, int $user_id): array {
        // Access services via filters - maximum extensibility
        $logger = apply_filters('dm_get_service', null, 'logger');
        $http_service = apply_filters('dm_get_service', null, 'http_service');
        
        // Send to Discord webhook
        $response = $http_service->post($module->output_config['webhook_url'], [
            'content' => $finalized_data['content']
        ]);
        
        return [
            'success' => !is_wp_error($response),
            'response' => $response
        ];
    }
    
    public static function get_settings_fields(array $current_config = []): array {
        return [
            'webhook_url' => [
                'type' => 'url',
                'label' => 'Discord Webhook URL',
                'required' => true
            ]
        ];
    }
}
```

## Database Schema

### Core Tables
- **`wp_dm_jobs`**: 5-step pipeline data with JSON fields for each step
- **`wp_dm_modules`**: Handler configurations and settings
- **`wp_dm_projects`**: Project management and scheduling
- **`wp_dm_processed_items`**: Deduplication tracking
- **`wp_dm_remote_locations`**: Encrypted remote WordPress credentials

### Extensible Job Processing Flow

```php
// Job creation via filter-based service
$job_creator = apply_filters('dm_get_service', null, 'job_creator');
$result = $job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);

// Dynamic pipeline execution - hooks generated from registered steps
// Core pipeline (extensible via dm_register_pipeline_steps filter):
// Step 1: dm_input_job_event → input_data
// Step 2: dm_process_job_event → processed_data
// Step 3: dm_factcheck_job_event → fact_checked_data  
// Step 4: dm_finalize_job_event → finalized_data
// Step 5: dm_output_job_event → result_data

// Custom steps automatically integrated:
// Step X: dm_sentiment_analysis_job_event → sentiment_data
// Step Y: dm_custom_validation_job_event → validation_data

// All step data stored in wp_dm_jobs JSON fields
// ProcessingOrchestrator coordinates execution dynamically
```

## Configuration

### Filter-Based Configuration System

**Pure WordPress-Native Service Access**:
```php
// Universal service access pattern throughout the platform
$logger = apply_filters('dm_get_service', null, 'logger');
$db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
$ai_client = apply_filters('dm_get_service', null, 'ai_http_client');
$handler_factory = apply_filters('dm_get_service', null, 'handler_factory');

// Third-party plugins can override any service
add_filter('dm_get_service', function($service, $name) {
    if ($name === 'logger') {
        return new MyCustomLogger(); // Override core logger
    }
    return $service;
}, 15, 2); // Higher priority overrides core services
```

**AI Provider Configuration**:
- **Multi-Provider Support**: OpenAI, Anthropic, Google Gemini, Grok, OpenRouter
- **Step-Aware Configuration**: Different providers/models per pipeline step
- **Filter-Based Access**: AI client accessed via `dm_get_service` filter
- **Plugin Extensibility**: Third-party plugins can add new AI providers

**Dynamic Configuration** via `DataMachine\Constants`:
- Handler registration and discovery
- Pipeline step management
- Service registry patterns
- WordPress-native configuration storage

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow pure WordPress filter patterns for extensibility
4. Use `apply_filters('dm_get_service', null, 'service_name')` for service access
5. Register handlers via `dm_register_input_handlers` and `dm_register_output_handlers` filters (no core modifications)
6. Register pipeline steps via `dm_register_pipeline_steps` filter
7. Implement proper interfaces (`PipelineStepInterface` for steps)
8. Add proper error handling with `WP_Error`
9. Test thoroughly with Action Scheduler and multiple plugins
10. Submit pull request

### Code Standards
- **Pure WordPress Patterns**: Use filters for all service access and extensibility
- **PSR-4 namespacing** with `DataMachine\` root namespace
- **Filter-Based Services**: Access services via `apply_filters('dm_get_service', null, 'service_name')`
- **No Constructor Injection**: Services retrieved dynamically when needed
- **Static Settings Methods**: Handler settings methods must be `public static`
- **WordPress-Native Security**: Use WordPress escaping and sanitization functions
- **Extensible Patterns**: Core and external code use identical registration patterns
- Always add `use` statements for proper PSR-4 imports

## License

GPL v2 or later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

## Links

- **WordPress Plugin Directory**: [Coming Soon]
- **Documentation**: See `CLAUDE.md` for detailed development guidance
- **Issues**: [GitHub Issues](https://github.com/chubes4/data-machine/issues)

---

*For WordPress users looking to install and use this plugin, see the [WordPress.org plugin page] for user-focused documentation and examples.*