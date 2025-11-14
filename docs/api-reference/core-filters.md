# Core Filters Reference

Comprehensive reference for all WordPress filters used by Data Machine for service discovery, configuration, and data processing.

## Service Discovery Filters

### `datamachine_handlers`

**Purpose**: Register fetch, publish, and update handlers

**Parameters**:
- `$handlers` (array) - Current handlers array

**Return**: Array of handler definitions

**Handler Structure**:
```php
$handlers['handler_slug'] = [
    'type' => 'fetch|publish|update',
    'class' => 'HandlerClassName',
    'label' => __('Human Readable Name', 'data-machine'),
    'description' => __('Handler description', 'data-machine'),
    'requires_auth' => true  // Optional: Metadata flag for auth detection
];
```

**Usage Example**:
```php
add_filter('datamachine_handlers', function($handlers) {
    $handlers['twitter'] = [
        'type' => 'publish',
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
        'label' => __('Twitter', 'data-machine'),
        'description' => __('Post content to Twitter with media support', 'data-machine'),
        'requires_auth' => true  // Eliminates auth provider instantiation overhead
    ];
    return $handlers;
});
```

**Handler Metadata**:
- `requires_auth` (boolean): Optional metadata flag for performance optimization
- Eliminates auth provider instantiation during handler settings modal load
- Auth-enabled handlers: Twitter, Bluesky, Facebook, Threads, Google Sheets (publish & fetch), Reddit (fetch)

### `datamachine_step_types`

**Purpose**: Register step types for pipeline execution

**Parameters**:
- `$steps` (array) - Current steps array

**Return**: Array of step definitions

**Step Structure**:
```php
$steps['step_type'] = [
    'name' => __('Step Display Name', 'data-machine'),
    'class' => 'StepClassName',
    'position' => 50 // Display order
];
```

### `datamachine_db`

**Purpose**: Register database service classes

**Parameters**:
- `$databases` (array) - Current database services

**Return**: Array of database service instances

**Structure**:
```php
$databases['service_name'] = new ServiceClass();
```

## AI Integration Filters

### `ai_tools`

**Purpose**: Register AI tools for agentic execution

**Parameters**:
- `$tools` (array) - Current tools array
- `$handler_slug` (string|null) - Target handler slug (for handler-specific tools)
- `$handler_config` (array) - Handler configuration

**Return**: Array of tool definitions

**Tool Structure**:
```php
$tools['tool_name'] = [
    'class' => 'ToolClassName',
    'method' => 'handle_tool_call',
    'description' => 'Tool description for AI',
    'parameters' => [
        'param_name' => [
            'type' => 'string|integer|boolean',
            'required' => true|false,
            'description' => 'Parameter description'
        ]
    ],
    'handler' => 'handler_slug', // Optional: makes tool handler-specific
    'requires_config' => true|false, // Optional: UI configuration indicator
    'handler_config' => $handler_config // Optional: passed to tool execution
];
```

### `ai_request`

**Purpose**: Process AI requests with provider routing and modular directive system message injection

**Parameters**:
- `$request` (array) - AI request data
- `$provider` (string) - AI provider slug
- `$streaming_callback` (mixed) - Streaming callback function
- `$tools` (array) - Available tools array
- `$pipeline_step_id` (string|null) - Pipeline step ID for context

**Return**: Array with AI response

**5-Tier Directive System**: System messages automatically injected via separate directive classes in priority order:

**Priority 10**: Plugin core directive (`PluginCoreDirective`) - foundational AI agent identity with workflow termination logic and data packet structure guidance
**Priority 20**: Global system prompt (`GlobalSystemPromptDirective`) - background guidance
**Priority 30**: Pipeline system prompt (`PipelineSystemPromptDirective`) - user configuration
**Priority 40**: Tool definitions and directives (`ToolDefinitionsDirective`) - usage instructions
**Priority 50**: WordPress site context (`SiteContextDirective`) - environment info

**Request Structure**:
```php
$request = [
    'messages' => [
        ['role' => 'user', 'content' => 'Prompt text']
    ],
    'model' => 'model-name',
    'tools' => $tools_array // Optional
];
```

