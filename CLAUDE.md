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

// Templates & Files
apply_filters('dm_render_template', '', $template, $data);
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;

// AI Integration
$result = apply_filters('ai_request', $request, 'openrouter');
$tools = apply_filters('ai_tools', []);

// OAuth Operations (see OAuth Integration section for complete details)
apply_filters('dm_oauth', [], 'retrieve', 'handler');
apply_filters('dm_get_oauth_url', '', 'provider');

// ID Generation & Status Detection
apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
apply_filters('dm_detect_status', 'green', 'context', $data);
apply_filters('dm_generate_handler_tool', $tool, $handler_slug, $handler_config);

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

// CRUD
do_action('dm_create', 'pipeline', ['pipeline_name' => $name]);
do_action('dm_create', 'flow', ['flow_name' => $name, 'pipeline_id' => $id]);
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $id]);
do_action('dm_create', 'job', ['pipeline_id' => $id, 'flow_id' => $flow_id]);
do_action('dm_update_flow_handler', $flow_step_id, $handler_slug, $handler_settings);
do_action('dm_update_flow_schedule', $flow_id, $schedule_interval, $old_interval);
do_action('dm_delete', 'pipeline', $pipeline_id, ['cascade' => true]);

// System
do_action('dm_log', $level, $message, $context);
do_action('dm_ajax_route', 'action_name', 'page|modal');
do_action('dm_mark_item_processed', $flow_step_id, $source_type, $item_identifier, $job_id);
do_action('dm_auto_save', $pipeline_id);
```

## Architecture

**Action-Based Engine**: Three-action execution cycle drives all pipeline processing

**Stateless Execution Cycle**:
1. `dm_run_flow_now` → Creates job via `dm_create` action
2. `dm_execute_step` → Stateless step execution with explicit job_id parameter passing
3. `dm_schedule_next_step` → Action Scheduler step transitions with job context

**Job Status Management**: `completed`, `failed`, `completed_no_items` - engine exceptions fail jobs immediately

**Array-Based Processing**: Steps process arrays of data entries sequentially via Action Scheduler with explicit job_id parameter passing for complete job isolation

**Filter-Based Discovery**: Self-registering components via `*Filters.php`. All services discoverable via `apply_filters()`

**Pipeline+Flow**: 
- **Pipelines**: Reusable templates (steps 0-99, UUID4 IDs)
- **Flows**: Configured instances with handlers/scheduling
- **Execution**: Sequential item processing through Action Scheduler queue

**Database**: `wp_dm_pipelines`, `wp_dm_flows`, `wp_dm_jobs`, `wp_dm_processed_items` (deduplication tracking)

**Handlers**:
- **Fetch**: Files, RSS, Reddit, Google Sheets, WordPress
- **Publish**: Bluesky, Twitter, Threads, Facebook, Google Sheets, WordPress
- **AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Admin-Only**: Site-level auth, `manage_options` checks, zero user dependencies

**Stateless Execution**: Complete job isolation via explicit job_id parameter passing. No global variables, all step execute() methods receive job_id as first parameter for proper deduplication tracking

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

**Dual Tool Architecture**: AI steps discover both handler-specific tools (filtered by next step) and general tools (available to all AI steps)

**Handler Tools**: Platform-specific publishing tools (twitter_publish, facebook_publish, etc.) - only available when next step matches handler
**General Tools**: Universal capabilities (Google Search, data analysis, etc.) - available to all AI steps regardless of next step

**Tool Detection Logic**:
- Handler tools: Tools with `handler` property matching next step's handler
- General tools: Tools without `handler` property (universal availability)

**Discovery**: `apply_filters('ai_providers', [])`, `apply_filters('ai_models', $provider, $config)`

**Tool Execution Path**: PublishStep uses tool-first execution exclusively - checks for tools via `ai_tools` filter, then executes `handle_tool_call()` method directly

## Agentic Tool Calling

**Pure Tool Pattern**: All current publish handlers use ONLY `handle_tool_call()` method. Legacy `handle_publish()` fallback exists only for future handlers without tool support

**Dual Discovery System**: AI models discover both handler tools (next step only) and general tools (all AI steps) via `apply_filters('ai_tools', [])`

**Tool Registration**: Configuration-aware tool discovery supporting both handler-specific and general tools

```php
// Handler tool with dynamic configuration
add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
    if ($handler_slug === 'twitter') {
        $tool = [
            'class' => 'DataMachine\\Core\\Handlers\\Publish\\Twitter\\Twitter',
            'method' => 'handle_tool_call',
            'handler' => 'twitter', // Handler property = next step only
            'description' => 'Post content to Twitter (280 character limit)',
            'parameters' => [
                'content' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'required' => false]
            ],
            'handler_config' => $handler_config
        ];
        
        // Dynamic parameters based on configuration
        if ($handler_config['twitter_include_source'] ?? true) {
            $tool['parameters']['source_url'] = ['type' => 'string', 'required' => false];
        }
        if ($handler_config['twitter_enable_images'] ?? true) {
            $tool['parameters']['image_url'] = ['type' => 'string', 'required' => false];
        }
    }
    return $tool;
}, 10, 3);

