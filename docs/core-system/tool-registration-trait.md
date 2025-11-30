# Tool Registration Trait

**Location**: `/inc/Engine/AI/Tools/ToolRegistrationTrait.php`  
**Since**: v0.2.2  
**Purpose**: Standardized AI tool registration functionality that eliminates repetitive registration code across tool implementations.

## Overview

The ToolRegistrationTrait provides agent-agnostic tool registration patterns that dynamically create appropriate filters based on agent type. This enables unlimited agent specialization while maintaining consistent registration patterns across the entire AI tool ecosystem.

## Key Features

- **Agent-Agnostic Registration**: Single `registerTool()` method handles all agent types
- **Dynamic Filter Creation**: Automatically creates `datamachine_{agentType}_tools` filters
- **Extensible Architecture**: Supports current and future agent types (global, chat, frontend, etc.)
- **Configuration Management**: Built-in support for tool configuration handlers
- **Success Message Formatting**: Standardized success message handling

## Methods

### `registerTool(string $agentType, string $toolName, array $toolDefinition)`

Core registration method that dynamically creates the appropriate filter based on agent type.

**Parameters:**
- `$agentType`: Agent type identifier (global, chat, frontend, etc.)
- `$toolName`: Tool identifier
- `$toolDefinition`: Complete tool definition array

**Example:**
```php
$this->registerTool('chat', 'create_pipeline', [
    'class' => 'DataMachine\Api\Chat\Tools\CreatePipeline',
    'method' => 'handle_tool_call',
    'description' => 'Create a new pipeline with optional steps',
    'parameters' => [
        'name' => ['type' => 'string', 'description' => 'Pipeline name'],
        'steps' => ['type' => 'array', 'description' => 'Optional initial steps']
    ]
]);
```

### `registerGlobalTool(string $tool_name, array $tool_definition)`

Convenience method for registering tools available to all AI agents (pipeline + chat).

**Example:**
```php
$this->registerGlobalTool('google_search', [
    'class' => 'DataMachine\Engine\AI\Tools\Global\GoogleSearch',
    'method' => 'handle_tool_call',
    'description' => 'Search the web using Google Custom Search API',
    'parameters' => [
        'query' => ['type' => 'string', 'description' => 'Search query']
    ]
]);
```

### `registerChatTool(string $tool_name, array $tool_definition)`

Convenience method for registering chat-specific tools.

**Example:**
```php
$this->registerChatTool('create_flow', [
    'class' => 'DataMachine\Api\Chat\Tools\CreateFlow',
    'method' => 'handle_tool_call',
    'description' => 'Create a flow instance from an existing pipeline',
    'parameters' => [
        'pipeline_id' => ['type' => 'integer', 'description' => 'Pipeline ID to instantiate'],
        'name' => ['type' => 'string', 'description' => 'Flow name']
    ]
]);
```

### `registerConfigurationHandlers(string $tool_id)`

Registers configuration management handlers for tools that require setup.

**Registers Filters:**
- `datamachine_tool_configured` - Configuration validation
- `datamachine_get_tool_config` - Configuration retrieval
- `datamachine_save_tool_config` - Configuration saving

### `registerSuccessMessageHandler(string $tool_name)`

Registers success message formatting handler for human-readable tool responses.

**Registers Filter:**
- `datamachine_tool_success_message` - Success message formatting

## Usage Patterns

### Global Tool Registration

```php
<?php
namespace DataMachine\Engine\AI\Tools\Global;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class GoogleSearch {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerGlobalTool('google_search', [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Search the web using Google Custom Search API',
            'parameters' => [
                'query' => ['type' => 'string', 'description' => 'Search query'],
                'site_restriction' => ['type' => 'string', 'description' => 'Optional site restriction']
            ]
        ]);

        $this->registerConfigurationHandlers('google_search');
        $this->registerSuccessMessageHandler('google_search');
    }

    public function handle_tool_call(array $parameters): array {
        // Tool implementation
    }

    public function check_configuration(): bool {
        // Configuration validation
    }

    public function get_configuration(): array {
        // Configuration retrieval
    }

    public function save_configuration(array $config): void {
        // Configuration saving
    }

    public function format_success_message(array $result, array $parameters): string {
        // Success message formatting
    }
}
```

### Chat-Only Tool Registration

```php
<?php
namespace DataMachine\Api\Chat\Tools;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class CreatePipeline {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerChatTool('create_pipeline', [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Create a new pipeline with optional steps',
            'parameters' => [
                'name' => ['type' => 'string', 'description' => 'Pipeline name'],
                'steps' => ['type' => 'array', 'description' => 'Optional initial steps']
            ]
        ]);
    }

    public function handle_tool_call(array $parameters): array {
        // Pipeline creation logic
    }
}
```

## Benefits

- **Code Reduction**: Eliminates ~50% of repetitive registration code across tool implementations
- **Consistency**: Ensures uniform registration patterns across all AI tools
- **Extensibility**: Supports unlimited agent types without code changes
- **Maintainability**: Centralized registration logic in reusable trait
- **Future-Proof**: Automatic support for new agent types through dynamic filter creation

## Integration with ToolManager

The ToolRegistrationTrait integrates seamlessly with the ToolManager system:

- **Filter-Based Discovery**: Registered tools are automatically discovered by ToolManager
- **Configuration Validation**: `check_configuration()` methods are called during tool enablement checks
- **Success Messaging**: `format_success_message()` methods provide human-readable responses

## Agent Type Support

The trait supports multiple agent types through dynamic filter creation:

- **global**: `datamachine_global_tools` - Available to all agents
- **chat**: `datamachine_chat_tools` - Chat agent specific
- **pipeline**: `datamachine_pipeline_tools` - Pipeline agent specific
- **frontend**: `datamachine_frontend_tools` - Frontend agent specific
- **Custom**: Any agent type automatically gets `datamachine_{type}_tools` filter

This architecture enables the AI system to scale to unlimited specialized agents while maintaining consistent tool registration patterns.</content>
</xai:function_call:>Write to file /Users/chubes/Developer/Data Machine Ecosystem/datamachine/docs/core-system/tool-registration-trait.md