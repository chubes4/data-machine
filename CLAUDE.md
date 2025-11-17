# CLAUDE.md

Data Machine: AI-first WordPress plugin with Pipeline+Flow architecture and multi-provider AI integration.

**Version**: 0.2.0

*For user documentation, see `docs/README.md` | For GitHub overview, see `README.md`*

## Architecture

**Engine**: Three-action execution cycle: `datamachine_run_flow_now` → `datamachine_execute_step` → `datamachine_schedule_next_step`

**Job Status**: `completed`, `failed`, `completed_no_items`

**Self-Registration**: Components auto-register via `*Filters.php` files loaded through composer.json:
```php
function datamachine_register_twitter_filters() {
    add_filter('datamachine_handlers', function($handlers) {
        $handlers['twitter'] = [
            'type' => 'publish',
            'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
            'label' => __('Twitter', 'data-machine'),
            'description' => __('Post content to Twitter with media support', 'data-machine')
        ];
        return $handlers;
    });
    add_filter('datamachine_auth_providers', function($providers) {
        $providers['twitter'] = new TwitterAuth();
        return $providers;
    });
    add_filter('chubes_ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
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
datamachine_register_twitter_filters(); // Auto-execute at file load
```

**Core Components**:
- **Pipeline+Flow**: Templates → instances pattern
- **Database**: 5 core tables (pipelines, flows, jobs, processed_items, chat_sessions)
- **Handlers**: See Handler Matrix section
- **AutoSave**: Complete pipeline persistence
- **Admin**: `manage_options` security model
- **OAuth Handlers**: Centralized OAuth 1.0a and OAuth 2.0 flow implementations (`/inc/Core/OAuth/`)

**Filter-Based Architecture**: All functionality accessed via WordPress filters for service discovery, configuration, and cross-cutting concerns.

## Database Schema

**Core Tables**:

