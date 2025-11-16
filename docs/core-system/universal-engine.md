# Universal Engine Architecture

**Since**: 0.2.0

The Universal Engine is a shared AI infrastructure layer that provides consistent request building, tool execution, and conversation management for both Pipeline AI and Chat API agents in Data Machine.

## Overview

Prior to v0.2.0, Pipeline AI and Chat agents maintained separate implementations of conversation loops, tool execution, and request building. This architectural duplication created maintenance overhead and potential behavioral drift between agent types.

The Universal Engine consolidates this shared functionality into a centralized layer at `/inc/Engine/AI/`, enabling both agent types to leverage identical AI infrastructure while maintaining their specialized behaviors through filter-based integration.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│          Universal Engine (/inc/Engine/AI/)         │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ AIConversationLoop│      │ RequestBuilder   │   │
│  │ Multi-turn loops │      │ Centralized AI   │   │
│  │ Tool coordination│      │ request building │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ ToolExecutor     │      │ ToolParameters   │   │
│  │ Tool discovery   │      │ Parameter        │   │
│  │ Tool execution   │      │ building         │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────────────────────────────────┐  │
│  │ ConversationManager                          │  │
│  │ Message formatting and validation utilities  │  │
│  └──────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
                        │
        ┌───────────────┴───────────────┐
        │                               │
        ▼                               ▼
┌──────────────────┐          ┌──────────────────┐
│  Pipeline Agent  │          │    Chat Agent    │
│  (/inc/Core/     │          │   (/inc/Api/     │
│   Steps/AI/)     │          │    Chat/)        │
│                  │          │                  │
│ • PipelineCore   │          │ • ChatAgent      │
│   Directive      │          │   Directive      │
│ • Pipeline       │          │ • MakeAPIRequest │
│   SystemPrompt   │          │   tool           │
│   Directive      │          │ • Session        │
│ • PipelineContext│          │   management     │
│   Directive      │          │                  │
│ • ToolDefinitions│          │                  │
│   Directive      │          │                  │
└──────────────────┘          └──────────────────┘
```

## Core Components

### AIConversationLoop

**File**: `/inc/Engine/AI/AIConversationLoop.php`

Executes multi-turn AI conversations with automatic tool execution and result feedback. Handles conversation flow control, turn counting, and completion detection.

**Key Features**:
- Multi-turn conversation execution with configurable max turns (default: 8)
- Automatic tool call detection and execution via ToolExecutor
- Completion detection (conversation ends when AI returns no tool calls)
- Turn-based state tracking and comprehensive logging
- Duplicate tool call prevention via ConversationManager validation

**Usage**:
```php
use DataMachine\Engine\AI\AIConversationLoop;

$loop = new AIConversationLoop();
$result = $loop->execute(
    $messages,        // Initial conversation messages
    $tools,           // Available tools for AI
    $provider,        // AI provider (openai, anthropic, etc.)
    $model,           // AI model identifier
    $agent_type,      // 'pipeline' or 'chat'
    $context,         // Agent-specific context data
    $max_turns        // Maximum conversation turns (default: 8)
);

// Result structure:
// [
//     'messages' => [],         // Final conversation state
//     'final_content' => '',    // Last AI text response
//     'turn_count' => 0,        // Number of turns executed
//     'completed' => false,     // Whether loop finished naturally
//     'last_tool_calls' => []   // Last set of tool calls (if any)
// ]
```

### ToolExecutor

**File**: `/inc/Engine/AI/ToolExecutor.php`

Universal tool discovery and execution infrastructure. Handles tool registration filtering, enablement checking, and execution with comprehensive error handling.

**Key Features**:
- Tool discovery via filter-based registration (`chubes_ai_tools`, `datamachine_global_tools`, `datamachine_chat_tools`)
- Tool enablement control via `datamachine_tool_enabled` filter
- Configuration validation via `datamachine_tool_configured` filter
- Parameter building integration with ToolParameters
- Exception handling with detailed error logging

**Tool Discovery**:
```php
use DataMachine\Engine\AI\ToolExecutor;

// Get tools for pipeline agent (with previous/next step context)
$tools = ToolExecutor::getAvailableTools(
    $previous_step_config,    // Previous step configuration
    $next_step_config,        // Next step configuration
    $current_pipeline_step_id // Current pipeline step ID
);

