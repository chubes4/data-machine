# CLAUDE.md

Data Machine is an AI-first WordPress plugin that transforms WordPress sites into content processing platforms through a Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**"Plugins Within Plugins"**: Self-registering component system with 100% filter-based dependencies, eliminating traditional dependency injection while maintaining modularity.

**Pipeline+Flow System**: Pipelines define reusable workflow templates, Flows execute configured instances with handler settings and scheduling.

**Filter-Based Design**: Every service uses `apply_filters()` with pure discovery patterns. Components self-register via dedicated `*Filters.php` files.

**Architectural Consistency**: All components follow consistent admin-only patterns with zero mixed architectural approaches, user-scoped code, or inconsistent patterns.

**Admin-Only Authentication Architecture**: Complete site-level authentication system with zero user_id dependencies. All OAuth integrations use admin-global storage with `manage_options` capability checks, providing unified authentication for all site administrators.

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

**Action Hook System**: Central "button press" style hooks eliminate code duplication across components (dm_run_flow_now, dm_update_job_status, dm_execute_step, dm_log, dm_ajax_route, dm_auto_save, dm_mark_item_processed).

**NO MANUAL SAVE Architecture**: Complete auto-save system eliminates manual save operations. All user interactions trigger automatic pipeline persistence via `dm_auto_save` action hook with real-time status indicators.

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
// Flow execution via dm_run_flow_now action hook (see Action Hook System)
do_action('dm_run_flow_now', $flow_id, 'manual_execution');

// HTTP requests - Filter-based discovery (all handlers)
$response = apply_filters('dm_request', [], 'GET', $url, $args);

// Database services - Collection discovery
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;

// Handler discovery - Type filtering
$all_handlers = apply_filters('dm_handlers', []);
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');
$specific_handler = $all_handlers['twitter'] ?? null;

// Authentication services - Collection discovery with admin-only patterns
$all_auth = apply_filters('dm_auth_providers', []);
$twitter_auth = $all_auth['twitter'] ?? null;
if ($twitter_auth && $twitter_auth->is_authenticated()) {
    $account_info = $twitter_auth->get_account_details(); // Admin-global authentication
}

// Handler Settings and Directives - Collection patterns  
$all_settings = apply_filters('dm_handler_settings', []);
$all_directives = apply_filters('dm_handler_directives', []);

// Context services - Available via database collections
// Use: $all_databases = apply_filters('dm_db', []); to access context services

// Step discovery and configuration
$all_steps = apply_filters('dm_steps', []);
$step_settings = apply_filters('dm_step_settings', []);

// Template rendering with context resolution
$content = apply_filters('dm_render_template', '', 'modal/handler-settings', $data);

// Files repository - Direct instantiation (handler-specific infrastructure)
$files_repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
```

## Development Priorities

**Available for Extension**:
- Handler directive framework (WordPress implementation complete, framework ready for all handlers)
- Filter-based authentication system (all handlers complete)
- Pipeline execution engine with DataPacket flow
- Universal modal and template rendering systems
- File upload system with AJAX processing and status tracking
- ProcessedItems duplicate prevention system

## Development Commands

```bash
# Setup
composer install && composer dump-autoload

# Testing
composer test                # All PHPUnit tests
composer test:unit           # Unit tests only
composer test:integration    # Integration tests only
composer test:coverage       # HTML coverage report

# AI HTTP Client (integrated library)
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

# Authentication System Debugging
error_log('Auth providers: ' . print_r(apply_filters('dm_auth_providers', []), true));
```

## Components

**Core Services**: Logger (3-level system: debug, error, none with runtime configuration), Database, Execution Engine (actions/ architecture), AI HTTP Client (integrated library: OpenAI, Anthropic, Google, Grok, OpenRouter with step-aware configuration and component UI system)

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
- Google Sheets: ✅ Complete OAuth 2.0 implementation
- Reddit: ✅ Complete OAuth 2.0 implementation
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

// Logger service discovery via filter-based pattern
$current_level = apply_filters('dm_log_file', '', 'get_level');
$levels = apply_filters('dm_log_file', [], 'get_available_levels');
```

