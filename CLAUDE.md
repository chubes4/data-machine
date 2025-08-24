# CLAUDE.md

Data Machine: AI-first WordPress plugin with Pipeline+Flow architecture and multi-provider AI integration.

## Core Filters & Actions

```php
// Service Discovery
$handlers = apply_filters('dm_handlers', []);
$steps = apply_filters('dm_steps', []);
$databases = apply_filters('dm_db', []);

// Pipeline Operations
$pipeline_id = apply_filters('dm_create_pipeline', null, $data);
$step_id = apply_filters('dm_create_step', null, $data);
$flow_id = apply_filters('dm_create_flow', null, $data);
apply_filters('dm_get_pipelines', [], $pipeline_id);
apply_filters('dm_get_flow_config', [], $flow_id);

// AI & Tools
$result = apply_filters('ai_request', $request, 'openrouter');
$tools = apply_filters('ai_tools', []);
apply_filters('dm_tool_configured', false, $tool_id);
apply_filters('dm_get_tool_config', [], $tool_id);
do_action('dm_save_tool_config', $tool_id, $config_data);

// OAuth
apply_filters('dm_oauth', [], 'retrieve', 'handler');
apply_filters('dm_get_oauth_url', '', 'provider');
$providers = apply_filters('dm_auth_providers', []);

// Execution
do_action('dm_run_flow_now', $flow_id, $context);
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);

// Processing
do_action('dm_mark_item_processed', $flow_step_id, $source_type, $item_id, $job_id);
apply_filters('dm_is_item_processed', false, $flow_step_id, $source_type, $item_id);
apply_filters('dm_detect_status', 'green', 'context', $data);

// Settings (Direct function access for internal system components)
$settings = dm_get_data_machine_settings();
$enabled_pages = dm_get_enabled_admin_pages();
$enabled_tools = dm_get_enabled_general_tools();

// System
do_action('dm_log', $level, $message, $context);
do_action('dm_auto_save', $pipeline_id);
do_action('dm_cleanup_old_files'); // File repository maintenance via Action Scheduler
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
```

## Architecture

**Engine**: Three-action execution cycle: `dm_run_flow_now` → `dm_execute_step` → `dm_schedule_next_step`

**Job Status**: `completed`, `failed`, `completed_no_items`

**Self-Registration**: Components auto-register via `*Filters.php` files loaded through composer.json:
```php
function dm_register_twitter_filters() {
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => Twitter::class,
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });
    add_filter('dm_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });
    add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'twitter') {
            $tools['twitter_publish'] = [
                'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
                'method' => 'handle_tool_call',
                'handler' => 'twitter',
                'description' => 'Post content to Twitter (280 character limit)',
                'parameters' => ['content' => ['type' => 'string', 'required' => true]],
                'handler_config' => $handler_config
            ];
        }
        return $tools;
    }, 10, 3);
}
dm_register_twitter_filters(); // Auto-execute at file load
```

**Components**:
- **Pipeline+Flow**: Reusable templates + configured instances
- **Database**: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items`
- **Files Repository**: Flow-isolated UUID storage with cleanup
- **Handlers**: Fetch (Files, RSS, Reddit, Google Sheets, WordPress) | Publish (Twitter, Bluesky, Threads, Facebook, Google Sheets, WordPress) | Update (Content modification, existing post updates) | AI (OpenAI, Anthropic, Google, Grok, OpenRouter)
- **Admin**: `manage_options` only, zero user dependencies

## Database Schema

**Core Tables**:

```sql
-- Pipeline templates (reusable)
wp_dm_pipelines: pipeline_id, pipeline_name, pipeline_config, created_at, updated_at

-- Flow instances (scheduled + configured)
wp_dm_flows: flow_id, pipeline_id, flow_name, cron_expression, flow_config_json, status, created_at, updated_at

-- Job executions
wp_dm_jobs: job_id, flow_id, pipeline_id, status, job_data_json, started_at, completed_at, error_message