// Get tools for chat agent (global tools only)
$tools = ToolExecutor::getAvailableTools(null, null, null);
```

**Tool Execution**:
```php
$result = ToolExecutor::executeTool(
    $tool_name,           // Tool name to execute
    $tool_parameters,     // Parameters from AI
    $available_tools,     // Available tools array
    $data,                // Data packets (empty for chat)
    $flow_step_id,        // Flow step ID (null for chat)
    $unified_parameters   // Unified parameters (session_id or job_id + engine_data)
);
```

### ToolParameters

**File**: `/inc/Engine/AI/ToolParameters.php`

Centralized parameter building for AI tool execution. Merges AI-provided parameters with engine context to create complete parameter sets for tool handlers.

**Key Features**:
- Unified flat parameter structure for all tools
- Automatic content/title extraction from data packets
- Engine data integration (source_url, image_url, etc.)
- Support for both standard tools and handler-specific tools

**Standard Parameter Building**:
```php
use DataMachine\Engine\AI\ToolParameters;

$complete_parameters = ToolParameters::buildParameters(
    $ai_tool_parameters,   // Parameters from AI
    $unified_parameters,   // Unified context (session_id or job_id + engine_data)
    $tool_definition       // Tool definition array
);
```

**Handler Tool Parameter Building**:
```php
$complete_parameters = ToolParameters::buildForHandlerTool(
    $ai_tool_parameters,   // Parameters from AI
    $data,                 // Data packets
    $tool_definition,      // Tool definition array
    $engine_parameters,    // Engine parameters (source_url, image_url)
    $handler_config        // Handler configuration
);
```

### ConversationManager

**File**: `/inc/Engine/AI/ConversationManager.php`

Message formatting and validation utilities for standardized conversation management. All methods are static with no state management.

**Key Features**:
- Standardized message structure (`role` + `content`)
- Tool call message formatting with turn tracking
- Tool result message formatting with success/failure handling
- Duplicate tool call detection and prevention
- Extensible success message generation via `datamachine_tool_success_message` filter

**Message Building**:
```php
use DataMachine\Engine\AI\ConversationManager;

// Build conversation message
$message = ConversationManager::buildConversationMessage('user', 'Message content');

// Format tool call message
$tool_call_message = ConversationManager::formatToolCallMessage(
    $tool_name,
    $tool_parameters,
    $turn_count
);

// Format tool result message
$tool_result_message = ConversationManager::formatToolResultMessage(
    $tool_name,
    $tool_result,
    $tool_parameters,
    $is_handler_tool,
    $turn_count
);
```

**Tool Call Validation**:
```php
$validation_result = ConversationManager::validateToolCall(
    $tool_name,
    $tool_parameters,
    $conversation_messages
);

if ($validation_result['is_duplicate']) {
    $correction_message = ConversationManager::generateDuplicateToolCallMessage($tool_name);
    $messages[] = $correction_message;
}
```

### RequestBuilder

**File**: `/inc/Engine/AI/RequestBuilder.php`

Centralized AI request construction ensuring consistent request structure across all agent types. Single source of truth for building standardized AI requests to prevent architectural drift.

**Key Features**:
- Standardized request structure for all AI providers
- Tool restructuring for provider compatibility
- Directive application hierarchy (global → agent → type-specific)
- Integration with ai-http-client via `chubes_ai_request` filter
- Context-aware request building for pipeline and chat agents

**Usage**:
```php
use DataMachine\Engine\AI\RequestBuilder;

