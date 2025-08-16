# CLAUDE.md

Data Machine: AI-first WordPress plugin with Pipeline+Flow architecture and multi-provider AI integration.

## Core Patterns

**Essential Filters**:
```php
// Service Discovery
$handlers = apply_filters('dm_handlers', []);
$steps = apply_filters('dm_steps', []);
$databases = apply_filters('dm_db', []);

// Data Access
apply_filters('dm_get_pipelines', [], $pipeline_id);
apply_filters('dm_get_flow_config', [], $flow_id);
apply_filters('dm_is_item_processed', false, $flow_step_id, $source_type, $item_id);

// Templates & Files
apply_filters('dm_render_template', '', $template, $data);
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;

// AI Integration
$result = apply_filters('ai_request', $request, 'openrouter');
$tools = apply_filters('ai_tools', []);

// OAuth Operations
apply_filters('dm_oauth', [], 'retrieve', 'handler');
apply_filters('dm_oauth', null, 'store', 'handler', $data);
apply_filters('dm_oauth', [], 'get_config', 'handler');
apply_filters('dm_oauth', null, 'store_config', 'handler', $config);
apply_filters('dm_oauth', false, 'clear', 'handler');
```

**Essential Actions**:
```php
// Execution
do_action('dm_run_flow_now', $flow_id);
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// CRUD
do_action('dm_create', 'pipeline', ['pipeline_name' => $name]);
do_action('dm_create', 'flow', ['flow_name' => $name, 'pipeline_id' => $id]);
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $id]);
do_action('dm_create', 'job', ['pipeline_id' => $id, 'flow_id' => $flow_id]);
do_action('dm_update_flow_handler', $flow_step_id, $handler, $settings);
do_action('dm_delete', 'pipeline', $pipeline_id, ['cascade' => true]);

// System
do_action('dm_log', 'error', $message, ['context' => $data]);
do_action('dm_ajax_route', 'action_name', 'page|modal');
```

## Architecture

**Action-Based Engine**: Three-action execution cycle drives all pipeline processing

**Execution Cycle**:
1. `dm_run_flow_now` → Creates job via `dm_create` action
2. `dm_execute_step` → Functional step execution with global job context
3. `dm_schedule_next_step` → Action Scheduler step transitions

**Job Status Management**: `completed`, `failed`, `completed_no_items` - engine exceptions fail jobs immediately

**Array-Based Processing**: Steps process arrays of data entries sequentially via Action Scheduler

**Filter-Based Discovery**: Self-registering components via `*Filters.php`. All services discoverable via `apply_filters()`

**Pipeline+Flow**: 
- **Pipelines**: Reusable templates (steps 0-99, UUID4 IDs)
- **Flows**: Configured instances with handlers/scheduling
- **Execution**: Sequential item processing through Action Scheduler queue

**Database**: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items` (deduplication tracking)

**Handlers**:
- **Fetch**: Files, RSS, Reddit, Google Sheets, WordPress
- **Publish**: Bluesky, Twitter, Threads, Facebook, Google Sheets  
- **AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Admin-Only**: Site-level auth, `manage_options` checks, zero user dependencies

## AI Integration

**Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Usage**:
```php
$result = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');
```

**Discovery**: `apply_filters('ai_providers', [])`, `apply_filters('ai_models', $provider, $config)`

## Agentic Tool Calling

**Dynamic Discovery**: AI models discover and use handler capabilities automatically via `apply_filters('ai_tools', [])`

**Tool Registration**:
```php
add_filter('ai_tools', function($tools) {
    $tools['wordpress_publish'] = [
        'class' => 'HandlerClass',
        'method' => 'handle_tool_call',
        'description' => 'Publish content to WordPress',
        'parameters' => [
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'string', 'required' => true]
        ]
    ];
    return $tools;
});
```

**Tool Execution**: `ai_http_execute_tool($tool_name, $parameters)` - Class method execution with WordPress action fallback

**Context-Aware**: Next step handlers automatically register tools for AI step discovery

**AI Integration**: Models receive available tools and execute them during processing without hardcoded logic

## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing |
| RSS | None | Feed parsing, deduplication |
| Reddit | OAuth2 | Subreddit posts, comments |
| Google Sheets | OAuth2 | Spreadsheet data extraction |
| WordPress | None | Post/page content retrieval |

| **Publish** | **Auth** | **Features** |
|-------------|----------|--------------|
| Bluesky | App Password | Text posts, media upload |
| Twitter | OAuth 1.0a | Tweets, media, threads |
| Threads | OAuth2 | Text posts, media |
| Facebook | OAuth2 | Page posts, media |
| Google Sheets | OAuth2 | Row insertion, data logging |
| WordPress | None | Post creation, content publishing |

## DataPacket Array

```php
// Fetch DataPacket
[
    'type' => 'fetch',
    'handler' => 'rss',
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => [
        'source_type' => 'rss',
        'pipeline_id' => $pipeline_id,
        'flow_id' => $flow_id
    ],
    'attachments' => [],
    'timestamp' => time()
]

// AI DataPacket
[
    'type' => 'ai',
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => [
        'model' => 'gpt-4',
        'provider' => 'openai',
        'usage' => [],
        'source_type' => 'rss'
    ],
    'timestamp' => time()
]