```sql
-- Pipeline templates (reusable)
wp_datamachine_pipelines: pipeline_id, pipeline_name, pipeline_config, created_at, updated_at

-- Flow instances (scheduled + configured)
wp_datamachine_flows: flow_id, pipeline_id, flow_name, flow_config, scheduling_config

-- Job executions
wp_datamachine_jobs: job_id, flow_id, pipeline_id, status, job_data_json, engine_data, started_at, completed_at, error_message

-- Deduplication tracking
wp_datamachine_processed_items: item_id, flow_step_id, source_type, item_id, job_id, processed_at

-- Chat sessions (conversation persistence)
wp_datamachine_chat_sessions: session_id, user_id, messages, metadata, provider, model, created_at, updated_at, expires_at
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

**Dual-Layer Persistence**: Pipeline-level system prompts (templates) + flow-level user messages (instances)

**AI Directive System**: Filter-based architecture with directive categories applied by `RequestBuilder`:
- `datamachine_global_directives` - Applied to all AI agents (GlobalSystemPromptDirective, SiteContextDirective)
- `datamachine_agent_directives` - Agent-specific directives (PipelineCoreDirective, ChatAgentDirective, PipelineSystemPromptDirective)

**Universal Engine Architecture**: Shared AI infrastructure serving both Pipeline and Chat agents with centralized components in `/inc/Engine/AI/`:
- **AIConversationLoop** - Multi-turn conversation execution with automatic tool calling
- **ToolExecutor** - Universal tool discovery and execution infrastructure
- **ToolParameters** - Centralized parameter building for AI tools
- **ConversationManager** - Message formatting and conversation utilities
- **RequestBuilder** - Centralized AI request construction with directive application
- **ToolResultFinder** - Universal tool result search utility for data packet interpretation

**Tool Categories**:
- **Handler Tools**: Step-specific, registered via `chubes_ai_tools` filter
- **Global Tools**: Universal, registered via `datamachine_global_tools` filter, located in `/inc/Engine/AI/Tools/` (GoogleSearch, LocalSearch, WebFetch, WordPressPostReader)
- **Chat Tools**: Chat-only, registered via `datamachine_chat_tools` filter (MakeAPIRequest)

## Chat API

**Endpoint**: `POST /datamachine/v1/chat` - conversational AI for workflow building

**Implementation**: Directory-based structure at `/inc/Api/Chat/` (@since v0.2.0):
- `Chat.php` - Main endpoint handler (`POST /datamachine/v1/chat`, namespace `DataMachine\Api\Chat`)
- `ChatAgentDirective.php` - Chat-specific AI directive implementing filter-based directive registration
- `ChatFilters.php` - Self-registration filters loaded via composer.json for tool enablement (`datamachine_tool_enabled`) and directive application (`datamachine_agent_directives`)
- `Tools/MakeAPIRequest.php` - Chat-only tool for REST API operations (registered via `datamachine_chat_tools` filter)

**Session Management**: Persistent sessions via `wp_datamachine_chat_sessions` table with user isolation, 24-hour expiration

**Database Component**: `ChatDatabase` class at `/inc/Core/Database/Chat/Chat.php` handles CRUD operations for chat sessions

**Key Integration**: Uses Universal Engine architecture (AIConversationLoop, RequestBuilder, ToolExecutor, ToolParameters, ConversationManager) - see `/docs/api-reference/rest-api.md` for complete API documentation

## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking, timeframe filtering, keyword search |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching, timeframe filtering, keyword search |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress Local | None | Local post/page content retrieval, specific post ID targeting, taxonomy filtering, timeframe filtering, keyword search |
| WordPress Media | None | Media library attachments with post content integration, file URLs, metadata handling, parent post content inclusion, clean content generation, timeframe filtering, keyword search |
| WordPress API | None | External WordPress sites via REST API, structured data access, modern RSS alternative, timeframe filtering, keyword search |

| **Publish** | **Auth** | **Limit** | **Features** |
|-------------|----------|-----------|--------------|
| Twitter | OAuth 1.0a | 280 chars | URL replies, media upload, t.co link handling |
| Bluesky | App Password | 300 chars | Media upload, AT Protocol integration |
| Threads | OAuth2 | 500 chars | Media upload |
| Facebook | OAuth2 | No limit | Comment mode, link handling |
| WordPress | Required Config | No limit | Modular post creation with `FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler`, Gutenberg blocks, configuration hierarchy (system defaults override handler config) |
| Google Sheets | OAuth2 | No limit | Row insertion |

| **Update** | **Auth** | **Features** |
|------------|----------|---------------|
| WordPress Update | None | Existing post/page modification, taxonomy updates, requires source_url |
| *Extensible* | *Varies* | *Custom update handlers via extensions* |

| **Global Tools** | **Auth** | **Features** |
|-------------------|----------|--------------|
| Google Search | API Key + Search Engine ID | Web search, site restriction, 1-10 results |
| Local Search | None | WordPress search, 1-20 results |
| WebFetch | None | Web page content retrieval, 50K character limit, HTML processing |
| WordPress Post Reader | None | Single WordPress post content retrieval by URL, full post analysis |

## Data Flow Architecture

**Clean Data Separation**: AI agents receive clean data packets while handlers access engine parameters via database storage.

**Unified Step Payload**: Engine builds a single payload array and hands it to every step:
```php
$payload = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'data' => $data,  // Data packet array
    'flow_step_config' => $flow_step_config,
    'engine_data' => apply_filters('datamachine_engine_data', [], $job_id)
];
```

**Data Packet Format** (AI-visible):
```php
[
    'data' => [
        'content_string' => $content,  // Clean content without URLs
        'file_info' => $file_info      // File metadata when applicable
    ],
    'metadata' => [
        'source_type' => $type,
        'item_identifier_to_log' => $id,
        'original_id' => $id,
        'original_title' => $title,
        'original_date_gmt' => $date
        // No URLs in metadata - kept separate
    ]
]
```

**Engine Data Storage**: Fetch handlers store specific parameters in database for downstream handlers:
```php
// Store engine parameters via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}

// Retrieve engine data
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

**Handler-Specific Engine Parameters**:
- **Reddit**: `source_url` (Reddit post URL), `image_url` (stored image URL)
- **WordPress Local**: `source_url` (permalink), `image_url` (featured image URL)
- **WordPress API**: `source_url` (post link), `image_url` (featured image URL)
- **WordPress Media**: `source_url` (parent post permalink when include_parent_content enabled), `image_url` (media URL)
- **RSS**: `source_url` (item link), `image_url` (enclosure URL)
- **Google Sheets**: `source_url` (empty), `image_url` (empty)
- **Files**: `image_url` (public URL for images only)

