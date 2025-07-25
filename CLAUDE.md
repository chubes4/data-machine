# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### WordPress Plugin Development
```bash
# No build process - changes take effect immediately
composer install           # Install dependencies
composer dump-autoload     # After adding/removing classes

# Database changes (no migrations - recreated on activation):
# Deactivate → Reactivate plugin to recreate tables
```

### Debugging & Testing
```bash
# Enable verbose browser logging:
window.dmDebugMode = true

# Test jobs manually:
# WordPress Admin → Data Machine → Projects → Run Now

# Monitor job status:
# WordPress Admin → Data Machine → Jobs
# WordPress Admin → Tools → Action Scheduler

# Debug specific job:
# Check wp_dm_jobs table for step data (input_data, processed_data, etc.)
# Action Scheduler logs show hook execution details
```

### Development Workflow
```bash
# Typical development cycle:
1. Edit handler files (changes immediate)
2. composer dump-autoload (if new files)
3. Test via admin interface
4. Check Action Scheduler for async job status
5. Monitor wp_dm_jobs table for step progression
```

## Core Architecture

**5-Step Async Pipeline**: Input Collection → AI Processing → Fact Check → Finalize → Output Publishing

**Key Patterns**:
- **WordPress-Native Hooks**: All handlers registered via `apply_filters('dm_register_handlers')` - no filesystem scanning
- **PSR-4 Namespacing**: Modern namespace structure with `DataMachine\` root namespace
- **Service Locator Pattern**: HandlerFactory uses PSR-4 autoloading + global container for dependencies
- **Unified Job Creation**: All jobs flow through `DataMachine\Engine\JobCreator` class
- **Action Scheduler**: Background processing with 2 max concurrent jobs
- **Direct Filter Access**: Handler registration uses direct WordPress filter calls via `DataMachine\Constants` helper methods

**Namespace Structure**:
```
DataMachine\
├── Admin\{Projects,ModuleConfig,RemoteLocations,OAuth}\
├── Database\{Modules,Projects,Jobs,ProcessedItems,RemoteLocations}\
├── Engine\{JobCreator,ProcessingOrchestrator,JobStatusManager,ProcessedItemsManager}\
├── Handlers\{HandlerFactory,Input\*,Output\*}\
├── Helpers\{Logger,ActionScheduler,HttpService,Encryption}\
├── Api\{FactCheck,Finalize integration classes}\
└── Constants (Handler helper methods + configuration)\
```

## Critical File Locations

```
data-machine.php           # Bootstrap: Service locator + core handler registration + hook registration

admin/
├── page-templates/         # Pure view templates (no business logic)
├── ModuleConfig/          # Handler configuration system
│   ├── handler-templates/  # Core handler form templates
│   └── js/                # Frontend state management
├── Projects/              # Job creation & scheduling
├── RemoteLocations/       # Remote WordPress management
└── OAuth/                 # Social media authentication

includes/
├── engine/                 # Core processing pipeline
│   ├── JobCreator.php                   # Single entry point for all jobs
│   ├── ProcessingOrchestrator.php       # 5-step async coordinator
│   ├── JobStatusManager.php             # Centralized status updates
│   ├── ProcessedItemsManager.php        # Deduplication management
│   └── filters/           # AI utilities (prompt builder, parser, etc.)
├── handlers/              # Input/Output handlers
│   ├── HandlerFactory.php              # PSR-4 autoloading + service locator pattern
│   ├── input/BaseInputHandler.php      # Shared input logic with service locator
│   └── input/output/      # Handler implementations
├── database/              # Custom wp_dm_* tables (no migrations)
├── api/                   # OpenAI integration classes
└── helpers/               # Logger, encryption, memory guard, constants
```

## Handler Development

**Adding Core Handlers**:
1. Create class extending `DataMachine\Handlers\Input\BaseInputHandler` or `DataMachine\Handlers\Output\BaseOutputHandler`
2. Implement required methods: `get_input_data()` or `handle_output()`
3. No constructor needed - dependencies available via service locator in base class
4. Register in `data_machine_register_core_handlers()` function in `data-machine.php`
5. **Done!** HandlerFactory uses PSR-4 autoloading: `new $className()`

**Adding External Handlers (Third-Party Plugins)**:
```php
// Third-party plugins register handlers via WordPress hooks:
add_filter('dm_register_handlers', function($handlers) {
    $handlers['input']['shopify_orders'] = [
        'class' => 'MyPlugin\ShopifyOrdersHandler',
        'label' => 'Shopify Orders'
    ];
    return $handlers;
});