-- Deduplication tracking
wp_dm_processed_items: item_id, flow_step_id, source_type, item_id, job_id, processed_at
```

**Relationships**:
- Pipeline (1) → Flow (many) - Templates to instances
- Flow (1) → Job (many) - Scheduled executions
- Job (1) → ProcessedItems (many) - Deduplication per execution

**Key Fields**:
- `pipeline_step_id`: UUID4 for cross-flow step referencing
- `flow_step_id`: `{pipeline_step_id}_{flow_id}` composite for flow-specific tracking
- `status`: `pending`, `running`, `completed`, `failed`, `completed_no_items`

## AI Integration

**Tool-First Architecture**: All publish handlers use `handle_tool_call()` method for agentic execution.

**Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

```php
$result = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');
```

**Tool Discovery**:
- **Handler Tools**: Step-specific (twitter_publish, wordpress_update) - available when next step matches handler type
- **General Tools**: Universal (Google Search, Local Search) - available to all AI steps

**Tool Registration**:
```php
// Handler-specific (next step only)
add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
    if ($handler_slug === 'twitter') {
        $tools['twitter_publish'] = [
            'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
            'method' => 'handle_tool_call',
            'handler' => 'twitter',
            'description' => 'Post content to Twitter (280 character limit)',
            'parameters' => ['content' => ['type' => 'string', 'required' => true]],
            'handler_config' => $handler_config
        ];
    }
    return $tools;
}, 10, 3);

// General tools (all steps)
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for current information and context',
        'requires_config' => true,
        'parameters' => ['query' => ['type' => 'string', 'required' => true]]
        // No 'handler' = universal
    ];
    return $tools;
});
```

**Handler Implementation**:
```php
class Twitter {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        if (empty($parameters['content'])) {
            return ['success' => false, 'error' => 'Missing content', 'tool_name' => 'twitter_publish'];
        }
        
        $handler_config = $tool_def['handler_config'] ?? [];
        $connection = $this->auth->get_connection();
        if (is_wp_error($connection)) {
            return ['success' => false, 'error' => $connection->get_error_message()];
        }
        
        // Format and publish logic
        return ['success' => true, 'data' => ['tweet_id' => $id, 'url' => $url]];
    }
}
```

**Tool Configuration**:
```php
$configured = apply_filters('dm_tool_configured', false, 'google_search');
$config = apply_filters('dm_get_tool_config', [], 'google_search');
do_action('dm_save_tool_config', 'google_search', $config_data);
```

**Tool Capabilities**:
- **Google Search**: Web search, site restriction (API key + Search Engine ID required)
- **Local Search**: WordPress WP_Query search (no configuration needed)

## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress | None | Post/page content retrieval, specific post ID targeting, taxonomy filtering, timeframe filtering |

| **Publish** | **Auth** | **Limit** | **Features** |
|-------------|----------|-----------|--------------|
| Twitter | OAuth 1.0a | 280 chars | URL replies, media upload, t.co link handling |
| Bluesky | App Password | 300 chars | Media upload, AT Protocol integration |
| Threads | OAuth2 | 500 chars | Media upload |
| Facebook | OAuth2 | No limit | Comment mode, link handling |
| WordPress | None | No limit | Taxonomy assignment |
| Google Sheets | OAuth2 | No limit | Row insertion |

| **Update** | **Auth** | **Features** |
|------------|----------|---------------|
| WordPress | None | Post/page modification, taxonomy updates, meta field updates |
| *Extensible* | *Varies* | *Custom update handlers via extensions* |

| **General Tools** | **Auth** | **Features** |
|-------------------|----------|--------------|
| Google Search | API Key + Search Engine ID | Web search, site restriction, 1-10 results |
| Local Search | None | WordPress search, 1-20 results |

## DataPacket Structure

```php
// Standard format for all step types
[
    'type' => 'fetch|ai|update|publish',
    'handler' => 'rss|twitter|etc', // Optional for AI
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => ['source_type' => $type, 'pipeline_id' => $id, /*...*/],
    'timestamp' => time()
]
```

**Processing**: Each step adds entry to array front → accumulates complete workflow history

## Admin Interface

**Pages**: Pipelines, Jobs, Logs
**Settings**: WordPress Settings → Data Machine
**Features**: Drag & drop, auto-save, status indicators, modal configuration
**OAuth**: `/dm-oauth/{provider}/` URLs with popup flow

## Settings

**Controls**: Engine Mode (headless), admin page toggles, tool toggles, global system prompt
**WordPress Defaults**: Site-wide post type, taxonomy, author, status defaults
**Tool Configuration**: Modal setup for API keys

```php
// Direct function access (internal system components)
$settings = dm_get_data_machine_settings();
$enabled_pages = dm_get_enabled_admin_pages();
$enabled_tools = dm_get_enabled_general_tools();