**Processing Flow**: Each step adds entry to array front → accumulates workflow history
**Access Pattern**: Clean data for AI, structured engine parameters for handlers

## WordPress Publish Handler Architecture

**Modular Components**: Specialized processing modules with configuration hierarchy (system defaults override handler config):

### Core Components
- **FeaturedImageHandler**: Image processing, validation, media library integration
- **TaxonomyHandler**: Three selection modes (skip, AI-decided, pre-selected), dynamic term creation
- **SourceUrlHandler**: URL attribution with Gutenberg block generation

**Integration Pattern**:
```php
class WordPress {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $post_id = $this->create_wordpress_post($content, $handler_config);
        // Process: image → taxonomies → source URL
        return ['success' => true, 'data' => ['id' => $post_id, 'url' => $url]];
    }
}
```

## AutoSave System

**Complete Pipeline Persistence**: Single action handles all pipeline data, flows, configurations, and cache invalidation:

```php
do_action('datamachine_auto_save', $pipeline_id);
```

## Step Configuration Persistence

**Dual-Layer Architecture**:
- **Pipeline Level**: System prompts (templates) shared across flow instances
- **Flow Level**: User messages (instance-specific) per flow

**Access Pattern**:
```php
$flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
$pipeline_config = apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);
$flow_config = apply_filters('datamachine_get_flow_config', [], $flow_id);
```

**Flow Handler Structure** (Flat):
```php
// Flow step configuration structure (flat, simplified)
$flow_config[$flow_step_id] = [
    'flow_step_id' => 'step_uuid_flow_123',
    'pipeline_step_id' => 'step_uuid',
    'step_type' => 'publish',
    'execution_order' => 2,
    'handler_slug' => 'twitter',           // Handler identifier
    'handler_config' => [                  // Handler settings (flat)
        'setting1' => 'value1',
        'setting2' => 'value2'
    ],
    'enabled' => true
];

// Access handler configuration
$handler_slug = $flow_step_config['handler_slug'];
$handler_settings = $flow_step_config['handler_config'];
```

**Migration Note**: Flow handler structure is flat in current implementation (v0.2.0) using `handler_slug + handler_config` pattern.

## Admin Interface

**Pages**: Pipelines (React), Jobs, Logs, Settings
**Architecture**: React 18 with WordPress Components (zero jQuery/AJAX)
**Features**: Drag & drop, silent auto-save, status indicators, modal configuration
**OAuth**: `/datamachine-auth/{provider}/` URLs with popup flow

**Universal Handler Settings Template**: Single template handles all handler types with dynamic field rendering and auth integration.

**Handler Metadata Pattern**: Auth-enabled handlers include `'requires_auth' => true` flag for performance optimization.

## Settings

**Controls**: Engine Mode (headless - disables admin pages only), admin page toggles, tool toggles, site context toggle, global system prompt, job data cleanup on failure
**WordPress Defaults**: Site-wide post type, taxonomy, author, status defaults
**Tool Configuration**: Modal setup for API keys and service configurations
**Site Context**: Automatic WordPress context injection (enabled by default)
**Job Data Cleanup**: Clean up job data files on failure (enabled by default, disable for debugging failed jobs)

```php
// Direct function access (internal system components)
$settings = datamachine_get_data_machine_settings();
$enabled_pages = datamachine_get_enabled_admin_pages();
$enabled_tools = datamachine_get_enabled_general_tools();

// Filter-based configuration handling
apply_filters('datamachine_enabled_settings', $fields, $handler_slug, $step_type, $context);
apply_filters('datamachine_apply_global_defaults', $current_settings, $handler_slug, $step_type);
```





## Step Implementation

**Standard Implementation Patterns**:

```php
// Step Pattern
class MyStep {
    public function execute(array $payload): array {
        $job_id = $payload['job_id'];
        $flow_step_id = $payload['flow_step_id'];
        $data = $payload['data'] ?? [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        $engine_data = $payload['engine_data'] ?? [];

        do_action('datamachine_mark_item_processed', $flow_step_id, 'my_step', $item_id, $job_id);

        array_unshift($data, [
            'type' => 'my_step',
            'content' => ['title' => $title, 'body' => $content],
            'metadata' => ['source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'],
            'timestamp' => time()
        ]);
        return $data;
    }
}

// Fetch Handler with Database Storage
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        // Extract flow_step_id from handler config
        $flow_step_id = $handler_config['flow_step_id'] ?? null;

        // Mark as processed for deduplication
        if ($flow_step_id) {
            do_action('datamachine_mark_item_processed', $flow_step_id, 'my_handler', $item_id, $job_id);
        }

        // Create clean data packet (no URLs)
        $clean_data = [
            'data' => [
                'content_string' => $content_string,
                'file_info' => null
            ],
            'metadata' => [
                'source_type' => 'my_handler',
                'item_identifier_to_log' => $item_id,
                'original_id' => $item_id
            ]
        ];

        // Store engine parameters in database via centralized datamachine_engine_data filter
        if ($job_id) {
            apply_filters('datamachine_engine_data', null, $job_id, [
                'source_url' => $source_url,
                'image_url' => $image_url
            ]);
        }

        return ['processed_items' => [$clean_data]];
    }
}

// Publish/Update Handler with Engine Data Access
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_config = $tool_def['handler_config'] ?? [];

        // Access engine data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        // Access source_url from engine data for link attribution (publish handlers) or post identification (update handlers)
        return ['success' => true, 'data' => ['id' => $id, 'url' => $url]];
    }
}
```



## Logging & Debugging

**Centralized Logging**: All components use `datamachine_log` action for consistent debug output

```php
// Standard logging format
do_action('datamachine_log', $level, $message, $context_array);

// Examples from codebase
do_action('datamachine_log', 'debug', 'AI Step Directive: Injected system directive', [
    'tool_count' => count($tools),
    'available_tools' => array_keys($tools),
    'directive_length' => strlen($directive)
]);

do_action('datamachine_log', 'debug', 'AIStepTools: Generated HTML attributes', [
    'pipeline_step_id' => $pipeline_step_id,
    'tool_id' => $tool_id,
    'checked_attr_output' => $checked_attr,
    'disabled_attr_output' => $disabled_attr
]);
```

**Log Levels**: `debug`, `info`, `warning`, `error`
**Key Points**: AI processing, tool validation, pipeline execution, OAuth, item processing

## Usage Examples

**Single Platform (Recommended)**:
```php
$pipeline_id = apply_filters('datamachine_create_pipeline', null, ['pipeline_name' => 'RSS to Twitter']);
apply_filters('datamachine_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
apply_filters('datamachine_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
apply_filters('datamachine_create_step', null, ['step_type' => 'publish', 'pipeline_id' => $pipeline_id]);
$flow_id = apply_filters('datamachine_create_flow', null, ['pipeline_id' => $pipeline_id]);
do_action('datamachine_run_flow_now', $flow_id, 'manual');
```

**Multi-Platform (AI→Publish→AI→Publish pattern)**:
```php
// Fetch → AI → Publish → AI → Publish
$pipeline_id = apply_filters('datamachine_create_pipeline', null, ['pipeline_name' => 'Multi-Platform']);
// Add steps: fetch, ai (twitter), publish (twitter), ai (facebook), publish (facebook)
```

**Content Update (Fetch→AI→Update pattern)**:
```php
// WordPress content enhancement workflow
$pipeline_id = apply_filters('datamachine_create_pipeline', null, ['pipeline_name' => 'Content Enhancement']);
apply_filters('datamachine_create_step', null, ['step_type' => 'fetch', 'pipeline_id' => $pipeline_id]);
apply_filters('datamachine_create_step', null, ['step_type' => 'ai', 'pipeline_id' => $pipeline_id]);
apply_filters('datamachine_create_step', null, ['step_type' => 'update', 'pipeline_id' => $pipeline_id]);
```

> **Critical**: Update steps require `source_url` from engine data to identify target content. All fetch handlers store this data in database via centralized `datamachine_engine_data` filter for later retrieval. AI agents discover handler tools for immediate next step only.



## Cache System

