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

// Pipeline Navigation
apply_filters('dm_get_next_pipeline_step_id', null, $pipeline_step_id);
apply_filters('dm_get_previous_pipeline_step_id', null, $pipeline_step_id);

// Creation System (Filter-Based)
$pipeline_id = apply_filters('dm_create_pipeline', null, $data);
$step_id = apply_filters('dm_create_step', null, $data);
$flow_id = apply_filters('dm_create_flow', null, $data);

// Templates & Files
apply_filters('dm_render_template', '', $template, $data);
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;
ai_http_render_template($template, $vars); // AI HTTP Client library function call

// AI Integration
$result = apply_filters('ai_request', $request, 'openrouter');
$tools = apply_filters('ai_tools', []);

// OAuth Operations (see OAuth Integration section for complete details)
apply_filters('dm_oauth', [], 'retrieve', 'handler');
apply_filters('dm_get_oauth_url', '', 'provider');

// ID Generation & Status Detection
apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
apply_filters('dm_detect_status', 'green', 'context', $data);

// Settings Administration
$settings = apply_filters('dm_get_data_machine_settings', []);
$enabled_pages = apply_filters('dm_get_enabled_admin_pages', []);
$enabled_tools = apply_filters('dm_get_enabled_general_tools', []);
apply_filters('dm_enabled_settings', $fields, $handler_slug, $step_type, $context);
apply_filters('dm_apply_global_defaults', $current_settings, $handler_slug, $step_type);

// Tool Configuration
apply_filters('dm_tool_configured', false, $tool_id);
apply_filters('dm_get_tool_config', [], $tool_id);
do_action('dm_save_tool_config', $tool_id, $config_data);

// Flow Step Operations
apply_filters('dm_split_flow_step_id', [], $flow_step_id);
apply_filters('dm_get_flow_step_config', [], $flow_step_id);
apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
```

**Essential Actions**:
```php
// Execution
do_action('dm_run_flow_now', $flow_id, $context);
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
do_action('dm_schedule_next_step', $job_id, $flow_step_id, $data);


// System
do_action('dm_log', $level, $message, $context);
do_action('dm_mark_item_processed', $flow_step_id, $source_type, $item_identifier, $job_id);
do_action('dm_auto_save', $pipeline_id);
do_action('dm_cleanup_old_files'); // File repository maintenance
```

## Architecture

**Action-Based Engine**: Three-action execution cycle drives all pipeline processing

**Stateless Execution Cycle**:
1. `dm_run_flow_now` → Creates job via `dm_create` action
2. `dm_execute_step` → Stateless step execution with explicit job_id parameter passing
3. `dm_schedule_next_step` → Action Scheduler step transitions with job context

**Job Status Management**: `completed`, `failed`, `completed_no_items` - engine exceptions fail jobs immediately

**Array-Based Processing**: Steps process arrays of data entries sequentially via Action Scheduler with explicit job_id parameter passing for complete job isolation

**Filter-Based Discovery**: Self-registering components via `*Filters.php` loaded through composer.json "files" array. Components auto-register at file load eliminating bootstrap dependencies.

**Self-Registration Pattern**:
```php
// Example: TwitterFilters.php
function dm_register_twitter_filters() {
    add_filter('dm_handlers', function($handlers) {
        $handlers['twitter'] = [
            'name' => __('Twitter'),
            'steps' => ['publish'],
            'auth_required' => true
        ];
        return $handlers;
    });
    
    add_filter('ai_tools', function($tools) {
        $tools['twitter_publish'] = [
            'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
            'method' => 'handle_tool_call',
            'handler' => 'twitter',
            'description' => 'Post content to Twitter',
            'parameters' => [/* ... */]
        ];
        return $tools;
    });
}
// Auto-call at file load - no bootstrap needed
dm_register_twitter_filters();
```

**Architecture Benefits**:
- **Zero Dependencies**: No bootstrap or initialization sequence required
- **Automatic Discovery**: Components discoverable immediately via `apply_filters()`  
- **PSR-4 Autoloading**: Composer autoloader handles all class loading
- **Pure Filter System**: All services accessed through filter patterns only

**Files Repository**: File storage and data packet management with flow-isolated namespacing and automatic cleanup

**Pipeline+Flow**: 
- **Pipelines**: Reusable templates (steps 0-99, UUID4 IDs)
- **Flows**: Configured instances with handlers/scheduling
- **Execution**: Sequential item processing through Action Scheduler queue

**Database**: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items` (deduplication tracking)

