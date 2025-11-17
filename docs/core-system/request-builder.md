# RequestBuilder Pattern

**File**: `/inc/Engine/AI/RequestBuilder.php`
**Since**: 0.2.0

Centralized AI request construction ensuring consistent request structure across Pipeline AI and Chat API agents. Single source of truth for building standardized AI requests to prevent architectural drift between agent types.

## Purpose

The `RequestBuilder` class consolidates all AI request building logic into a single, unified interface. This prevents behavioral differences between Pipeline and Chat agents by ensuring both use identical request construction, tool formatting, and directive application patterns.

**Critical Rule**: Never call `ai-http-client` directly. Always use `RequestBuilder::build()` to ensure consistent request structure and directive application.

## Architecture

```
Request Building Flow:
┌─────────────────────────────────────────────────────┐
│              RequestBuilder::build()                │
│                                                      │
│  1. Initialize Request                              │
│     • Set model and messages                        │
│                                                      │
│  2. Restructure Tools                               │
│     • Normalize tool definitions                    │
│     • Ensure provider compatibility                 │
│                                                      │
│  3. Apply Global Directives                         │
│     • datamachine_global_directives filter          │
│     • All AI agents receive these                   │
│                                                      │
│  4. Apply Agent Directives                          │
│     • datamachine_agent_directives filter           │
│     • Pipeline or Chat specific directives          │
│                                                      │
│  5. Send to ai-http-client                          │
│     • chubes_ai_request filter                      │
│     • Returns standardized AI response              │
└─────────────────────────────────────────────────────┘
```

## Usage

### Basic Usage

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

### Pipeline Agent Example

```php
use DataMachine\Engine\AI\RequestBuilder;

// Build context for pipeline agent
$context = [
    'step_id' => $flow_step_id,
    'payload' => [
        'job_id' => $job_id,
        'flow_step_id' => $flow_step_id,
        'data' => $data,
        'flow_step_config' => $flow_step_config,
        'engine_data' => $engine_data
    ]
];

// Build AI request
$ai_response = RequestBuilder::build(
    $messages,
    'openai',
    'gpt-4',
    $tools,
    'pipeline',
    $context
);

// Check response
if ($ai_response['success']) {
    $tool_calls = $ai_response['data']['tool_calls'] ?? [];
    $content = $ai_response['data']['content'] ?? '';
} else {
    $error = $ai_response['error'] ?? 'Unknown error';
}
```

### Chat Agent Example

```php
use DataMachine\Engine\AI\RequestBuilder;

// Build context for chat agent
$context = [
    'session_id' => $session_id
];

// Build AI request
$ai_response = RequestBuilder::build(
    $messages,
    'anthropic',
    'claude-3-5-sonnet-20241022',
    $tools,
    'chat',
    $context
);

// Check response
if ($ai_response['success']) {
    $content = $ai_response['data']['content'] ?? '';
    $tool_calls = $ai_response['data']['tool_calls'] ?? [];
}
```

## Directive Application System

The RequestBuilder applies directives in a specific hierarchical order to ensure consistent behavior across all agents.

### Directive Hierarchy

```
1. Global Directives (datamachine_global_directives)
   ↓ Applies to ALL AI agents

2. Agent Directives (datamachine_agent_directives)
   ↓ Pipeline-specific OR Chat-specific

3. Type-Specific Directives (legacy compatibility)
   • datamachine_pipeline_directives (pipeline only)
   • datamachine_chat_directives (chat only)
```

### Global Directives

Applied to all AI agents (pipeline + chat) via `datamachine_global_directives` filter:

```php
$request = apply_filters(
    'datamachine_global_directives',
    $request,
    $provider,
    $structured_tools,
    $context['step_id'] ?? null,
    $context['payload'] ?? []
);
```

**Implementation Example**:
```php
add_filter('datamachine_global_directives', function($request, $provider, $tools, $step_id, $payload) {
    // Add global system directive
    $request['messages'][] = [
        'role' => 'system',
        'content' => 'You are an AI agent in the Data Machine system.'
    ];
    return $request;
}, 10, 5);
```

### Agent Directives

Applied based on agent type via `datamachine_agent_directives` filter:

```php
$request = apply_filters(
    'datamachine_agent_directives',
    $request,
    $agent_type,      // 'pipeline' or 'chat'
    $provider,
    $structured_tools,
    $context
);
```

