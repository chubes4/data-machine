# CLAUDE.md

Data Machine: AI-first WordPress plugin transforming sites into content processing platforms via Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**Filter-Based Design**: Self-registering components with 100% filter-based discovery. All services via `apply_filters()` patterns, components self-register via `*Filters.php` files.

**Pipeline+Flow System**: 
- **Pipelines**: Reusable workflow templates with step sequences (positions 0-99)
- **Flows**: Configured instances with handler settings and scheduling
- **Auto-Creation**: New pipelines create "Draft Flow" automatically

**Admin-Only Architecture**: Site-level authentication with zero user_id dependencies. All OAuth via admin-global storage with `manage_options` checks.

## AJAX Architecture

**Universal Routing**: `dm_ajax_route` action hook with automatic handler discovery.
```php
add_action('wp_ajax_dm_add_step', fn() => do_action('dm_ajax_route', 'dm_add_step', 'page'));
add_action('wp_ajax_dm_get_template', fn() => do_action('dm_ajax_route', 'dm_get_template', 'modal'));
```

## System Architecture

**Action Hook System**: Central hooks eliminate code duplication (dm_run_flow_now, dm_execute_step, dm_log, dm_ajax_route, dm_auto_save, dm_mark_item_processed).

**Auto-Save**: All interactions trigger automatic persistence via `dm_auto_save` action hook.

**AI Processing**: Whole-packet processing with rich content support and handler directive injection.

## Database Schema

**Core Tables**:
- **wp_dm_jobs**: job_id, pipeline_id, flow_id, status, created_at, started_at, completed_at
- **wp_dm_pipelines**: pipeline_id, pipeline_name, step_configuration (longtext NULL), created_at, updated_at
- **wp_dm_flows**: flow_id, pipeline_id, flow_name, flow_config (longtext NOT NULL), scheduling_config (longtext NOT NULL)
- **wp_dm_processed_items**: id, flow_step_id, source_type, item_identifier, processed_timestamp
- **wp_dm_remote_locations**: location_id, location_name, target_site_url, target_username, password, synced_site_info (JSON), enabled_post_types (JSON), enabled_taxonomies (JSON), last_sync_time, created_at, updated_at

**Table Relationships**:
- Flows reference Pipelines (many-to-one): `flows.pipeline_id → pipelines.pipeline_id`
- Jobs reference Flows (many-to-one): `jobs.flow_id → flows.flow_id`
- Admin-only architecture: No user_id columns in any table

## Filter Reference

```php
// Core actions
do_action('dm_log', 'error', 'Message', ['context' => 'data']);
do_action('dm_run_flow_now', $flow_id);

// HTTP requests - 5 parameter signature
$response = apply_filters('dm_request', null, 'POST', $url, $args, 'Context Description');

// Database services
$all_databases = apply_filters('dm_db', []);
$db_jobs = $all_databases['jobs'] ?? null;

// Handler discovery
$all_handlers = apply_filters('dm_handlers', []);
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');

// Authentication
$all_auth = apply_filters('dm_auth_providers', []);
$twitter_auth = $all_auth['twitter'] ?? null;

// Steps and templates
$all_steps = apply_filters('dm_steps', []);
$content = apply_filters('dm_render_template', '', 'modal/handler-settings', $data);

// Handler directives
$all_directives = apply_filters('dm_handler_directives', []);

// Files repository - Direct instantiation
$files_repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();
```


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

**Handlers**:
- **Fetch**: Files, Reddit, RSS, WordPress, Google Sheets
- **Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets
- **AI Integration**: OpenAI, Anthropic, Google, Grok, OpenRouter

**Services**: Logger (3-level), Database, Execution Engine, Authentication, Template Rendering

**Principles**:
- Zero constructor injection - all services via `apply_filters()`
- Self-registering components via `*Filters.php` files
- Engine agnostic - no hardcoded step types in `/inc/engine/`
- Position-based execution (0-99) with DataPacket flow

## Logger System

**Levels**: `debug` (full logging), `error` (problems only), `none` (disabled). Defaults to `error`.

```php
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
do_action('dm_log', 'clear_logs');
do_action('dm_log', 'cleanup', 10, 30); // 10MB max, 30 days
```

## DataPacket Structure

Universal data contract:
```php
[
    'type' => 'rss|files|ai|wordpress|twitter',
    'content' => ['body' => $content, 'title' => $title],
    'metadata' => ['source_type', 'file_path', 'model', 'provider', 'usage'],
    'timestamp' => $timestamp
]
```

**Step Implementation**:
```php
class MyStep {
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        foreach ($data as $data_item) {
            $content = $data_item['content']['body'] ?? '';
            $type = $data_item['type'] ?? 'unknown';
            // Process content
        }
        return $data;
    }
}
```

## Execution Engine

**Three-Action Engine** (`/inc/engine/actions/Engine.php`):
- `dm_run_flow_now`: Pipeline initiation
- `dm_execute_step`: Core step execution  
- `dm_schedule_next_step`: Action Scheduler transitions

**Step Requirements**:
- Parameter-less constructor
- `execute($flow_step_id, array $data = [], array $step_config = []): array`
- Return updated DataPacket array

**Action Scheduler Integration**:
```php
as_schedule_single_action(time(), 'dm_execute_step', [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'data' => $data
], 'data-machine');
```

## Step Card Templates

