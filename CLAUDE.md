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

**Execution Cycle**:
1. `dm_run_flow_now` → Creates job via `dm_create` action
2. `dm_execute_step` → Functional step execution with job_id and flow_step_id
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
- **Publish**: Bluesky, Twitter, Threads, Facebook, Google Sheets, WordPress
- **AI**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Admin-Only**: Site-level auth, `manage_options` checks, zero user dependencies

## AI Integration

**Tool-First Architecture**: AI execution prioritizes agentic tool calling over traditional request/response patterns

**Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter (200+ models)

**Usage**:
```php
$result = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'model' => 'gpt-4'
], 'openrouter');
```

**Tool Integration**: AI models automatically discover and execute handler capabilities via `apply_filters('ai_tools', [])`

**Discovery**: `apply_filters('ai_providers', [])`, `apply_filters('ai_models', $provider, $config)`

## Agentic Tool Calling

**Pure Tool Pattern**: All current publish handlers use ONLY `handle_tool_call()` method. Legacy `handle_publish()` fallback exists only for future handlers without tool support

**Dynamic Discovery**: AI models discover and use handler capabilities automatically via `apply_filters('ai_tools', [])`

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
            'content' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => false]
        ]
    ];
    
    // Store configuration for execution
    if (!empty($handler_config)) {
        $tool['handler_config'] = $handler_config;
    }
    
    // Add conditional parameters based on user configuration
    if ($handler_config['twitter_include_source'] ?? true) {
        $tool['parameters']['source_url'] = ['type' => 'string', 'required' => false];
    }
    if ($handler_config['twitter_enable_images'] ?? true) {
        $tool['parameters']['image_url'] = ['type' => 'string', 'required' => false];
    }
    
    return $tool;
}
```

**Handler Implementation**:
```php
class Twitter {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Get handler configuration from tool definition
        $handler_config = $tool_def['handler_config'] ?? [];
        
        // Extract parameters
        $content = $parameters['content'] ?? '';
        $title = $parameters['title'] ?? '';
        $source_url = $parameters['source_url'] ?? null;
        $image_url = $parameters['image_url'] ?? null;
        
        // Use configuration to control behavior
        $include_source = $handler_config['twitter_include_source'] ?? true;
        $enable_images = $handler_config['twitter_enable_images'] ?? true;
        
        // Execute publishing logic with hardcoded character limits
        // Twitter: 280, Bluesky: 300, Threads: 500
        
        return [
            'success' => true,
            'data' => ['tweet_id' => $id, 'tweet_url' => $url],
            'tool_name' => 'twitter_publish'
        ];
    }
}
```

**Tool Execution**: Direct `handle_tool_call()` method execution with automatic tool discovery. PublishStep uses tool-first execution path for all current handlers

**Context-Aware**: Next step handlers automatically register tools for AI step discovery

**Configuration Integration**: Handler settings passed through tool definitions for dynamic parameter generation

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
| Files | None | Local/remote file processing |
| RSS | None | Feed parsing, deduplication |
| Reddit | OAuth2 | Subreddit posts, comments |
| Google Sheets | OAuth2 | Spreadsheet data extraction |
| WordPress | None | Post/page content retrieval |

| **Publish** | **Auth** | **Features** |
|-------------|----------|--------------|
| Bluesky | App Password | Text posts, media upload |
| Twitter | OAuth 1.0a | Tweets, media, URL replies |
| Threads | OAuth2 | Text posts, media |
| Facebook | OAuth2 | Page posts, media, URL comments |
| Google Sheets | OAuth2 | Row insertion, data logging |
| WordPress | None | Post creation, taxonomy assignment, content publishing |

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

```php
class MyStep {
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        // Job ID provided as explicit parameter
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

> **Important**: AI steps only discover tools for the immediate next step. Multiple consecutive publish steps will execute without AI guidance. The system will show yellow warning status for publish steps that follow other publish steps.

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
    $result = $this->process_data($data);
    return $result;
} catch (Exception $e) {
    do_action('dm_log', 'error', $e->getMessage(), ['flow_step_id' => $flow_step_id]);
    return $data; // Return original on failure
}
```

**Explicit Job Context**: Job ID passed as first parameter to all step execute() methods

**Job Status**: `completed`, `failed`, `completed_no_items`

## OAuth Integration

**Centralized Architecture**: `/inc/engine/filters/OAuth.php` provides unified OAuth operations and public URL rewrite system

**Central Operations**: Unified `dm_oauth` filter for all handlers

```php
// Account Management
$account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);
apply_filters('dm_oauth', false, 'clear', 'twitter');

// Configuration Management
$config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
apply_filters('dm_oauth', null, 'store_config', 'twitter', $config_data);
apply_filters('dm_oauth', false, 'clear_config', 'twitter');
apply_filters('dm_oauth', false, 'clear_all', 'twitter');

// URL Generation
$callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');
$auth_url = apply_filters('dm_get_oauth_auth_url', '', 'twitter');
```

**Public URL System**: `/dm-oauth/{provider}/` rewrite rules enable external API callbacks without exposing wp-admin URLs

**Handler Requirements**:
- **Reddit**: OAuth2 (read access)
- **Twitter**: OAuth 1.0a (read/write)
- **Facebook**: OAuth2 (pages_manage_posts)
- **Threads**: OAuth2 (threads_basic, threads_content_publish)
- **Google Sheets**: OAuth2 (spreadsheets scope)
- **Bluesky**: App Password (username/password)

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