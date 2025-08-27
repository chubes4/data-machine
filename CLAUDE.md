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

// AI Step Persistence
do_action('dm_update_system_prompt', $pipeline_step_id, $system_prompt); // Pipeline-level templates
do_action('dm_update_flow_user_message', $flow_step_id, $user_message); // Flow-level instances
$flow_step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
$pipeline_config = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);

// Site Context
$context = SiteContext::get_context();
$formatted_context = SiteContext::format_for_ai($context);
SiteContext::clear_cache();

// System
do_action('dm_log', $level, $message, $context);
do_action('dm_auto_save', $pipeline_id);
do_action('dm_fail_job', $job_id, $reason, $context_data); // Explicit job failure with configurable cleanup
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
- **Handlers**: Fetch (Files, RSS, Reddit, Google Sheets, WordPress, WordPress Media) | Publish (Twitter, Bluesky, Threads, Facebook, Google Sheets, WordPress) | Update (Content modification, existing post updates) | AI (OpenAI, Anthropic, Google, Grok, OpenRouter)
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

**Dual-Layer Persistence Model**:
- **Pipeline Level**: System prompts stored per `pipeline_step_id` - serve as reusable templates
- **Flow Level**: User messages stored per `flow_step_id` - enable instance-specific customization  
- **Inheritance**: Flow steps inherit pipeline system prompts, add flow-specific user messages

```php
$result = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-5-mini'
], 'anthropic');
```

### AI Request Pipeline

**Message Injection Priority System**: AI requests receive multiple system messages in priority order:

1. **Priority 1 - AI Step Directives** (`AIStepDirective`): Dynamic tool-specific prompts
2. **Priority 3 - Site Context** (`AIStepDirective`): WordPress site information injection  
3. **Priority 5 - Global System Prompt**: User-configured system prompt

```php
// Automatic directive injection via ai_request filter hooks
add_filter('ai_request', [AIStepDirective::class, 'inject_dynamic_directive'], 1, 4);
add_filter('ai_request', [AIStepDirective::class, 'inject_site_context'], 3, 4);
```

**AI Step Directive Content**:
- Context-aware role clarification (detects next-step handlers for targeted guidance)
- Available tools enumeration with descriptions  
- Task completion strategy and workflow context

**Site Context Integration**:
- WordPress site metadata (name, URL, language)
- Post types with counts and capabilities
- Taxonomies with term counts and associations
- User statistics and theme information
- Cached with automatic invalidation on content changes

**AI Conversation State Management**:
- Centralized conversation history building from data packets
- Tool result formatting for optimal AI model consumption
- Context preservation across multi-turn conversations
- Specialized formatters for search, publish, and generic tool results

**AI Step Execution Model**:
- AI steps can run standalone using flow-level user messages when no fetch step precedes
- System prompts (pipeline-level) provide consistent behavior templates
- User messages (flow-level) enable different prompts per flow instance
- Multi-turn conversation support with context preservation across tool executions

### Tool Management

**Three-Layer Enablement**:
1. **Global Settings**: Admin toggles tools site-wide (`enabled_tools` setting)
2. **Modal Selection**: Per-step tool activation in pipeline configuration
3. **Configuration Validation**: Tools requiring API keys checked at runtime

**Tool Discovery Hierarchy**:
```php
// Global level (settings filtering)
$global_tools = AIStepTools->get_global_enabled_tools();

// Modal level (per-step selections)
$modal_tools = AIStepTools->get_step_enabled_tools($pipeline_step_id);

// Runtime validation (configuration requirements)
$tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
```

**Tool Categories**:
- **Handler Tools**: Step-specific (twitter_publish, wordpress_update) - available when next step matches handler type
- **General Tools**: Universal (Google Search, Local Search, Google Search Console) - available to all AI agents

### Tool Registration

**Handler-Specific Tools**:
```php
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
```