**5-Tier Auto-Registration**: Each directive class automatically registers with the ai_request filter:
```php
// Priority 10: Plugin core directive (foundational AI agent identity)
add_filter('ai_request', [PluginCoreDirective::class, 'inject'], 10, 5);

// Priority 20: Global system prompt (background guidance)
add_filter('ai_request', [GlobalSystemPromptDirective::class, 'inject'], 20, 5);

// Priority 30: Pipeline system prompt (user configuration)
add_filter('ai_request', [PipelineSystemPromptDirective::class, 'inject'], 30, 5);

// Priority 40: Tool definitions and directives (how to use available tools)
add_filter('ai_request', [ToolDefinitionsDirective::class, 'inject'], 40, 5);

// Priority 50: WordPress site context (environment info - lowest priority)
add_filter('ai_request', [SiteContextDirective::class, 'inject'], 50, 5);
```

## Pipeline Operations Filters

### `datamachine_create_pipeline`

**Purpose**: Create new pipeline

**Parameters**:
- `$pipeline_id` (null) - Placeholder for return value
- `$data` (array) - Pipeline creation data

**Return**: Integer pipeline ID or false

**Data Structure**:
```php
$data = [
    'pipeline_name' => 'Pipeline Name',
    'pipeline_config' => $config_array
];
```

### `datamachine_create_flow`

**Purpose**: Create new flow instance

**Parameters**:
- `$flow_id` (null) - Placeholder for return value
- `$data` (array) - Flow creation data

**Return**: Integer flow ID or false

### `datamachine_get_pipelines`

**Purpose**: Retrieve pipeline data

**Parameters**:
- `$pipelines` (array) - Empty array for return data
- `$pipeline_id` (int|null) - Specific pipeline ID or null for all

**Return**: Array of pipeline data

### `datamachine_get_flow_config`

**Purpose**: Get flow configuration

**Parameters**:
- `$config` (array) - Empty array for return data
- `$flow_id` (int) - Flow ID

**Return**: Array of flow configuration

### `datamachine_get_flow_step_config`

**Purpose**: Get specific flow step configuration

**Parameters**:
- `$config` (array) - Empty array for return data
- `$flow_step_id` (string) - Composite flow step ID

**Return**: Array containing flow step configuration

## Authentication Filters

### `datamachine_auth_providers`

**Purpose**: Register OAuth authentication providers

**Parameters**:
- `$providers` (array) - Current auth providers

**Return**: Array of authentication provider instances

**Structure**:
```php
$providers['provider_slug'] = new AuthProviderClass();
```

### `datamachine_retrieve_oauth_account`

**Purpose**: Get stored OAuth account data

**Parameters**:
- `$account` (array) - Empty array for return data
- `$handler` (string) - Handler slug

**Return**: Array of account information

### `datamachine_oauth_callback`

**Purpose**: Generate OAuth authorization URL

**Parameters**:
- `$url` (string) - Empty string for return data
- `$provider` (string) - Provider slug

**Return**: OAuth authorization URL string

## Configuration Filters

### `datamachine_tool_configured`

**Purpose**: Check if tool is properly configured

**Parameters**:
- `$configured` (bool) - Default configuration status
- `$tool_id` (string) - Tool identifier

**Return**: Boolean configuration status

### `datamachine_get_tool_config`

**Purpose**: Retrieve tool configuration data

**Parameters**:
- `$config` (array) - Empty array for return data
- `$tool_id` (string) - Tool identifier

**Return**: Array of tool configuration

### `datamachine_handler_settings`

**Purpose**: Register handler settings classes

**Parameters**:
- `$settings` (array) - Current settings array

**Return**: Array of settings class instances

## Parameter Processing Filters

### `datamachine_engine_data`

**Purpose**: Centralized engine data access filter for retrieving stored engine parameters

**Parameters**:
- `$engine_data` (array) - Default empty array for return data
- `$job_id` (int) - Job ID to retrieve engine data for

**Return**: Array containing engine data (source_url, image_url, etc.)

**Engine Data Structure**:
```php
$engine_data = [
    'source_url' => $source_url,    // For link attribution and content updates
    'image_url' => $image_url,      // For media handling
    // Additional engine parameters as needed
];
```

