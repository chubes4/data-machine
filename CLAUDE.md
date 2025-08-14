# CLAUDE.md

Data Machine: AI-first WordPress plugin transforming sites into content processing platforms via Pipeline+Flow architecture and multi-provider AI integration.

## Quick Reference

**Core Filters**:
```php
// Services
$databases = apply_filters('dm_db', []);
$handlers = apply_filters('dm_handlers', []);
$steps = apply_filters('dm_steps', []);
$auth = apply_filters('dm_auth_providers', []);

// Operations
apply_filters('dm_render_template', '', $template, $data);
apply_filters('dm_request', null, 'POST', $url, $args, 'context');
apply_filters('dm_get_flow_step_config', [], $flow_step_id);
apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);

// Database Access
apply_filters('dm_get_pipelines', [], $pipeline_id);
apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
apply_filters('dm_get_flow_config', [], $flow_id);
apply_filters('dm_is_item_processed', false, $flow_step_id, $source_type, $item_id);

// Repositories & Services
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
$importer = apply_filters('dm_importer', null);
$modals = apply_filters('dm_modals', []);
$admin_pages = apply_filters('dm_admin_pages', []);

// Import/Export
$import_result = apply_filters('dm_import_result', []);
$export_result = apply_filters('dm_export_result', '');

// Handler Configuration
$handler_settings = apply_filters('dm_handler_settings', []);
$handler_directives = apply_filters('dm_handler_directives', []);
$step_settings = apply_filters('dm_step_settings', []);

// Template System
$template_requirements = apply_filters('dm_template_requirements', []);
$scheduler_intervals = apply_filters('dm_scheduler_intervals', []);

// Flow ID Utilities
$flow_step_parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);

// OAuth Management
apply_filters('dm_oauth', null, 'operation', 'provider', $data);
```

**Core Actions**:
```php
// Execution
do_action('dm_run_flow_now', $flow_id, 'context');
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// CRUD Operations
do_action('dm_create', 'type', $data, $context);
do_action('dm_update_job_status', $job_id, 'status', 'context', 'old_status');
do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $settings);
do_action('dm_update_flow_schedule', $flow_id, 'status', 'interval', 'old_status');
do_action('dm_sync_steps_to_flow', $flow_id, $step_data, ['context' => 'add_step']);
do_action('dm_delete', 'type', $id, $options);

// Import/Export
do_action('dm_import', 'pipelines', $csv_data, ['source' => 'upload']);
do_action('dm_export', 'pipelines', [$pipeline_id], ['format' => 'csv']);

// Item Processing
do_action('dm_mark_item_processed', $flow_step_id, 'source_type', $item_id, $job_id);

// System Operations
do_action('dm_log', 'level', 'message', ['context' => 'data']);
do_action('dm_ajax_route', 'action_name', 'page|modal');
do_action('dm_auto_save', $pipeline_id);
do_action('dm_oauth', 'operation', 'provider', $credentials);
do_action('dm_cleanup_old_files');
```

## Core Architecture

**Filter-Based Design**: Self-registering components via `*Filters.php` files. All services via `apply_filters()` patterns.

**Pipeline+Flow System**: 
- **Pipelines**: Reusable workflow templates (steps 0-99, UUID4 pipeline_step_id)
- **Flows**: Configured instances with handlers/scheduling
- **Auto-Creation**: New pipelines create "Draft Flow"

**Admin-Only**: Site-level auth, zero user_id dependencies, `manage_options` checks.

## Database Schema

**Tables**: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items`, `wp_dm_remote_locations`

**Relationships**: flows.pipeline_id → pipelines.pipeline_id, jobs.flow_id → flows.flow_id

## Components

**Handlers**: Files, Reddit, RSS, WordPress, Google Sheets, Facebook, Threads, Twitter, Bluesky, OpenAI, Anthropic, Google, Grok, OpenRouter

**Services**: Logger, Database, Engine, Auth, Templates, Files Repository, Import/Export

**Principles**: Filter-based discovery, self-registering via `*Filters.php`, engine agnostic, position-based execution, DataPacket flow

## Development

```bash
# Setup & Testing
composer install && composer dump-autoload
composer test && composer test:coverage

