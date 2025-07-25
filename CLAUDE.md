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

# Job monitoring locations:
# 1. WordPress Admin → Data Machine → Projects → Run Now (manual job execution)
# 2. WordPress Admin → Data Machine → Jobs (job status/history)
# 3. WordPress Admin → Tools → Action Scheduler (background processing)
# 4. Database: wp_dm_jobs table (step progression data)

# Step configuration validation:
# WordPress Admin → Data Machine → API Keys → Configuration Status (real-time status)
```

### Development Workflow
```bash
# Standard cycle:
1. Edit handler/step files (changes immediate)
2. composer dump-autoload (if new classes added)
3. Test via WordPress admin interface  
4. Monitor Action Scheduler for background job status
5. Check wp_dm_jobs table for step data persistence

# Debugging failed jobs:
# Check Action Scheduler logs for immediate errors
# Examine wp_dm_jobs.error_details for step failures
# Verify step configurations via StepConfigurationValidator

# Pipeline-specific debugging:
# Step failures: Check individual step class implementations in includes/engine/steps/
# Memory issues: Monitor wp_dm_jobs table size - large JSON fields can impact performance
```

## Core Architecture

**Extensible 5-Step Pipeline**: Input Collection → AI Processing → Fact Check → Finalize → Output Publishing

**Revolutionary Architecture Change** (2024): Complete migration to pure filter-based architecture. The plugin now uses 100% WordPress-native filter patterns for all service access, eliminating constructor injection entirely. This transforms Data Machine from a rigid 5-step system into a universal content processing platform with maximum WordPress compatibility.

**Key Patterns**:
- **100% Pure Filter-Based Architecture**: Complete elimination of constructor injection - all services accessed via `apply_filters('dm_get_service', null, 'service_name')`
- **Zero Constructor Dependencies**: All handlers and steps use parameter-less constructors with filter-based service retrieval
- **WordPress-Native Hooks**: Handler registration via `apply_filters('dm_register_handlers')`
- **Extensible Pipeline**: Step registration via `apply_filters('dm_register_pipeline_steps')`
- **PSR-4 Namespacing**: `DataMachine\` root namespace with autoloading
- **Unified Job Creation**: All jobs flow through `DataMachine\Engine\JobCreator`
- **Dynamic Step Execution**: Single `execute_step($step_name, $job_id)` method handles all pipeline steps
- **Action Scheduler**: Background processing (2 max concurrent jobs)
- **Programmatic Forms**: Forms generated from field definitions, no template files needed

**Pipeline Data Flow**:
- Each step stores results in `wp_dm_jobs` table JSON fields (input_data, processed_data, etc.)
- Step progression tracked via `current_step` field (1-5)
- Large content stored in database fields to avoid Action Scheduler 8000 char limit
- ProcessingOrchestrator coordinates async execution between steps dynamically
- Jobs fail immediately with detailed error logging for rapid debugging

**Namespace Structure**:
```
DataMachine\
├── Admin\           # UI management (Projects, ModuleConfig, OAuth, RemoteLocations)
├── Container\       # Legacy infrastructure (deprecated)
├── Contracts\       # Interfaces for type safety (LoggerInterface, etc.)
├── Database\        # Custom wp_dm_* table abstractions
├── Engine\          # Core processing pipeline + extensible step system
│   ├── Interfaces\  # Pipeline contracts (PipelineStepInterface)
│   └── Steps\       # Individual pipeline step implementations
├── Handlers\        # Input/Output handlers with factory pattern
├── Helpers\         # Utilities (Logger, ActionScheduler, HttpService, Encryption)
└── Constants        # Configuration and handler helper methods
```

## Critical File Locations

```
data-machine.php           # Bootstrap: Filter-based service registration + dynamic pipeline registration