// General tool registration (all AI steps)
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for current information and context',
        'parameters' => [
            'query' => ['type' => 'string', 'required' => true],
            'max_results' => ['type' => 'integer', 'required' => false],
            'site_restrict' => ['type' => 'string', 'required' => false]
        ]
        // NOTE: No 'handler' property - available to all AI steps
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

**Tool Execution**: Direct `handle_tool_call()` method execution with automatic dual discovery system
- Handler tools: Available only when next step matches handler 
- General tools: Available to all AI steps universally

**Context-Aware**: Handler tools filtered by next step, general tools always available for enhanced AI capabilities

**Configuration Integration**: Handler settings passed through tool definitions for dynamic parameter generation

**General Tool Implementation**:
```php
class GoogleSearch {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Validate required parameters
        if (empty($parameters['query'])) {
            return [
                'success' => false,
                'error' => 'Google Search tool call missing required query parameter',
                'tool_name' => 'google_search'
            ];
        }

        // Get search configuration
        $config = get_option('dm_search_config', []);
        $google_config = $config['google_search'] ?? [];
        
        if (empty($google_config['api_key']) || empty($google_config['search_engine_id'])) {
            return [
                'success' => false,
                'error' => 'Google Search tool not configured. Please configure API key and Search Engine ID.',
                'tool_name' => 'google_search'
            ];
        }

        // Extract parameters with validation
        $query = sanitize_text_field($parameters['query']);
        $max_results = min(max(intval($parameters['max_results'] ?? 5), 1), 10); // Limit 1-10 results
        $site_restrict = !empty($parameters['site_restrict']) ? sanitize_text_field($parameters['site_restrict']) : '';
        
        // Execute Google Custom Search API request with WordPress HTTP API
        $search_url = 'https://www.googleapis.com/customsearch/v1';
        $search_params = [
            'key' => $google_config['api_key'],
            'cx' => $google_config['search_engine_id'],
            'q' => $query,
            'num' => $max_results,
            'safe' => 'active'
        ];
        
        if ($site_restrict) {
            $search_params['siteSearch'] = $site_restrict;
        }
        
        $request_url = add_query_arg($search_params, $search_url);
        
        $response = wp_remote_get($request_url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to connect to Google Search API: ' . $response->get_error_message(),
                'tool_name' => 'google_search'
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => 'Google Search API error (HTTP ' . $response_code . '): ' . $response_body,
                'tool_name' => 'google_search'
            ];
        }
        
        $search_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Failed to parse Google Search API response',
                'tool_name' => 'google_search'
            ];
        }
        
        // Process search results
        $results = [];
        if (!empty($search_data['items'])) {
            foreach ($search_data['items'] as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'displayLink' => $item['displayLink'] ?? ''
                ];
            }
        }
        
        $search_info = $search_data['searchInformation'] ?? [];
        $total_results = $search_info['totalResults'] ?? '0';
        $search_time = $search_info['searchTime'] ?? 0;
        
        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'results_count' => count($results),
                'total_available' => $total_results,
                'search_time' => $search_time,
                'results' => $results
            ],
            'tool_name' => 'google_search'
        ];
    }
    
    public static function is_configured(): bool {
        $config = get_option('dm_search_config', []);
        $google_config = $config['google_search'] ?? [];
        
        return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
    }
}
```


## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress | None | Post/page content retrieval, taxonomy filtering |

| **Publish** | **Auth** | **Limit** | **Features** |
|-------------|----------|-----------|--------------|
| Twitter | OAuth 1.0a | 280 chars | URL replies, media upload |
| Bluesky | App Password | 300 chars | Media upload |
| Threads | OAuth2 | 500 chars | Media upload |
| Facebook | OAuth2 | No limit | Comment mode, link handling |
| WordPress | None | No limit | Taxonomy assignment |
| Google Sheets | OAuth2 | No limit | Row insertion |