// Filter-based configuration handling
apply_filters('dm_enabled_settings', $fields, $handler_slug, $step_type, $context);
apply_filters('dm_apply_global_defaults', $current_settings, $handler_slug, $step_type);
```

## Step Implementation

**Pipeline Steps**:
```php
class MyStep {
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        do_action('dm_mark_item_processed', $flow_step_id, 'my_step', $item_id, $job_id);
        
        array_unshift($data, [
            'type' => 'my_step',
            'content' => ['title' => $title, 'body' => $content],
            'metadata' => ['source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'],
            'timestamp' => time()
        ]);
        return $data;
    }
}

add_filter('dm_steps', function($steps) {
    $steps['my_step'] = ['name' => __('My Step'), 'class' => 'MyStep', 'position' => 50];
    return $steps;
});
```

**Fetch Handlers**:
```php
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        do_action('dm_mark_item_processed', $flow_step_id, 'my_handler', $item_id, $job_id);
        return ['processed_items' => $items];
    }
}
```

**Publish Handlers**:
```php
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_config = $tool_def['handler_config'] ?? [];
        return ['success' => true, 'data' => ['id' => $id, 'url' => $url]];
    }
}
```

**Update Handlers**:
```php
class MyUpdateHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_config = $tool_def['handler_config'] ?? [];
        $original_id = $parameters['original_id'] ?? null; // Required for updates
        return ['success' => true, 'data' => ['updated_id' => $original_id, 'modifications' => $changes]];
    }
}

