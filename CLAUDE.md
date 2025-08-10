# CLAUDE.md

Data Machine is an AI-first WordPress plugin that transforms WordPress sites into content processing platforms through a Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**"Plugins Within Plugins"**: Self-registering component system with 100% filter-based dependencies, eliminating traditional dependency injection while maintaining modularity.

**Pipeline+Flow System**: Pipelines define reusable workflow templates, Flows execute configured instances with handler settings and scheduling.

**Filter-Based Design**: Every service uses `apply_filters()` with pure discovery patterns. Components self-register via dedicated `*Filters.php` files.

**Architectural Consistency**: All components follow consistent admin-only patterns with zero mixed architectural approaches, user-scoped code, or inconsistent patterns.

**Two-Layer Architecture**:
- **Pipelines**: Reusable workflow templates with step sequences (positions 0-99)
- **Flows**: Configured instances with handler settings and scheduling (auto-created "Draft Flow" for new pipelines)

## AJAX Handler Architecture

**Universal AJAX Routing**: Clean architecture using `dm_ajax_route` action hook with automatic handler discovery. Page actions handle business logic, modal actions handle UI/templates.

**Registration Pattern**:
```php
add_action('wp_ajax_dm_add_step', fn() => do_action('dm_ajax_route', 'dm_add_step', 'page'));
add_action('wp_ajax_dm_get_template', fn() => do_action('dm_ajax_route', 'dm_get_template', 'modal'));
add_action('wp_ajax_dm_get_modal_content', [$modal_ajax, 'handle_get_modal_content']);
```

**Handler Resolution**: `dm_add_step` → `handle_add_step()` method via admin page configuration.

## Current Implementation Status

**Core Systems**: Complete filter-based architecture, pipeline execution, AI integration, handler framework, action hook system, template context resolution.

**Completed Features**: Pipeline+Flow architecture, multi-provider AI integration, filter-based dependencies, AJAX pipeline builder, universal modal system, template rendering, automatic "Draft Flow" creation, universal AJAX routing, handler directive system, action scheduler integration, template context resolution system, files repository with cleanup.

**AI Processing Architecture**: Complete whole-packet processing with rich content support (text, files, mixed content) and handler directive injection.

**Action Hook System**: Central "button press" style hooks eliminate code duplication across components (dm_run_flow_now, dm_update_job_status, dm_execute_step, dm_log, dm_ajax_route, dm_pipeline_auto_save, dm_mark_item_processed).

## Database Schema

**Core Tables**:
- **wp_dm_jobs**: job_id, pipeline_id, flow_id, status, created_at, started_at, completed_at
- **wp_dm_pipelines**: pipeline_id, pipeline_name, step_configuration (longtext NULL), created_at, updated_at
- **wp_dm_flows**: flow_id, pipeline_id, flow_name, flow_config (longtext NOT NULL), scheduling_config (longtext NOT NULL)
- **wp_dm_processed_items**: id, flow_id, source_type, item_identifier, processed_timestamp
- **wp_dm_remote_locations**: location_id, location_name, target_site_url, target_username, password, synced_site_info (JSON), enabled_post_types (JSON), enabled_taxonomies (JSON), last_sync_time, created_at, updated_at

**Table Relationships**:
- Flows reference Pipelines (many-to-one): `flows.pipeline_id → pipelines.pipeline_id`
- Jobs reference Flows (many-to-one): `jobs.flow_id → flows.flow_id`
- Admin-only architecture: No user_id columns in any table

## Filter Reference

```php
// Core services - Action hooks and direct instantiation
// Logger operations via dm_log action hook (see Action Hook System)
do_action('dm_log', 'error', 'Message', ['context' => 'data']);
// ProcessingOrchestrator is core engine - direct instantiation
$orchestrator = new \DataMachine\Engine\ProcessingOrchestrator();
// JobCreator operations via dm_run_flow_now action hook (see Action Hook System)
do_action('dm_run_flow_now', $flow_id, 'manual_execution');

// HTTP requests - Use dm_send_request action hook
$result = null;
do_action('dm_send_request', 'GET', $url, $args, 'API Context', $result);
// $result['success'] boolean, $result['data'] response, $result['error'] message

// Database services - Collection discovery
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;

// Handler discovery - Type filtering
$all_handlers = apply_filters('dm_handlers', []);
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');
$specific_handler = $all_handlers['twitter'] ?? null;

// Authentication, Settings, Directives - Collection patterns
$all_auth = apply_filters('dm_get_auth_providers', []);
$all_settings = apply_filters('dm_get_handler_settings', []);
$all_directives = apply_filters('dm_get_handler_directives', []);

// Context services - Parameter-based discovery
$job_context = apply_filters('dm_get_context', null, $job_id);

// Step discovery and configuration
$all_steps = apply_filters('dm_steps', []);
$step_settings = apply_filters('dm_step_settings', []);

// Template rendering with context resolution
$content = apply_filters('dm_render_template', '', 'modal/handler-settings', $data);

// Files repository - Direct instantiation (handler-specific infrastructure)
$files_repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
```