**Core Implementation (EngineData.php)**:
```php
add_filter('datamachine_engine_data', function($engine_data, $job_id) {
    if (empty($job_id)) {
        return [];
    }

    // Use established filter pattern for database service discovery
    $all_databases = apply_filters('datamachine_db', []);
    $db_jobs = $all_databases['jobs'] ?? null;

    if (!$db_jobs) {
        return [];
    }

    $retrieved_data = $db_jobs->retrieve_engine_data($job_id);
    return $retrieved_data ?: [];
}, 10, 2);
```

**Usage by Steps**:
```php
// Steps access engine data as needed
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
```

**Engine Data Storage (by Fetch Handlers)**:
```php
// Fetch handlers store engine parameters in database via centralized filter (array storage)
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}
```

**Benefits**:
- ✅ **Centralized Access**: Single filter for all engine data retrieval
- ✅ **Filter-Based Discovery**: Uses established database service discovery pattern
- ✅ **Clean Separation**: Engine data separate from AI data packets
- ✅ **Flexible**: Steps access only what they need via filter call

## Centralized Handler Filters

### `datamachine_timeframe_limit`

**Purpose**: Shared timeframe parsing across fetch handlers with discovery and conversion modes

**Parameters**:
- `$default` (mixed) - Default value (null or timestamp)
- `$timeframe_limit` (string|null) - Timeframe specification

**Return**: Array of options (discovery mode) or timestamp (conversion mode) or null

**Discovery Mode** (when `$timeframe_limit` is null):
```php
$timeframe_options = apply_filters('datamachine_timeframe_limit', null, null);
// Returns:
[
    'all_time' => __('All Time', 'data-machine'),
    '24_hours' => __('Last 24 Hours', 'data-machine'),
    '72_hours' => __('Last 72 Hours', 'data-machine'),
    '7_days'   => __('Last 7 Days', 'data-machine'),
    '30_days'  => __('Last 30 Days', 'data-machine'),
]
```

**Conversion Mode** (when `$timeframe_limit` is a string):
```php
$cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, '24_hours');
// Returns: Unix timestamp for 24 hours ago or null for 'all_time'
```

### `datamachine_keyword_search_match`

**Purpose**: Universal keyword matching with OR logic for all fetch handlers

**Parameters**:
- `$default` (bool) - Default match result
- `$content` (string) - Content to search in
- `$search_term` (string) - Comma-separated keywords

**Return**: Boolean indicating if any keyword matches

**Usage**:
```php
$matches = apply_filters('datamachine_keyword_search_match', true, $content, 'wordpress,ai,automation');
// Returns true if content contains 'wordpress' OR 'ai' OR 'automation'
```

**Features**:
- **OR Logic**: Any keyword match passes the filter
- **Case Insensitive**: Uses `mb_stripos()` for Unicode-safe matching
- **Comma Separated**: Supports multiple keywords separated by commas
- **Empty Filter**: Returns true when no search term provided (match all)

### `datamachine_data_packet`

**Purpose**: Centralized data packet creation with standardized structure

**Parameters**:
- `$data` (array) - Current data packet array
- `$packet_data` (array) - Packet data to add
- `$flow_step_id` (string) - Flow step identifier
- `$step_type` (string) - Step type

**Return**: Array with new packet added to front

**Usage**:
```php
$data = apply_filters('datamachine_data_packet', $data, $packet_data, $flow_step_id, $step_type);
```

**Features**:
- **Standardized Structure**: Ensures type and timestamp fields are present
- **Preserves All Fields**: Merges packet_data while adding missing structure
- **Front Addition**: Uses `array_unshift()` to add new packets to the beginning

## Data Processing Filters

### `datamachine_is_item_processed`

**Purpose**: Check if item was already processed

**Parameters**:
- `$processed` (bool) - Default processed status
- `$flow_step_id` (string) - Flow step identifier
- `$source_type` (string) - Handler source type
- `$item_id` (mixed) - Item identifier

**Return**: Boolean processed status


## Files Repository Filters

### `datamachine_files_repository`

**Purpose**: Access files repository service

**Parameters**:
- `$repositories` (array) - Empty array for repository services

**Return**: Array with 'files' key containing repository instance

## Settings Filters

### `datamachine_enabled_settings`

**Purpose**: Get enabled settings for handlers/steps

**Parameters**:
- `$fields` (array) - Default settings fields
- `$handler_slug` (string) - Handler identifier
- `$step_type` (string) - Step type
- `$context` (array) - Additional context