**Handlers**:
- **Fetch**: Files, RSS, Reddit, Google Sheets, WordPress (specific post IDs or query filtering)
- **Publish**: Bluesky, Twitter, Threads, Facebook, Google Sheets, WordPress
- **AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Admin-Only**: Site-level auth, `manage_options` checks, zero user dependencies
**Stateless Execution**: Complete job isolation via explicit job_id parameter passing

## AI Integration

**Tool-First Architecture**: AI execution prioritizes agentic tool calling over traditional request/response patterns. All publish handlers use ONLY `handle_tool_call()` method for execution.

**Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Usage**:
```php
$result = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');
```

**Dual Tool Architecture**: 
- **Handler Tools**: Platform-specific tools (twitter_publish, etc.) - available only when next step matches handler
- **General Tools**: Universal tools (Google Search, Local Search) - available to all AI steps

**Discovery**: `apply_filters('ai_providers', [])`, `apply_filters('ai_models', $provider, $config)`

## Agentic Tool Calling

**Pure Tool Pattern**: All publish handlers use ONLY `handle_tool_call()` method for agentic execution. Tool-first architecture enables AI models to interact directly with publishing platforms

**Dual Discovery System**: AI models discover both handler tools (next step only) and general tools (all AI steps) via `apply_filters('ai_tools', [])`

**Tool Registration Examples**:

```php
// Handler-specific tool (next step only)
add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
    if ($handler_slug === 'twitter') {
        $tools['twitter_publish'] = [
            'class' => 'DataMachine\\Core\\Handlers\\Publish\\Twitter\\Twitter',
            'method' => 'handle_tool_call',
            'handler' => 'twitter',
            'description' => 'Post content to Twitter (280 character limit)',
            'parameters' => [
                'content' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Tweet content (will be formatted and truncated if needed)'
                ]
            ],
            'handler_config' => $handler_config
        ];
    }
    return $tools;
}, 10, 3);

// General tools (all AI steps)
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for real-time information',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query to execute'
            ]
        ]
        // No 'handler' = available everywhere
    ];
    return $tools;
});
```

**Handler Implementation**:
```php
class Twitter {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Validate required parameters
        if (empty($parameters['content'])) {
            return [
                'success' => false,
                'error' => 'Twitter tool call missing required content parameter',
                'tool_name' => 'twitter_publish'
            ];
        }

        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        // Extract parameters
        $title = $parameters['title'] ?? '';
        $content = $parameters['content'] ?? '';
        $source_url = $parameters['source_url'] ?? null;
        
        // Get config from handler settings
        $include_source = $handler_config['twitter_include_source'] ?? true;
        $enable_images = $handler_config['twitter_enable_images'] ?? true;
        $url_as_reply = $handler_config['twitter_url_as_reply'] ?? false;

        // Get authenticated connection
        $connection = $this->auth->get_connection();
        if (is_wp_error($connection)) {
            return [
                'success' => false,
                'error' => 'Twitter authentication failed: ' . $connection->get_error_message(),
                'tool_name' => 'twitter_publish'
            ];
        }

        // Format tweet content (Twitter's character limit is 280)
        $tweet_text = $title ? $title . ": " . $content : $content;
        
        // Handle URL based on configuration
        $should_append_url = $include_source && !empty($source_url) && filter_var($source_url, FILTER_VALIDATE_URL) && !$url_as_reply;
        $link = $should_append_url ? ' ' . $source_url : '';
        $link_length = $link ? 24 : 0; // t.co link length
        $available_chars = 280 - $link_length;
        
        if (mb_strlen($tweet_text, 'UTF-8') > $available_chars) {
            $tweet_text = mb_substr($tweet_text, 0, $available_chars - 1) . '…';
        }
        $tweet_text .= $link;
        
        // Execute publishing logic and return standardized result
        return [
            'success' => true,
            'data' => [
                'tweet_id' => $tweet_id,
                'tweet_url' => $tweet_url,
                'content' => $tweet_text
            ],
            'tool_name' => 'twitter_publish'
        ];
    }
}
```

**Tool Execution**: Direct `handle_tool_call()` method execution with automatic discovery system

**Tool Discovery and Selection**:
```php
// Discovery and filtering in AI execution
$all_tools = apply_filters('ai_tools', []);
foreach ($all_tools as $tool_name => $tool_config) {
    if (isset($tool_config['handler']) && $tool_config['handler'] === $handler_slug) {
        $available_tools[$tool_name] = $tool_config;
    }
}
```

**Static Tool Parameters**: Tools have fixed parameters defined at registration time

**General Tool Capabilities**:
- **Google Search**: Web search, fact verification, trend analysis, site-specific searches. Real-time information access.
- **Local Search**: WordPress site search using WP_Query. Post type filtering, relevance ranking. Privacy-first (no external APIs).

**Tool Configuration**: Modal-based configuration via WordPress Settings → Data Machine → Tool Configuration.

