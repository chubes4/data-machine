# CLAUDE.md

Data Machine: AI-first WordPress plugin with Pipeline+Flow architecture and multi-provider AI integration.

**Version**: 0.1.2

*For user documentation, see `docs/README.md` | For GitHub overview, see `README.md`*

## Current Implementation Status

**Prefix Convention:**
- Current: `datamachine_` prefix used throughout codebase (filters, actions, functions)
- Migration: Completed transition from `dm_` to `datamachine_` prefix
- Status: Migration complete for all code components and database table names

**API Architecture:**
- REST API: 10 endpoint files implemented (Auth, Execute, Files, Flows, Jobs, Logs, Pipelines, ProcessedItems, Settings, Users)
- **Pipelines Page**: React-only architecture, zero jQuery/AJAX (complete migration, 2,223 lines jQuery removed, 6 PHP templates removed, all AJAX endpoints eliminated)
- **Jobs/Logs Pages**: REST API integration complete
- **Settings Page**: REST API migration complete
- Migration Status: Full REST API migration complete for all admin pages with React frontend modernization

## Migration Status (Complete)

**Phase 2 Migration Complete (100% complete):**
- ✅ Filter hooks (161 occurrences across 50 files) - all converted to `datamachine_*`
- ✅ Action hooks (100+ occurrences) - all converted to `datamachine_*`
- ✅ Core function names - all converted to `datamachine_*`
- ✅ Cache Keys (30+ constants) - all converted to `datamachine_*`
- ✅ WordPress Options (7+ option names) - all converted to `datamachine_*`
- ✅ Transients (10+ transient names) - all converted to `datamachine_*`
- ✅ Query Variables (OAuth routing) - all converted to `datamachine_*`
- ✅ CSS Classes - all converted to `datamachine-*`
- ✅ OAuth URL Routes - all converted to `/datamachine-auth/{provider}/`

**Phase 3 Database Migration (Complete):**
- `wp_datamachine_pipelines`, `wp_datamachine_flows`, `wp_datamachine_jobs`, `wp_datamachine_processed_items`
- Database table names updated to use `datamachine_` prefix
- Migration plugin available for existing installations

## Core Filters & Actions