**Default Configuration**: Log level defaults to `error` (problems only) for production environments while providing full debugging capability when needed.

**Log Management**:
```php
// File operations via logger service discovery
$log_path = apply_filters('dm_log_file', '', 'get_path');
$log_size = apply_filters('dm_log_file', 0, 'get_size'); // Returns MB
$recent_logs = apply_filters('dm_log_file', [], 'get_recent', 100); // Last 100 lines
// Log management operations use dm_log action hook
do_action('dm_log', 'clear_logs'); // Remove all log files
do_action('dm_log', 'cleanup', 10, 30); // Auto-cleanup based on size/age
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
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        foreach ($data as $data_item) {
            // Handle text content
            $content = $data_item['content']['body'] ?? '';
            
            // Handle file content
            $file_path = $data_item['metadata']['file_path'] ?? '';
            if ($file_path && file_exists($file_path)) {
                // Process file content
            }
            
            // Process based on type
            $type = $data_item['type'] ?? 'unknown';
        }
        return $data; // Return updated data packet array
    }
}
```

**AI Processing**: AI steps process ALL data packet entries simultaneously for complete pipeline context and rich content support (files, text, mixed inputs). The AI step automatically injects handler directives for subsequent steps to provide platform-specific formatting instructions.

## Execution Engine Architecture

**Pure Functional Execution**: Three-action engine design located in `/inc/engine/actions/Engine.php` providing complete pipeline execution with KISS compliance.

**Core Execution Actions**:
- **dm_run_flow_now**: Pipeline initiation ("start button")
- **dm_execute_step**: Core step execution with functional orchestration
- **dm_schedule_next_step**: Action Scheduler step transitions

**Execution Flow**:
1. `dm_run_flow_now` creates job via organized `dm_create` action
2. `dm_schedule_next_step` schedules first step with Action Scheduler
3. `dm_execute_step` loads step config, instantiates step class, executes step
4. Step returns updated DataPacket array for next step
5. `dm_schedule_next_step` continues pipeline flow

**Step Requirements**:
- **Parameter-less Constructor**: Steps instantiated without dependencies
- **Standard Execute Method**: `execute($flow_step_id, array $data = [], array $step_config = []): array`
- **Return Updated DataPacket**: Must return modified data packet array for next step
- **Self-Configuration**: Steps extract needed configuration from step_config parameter

**Action Scheduler Integration**:
```php
// Core execution via Action Scheduler
as_schedule_single_action(time(), 'dm_execute_step', [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'data' => $data
], 'data-machine');

// Called via action hook system
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
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

**Elegant Filter-Based Architecture**: Handlers register AI-specific directives via `dm_handler_directives` filter that AI steps automatically inject into system prompts.

**Implementation Pattern**:
```php
// Handler registration in handler filters file
add_filter('dm_handler_directives', function($directives) {
    $directives['wordpress_publish'] = 'When publishing to WordPress, format your response as:\nTITLE: [compelling post title]\nCATEGORY: [single category name]\nTAGS: [comma,separated,tags]\nCONTENT:\n[your content here]';
    return $directives;
});