## Development Priorities

**Immediate Tasks**:
1. Complete authentication implementations for remaining handlers (Google Sheets, Reddit OAuth 2.0)
2. Implement handler directives for social media handlers (Twitter, Facebook, Threads, Bluesky)
3. Comprehensive testing of ProcessedItems system across all handlers
4. Expand template context resolution system for additional handler-specific templates

**Available for Extension**:
- Handler directive framework (WordPress implementation complete)
- Filter-based authentication system (Twitter and Facebook implementations complete)
- Pipeline execution engine with DataPacket flow
- Universal modal and template rendering systems

## Development Commands

```bash
# Setup
composer install && composer dump-autoload

# Testing
composer test                # All PHPUnit tests
composer test:unit           # Unit tests only
composer test:integration    # Integration tests only
composer test:coverage       # HTML coverage report

# AI HTTP Client (subtree)
cd lib/ai-http-client/ && composer test && composer analyse

# Debugging
window.dmDebugMode = true;   # Browser debugging
define('WP_DEBUG', true);    # Enable conditional error_log

# Common fixes
composer dump-autoload      # Fix class loading
php -l file.php             # Syntax check

# Service Discovery Validation
define('WP_DEBUG', true); # Enable debugging
error_log('Database services: ' . print_r(apply_filters('dm_db', []), true));
error_log('Handlers: ' . print_r(apply_filters('dm_handlers', []), true));
error_log('Steps: ' . print_r(apply_filters('dm_steps', []), true));
```

## Components

**Core Services**: Logger (3-level system: debug, error, none with runtime configuration), Database, Orchestrator, AI Client (multi-provider: OpenAI, Anthropic, Google, Grok, OpenRouter with step-aware configuration)

**Handlers**:
- Fetch: Files, Reddit, RSS, WordPress, Google Sheets (5 handlers)
- Publish: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (6 handlers)
- Receiver: Webhook framework (stub implementation)
- Total: 11 handlers + 1 stub framework

**Authentication Implementation Status**:
- Twitter: ✅ Complete OAuth 1.0a implementation
- Facebook: ✅ Complete OAuth 2.0 implementation
- Threads: ✅ Complete OAuth 2.0 implementation with long-lived token refresh
- Bluesky: ✅ Complete authentication implementation with app password system
- Google Sheets: ⚠️ Authentication framework structure ready for OAuth 2.0 implementation
- Reddit: ⚠️ Authentication framework structure ready for OAuth 2.0 implementation
- WordPress: Uses Remote Locations (site-to-site authentication)
- Files, RSS: No authentication required

**Admin**: AJAX pipeline builder, job management, universal modal system, universal template rendering

**Key Principles**:
- Zero constructor injection - all services via `apply_filters()`
- Components self-register via `*Filters.php` files
- Engine agnostic - no hardcoded step types in `/inc/engine/`
- Position-based execution (0-99) with DataPacket flow
- Universal template rendering via filter-based discovery system

## Logger System

**Three-Level Logging**: Log system with configurable levels and runtime control.

**Log Levels**:
- `debug`: Full logging (all debug, info, warning, error, critical messages)
- `error`: Problems only (warning, error, critical messages)  
- `none`: Disable logging completely

**Runtime Configuration**:
```php
// Use dm_log action hook for logging operations (preferred)
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);

// Direct access if needed for configuration
$logger = new \DataMachine\Engine\Logger();

// Get current level
$current_level = $logger->get_level(); // Returns 'debug', 'error', or 'none'

// Set new level
$logger->set_level('error'); // Changes effective immediately

// Available levels
$levels = \DataMachine\Engine\Logger::get_available_log_levels(); // Returns array with descriptions
```

**Default Configuration**: Log level defaults to `error` (problems only) for production environments while providing full debugging capability when needed.