**Return**: Array of enabled settings fields

### `datamachine_apply_global_defaults`

**Purpose**: Apply global default settings

**Parameters**:
- `$current_settings` (array) - Current settings
- `$handler_slug` (string) - Handler identifier
- `$step_type` (string) - Step type

**Return**: Array of settings with global defaults applied

## Navigation Filters

### `datamachine_get_next_flow_step_id`

**Purpose**: Find next step in flow execution sequence

**Parameters**:
- `$next_id` (null) - Placeholder for return value
- `$current_flow_step_id` (string) - Current step ID

**Return**: String next flow step ID or null if last step

## AI Tool Parameter Management

### AIStepToolParameters Static Methods

**Purpose**: Centralized flat parameter building for AI tool execution with unified structure compatible with all handler tool call methods.

**Core Methods**:

#### `buildParameters()`
```php
AIStepToolParameters::buildParameters(array $ai_tool_parameters, array $unified_parameters, array $tool_definition): array
```
Builds flat parameter structure for standard AI tool execution with content extraction and tool metadata.

#### `buildForHandlerTool()`
```php
AIStepToolParameters::buildForHandlerTool(array $ai_tool_parameters, array $data, array $tool_definition, array $engine_parameters, array $handler_config): array
```
Builds parameters for handler-specific tools with engine parameters merged (like source_url for link attribution).

**Key Features**:
- **Content Extraction**: Automatically extracts content and title from data packets based on tool specifications
- **Flat Parameter Structure**: Single array containing all parameters without nested objects
- **Tool Metadata Integration**: Adds tool_definition, tool_name, handler_config directly to parameter structure
- **Engine Parameter Merging**: For handler tools, merges additional engine parameters like source_url
- **AI Parameter Priority**: AI-provided parameters overwrite any conflicting keys in final structure

## AI Conversation Management

### AIStepConversationManager Static Methods

**Purpose**: Centralized conversation state management and message formatting for AI steps with turn tracking and chronological message ordering

**Core Methods**:

#### `generateSuccessMessage()`
```php
AIStepConversationManager::generateSuccessMessage(string $tool_name, array $tool_result, array $tool_parameters): string
```
Creates human-readable success messages for tool execution results, enabling natural AI agent conversation termination.

#### `formatToolCallMessage()`
```php
AIStepConversationManager::formatToolCallMessage(string $tool_name, array $tool_parameters, int $turn_count): array
```
Records AI tool calls in conversation history with turn tracking before execution.

#### `formatToolResultMessage()`
```php  
AIStepConversationManager::formatToolResultMessage(string $tool_name, array $tool_result, array $tool_parameters, bool $is_handler_tool = false, int $turn_count = 0): array
```
Formats tool results into conversation message structure for AI consumption with temporal context.

#### `updateDataPacketMessages()`
```php
AIStepConversationManager::updateDataPacketMessages(array $conversation_messages, array $data): array
```
Updates data packet messages in multi-turn conversations to keep AI aware of current data state.

#### `buildConversationMessage()`
```php
AIStepConversationManager::buildConversationMessage(string $role, string $content): array
```
Creates standardized conversation message structure with proper role assignment.

#### `generateFailureMessage()`
```php
AIStepConversationManager::generateFailureMessage(string $tool_name, string $error_message): string
```
Generates clear error messages when tools fail to execute properly.

#### `logConversationAction()`
```php
AIStepConversationManager::logConversationAction(string $action, array $context = []): void
```
Provides debug logging for conversation message generation with context data.

**Key Features**:
- **Turn Tracking**: Each conversation iteration tracked with turn counter for multi-turn AI executions
- **Chronological Message Ordering**: `array_push()` maintains temporal sequence in conversation messages (newest at end)
- **AI Action Records**: Tool calls recorded in conversation history before execution with turn number context
- **Tool Result Messaging**: Enhanced tool result messages with temporal context (`Turn X`) and specialized formatting
- Platform-specific success message templates (Twitter, WordPress, Google Search, etc.)
- Clear completion messaging enabling natural conversation termination
- Multi-turn conversation state preservation with complete conversation history
- Centralized conversation history building with context awareness
- Tool result formatting for optimal AI model consumption