**Configuration Check**:
```php
// Primary method - filter-based access
$configured = apply_filters('dm_tool_configured', false, 'google_search');
$config = apply_filters('dm_get_tool_config', [], 'google_search');
do_action('dm_save_tool_config', 'google_search', $config_data);

// Static method also available
$configured = GoogleSearch::is_configured();
$configured = LocalSearch::is_configured(); // Always returns true
```

**Requirements**:
- **Google Search**: API key + Search Engine ID (100 queries/day free)
- **Local Search**: Always available (no configuration needed)

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

| **General Tools** | **Auth** | **Features** |
|-------------------|----------|--------------|
| Google Search | API Key + Search Engine ID | Web search, site restriction, 1-10 results |
| Local Search | None | WordPress search, 1-20 results |

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

## Admin Interface

**Admin Pages**: Pipelines (builder), Jobs (monitor), Logs (debug)
**Settings**: WordPress Settings → Data Machine
**Features**: Drag & drop reordering with auto-save, visual status indicators, modal configuration, real-time log monitoring
**OAuth System**: Public `/dm-oauth/{provider}/` URLs, popup authentication, `manage_options` security

## Settings Administration

**Location**: WordPress Settings → Data Machine

**Admin Interface Control**: 
- **Engine Mode**: Disables all admin pages while preserving core functionality (headless deployment)
- **Admin Page Control**: Enable/disable individual admin pages (defaults to all enabled)  
- **General Tool Control**: Enable/disable AI tools (defaults to all enabled)
- **Global System Prompt**: Prepended to all AI interactions for consistent brand voice
- **Tool Configuration**: Modal-based configuration for tools requiring API keys

**WordPress Global Defaults**: Site-wide defaults for post types, taxonomies, author, and post status to streamline handler configuration.

**Filter Patterns**:
```php
// Settings access
$settings = apply_filters('dm_get_data_machine_settings', []);
$enabled_pages = apply_filters('dm_get_enabled_admin_pages', []);
$enabled_tools = apply_filters('dm_get_enabled_general_tools', []);

// WordPress handler configuration filtering
apply_filters('dm_enabled_settings', $fields, $handler_slug, $step_type, $context);
apply_filters('dm_apply_global_defaults', $current_settings, $handler_slug, $step_type);

// Tool configuration
$configured = apply_filters('dm_tool_configured', false, $tool_id);
$config = apply_filters('dm_get_tool_config', [], $tool_id);
do_action('dm_save_tool_config', $tool_id, $config_data);
```

## Step Implementation