**Log Management**:
```php
// File operations
$log_path = $logger->get_log_file_path();
$log_size = $logger->get_log_file_size(); // Returns MB
$recent_logs = $logger->get_recent_logs(100); // Last 100 lines
$logger->clear_logs(); // Remove all log files
$logger->cleanup_log_files(10, 30); // Auto-cleanup based on size/age
```

## DataPacket Structure

Universal data contract between pipeline steps supporting rich content:
```php
[
    'type' => 'rss|files|ai|wordpress|twitter', // Source/processing type
    'content' => ['body' => $content, 'title' => $title],
    'metadata' => [
        'source_type' => $source_type,
        'file_path' => $file_path,     // For file inputs
        'mime_type' => $mime_type,     // For file inputs
        'model' => $model,             // For AI outputs
        'provider' => $provider,       // For AI outputs
        'usage' => $usage_stats        // For AI outputs
    ],
    'timestamp' => $timestamp
]
```

**Step Implementation with Rich Content**:
```php
class MyStep {
    public function execute(int $job_id, array $data_packet, array $step_config): array {
        foreach ($data_packet as $data) {
            // Handle text content
            $content = $data['content']['body'] ?? '';
            
            // Handle file content
            $file_path = $data['metadata']['file_path'] ?? '';
            if ($file_path && file_exists($file_path)) {
                // Process file content
            }
            
            // Process based on type
            $type = $data['type'] ?? 'unknown';
        }
        return $data_packet; // Return updated data packet array
    }
}
```

**AI Processing**: AI steps process ALL data packet entries simultaneously for complete pipeline context and rich content support (files, text, mixed inputs). The AI step automatically injects handler directives for subsequent steps to provide platform-specific formatting instructions.

## ProcessingOrchestrator Architecture

**Pure Execution Engine**: Lean architecture designed exclusively for pipeline step execution with zero database dependencies or redundant data preparation.

**Architectural Principles**:
- **JobCreator Builds Configuration**: Complete pipeline configuration assembled by JobCreator before execution
- **Orchestrator Executes Steps**: Receives pre-built configuration and executes steps in sequence
- **Zero Database Dependencies**: No database operations during execution - all data provided in configuration
- **Pure Pipeline Flow**: DataPacket array flows through steps with Action Scheduler coordination

**Execution Flow**:
1. Receives pre-built configuration from JobCreator via Action Scheduler
2. Gets step data directly from provided pipeline_config array
3. Instantiates step classes via filter-based discovery
4. Passes complete job configuration to steps for self-configuration
5. Schedules next step with updated DataPacket array

**Step Requirements**:
- **Parameter-less Constructor**: Steps instantiated without dependencies
- **Standard Execute Method**: `execute(int $job_id, array $data_packet = [], array $step_config = []): array`
- **Return Updated DataPacket**: Must return modified data packet array for next step
- **Self-Configuration**: Steps extract needed configuration from step_config parameter (merged step and flow configuration)

**Action Scheduler Integration**:
```php
// Static callback method for direct Action Scheduler execution
ProcessingOrchestrator::execute_step_callback($job_id, $execution_order, $pipeline_id, $flow_id, $job_config, $data_packet);

// Called via dm_execute_step action hook
do_action('dm_execute_step', $job_id, $execution_order, $pipeline_id, $flow_id, $pipeline_config, $previous_data_packets);
```

## Context-Specific Step Card Templates

**Dedicated Template Architecture**: Step card rendering uses context-specific templates that provide specialized functionality for pipeline and flow contexts.

**Template Structure**:
- **Pipeline Context**: `pipeline-step-card.php` - Shows "Add Step" buttons, delete/configure actions, structural-only display
- **Flow Context**: `flow-step-card.php` - Shows "Add Handler" buttons, handler management UI, configuration details

**Template Usage Pattern**:
```php
// Pipeline step card
$content = apply_filters('dm_render_template', '', 'page/pipeline-step-card', [
    'step' => $step_data,
    'pipeline_id' => $pipeline_id,
    'is_first_step' => $is_first_step
]);

// Flow step card  
$content = apply_filters('dm_render_template', '', 'page/flow-step-card', [
    'step' => $step_data,
    'flow_id' => $flow_id,
    'flow_config' => $flow_config,
    'is_first_step' => $is_first_step
]);
```

**Consistent Container Architecture**: Both templates use `dm-step-container` wrapper with consistent data attributes and internal `dm-step-card` structure while providing context-specific functionality.

