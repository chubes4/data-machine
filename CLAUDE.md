# CLAUDE.md

Data Machine: AI-first WordPress plugin transforming sites into content processing platforms via Pipeline+Flow architecture and multi-provider AI integration.

## Quick Reference

**Core Filters**:
```php
// Services
$databases = apply_filters('dm_db', []);
$handlers = apply_filters('dm_handlers', []);
$auth = apply_filters('dm_auth_providers', []);
$steps = apply_filters('dm_steps', []);

// Operations
apply_filters('dm_render_template', '', $template, $data);
apply_filters('dm_request', null, 'POST', $url, $args, 'context');
apply_filters('dm_get_flow_step_config', [], $flow_step_id);

// Repositories
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
```

**Core Actions**:
```php
do_action('dm_run_flow_now', $flow_id);
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_log', 'error', 'Message', ['context' => 'data']);
do_action('dm_ajax_route', 'action_name', 'context');
do_action('dm_oauth', 'store', 'provider', $credentials);
```

## Core Architecture

**Filter-Based Design**: Self-registering components via `*Filters.php` files. All services via `apply_filters()` patterns.

**Pipeline+Flow System**: 
- **Pipelines**: Reusable workflow templates (steps 0-99)
- **Flows**: Configured instances with handlers/scheduling
- **Auto-Creation**: New pipelines create "Draft Flow"

**Admin-Only**: Site-level auth, zero user_id dependencies, `manage_options` checks.

## Database Schema

**Tables**: `wp_dm_pipelines` (templates, UUID4 pipeline_step_id), `wp_dm_flows` (instances with handlers), `wp_dm_jobs` (execution records), `wp_dm_processed_items` (duplicate tracking), `wp_dm_remote_locations` (site-to-site auth).

**Relationships**: flows.pipeline_id → pipelines.pipeline_id, jobs.flow_id → flows.flow_id. Admin-only: no user_id columns.


## Components

**Handlers**:
- **Fetch**: Files, Reddit, RSS, WordPress, Google Sheets
- **Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets
- **AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (via AI HTTP Client)

**Services**: Logger, Database, Engine, Auth, Templates, Files Repository

**Principles**: Filter-based discovery, self-registering via `*Filters.php`, engine agnostic, position-based execution (0-99), DataPacket flow

## Development

```bash
# Setup & Testing
composer install && composer dump-autoload
composer test                # All PHPUnit tests
composer test:coverage       # HTML coverage report

# Debugging
window.dmDebugMode = true;   # Browser debugging
define('WP_DEBUG', true);    # Enable error_log

# Validation
error_log(print_r(apply_filters('dm_db', []), true));
error_log(print_r(apply_filters('dm_handlers', []), true));
```

## DataPacket & Execution

**DataPacket Structure**:
```php
[
    'type' => 'rss|files|ai|wordpress|twitter',
    'content' => ['body' => $content, 'title' => $title],
    'metadata' => ['source_type', 'file_path', 'model', 'provider'],
    'timestamp' => $timestamp
]
```

**Step Implementation**:
```php
class MyStep {
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        foreach ($data as $item) {
            $content = $item['content']['body'] ?? '';
            // Process content
        }
        return $data;
    }
}
```

**Execution Engine**: Three-action system - `dm_run_flow_now` (initiation), `dm_execute_step` (core execution), `dm_schedule_next_step` (Action Scheduler transitions).

## Templates & UI

**Template Rendering**: PHP templates as single source, JavaScript requests templates via AJAX:
```php
apply_filters('dm_render_template', '', $template, $data);
```

**Modal System**: Universal `dm_get_modal_content` endpoint with auto-discovery. Register via `dm_modals` filter.

**Step Cards**: `pipeline-step-card.php` (configuration), `flow-step-card.php` (handler management).

**Handler Directives**: AI steps auto-inject platform formatting via `dm_handler_directives` filter.

## Files Repository

Flow-isolated file storage with automatic cleanup:
```php
$repo = apply_filters('dm_files_repository', [])['files'] ?? null;
$repo->store_file($tmp_name, $filename, $flow_step_id);
$files = $repo->get_all_files($flow_step_id);
```



## Action Hook System

**Central Hooks** (organized in `/inc/engine/actions/DataMachineActions.php`):
```php
// Execution
do_action('dm_run_flow_now', $flow_id, 'context');
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// CRUD
do_action('dm_create', 'job', ['pipeline_id' => $pipeline_id, 'flow_id' => $flow_id]);
do_action('dm_update_job_status', $job_id, 'completed', 'context', 'old_status');
do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);
do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly', 'inactive');
do_action('dm_sync_steps_to_flow', $flow_id, $step_data, ['context' => 'add_step']);

// Utilities
do_action('dm_log', 'error', 'Process failed', ['context' => 'data']);
do_action('dm_ajax_route', 'dm_add_step', 'page');
do_action('dm_mark_item_processed', $flow_id, 'rss', $item_guid);
do_action('dm_auto_save', $pipeline_id);
do_action('dm_oauth', 'store', 'twitter', $credentials);
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