**Pipeline Implementation Example**:
```php
add_filter('datamachine_agent_directives', function($request, $agent_type, $provider, $tools, $context) {
    if ($agent_type === 'pipeline') {
        // Add pipeline-specific directives
        $request = PipelineCoreDirective::inject($request, $provider, $tools, $context['step_id'] ?? null, $context['payload'] ?? []);
        $request = PipelineSystemPromptDirective::inject($request, $provider, $tools, $context['step_id'] ?? null, $context['payload'] ?? []);
    }
    return $request;
}, 10, 5);
```

**Chat Implementation Example**:
```php
add_filter('datamachine_agent_directives', function($request, $agent_type, $provider, $tools, $context) {
    if ($agent_type === 'chat') {
        // Add chat-specific directives
        $request = ChatAgentDirective::inject($request, $provider, $tools, $context);
    }
    return $request;
}, 10, 5);
```

**Current Directive Implementations**:

**Pipeline Directives**:
- `PipelineCoreDirective` - Foundational agent identity and tool instructions (priority 10)
- `PipelineSystemPromptDirective` - User-defined system prompts (priority 20)
- `PipelineContextDirective` - WordPress site context (priority 30)

**Chat Directives**:
- `ChatAgentDirective` - Chat agent identity and capabilities

## Tool Restructuring

The RequestBuilder normalizes tool definitions to ensure consistent structure across all providers:

```php
private static function restructure_tools(array $raw_tools): array
{
    $structured = [];

    foreach ($raw_tools as $tool_name => $tool_config) {
        $structured[$tool_name] = [
            'name' => $tool_name,
            'description' => $tool_config['description'] ?? '',
            'parameters' => $tool_config['parameters'] ?? [],
            'handler' => $tool_config['handler'] ?? null,
            'handler_config' => $tool_config['handler_config'] ?? []
        ];
    }

    return $structured;
}
```

**Purpose**: Ensures all tools have explicit `name`, `description`, `parameters`, `handler`, and `handler_config` fields, preventing tool format mismatches with AI providers.

**Example**:

**Input** (raw tool from filter):
```php
$tools['twitter_publish'] = [
    'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\Twitter\\Twitter',
    'method' => 'handle_tool_call',
    'handler' => 'twitter',
    'description' => 'Post content to Twitter',
    'parameters' => [...]
];
```

**Output** (structured tool):
```php
$structured_tools['twitter_publish'] = [
    'name' => 'twitter_publish',
    'description' => 'Post content to Twitter',
    'parameters' => [...],
    'handler' => 'twitter',
    'handler_config' => []
];
```

## Integration with ai-http-client

The RequestBuilder sends the finalized request to the ai-http-client library via the `chubes_ai_request` filter:

```php
return apply_filters(
    'chubes_ai_request',
    $request,
    $provider,
    null, // streaming_callback
    $structured_tools,
    $context['step_id'] ?? $context['session_id'] ?? null,
    [
        'agent_type' => $agent_type,
        'context' => $context
    ]
);
```

**Parameters**:
- `$request` - Complete request array (model, messages, tools)
- `$provider` - AI provider name (openai, anthropic, google, grok, openrouter)
- `null` - Streaming callback (not used in current implementation)
- `$structured_tools` - Restructured tools array
- `$context['step_id'] ?? $context['session_id']` - Identifier for logging
- `['agent_type' => ..., 'context' => ...]` - Additional metadata

**Response Structure**:
```php
[
    'success' => true,
    'data' => [
        'content' => 'AI text response',
        'tool_calls' => [
            [
                'name' => 'tool_name',
                'parameters' => [...]
            ]
        ]
    ],
    'provider' => 'openai',
    'model' => 'gpt-4',
    'error' => 'Error message if success=false'
]
```

## Context Parameter

The `$context` array provides information to directives and the ai-http-client:

### Pipeline Context

```php
$context = [
    'step_id' => $flow_step_id,        // Used for logging and directive identification
    'payload' => [                      // Complete step execution context
        'job_id' => $job_id,
        'flow_step_id' => $flow_step_id,
        'data' => $data,
        'flow_step_config' => $flow_step_config,
        'engine_data' => $engine_data
    ]
];
```

**Available to Directives**:
- `$context['step_id']` - Flow step identifier
- `$context['payload']['job_id']` - Job execution ID
- `$context['payload']['data']` - Data packets from previous steps
- `$context['payload']['flow_step_config']` - Step configuration
- `$context['payload']['engine_data']` - Engine parameters (source_url, image_url)

### Chat Context

```php
$context = [
    'session_id' => $session_id  // Chat session identifier
];
```

