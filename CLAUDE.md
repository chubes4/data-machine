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
$result = apply_filters('ai_request', $request, 'anthropic');
$tools = apply_filters('ai_tools', []);
apply_filters('dm_tool_configured', false, $tool_id);
apply_filters('dm_get_tool_config', [], $tool_id);
do_action('dm_save_tool_config', $tool_id, $config_data);

// OAuth
apply_filters('dm_retrieve_oauth_account', [], 'handler');
apply_filters('dm_oauth_callback', '', 'provider');
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
SiteContext::clear_cache();

// System
do_action('dm_log', $level, $message, $context);
do_action('dm_auto_save', $pipeline_id);
do_action('dm_fail_job', $job_id, $reason, $context_data); // Explicit job failure with configurable cleanup
do_action('dm_cleanup_old_files'); // File repository maintenance via Action Scheduler
$files_repo = apply_filters('dm_files_repository', [])['files'] ?? null;

// Cache Management
do_action('dm_clear_pipeline_cache', $pipeline_id); // Clear specific pipeline cache
do_action('dm_clear_flow_cache', $flow_id); // Clear specific flow cache
do_action('dm_clear_jobs_cache'); // Clear all job caches
do_action('dm_clear_all_cache'); // Clear all Data Machine caches
do_action('dm_cache_set', $key, $data, $timeout, $group); // Standardized cache storage
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
            'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
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
- **Handlers**: Fetch (Files, RSS, Reddit, Google Sheets, WordPress Local, WordPress Media, WordPress API) | Publish (Twitter, Bluesky, Threads, Facebook, Google Sheets, WordPress) | Update (WordPress content updates) | AI (OpenAI, Anthropic, Google, Grok, OpenRouter)
- **WordPress Publish Components**: Modular handler architecture with `FeaturedImageHandler`, `TaxonomyHandler`, `SourceUrlHandler` for specialized processing
- **AutoSave System**: Complete pipeline auto-save with flow synchronization and cache invalidation
- **Admin**: `manage_options` only, zero user dependencies

## Database Schema

**Core Tables**:

```sql
-- Pipeline templates (reusable)
wp_dm_pipelines: pipeline_id, pipeline_name, pipeline_config, created_at, updated_at

-- Flow instances (scheduled + configured)
wp_dm_flows: flow_id, pipeline_id, flow_name, flow_config, scheduling_config, display_order

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
], 'openai');
```

### AI Request Priority System

**6-Tier AI Directive Priority System**: AI requests receive multiple system messages via auto-registering directive classes:

1. **Priority 5 - Plugin Core Directive** (`PluginCoreDirective`): Foundational AI agent identity and core behavioral principles
2. **Priority 10 - Global System Prompt** (`GlobalSystemPromptDirective`): User-configured foundational AI behavior
3. **Priority 20 - Pipeline System Prompt** (`PipelineSystemPromptDirective`): Pipeline instructions and workflow visualization
4. **Priority 30 - Tool Definitions** (`ToolDefinitionsDirective`): Dynamic tool prompts and workflow context
5. **Priority 40 - Data Packet Structure** (`DataPacketStructureDirective`): JSON structure explanation for AI agents
6. **Priority 50 - WordPress Site Context** (`SiteContextDirective`): WordPress environment info (toggleable)