**Step Type Architecture**: 
- **AI Steps** (`'ai'`): No handlers, pipeline-level configuration only, process all data packets
- **Fetch/Publish Steps** (`'fetch'`, `'publish'`): Use handlers, flow-level handler selection and configuration
- **Handler Logic**: `$step_uses_handlers = ($step_type !== 'ai')` determines whether step shows handler management UI

## Handler Directive System

**Elegant Filter-Based Architecture**: Handlers register AI-specific directives via `dm_get_handler_directives` filter that AI steps automatically inject into system prompts.

**Implementation Pattern**:
```php
// Handler registration in handler filters file
add_filter('dm_get_handler_directives', function($directives) {
    $directives['wordpress_publish'] = 'When publishing to WordPress, format your response as:\nTITLE: [compelling post title]\nCATEGORY: [single category name]\nTAGS: [comma,separated,tags]\nCONTENT:\n[your content here]';
    return $directives;
});

// Automatic injection in AI step processing
$all_directives = apply_filters('dm_get_handler_directives', []);
$handler_directive = $all_directives[$next_step['handler']['handler_slug']] ?? '';
if (!empty($handler_directive)) {
    $system_prompt .= "\n\n" . $handler_directive;
}
```

**Current Implementation**:
- WordPress handler: Complete directive for structured content formatting
- Twitter, Facebook, Threads, Bluesky handlers: Framework ready for directive implementation
- AI steps automatically discover and inject appropriate directives based on pipeline step sequence
- Handlers self-register directives via filter system using consistent registration pattern

**Architecture Benefits**:
- **Contextual AI Guidance**: AI receives specific formatting instructions for target platforms
- **Self-Registering**: Handlers declare their own AI requirements via filters
- **Pipeline-Aware**: AI steps automatically detect next step handler and apply appropriate directive
- **Extensible**: Any handler can register custom AI directives following the same pattern

## Arrow Rendering Architecture

**is_first_step Pattern**: Simplified arrow logic using binary flag for consistent rendering across all contexts.

**Universal Arrow Logic**: Single template pattern with context validation:
```php
// Universal arrow logic with contextual validation
if (!isset($is_first_step)) {
    if ($is_empty) {
        // Empty steps default to showing arrow
        $is_first_step = false;
    } else {
        // Populated steps require proper arrow logic
        throw new \InvalidArgumentException('Step card template requires is_first_step parameter for populated steps');
    }
}
if (!$is_first_step): ?>
    <div class="dm-step-arrow">
        <span class="dashicons dashicons-arrow-right-alt"></span>
    </div>
<?php endif;
```

**JavaScript Template Integration**: Calculate `is_first_step` when requesting templates:
```javascript
// Calculate is_first_step for template requests
const nonEmptySteps = $container.find('.dm-step:not(.dm-step-card--empty)').length;
const isFirstRealStep = nonEmptySteps === 0;

this.requestTemplate('page/pipeline-step-card', {
    step: stepData,
    is_first_step: isFirstRealStep,
    pipeline_id: this.pipelineId
});
```

## Template Rendering Architecture

**JavaScript Template Requesting**: JavaScript requests pre-rendered templates from PHP to eliminate HTML generation inconsistencies between page load and AJAX updates.

**Core Principle**: PHP templates are single source of HTML structure, JavaScript handles pure DOM manipulation.

**Template Requesting Pattern**:
```javascript
requestTemplate(templateName, templateData) {
    return $.ajax({
        url: dmPipelineBuilder.ajax_url, type: 'POST',
        data: {
            action: 'dm_pipeline_ajax', pipeline_action: 'get_template',
            template: templateName, template_data: JSON.stringify(templateData),
            nonce: dmPipelineBuilder.pipeline_ajax_nonce
        }
    }).then(response => response.data.html);
}

// Usage with arrow logic
const isFirstRealStep = $container.find('.dm-step:not(.dm-step-card--empty)').length === 0;
this.requestTemplate('page/step-card', {step: stepData, is_first_step: isFirstRealStep});
```

**AJAX Handler Pattern**: Return data only, request templates separately:
```php
wp_send_json_success(['step_data' => $data]); // NOT step_html
```

**Benefits**: Consistent HTML structure, single source of truth, clean separation, seamless `dm_render_template` integration.

## Modal System

**Three-File JavaScript Architecture**: Universal modal lifecycle (core-modal.js), pipeline content interactions (pipelines-modal.js), flow operations (flow-builder.js).