```php
// Service Discovery
$handlers = apply_filters('datamachine_handlers', []);
$steps = apply_filters('datamachine_step_types', []);
$databases = apply_filters('datamachine_db', []);
$providers = apply_filters('datamachine_auth_providers', []);

// Pipeline & Flow Operations
$pipeline_id = apply_filters('datamachine_create_pipeline', null, $data); // Accepts simple or complete pipeline data with steps array
$step_id = apply_filters('datamachine_create_step', null, $data);
$flow_id = apply_filters('datamachine_create_flow', null, $data);
$flow_id = apply_filters('datamachine_duplicate_flow', null, $source_flow_id);
apply_filters('datamachine_get_pipelines', [], $pipeline_id);
apply_filters('datamachine_get_pipelines_list', []);
apply_filters('datamachine_get_flow_config', [], $flow_id);
apply_filters('datamachine_get_pipeline_flows', [], $pipeline_id);
apply_filters('datamachine_get_pipeline_steps', [], $pipeline_id);

// Step Configuration & Navigation
$flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
$pipeline_step_config = apply_filters('datamachine_get_pipeline_step_config', [], $pipeline_step_id);
$next_flow_step_id = apply_filters('datamachine_get_next_flow_step_id', null, $flow_step_id);
$prev_flow_step_id = apply_filters('datamachine_get_previous_flow_step_id', null, $flow_step_id);
$next_pipeline_step_id = apply_filters('datamachine_get_next_pipeline_step_id', null, $pipeline_step_id);
$prev_pipeline_step_id = apply_filters('datamachine_get_previous_pipeline_step_id', null, $pipeline_step_id);

// Step ID Management
$flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
$parts = apply_filters('datamachine_split_pipeline_step_id', null, $pipeline_step_id);
$parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);

// Engine Data Access
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);

// AI & Tools
$result = apply_filters('ai_request', $request, 'anthropic');
$tools = apply_filters('ai_tools', []);
$ai_config = apply_filters('datamachine_ai_config', [], $pipeline_step_id);
apply_filters('datamachine_tool_configured', false, $tool_id);
apply_filters('datamachine_get_tool_config', [], $tool_id);
apply_filters('datamachine_tool_success_message', $message, $tool_name, $result, $parameters);
apply_filters('datamachine_parse_ai_response', []);
do_action('datamachine_save_tool_config', $tool_id, $config_data);
do_action('ai_api_error', $error_data);

// OAuth & Authentication
apply_filters('datamachine_retrieve_oauth_account', [], 'handler');
apply_filters('datamachine_store_oauth_account', $data, 'handler');
apply_filters('datamachine_clear_oauth_account', false, 'handler');
apply_filters('datamachine_retrieve_oauth_keys', [], 'handler');
apply_filters('datamachine_store_oauth_keys', $data, 'handler');
apply_filters('datamachine_oauth_callback', '', 'provider');
apply_filters('datamachine_oauth_url', $auth_url, 'provider');

// Execution
do_action('datamachine_run_flow_now', $flow_id, $context);
do_action('datamachine_execute_step', $job_id, $flow_step_id, $data);
do_action('datamachine_schedule_next_step', $job_id, $flow_step_id, $data);

// Job Management
do_action('datamachine_update_job_status', $job_id, $status, $context);
do_action('datamachine_fail_job', $job_id, $reason, $context_data);

// Processing & Status
do_action('datamachine_mark_item_processed', $flow_step_id, $source_type, $item_id, $job_id);
apply_filters('datamachine_is_item_processed', false, $flow_step_id, $source_type, $item_id);
apply_filters('datamachine_detect_status', 'green', 'context', $data);
apply_filters('datamachine_get_handler_settings_display', [], $flow_step_id, $step_type); // Handler settings display with smart defaults (priority 5) and handler-specific customization (priority 10+)

// Data Processing
apply_filters('datamachine_data_packet', $data, $packet_data, $flow_step_id, $step_type);
apply_filters('datamachine_request', [], $method, $url, $args, $context);

// Centralized Handler Filters
apply_filters('datamachine_timeframe_limit', null, $timeframe_limit); // Shared timeframe parsing across fetch handlers
apply_filters('datamachine_keyword_search_match', true, $content, $search_term); // Universal keyword matching with OR logic

// WordPress Utilities (Engine/Filters/WordPress.php)
apply_filters('datamachine_wordpress_user_display_name', null, $user_id); // Convert user ID to display name
apply_filters('datamachine_wordpress_term_name', null, $term_id, $taxonomy); // Convert term ID to term name
apply_filters('datamachine_wordpress_system_taxonomies', []); // Get excluded system taxonomies list
apply_filters('datamachine_wordpress_public_taxonomies', [], $args); // Get public taxonomies (with exclusion)

// Delete Operations
do_action('datamachine_delete_pipeline', $pipeline_id);
do_action('datamachine_delete_flow', $flow_id);
do_action('datamachine_delete_step', $pipeline_step_id, $pipeline_id);
do_action('datamachine_delete_processed_items', $criteria); // Criteria array: ['job_id' => 123] or ['flow_id' => 456]
do_action('datamachine_delete_jobs', $clear_type, $cleanup_processed); // clear_type: 'all' or 'failed'
do_action('datamachine_delete_logs'); // Clear log file

// Update Operations
do_action('datamachine_update_flow_handler', $flow_step_id, $handler_slug, $settings);
do_action('datamachine_update_flow_schedule', $flow_id, $interval, $context);
do_action('datamachine_update_system_prompt', $pipeline_step_id, $system_prompt);
do_action('datamachine_update_flow_user_message', $flow_step_id, $user_message);
do_action('datamachine_sync_steps_to_flow', $flow_id, $step_data, $context);

// Template & UI System
apply_filters('datamachine_render_template', '', $template_name, $data);
apply_filters('datamachine_modals', []);
apply_filters('datamachine_admin_pages', []);
apply_filters('datamachine_admin_assets', [], $page_slug);
apply_filters('datamachine_pipeline_templates', []);

// Settings & Configuration
apply_filters('datamachine_handler_settings', [], $handler_slug); // Pass handler_slug to load only specific handler's settings
apply_filters('datamachine_step_settings', []);
apply_filters('datamachine_scheduler_intervals', []);
$settings = datamachine_get_data_machine_settings(); // Direct function access
$enabled_pages = datamachine_get_enabled_admin_pages(); // Direct function access
$enabled_tools = datamachine_get_enabled_general_tools(); // Direct function access

// Context Management
\DataMachine\ExecutionContext::$job_id; // Current job ID during execution
\DataMachine\ExecutionContext::$flow_step_id; // Current flow step ID during execution
\DataMachine\ExecutionContext::clear(); // Clear execution context
$context = SiteContext::get_context(); // Direct class access
SiteContext::clear_cache(); // Direct class access

// Import/Export
apply_filters('datamachine_importer', null);
apply_filters('datamachine_import_result', []);
apply_filters('datamachine_export_result', '');

// Files Repository
$files_repo = apply_filters('datamachine_files_repository', [])['files'] ?? null;

// System & Logging
do_action('datamachine_log', $level, $message, $context);
apply_filters('datamachine_log_file', null, $operation, $param);
do_action('datamachine_auto_save', $pipeline_id);
do_action('datamachine_cleanup_old_files'); // File repository maintenance via Action Scheduler

// Cache Management
do_action('datamachine_clear_pipeline_cache', $pipeline_id);
do_action('datamachine_clear_flow_cache', $flow_id);
do_action('datamachine_clear_flow_config_cache', $flow_id);
do_action('datamachine_clear_flow_scheduling_cache', $flow_id);
do_action('datamachine_clear_flow_steps_cache', $flow_id);
do_action('datamachine_clear_jobs_cache');
do_action('datamachine_clear_all_cache');
do_action('datamachine_clear_pipelines_list_cache');
do_action('datamachine_cache_set', $key, $data, $timeout, $group);

// Engine Data Storage & Retrieval
apply_filters('datamachine_engine_data', null, $job_id, [
    'source_url' => $source_url,
    'image_url' => $image_url
]); // Store engine data
$engine_data = apply_filters('datamachine_engine_data', [], $job_id); // Retrieve all engine data
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

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
datamachine_register_twitter_filters(); // Auto-execute at file load
```