```php
// Auto-registering directive classes with standardized priority spacing
add_filter('ai_request', [PluginCoreDirective::class, 'inject'], 5, 5);
add_filter('ai_request', [GlobalSystemPromptDirective::class, 'inject'], 10, 5);
add_filter('ai_request', [PipelineSystemPromptDirective::class, 'inject'], 20, 5);
add_filter('ai_request', [ToolDefinitionsDirective::class, 'inject'], 30, 5);
add_filter('ai_request', [DataPacketStructureDirective::class, 'inject'], 40, 5);
add_filter('ai_request', [SiteContextDirective::class, 'inject'], 50, 5);
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
- **AIStepConversationManager**: Centralized conversation state management and message formatting with turn-based tracking
- **Turn-Based Conversations**: Multi-turn conversation loops with chronological message ordering using `array_push()` for temporal sequence preservation
- **AI Action Records**: AI tool calls are recorded in conversation history before execution with turn number tracking
- **Tool Result Messaging**: Enhanced tool result messages with temporal context (`Turn X`) and specialized formatting for different tool types
- **Data Packet Synchronization**: Dynamic data packet updates in conversation messages via `updateDataPacketMessages()`
- **Conversation Completion**: Natural AI agent termination with clear success/failure messaging and handler tool execution tracking

```php
// Conversation Management Methods with Turn Tracking
AIStepConversationManager::formatToolCallMessage($tool_name, $tool_parameters, $turn_count);
AIStepConversationManager::formatToolResultMessage($tool_name, $tool_result, $tool_parameters, $is_handler_tool, $turn_count);
AIStepConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);
AIStepConversationManager::updateDataPacketMessages($conversation_messages, $data);
AIStepConversationManager::buildConversationMessage($role, $content);
AIStepConversationManager::generateFailureMessage($tool_name, $error_message);
AIStepConversationManager::logConversationAction($action, $context);
```

**Conversation Flow Architecture**:
- **Chronological Ordering**: `array_push()` maintains temporal sequence in conversation messages (newest at end)
- **Turn Counter**: Each conversation iteration increments turn counter for tracking multi-turn executions
- **State Preservation**: Complete conversation history maintained across tool executions with context awareness
- **Message Types**: System directives → User data → AI responses → Tool calls → Tool results in chronological order

**Tool Result Message Examples**:
- **Google Search**: "SEARCH COMPLETE: Found {count} results for \"{query}\".\nSearch Results:" or "SEARCH COMPLETE: No results found for \"{query}\". Search task finished."
- **WordPress Publish**: "SUCCESS: WordPress post published successfully. Title: '{title}' is now live at {url} (ID: {id}). Your content has been published as requested."
- **Default**: "SUCCESS: {Tool Name} completed successfully. The requested operation has been finished as requested." (filtered via `dm_tool_success_message`)

**AI Step Execution Model**:
- AI steps can run standalone using flow-level user messages when no fetch step precedes
- System prompts (pipeline-level) provide consistent behavior templates
- User messages (flow-level) enable different prompts per flow instance
- Multi-turn conversation support with context preservation across tool executions
- Conversation state management through AIStepConversationManager for clear AI communication

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
- **General Tools**: Universal (Google Search, Local Search, WebFetch, WordPress Post Reader) - available to all AI agents

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

    $tools['local_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\LocalSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search WordPress site for existing content',
        'requires_config' => false,
        'parameters' => ['query' => ['type' => 'string', 'required' => true]]
        // No 'handler' property = universal tool
    ];

    $tools['web_fetch'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\WebFetch',
        'method' => 'handle_tool_call',
        'description' => 'Retrieve and process web page content (50K character limit)',
        'requires_config' => false,
        'parameters' => ['url' => ['type' => 'string', 'required' => true]]
        // No 'handler' property = universal tool
    ];

    $tools['wordpress_post_reader'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\WordPressPostReader',
        'method' => 'handle_tool_call',
        'description' => 'Read full WordPress post content by URL for detailed analysis',
        'requires_config' => false,
        'parameters' => ['source_url' => ['type' => 'string', 'required' => true]]
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

### Tool Parameter Architecture

**AIStepToolParameters Class**: Centralized flat parameter building for AI tool execution with unified structure compatible with all handler tool call methods.

```php
// Standard AI tool parameter building - creates flat parameter structure  
$parameters = AIStepToolParameters::buildParameters(
    $ai_tool_parameters,     // Parameters from AI tool call
    $unified_parameters,     // Unified parameter structure from engine
    $tool_definition         // Tool definition from ai_tools filter
);

