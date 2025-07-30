# Data Machine

A highly extensible WordPress plugin that transforms your site into a Universal Content Processing Platform. Featuring a **pure filter-based dependency architecture** and **intuitive horizontal pipeline builder**, Data Machine provides infinitely extensible content automation with visual workflow construction through WordPress-native patterns.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Overview

Data Machine implements a **100% pure filter-based dependency system** that eliminates traditional constructor injection patterns while maintaining WordPress-native extensibility. The plugin provides a content automation platform with an intuitive visual pipeline builder, built on WordPress's core architectural principles.

### Key Features

- **🎨 Intuitive Pipeline Builder**: Drag-and-drop visual pipeline construction with position-based execution
- **🔄 Filter-Based Architecture**: 100% WordPress-native dependency system
- **⚡ Ultra-Direct Service Access**: All 29 services accessible via specific `dm_get_{service_name}` filter patterns
- **🎯 Universal Modal Configuration**: All step configuration through contextual modals, eliminating complex navigation
- **🧠 Fluid Context System**: AI steps automatically receive ALL previous pipeline DataPackets for enhanced context
- **🤖 Multi-Model Workflows**: Different AI providers/models per step (GPT-4 → Claude → Gemini chains)
- **🔌 Zero Constructor Dependencies**: Maximum WordPress compatibility with parameter-less constructors
- **📡 Self-Registering Handlers**: "Plugins within plugins" pattern with auto-loading and filter registration
- **🚀 Position-Based Execution**: Linear pipeline execution using user-controlled ordering (0-99 positions)
- **🎯 External Override Capability**: Any service can be overridden by external plugins via filter priority

## Architecture Overview

### Content Processing Platform
Data Machine's **Extensible Pipeline Architecture** transforms WordPress into a content processing platform where workflows are implemented through WordPress hooks and filters with 100% filter-based service access.

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
• dm_get_steps                - Access/modify pipeline steps  
• dm_get_{service_name}       - Access/override specific services
```

### Filter-Based Service Architecture
100% of Data Machine core components use WordPress filters for service access. External library boundaries are intentionally maintained:
- External AI HTTP Client library (maintains its own dependency architecture as a separate library)
- PHP built-in classes (ZipArchive, etc.) use direct instantiation patterns
- WordPress core classes use standard WordPress patterns

**Filter-based service access pattern:**

```php
// Filter-based service access pattern (100% platform coverage)
// Each service has its own specific filter for maximum clarity
$logger = apply_filters('dm_get_logger', null);
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$ai_client = apply_filters('dm_get_ai_http_client', null);

// Pipeline step registration
add_filter('dm_get_steps', function($steps) {
    $steps['custom_analysis'] = [
        'class' => 'MyPlugin\\CustomStep',
        'label' => 'Custom Analysis',
        'type' => 'custom'
    ];
    return $steps;
});

// Additional step registration
add_filter('dm_get_steps', function($steps) {
    $steps['shopify'] = [
        'class' => 'MyPlugin\\ShopifyStep',
        'label' => 'Shopify Integration'
    ];
    return $steps;
});
```

### Migration Status: 100% Complete
The plugin has successfully achieved complete migration from traditional dependency injection to a WordPress-native filter-based system:

**✅ Completed (100%)**:
- All core pipeline services use specific filters like `apply_filters('dm_get_logger', null)`
- Handler system fully filter-based with zero constructor dependencies
- Database classes converted to static methods with filter-based access
- Pipeline orchestrator and step execution system
- Admin interface and AJAX handlers migrated
- AI response parsing and utility classes
- Authentication classes with dedicated filters

**Intentional External Library Boundaries**:
- AI HTTP Client library (maintains its own dependency architecture as separate library)
- PHP built-in classes (ZipArchive, etc.) use appropriate direct instantiation
- WordPress core class extensions use standard WordPress patterns

### Technical Stack
- **Architecture**: 100% WordPress filter-based dependency system
- **Extensibility**: Plugin integration via WordPress hooks and filters
- **Backend**: PHP 8.0+ with PSR-4 namespacing  
- **Service System**: Filter-based service registry with specific service filters
- **Pipeline**: Step registration via `dm_get_steps`
- **Database**: Custom WordPress tables (`wp_dm_*`) with JSON step data
- **Job Processing**: Dynamic Action Scheduler hooks from pipeline config
- **AI Integration**: Multi-provider library with step-aware configuration
- **Security**: WordPress-native patterns with encrypted credential storage

### Directory Structure
```
data-machine/
├── assets/                      # Frontend assets (CSS/JS)
├── inc/
│   ├── admin/                   # Admin UI components
│   │   ├── AdminPage.php        # Base admin page class
│   │   ├── AdminMenuAssets.php  # Menu and asset management
│   │   ├── EncryptionHelper.php # Security utilities
│   │   └── Logger.php           # Logging utilities
│   ├── core/
│   │   ├── admin/pages/         # Organized admin page structure
│   │   │   ├── jobs/           # Job management pages
│   │   │   ├── pipelines/      # Pipeline management pages
│   │   │   └── remote-locations/ # Remote location pages
│   │   ├── database/           # WordPress table abstractions
│   │   │   ├── Jobs.php        # Job data management
│   │   │   ├── Pipelines.php   # Pipeline data management
│   │   │   ├── Flows.php       # Flow data management
│   │   │   ├── ProcessedItems.php # Deduplication tracking
│   │   │   └── RemoteLocations.php # Remote site data
│   │   ├── handlers/           # Extensible input/output handlers
│   │   │   ├── input/          # Input handler implementations
│   │   │   └── output/         # Output handler implementations
│   │   ├── steps/              # Pipeline step implementations
│   │   │   ├── ai/             # AI-related step classes
│   │   │   ├── InputStep.php   # Universal input step
│   │   │   ├── OutputStep.php  # Universal output step
│   │   │   └── BasePipelineStep.php # Base step class
│   │   ├── Constants.php       # Service constants and registration
│   │   └── DataMachine.php     # Core service class
│   ├── engine/                 # Pipeline execution system
│   └── DataMachineFilters.php  # Filter-based service bootstrap
├── lib/ai-http-client/         # Multi-provider AI library
├── vendor/                     # Composer dependencies
└── data-machine.php           # WordPress plugin entry point
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