**Pipeline Steps** (fetch, ai, publish):
```php
class MyStep {
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        // Job ID provided as explicit parameter - no global state
        // Mark items as processed with explicit job_id
        do_action('dm_mark_item_processed', $flow_step_id, 'my_step', $item_identifier, $job_id);
        
        $processed_entry = [
            'type' => 'my_step',
            'content' => ['title' => $title, 'body' => $processed_content],
            'metadata' => [
                'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown',
                'job_id' => $job_id
            ],
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

**Fetch Handlers**:
```php
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        // Job ID parameter for processed items tracking and deduplication
        // Parameter-less constructor for pure filter-based architecture
        
        // Mark items as processed with explicit job_id
        do_action('dm_mark_item_processed', $flow_step_id, 'my_handler', $item_identifier, $job_id);
        
        return [
            'processed_items' => $items // Array of fetched data items
        ];
    }
}
```

**Publish Handlers** (Tool-First):
```php
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        // Execute publishing logic
        return [
            'success' => true,
            'data' => ['id' => $result_id, 'url' => $result_url],
            'tool_name' => 'my_handler_publish'
        ];
    }
}
```

## Import/Export

```php
do_action('dm_import', 'pipelines', $csv_data);
do_action('dm_export', 'pipelines', [$pipeline_id]);
```

**CSV Schema**: `pipeline_id, pipeline_name, step_position, step_type, step_config, flow_id, flow_name, handler, settings`

## System Integration

**AJAX Security**: Direct WordPress AJAX hooks with `dm_ajax_actions` nonce validation and `manage_options` checks

**Jobs Management**: Clear processed items, clear jobs (failed/all), development testing via modal

**Templates**: Filter-based template rendering for modals and admin pages

## Usage Examples

**Single Destination Pipeline (Recommended)**:
```php
// Filter-based creation (current approach)
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'RSS to Twitter']);
$step_1_id = apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
$step_2_id = apply_filters('dm_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
$step_3_id = apply_filters('dm_create_step', null, ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('dm_create_flow', null, ['pipeline_id' => $pipeline_id, 'flow_name' => 'Twitter Flow']);

// Flow configuration via UI or filter-based updates
// Use admin interface for handler/schedule configuration
```

**Multi-Platform Pipeline (Advanced - Alternating Pattern)**:
```php
// Pattern: Fetch → AI → Publish → AI → Publish
// Each AI step guides the next publish step
$pipeline_id = apply_filters('dm_create_pipeline', null, ['pipeline_name' => 'Multi-Platform Content']);
apply_filters('dm_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
apply_filters('dm_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]); // Twitter AI
apply_filters('dm_create_step', null, ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]); // Twitter
apply_filters('dm_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]); // Facebook AI
apply_filters('dm_create_step', null, ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]); // Facebook
```

**Execution & Testing**:
```php
do_action('dm_run_flow_now', $flow_id, 'manual_trigger');
do_action('dm_delete', 'processed_items', $flow_id, ['delete_by' => 'flow_id']);
```

> **Important**: AI steps discover handler tools for the immediate next step + general tools universally. Multiple consecutive publish steps execute without handler-specific AI guidance. General tools (search, analysis, data processing) remain available to all AI steps. The `dm_detect_status` filter returns `yellow` warning for `subsequent_publish_step` context.

## Files Repository

**Flow-Isolated Storage**: UUID-based namespacing with Action Scheduler integration. Filter-based access via `dm_files_repository`. Automatic cleanup operations.

## Development

```bash
composer install && composer test
./build.sh  # Production build
```

## PSR-4 Architecture

**Directory Structure**: PSR-4 autoloading with proper case paths (`inc/Core/`, `inc/Engine/`)

**Namespace Mapping**:
```php
"DataMachine\\Core\\" => "inc/Core/",
"DataMachine\\Admin\\" => "inc/Admin/",
"DataMachine\\Engine\\" => "inc/Engine/",
"DataMachine\\Services\\" => "inc/Services/",
"DataMachine\\Helpers\\" => "inc/Helpers/"
```

**Filter Registration**: Automatic loading via composer.json "files" array with 57 filter files

**Build System**: `build.sh` script creates production-ready zip with optimized autoloader

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
    $result = $this->process_data($data, $job_id);
    return $result;
} catch (Exception $e) {
    do_action('dm_log', 'error', $e->getMessage(), [
        'flow_step_id' => $flow_step_id,
        'job_id' => $job_id
    ]);
    return $data; // Return original on failure
}
```

**Job Status**: `completed`, `failed`, `completed_no_items`

## OAuth Integration

**Centralized Operations**: Unified `dm_oauth` filter eliminates handler-specific OAuth code duplication

```php
// Account Management (access tokens, session data)
$account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration Management (API keys, client secrets)
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);
apply_filters('dm_oauth', false, 'clear_config', 'twitter');
apply_filters('dm_oauth', false, 'clear_all', 'twitter');

// URL Generation
$callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');
$auth_url = apply_filters('dm_get_oauth_auth_url', '', 'twitter');
```

**Public URLs**: `/dm-oauth/{provider}/` rewrite rules enable external API callbacks with `manage_options` security

**Data Storage**: Centralized `dm_auth_data` option stores config and account data per handler

**Handler Requirements**:
- **Reddit**: OAuth2 Authorization Code Grant (read access, client_id/client_secret)
- **Twitter**: OAuth 1.0a (read/write access, consumer_key/consumer_secret)
- **Facebook**: OAuth2 (pages_manage_posts scope, app_id/app_secret)
- **Threads**: OAuth2 (threads_basic, threads_content_publish scopes)
- **Google Sheets**: OAuth2 (spreadsheets scope, client_id/client_secret)
- **Bluesky**: App Password authentication (username/app_password)

## Status Detection

```php
$status = apply_filters('dm_detect_status', 'green', 'context', $data);
```

**Values**: `red` (error), `yellow` (warning), `green` (ready)
**Contexts**: `ai_step`, `handler_auth`, `wordpress_draft`, `files_status`, `subsequent_publish_step`

## Rules

**Engine Agnosticism**: No hardcoded step types in `/inc/Engine/`
**Service Discovery**: Filter-based only - `$service = apply_filters('dm_service', [])['key'] ?? null`
**Security Pattern**: `wp_unslash()` BEFORE `sanitize_text_field()` - enforced universally across all OAuth handlers and form processing
**CSS Namespace**: `dm-` prefix
**Authentication**: `manage_options` checks only
**OAuth Storage**: Unified `dm_oauth` filter system with centralized URL rewriting
**Field Naming**: `pipeline_step_id` (UUID4)
**Job Failure**: Engine exceptions fail jobs immediately
**Logging**: Include relevant IDs in context
**AJAX Security**: Universal `dm_ajax_actions` nonce validation