// Handler tools with engine parameters merged - for Update handlers requiring source_url
$parameters = AIStepToolParameters::buildForHandlerTool(
    $ai_tool_parameters,     // AI tool call parameters
    $data,                   // Data packet array for content extraction
    $tool_definition,        // Tool specification
    $engine_parameters,      // Additional parameters from engine context (source_url, etc.)
    $handler_config         // Handler-specific settings
);
```

**Parameter Building Process**:
- Starts with engine parameters as base flat structure (job_id, flow_step_id, data, flow_step_config)
- Extracts content/title from data packets based on tool parameter specifications
- Adds tool metadata (tool_definition, tool_name, handler_config) directly to flat structure
- Merges AI-provided parameters directly - overwrites any conflicting keys
- For handler tools: merges engine parameters (like source_url) for Update handlers

**Flat Parameter Benefits**:
- **Unified Interface**: Single flat array structure for all tool execution methods
- **Simple Extraction**: No complex nested structures - all data at root level
- **Extensible**: New metadata automatically available as additional parameters
- **Compatible**: Works with existing handler tool call method signatures


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
$data = AIStepTools->get_tools_data($pipeline_step_id);
// Includes configuration warnings and per-step enablement checkboxes
```


## Handler Matrix

| **Fetch** | **Auth** | **Features** |
|-----------|----------|--------------|
| Files | None | Local/remote file processing, flow-isolated storage |
| RSS | None | Feed parsing, deduplication tracking |
| Reddit | OAuth2 | Subreddit posts, comments, API-based fetching |
| Google Sheets | OAuth2 | Spreadsheet data extraction, cell-level access |
| WordPress Local | None | Local post/page content retrieval, specific post ID targeting, taxonomy filtering, timeframe filtering |
| WordPress Media | None | Media library attachments, file URLs, metadata handling, recent uploads filtering |
| WordPress API | None | External WordPress sites via REST API, structured data access, modern RSS alternative |

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

## DataPacket Structure

```php
// Standard format for all step types
[
    'type' => 'fetch|ai|update|publish',
    'handler' => 'rss|twitter|etc', // Optional for AI
    'content' => ['title' => $title, 'body' => $content], // Clean content without URL injection
    'metadata' => ['source_type' => $type, 'pipeline_id' => $id, 'source_url' => $url /*...*/],
    'timestamp' => time()
]
```

**Processing**: Each step adds entry to array front → accumulates complete workflow history
**Clean Content**: Fetch handlers (RSS, Reddit) provide clean content without URL pollution; source URLs maintained in metadata only

## WordPress Publish Handler Architecture

**Modular Components**: The WordPress publish handler is refactored into specialized processing modules for maintainability and extensibility.

### FeaturedImageHandler
```php
// Configuration hierarchy - system defaults override handler config
$image_handler = new FeaturedImageHandler();
$result = $image_handler->processImage($post_id, $parameters, $handler_config);

// Configuration hierarchy
if (isset($wp_settings['default_enable_images'])) {
    return (bool) $wp_settings['default_enable_images'];  // System default ALWAYS overrides
}
return (bool) ($handler_config['enable_images'] ?? true);  // Fallback to handler config
```

**Features**:
- Configuration hierarchy validation (system defaults override handler settings)
- Image URL validation and sanitization
- WordPress media library integration with `media_handle_sideload()`
- Featured image assignment with comprehensive error handling
- Temporary file cleanup and logging

### TaxonomyHandler
```php
// Configuration-based taxonomy processing
$taxonomy_handler = new TaxonomyHandler();
$results = $taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);

// Three selection modes per taxonomy:
// 1. 'skip' - No processing
// 2. 'ai_decides' - Use AI-provided parameters
// 3. numeric ID - Pre-selected term assignment
```

**Features**:
- Configuration-based taxonomy selection (skip, AI-decided, pre-selected)
- Dynamic term creation with `wp_insert_term()` for non-existing terms
- AI parameter extraction with standard naming (`category`, `tags`, custom taxonomy names)
- WordPress taxonomy operations with comprehensive validation
- Public taxonomy discovery excluding system taxonomies (`post_format`, `nav_menu`, `link_category`)

