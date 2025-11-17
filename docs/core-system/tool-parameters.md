# ToolParameters

**Location**: `/inc/Engine/AI/ToolParameters.php`

**Since**: v0.2.0 (Universal Engine Architecture)

**Namespace**: `DataMachine\Engine\AI`

## Overview

ToolParameters provides centralized parameter building infrastructure for AI tool execution, shared by both Chat and Pipeline agents. It creates unified parameter structures that combine AI-provided arguments, engine context, and handler configurations into a single flat array for tool handler consumption.

## Purpose

Eliminates duplicate parameter building logic across agents by centralizing the transformation of disparate parameter sources into standardized tool handler input.

## Core Concept

Tool handlers receive a single flat parameter array containing:
- **AI Parameters**: Arguments provided by the AI (e.g., `query`, `content`, `url`)
- **Context Parameters**: Agent-specific context (job_id for Pipeline, session_id for Chat)
- **Engine Parameters**: Workflow data (source_url, image_url from centralized filter)
- **Configuration**: Tool definition and handler configuration
- **Extracted Data**: Automatic content/title extraction from data packets

## Core Methods

### buildParameters()

Build unified flat parameter structure for tool execution.

**Signature**:
```php
public static function buildParameters(
    array $ai_tool_parameters,
    array $unified_parameters,
    array $tool_definition
): array
```

**Parameters**:
- `$ai_tool_parameters` (array) - Parameters from AI tool call
- `$unified_parameters` (array) - Context parameters with `data` array
- `$tool_definition` (array) - Tool definition with `parameters`, `handler_config`, `name`

**Returns**: Complete flat parameter array for tool handler

**Parameter Building Process**:
1. Start with unified context parameters
2. Extract `content` from data packet if tool definition requires it
3. Extract `title` from data packet if tool definition requires it
4. Add tool metadata (`tool_definition`, `tool_name`, `handler_config`)
5. Merge AI-provided parameters (overrides automatic extraction)

**Example**:
```php
// AI tool call
$ai_params = ['query' => 'WordPress plugins'];

// Unified context (Chat agent)
$unified = [
    'session_id' => 'session_123',
    'data' => []
];

// Tool definition
$tool_def = [
    'name' => 'google_search',
    'parameters' => ['query' => ['type' => 'string', 'required' => true]],
    'handler_config' => []
];

$params = ToolParameters::buildParameters($ai_params, $unified, $tool_def);

// Result:
// [
//     'session_id' => 'session_123',
//     'data' => [],
//     'content' => null,  // No content in data packet
//     'title' => null,    // No title in data packet
//     'tool_definition' => [...],
//     'tool_name' => 'google_search',
//     'handler_config' => [],
//     'query' => 'WordPress plugins'  // From AI
// ]
```

**Usage**: Base method for all parameter building (used by both buildForHandlerTool and direct calls)

---

### buildForHandlerTool()

Build parameters for handler tools with engine data integration.

**Signature**:
```php
public static function buildForHandlerTool(
    array $ai_tool_parameters,
    array $data,
    array $tool_definition,
    array $engine_parameters,
    array $handler_config
): array
```

**Parameters**:
- `$ai_tool_parameters` (array) - Parameters from AI tool call
- `$data` (array) - Data packets from previous pipeline steps
- `$tool_definition` (array) - Tool definition array
- `$engine_parameters` (array) - Engine data (source_url, image_url, etc.)
- `$handler_config` (array) - Handler configuration

**Returns**: Complete parameter array with engine data merged

**Engine Parameters**:
- `source_url` - URL of source content (for attribution or updates)
- `image_url` - URL of associated image
- `job_id` - Current job identifier
- `flow_step_id` - Current flow step identifier