**Context-Specific Templates**:
- `pipeline-step-card.php` - Add/configure steps
- `flow-step-card.php` - Handler management UI

**Step Types**:
- **AI Steps**: No handlers, pipeline-level config only
- **Fetch/Publish Steps**: Use handlers, flow-level configuration

```php
$content = apply_filters('dm_render_template', '', 'page/pipeline-step-card', [
    'step' => $step_data,
    'pipeline_id' => $pipeline_id,
    'is_first_step' => $is_first_step
]);
```

## Handler Directive System

**AI Integration**: Handlers register directives via `dm_handler_directives` filter. AI steps automatically inject platform-specific formatting instructions.

```php
add_filter('dm_handler_directives', function($directives) {
    $directives['wordpress_publish'] = 'Format as:\nTITLE: [title]\nCATEGORY: [category]\nTAGS: [tags]\nCONTENT:\n[content]';
    return $directives;
});

// Automatic injection in AI steps
$all_directives = apply_filters('dm_handler_directives', []);
$handler_directive = $all_directives[$next_step['handler']['handler_slug']] ?? '';
if (!empty($handler_directive)) {
    $system_prompt .= "\n\n" . $handler_directive;
}
```



## Template Rendering

**JavaScript Template Requesting**: PHP templates as single source of HTML, JavaScript handles DOM manipulation.

```javascript
requestTemplate(templateName, templateData) {
    return $.ajax({
        url: dmPipelineBuilder.ajax_url, type: 'POST',
        data: {
            action: 'dm_get_template',
            template: templateName, 
            template_data: JSON.stringify(templateData),
            nonce: dmPipelineBuilder.pipeline_ajax_nonce
        }
    }).then(response => response.data.html);
}
```

**AJAX Pattern**: Return data only, request templates separately:
```php
wp_send_json_success(['step_data' => $data]); // NOT HTML
```

## Modal System

**Components**:
- **core-modal.js**: Modal lifecycle, AJAX loading
- **pipelines-modal.js**: OAuth workflows, file uploads
- **flow-builder.js**: Flow management, execution

```php
add_filter('dm_modals', function($modals) {
    $modals['step-selection'] = [
        'template' => 'modal/step-selection-cards',
        'title' => __('Select Step Type', 'data-machine')
    ];
    return $modals;
});
```

**Usage**: Add `.dm-modal-open` buttons with data attributes, register via `dm_modals` filter.


## Template Context Resolution

**Auto-Generated Context**: Templates register requirements, system auto-generates composite IDs and extracts nested data.

```php
'page/flow-step-card' => [
    'required' => ['flow_id', 'step'],
    'extract_from_step' => ['pipeline_step_id'],
    'auto_generate' => ['flow_step_id' => '{step.pipeline_step_id}_{flow_id}']
]
```

## Files Repository

**Direct Instantiation**: Files handler repository with flow-specific isolation and automatic cleanup.

```php
$repository = new \DataMachine\Core\Handlers\Fetch\Files\FilesRepository();

// Operations
$stored_path = $repository->store_file($tmp_name, $filename, $flow_step_id);
$files = $repository->get_all_files($flow_step_id);
$deleted_count = $repository->cleanup_old_files(7); // 7 days
```

**Features**: Flow isolation, 32MB size limits, weekly cleanup via ActionScheduler.



## Action Hook System

**Central Hooks** (organized in `/inc/engine/actions/`):
```php
// Execution
do_action('dm_run_flow_now', $flow_id);
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// CRUD
do_action('dm_create', 'job', ['pipeline_id' => $pipeline_id, 'flow_id' => $flow_id]);
do_action('dm_update_job_status', $job_id, 'completed');

// Utilities
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
do_action('dm_ajax_route', 'dm_add_step', 'page');
do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
do_action('dm_auto_save', $pipeline_id);
```

## Critical Rules

**Engine Agnosticism**: Never hardcode step types in `/inc/engine/`
**Discovery Pattern**: Use `$all_services = apply_filters('dm_get_services', []); $service = $all_services[$key] ?? null;`
**Service Access**: Filter-based discovery only - never direct instantiation
**Template Rendering**: Always `apply_filters('dm_render_template', '', $template, $data)`
**Field Naming**: Use `pipeline_step_id` consistently (UUID4)
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()`
**CSS Namespace**: All admin CSS uses `dm-` prefix
**Authentication**: Admin-global only with `manage_options` checks
**OAuth Storage**: `{handler}_auth_data` option keys

## Database Architecture

**Tables**:
- `wp_dm_pipelines`: Template definitions (pipeline_step_id UUID4)
- `wp_dm_flows`: Configured instances with handler settings
- `wp_dm_jobs`: Execution records with Action Scheduler integration
- `wp_dm_processed_items`: Duplicate tracking (flow_step_id, source_type, item_identifier)
- `wp_dm_remote_locations`: Site-to-site authentication

**ProcessedItems**: Flow-step level tracking via database service collection:
```php
$all_databases = apply_filters('dm_db', []);
$processed_items_service = $all_databases['processed_items'] ?? null;
```

**Admin Pages**: Register via `dm_admin_pages` filter:
```php
add_filter('dm_admin_pages', function($pages) {
    $pages['jobs'] = [
        'page_title' => __('Jobs', 'data-machine'),
        'capability' => 'manage_options',
        'templates' => __DIR__ . '/templates/',
        'assets' => ['css' => [...], 'js' => [...]]
    ];
    return $pages;
});
```