# Debugging
window.dmDebugMode = true;   # Browser debugging
define('WP_DEBUG', true);    # Enable error_log

# Validation
error_log(print_r(apply_filters('dm_db', []), true));
```

## DataPacket Structure

**Standard Format**:
```php
[
    'type' => 'rss|files|ai|wordpress|twitter|reddit|googlesheets',
    'content' => [
        'body' => $content,
        'title' => $title,
        'excerpt' => $excerpt // Optional
    ],
    'metadata' => [
        'source_type' => 'rss|files|etc',
        'file_path' => '/path/to/file', // For file sources
        'model' => 'gpt-4', // For AI sources  
        'provider' => 'openai', // For AI sources
        'url' => $original_url, // For web sources
        'author' => $author_name, // When available
        'published' => $publish_date, // When available
        'item_id' => $unique_identifier // For deduplication
    ],
    'timestamp' => time()
]
```

**Processing Flow**:
1. Fetch steps create initial DataPackets
2. AI steps transform content while preserving metadata
3. Publish steps consume DataPackets for final output
4. Each step can add metadata without modifying existing data

## Step Implementation

**Base Step Pattern**:
```php
class MyStep {
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        foreach ($data as $item) {
            $content = $item['content']['body'] ?? '';
            // Process content based on step type
        }
        return $data; // Return modified DataPacket array
    }
}
```

**Step Registration**:
```php
add_filter('dm_steps', function($steps) {
    $steps['my_step'] = [
        'name' => __('My Step', 'textdomain'),
        'description' => __('Custom processing step', 'textdomain'),
        'class' => 'MyStep',
        'position' => 50
    ];
    return $steps;
});
```

**Step Configuration**:
```php
add_filter('dm_step_settings', function($configs) {
    $configs['my_step'] = [
        'template' => 'step-settings/my-step',
        'fields' => ['setting1', 'setting2']
    ];
    return $configs;
});
```

## Import/Export System

**Core Actions**:
```php
do_action('dm_import', 'pipelines', $csv_data, ['source' => 'upload']);
do_action('dm_export', 'pipelines', [$pipeline_id1, $pipeline_id2], ['format' => 'csv']);
```

**CSV Schema**: `pipeline_id, pipeline_name, step_position, step_type, step_config, flow_id, flow_name, handler, settings`

**Export Workflow**:
```php
// Pipeline structure + flow configurations in single CSV
// Includes pipeline steps and configured handlers for each flow
$csv = apply_filters('dm_export_result', '');
```

**Import Workflow**:
```php
// Creates pipelines by name (checks existing)
// Parses step configurations and recreates pipeline structure
// Auto-creates flows as needed during import process
$result = apply_filters('dm_import_result', []); // ['imported' => [$pipeline_ids]]
```

**Modal Interface**:
```php
// Tab-based modal: Export (checkbox table) + Import (drag-drop CSV)
add_filter('dm_modals', function($modals) {
    $modals['import-export'] = [
        'title' => __('Import/Export Pipelines', 'data-machine'),
        'template' => 'modal/import-export'
    ];
    return $modals;
});
```

**Implementation**:
- **Handler**: `/inc/engine/actions/ImportExport.php` - contains complete logic
- **UI**: Tab interface with pipeline selection table and drag-drop upload
- **Security**: `manage_options` capability checks, CSV parsing validation
- **Integration**: Works with existing pipeline/flow creation system via `dm_create` actions

## AJAX Routing System

**Centralized Security**: Universal `dm_ajax_route` action eliminates security duplication across AJAX handlers.

**Registration Pattern**:
```php
add_action('wp_ajax_dm_action_name', fn() => do_action('dm_ajax_route', 'dm_action_name', 'page'));
```

**Handler Types**: `page` (page AJAX handlers), `modal` (modal content handlers)

**Security**: Automatic `manage_options` + nonce verification with `dm_pipeline_ajax` action

**Complete AJAX Actions**:
```php
// Pipeline Management
add_action('wp_ajax_dm_add_step', fn() => do_action('dm_ajax_route', 'dm_add_step', 'page'));
add_action('wp_ajax_dm_create_pipeline', fn() => do_action('dm_ajax_route', 'dm_create_pipeline', 'page'));
add_action('wp_ajax_dm_delete_pipeline', fn() => do_action('dm_ajax_route', 'dm_delete_pipeline', 'page'));
add_action('wp_ajax_dm_delete_step', fn() => do_action('dm_ajax_route', 'dm_delete_step', 'page'));