// Automatic injection in AI step processing
$all_directives = apply_filters('dm_handler_directives', []);
$handler_directive = $all_directives[$next_step['handler']['handler_slug']] ?? '';
if (!empty($handler_directive)) {
    $system_prompt .= "\n\n" . $handler_directive;
}
```

**Current Implementation**:
- WordPress handler: ✅ Complete directive implementation with structured content formatting
- Twitter, Facebook, Threads, Bluesky, Google Sheets handlers: ⭕ Framework ready, no directives implemented
- AI steps automatically discover and inject appropriate directives based on pipeline step sequence
- Handlers self-register directives via `dm_handler_directives` filter using consistent registration pattern

**Architecture Benefits**:
- **Contextual AI Guidance**: AI receives specific formatting instructions for target platforms
- **Self-Registering**: Handlers declare their own AI requirements via filters
- **Pipeline-Aware**: AI steps automatically detect next step handler and apply appropriate directive
- **Extensible**: Any handler can register custom AI directives following the same pattern

**Framework Implementation**:
- **Filter Registration**: Engine provides `dm_handler_directives` filter discovery
- **Automatic Discovery**: AI steps query all registered directives via `apply_filters('dm_handler_directives', [])`
- **Context Injection**: AI steps automatically inject appropriate directive based on next pipeline step
- **Handler Independence**: Each handler implements own directive registration without cross-dependencies

**Adding New Handler Directives**:
```php
// In handler's *Filters.php file
add_filter('dm_handler_directives', function($directives) {
    $directives['twitter_publish'] = 'For Twitter, keep posts under 280 characters...';
    $directives['facebook_publish'] = 'For Facebook, use engaging headlines and...';
    return $directives;
});
```


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
            action: 'dm_get_template',
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

**Filter-Based Template Discovery**: Templates discovered from admin page registration and rendered through universal `dm_render_template` filter system with automatic context resolution.

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

**Context Resolution Process**:
1. **Template Registration**: Templates register requirements in PipelinesFilters.php
2. **Data Validation**: Required fields verified before template rendering
3. **Extraction Processing**: Multi-source data extraction (step, flow, pipeline objects)
4. **Pattern Generation**: Complex ID patterns resolved using substitution
5. **Context Assembly**: Final context array prepared for template rendering
6. **Debug Logging**: Missing fields logged for development troubleshooting

**Implementation Example**:
```php
// Template context resolution for flow step cards
$template_requirements = [
    'required' => ['flow_id', 'step'],
    'extract_from_step' => ['pipeline_step_id'],
    'auto_generate' => ['flow_step_id' => '{step.pipeline_step_id}_{flow_id}']
];

// Automatic resolution produces:
$resolved_context = [
    'flow_id' => 123,
    'step' => $step_object,
    'pipeline_step_id' => 'uuid-from-step-object',
    'flow_step_id' => 'uuid-from-step-object_123' // Auto-generated
];
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

**Cleanup System Architecture**:
- **ActionScheduler Integration**: Weekly cleanup tasks scheduled automatically
- **Age-Based Cleanup**: Files older than configurable threshold (default: 7 days)
- **Flow-Aware Deletion**: Only removes files from completed or inactive flows
- **Storage Management**: Prevents unlimited disk usage from abandoned uploads
- **Cleanup Logging**: All cleanup operations logged for monitoring

**Cleanup Operations**:
```php
// Manual cleanup trigger
$deleted_count = $repository->cleanup_old_files(7); // Remove files older than 7 days

// Automatic scheduling (handled by FilesFilters.php)
// Weekly: wp_schedule_event() + 'dm_files_cleanup' hook
// Executes: $repository->cleanup_old_files() with default settings
```

**File Management Operations**:
```php
// Store file with handler context
$stored_path = $repository->store_file($tmp_name, $filename, $flow_step_id);

// Get all files for specific flow step
$files = $repository->get_all_files($flow_step_id);

// Cleanup old files
$deleted_count = $repository->cleanup_old_files(7); // 7 days
```

## File Upload System

**AJAX File Upload Architecture**: Complete file upload system with drag-and-drop support, auto-upload functionality, and real-time status tracking integrated with the Files handler.

**Core Components**:
- **file-uploads.js**: Dedicated JavaScript module handling file upload functionality
- **file-status-rows.php**: Template for rendering file status with processing indicators
- **FilesFilters.php**: Backend AJAX handlers for upload processing and status queries

