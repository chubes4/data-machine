# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### WordPress Plugin Development
```bash
# Install dependencies
composer install

# After adding/removing classes
composer dump-autoload

# No build process - changes take effect immediately
# Database changes: no migrations - recreated on plugin activation
```

### Testing & Debugging
```bash
# Enable verbose browser logging
window.dmDebugMode = true

# Test jobs manually: WordPress Admin → Data Machine → Projects → Run Now
# Monitor jobs: WordPress Admin → Data Machine → Jobs  
# Background processing: WordPress Admin → Tools → Action Scheduler
# Database inspection: Check wp_dm_jobs table for step progression
```

### Development Workflow
```bash
# Standard cycle:
1. Edit handler files (changes immediate)
2. composer dump-autoload (if new classes added)
3. Test via WordPress admin interface  
4. Monitor Action Scheduler for background job status
5. Check wp_dm_jobs table for step data persistence
```

## Core Architecture

**5-Step Async Pipeline**: Input Collection → AI Processing → Fact Check → Finalize → Output Publishing

**Key Patterns**:
- **WordPress-Native Hooks**: Handler registration via `apply_filters('dm_register_handlers')`
- **PSR-4 Namespacing**: `DataMachine\` root namespace with autoloading
- **Service Locator Pattern**: Dependencies accessible via global container
- **Unified Job Creation**: All jobs flow through `DataMachine\Engine\JobCreator`
- **Action Scheduler**: Background processing (2 max concurrent jobs)
- **Bootstrap Container**: `global $data_machine_container` for dependency access
- **Programmatic Forms**: Forms generated from field definitions, no template files needed

**Namespace Structure**:
```
DataMachine\
├── Admin\           # UI management (Projects, ModuleConfig, OAuth, RemoteLocations)
├── Database\        # Custom wp_dm_* table abstractions
├── Engine\          # Core processing pipeline (JobCreator, ProcessingOrchestrator)
├── Handlers\        # Input/Output handlers with factory pattern
├── Helpers\         # Utilities (Logger, ActionScheduler, HttpService, Encryption)
├── Api\             # AI integration (FactCheck, Finalize)
└── Constants        # Configuration and handler helper methods
```

## Critical File Locations

```
data-machine.php           # Bootstrap: Service locator + handler registration + hooks

admin/
├── page-templates/        # View templates (jobs.php, module-config-page.php, etc.)
├── ModuleConfig/          # Handler configuration UI + AJAX handlers
│   ├── FormRenderer.php   # Programmatic form generation from field definitions
│   └── Ajax/              # AJAX handlers for module config
├── Projects/              # Job scheduling + import/export
├── RemoteLocations/       # Remote WordPress site management  
└── OAuth/                 # Social media authentication

includes/
├── engine/                # Core 5-step processing pipeline
│   ├── JobCreator.php     # Single entry point for job creation
│   ├── ProcessingOrchestrator.php  # Async step coordinator  
│   └── filters/           # AI utilities (PromptBuilder, AiResponseParser)
├── handlers/              # Input/Output handler implementations
│   ├── HandlerFactory.php # PSR-4 autoloading + dependency injection
│   └── input/, output/    # Handler implementations extending base classes
├── database/              # Custom wp_dm_* table abstractions
├── api/                   # AI provider integration (FactCheck, Finalize)
├── CoreHandlerRegistry.php # Auto-discovery and registration system
└── helpers/               # Utilities (Logger, EncryptionHelper, ActionScheduler)

lib/ai-http-client/        # Multi-provider AI library (OpenAI, Anthropic, etc.)
```

## Handler Development

### **Adding Core Handlers**:
1. Create class extending `DataMachine\Handlers\Input\BaseInputHandler` or `DataMachine\Handlers\Output\BaseOutputHandler`
2. Implement required methods: `get_input_data()` or `handle_output()` 
3. **CRITICAL**: Constructor must use service locator pattern - call `parent::__construct()` with no parameters
4. **CRITICAL**: `get_settings_fields()` method must be `public static`
5. Dependencies auto-injected via service locator pattern in base class
6. CoreHandlerRegistry handles PSR-4 auto-discovery and registration

### **Adding External Handlers (Third-Party Plugins)**:
```php
// Third-party plugins register handlers via WordPress hooks:
add_filter('dm_register_handlers', function($handlers) {
    $handlers['input']['shopify_orders'] = [
        'class' => 'MyPlugin\ShopifyOrdersHandler',
        'label' => 'Shopify Orders'
    ];
    return $handlers;
});

// Register settings fields - MUST be static method
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
```

### **Handler Pattern**:
```php
namespace DataMachine\Handlers\Input;

class ExampleHandler extends BaseInputHandler {
    // CRITICAL: Service locator constructor pattern
    public function __construct() {
        parent::__construct(); // No parameters - uses service locator
    }
    
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Dependencies auto-available via service locator:
        // $this->logger, $this->db_modules, $this->db_projects, 
        // $this->processed_items_manager, $this->http_service
        