// Flow Management  
add_action('wp_ajax_dm_add_flow', fn() => do_action('dm_ajax_route', 'dm_add_flow', 'page'));
add_action('wp_ajax_dm_delete_flow', fn() => do_action('dm_ajax_route', 'dm_delete_flow', 'page'));
add_action('wp_ajax_dm_save_flow_schedule', fn() => do_action('dm_ajax_route', 'dm_save_flow_schedule', 'page'));
add_action('wp_ajax_dm_run_flow_now', fn() => do_action('dm_ajax_route', 'dm_run_flow_now', 'page'));

// Modal Content
add_action('wp_ajax_dm_get_modal_content', $handler); // Core modal system
add_action('wp_ajax_dm_get_template', fn() => do_action('dm_ajax_route', 'dm_get_template', 'modal'));
add_action('wp_ajax_dm_get_flow_step_card', fn() => do_action('dm_ajax_route', 'dm_get_flow_step_card', 'modal'));
add_action('wp_ajax_dm_get_flow_config', fn() => do_action('dm_ajax_route', 'dm_get_flow_config', 'modal'));

// Configuration
add_action('wp_ajax_dm_configure_step_action', fn() => do_action('dm_ajax_route', 'dm_configure_step_action', 'modal'));
add_action('wp_ajax_dm_add_location_action', fn() => do_action('dm_ajax_route', 'dm_add_location_action', 'modal'));
add_action('wp_ajax_dm_add_handler_action', fn() => do_action('dm_ajax_route', 'dm_add_handler_action', 'modal'));
add_action('wp_ajax_dm_save_handler_settings', $direct_handler); // Direct handler

// File Management
add_action('wp_ajax_dm_upload_file', fn() => do_action('dm_ajax_route', 'dm_upload_file', 'modal'));

// Import/Export
add_action('wp_ajax_dm_export_pipelines', fn() => do_action('dm_ajax_route', 'dm_export_pipelines', 'page'));
add_action('wp_ajax_dm_import_pipelines', fn() => do_action('dm_ajax_route', 'dm_import_pipelines', 'page'));

// Jobs Administration
add_action('wp_ajax_dm_clear_processed_items_manual', $direct_handler);
add_action('wp_ajax_dm_get_pipeline_flows_for_select', $direct_handler);
add_action('wp_ajax_dm_clear_jobs_manual', $direct_handler);
```

## Jobs Administration System

**Modal Interface**:
```php
// Administrative tools for job processing and testing workflows
add_filter('dm_modals', function($modals) {
    $modals['jobs-admin'] = [
        'title' => __('Jobs Administration', 'data-machine'),
        'template' => 'modal/jobs-admin'
    ];
    return $modals;
});
```

**Core Features**:
- **Clear Processed Items**: Remove deduplication records to allow reprocessing
- **Clear Jobs**: Delete job execution history (failed jobs or all jobs)
- **Development Testing**: Iterative prompt refinement and configuration testing

**Clear Operations**:
```php
// Clear processed items by pipeline or specific flow
add_action('wp_ajax_dm_clear_processed_items_manual', $handler);