### SourceUrlHandler
```php
// Source URL processing with Gutenberg block generation
$source_handler = new SourceUrlHandler();
$content = $source_handler->processSourceUrl($content, $parameters, $handler_config);

// Configuration hierarchy - same pattern as image handler
if (isset($wp_settings['default_include_source'])) {
    return (bool) $wp_settings['default_include_source'];  // System override
}
return (bool) ($handler_config['include_source'] ?? false);  // Handler fallback
```

**Features**:
- Configuration hierarchy validation for source inclusion
- URL validation and sanitization with `esc_url()`
- Gutenberg block generation (separator + paragraph with source link)
- Content appending with proper formatting
- Clean source attribution without content pollution

### Handler Integration
```php
// Main WordPress handler uses modular components
class WordPress {
    private $featured_image_handler;
    private $taxonomy_handler;
    private $source_url_handler;

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Create post first, then process components
        $post_id = $this->create_wordpress_post($content, $handler_config);

        // Process featured image
        $image_result = $this->featured_image_handler->processImage($post_id, $parameters, $handler_config);

        // Process taxonomies
        $taxonomy_results = $this->taxonomy_handler->processTaxonomies($post_id, $parameters, $handler_config);

        // Process source URL in content
        $final_content = $this->source_url_handler->processSourceUrl($content, $parameters, $handler_config);

        return ['success' => true, 'data' => ['id' => $post_id, 'url' => $url]];
    }
}
```

## AutoSave System

**Complete Pipeline Persistence**: Centralized auto-save operations handle all pipeline-related data in a single action.

```php
// Single action saves everything
do_action('dm_auto_save', $pipeline_id);

// AutoSave system handles:
// 1. Pipeline data and configuration
// 2. All flows for the pipeline
// 3. Flow configurations and scheduling
// 4. execution_order synchronization from pipeline steps to flow steps
// 5. Cache invalidation after successful save
```

**AutoSave Features**:
- **Complete Data Persistence**: Saves pipeline data, all flows, flow configurations, scheduling, and handler settings
- **execution_order Synchronization**: Updates flow step execution_order to match pipeline step order
- **Cache Invalidation**: Clears pipeline cache after successful auto-save for data consistency
- **Database Service Discovery**: Uses filter-based database service access
- **Comprehensive Logging**: Debug logs for all auto-save operations with context data
- **Error Handling**: Validates database services and pipeline existence before processing

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

### Universal Handler Settings Template System

**Template Path**: `inc/Core/Admin/Pages/Pipelines/templates/modal/handler-settings.php`

**Unified Configuration**: Single template handles all handler types, eliminating code duplication across individual handler templates.

```php
// Universal template approach
$handler_settings = apply_filters('dm_handler_settings', [])[$handler_slug] ?? null;
if ($handler_settings && method_exists($handler_settings, 'get_fields')) {
    $settings_fields = apply_filters('dm_enabled_settings',
        $handler_settings::get_fields($current_settings),
        $handler_slug, $step_type, $context
    );
}
```

**Template Features**:
- **Dynamic Field Rendering**: Uses Settings classes to generate appropriate fields for any handler type
- **Authentication Integration**: Automatic auth system detection and management button display
- **Global Settings Notification**: Shows active WordPress global settings with direct settings link
- **Context-Aware Configuration**: Supports flow-level and pipeline-level configuration inheritance
- **Validation Integration**: Real-time configuration validation and status indicators

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

## Parameter Systems

**Simplified Two-Parameter Architecture**:

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

### 2. Flat Parameter Structure  
All parameters flow through the single `dm_engine_parameters` filter as a simple flat array:
```php
// Flat parameter structure built by engine
$parameters = apply_filters('dm_engine_parameters', [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data
    // Additional parameters added by filters as needed
], $data, $flow_step_config, $step_type, $flow_step_id);
```

### Step Implementation Pattern
```php
class MyStep {
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        
        // Extract what this step needs
        $source_url = $parameters['source_url'] ?? null;
        // ... use parameters as needed
        
        return $data;
    }
}
```



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

