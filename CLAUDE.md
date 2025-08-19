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

// OAuth Operations
apply_filters('dm_oauth', [], 'retrieve', 'handler');
apply_filters('dm_oauth', null, 'store', 'handler', $data);
apply_filters('dm_oauth', [], 'get_config', 'handler');
apply_filters('dm_oauth', null, 'store_config', 'handler', $config);
apply_filters('dm_oauth', false, 'clear', 'handler');
apply_filters('dm_oauth', false, 'clear_config', 'handler');
apply_filters('dm_oauth', false, 'clear_all', 'handler');

// OAuth URL Generation
apply_filters('dm_get_oauth_url', '', 'provider');
apply_filters('dm_get_oauth_auth_url', '', 'provider');

// ID Generation & Status Detection
apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
apply_filters('dm_detect_status', 'green', 'context', $data);
apply_filters('dm_generate_handler_tool', $tool, $handler_slug, $handler_config);

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

**Stateless Execution Model**: Complete removal of global variables ensures job isolation:
- No `global $dm_current_job_id` usage anywhere in codebase
- All step execute() methods receive job_id as explicit first parameter
- Fetch handlers updated to accept job_id parameter instead of flow_id
- Processed items tracking requires explicit job_id for isolation

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
**General Tools**: Universal capabilities (search, analysis, data processing, etc.) - available to all AI steps regardless of next step

**Tool Detection Logic**:
- Handler tools: Tools with `handler` property matching next step's handler
- General tools: Tools without `handler` property (universal availability)

**Discovery**: `apply_filters('ai_providers', [])`, `apply_filters('ai_models', $provider, $config)`

**Tool Execution Path**: PublishStep uses tool-first execution exclusively - checks for tools via `ai_tools` filter, then executes `handle_tool_call()` method directly

## Agentic Tool Calling

**Pure Tool Pattern**: All current publish handlers use ONLY `handle_tool_call()` method. Legacy `handle_publish()` fallback exists only for future handlers without tool support

**Dual Discovery System**: AI models discover both handler tools (next step only) and general tools (all AI steps) via `apply_filters('ai_tools', [])`

**Tool Registration**:
```php
// Static tool registration (basic)
add_filter('ai_tools', function($tools) {
    $tools['twitter_publish'] = dm_get_twitter_tool();
    return $tools;
});

// Dynamic tool generation (configuration-aware)
add_filter('dm_generate_handler_tool', function($tool, $handler_slug, $handler_config) {
    if ($handler_slug === 'twitter') {
        return dm_get_twitter_tool($handler_config);
    }
    return $tool;
}, 10, 3);

// Tool definition with dynamic parameters
function dm_get_twitter_tool(array $handler_config = []): array {
    $tool = [
        'class' => 'DataMachine\\Core\\Handlers\\Publish\\Twitter\\Twitter',
        'method' => 'handle_tool_call',
        'handler' => 'twitter',
        'description' => 'Post content to Twitter (280 character limit)',
        'parameters' => [
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Tweet content (will be formatted and truncated if needed)'
            ],
            'title' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional title to prepend to content'
            ]
        ]
    ];
    
    // Store configuration for execution
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Get configuration values with defaults
    $include_source = $handler_config['twitter_include_source'] ?? true;
    $enable_images = $handler_config['twitter_enable_images'] ?? true;
    $url_as_reply = $handler_config['twitter_url_as_reply'] ?? false;
    
    // Add conditional parameters based on configuration
    if ($include_source) {
        $description = $url_as_reply ? 'Optional source URL to post as reply tweet' : 'Optional source URL to append to tweet';
        $tool['parameters']['source_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => $description
        ];
    }
    
    if ($enable_images) {
        $tool['parameters']['image_url'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'Optional image URL to attach to tweet'
        ];
    }
    
    return $tool;
}

// General tool registration (no handler property)
add_filter('ai_tools', function($tools) {
    $tools['search_web'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\WebSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search the web for current information',
        'parameters' => [
            'query' => ['type' => 'string', 'required' => true],
            'max_results' => ['type' => 'integer', 'required' => false]
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
class WebSearch {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $query = $parameters['query'] ?? '';
        $max_results = $parameters['max_results'] ?? 5;
        
        // Execute search logic
        $results = $this->search($query, $max_results);
        
        return [
            'success' => true,
            'data' => ['results' => $results, 'query' => $query],
            'tool_name' => 'search_web'
        ];
    }
}
```

**Character Limits**: Hardcoded per platform (Twitter: 280, Bluesky: 300, Threads: 500)

**Enhanced Social Features**:

**Twitter URL Reply**: Post source URLs as separate reply tweets
```php
'twitter_include_source' => true,    // Enable URL parameter access
'twitter_enable_images' => true,     // Enable image upload capability
'twitter_url_as_reply' => false,     // Post URLs as reply tweets (default: inline)

// Reply mode result includes both tweets
return [
    'success' => true,
    'data' => [
        'tweet_id' => $tweet_id,
        'tweet_url' => $tweet_url,
        'reply_tweet_id' => $reply_tweet_id,     // Only when reply mode
        'reply_tweet_url' => $reply_tweet_url,   // Only when reply mode
        'content' => $tweet_text
    ]
];
```