**Available to Directives**:
- `$context['session_id']` - Chat session ID for persistence

## Response Format

The RequestBuilder returns a standardized response structure:

### Success Response

```php
[
    'success' => true,
    'data' => [
        'content' => 'AI generated text response',
        'tool_calls' => [
            [
                'name' => 'tool_name',
                'parameters' => [
                    'param1' => 'value1',
                    'param2' => 'value2'
                ]
            ]
        ]
    ],
    'provider' => 'openai',
    'model' => 'gpt-4'
]
```

### Error Response

```php
[
    'success' => false,
    'error' => 'Descriptive error message',
    'provider' => 'anthropic',
    'model' => 'claude-3-5-sonnet-20241022'
]
```

## Error Handling

### Provider Errors

AI provider errors (API failures, rate limits, invalid credentials) are returned in the error response:

```php
$ai_response = RequestBuilder::build(...);

if (!$ai_response['success']) {
    $error = $ai_response['error'] ?? 'Unknown error';
    $provider = $ai_response['provider'] ?? 'Unknown';

    do_action('datamachine_log', 'error', 'RequestBuilder: AI request failed', [
        'provider' => $provider,
        'error' => $error
    ]);
}
```

### Configuration Errors

Missing or invalid configuration (model not set, provider not configured) are handled by ai-http-client:

```php
[
    'success' => false,
    'error' => 'Invalid provider configuration'
]
```

## Logging

The RequestBuilder logs request building details:

```php
do_action('datamachine_log', 'debug', 'RequestBuilder: Built AI request', [
    'agent_type' => $agent_type,
    'provider' => $provider,
    'model' => $model,
    'message_count' => count($request['messages']),
    'tool_count' => count($structured_tools)
]);
```

**Log Entry**:
```
RequestBuilder: Built AI request
- agent_type: pipeline
- provider: openai
- model: gpt-4
- message_count: 5
- tool_count: 3
```

## Best Practices

### Always Use RequestBuilder

**Correct**:
```php
use DataMachine\Engine\AI\RequestBuilder;

$ai_response = RequestBuilder::build(
    $messages,
    $provider,
    $model,
    $tools,
    $agent_type,
    $context
);
```

**Incorrect** (bypasses directive system and tool restructuring):
```php
// NEVER DO THIS
$ai_response = apply_filters('chubes_ai_request', $request, $provider, null, $tools);
```

### Provide Complete Context

**Pipeline Agent**:
```php
$context = [
    'step_id' => $flow_step_id,
    'payload' => [
        'job_id' => $job_id,
        'flow_step_id' => $flow_step_id,
        'data' => $data,
        'flow_step_config' => $flow_step_config,
        'engine_data' => $engine_data
    ]
];
```

**Chat Agent**:
```php
$context = [
    'session_id' => $session_id
];
```

### Handle Errors Gracefully

```php
$ai_response = RequestBuilder::build(...);

if (!$ai_response['success']) {
    do_action('datamachine_log', 'error', 'AI request failed', [
        'error' => $ai_response['error'],
        'provider' => $ai_response['provider']
    ]);

    // Return error to caller or retry
    return [
        'success' => false,
        'error' => $ai_response['error']
    ];
}

// Process successful response
$content = $ai_response['data']['content'] ?? '';
$tool_calls = $ai_response['data']['tool_calls'] ?? [];
```

### Directive Application Order

Directives are applied in hierarchical order. Ensure your custom directives use appropriate filter priorities:

```php
// Global directives (priority 10)
add_filter('datamachine_global_directives', function($request, ...) {
    // Applied to ALL agents
    return $request;
}, 10, 5);

// Agent-specific directives (priority 10-40)
add_filter('datamachine_agent_directives', function($request, $agent_type, ...) {
    if ($agent_type === 'pipeline') {
        // Pipeline directives at various priorities
        $request = PipelineCoreDirective::inject(...);      // Priority 10
        $request = PipelineSystemPromptDirective::inject(...); // Priority 20
    }
    return $request;
}, 10, 5);
```

## Related Components

- [Universal Engine Architecture](/docs/core-system/universal-engine.md) - Overall engine structure
- [AI Conversation Loop](/docs/core-system/ai-conversation-loop.md) - Uses RequestBuilder for AI requests
- [Tool Execution Architecture](/docs/core-system/tool-execution.md) - Tool discovery and execution
- [Universal Engine Filters](/docs/api-reference/engine-filters.md) - Complete filter reference