admin/
├── page-templates/        # View templates (jobs.php, module-config-page.php, etc.)
├── ModuleConfig/          # Handler configuration UI + AJAX handlers
│   ├── FormRenderer.php   # Programmatic form generation from field definitions
│   └── Ajax/              # AJAX handlers for module config
├── Projects/              # Job scheduling + import/export
├── RemoteLocations/       # Remote WordPress site management  
└── OAuth/                 # Social media authentication

includes/
├── Container/             # Legacy infrastructure (deprecated)
│   └── ServiceContainer.php  # Legacy container (being phased out)
├── Contracts/             # Type-safe interfaces
│   ├── LoggerInterface.php    # Logger contract
│   ├── DatabaseInterface.php  # Database abstraction contract
│   └── ActionSchedulerInterface.php  # Action Scheduler contract
├── engine/                # Core extensible processing pipeline
│   ├── JobCreator.php     # Single entry point for job creation
│   ├── ProcessingOrchestrator.php  # Dynamic step coordinator with filter-based services
│   ├── interfaces/        # Pipeline interfaces and contracts
│   │   └── PipelineStepInterface.php  # Contract for all pipeline steps
│   └── steps/            # Individual pipeline step implementations
│       ├── BasePipelineStep.php      # Common functionality with filter-based services
│       ├── InputStep.php             # Data collection step
│       ├── ProcessStep.php           # AI processing step  
│       ├── FactCheckStep.php         # AI fact-checking step
│       ├── FinalizeStep.php          # Content finalization step
│       └── OutputStep.php            # Multi-platform publishing step
├── handlers/              # Input/Output handler implementations
│   ├── HandlerFactory.php # Filter-based handler creation
│   └── input/, output/    # Handler implementations with filter-based service access
├── database/              # Custom wp_dm_* table abstractions
├── CoreHandlerRegistry.php # Auto-discovery and registration system
└── helpers/               # Utilities (Logger, EncryptionHelper, ActionScheduler)

lib/ai-http-client/        # Multi-provider AI library (OpenAI, Anthropic, etc.)
```

## Extensible Pipeline System (NEW 2024)

**Revolutionary Change**: The 5-step pipeline is now fully extensible, allowing third-party plugins to add, remove, or modify pipeline steps without touching core code.

### **Pipeline Step Registration**
```php
// Core pipeline registered in data-machine.php bootstrap
add_filter('dm_register_pipeline_steps', function($steps) {
    return [
        'input' => ['class' => 'DataMachine\\Engine\\Steps\\InputStep', 'next' => 'process'],
        'process' => ['class' => 'DataMachine\\Engine\\Steps\\ProcessStep', 'next' => 'factcheck'],
        'factcheck' => ['class' => 'DataMachine\\Engine\\Steps\\FactCheckStep', 'next' => 'finalize'],
        'finalize' => ['class' => 'DataMachine\\Engine\\Steps\\FinalizeStep', 'next' => 'output'],
        'output' => ['class' => 'DataMachine\\Engine\\Steps\\OutputStep', 'next' => null]
    ];
}, 5);

// Third-party plugins can extend the pipeline:
add_filter('dm_register_pipeline_steps', function($steps) {
    // Insert custom step between process and factcheck
    $steps['custom_analysis'] = [
        'class' => 'MyPlugin\\CustomAnalysisStep',
        'next' => 'factcheck'
    ];
    $steps['process']['next'] = 'custom_analysis'; // Redirect process to custom step
    
    return $steps;
}, 10);
```

### **Creating Custom Pipeline Steps**
```php
namespace MyPlugin;

use DataMachine\Engine\{Interfaces\PipelineStepInterface, Steps\BasePipelineStep};