**General Tools**:
```php
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for current information and context',
        'requires_config' => true,
        'parameters' => ['query' => ['type' => 'string', 'required' => true]]
        // No 'handler' property = universal tool
    ];
    return $tools;
});
```

### Tool Implementation

**Handler Tool Implementation**:
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


### Tool Configuration

**Configuration Management**:
```php
$configured = apply_filters('dm_tool_configured', false, 'google_search');
$config = apply_filters('dm_get_tool_config', [], 'google_search');
do_action('dm_save_tool_config', 'google_search', $config_data);
```

**Modal HTML Generation**:
```php
// Automatic tool selection rendering in pipeline modals
$html = AIStepTools->render_tools_html($pipeline_step_id);
// Includes configuration warnings and per-step enablement checkboxes
```

**Tool Capabilities**:
- **Google Search**: Web search, site restriction (API key + Search Engine ID required)
- **Local Search**: WordPress WP_Query search (no configuration needed)
- **Google Search Console**: SEO performance analysis, keyword opportunities, internal linking suggestions (OAuth2 required)

## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress | None | Post/page content retrieval, specific post ID targeting, taxonomy filtering, timeframe filtering |
| WordPress Media | None | Media library attachments, file URLs, metadata handling |

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
| Google Search Console | OAuth2 | SEO performance analysis, keyword opportunities, internal link suggestions |

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

## Step Configuration Persistence

**Dual-Layer Architecture**:

**Pipeline Level (Templates)**:
- System prompts stored in `pipeline_config` per `pipeline_step_id`
- Shared across all flow instances using this pipeline
- Updated via `dm_update_system_prompt` action

**Flow Level (Instances)**:
- User messages stored in `flow_config` per `flow_step_id`
- Instance-specific customization per flow
- Updated via `dm_update_flow_user_message` action

**Configuration Access**:
```php
// Flow step configuration (inherits from pipeline + flow-specific data)
$flow_step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
// Contains: user_message (flow-level), system_prompt (inherited from pipeline), handler config

// Pipeline configuration (templates)
$pipeline_config = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
// Contains: system_prompt, step configuration templates

// Flow configuration (instance data)  
$flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
// Contains: flow_step configurations, user_message overrides
```

**AI Step Execution**:
- Reads `user_message` from flow step config (flow-specific)
- Inherits system prompt template from pipeline step
- Can run standalone when flow has user_message but no preceding fetch step

## Admin Interface

**Pages**: Pipelines, Jobs, Logs
**Settings**: WordPress Settings → Data Machine
**Features**: Drag & drop, auto-save, status indicators, modal configuration
**OAuth**: `/dm-oauth/{provider}/` URLs with popup flow

## Settings

**Controls**: Engine Mode (headless - disables admin pages only), admin page toggles, tool toggles, site context toggle, global system prompt, job data cleanup on failure
**WordPress Defaults**: Site-wide post type, taxonomy, author, status defaults
**Tool Configuration**: Modal setup for API keys and service configurations
**Site Context**: Automatic WordPress context injection (enabled by default)
**Job Data Cleanup**: Clean up job data files on failure (enabled by default, disable for debugging failed jobs)

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

## Logging & Debugging

**Centralized Logging**: All components use `dm_log` action for consistent debug output

```php
// Standard logging format
do_action('dm_log', $level, $message, $context_array);

// Examples from codebase
do_action('dm_log', 'debug', 'AI Step Directive: Injected system directive', [
    'tool_count' => count($tools),
    'available_tools' => array_keys($tools),
    'directive_length' => strlen($directive)
]);

do_action('dm_log', 'debug', 'AIStepTools: Generated HTML attributes', [
    'pipeline_step_id' => $pipeline_step_id,
    'tool_id' => $tool_id,
    'checked_attr_output' => $checked_attr,
    'disabled_attr_output' => $disabled_attr
]);
```

**Log Levels**: `debug`, `info`, `warning`, `error`
**Context Data**: Structured arrays with relevant debugging information
**Timestamps**: Automatic microtime tracking in critical operations