**Upload Features**:
- **Auto-Upload**: Files automatically upload on selection via file input
- **Drag-and-Drop**: Optional drag-and-drop zone for file selection
- **Status Tracking**: Real-time processing status with visual indicators (pending/processed)
- **File Validation**: Size limits (32MB) and dangerous extension blocking
- **Flow Isolation**: Files stored with flow_step_id context for proper isolation

**Status Integration**:
```php
// File status tracking with ProcessedItems system
foreach ($files as $file) {
    $status_class = $file['is_processed'] ? 'processed' : 'pending';
    $status_icon = $file['is_processed'] ? 'dashicons-yes-alt' : 'dashicons-clock';
}
```

**JavaScript Integration**:
```javascript
// Auto-initialization on modal load
$(document).on('dm-core-modal-content-loaded', handleModalOpened);
// Auto-upload on file selection
$(document).on('change', '#dm-file-upload', handleFileAutoUpload);
// File refresh functionality
$(document).on('click', '.dm-refresh-files', handleRefreshFiles);
```


## Action Hook System

**Central "Button Press" Hooks**: Eliminates code duplication through centralized action handlers with consistent service discovery and error handling.

**Core Action Hooks** (organized in `/inc/engine/actions/`):
```php
// EXECUTION ENGINE (Engine.php)
// Pipeline initiation
do_action('dm_run_flow_now', $flow_id);
// Core step execution  
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
// Action Scheduler step transitions
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// ORGANIZED ACTIONS (Create.php, Update.php, Delete.php)
// CRUD operations
do_action('dm_create', 'job', ['pipeline_id' => $pipeline_id, 'flow_id' => $flow_id]);
do_action('dm_update_job_status', $job_id, 'completed', 'complete');
do_action('dm_delete', 'flow', $flow_id);

// UTILITY ACTIONS (DataMachineActions.php)
// Central logging eliminating logger service discovery
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
// Universal AJAX routing eliminating 132 lines of duplication
do_action('dm_ajax_route', 'dm_add_step', 'page');
// Universal processed item marking across all handlers
do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
// Central pipeline auto-save operations eliminating database service discovery
do_action('dm_auto_save', $pipeline_id);
```

**HTTP Request System**: Filter-based discovery for all handlers:
- **Universal Pattern**: `$response = apply_filters('dm_request', [], 'GET', $url, $args);`

**Architecture Organization**: 
- **Engine.php**: Core 3-action execution engine
- **Create.php, Update.php, Delete.php**: Organized CRUD operations
- **DataMachineActions.php**: Utility actions eliminating duplication

**Benefits**: WordPress-native action registration, clean file organization, eliminates duplication, consistent error handling, simplified call sites.

## Critical Rules

**Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory  
**Discovery Pattern**: Prefer collection-based discovery `$all_services = apply_filters('dm_get_services', []); $service = $all_services[$key] ?? null;` for most services  
**Service Access**: Always use filter-based discovery patterns - never `new ServiceClass()` direct instantiation  
**Template Rendering**: Always use `apply_filters('dm_render_template', '', $template, $data)` - never direct template methods - automatic context resolution applied  
**Action Hooks**: Use central action hooks for common operations - eliminates service discovery duplication
**Field Naming**: Use `pipeline_step_id` consistently throughout system - matches database schema and UUID4 requirements
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)  
**CSS Namespace**: All admin CSS must use `dm-` prefix
**Authentication Architecture**: NEVER use user_id dependencies - all authentication is admin-global with `manage_options` capability checks
**OAuth Storage**: Use consistent `{handler}_auth_data` option keys for all OAuth implementations  
**Authentication Discovery**: Always use `apply_filters('dm_auth_providers', [])` collection pattern - never direct instantiation  

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

## Admin Page Architecture

**Centralized Filter Registration**: Admin pages register via `dm_admin_pages` filter using standardized template patterns for unified rendering.

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