**Core Components**:
- **Pipeline+Flow**: Templates → instances pattern
- **Database**: 4 core tables (see Database Schema)
- **Handlers**: See Handler Matrix section
- **AutoSave**: Complete pipeline persistence
- **Admin**: `manage_options` security model

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

### AI Request Priority System

**5-Tier AI Directive Priority System**: AI requests receive multiple system messages via auto-registering directive classes:

1. **Priority 10 - Plugin Core Directive** (`PluginCoreDirective`): Foundational AI agent identity establishing role as content processing agent, operational principles for systematic execution, workflow approach with termination logic, and data packet structure guidance
2. **Priority 20 - Global System Prompt** (`GlobalSystemPromptDirective`): User-configured foundational AI behavior
3. **Priority 30 - Pipeline System Prompt** (`PipelineSystemPromptDirective`): Pipeline instructions and workflow visualization
4. **Priority 40 - Tool Definitions** (`ToolDefinitionsDirective`): Dynamic tool prompts and workflow context
5. **Priority 50 - WordPress Site Context** (`SiteContextDirective`): WordPress environment info (toggleable)

```php
// Auto-registering directive classes with standardized priority spacing
add_filter('ai_request', [PluginCoreDirective::class, 'inject'], 10, 5);
add_filter('ai_request', [GlobalSystemPromptDirective::class, 'inject'], 20, 5);
add_filter('ai_request', [PipelineSystemPromptDirective::class, 'inject'], 30, 5);
add_filter('ai_request', [ToolDefinitionsDirective::class, 'inject'], 40, 5);
add_filter('ai_request', [SiteContextDirective::class, 'inject'], 50, 5);
```

**Site Context Integration**: WordPress metadata, post types, taxonomies, cached with auto-invalidation

**AI Conversation State Management**:
- **AIStepConversationManager**: Turn-based conversation loops with chronological ordering and temporal context
- **State Preservation**: Complete conversation history with turn tracking and duplicate detection
- **Tool Integration**: Tool calls recorded before execution with turn-numbered messages and result formatting
- **Data Synchronization**: Dynamic data packet updates via `updateDataPacketMessages()` with JSON synchronization
- **Conversation Validation**: Duplicate tool call detection with parameter comparison and corrective messaging