        // Use $this->http_service for API calls (WordPress wp_remote_* functions)
        // Use $this->filter_processed_items() for deduplication
        return ['processed_items' => $items];
    }
    
    // CRITICAL: Must be static method
    public static function get_settings_fields(array $current_config = []): array {
        return [
            'api_endpoint' => [
                'type' => 'url',
                'label' => 'API Endpoint',
                'required' => true
            ]
        ];
    }
}
```

**Extensibility**: Core and external handlers use identical WordPress hook patterns. No filesystem scanning, no core modifications, no template files needed - forms generated programmatically from field definitions.

## Job Processing Flow

**Job Creation**: 
```php
$job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);
// Returns: ['success' => bool, 'message' => string, 'job_id' => int]
```

**5-Step Async Pipeline**: Each step stores data in `wp_dm_jobs` table and schedules next step
1. `dm_input_job_event` → `input_data` (collect from sources)
2. `dm_process_job_event` → `processed_data` (AI processing)  
3. `dm_factcheck_job_event` → `fact_checked_data` (optional AI validation)
4. `dm_finalize_job_event` → `finalized_data` (content finalization)
5. `dm_output_job_event` → `result_data` (multi-platform publishing)

## Database Schema

**Core Tables**:
- `wp_dm_jobs` - 5-step pipeline data with JSON fields for step results
- `wp_dm_modules` - Handler configurations and settings  
- `wp_dm_projects` - Project scheduling and management
- `wp_dm_processed_items` - Deduplication tracking with content hashes
- `wp_dm_remote_locations` - Encrypted remote WordPress credentials

**Key Fields**:
```sql
wp_dm_jobs: job_id, module_id, user_id, status, current_step (1-5),
            input_data, processed_data, fact_checked_data, finalized_data, result_data
```

**Configuration**: 
- **Module Config**: AJAX-loaded handler templates with state management
- **Remote Locations**: Form submissions (no AJAX) for reliability  
- **No Migrations**: Tables recreated on plugin activation/deactivation

## Common Development Issues

- **Handler Registration**: Use `DataMachine\Constants::get_*_handler*()` methods to access registered handlers
- **Job Failures**: Check Action Scheduler status - jobs fail immediately with descriptive errors  
- **Large Content**: Stored in database step fields, not Action Scheduler args (8000 char limit)
- **Asset Loading**: Use `DATA_MACHINE_PATH` constant for reliable file paths
- **Global Container**: Access dependencies via `global $data_machine_container` in hooks
- **PSR-4 Type Safety**: Always add `use` statements - missing imports cause fatal errors

## Dependencies

- **Core**: PHP 8.0+, WordPress 5.0+, MySQL 5.6+
- **Composer**: monolog, parsedown, twitteroauth, action-scheduler
- **AI Library**: `/lib/ai-http-client/` - Multi-provider AI integration
- **API Keys**: At least one AI provider key required (OpenAI, Anthropic, etc.)

## AI Integration

**Multi-Provider Support**: Custom AI HTTP Client library supports OpenAI, Anthropic, Google Gemini, Grok, OpenRouter

**Critical Requirements**:
- **ALWAYS use OpenAI Responses API**, never Chat Completions API
- No hard-coded defaults - fail with API error if settings missing
- Library handles provider normalization automatically

**Access Pattern**:
```php
global $data_machine_container;
$ai_http_client = $data_machine_container['ai_http_client'];

$response = $ai_http_client->send_step_request('process', ['messages' => $messages]);
if ($response['success']) {
    $content = $response['data']['content'];
}
```

## Storage & Configuration

**Credential Storage**:
- **Global Options**: `openai_api_key`, `bluesky_username`, `bluesky_app_password`  
- **User Meta**: OAuth tokens (Twitter/Facebook/Threads) stored per-user
- **Encrypted**: Remote locations use `EncryptionHelper` for passwords

**Action Scheduler Integration**:
- **5 Hook Events**: `dm_input_job_event`, `dm_process_job_event`, `dm_factcheck_job_event`, `dm_finalize_job_event`, `dm_output_job_event`
- **Concurrency**: Limited to 2 concurrent jobs
- **Data Storage**: Large content in `wp_dm_jobs` table (no 8000 char Action Scheduler limit)

## PSR-4 Architecture

**Naming Conventions**: 
- **Classes/Files**: PascalCase (PSR-4 standard)
- **Database/Slugs**: snake_case (WordPress standard)
- **Namespaces**: `DataMachine\Database\Jobs` matches `includes/database/Jobs.php`

**Type Safety Requirements**:
- Always add `use` statements for dependencies
- Namespace declaration before `ABSPATH` check  
- Missing imports cause fatal "Class not found" errors

**Service Locator Pattern**: 
- HandlerFactory uses PSR-4 autoloading + global container
- Dependencies accessible via `global $data_machine_container`
- All handlers use consistent constructor pattern: `parent::__construct()` with no parameters

## WordPress Standards

**HTTP Requests**: NEVER use cURL - ALWAYS use `wp_remote_get()` and `wp_remote_post()`
**Security**: All output through escaping functions (`esc_html`, `esc_attr`, etc.) 
**Code Style**: No inline CSS - external files only
**Error Handling**: Return `WP_Error` objects, log via `DataMachine\Helpers\Logger`

## Architecture Principles

**"Eating Our Own Dog Food"**: Core handlers use the **exact same** registration system as external handlers - no special core-only code paths.

**Zero Core Modifications**: External plugins can add handlers without touching Data Machine plugin code - purely hook-based extensibility.

**Programmatic Everything**: Forms, validation, and templates generated from data structures - no hardcoded HTML files needed.