| **General Tools** | **Auth** | **Features** |
|-------------------|----------|--------------|
| Google Search | API Key | Web search, site restriction, 1-10 results |

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

**Pages**: Pipelines (builder), Jobs (monitor), Logs (debug), Settings (control)

**Pipeline Builder**: Drag & drop reordering, auto-save, visual status indicators, modal configuration
**OAuth System**: Public `/dm-oauth/{provider}/` URLs, popup authentication, `manage_options` security
**Logs**: `/wp-content/uploads/data-machine-logs/data-machine.log`, configurable levels, 100-entry view

## Settings

**Location**: WordPress Settings → Data Machine

```php
$settings = dm_get_data_machine_settings();
$enabled_pages = dm_get_enabled_admin_pages();
$enabled_tools = dm_get_enabled_general_tools();
```

**Engine Mode**: Headless deployment (disables admin UI, preserves API)
**Page Control**: Selective admin page enable/disable  
**Tool Control**: General AI tool availability (Google Search, etc.)
**Global Prompt**: Universal system message for all AI requests

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

**Single Destination Pipeline (Recommended)**:
```php
do_action('dm_create', 'pipeline', ['pipeline_name' => 'RSS to Twitter']);
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
do_action('dm_create', 'step', ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
do_action('dm_create', 'step', ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]);
do_action('dm_update_flow_handler', $flow_step_id, 'rss', $settings);
do_action('dm_update_flow_schedule', $flow_id, 'active', 'hourly');
```

**Multi-Platform Pipeline (Advanced - Alternating Pattern)**:
```php
// Pattern: Fetch → AI → Publish → AI → Publish
// Each AI step guides the next publish step
do_action('dm_create', 'pipeline', ['pipeline_name' => 'Multi-Platform Content']);
do_action('dm_create', 'step', ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
do_action('dm_create', 'step', ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]); // Twitter AI
do_action('dm_create', 'step', ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]); // Twitter
do_action('dm_create', 'step', ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]); // Facebook AI
do_action('dm_create', 'step', ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]); // Facebook
```

**Execution & Testing**:
```php
do_action('dm_run_flow_now', $flow_id, 'manual_trigger');
do_action('dm_delete', 'processed_items', $flow_id, ['delete_by' => 'flow_id']);
```

> **Important**: AI steps discover handler tools for the immediate next step + general tools universally. Multiple consecutive publish steps will execute without handler-specific AI guidance. General tools (search, analysis, data processing) remain available to all AI steps. The system will show yellow warning status for publish steps that follow other publish steps.

## Files Repository

**Path**: `/wp-content/uploads/data-machine/files/{flow_step_id}/`
**Isolation**: UUID-based namespaces per flow: `{pipeline_step_id}_{flow_id}`

```php
$repo = apply_filters('dm_files_repository', [])['files'] ?? null;
$repo->store_file($tmp_name, $filename, $flow_step_id);
do_action('dm_cleanup_old_files');
```

## Development

```bash
composer install && composer test
```

```php
define('WP_DEBUG', true);
window.dmDebugMode = true;
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

**Centralized Architecture**: `/inc/engine/filters/OAuth.php` provides unified OAuth operations, public URL rewrite system, and centralized data storage

**Central Operations**: Unified `dm_oauth` filter eliminates handler-specific OAuth code duplication

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

**Public URL System**: `/dm-oauth/{provider}/` rewrite rules enable external API callbacks without exposing wp-admin URLs. Template redirect handles OAuth callbacks with `manage_options` security checks.

**Data Storage**: Centralized `dm_auth_data` WordPress option stores both configuration and account data per handler: `$all_auth_data[$handler]['config']` and `$all_auth_data[$handler]['account']`

**Filter-Based Discovery**: Auth providers register via `dm_auth_providers` filter for automatic callback routing

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

**Engine Agnosticism**: No hardcoded step types in `/inc/engine/`
**Service Discovery**: Filter-based only - `$service = apply_filters('dm_service', [])['key'] ?? null`
**Security Pattern**: `wp_unslash()` BEFORE `sanitize_text_field()` - enforced universally across all OAuth handlers and form processing
**CSS Namespace**: `dm-` prefix
**Authentication**: `manage_options` checks only
**OAuth Storage**: Unified `dm_oauth` filter system with centralized URL rewriting
**Field Naming**: `pipeline_step_id` (UUID4)
**Job Failure**: Engine exceptions fail jobs immediately
**Logging**: Include relevant IDs in context
**AJAX Security**: Universal `dm_ajax_actions` nonce validation
**Tool Generation**: `dm_generate_handler_tool` enables configuration-aware tool definitions