class CustomAnalysisStep extends BasePipelineStep implements PipelineStepInterface {
    // Parameter-less constructor - pure filter-based architecture
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }
    
    public function execute(int $job_id): bool {
        // 100% filter-based service access - no constructor injection
        $logger = apply_filters('dm_get_service', null, 'logger');
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        $ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');
        
        // Get data from previous step
        $input_data = $this->get_step_data($job_id, 1);
        $processed_data = $this->get_step_data($job_id, 2);
        
        // Perform custom analysis
        $analysis_result = $this->perform_analysis($processed_data);
        
        // Store result in database (step number dynamically assigned)
        return $this->store_step_data($job_id, 'custom_analysis_data', $analysis_result);
    }
}
```

### **Dynamic Hook Registration**
Action Scheduler hooks are now generated automatically from pipeline configuration:
```php
// In data-machine.php - Dynamic hook registration
$pipeline_steps = apply_filters('dm_register_pipeline_steps', []);
foreach ($pipeline_steps as $step_name => $step_config) {
    $hook_name = 'dm_' . $step_name . '_job_event';
    add_action($hook_name, function($job_id) use ($orchestrator, $step_name) {
        return $orchestrator->execute_step($step_name, $job_id);
    }, 10, 1);
}
```

## Handler Development

### **Adding Core Handlers**:
1. Create class extending `DataMachine\Handlers\Input\BaseInputHandler` or `DataMachine\Handlers\Output\BaseOutputHandler`
2. **CRITICAL**: Use parameter-less constructor - pure filter-based architecture eliminates ALL constructor injection
3. Implement required methods: `get_input_data()` or `handle_output()`
4. **CRITICAL**: All services accessed via `apply_filters('dm_get_service', null, 'service_name')` - no exceptions
5. **CRITICAL**: `get_settings_fields()` method must be `public static`
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
    // Parameter-less constructor - 100% filter-based architecture
    public function __construct() {
        // No parameters - pure filter-based service access
    }
    
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // 100% filter-based service access - no constructor injection anywhere
        $logger = apply_filters('dm_get_service', null, 'logger');
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $processed_items_manager = apply_filters('dm_get_service', null, 'processed_items_manager');
        $http_service = apply_filters('dm_get_service', null, 'http_service');
        
        // Use $http_service for API calls (WordPress wp_remote_* functions)
        // Use $processed_items_manager->filter_processed_items() for deduplication
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

**Critical Handler Requirements**:
- **Architecture**: Parameter-less constructors only - 100% filter-based service access completed
- **Services**: ALL services accessed via `apply_filters('dm_get_service', null, 'service_name')` - zero constructor injection
- **Settings**: `public static function get_settings_fields()` must return array of field definitions
- **Dependencies**: Retrieved dynamically via WordPress filters - no constructor parameters ever
- **Registration**: Core handlers auto-discovered, external handlers use `dm_register_handlers` filter

## Step Configuration Validation

**Critical Requirement**: All pipeline steps require explicit AI configuration to function.

**Validation Pattern**:
- AI HTTP Client library handles ALL validation internally
- Jobs fail immediately if step configurations missing
- Clear, provider-specific error messages returned
- No duplicate validation needed

**Library Integration**: AI HTTP Client validates configurations during `send_step_request()` calls:

```php
// Library handles validation automatically
$response = $ai_http_client->send_step_request('process', ['messages' => $messages]);