**Centralized Cache Management**: Actions/Cache.php provides WordPress action-based cache clearing system for comprehensive cache management throughout the codebase.

**Cache Actions**:
```php
// Granular cache clearing
do_action('datamachine_clear_pipeline_cache', $pipeline_id); // Pipeline + flows + jobs
do_action('datamachine_clear_flow_cache', $flow_id); // Flow-specific caches
do_action('datamachine_clear_flow_config_cache', $flow_id); // Flow configuration cache
do_action('datamachine_clear_flow_scheduling_cache', $flow_id); // Flow scheduling cache
do_action('datamachine_clear_flow_steps_cache', $flow_id); // Flow steps cache
do_action('datamachine_clear_jobs_cache'); // All job-related caches
do_action('datamachine_clear_all_cache'); // Complete cache reset

// Standardized cache storage
do_action('datamachine_cache_set', $key, $data, $timeout, $group);
```

**Cache Architecture**: Pipeline/Flow/Job caches with pattern-based clearing, WordPress transients, action-based database component integration

**Key Features**: Centralized cache key constants, granular invalidation with targeted methods (`datamachine_clear_flow_config_cache`, `datamachine_clear_flow_scheduling_cache`, `datamachine_clear_flow_steps_cache`), comprehensive logging, and extensible action-based architecture for database components





## OAuth Integration

**Centralized OAuth Handlers**: Unified OAuth flow implementations eliminating code duplication across all providers

**Service Discovery**:
```php
// OAuth 1.0a Handler (Twitter)
$oauth1 = apply_filters('datamachine_get_oauth1_handler', null);
$request_token = $oauth1->get_request_token($url, $key, $secret, $callback, 'twitter');
$auth_url = $oauth1->get_authorization_url($authorize_url, $oauth_token, 'twitter');
$result = $oauth1->handle_callback('twitter', $access_url, $key, $secret, $account_fn);

// OAuth 2.0 Handler (Reddit, Facebook, Threads, Google Sheets)
$oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
$state = $oauth2->create_state('provider_key');
$auth_url = $oauth2->get_authorization_url($base_url, $params);
$result = $oauth2->handle_callback($provider_key, $token_url, $token_params, $account_fn);
```

**Account Operations**:
```php
// Account management
$account = apply_filters('datamachine_retrieve_oauth_account', [], 'twitter');
apply_filters('datamachine_store_oauth_account', $account_data, 'twitter');
apply_filters('datamachine_clear_oauth_account', false, 'twitter');

// Configuration with validation
$is_configured = apply_filters('datamachine_tool_configured', false, 'twitter');
$provider_url = apply_filters('datamachine_oauth_callback', '', 'twitter');

// Provider discovery
$providers = apply_filters('datamachine_auth_providers', []);
```

**Configuration Validation**: Auto-validated via `datamachine_tool_configured` filter, UI disabled if unconfigured

**URLs**: `/datamachine-auth/{provider}/` with `manage_options` security - all OAuth operations require admin capabilities
**Storage**: Dual-path authentication storage based on auth type detection
- **OAuth providers** (Reddit, Twitter, Facebook, Threads, Google Sheets): Save to `oauth_keys` (API configuration)
- **Simple auth providers** (Bluesky): Save to `oauth_account` (final credentials)
**Validation**: Real-time configuration checks prevent unconfigured tools from being enabled

**Requirements**:
- **Reddit**: OAuth2 (client_id/client_secret)
- **Twitter**: OAuth 1.0a (consumer_key/consumer_secret)
- **Facebook**: OAuth2 (app_id/app_secret)
- **Threads**: OAuth2 (same as Facebook)
- **Google Sheets**: OAuth2 (client_id/client_secret)
- **Bluesky**: App Password (username/app_password)
- **Google Search**: API Key + Custom Search Engine ID (not OAuth)

## Core Rules
- Engine agnostic (no hardcoded step types in `/inc/Engine/`)
- Filter-based service discovery only
- Security: `wp_unslash()` BEFORE `sanitize_text_field()`
- CSS namespace: `datamachine-` prefix (explicit clarity, no abbreviations)
- Auth: `manage_options` only
- Field naming: `pipeline_step_id` (UUID4)