**Core Components**:
- **core-modal.js**: Modal lifecycle, AJAX loading via `dm_get_modal_content`, accessibility focus management
- **pipelines-modal.js**: OAuth workflows, file uploads, handler configuration UI 
- **flow-builder.js**: Flow management, handler addition, execution via "Run Now"

**Data-Attribute Communication**:
```javascript
<div class="dm-step-selection-card dm-modal-close" 
     data-template="add-step-action" data-context='{"step_type":"fetch"}'>
```

**Modal Registration**:
```php
add_filter('dm_modals', function($modals) {
    $modals['step-selection'] = [
        'template' => 'modal/step-selection-cards',
        'title' => __('Select Step Type', 'data-machine')
    ];
    return $modals;
});
```

**Handler-Specific Templates**: Automatic fallback from `modal/handler-settings/{handler}` to universal `modal/handler-settings`.

**AJAX Endpoints**: 
- `dm_get_modal_content` - General modal loading
- `dm_pipeline_ajax` with `get_template` - Dynamic template rendering

**Universal Reusability**: Include `core-modal.js`, add `.dm-modal-open` buttons with data attributes, register via `dm_modals` filter.


## Template Context Resolution System

**Sophisticated Template Requirements**: PipelinesFilters.php implements comprehensive template context resolution with automatic field generation and data extraction.

**Key Features**:
- **Required Field Validation**: Ensures critical context data with debug logging
- **Auto-Generated Composite IDs**: Creates flow_step_id patterns (`{pipeline_step_id}_{flow_id}`)
- **Data Extraction**: Extracts nested data from step/flow/pipeline objects using dot notation
- **Pattern Substitution**: Complex ID generation like `{step.pipeline_step_id}_{flow_id}`
- **Multiple Extraction Points**: `extract_from_step`, `extract_from_flow`, `extract_from_pipeline`

**Template Requirements Pattern**:
```php
'modal/handler-settings/[handler]' => [
    'required' => ['handler_slug', 'step_type'],
    'optional' => ['flow_id', 'pipeline_id', 'pipeline_step_id', 'flow_step_id']
],
'page/flow-step-card' => [
    'required' => ['flow_id', 'pipeline_id', 'step', 'flow_config'],
    'extract_from_step' => ['pipeline_step_id', 'step_type'],
    'auto_generate' => ['flow_step_id' => '{step.pipeline_step_id}_{flow_id}']
]
```

## Files Handler Repository System

**Direct Instantiation Pattern**: Files handler implements dedicated repository for file management with flow-specific isolation.

**Repository Usage**:
```php
// Direct instantiation - Files handler internal infrastructure
$repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
```

**Key Features**:
- **Flow-Specific Isolation**: Files stored with `flow_step_id` context (`{pipeline_step_id}_{flow_id}`)
- **Automatic Cleanup**: Weekly scheduled cleanup of old files via ActionScheduler
- **Security Validation**: File size limits (32MB) and dangerous extension blocking
- **Processing Status Integration**: Files marked as processed via ProcessedItems service
- **AJAX File Upload**: Secure upload handling with nonce verification
- **Repository Pattern**: Direct instantiation as Files handler internal infrastructure

**File Management Operations**:
```php
// Store file with handler context
$stored_path = $repository->store_file($tmp_name, $filename, $flow_step_id);

// Get all files for specific flow step
$files = $repository->get_all_files($flow_step_id);

// Cleanup old files
$deleted_count = $repository->cleanup_old_files(7); // 7 days
```


## Action Hook System

**Central "Button Press" Hooks**: Eliminates code duplication through centralized action handlers with consistent service discovery and error handling.

**Core Action Hooks**:
```php
// Flow execution - eliminates 40+ lines of duplication per call site
do_action('dm_run_flow_now', $flow_id, 'manual_execution');

// Intelligent job status updates with automatic method selection
do_action('dm_update_job_status', $job_id, 'processing', 'start'); // Uses start_job()
do_action('dm_update_job_status', $job_id, 'completed', 'complete'); // Uses complete_job()
do_action('dm_update_job_status', $job_id, 'failed', 'step_execution_failure');

// Core pipeline step execution with enhanced error handling
do_action('dm_execute_step', $job_id, $execution_order, $pipeline_id, $flow_id, $job_config, $data_packet);

// Central logging eliminating logger service discovery
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);

// Universal AJAX routing eliminating 132 lines of duplication
do_action('dm_ajax_route', 'dm_add_step', 'page');

// Universal processed item marking across all handlers
do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);

// Universal HTTP request handling
$result = null;
do_action('dm_send_request', 'POST', $url, $args, 'API Call', $result);
// Result: ['success' => bool, 'data' => response_array, 'error' => error_message]
```