**Facebook Comment Mode**: Post URLs as separate comments
```php
'link_handling' => 'append',    // Add URL to post content (default)
'link_handling' => 'replace',   // Replace post content with URL only
'link_handling' => 'comment',   // Post URL as Facebook comment
'link_handling' => 'none',      // No URL inclusion

// Comment mode result includes both post and comment
return [
    'success' => true,
    'data' => [
        'post_id' => $post_id,
        'post_url' => $post_url,
        'comment_id' => $comment_id,         // Only when comment mode
        'comment_url' => $comment_url,       // Only when comment mode
        'content' => $post_text
    ]
];
```

**WordPress Taxonomy Assignment**: Dynamic category/tag/custom taxonomy support
```php
// Tool definition with taxonomy parameters
'parameters' => [
    'title' => ['type' => 'string', 'required' => true],
    'content' => ['type' => 'string', 'required' => true],
    'category' => ['type' => 'string', 'required' => false],
    'tags' => ['type' => 'array', 'required' => false],
    // Custom taxonomies dynamically added based on post type
];

// Result includes taxonomy assignments
return [
    'success' => true,
    'data' => [
        'post_id' => $post_id,
        'post_url' => $post_url,
        'taxonomy_results' => [
            'category' => ['success' => true, 'category_id' => 5],
            'tags' => ['success' => true, 'tag_count' => 3],
            'custom_taxonomy' => ['success' => true, 'term_count' => 1]
        ]
    ]
];
```

## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress | None | Post/page content retrieval, taxonomy filtering |

**Note**: All fetch handlers accept job_id as explicit parameter for stateless execution and proper job isolation.

| **Publish** | **Auth** | **Features** |
|-------------|----------|--------------|
| Bluesky | App Password | Text posts (300 chars), media upload, session management |
| Twitter | OAuth 1.0a | Tweets (280 chars), media, URL replies, image uploads |
| Threads | OAuth2 | Text posts (500 chars), media upload, Meta API integration |
| Facebook | OAuth2 | Page posts, media upload, URL comments, link handling modes |
| Google Sheets | OAuth2 | Row insertion, data logging, spreadsheet management |
| WordPress | None | Post creation, taxonomy assignment, draft/publish modes |

**Tool-First Execution**: All publish handlers use ONLY `handle_tool_call()` method with configuration-aware tool generation via `dm_generate_handler_tool` filter.

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
4. **Jobs Page**: Monitor execution, clear jobs, view status
5. **Logs Page**: System logging, error tracking, debug information
6. **Testing**: Manual execution with `dm_run_flow_now`

**OAuth URL System**: Public `/dm-oauth/{provider}/` rewrite URLs for external API callbacks with centralized authentication flow

**Authentication Assets**: `pipeline-auth.js` handles OAuth popup window closure and parent communication for seamless modal authentication

**Security Architecture**: Universal `manage_options` checks, centralized `dm_ajax_actions` nonce validation, OAuth callback permission verification

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

**Flow-Isolated Architecture**: `/wp-content/uploads/data-machine/files/{flow_step_id}/`

**Concrete Example**:
```
/wp-content/uploads/data-machine/files/
├── 12345678-abcd-efgh-ijkl-mnopqrstuvwx_f1a2b3c4/     # fetch step files
│   ├── document1.pdf
│   └── data.csv
├── 12345678-abcd-efgh-ijkl-mnopqrstuvwx_f1a2b3c4/     # AI step files (same flow_step_id pattern)
└── 87654321-wxyz-mnop-qrst-uvwxyz123456_a9b8c7d6/     # different flow files
    └── output.json
```

**Path Patterns**:
- Flow isolation prevents cross-contamination between different pipeline flows
- UUID-based flow_step_id ensures unique namespaces: `{pipeline_step_id}_{flow_id}`
- Automatic cleanup on flow deletion

**Operations**:
```php
$repo = apply_filters('dm_files_repository', [])['files'] ?? null;

// Storage
$repo->store_file($tmp_name, $filename, $flow_step_id);
$repo->get_repository_path($flow_step_id);

// Cleanup
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

**Stateless Architecture**: Complete removal of global variables. Job ID passed explicitly as first parameter to all step execute() methods ensuring full job isolation and preventing cross-job data contamination

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

**UI Status Indicators**: `dm_detect_status` filter provides red/yellow/green status

```php
// AI step configuration status
$status = apply_filters('dm_detect_status', 'green', 'ai_step', [
    'pipeline_step_id' => $pipeline_step_id
]);

// Handler authentication status
$auth_status = apply_filters('dm_detect_status', 'green', 'handler_auth', [
    'handler_slug' => $handler_slug
]);

// WordPress draft mode detection
$draft_status = apply_filters('dm_detect_status', 'green', 'wordpress_draft', [
    'flow_step_id' => $flow_step_id
]);

// Files handler status
$files_status = apply_filters('dm_detect_status', 'green', 'files_status', [
    'flow_step_id' => $flow_step_id
]);

// Subsequent publish step detection
$subsequent_status = apply_filters('dm_detect_status', 'green', 'subsequent_publish_step', [
    'pipeline_step_id' => $pipeline_step_id
]);
```

**Status Values**: `'red'` (error/missing), `'yellow'` (warning), `'green'` (ready)

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