// Clear jobs with optional processed items cleanup
add_action('wp_ajax_dm_clear_jobs_manual', $handler);

// Dynamic flow selection based on pipeline
add_action('wp_ajax_dm_get_pipeline_flows_for_select', $handler);
```

**JavaScript Integration**:
```javascript
// Modal content initialization
$(document).on('dm-core-modal-content-loaded', function(e, title, content) {
    if (content.includes('dm-clear-processed-items-form')) {
        dmJobsModal.init(); // Initialize jobs-specific handlers
    }
});

// Event emission for page updates
$(document).on('dm-jobs-processed-items-cleared', function(e, data) {
    // Page can refresh job listings or update UI
});
$(document).on('dm-jobs-cleared', function(e, data) {
    // Page can refresh job listings
});
```

**UI Pattern**:
- **Progressive Disclosure**: Form fields show/hide based on operation type
- **Confirmation Dialogs**: Destructive operations require user confirmation
- **Loading States**: Visual feedback during AJAX operations
- **Result Display**: Success/error messages with appropriate styling

**Implementation**: Modal content managed by `jobs-modal.js`, form validation and AJAX handling, integrates with existing jobs framework

## Template System

**Template Rendering**:
```php
apply_filters('dm_render_template', '', $template, $data);
```

**Modal System**: Universal `dm_get_modal_content` endpoint with auto-discovery via `dm_modals` filter

**Admin Pages**: Register via `dm_admin_pages` filter with templates/assets configuration

**Template Requirements**:
```php
add_filter('dm_template_requirements', function($requirements) {
    $requirements['my-template'] = [
        'required_fields' => ['field1', 'field2'],
        'optional_fields' => ['field3']
    ];
    return $requirements;
});
```

## Workflow Examples

**Complete Pipeline Creation**:
```php
// 1. Create pipeline
do_action('dm_create', 'pipeline', ['pipeline_name' => 'RSS to Twitter']);

// 2. Add steps
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
do_action('dm_create', 'step', ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
do_action('dm_create', 'step', ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]);

// 3. Configure flow handlers
do_action('dm_update_flow_handler', $fetch_flow_step_id, 'rss', $rss_settings);
do_action('dm_update_flow_handler', $publish_flow_step_id, 'twitter', $twitter_settings);

// 4. Activate scheduling
do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly');
```

**Manual Flow Execution**:
```php
// Trigger immediate execution
do_action('dm_run_flow_now', $flow_id, 'manual_trigger');

// Monitor via jobs page or programmatically
$job_status = apply_filters('dm_get_job_status', null, $job_id);
```

**Development Testing**:
```php
// Clear processed items for reprocessing
do_action('dm_delete', 'processed_items', $flow_id, ['delete_by' => 'flow_id']);

// Clear failed jobs
do_action('dm_delete', 'jobs', null, ['status' => 'failed']);

// Debug logging
do_action('dm_log', 'debug', 'Custom debug message', [
    'context' => 'development',
    'data' => $debug_data
]);
```

## Files Repository

**Flow-Isolated Storage**:
```php
$repo = apply_filters('dm_files_repository', [])['files'] ?? null;
$repo->store_file($tmp_name, $filename, $flow_step_id);
$files = $repo->get_all_files($flow_step_id);
$repo->delete_file($filename, $flow_step_id);
$repo->cleanup_flow_files($flow_step_id);
```

**Auto-Cleanup**: Files removed when flows deleted

**Storage Structure**: `/wp-content/uploads/data-machine/files/{flow_step_id}/`

**File Operations**:
```php
// Manual cleanup
do_action('dm_cleanup_old_files');