// Optional: Register settings fields
add_filter('dm_handler_settings_fields', function($fields, $type, $slug, $config) {
    if ($type === 'input' && $slug === 'shopify_orders') {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => 'Shopify API Key',
                'required' => true
            ]
        ];
    }
    return $fields;
}, 10, 4);

// Optional: Register custom template
add_action('dm_load_handler_template', function(&$loaded, $type, $slug, $config) {
    if ($type === 'input' && $slug === 'shopify_orders') {
        include plugin_dir_path(__FILE__) . 'templates/shopify-settings.php';
        $loaded = true;
    }
}, 10, 4);
```

**WordPress-Native Extensibility**: 
- Core and external handlers use identical WordPress hook patterns
- HandlerFactory uses simple PSR-4 autoloading for all handlers
- Base classes provide service locator access to dependencies
- Direct filter access via `DataMachine\Constants` helper methods
- No filesystem scanning or core modifications needed

**Handler Pattern**:
```php
namespace DataMachine\Handlers\Input;

class ExampleHandler extends BaseInputHandler {
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Dependencies available via service locator:
        // $this->logger, $this->db_modules, $this->db_projects, 
        // $this->processed_items_manager, $this->http_service
        
        // Use $this->http_service for API calls
        // Use $this->filter_processed_items() for deduplication
    }
}
```

## Job Processing Flow

**Job Creation**: 
```php
$job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);
// Returns: ['success' => bool, 'message' => string, 'job_id' => int]
```

**Async Steps**: Each step stores data in `wp_dm_jobs` table and schedules next step
- Step 1: `dm_input_job_event` → `input_data` 
- Step 2: `dm_process_job_event` → `processed_data`
- Step 3: `dm_factcheck_job_event` → `fact_checked_data`
- Step 4: `dm_finalize_job_event` → `finalized_data`
- Step 5: `dm_output_job_event` → `result_data`

## Configuration System

**Module Config**: Handler-specific templates loaded via AJAX with state management
**Remote Locations**: Form submissions (no AJAX) for reliability
**Database Schema**: Custom `wp_dm_*` tables - no migrations, created on activation

## Database Schema

**Core Tables**:
```sql
wp_dm_jobs - 5-step pipeline data storage:
├── job_id, module_id, user_id, status, current_step (1-5)
├── input_data, processed_data, fact_checked_data, finalized_data, result_data
├── cleanup_scheduled (data retention), created_at, updated_at

wp_dm_modules - Handler configuration and settings
wp_dm_projects - Project scheduling and management
wp_dm_processed_items - Deduplication tracking with content hashes
wp_dm_remote_locations - Remote WordPress credentials (encrypted)
```

## Common Issues

**Handler Registration**: Use `DataMachine\Constants::get_*_handler*()` methods to access registered handlers
**Handler Factory**: Uses PSR-4 autoloading + service locator - no manual dependency injection needed
**Job Failures**: Check Action Scheduler status, jobs fail immediately with descriptive errors
**Large Content**: Stored in database step fields, not Action Scheduler args (8000 char limit)
**Asset Loading**: Use `DATA_MACHINE_PATH` constant for reliable file paths
**Global Container**: Access orchestrator/dependencies via `global $data_machine_container` in hooks

## Dependencies

- PHP 8.0+, WordPress 5.0+, MySQL 5.6+
- Composer packages: monolog, parsedown, twitteroauth, action-scheduler
- **AI HTTP Client Library** (`/lib/ai-http-client/`): Multi-provider AI integration with unified interface
- **OpenAI API key** required for AI processing steps

## AI Integration Architecture

**Multi-Provider AI Library**: Data Machine uses a custom AI HTTP Client library that supports multiple providers
- **Providers Supported**: OpenAI, Anthropic, Google Gemini, Grok, OpenRouter
- **Plugin-Scoped Configuration**: Each plugin can use different models/providers
- **Shared API Keys**: Efficient storage across multiple plugins using this library
- **Unified Interface**: Standard request/response format regardless of provider

**IMPORTANT - OpenAI Integration**: 
- **ALWAYS use OpenAI Responses API**, never Chat Completions API
- No hard-coded defaults for AI Provider APIs - if settings missing, fail with API error
- AI library handles provider normalization automatically

**AI Library Integration Pattern**:
```php
// Access via global container (current implementation)
global $data_machine_container;
$ai_http_client = $data_machine_container['ai_http_client'];