**Example**:
```php
// AI wants to publish content
$ai_params = ['content' => 'My tweet about WordPress'];

// Data packets from pipeline
$data = [
    [
        'type' => 'ai',
        'content' => ['title' => 'WordPress Tips', 'body' => 'Great WordPress practices...'],
        'metadata' => ['source_type' => 'rss']
    ]
];

// Tool definition for twitter_publish
$tool_def = [
    'name' => 'twitter_publish',
    'parameters' => [
        'content' => ['type' => 'string', 'required' => true]
    ],
    'handler_config' => ['account_id' => '123']
];

// Engine parameters from centralized filter
$engine_params = [
    'source_url' => 'https://example.com/article',
    'image_url' => 'https://example.com/image.jpg',
    'job_id' => 'job_456',
    'flow_step_id' => 'step_789'
];

// Handler configuration
$handler_config = ['account_id' => '123'];

$params = ToolParameters::buildForHandlerTool(
    $ai_params,
    $data,
    $tool_def,
    $engine_params,
    $handler_config
);

// Result:
// [
//     'data' => [...],  // Full data packet array
//     'handler_config' => ['account_id' => '123'],
//     'content' => 'Great WordPress practices...',  // Extracted from data packet
//     'title' => 'WordPress Tips',  // Extracted from data packet
//     'tool_definition' => [...],
//     'tool_name' => 'twitter_publish',
//     'source_url' => 'https://example.com/article',  // From engine params
//     'image_url' => 'https://example.com/image.jpg',  // From engine params
//     'job_id' => 'job_456',  // From engine params
//     'flow_step_id' => 'step_789'  // From engine params
// ]
```

**Usage**: Exclusive to Pipeline agent's AIStep for handler tool execution

---

## Private Utilities

### extractContent()

Extract content from data packet if tool requires content parameter.

**Signature**:
```php
private static function extractContent(array $data_packet, array $tool_definition): ?string
```

**Extraction Logic**:
1. Check if tool definition includes `content` parameter
2. If yes, extract from `data_packet[0]['content']['body']`
3. If no, return null

**Data Packet Structure**:
```php
$data_packet = [
    [  // Latest entry (index 0)
        'type' => 'ai',
        'content' => [
            'title' => 'Post Title',
            'body' => 'Post content here...'  // <- Extracted as content
        ],
        'metadata' => [...]
    ]
];
```

**Example**:
```php
$data = [
    ['content' => ['body' => 'Article content', 'title' => 'Article Title']]
];

$tool_def = [
    'parameters' => ['content' => ['type' => 'string', 'required' => true]]
];

$content = ToolParameters::extractContent($data, $tool_def);
// Returns: "Article content"

// Tool without content parameter
$tool_def2 = [
    'parameters' => ['query' => ['type' => 'string', 'required' => true]]
];

$content2 = ToolParameters::extractContent($data, $tool_def2);
// Returns: null
```

---

### extractTitle()

Extract title from data packet if tool requires title parameter.

**Signature**:
```php
private static function extractTitle(array $data_packet, array $tool_definition): ?string
```

**Extraction Logic**:
1. Check if tool definition includes `title` parameter
2. If yes, extract from `data_packet[0]['content']['title']`
3. If no, return null

**Example**:
```php
$data = [
    ['content' => ['body' => 'Article content', 'title' => 'Article Title']]
];

$tool_def = [
    'parameters' => [
        'title' => ['type' => 'string', 'required' => true],
        'content' => ['type' => 'string', 'required' => true]
    ]
];

$title = ToolParameters::extractTitle($data, $tool_def);
// Returns: "Article Title"
```

---

## Integration with Universal Engine

### AIConversationLoop Integration

AIConversationLoop uses ToolParameters differently for each agent type:

**Chat Agent**:
```php
$unified_parameters = ['session_id' => $session_id, 'data' => []];
$params = ToolParameters::buildParameters(
    $ai_tool_parameters,
    $unified_parameters,
    $tool_definition
);
```

**Pipeline Agent (via AIStep)**:
```php
$params = ToolParameters::buildForHandlerTool(
    $ai_tool_parameters,
    $data,  // Data packets from previous steps
    $tool_definition,
    $engine_parameters,  // From datamachine_engine_data filter
    $handler_config
);
```

### ToolExecutor Integration

ToolExecutor calls ToolParameters before invoking tool handlers:

```php
// In ToolExecutor::executeTool()

// Build parameters based on tool type
if ($is_handler_tool) {
    $parameters = ToolParameters::buildForHandlerTool(
        $ai_parameters,
        $data,
        $tool_definition,
        $engine_params,
        $handler_config
    );
} else {
    $unified = ['session_id' => $session_id, 'data' => $data];
    $parameters = ToolParameters::buildParameters(
        $ai_parameters,
        $unified,
        $tool_definition
    );
}

// Execute tool handler with built parameters
$result = call_user_func([$class, $method], $parameters, $tool_definition);
```

### Handler Tool Pattern

Publish/Update handlers receive parameters from ToolParameters:

```php
class TwitterHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Access AI parameters
        $tweet_content = $parameters['content'] ?? '';

        // Access engine data (from ToolParameters::buildForHandlerTool)
        $source_url = $parameters['source_url'] ?? null;
        $image_url = $parameters['image_url'] ?? null;
        $job_id = $parameters['job_id'] ?? null;

        // Access handler configuration
        $handler_config = $parameters['handler_config'] ?? [];
        $account_id = $handler_config['account_id'] ?? null;

        // Publish tweet...

        return ['success' => true, 'data' => ['id' => $tweet_id, 'url' => $tweet_url]];
    }
}
```

---

## Parameter Flow Architecture

### Chat Agent Flow

```
User Message
    ↓
AI Response (tool call: google_search with query="WordPress")
    ↓
ToolExecutor::executeTool()
    ↓
ToolParameters::buildParameters(
    ['query' => 'WordPress'],  // From AI
    ['session_id' => 'session_123', 'data' => []],  // Unified context
    $tool_definition
)
    ↓
Google Search Handler receives:
    [
        'session_id' => 'session_123',
        'query' => 'WordPress',
        'tool_name' => 'google_search',
        'tool_definition' => [...],
        ...
    ]
```

### Pipeline Agent Flow

```
Pipeline Step Execution (AI Step with handler tools)
    ↓
AI Response (tool call: twitter_publish with content="Tweet")
    ↓
AIStep retrieves engine data via datamachine_engine_data filter:
    ['source_url' => 'https://...', 'image_url' => 'https://...', 'job_id' => '...']
    ↓
ToolExecutor::executeTool()
    ↓
ToolParameters::buildForHandlerTool(
    ['content' => 'Tweet'],  // From AI (may be overridden)
    $data,  // Data packets from previous steps
    $tool_definition,
    $engine_parameters,  // Retrieved above
    $handler_config
)
    ↓
Automatic content extraction from data[0]['content']['body']:
    'content' => 'Actual article content...'  // Overrides AI parameter if empty
    ↓
Twitter Handler receives:
    [
        'data' => [...],  // Full data packets
        'content' => 'Actual article content...',  // Extracted + merged
        'title' => 'Article Title',  // Extracted
        'source_url' => 'https://...',  // From engine
        'image_url' => 'https://...',  // From engine
        'job_id' => '...',  // From engine
        'flow_step_id' => '...',  // From engine
        'handler_config' => [...],
        'tool_name' => 'twitter_publish',
        ...
    ]
```

---

## Architecture Principles

### Unified Parameter Structure

Single flat array containing all parameter types:
- **Benefit**: Handlers access parameters via simple array access
- **Pattern**: No nested structures for common parameters
- **Consistency**: Same structure regardless of agent type

### Automatic Data Extraction

Content/title automatically extracted from data packets:
- **Benefit**: AI doesn't need to pass full content in tool calls
- **Pattern**: Check tool definition for required parameters, extract if present
- **Override**: AI can still provide explicit content/title parameters

### Engine Data Integration

Handler tools automatically receive engine data:
- **source_url**: For attribution (publish) or identification (update)
- **image_url**: For media handling
- **job_id**: For tracking and logging
- **flow_step_id**: For deduplication and state management

### Centralized Building Logic

All parameter construction happens in ToolParameters:
- **Benefit**: No duplicate building code across agents
- **Pattern**: Static methods for stateless transformations
- **Extensibility**: Single location for parameter structure changes

---

## Usage Examples

### Global Tool (Chat Agent)