// Repository filter registration
add_filter('dm_files_repository', function($repositories) {
    $repositories['files'] = new FilesRepository();
    return $repositories;
});
```

## Action Hook System

**Execution Engine**:
```php
do_action('dm_run_flow_now', $flow_id, 'context');
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);
```

**CRUD Operations**:
```php
// Create
do_action('dm_create', 'pipeline', ['pipeline_name' => $name], ['source' => 'user']);
do_action('dm_create', 'flow', ['pipeline_id' => $id, 'flow_name' => $name]);
do_action('dm_create', 'step', ['step_type' => 'ai', 'pipeline_id' => $id]);
do_action('dm_create', 'job', ['pipeline_id' => $id, 'flow_id' => $id]);

// Update
do_action('dm_update_job_status', $job_id, 'completed', 'execution', 'running');
do_action('dm_update_flow_handler', $flow_step_id, 'twitter', $handler_settings);
do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly', 'inactive');
do_action('dm_sync_steps_to_flow', $flow_id, $step_data, ['context' => 'add_step']);

// Delete
do_action('dm_delete', 'pipeline', $pipeline_id, ['cascade' => true]);
do_action('dm_delete', 'flow', $flow_id, ['cleanup_files' => true]);
do_action('dm_delete', 'step', $pipeline_step_id, ['context' => 'user']);
do_action('dm_delete', 'processed_items', $job_id, ['delete_by' => 'job_id']);
```

**Data Management**:
```php
do_action('dm_mark_item_processed', $flow_step_id, 'rss', $item_guid, $job_id);
do_action('dm_auto_save', $pipeline_id);
```

**System Operations**:
```php
do_action('dm_log', 'error', 'Message', ['context' => 'execution', 'job_id' => $id]);
do_action('dm_ajax_route', 'dm_action_name', 'page');
do_action('dm_oauth', 'store', 'twitter', $credentials);
do_action('dm_cleanup_old_files');
```

## Integration Patterns

**Handler Registration**:
```php
add_filter('dm_handlers', function($handlers) {
    $handlers['my_handler'] = [
        'name' => __('My Handler', 'textdomain'),
        'steps' => ['fetch', 'publish'], // Available step types
        'auth_required' => true,
        'settings_template' => 'handler-settings/my-handler'
    ];
    return $handlers;
});
```

**Authentication Provider**:
```php
add_filter('dm_auth_providers', function($providers) {
    $providers['my_service'] = [
        'name' => __('My Service', 'textdomain'),
        'type' => 'oauth2', // or 'api_key'
        'fields' => ['client_id', 'client_secret'],
        'auth_class' => 'MyServiceAuth'
    ];
    return $providers;
});
```

**Handler Directives (AI Instructions)**:
```php
add_filter('dm_handler_directives', function($directives) {
    $directives['my_handler'] = [
        'format_instructions' => 'Format for My Service: ...',
        'constraints' => 'Character limits: ...',
        'examples' => 'Example output: ...'
    ];
    return $directives;
});
```

**Error Handling Pattern**:
```php
try {
    $result = $this->process_data($data);
    do_action('dm_log', 'debug', 'Processing completed', ['items' => count($result)]);
    return $result;
} catch (Exception $e) {
    do_action('dm_log', 'error', 'Processing failed: ' . $e->getMessage(), [
        'flow_step_id' => $flow_step_id,
        'exception' => $e
    ]);
    return $data; // Return original data on failure
}
```

## Critical Rules

**Engine Agnosticism**: Never hardcode step types in `/inc/engine/`

**Service Discovery**: Filter-based access only - `$service = apply_filters('dm_service', [])['key'] ?? null`

**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()`

**CSS Namespace**: All admin CSS uses `dm-` prefix

**Authentication**: Admin-global only with `manage_options` checks

**OAuth Storage**: `{handler}_auth_data` option keys

**Field Naming**: Use `pipeline_step_id` consistently (UUID4)

**Error Recovery**: Steps should return original data on failure, not empty arrays

**Logging Context**: Always include relevant IDs in log context for debugging

**Performance**: Use `apply_filters()` patterns for lazy loading and memory efficiency

**Debugging**: Enable `window.dmDebugMode = true` for JavaScript debugging and `WP_DEBUG` for PHP logging