$ai_response = RequestBuilder::build(
    $messages,      // Messages array with role/content
    $provider,      // AI provider name (openai, anthropic, etc.)
    $model,         // Model identifier
    $tools,         // Raw tools array from filters
    $agent_type,    // 'chat' or 'pipeline'
    $context        // Agent-specific context (session_id, step_id, payload, etc)
);
```

**Critical Rule**: Never call `ai-http-client` directly. Always use `RequestBuilder::build()` to ensure consistent request structure and directive application.

## Dual-Agent System

The Universal Engine supports two distinct agent types with specialized behaviors:

### Pipeline Agent

**Location**: `/inc/Core/Steps/AI/`

**Characteristics**:
- Executes within structured pipeline workflows
- Receives data packets from previous steps
- Has access to handler tools for immediate next step
- Uses pipeline-specific directives (PipelineCoreDirective, PipelineSystemPromptDirective, PipelineContextDirective, ToolDefinitionsDirective)
- Operates with job_id and flow_step_id context

**Directive Registration**:
```php
// Pipeline directives register via datamachine_agent_directives filter
add_filter('datamachine_agent_directives', function($request, $agent_type, $provider, $tools, $context) {
    if ($agent_type === 'pipeline') {
        $request = PipelineCoreDirective::inject($request, $provider, $tools, $context['step_id'] ?? null, $context['payload'] ?? []);
    }
    return $request;
}, 10, 5);
```

### Chat Agent

**Location**: `/inc/Api/Chat/`

**Characteristics**:
- Conversational interface for workflow building
- Session-based persistence via `wp_datamachine_chat_sessions` table
- Has access to global tools plus chat-specific tools (MakeAPIRequest)
- Uses chat-specific directive (ChatAgentDirective)
- Operates with session_id context

**Directive Registration**:
```php
// Chat directives register via datamachine_agent_directives filter
add_filter('datamachine_agent_directives', function($request, $agent_type, $provider, $tools, $context) {
    if ($agent_type === 'chat') {
        $request = ChatAgentDirective::inject($request, $provider, $tools, $context);
    }
    return $request;
}, 10, 5);
```

## Filter-Based Integration

The Universal Engine uses WordPress filters for extensible integration:

### Directive Filters

```php
// Global directives (all AI agents)
add_filter('datamachine_global_directives', function($request, $provider, $tools, $step_id, $payload) {
    // Inject global system directives
    return $request;
}, 10, 5);

// Agent-specific directives (universal system - agents implement via filter)
add_filter('datamachine_agent_directives', function($request, $agent_type, $provider, $tools, $context) {
    if ($agent_type === 'pipeline') {
        // Inject pipeline directives
    } elseif ($agent_type === 'chat') {
        // Inject chat directives
    }
    return $request;
}, 10, 5);

// Pipeline-only directives (legacy compatibility)
add_filter('datamachine_pipeline_directives', function($request, $provider, $tools, $step_id, $payload) {
    // Inject pipeline-specific directives
    return $request;
}, 10, 5);

// Chat-only directives (legacy compatibility)
add_filter('datamachine_chat_directives', function($request, $provider, $tools, $context) {
    // Inject chat-specific directives
    return $request;
}, 10, 4);
```

### Tool Enablement Filters

```php
// Tool enablement control (universal)
add_filter('datamachine_tool_enabled', function($enabled, $tool_name, $tool_config, $context_id) {
    // $context_id = pipeline_step_id for pipeline, null for chat
    return $enabled;
}, 10, 4);

// Tool configuration validation
add_filter('datamachine_tool_configured', function($configured, $tool_name) {
    // Check if tool has required configuration
    return $configured;
}, 10, 2);
```

## Benefits

### Code Reuse

- Single implementation of conversation loops eliminates duplication
- Shared tool execution logic ensures consistent behavior
- Centralized request building prevents architectural drift

### Maintainability

- Bug fixes in Universal Engine benefit both agent types
- Feature additions automatically available to all agents
- Clear separation between shared infrastructure and agent-specific logic

### Extensibility

- Filter-based architecture enables custom directives
- Tool registration patterns support third-party extensions
- Agent-specific behaviors implemented via WordPress filters

### Consistency

- Identical request structure for all AI providers
- Standardized message formatting across agent types
- Uniform tool execution patterns throughout system

## Migration from 0.1.x

**Deprecated Classes**:
- `AIStepConversationManager` - Replaced by `AIConversationLoop` + `ConversationManager`
- `AIStepToolParameters` - Replaced by `ToolParameters`

**New Architecture**:
- All AI request building now uses `RequestBuilder::build()`
- Tool execution centralized in `ToolExecutor::executeTool()`
- Conversation management split into loop execution (`AIConversationLoop`) and message utilities (`ConversationManager`)

**Migration Path**:
- Update custom directives to use `datamachine_agent_directives` filter
- Replace direct ai-http-client calls with `RequestBuilder::build()`
- Use `ToolExecutor` for tool discovery and execution

## Related Documentation

- [AI Conversation Loop](/docs/core-system/ai-conversation-loop.md) - Multi-turn conversation execution
- [Tool Execution Architecture](/docs/core-system/tool-execution.md) - Tool discovery and execution
- [RequestBuilder Pattern](/docs/core-system/request-builder.md) - Centralized request building
- [Universal Engine Filters](/docs/api-reference/engine-filters.md) - Filter hook reference
- [Chat API](/docs/api/chat.md) - Chat agent implementation