**HTTP Request System**: The `dm_send_request` action hook provides comprehensive HTTP functionality with all HTTP methods (GET, POST, PUT, DELETE, PATCH), intelligent status validation, enhanced error handling, automatic logging, and structured response format.

**Benefits**: Eliminates duplication, consistent error handling, unified service discovery, simplified call sites (40+ lines → single action call), centralized logic in DataMachineActions.php.

## Critical Rules

**Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory  
**Discovery Pattern**: Prefer collection-based discovery `$all_services = apply_filters('dm_get_services', []); $service = $all_services[$key] ?? null;` for most services. Parameter-based filters are acceptable for context-specific services like `dm_get_context` which requires job_id parameter  
**Service Access**: Always use filter-based discovery patterns - never `new ServiceClass()` direct instantiation  
**Template Rendering**: Always use `apply_filters('dm_render_template', '', $template, $data)` - never direct template methods - automatic context resolution applied  
**Action Hooks**: Use central action hooks for common operations - eliminates service discovery duplication
**Field Naming**: Use `pipeline_step_id` consistently throughout system - matches database schema and UUID4 requirements
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)  
**CSS Namespace**: All admin CSS must use `dm-` prefix  

**Database Tables**:
- `wp_dm_pipelines`: Template definitions with step sequences (pipeline_step_id UUID4 fields consistently used)
- `wp_dm_flows`: Configured instances with handler settings (auto-created "Draft Flow" for new pipelines)
- `wp_dm_jobs`: Execution records with Action Scheduler integration
- `wp_dm_processed_items`: Duplicate tracking with flow_id, source_type, item_identifier
- `wp_dm_remote_locations`: Site-to-site authentication for WordPress handlers

## ProcessedItems Architecture

**Database Layer Location**: ProcessedItems system located in `/inc/core/database/ProcessedItems/` following consistent database component architecture.

**Components**:
- **ProcessedItems.php**: Core database operations, duplicate tracking services, and table management
- **ProcessedItemsFilters.php**: Self-registration via filter-based discovery patterns

**Discovery Registration**: Uses standard database service collection pattern:
```php
// ProcessedItems discovery pattern - database service collection only
$all_databases = apply_filters('dm_db', []);
$processed_items_service = $all_databases['processed_items'] ?? null;
```

## Pipeline+Flow Lifecycle

**Automatic Flow Creation**: Every new pipeline automatically creates a "Draft Flow" instance for immediate workflow execution.

## Flow Management

**Flow Operations**: Flows support deletion with cascade cleanup of associated jobs. Jobs table displays "Pipeline → Flow" format for clear relationship visibility.

## Component Registration

**Self-Registration Pattern**: Each component registers all services in dedicated `*Filters.php` files using consistent filter-based discovery patterns. See Critical Rules section for complete collection-based discovery guidelines.


## Admin Page Architecture

**Direct Template Rendering Pattern**: AdminMenuAssets.php uses standardized `"page/{$page_slug}-page"` template pattern for unified rendering.

**Unified Registration**: Admin pages register with assets, templates, and configuration in a single `dm_admin_pages` filter:

```php
add_filter('dm_admin_pages', function($pages) {
    $pages['jobs'] = [
        'page_title' => __('Jobs', 'data-machine'),
        'capability' => 'manage_options', 'position' => 20,
        'templates' => __DIR__ . '/templates/',
        'assets' => ['css' => [...], 'js' => [...]]
    ];
    return $pages;
});
```

## Universal Template Rendering

**Filter-Based Template Discovery**: Templates discovered from admin page registration and rendered through universal `dm_render_template` filter system.

**Template Usage**:
```php
$content = apply_filters('dm_render_template', '', 'modal/handler-settings-form', $data);
```

**JavaScript Template Integration**: Use template requesting pattern for dynamic HTML updates:
```javascript
requestTemplate(template, data) {
    return $.ajax({
        action: 'dm_pipeline_ajax', pipeline_action: 'get_template',
        template: template, template_data: JSON.stringify(data)
    }).then(response => response.data.html);
}
```

**AJAX Handler Pattern**: Return data only, never HTML - JavaScript requests templates separately.