```php
// Conversation Management Methods with Turn Tracking
AIStepConversationManager::formatToolCallMessage($tool_name, $tool_parameters, $turn_count);
AIStepConversationManager::formatToolResultMessage($tool_name, $tool_result, $tool_parameters, $is_handler_tool, $turn_count);
AIStepConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);
AIStepConversationManager::updateDataPacketMessages($conversation_messages, $data);
AIStepConversationManager::buildConversationMessage($role, $content);
AIStepConversationManager::generateFailureMessage($tool_name, $error_message);
AIStepConversationManager::validateToolCall($tool_name, $tool_parameters, $conversation_messages);
AIStepConversationManager::extractToolCallFromMessage($message);
AIStepConversationManager::generateDuplicateToolCallMessage($tool_name);
```

**Flow Architecture**: System directives → User data → AI responses → Tool calls → Tool results (chronological)

**AI Step Execution**: Standalone execution with flow-level user messages and multi-turn support

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
$tool_configured = apply_filters('datamachine_tool_configured', false, $tool_id);
```

**Tool Categories**:
- **Handler Tools**: Step-specific (twitter_publish, wordpress_update) - available when next step matches handler type
- **General Tools**: Universal (Google Search, Local Search, WebFetch, WordPress Post Reader) - available to all AI agents

**Enhanced Tool Discovery**: UpdateStep and PublishStep implement intelligent tool result detection with exact handler matching and partial name matching for flexible tool discovery:

```php
// UpdateStep/PublishStep tool result detection with flexible matching
private function find_tool_result_for_handler(array $data, string $handler): ?array {
    foreach ($data as $entry) {
        if (($entry['type'] ?? '') === 'tool_result') {
            $tool_name = $entry['metadata']['tool_name'] ?? '';
            $tool_handler = $entry['metadata']['tool_handler'] ?? '';

            // Exact handler match (primary method)
            if ($tool_handler === $handler) {
                return $entry;
            }

            // Partial name matching for tool discovery (fallback method)
            if (strpos($tool_name, $handler) !== false || strpos($handler, $tool_name) !== false) {
                return $entry;
            }
        }
    }
    return null;
}
```

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

**General Tools**: Google Search, Local Search, WebFetch, WordPress Post Reader (no 'handler' property = universal)

### Tool Implementation

**Handler Tool Implementation**:
```php
class Twitter {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        if (empty($parameters['content'])) {
            return ['success' => false, 'error' => 'Missing content', 'tool_name' => 'twitter_publish'];
        }

        $handler_config = $tool_def['handler_config'] ?? [];

        // Access engine data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        $connection = $this->auth->get_connection();
        if (is_wp_error($connection)) {
            return ['success' => false, 'error' => $connection->get_error_message()];
        }

        // Format and publish logic using engine data
        return ['success' => true, 'data' => ['tweet_id' => $id, 'url' => $url]];
    }
}
```

### Tool Configuration

**AIStepToolParameters**: Centralized flat parameter building with `buildParameters()` and `buildForHandlerTool()` methods for unified tool execution
**Configuration**: `datamachine_tool_configured`, `datamachine_get_tool_config`, `datamachine_save_tool_config` filters


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

| **General Tools** | **Auth** | **Features** |
|-------------------|----------|--------------|
| Google Search | API Key + Search Engine ID | Web search, site restriction, 1-10 results |
| Local Search | None | WordPress search, 1-20 results |
| WebFetch | None | Web page content retrieval, 50K character limit, HTML processing |
| WordPress Post Reader | None | Single WordPress post content retrieval by URL, full post analysis |

## DataPacket Structure & Engine Data

**Clean Data Separation**: AI agents receive clean data packets while handlers access engine parameters via database storage:

```php
// Clean data packet format (AI-visible)
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

// Engine parameters stored in database by fetch handlers via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}