// Publish DataPacket
[
    'type' => 'publish',
    'handler' => 'twitter',
    'content' => ['title' => 'Publish Complete', 'body' => $result_json],
    'metadata' => [
        'handler_used' => 'twitter',
        'publish_success' => true,
        'flow_step_id' => $flow_step_id
    ],
    'result' => $handler_result,
    'timestamp' => time()
]
```

**Flow**: Fetch creates entry → AI adds entry → Publish adds entry → Array accumulates all processing history

## Admin Workflow

1. **Pipelines Page**: Create/manage pipeline templates
2. **Add Steps**: Configure step positions and types via modals
3. **Flow Configuration**: Set handlers, authentication, scheduling
4. **Job Management**: Monitor execution, clear processed items
5. **Testing**: Manual execution with `dm_run_flow_now`

## Step Implementation

```php
class MyStep {
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        // Global job context available via $GLOBALS['dm_current_job_id']
        $processed_entry = [
            'type' => 'my_step',
            'content' => ['title' => $title, 'body' => $processed_content],
            'metadata' => ['source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'],
            'timestamp' => time()
        ];
        
        // Add new entry to front of array
        array_unshift($data, $processed_entry);
        return $data;
    }
}

add_filter('dm_steps', function($steps) {
    $steps['my_step'] = ['name' => __('My Step'), 'class' => 'MyStep', 'position' => 50];
    return $steps;
});
```

## Import/Export

```php
do_action('dm_import', 'pipelines', $csv_data);
do_action('dm_export', 'pipelines', [$pipeline_id]);
```

**CSV Schema**: `pipeline_id, pipeline_name, step_position, step_type, step_config, flow_id, flow_name, handler, settings`

## AJAX System

**Centralized Security**: `dm_ajax_route` handles all AJAX with automatic `manage_options` + nonce checks

```php
add_action('wp_ajax_dm_action_name', fn() => do_action('dm_ajax_route', 'dm_action_name', 'page'));
```

**Handler Types**: `page` (admin pages), `modal` (modal content)

## Jobs Administration

**Modal**: Clear processed items, clear jobs (failed/all), development testing

```php
do_action('dm_delete', 'processed_items', $flow_id, ['delete_by' => 'flow_id']);
do_action('dm_delete', 'jobs', null, ['status' => 'failed']);
```

## Templates

```php
apply_filters('dm_render_template', '', $template, $data);
$modals = apply_filters('dm_modals', []);
$admin_pages = apply_filters('dm_admin_pages', []);
```

## Usage Examples

**Pipeline Creation**:
```php
do_action('dm_create', 'pipeline', ['pipeline_name' => 'RSS to Twitter']);
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
do_action('dm_update_flow_handler', $flow_step_id, 'rss', $settings);
do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly');
```

**Execution & Testing**:
```php
do_action('dm_run_flow_now', $flow_id, 'manual_trigger');
do_action('dm_delete', 'processed_items', $flow_id, ['delete_by' => 'flow_id']);
```

## Files Repository

**Flow-Isolated Architecture**: `/wp-content/uploads/data-machine/files/{flow_step_id}/`

**Path Patterns**:
- Flow isolation prevents cross-contamination
- UUID-based flow_step_id ensures unique namespaces
- Automatic cleanup on flow deletion

**Operations**:
```php
$repo = apply_filters('dm_files_repository', [])['files'] ?? null;

// Storage
$repo->store_file($tmp_name, $filename, $flow_step_id);
$repo->get_file_path($filename, $flow_step_id);
$repo->file_exists($filename, $flow_step_id);

// Cleanup
$repo->cleanup_flow_files($flow_step_id);        // Single flow
do_action('dm_cleanup_old_files');               // Global maintenance
$repo->delete_file($filename, $flow_step_id);    // Single file
```

**Maintenance**: Automatic cleanup via `dm_cleanup_old_files` action for orphaned files

## Development

**Debug**:
```bash
composer install && composer test
window.dmDebugMode = true; # Browser
define('WP_DEBUG', true);  # PHP
```

**Service Validation**:
```php
error_log(print_r(apply_filters('dm_db', []), true));
```

## Extension Patterns

**Handler Registration**:
```php
add_filter('dm_handlers', function($handlers) {
    $handlers['my_handler'] = [
        'name' => __('My Handler'),
        'steps' => ['fetch', 'publish'],
        'auth_required' => true
    ];
    return $handlers;
});
```

**Error Handling**:
```php
try {
    $result = $this->process_data($data);
    return $result;
} catch (Exception $e) {
    do_action('dm_log', 'error', $e->getMessage(), ['flow_step_id' => $flow_step_id]);
    return $data; // Return original on failure
}
```

**Global Job Context**: `$GLOBALS['dm_current_job_id']` available during step execution

**Job Status**: `completed`, `failed`, `completed_no_items`

## OAuth Integration

**Central Operations**: Unified `dm_oauth` filter for all handlers

```php
// Account Management
$account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration Management
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);
```

**Handler Requirements**:
- **Reddit**: OAuth2 (read access)
- **Twitter**: OAuth 1.0a (read/write)
- **Facebook**: OAuth2 (pages_manage_posts)
- **Threads**: OAuth2 (threads_basic, threads_content_publish)
- **Google Sheets**: OAuth2 (spreadsheets scope)
- **Bluesky**: App Password (username/password)

## Rules

**Engine Agnosticism**: No hardcoded step types in `/inc/engine/`
**Service Discovery**: Filter-based only - `$service = apply_filters('dm_service', [])['key'] ?? null`
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()`
**CSS Namespace**: `dm-` prefix
**Authentication**: `manage_options` checks only
**OAuth Storage**: Unified `dm_oauth` filter system
**Field Naming**: `pipeline_step_id` (UUID4)
**Job Failure**: Engine exceptions fail jobs immediately
**Logging**: Include relevant IDs in context
**AJAX Security**: Universal `dm_ajax_actions` nonce