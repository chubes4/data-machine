# Core Filters Reference

Comprehensive reference for all WordPress filters used by Data Machine for service discovery, configuration, and data processing.

## Service Discovery Filters

### `dm_handlers`

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
    'description' => __('Handler description', 'data-machine')
];
```

**Usage Example**:
```php
add_filter('dm_handlers', function($handlers) {
    $handlers['twitter'] = [
        'type' => 'publish',
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
        'label' => __('Twitter', 'data-machine'),
        'description' => __('Post content to Twitter with media support', 'data-machine')
    ];
    return $handlers;
});
```

### `dm_steps`

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

### `dm_db`

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

**Purpose**: Process AI requests with provider routing

**Parameters**:
- `$request` (array) - AI request data
- `$provider` (string) - AI provider slug

**Return**: Array with AI response

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

## Pipeline Operations Filters

### `dm_create_pipeline`

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

### `dm_create_flow`

**Purpose**: Create new flow instance

**Parameters**:
- `$flow_id` (null) - Placeholder for return value  
- `$data` (array) - Flow creation data

**Return**: Integer flow ID or false

### `dm_get_pipelines`

**Purpose**: Retrieve pipeline data

**Parameters**:
- `$pipelines` (array) - Empty array for return data
- `$pipeline_id` (int|null) - Specific pipeline ID or null for all

**Return**: Array of pipeline data

### `dm_get_flow_config`

**Purpose**: Get flow configuration

**Parameters**:
- `$config` (array) - Empty array for return data
- `$flow_id` (int) - Flow ID

**Return**: Array of flow configuration

### `dm_get_flow_step_config`

**Purpose**: Get specific flow step configuration

**Parameters**:
- `$config` (array) - Empty array for return data
- `$flow_step_id` (string) - Composite flow step ID

**Return**: Array containing flow step configuration

## Authentication Filters

### `dm_auth_providers`

**Purpose**: Register OAuth authentication providers

**Parameters**:
- `$providers` (array) - Current auth providers

**Return**: Array of authentication provider instances

**Structure**:
```php
$providers['provider_slug'] = new AuthProviderClass();
```

### `dm_retrieve_oauth_account`

**Purpose**: Get stored OAuth account data

**Parameters**:
- `$account` (array) - Empty array for return data
- `$handler` (string) - Handler slug

**Return**: Array of account information

### `dm_get_oauth_url`

**Purpose**: Generate OAuth authorization URL

**Parameters**:
- `$url` (string) - Empty string for return data
- `$provider` (string) - Provider slug

**Return**: OAuth authorization URL string

## Configuration Filters

### `dm_tool_configured`

**Purpose**: Check if tool is properly configured

**Parameters**:
- `$configured` (bool) - Default configuration status
- `$tool_id` (string) - Tool identifier

**Return**: Boolean configuration status

### `dm_get_tool_config`

**Purpose**: Retrieve tool configuration data

**Parameters**:
- `$config` (array) - Empty array for return data
- `$tool_id` (string) - Tool identifier

**Return**: Array of tool configuration

### `dm_handler_settings`

**Purpose**: Register handler settings classes

**Parameters**:
- `$settings` (array) - Current settings array

**Return**: Array of settings class instances

## Data Processing Filters

### `dm_is_item_processed`

**Purpose**: Check if item was already processed

**Parameters**:
- `$processed` (bool) - Default processed status
- `$flow_step_id` (string) - Flow step identifier
- `$source_type` (string) - Handler source type
- `$item_id` (mixed) - Item identifier

**Return**: Boolean processed status

### `dm_detect_status`

**Purpose**: Determine system/component status

**Parameters**:
- `$status` (string) - Default status ('green')
- `$context` (string) - Status context
- `$data` (array) - Additional data for status determination

**Return**: Status string ('red', 'yellow', 'green')

### `dm_step_additional_parameters`

**Purpose**: Provide additional parameters to step execution

**Parameters**:
- `$additional` (array) - Empty array for additional parameters
- `$data` (array) - Current data packet
- `$flow_step_config` (array) - Step configuration
- `$step_type` (string) - Step type
- `$flow_step_id` (string) - Flow step ID

**Return**: Array of additional parameters for step execution

## Files Repository Filters

### `dm_files_repository`

**Purpose**: Access files repository service

**Parameters**:
- `$repositories` (array) - Empty array for repository services

**Return**: Array with 'files' key containing repository instance

## Settings Filters

### `dm_enabled_settings`

**Purpose**: Get enabled settings for handlers/steps

**Parameters**:
- `$fields` (array) - Default settings fields
- `$handler_slug` (string) - Handler identifier
- `$step_type` (string) - Step type
- `$context` (array) - Additional context

**Return**: Array of enabled settings fields

### `dm_apply_global_defaults`

**Purpose**: Apply global default settings

**Parameters**:
- `$current_settings` (array) - Current settings
- `$handler_slug` (string) - Handler identifier
- `$step_type` (string) - Step type

**Return**: Array of settings with global defaults applied

## Navigation Filters

### `dm_get_next_flow_step_id`

**Purpose**: Find next step in flow execution sequence

**Parameters**:
- `$next_id` (null) - Placeholder for return value
- `$current_flow_step_id` (string) - Current step ID

**Return**: String next flow step ID or null if last step

## Handler Directives Filter

### `dm_handler_directives`

**Purpose**: Register AI directives for specific handlers

**Parameters**:
- `$directives` (array) - Current directives array

**Return**: Array of handler-specific AI directives

**Structure**:
```php
$directives['handler_slug'] = 'AI instruction text for this handler...';
```