```php
// AI calls google_search tool
$ai_params = ['query' => 'WordPress best practices', 'num_results' => 5];

$unified = [
    'session_id' => 'session_abc123',
    'data' => []
];

$tool_def = [
    'name' => 'google_search',
    'parameters' => [
        'query' => ['type' => 'string', 'required' => true],
        'num_results' => ['type' => 'integer', 'required' => false]
    ],
    'requires_config' => true,
    'handler_config' => []
];

$params = ToolParameters::buildParameters($ai_params, $unified, $tool_def);

// Handler receives:
// [
//     'session_id' => 'session_abc123',
//     'data' => [],
//     'content' => null,
//     'title' => null,
//     'tool_definition' => [...],
//     'tool_name' => 'google_search',
//     'handler_config' => [],
//     'query' => 'WordPress best practices',
//     'num_results' => 5
// ]
```

### Handler Tool (Pipeline Agent)

```php
// AI calls wordpress_publish tool
$ai_params = [];  // Empty - content will be extracted

$data = [
    [
        'type' => 'ai',
        'content' => [
            'title' => 'WordPress Security Tips',
            'body' => 'Here are 10 essential WordPress security practices...'
        ],
        'metadata' => ['source_type' => 'rss']
    ]
];

$tool_def = [
    'name' => 'wordpress_publish',
    'parameters' => [
        'content' => ['type' => 'string', 'required' => true],
        'title' => ['type' => 'string', 'required' => false]
    ],
    'handler_config' => ['post_type' => 'post', 'post_status' => 'draft']
];

$engine_params = [
    'source_url' => 'https://techblog.com/security-article',
    'image_url' => 'https://techblog.com/security-image.jpg',
    'job_id' => 'job_789',
    'flow_step_id' => 'step_publish_456'
];

$handler_config = ['post_type' => 'post', 'post_status' => 'draft'];

$params = ToolParameters::buildForHandlerTool(
    $ai_params,
    $data,
    $tool_def,
    $engine_params,
    $handler_config
);

// Handler receives:
// [
//     'data' => [...],  // Full data packet array
//     'handler_config' => ['post_type' => 'post', 'post_status' => 'draft'],
//     'content' => 'Here are 10 essential WordPress security practices...',  // Extracted
//     'title' => 'WordPress Security Tips',  // Extracted
//     'tool_definition' => [...],
//     'tool_name' => 'wordpress_publish',
//     'source_url' => 'https://techblog.com/security-article',
//     'image_url' => 'https://techblog.com/security-image.jpg',
//     'job_id' => 'job_789',
//     'flow_step_id' => 'step_publish_456'
// ]
```

### Chat Tool (Chat Agent)

```php
// AI calls make_api_request tool
$ai_params = [
    'endpoint' => '/datamachine/v1/pipelines',
    'method' => 'POST',
    'data' => ['pipeline_name' => 'My New Pipeline']
];

$unified = [
    'session_id' => 'session_xyz789',
    'data' => []
];

$tool_def = [
    'name' => 'make_api_request',
    'parameters' => [
        'endpoint' => ['type' => 'string', 'required' => true],
        'method' => ['type' => 'string', 'required' => true],
        'data' => ['type' => 'object', 'required' => false]
    ],
    'handler_config' => []
];

$params = ToolParameters::buildParameters($ai_params, $unified, $tool_def);

// MakeAPIRequest handler receives:
// [
//     'session_id' => 'session_xyz789',
//     'data' => [],
//     'content' => null,
//     'title' => null,
//     'tool_definition' => [...],
//     'tool_name' => 'make_api_request',
//     'handler_config' => [],
//     'endpoint' => '/datamachine/v1/pipelines',
//     'method' => 'POST',
//     'data' => ['pipeline_name' => 'My New Pipeline']
// ]
```

---

## Related Components

- [ToolExecutor](tool-execution.md) - Uses ToolParameters before invoking tool handlers
- [AIConversationLoop](ai-conversation-loop.md) - Calls ToolExecutor with proper context for parameter building
- [Universal Engine Architecture](universal-engine.md) - Shared infrastructure overview
- [Engine Data Architecture](../api-reference/engine-filters.md) - Centralized filter access for source_url/image_url

---

**Location**: `/inc/Engine/AI/ToolParameters.php`
**Namespace**: `DataMachine\Engine\AI`
**Type**: Static utility class
**Since**: v0.2.0