**Key Logging Points**:
- AI request processing and directive injection
- Tool selection and configuration validation
- Pipeline step execution and data flow
- OAuth authentication and configuration
- Item processing and deduplication
- Site context generation and caching

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

> **Note**: AI agents discover handler tools for immediate next step only. Update step requires `original_id` in metadata for content modification.

## Development

```bash
composer install && composer test
./build.sh  # Production build
```

**PSR-4 Structure**: `inc/Core/`, `inc/Engine/` - strict case-sensitive paths
**Filter Registration**: 29 `*Filters.php` files auto-loaded via composer.json (containing 85+ filter registrations)
**Key Auto-loaded Classes**: `AIStepDirective.php`, `AIConversationState.php` - automatic filter registration

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
add_filter('dm_tool_configured', [$this, 'check_config']);  // Configuration validation
```

**Enhanced Extension Pattern**:
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
        add_filter('dm_tool_configured', [$this, 'check_tool_configuration'], 10, 2);
    }
    
    public function register_handlers($handlers) {
        $handlers['my_handler'] = [
            'type' => 'publish',
            'class' => 'MyExtension\\MyHandler',
            'label' => __('My Handler'),
            'description' => __('Custom publishing handler with advanced features')
        ];
        return $handlers;
    }
    
    public function register_ai_tools($tools, $handler_slug = null, $handler_config = []) {
        // Handler-specific tool
        if ($handler_slug === 'my_handler') {
            $tools['my_publish'] = [
                'class' => 'MyExtension\\MyHandler',
                'method' => 'handle_tool_call',
                'handler' => 'my_handler',
                'description' => 'Publish to my custom platform',
                'parameters' => ['content' => ['type' => 'string', 'required' => true]],
                'handler_config' => $handler_config
            ];
        }
        
        // General tool (available to all AI steps)
        $tools['my_search'] = [
            'class' => 'MyExtension\\MySearchTool',
            'method' => 'handle_tool_call',
            'description' => 'Search my custom data source',
            'requires_config' => true,
            'parameters' => ['query' => ['type' => 'string', 'required' => true]]
        ];
        
        return $tools;
    }
    
    public function check_tool_configuration($is_configured, $tool_id) {
        if ($tool_id === 'my_search') {
            $config = apply_filters('dm_get_tool_config', [], $tool_id);
            return !empty($config['api_key']);
        }
        return $is_configured;
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

**Unified System**: Centralized `dm_oauth` filter handles all providers with configuration validation

```php
// Account operations
$account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration with validation
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);
$is_configured = apply_filters('dm_tool_configured', false, 'twitter');

// URLs
$callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');
$auth_url = apply_filters('dm_get_oauth_auth_url', '', 'twitter');
```

**Configuration Validation**: Tools requiring configuration are automatically validated before enablement:
```php
// Tool configuration check (applied in modal rendering and tool selection)
$tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
$requires_config = !empty($tool_config['requires_config']);

// Disabled in UI if configuration needed but not provided
$disabled = $requires_config && !$tool_configured;
```

**URLs**: `/dm-oauth/{provider}/` with `manage_options` security - all OAuth operations require admin capabilities
**Storage**: `dm_auth_data` option per handler
**Validation**: Real-time configuration checks prevent unconfigured tools from being enabled

**Requirements**:
- **Reddit**: OAuth2 (client_id/client_secret)
- **Twitter**: OAuth 1.0a (consumer_key/consumer_secret)
- **Facebook**: OAuth2 (app_id/app_secret)
- **Threads**: OAuth2 (same as Facebook)
- **Google Sheets**: OAuth2 (client_id/client_secret)
- **Google Search Console**: OAuth2 (client_id/client_secret)
- **Bluesky**: App Password (username/app_password)
- **Google Search**: API Key + Custom Search Engine ID (not OAuth)

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