// Return structure from fetch handlers (engine parameters stored separately in database)
return [
    'processed_items' => [$clean_data]
];
```

**Processing**: Each step adds entry to array front → accumulates workflow history
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

**Migration Note**: Flow handler structure is flat in current implementation (v0.1.2) using `handler_slug + handler_config` pattern.

## Admin Interface

**Pages**: Pipelines (React), Jobs, Logs
**Settings**: WordPress Settings → Data Machine
**Pipelines Architecture**: React 18 with WordPress Components (zero jQuery/AJAX)
**Features**: Drag & drop, silent auto-save, status indicators, modal configuration
**OAuth**: `/datamachine-auth/{provider}/` URLs with popup flow

### Universal Handler Settings Template System

**Template Path**: `inc/Core/Admin/Pages/Pipelines/templates/modal/handler-settings.php`

**Unified Configuration**: Single template handles all handler types, eliminating code duplication across individual handler templates.

```php
// Universal template approach
$handler_settings = apply_filters('datamachine_handler_settings', [], $handler_slug)[$handler_slug] ?? null;
if ($handler_settings && method_exists($handler_settings, 'get_fields')) {
    $settings_fields = apply_filters('datamachine_enabled_settings',
        $handler_settings::get_fields($current_settings),
        $handler_slug, $step_type, $context
    );
}
```

**Template Features**: Dynamic field rendering, auth integration, global settings notification, validation integration

### Performance Optimizations

**Handler Settings Modal Load** (50% query reduction):
- Single `datamachine_get_flow_step_config` query (eliminated duplicate at line 100)
- Direct metadata check via `$handler_info['requires_auth']` instead of auth provider instantiation
- Zero auth object overhead during modal load

**Handler Settings Save** (React implementation):
- Step config built from memory instead of database query
- Direct REST API calls with optimized payload structure
- Single targeted flow query for execution_order only

**Handler Metadata Pattern**:
All auth-enabled handlers include `'requires_auth' => true` flag:
```php
add_filter('datamachine_handlers', function($handlers, $step_type = null) {
    $handlers['twitter'] = [
        'type' => 'publish',
        'class' => Twitter::class,
        'label' => __('Twitter', 'data-machine'),
        'description' => __('Post content to Twitter with media support', 'data-machine'),
        'requires_auth' => true  // Metadata flag eliminates provider instantiation
    ];
    return $handlers;
}, 10, 2);
```

**Auth-Enabled Handlers**: Twitter, Bluesky, Facebook, Threads, Google Sheets (publish & fetch), Reddit (fetch)

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

## Parameter Systems

**Hybrid Database + Filter Injection Architecture**:

### 1. Core Parameters (Always Static)
Core parameters are always the same and passed to ALL steps:
```php
$core_parameters = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'data' => $data,  // Data packet array
    'flow_step_config' => $flow_step_config  // Step configuration
];
```

### 2. Engine Data Filter-Based Access
Fetch handlers store `source_url`, `image_url` in database; steps retrieve engine data via centralized `datamachine_engine_data` filter for unified access



## Step Implementation

**Standard Implementation Patterns**:

```php
// Step Pattern
class MyStep {
    public function execute(array $parameters): array {
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];

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

## System Integration

**Import/Export**:
```php
do_action('datamachine_import', 'pipelines', $csv_data);
do_action('datamachine_export', 'pipelines', [$pipeline_id]);
```

**REST API**:

All REST API endpoints are implemented in `inc/Api/` directory with automatic registration via `rest_api_init`.

**Execute Endpoint** (`POST /datamachine/v1/execute`):

Unified execution endpoint supporting both database flows and ephemeral workflows.

```php
// Implementation: inc/Api/Execute.php

// DATABASE FLOW - IMMEDIATE
POST /datamachine/v1/execute
{"flow_id": 123}

// DATABASE FLOW - DELAYED (one-time)
POST /datamachine/v1/execute
{"flow_id": 123, "timestamp": 1704153600}

// DATABASE FLOW - RECURRING
POST /datamachine/v1/execute
{"flow_id": 123, "interval": "hourly"}

// EPHEMERAL WORKFLOW - IMMEDIATE
POST /datamachine/v1/execute
{
  "workflow": {
    "steps": [
      {"type": "fetch", "handler": "rss", "config": {"feed_url": "https://..."}},
      {"type": "ai", "provider": "anthropic", "model": "claude-sonnet-4", "system_prompt": "Summarize"},
      {"type": "publish", "handler": "twitter", "config": {...}}
    ]
  }
}

// EPHEMERAL WORKFLOW - DELAYED (one-time only, no recurring)
POST /datamachine/v1/execute
{
  "workflow": {...},
  "timestamp": 1704153600
}

// Authentication: manage_options capability via WordPress application password or cookie
// See docs/api-reference/rest-api.md for complete documentation
```

**Complete REST Endpoint Catalog**:

*Flow Management:*
- `POST /datamachine/v1/flows` - Create flows
- `DELETE /datamachine/v1/flows/{id}` - Delete flows
- `POST /datamachine/v1/flows/{id}/duplicate` - Duplicate flows
- `GET /datamachine/v1/flows/{id}/config` - Flow configuration
- `GET /datamachine/v1/flows/steps/{flow_step_id}/config` - Flow step configuration

*Pipeline Management:*
- `GET /datamachine/v1/pipelines` - List pipelines
- `POST /datamachine/v1/pipelines` - Create pipelines
- `DELETE /datamachine/v1/pipelines/{id}` - Delete pipelines
- `POST /datamachine/v1/pipelines/{id}/steps` - Add pipeline steps
- `DELETE /datamachine/v1/pipelines/{id}/steps/{step_id}` - Remove pipeline steps
- `PUT /datamachine/v1/pipelines/{id}/steps/reorder` - Reorder pipeline steps
- `GET /datamachine/v1/pipelines/{id}/flows` - Get flows for pipeline

*Files & Storage:*
- `POST /datamachine/v1/files` - Upload files
- `GET /datamachine/v1/files` - List uploaded files
- `DELETE /datamachine/v1/files/{filename}` - Delete files

*User Management:*
- `GET /datamachine/v1/users/{id}` - User preferences
- `POST /datamachine/v1/users/{id}` - Update user preferences
- `GET /datamachine/v1/users/me` - Current user
- `POST /datamachine/v1/users/me` - Update current user

*System & Monitoring:*
- `GET /datamachine/v1/status` - Flow/pipeline status (query params: flow_id[], pipeline_id[])
- `GET /datamachine/v1/logs` - Retrieve logs
- `GET /datamachine/v1/logs/content` - Log file content
- `PUT /datamachine/v1/logs/level` - Set log level
- `DELETE /datamachine/v1/logs` - Clear logs
- `GET /datamachine/v1/jobs` - Job history
- `DELETE /datamachine/v1/jobs` - Clear jobs
- `GET /datamachine/v1/processed-items` - Processed items tracking
- `DELETE /datamachine/v1/processed-items` - Clear processed items

All REST endpoints require `manage_options` capability except `/users/me` (requires authentication only).

**Templates**: Filter-based rendering with REST API integration
**Jobs**: Clear processed items/jobs via modal

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

## Handler-Specific Engine Parameters

**Database Storage + Filter Access**: Each fetch handler stores specific engine parameters in database for downstream handlers accessed via `datamachine_engine_data` filter:

- **Reddit**: `source_url` (Reddit post URL), `image_url` (stored image URL)
- **WordPress Local**: `source_url` (permalink), `image_url` (featured image URL)
- **WordPress API**: `source_url` (post link), `image_url` (featured image URL)
- **WordPress Media**: `source_url` (parent post permalink when include_parent_content enabled), `image_url` (media URL)
- **RSS**: `source_url` (item link), `image_url` (enclosure URL)
- **Google Sheets**: `source_url` (empty), `image_url` (empty)
- **Files**: `image_url` (public URL for images only)

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

## Development

```bash
composer install    # Install dependencies
composer test       # Run tests (PHPUnit configured, test files pending implementation)
./build.sh          # Production build to /dist/datamachine.zip
```

**Required Dependencies**:
- Action Scheduler: Required for scheduled flow execution (woocommerce/action-scheduler via Composer)

**PSR-4 Structure**: `inc/Core/`, `inc/Engine/` - strict case-sensitive paths
**Filter Registration**: 40+ `*Filters.php` files auto-loaded via composer.json - handle registration, settings, auth providers, and centralized cross-cutting functionality
**Key Classes**: Directive classes, `AIStepToolParameters`, `AIStepConversationManager`
**AI HTTP Client**: `chubes4/ai-http-client` Composer dependency provides unified HTTP interface

### Engine Filter Architecture

**Streamlined Engine Filters** (`inc/Engine/Filters/`):
- **Create.php**: Centralized creation operations with comprehensive validation and permission checking
- **StatusDetection.php**: Legacy status detection removed; health check replacement pending implementation
- **EngineData.php**: Centralized engine data access via `datamachine_engine_data` filter - replaces direct database access patterns
- **Handlers.php**: Cross-cutting handler filters for shared functionality (timeframe parsing, keyword matching, data packet creation)
- **DataPacket.php**: Centralized data packet creation with standardized structure and timestamp management
- **Comment Cleanup**: Engine filters maintain clean, focused documentation without redundant comments
- **Atomic Operations**: Complete creation workflows with proper cache invalidation and REST API response handling
- **Permission Security**: All engine operations require `manage_options` capability with consistent validation

**Centralized Handler System**: The `Handlers.php` filter provides shared functionality across multiple handlers:

```php
// Timeframe parsing with discovery and conversion modes
$timeframe_options = apply_filters('datamachine_timeframe_limit', null, null); // Discovery mode
$cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, '24_hours'); // Conversion mode

// Universal keyword matching with OR logic
$matches = apply_filters('datamachine_keyword_search_match', true, $content, 'keyword1,keyword2');

// Standardized data packet creation
$data = apply_filters('datamachine_data_packet', $data, $packet_data, $flow_step_id, $step_type);
```

**Engine Data Centralization**: The `datamachine_engine_data` filter provides unified access to source_url, image_url stored by fetch handlers:

```php
// Centralized engine data access (replaces direct database calls)
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;

// Filter registration pattern maintains architectural consistency
add_filter('datamachine_engine_data', function($engine_data, $job_id) {
    $all_databases = apply_filters('datamachine_db', []);
    $db_jobs = $all_databases['jobs'] ?? null;
    return $db_jobs ? $db_jobs->retrieve_engine_data($job_id) : [];
}, 10, 2);
```

## Extensions

**Extension System**: Complete extension development framework for custom handlers and tools

**Discovery**: Filter-based auto-discovery system - extensions register using WordPress filters  
**Types**: Fetch handlers, Publish handlers, Update handlers, AI tools, Database services
**Development**: Extensions use WordPress plugin pattern with filter-based auto-discovery

**Extension Points**:
```php
add_filter('datamachine_handlers', [$this, 'register_handlers']);     // Fetch/publish/update handlers
add_filter('ai_tools', [$this, 'register_ai_tools']);       // AI capabilities
add_filter('datamachine_step_types', [$this, 'register_steps']);          // Custom step types
add_filter('datamachine_db', [$this, 'register_database']);          // Database services
add_filter('datamachine_admin_pages', [$this, 'register_pages']);    // Admin interfaces
add_filter('datamachine_tool_configured', [$this, 'check_config']);  // Configuration validation
add_filter('datamachine_get_handler_settings_display', [$this, 'customize_display'], 20, 3); // Flow step card display customization (priority 20+ for extensions)
```

**Handler Display Customization**:
Extensions get smart display defaults for free (auto-capitalized labels, acronym handling). Only customize when needed for special formatting.

**Smart Defaults (Priority 5 - Base Implementation)**:
- Auto-capitalizes field names: `'post_type'` → `'Post Type'`
- Handles common acronyms: `'ai'` → `'AI'`, `'api'` → `'API'`, `'url'` → `'URL'`, `'id'` → `'ID'`
- Formats values from field options automatically
- Extensions receive clean display without any customization

**Custom Display (Priority 10+ - Handler-Specific)**:
Extensions customize by hooking into `datamachine_get_handler_settings_display` at priority 10 or higher:

```php
// Example: Convert venue term IDs to full venue metadata display
add_filter('datamachine_get_handler_settings_display', function($settings_display, $flow_step_id, $step_type) {
    // Get flow step config to identify handler
    $flow_step_config = apply_filters('datamachine_get_flow_step_config', [], $flow_step_id);
    $handler_slug = $flow_step_config['handler_slug'] ?? '';

    // Only customize for your handler
    if ($handler_slug !== 'my_handler') {
        return $settings_display;
    }

    $customized_display = [];
    foreach ($settings_display as $setting) {
        // Convert IDs to human-readable names
        if ($setting['key'] === 'venue' && is_numeric($setting['value'])) {
            $venue_data = get_venue_metadata($setting['value']);

            // Replace single venue ID with comprehensive venue data
            $customized_display[] = [
                'key' => 'venue',
                'label' => 'Venue',
                'value' => $setting['value'],
                'display_value' => $venue_data['name']
            ];

            // Add additional venue fields
            foreach ($venue_data as $field => $value) {
                $customized_display[] = [
                    'key' => "venue_{$field}",
                    'label' => ucfirst($field),
                    'value' => $value,
                    'display_value' => $value
                ];
            }
            continue;
        }

        // Keep other settings unchanged
        $customized_display[] = $setting;
    }

    return $customized_display;
}, 20, 3); // Priority 20+ for extensions (core handlers use 10-15)
```

**Filter Parameters**:
- `$settings_display` (array): Current settings display array from base implementation or previous filters
- `$flow_step_id` (string): Flow step identifier for loading configuration
- `$step_type` (string): Step type ('fetch', 'publish', 'update')

**Priority System**:
- **Priority 5**: Base implementation with smart defaults (Engine/Filters/Handlers.php)
- **Priority 10**: WordPress core handlers (wordpress_publish, wordpress_posts, wordpress_update)
- **Priority 15**: Reddit handler (subreddit formatting)
- **Priority 20**: Facebook handler (OAuth field hiding)
- **Priority 20+**: Extension handlers (custom transformations)

**WordPress Utilities for Extensions**:
Extensions can access centralized WordPress utilities via filters (no imports needed):

```php
// Convert user ID to display name
$display_name = apply_filters('datamachine_wordpress_user_display_name', null, $user_id);

// Convert term ID to term name
$term_name = apply_filters('datamachine_wordpress_term_name', null, $term_id, $taxonomy);

// Get system taxonomies to exclude
$excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
// Returns: ['post_format', 'nav_menu', 'link_category']

// Get public taxonomies (with system taxonomies excluded)
$taxonomies = apply_filters('datamachine_wordpress_public_taxonomies', [], ['public' => true]);
```

These utilities are provided by `Engine/Filters/WordPress.php` and available to **all handlers and extensions** without requiring class imports. This prevents duplication of WordPress-specific logic across the ecosystem.

**Extension Pattern**: WordPress plugin with `datamachine_handlers`, `ai_tools`, `datamachine_tool_configured` filters

**Required Interfaces**:
- **Fetch**: `get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array`
- **Publish**: `handle_tool_call(array $parameters, array $tool_def = []): array`
- **Update**: `handle_tool_call(array $parameters, array $tool_def = []): array` (requires `source_url` from metadata)
- **Steps**: `execute(array $parameters): array`

**Error Handling**: Exception logging via `datamachine_log` action

## OAuth Integration

**Unified OAuth System**: Streamlined OAuth operations with automatic provider discovery and validation

```php
// Account operations
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

## Status & Rules

**Status Detection**: Legacy filter removed
- Previous values: `red` (error), `yellow` (warning), `green` (ready)
- Previous contexts: `ai_step`, `handler_auth`, `wordpress_draft`, `files_status`, `subsequent_publish_step`, `pipeline_step_status`, `flow_step_status`

**Status System**: Two specialized transports for optimized status refresh operations
- **Status REST** (`GET /datamachine/v1/status`): Returns flow and pipeline status maps using `flow_id[]` / `pipeline_id[]` query parameters; reuses `flow_step_status` and `pipeline_step_status` contexts for validation

**Core Rules**:
- Engine agnostic (no hardcoded step types in `/inc/Engine/`)
- Filter-based service discovery only
- Security: `wp_unslash()` BEFORE `sanitize_text_field()`
- CSS namespace: `dm-` prefix
- Auth: `manage_options` only
- Field naming: `pipeline_step_id` (UUID4)