if (!$response['success']) {
    // Library provides detailed, provider-specific error
    $error = $response['error']; // e.g., "OpenAI API: Invalid API key provided"
    $provider = $response['provider']; // e.g., "openai"
    // Job fails with actionable error message
}
```

**Configuration Status UI**: WordPress Admin → Data Machine → API Keys → Configuration Status uses library methods to show real-time step completion status.

## Job Processing Flow

**Job Creation**: 
```php
$job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);
// Returns: ['success' => bool, 'message' => string, 'job_id' => int]
```

**Extensible Pipeline**: Steps dynamically loaded from WordPress filters
1. `dm_input_job_event` → `input_data` (collect from sources)
2. `dm_process_job_event` → `processed_data` (AI processing)  
3. `dm_factcheck_job_event` → `fact_checked_data` (optional AI validation)
4. `dm_finalize_job_event` → `finalized_data` (content finalization)
5. `dm_output_job_event` → `result_data` (multi-platform publishing)

**Dynamic Execution**: `ProcessingOrchestrator->execute_step($step_name, $job_id)` handles all steps uniformly

## Database Schema

**Core Tables**:
- `wp_dm_jobs` - Extensible pipeline data with JSON fields for step results
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

- **Step Configuration Failures**: Check WordPress Admin → Data Machine → API Keys for missing step configurations
- **"No configuration found for step"**: AI HTTP Client library validates step setup during request execution
- **Job Immediate Failures**: Check Action Scheduler logs and wp_dm_jobs table for validation errors
- **AI Request Failures**: Verify both global provider settings AND step-specific configurations exist
- **Handler Registration**: Use `DataMachine\Constants::get_*_handler*()` methods to access registered handlers
- **Job Failures**: Check Action Scheduler status - jobs fail immediately with descriptive errors  
- **Large Content**: Stored in database step fields, not Action Scheduler args (8000 char limit)
- **Pipeline Step Errors**: Check individual step class implementations in `includes/engine/steps/`
- **Service Access**: Use `apply_filters('dm_get_service', null, 'service_name')` throughout
- **PSR-4 Type Safety**: Always add `use` statements - missing imports cause fatal errors
- **Service Registration**: Ensure all services are registered via filters in bootstrap
- **Pipeline Step Interface**: All custom steps must implement `PipelineStepInterface`
- **Step Registration**: Custom steps must be registered via `dm_register_pipeline_steps` filter
- **100% Filter-Based Architecture**: Complete refactor achieved - all constructors are parameter-less, all services accessed via filters

## Service System

**100% Pure Filter-Based Architecture**: The refactor is complete - the plugin now uses exclusively WordPress filters for ALL service access. Constructor injection has been completely eliminated throughout the entire codebase.

**Completed Filter-Based Service Architecture**:
- **Complete Migration**: 100% of handlers, steps, and services now use filter-based access
- **Zero Constructor Injection**: All constructors are parameter-less - no exceptions throughout codebase
- **Universal Pattern**: Every service accessed via `apply_filters('dm_get_service', null, 'service_name')`
- **Maximum Extensibility**: Third-party plugins can override any service via WordPress filter priority
- **Pure WordPress-Native**: Complete alignment with WordPress architectural patterns

**Available Services**:
- `logger` - Logger service for error and info logging
- `db_jobs`, `db_modules`, `db_projects`, `db_processed_items`, `db_remote_locations` - Database services
- `ai_http_client` - Multi-provider AI integration
- `handler_factory` - Factory for creating input/output handlers
- `processed_items_manager` - Service for tracking processed items
- `job_creator`, `job_status_manager` - Job management services
- `action_scheduler` - WordPress Action Scheduler integration
- `prompt_builder` - AI prompt construction service
- `oauth_*` - OAuth services for social media platforms
- `encryption_helper` - Credential encryption service

**Service Registration Pattern** (in bootstrap):
```php
// Simple array-based service registry
$services = [];

// Register service factories
add_filter('dm_get_service', function($service, $name) use (&$services) {
    if ($name === 'logger' && !isset($services['logger'])) {
        $services['logger'] = new Logger();
    }
    if ($name === 'db_jobs' && !isset($services['db_jobs'])) {
        // Services instantiated with parameter-less constructors
        // Dependencies resolved via filter-based access within service classes
        $services['db_jobs'] = new DatabaseJobs();
    }
    return $services[$name] ?? null;
}, 10, 2);
```

**Universal Service Access Pattern** (everywhere):
```php
// 100% filter-based access - the ONLY approach throughout entire codebase
$logger = apply_filters('dm_get_service', null, 'logger');
$db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
$ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');