// Fetch Handler
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        do_action('dm_mark_item_processed', $flow_step_id, 'my_handler', $item_id, $job_id);
        return ['processed_items' => $items];
    }
}

// Publish/Update Handler
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_config = $tool_def['handler_config'] ?? [];
        // For Update handlers: $parameters['source_url'] required from engine
        return ['success' => true, 'data' => ['id' => $id, 'url' => $url]];
    }
}
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

> **Critical**: Update steps require `source_url` from fetch metadata to identify target content. WordPress Local/API fetch handlers provide this automatically. AI agents discover handler tools for immediate next step only.

## Cache System

**Centralized Cache Management**: Actions/Cache.php provides WordPress action-based cache clearing system for comprehensive cache management throughout the codebase.

**Cache Actions**:
```php
// Granular cache clearing
do_action('dm_clear_pipeline_cache', $pipeline_id); // Pipeline + flows + jobs
do_action('dm_clear_flow_cache', $flow_id); // Flow-specific caches
do_action('dm_clear_jobs_cache'); // All job-related caches
do_action('dm_clear_all_cache'); // Complete cache reset

// Standardized cache storage
do_action('dm_cache_set', $key, $data, $timeout, $group);
```

**Cache Architecture**:
- **Pipeline Cache**: Configuration, flows aggregation, step definitions
- **Flow Cache**: Configuration, scheduling data, display order
- **Job Cache**: Status tracking, recent jobs, flow-specific job lists
- **Pattern-Based Clearing**: Supports wildcard patterns (dm_pipeline_*, dm_flow_*, dm_job_*)
- **Logging Integration**: All cache operations logged via dm_log action
- **WordPress Transients**: Uses native WordPress transient system for storage

**Key Features**:
- Eliminates scattered cache clearing throughout codebase
- Centralized cache key constants for consistency
- Granular cache invalidation for performance
- Comprehensive logging for debugging cache operations

## Development

```bash
composer install && composer test
./build.sh  # Production build
```

**PSR-4 Structure**: `inc/Core/`, `inc/Engine/` - strict case-sensitive paths
**Filter Registration**: 40+ `*Filters.php` files auto-loaded via composer.json
**Key Classes**: Directive classes, `AIStepToolParameters`, `AIStepConversationManager`
**AI HTTP Client**: `chubes4/ai-http-client` Composer dependency provides unified HTTP interface

### Engine Filter Architecture

**Streamlined Engine Filters** (`inc/Engine/Filters/`):
- **Create.php**: Centralized creation operations with comprehensive validation and permission checking
- **StatusDetection.php**: Filter-based status detection system with RED/YELLOW/GREEN priority architecture
- **Comment Cleanup**: Engine filters maintain clean, focused documentation without redundant comments
- **Atomic Operations**: Complete creation workflows with proper cache invalidation and AJAX response handling
- **Permission Security**: All engine operations require `manage_options` capability with consistent validation

## Extensions

**Extension System**: Complete extension development framework for custom handlers and tools

**Discovery**: Filter-based auto-discovery system - extensions register using WordPress filters  
**Types**: Fetch handlers, Publish handlers, Update handlers, AI tools, Database services
**Development**: Extensions use WordPress plugin pattern with filter-based auto-discovery

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
- **Update**: `handle_tool_call(array $parameters, array $tool_def = []): array` (requires `source_url` from metadata)
- **Steps**: `execute(array $parameters): array`

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

**Unified OAuth System**: Streamlined OAuth operations with automatic provider discovery and validation

```php
// Account operations
$account = apply_filters('dm_retrieve_oauth_account', [], 'twitter');
apply_filters('dm_store_oauth_account', $account_data, 'twitter');
apply_filters('dm_clear_oauth_account', false, 'twitter');

// Configuration with validation  
$is_configured = apply_filters('dm_tool_configured', false, 'twitter');
$provider_url = apply_filters('dm_oauth_callback', '', 'twitter');

// Provider discovery
$providers = apply_filters('dm_auth_providers', []);
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