// Send step-aware requests to AI providers
$response = $ai_http_client->send_step_request('process', [
    'messages' => $messages
]);

// Library handles provider switching and normalization automatically
if ($response['success']) {
    $content = $response['data']['content'];
}
```

## Storage Architecture

**Configuration Storage**: All app-level credentials (API keys, Bluesky credentials) use global WordPress options
- OpenAI: `openai_api_key`
- Bluesky: `bluesky_username`, `bluesky_app_password`
- Twitter/Facebook/Threads: OAuth tokens stored per-user in user meta

**Database Tables**: Custom `wp_dm_*` tables managed without migrations
- Recreated on plugin activation/deactivation cycle
- `wp_dm_jobs` stores step data for async pipeline (no 8000 char limit like Action Scheduler)
- Encrypted passwords for remote locations using `EncryptionHelper`

## Critical Integration Points

**Action Scheduler Hooks**: Background processing limited to 2 concurrent jobs
```php
'dm_input_job_event', 'dm_process_job_event', 'dm_factcheck_job_event', 
'dm_finalize_job_event', 'dm_output_job_event'
```

**Remote Locations**: Enhanced with system access for automated jobs
```php
$location = $this->db_locations->get_location($location_id, null, true, true); // System access
```

**PSR-4 Type Safety**: All constructor parameters use proper namespaced types
- Always add `use` statements for dependencies  
- Namespace declaration must come before `ABSPATH` check
- Missing imports cause fatal "Class not found" errors
- **Naming Conventions**: PascalCase for classes/files (PSR-4), snake_case for slugs/identifiers/database (WordPress standard)

**Bootstrap Container**: Access dependencies via `global $data_machine_container` in hooks

**Constants Configuration**: Global settings via `DataMachine\Constants` class
- **Handler Helper Methods**: Direct access to registered handlers via WordPress filters
- Cron intervals and job timeout settings
- Memory limits and cleanup schedules
- Encryption key management
- **Handler Access**: `get_input_handlers()`, `get_output_handlers()`, `get_*_handler_class()`, etc.

## File Organization & PSR-4 Migration

The codebase has been fully migrated to PSR-4 namespacing with PascalCase filenames:

**New Structure**:
- Old: `class-data-machine-*.php` files → New: PascalCase `*.php` files
- Classes use full namespaces: `DataMachine\Database\Jobs` instead of `Data_Machine_Database_Jobs`
- File paths match namespace structure: `includes/database/Jobs.php` for `DataMachine\Database\Jobs`

**Current State**: All classes use PSR-4 autoloading via Composer with proper namespace declarations

## Recent Architecture Changes

**Service Locator Migration**: Replaced complex manual dependency injection with PSR-4 autoloading + service locator pattern.

**HandlerFactory Simplification**: 
```php
// Old (removed): Complex reflection-based DI
$handler_factory = new HandlerFactory($logger, $processed_items_manager, $encryption_helper, ...);

// New (current): Simple PSR-4 autoloading
$handler_factory = new HandlerFactory(); // Dependencies via service locator
```

**Bootstrap Streamlining**: 
- Reduced from 373 lines to ~200 lines
- Organized into `init_data_machine_services()` and `register_data_machine_hooks()`
- Eliminated contradictory manual DI that fought against PSR-4/hooks system

**HandlerRegistry Removal**: The `HandlerRegistry` class has been removed as redundant. It was simply wrapping WordPress filter calls with no added value.

**Impact**: 50% reduction in complexity, leveraged existing PSR-4 + hooks architecture instead of undermining it

## WordPress Development Standards

**HTTP Requests**: 
- **NEVER use cURL functions** - highly discouraged in WordPress
- **ALWAYS use `wp_remote_get()` and `wp_remote_post()`** for all HTTP requests
- AI HTTP Client library uses WordPress-native HTTP functions internally

**Security & Output**:
- **All output MUST be run through escaping functions** (`esc_html`, `esc_attr`, etc.)
- Never include sensitive information (API keys, tokens) in code or commits
- Use WordPress security functions throughout (`wp_verify_nonce`, `current_user_can`, etc.)

**Code Style**:
- **Never use inline CSS styles** - all styles in external files
- Follow WordPress coding standards for HTML/CSS/JS
- Use WordPress hooks and filters for extensibility

**Error Handling**:
- Return `WP_Error` objects for error conditions
- Log errors via `DataMachine\Helpers\Logger` class
- Provide helpful error messages to users via admin notices