add_filter('dm_handlers', function($handlers) {
    $handlers['my_update'] = [
        'type' => 'update',
        'class' => 'MyUpdateHandler',
        'label' => __('My Update Handler'),
        'description' => __('Updates existing content')
    ];
    return $handlers;
});
```

## System Integration

**Import/Export**:
```php
do_action('dm_import', 'pipelines', $csv_data);
do_action('dm_export', 'pipelines', [$pipeline_id]);
```

**AJAX**: WordPress hooks with `dm_ajax_actions` nonce + `manage_options`
**Templates**: Filter-based rendering
**Jobs**: Clear processed items/jobs via modal

## Usage Examples

**Single Platform (Recommended)**:
```php
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'RSS to Twitter']);
apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
apply_filters('dm_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
apply_filters('dm_create_step', null, ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('dm_create_flow', null, ['pipeline_id' => $pipeline_id]);
do_action('dm_run_flow_now', $flow_id, 'manual');
```

**Multi-Platform (AI→Publish→AI→Publish pattern)**:
```php
// Fetch → AI → Publish → AI → Publish
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'Multi-Platform']);
// Add steps: fetch, ai (twitter), publish (twitter), ai (facebook), publish (facebook)
```

**Content Update (Fetch→AI→Update pattern)**:
```php
// WordPress content enhancement workflow
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'Content Enhancement']);
apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
apply_filters('dm_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
apply_filters('dm_create_step', null, ['step_type' => 'update', 'pipeline_id' => $pipeline_id]);
```

> **Note**: AI steps discover handler tools for immediate next step only. Update step requires `original_id` in metadata for content modification.

## Development

```bash
composer install && composer test
./build.sh  # Production build
```

**PSR-4 Structure**: `inc/Core/`, `inc/Engine/` - strict case-sensitive paths
**Filter Registration**: 27 `*Filters.php` files auto-loaded via composer.json (containing 50+ filter hooks)

## Extensions

**Extension System**: Complete extension development framework with LLM prompt templates in `/extensions/` (development builds only)

**Discovery**: Filter-based auto-discovery system - extensions register using WordPress filters
**Template**: `/extensions/extension-prompt.md` - Fill placeholders, give to LLM for complete extensions
**Types**: Fetch handlers, Publish handlers, Update handlers, AI tools, Admin pages, Database services

**Extension Points**:
```php
add_filter('dm_handlers', [$this, 'register_handlers']);     // Fetch/publish/update handlers
add_filter('ai_tools', [$this, 'register_ai_tools']);       // AI capabilities
add_filter('dm_steps', [$this, 'register_steps']);          // Custom step types
add_filter('dm_db', [$this, 'register_database']);          // Database services
add_filter('dm_admin_pages', [$this, 'register_pages']);    // Admin interfaces
```

**Extension Pattern**:
```php
<?php
/**
 * Plugin Name: My Data Machine Extension
 * Requires Plugins: data-machine
 */
class MyExtension {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        add_filter('dm_handlers', [$this, 'register_handlers']);
        add_filter('ai_tools', [$this, 'register_ai_tools']);
    }
    
    public function register_handlers($handlers) {
        $handlers['my_handler'] = [
            'name' => __('My Handler'),
            'steps' => ['publish'],
            'auth_required' => false
        ];
        return $handlers;
    }
}
new MyExtension();
```

**Required Interfaces**:
- **Fetch**: `get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array`
- **Publish**: `handle_tool_call(array $parameters, array $tool_def = []): array`
- **Update**: `handle_tool_call(array $parameters, array $tool_def = []): array` (requires `original_id`)
- **Steps**: `execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array`

**Error Handling**:
```php
try {
    return $this->process_data($data, $job_id);
} catch (Exception $e) {
    do_action('dm_log', 'error', $e->getMessage(), ['flow_step_id' => $flow_step_id]);
    return $data;
}
```

## OAuth Integration

**Unified System**: Centralized `dm_oauth` filter handles all providers

```php
// Account operations
$account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);

// URLs
$callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');
$auth_url = apply_filters('dm_get_oauth_auth_url', '', 'twitter');
```

**URLs**: `/dm-oauth/{provider}/` with `manage_options` security - all OAuth operations require admin capabilities
**Storage**: `dm_auth_data` option per handler

**Requirements**:
- **Reddit**: OAuth2 (client_id/client_secret)
- **Twitter**: OAuth 1.0a (consumer_key/consumer_secret)
- **Facebook**: OAuth2 (app_id/app_secret)
- **Threads**: OAuth2 (same as Facebook)
- **Google Sheets**: OAuth2 (client_id/client_secret)
- **Bluesky**: App Password (username/app_password)

## Status & Rules

**Status Detection**: `apply_filters('dm_detect_status', 'green', $context, $data)`
- Values: `red` (error), `yellow` (warning), `green` (ready)
- Contexts: `ai_step`, `handler_auth`, `wordpress_draft`, `files_status`, `subsequent_publish_step`

**Core Rules**:
- Engine agnostic (no hardcoded step types in `/inc/Engine/`)
- Filter-based service discovery only
- Security: `wp_unslash()` BEFORE `sanitize_text_field()`
- CSS namespace: `dm-` prefix
- Auth: `manage_options` only
- Field naming: `pipeline_step_id` (UUID4)
- AJAX: `dm_ajax_actions` nonce validation