// This pattern is now used in:
// - ALL handlers (input and output)
// - ALL pipeline steps
// - ALL service classes
// - ALL admin classes
// - NO constructor injection anywhere
```

## Dependencies

- **Core**: PHP 8.0+, WordPress 5.0+, MySQL 5.6+
- **Composer**: monolog, parsedown, twitteroauth, action-scheduler
- **AI Library**: `/lib/ai-http-client/` - Multi-provider AI integration
- **API Keys**: At least one AI provider key required (OpenAI, Anthropic, etc.)

## AI Integration

**Multi-Provider Support**: Custom AI HTTP Client library supports OpenAI, Anthropic, Google Gemini, Grok, OpenRouter

**Step-Aware Configuration**: Each pipeline step can use different AI providers/models:
- **Process Step**: Data processing and initial content generation
- **FactCheck Step**: AI validation with web search capabilities  
- **Finalize Step**: Content polishing and output formatting

**Configuration Location**: WordPress Admin → Data Machine → API Keys
- Global AI provider settings (fallback)
- Step-specific overrides (process, factcheck, finalize)
- Real-time configuration status display

**Critical Requirements**:
- **ALWAYS use OpenAI Responses API**, never Chat Completions API
- No hard-coded defaults - fail with API error if settings missing
- Step configurations must exist before pipeline execution
- Library handles provider normalization automatically

**Response Format Integration**:
All pipeline steps now use the AI HTTP Client library's standard response format directly:
```php
// Library standard format returned by all AI operations:
[
    'success' => bool,
    'data' => [
        'content' => string,        // AI response content
        'usage' => [...],          // Token usage stats
        'model' => string,         // Model used
        'finish_reason' => string, // Completion reason
        'tool_calls' => array|null // Tool calls if any
    ],
    'error' => string|null,        // Detailed error message
    'provider' => string,          // Provider used (openai, anthropic, etc.)
    'raw_response' => array        // Full provider response for debugging
]
```

**Access Pattern**:
```php
// Get services via filter-based system
$ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');

// Step-aware requests (recommended)
$response = $ai_http_client->send_step_request('process', ['messages' => $messages]);

// Extract content from library response:
if ($response['success']) {
    $content = $response['data']['content'];
    $usage_stats = $response['data']['usage'];
    $model_used = $response['data']['model'];
} else {
    $error = $response['error']; // Detailed provider-specific error
}
```

## Storage & Configuration

**Credential Storage**:
- **Global Options**: `openai_api_key`, `bluesky_username`, `bluesky_app_password`  
- **User Meta**: OAuth tokens (Twitter/Facebook/Threads) stored per-user
- **Encrypted**: Remote locations use `EncryptionHelper` for passwords

**Action Scheduler Integration**:
- **Dynamic Hook Events**: Generated from pipeline configuration (`dm_{step_name}_job_event`)
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

**Completed Filter-Based Service Architecture**: 
- **Refactor Complete**: 100% migration to filter-based patterns achieved
- **Universal Implementation**: All services, handlers, steps, and classes use identical filter access
- **Parameter-less Constructors**: Every class constructor eliminated dependencies completely
- **Service Registration**: Simple array-based registry via WordPress filters with lazy loading
- **Third-party Override**: Plugins override services via filter priority - maximum extensibility
- **Pure WordPress-Native**: Complete elimination of complex dependency injection - WordPress-first approach

## WordPress Standards

**HTTP Requests**: NEVER use cURL - ALWAYS use `wp_remote_get()` and `wp_remote_post()`
**Security**: All output through escaping functions (`esc_html`, `esc_attr`, etc.) 
**Code Style**: No inline CSS - external files only
**Error Handling**: Return `WP_Error` objects, log via `DataMachine\Helpers\Logger`

## Architecture Principles

**"Universal Content Processing Platform"**: The extensible pipeline architecture transforms Data Machine from a rigid 5-step system into a platform where any processing workflow can be implemented.

**"Eating Our Own Dog Food"**: Core handlers and pipeline steps use the **exact same** registration system as external extensions - no special core-only code paths.

**"Zero Core Modifications"**: External plugins can add handlers and pipeline steps without touching Data Machine plugin code - purely hook-based extensibility.

**"Programmatic Everything"**: Forms, validation, pipelines, and templates generated from data structures - no hardcoded implementations needed.