### Plugin Integration
Data Machine uses WordPress filter patterns - external plugins can extend functionality through standard WordPress hooks without modifying core code.

### Adding Custom Pipeline Steps

```php
// Register custom pipeline step via WordPress filter
add_filter('dm_get_steps', function($steps) {
    // Add custom sentiment analysis step
    $steps['sentiment_analysis'] = [
        'class' => 'MyPlugin\SentimentAnalysisStep',
        'label' => 'Sentiment Analysis',
        'type' => 'ai'
    ];
    
    return $steps;
}, 10);

// Implement the step
namespace MyPlugin;

use DataMachine\Core\Steps\BasePipelineStep;

class SentimentAnalysisStep extends BasePipelineStep {
    public function execute(int $job_id): bool {
        // Access services via WordPress filters (100% of services use this pattern)
        $logger = apply_filters('dm_get_logger', null);
        $ai_client = apply_filters('dm_get_ai_http_client', null);
        
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
// Revolutionary pure object-based handler registration
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input') {
        $handlers['shopify_orders'] = new \MyPlugin\ShopifyOrdersHandler();
    }
    return $handlers;
}, 10, 2);

// Authentication auto-links via parameter matching
add_filter('dm_get_auth', function($auth, $handler_slug) {
    if ($handler_slug === 'shopify_orders') {
        return new \MyPlugin\ShopifyAuth();
    }
    return $auth;
}, 10, 2);

// Settings auto-link via parameter matching
add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
    if ($handler_slug === 'shopify_orders') {
        return new \MyPlugin\ShopifySettings();
    }
    return $settings;
}, 10, 2);

// Implement the handler
namespace MyPlugin;

// Note: There is no BaseInputHandler - handlers implement their own patterns

class ShopifyOrdersHandler {
    // Services accessed via filters (following 100% filter-based pattern)
    
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Access services dynamically via WordPress filters
        $logger = apply_filters('dm_get_logger', null);
        $http_service = apply_filters('dm_get_http_service', null);
        $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
        
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
// Revolutionary pure object-based handler registration
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'output') {
        $handlers['discord'] = new \MyPlugin\DiscordHandler();
    }
    return $handlers;
}, 10, 2);

// Settings auto-link via parameter matching
add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
    if ($handler_slug === 'discord') {
        return new \MyPlugin\DiscordSettings();
    }
    return $settings;
}, 10, 2);

// Implement handler with filter-based service access
namespace MyPlugin;

use DataMachine\Core\Handlers\Output\BaseOutputHandler;

class DiscordHandler extends BaseOutputHandler {
    public function handle_output(array $finalized_data, object $module, int $user_id): array {
        // Access services via filters (100% coverage across platform)
        $logger = apply_filters('dm_get_logger', null);
        $http_service = apply_filters('dm_get_http_service', null);
        
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
- **`wp_dm_pipelines`**: Pipeline template definitions
- **`wp_dm_flows`**: Flow instances with scheduling
- **`wp_dm_processed_items`**: Deduplication tracking
- **`wp_dm_remote_locations`**: Encrypted remote WordPress credentials

### Extensible Job Processing Flow

```php
// Job creation via filter-based service
$job_creator = apply_filters('dm_get_job_creator', null);
$result = $job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);

// Dynamic pipeline execution - hooks generated from registered steps
// Core pipeline (extensible via dm_get_steps filter):
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

**Filter-Based Service Access (100% Coverage)**:
```php
// Filter-based service access pattern (100% platform coverage)
$logger = apply_filters('dm_get_logger', null);
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$ai_client = apply_filters('dm_get_ai_http_client', null);
// Note: handler_factory uses direct instantiation pattern

// Third-party plugins can override any service
add_filter('dm_get_logger', function($service) {
    return new MyCustomLogger(); // Override core logger
}, 15); // Higher priority overrides core services
```

**AI Provider Configuration**:
- **Multi-Provider Support**: OpenAI, Anthropic, Google Gemini, Grok, OpenRouter
- **Step-Aware Configuration**: Different providers/models per pipeline step
- **Filter-Based Access**: AI client accessed via `dm_get_ai_http_client` filter
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
4. Use specific service filters like `apply_filters('dm_get_logger', null)` for service access
5. Register handlers via type-specific filters (no core modifications)
6. Register pipeline steps via `dm_get_steps` filter
7. Implement proper interfaces (`PipelineStepInterface` for steps)
8. Add proper error handling with `WP_Error`
9. Test thoroughly with Action Scheduler and multiple plugins
10. Submit pull request

### Code Standards
- **WordPress Filter Patterns**: Use filters for service access and extensibility (100% coverage)
- **PSR-4 namespacing** with `DataMachine\` root namespace
- **Filter-Based Services**: Access services via specific filters like `apply_filters('dm_get_logger', null)` (100% of services)
- **Minimal Constructor Dependencies**: Most services retrieved dynamically via filters
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