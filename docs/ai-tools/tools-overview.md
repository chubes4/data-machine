# AI Tools Overview

AI tools provide capabilities to AI agents for interacting with external services, processing data, and performing research tasks. Data Machine supports both global tools and handler-specific tools.

## Tool Categories

### Global Tools (Universal)

Available to all AI agents (pipeline + chat) via `datamachine_global_tools` filter:

**Google Search** (`google_search`)
- **Purpose**: Search Google and return structured JSON results with titles, links, and snippets from external websites. Use for external information, current events, and fact-checking.
- **Configuration**: API key + Custom Search Engine ID required
- **Use Cases**: Fact-checking, research, external context gathering

**Local Search** (`local_search`)
- **Purpose**: Search this WordPress site and return structured JSON results with post titles, excerpts, permalinks, and metadata. Use ONCE to find existing content before creating new content.
- **Configuration**: None required (uses WordPress core)
- **Use Cases**: Content discovery, internal link suggestions, avoiding duplicate content

**WebFetch** (`web_fetch`)
- **Purpose**: Fetch and extract readable content from web pages. Use after Google Search to retrieve full article content. Returns page title and cleaned text content from any HTTP/HTTPS URL.
- **Configuration**: None required
- **Features**: 50K character limit, HTML processing, URL validation
- **Use Cases**: Web content analysis, reference material extraction, competitive research

**WordPress Post Reader** (`wordpress_post_reader`)
- **Purpose**: Read full WordPress post content by URL for detailed analysis
- **Configuration**: None required
- **Features**: Complete post content retrieval, optional custom fields inclusion
- **Use Cases**: Content analysis before WordPress Update operations, detailed post examination after Local Search


### Chat-Specific Tools

Available only to chat AI agents via `datamachine_chat_tools` filter:

**MakeAPIRequest** (`make_api_request`)
- **Purpose**: Execute Data Machine REST API operations from chat conversations
- **Configuration**: None required
- **Use Cases**: Conversational pipeline/flow creation, workflow execution, system queries

### Handler-Specific Tools

Available only when next step matches the handler type, registered via `chubes_ai_tools` filter:

**Publishing Tools**:
- `twitter_publish` - Post to Twitter (280 char limit)
- `bluesky_publish` - Post to Bluesky (300 char limit)  
- `facebook_publish` - Post to Facebook (no limit)
- `threads_publish` - Post to Threads (500 char limit)
- `wordpress_publish` - Create WordPress posts
- `google_sheets_publish` - Add data to Google Sheets

**Update Tools**:
- `wordpress_update` - Modify existing WordPress content

## Tool Architecture

### Registration System

**Global Tools** (available to all AI agents - pipeline + chat):
```php
// Registered via datamachine_global_tools filter
add_filter('datamachine_global_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Engine\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for information',
        'requires_config' => true,
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query'
            ]
        ]
    ];
    return $tools;
}, 10, 1);
```

**File Locations**: Global tools are located in `inc/Engine/AI/Tools/`:
- `GoogleSearch.php` - Web search with site restriction
- `LocalSearch.php` - WordPress content search
- `WebFetch.php` - Web page content retrieval
- `WordPressPostReader.php` - Single post analysis

**Chat-Specific Tools** (available only to chat AI agents):
```php
// Registered via datamachine_chat_tools filter
add_filter('datamachine_chat_tools', function($tools) {
    $tools['make_api_request'] = [
        'class' => 'DataMachine\\Api\\Chat\\Tools\\MakeAPIRequest',
        'method' => 'handle_tool_call',
        'description' => 'Execute Data Machine REST API operations',
        'parameters' => [/* ... */]
    ];
    return $tools;
});
```

**Handler-Specific Tools** (available when next step matches handler type):
```php
// Registered via chubes_ai_tools filter with handler context
add_filter('chubes_ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
    if ($handler_slug === 'twitter') {
        $tools['twitter_publish'] = [
            'class' => 'Twitter\\Handler',
            'method' => 'handle_tool_call',
            'handler' => 'twitter',
            'description' => 'Post to Twitter',
            'parameters' => ['content' => ['type' => 'string', 'required' => true]],
            'handler_config' => $handler_config
        ];
    }
    return $tools;
}, 10, 3);
```

### Discovery Hierarchy

1. **Global Level**: Admin settings enable/disable tools site-wide
2. **Modal Level**: Per-step tool selection in pipeline configuration
3. **Runtime Level**: Configuration validation checks at execution

**Configuration Check**:
```php
$tool_configured = apply_filters('datamachine_tool_configured', false, $tool_id);
```

## Tool Execution Architecture

### ToolExecutor Pattern

All tools integrate via the universal `ToolExecutor` class (`/inc/Engine/AI/ToolExecutor.php`):

```php
// Tool discovery
$available_tools = \DataMachine\Engine\AI\ToolExecutor::getAvailableTools(
    $agent_type,  // 'pipeline' or 'chat'
    $handler_slug,
    $handler_config,
    $flow_step_id
);
```

**Discovery Process**:
1. **Handler Tools**: Retrieved via `chubes_ai_tools` filter for specific handler
2. **Global Tools**: Retrieved via `datamachine_global_tools` filter
3. **Chat Tools**: Retrieved via `datamachine_chat_tools` filter (chat agent only)
4. **Enablement Check**: Each tool filtered through `datamachine_tool_enabled`

### Filter-Based Enablement

Tools can be enabled/disabled per agent type via filters:

```php
add_filter('datamachine_tool_enabled', function($enabled, $tool_id, $agent_type) {
    if ($agent_type === 'chat' && $tool_id === 'make_api_request') {
        return true;  // Chat-only tool
    }
    return $enabled;
}, 10, 3);
```

### Parameter Building

`ToolParameters` (`/inc/Engine/AI/ToolParameters.php`) provides unified parameter construction:

**Standard Tools** (global tools):
```php
$parameters = \DataMachine\Engine\AI\ToolParameters::buildParameters(
    $data,
    $job_id,
    $flow_step_id
);
// Returns: ['content_string' => ..., 'title' => ..., 'job_id' => ..., 'flow_step_id' => ...]
```

**Handler Tools** (publish/update handlers):
```php
$parameters = \DataMachine\Engine\AI\ToolParameters::buildForHandlerTool(
    $data,
    $tool_def,
    $job_id,
    $flow_step_id
);
// Returns: [...standard params, 'source_url' => ..., 'image_url' => ..., 'tool_definition' => ..., 'handler_config' => ...]
```

**Benefits**:
- Handler tools receive engine data (source_url, image_url) for link attribution and post identification
- Global tools receive clean data packets for content processing
- All tools receive job_id and flow_step_id context for tracking
- Unified flat parameter structure for AI simplicity

## Tool Interface

### `handle_tool_call()` Method

All tools implement the same interface:

```php
public function handle_tool_call(array $parameters, array $tool_def = []): array
```

**Parameters**:
- `$parameters` - AI-provided parameters (validated against tool definition)
- `$tool_def` - Complete tool definition including configuration

**Return Format**:
```php
[
    'success' => true|false,
    'data' => $result_data, // Tool-specific response data
    'error' => 'error_message', // Only if success = false
    'tool_name' => 'tool_identifier'
]
```

### Parameter Validation

**Required Parameters**:
```php
if (empty($parameters['query'])) {
    return [
        'success' => false,
        'error' => 'Missing required query parameter',
        'tool_name' => 'google_search'
    ];
}
```

**Type Validation**:
- `string` - Text content, URLs, identifiers
- `integer` - Numeric values, IDs, counts
- `boolean` - True/false flags

## Configuration Management

### Configuration Requirements

**Requires Config Flag**:
```php
'requires_config' => true // Shows configure link in UI
```

**Configuration Storage**:
- Global tools: WordPress options table
- Handler tools: Handler-specific configuration
- OAuth tools: Separate OAuth storage system

### Configuration Validation

```php
add_filter('datamachine_tool_configured', function($configured, $tool_id) {
    switch ($tool_id) {
        case 'google_search':
            $config = get_option('datamachine_search_config', []);
            $google_config = $config['google_search'] ?? [];
            return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
        
    }
    return $configured;
}, 10, 2);
```

## AI Integration

### Tool Selection

AI agents receive available tools based on:
1. **Global Settings** - Admin-enabled tools
2. **Step Configuration** - Modal-selected tools  
3. **Handler Context** - Next step handler type
4. **Configuration Status** - Tools with valid configuration

### Tool Descriptions

**AI-Optimized Descriptions**:
- Clear purpose and capabilities
- Usage instructions for AI
- Parameter requirements and formats
- Expected return data structure

**Example**:
```php
'description' => 'Search Google for current information and context. Provides real-time web data to inform content creation, fact-checking, and research. Use max_results to control response size.'
```

### Conversation Integration

**Universal Engine Architecture** - Tool execution flows through centralized Engine components:

**AIConversationLoop** (`/inc/Engine/AI/AIConversationLoop.php`):
- Multi-turn conversation execution with automatic tool calling
- Executes tools returned by AI and appends results to conversation
- Continues conversation loop until AI completes without tool calls
- Prevents infinite loops with maximum turn counter

**ToolExecutor** (`/inc/Engine/AI/ToolExecutor.php`):
- Universal tool discovery via `getAvailableTools()` method
- Filter-based tool enablement per agent type (pipeline vs chat)
- Handler tool and global tool integration
- Tool configuration validation

**ToolParameters** (`/inc/Engine/AI/ToolParameters.php`):
- Centralized parameter building for all AI tools
- `buildParameters()` for standard AI tools with clean data extraction
- `buildForHandlerTool()` for handler tools with engine parameters (source_url, image_url)
- Flat parameter structure for AI simplicity

**ConversationManager** (`/inc/Engine/AI/ConversationManager.php`):
- Message formatting utilities for AI providers
- Tool call recording and tracking
- Conversation message normalization
- Chronological message ordering

**RequestBuilder** (`/inc/Engine/AI/RequestBuilder.php`):
- Centralized AI request construction for all agents
- Directive application system (global, agent-specific, pipeline, chat)
- Tool restructuring for AI provider compatibility
- Integration with ai-http-client library

**Tool Results Processing**:
- Tool responses formatted by ConversationManager for AI consumption
- Structured data converted to human-readable success messages
- Platform-specific messaging enables natural AI agent conversation termination
- Multi-turn context preservation via AIConversationLoop

## Tool Implementation Examples

### Global Tool (Google Search)

```php
class GoogleSearch {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $config = apply_filters('datamachine_get_tool_config', [], 'google_search');
        $api_key = $config['api_key'] ?? '';
        $search_engine_id = $config['search_engine_id'] ?? '';
        if (empty($api_key) || empty($search_engine_id)) {
            return [ 'success' => false, 'error' => 'Google Search not configured', 'tool_name' => 'google_search' ];
        }
        $query = $parameters['query'];
        $results = $this->perform_search($query, $api_key, $search_engine_id, 10); // Fixed size
        return [
            'success' => true,
            'data' => [ 'results' => $results, 'query' => $query, 'total_results' => count($results) ],
            'tool_name' => 'google_search'
        ];
    }
}
```

### Handler Tool (Twitter Publish)

```php
class TwitterHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Get handler configuration
        $handler_config = $tool_def['handler_config'] ?? [];
        $twitter_config = $handler_config['twitter'] ?? [];
        
        // Process content
        $content = $parameters['content'];
        $formatted_content = $this->format_for_twitter($content, $twitter_config);
        
        // Publish to Twitter
        $result = $this->publish_tweet($formatted_content);
        
        return [
            'success' => true,
            'data' => [
                'tweet_id' => $result['id'],
                'tweet_url' => $result['url'],
                'content' => $formatted_content
            ],
            'tool_name' => 'twitter_publish'
        ];
    }
}
```

## Error Handling

### Configuration Errors

**Missing Configuration**:
- Tool returns error with configuration instructions
- UI shows configure link for unconfigured tools
- Runtime validation prevents broken tool calls

**Invalid Configuration**:
- API key validation during configuration save
- OAuth token refresh on authentication errors
- Clear error messages for troubleshooting

### Runtime Errors

**API Failures**:
- Network errors logged and returned to AI
- Rate limiting handled gracefully
- Service outages communicated clearly

**Parameter Errors**:
- Type validation with specific error messages
- Required parameter checking
- Format validation for complex parameters

## Performance Considerations

### Request Optimization

**External API Calls**:
- Single request per tool execution
- Timeout handling with WordPress defaults
- No automatic retries (AI can retry if needed)

**Data Processing**:
- Minimal memory usage during processing
- Streaming for large responses
- Efficient JSON parsing and formatting

### Caching Strategy

**Search Results**: Not cached (real-time data priority)
**Configuration Data**: Cached in WordPress options
**OAuth Tokens**: Cached with automatic refresh

## Extension Development

### Custom Global Tool

```php
class CustomTool {
    public function __construct() {
        // Self-register via datamachine_global_tools filter
        add_filter('datamachine_global_tools', [$this, 'register_tool'], 10, 1);
    }

    public function register_tool($tools) {
        $tools['custom_tool'] = [
            'class' => __CLASS__,
            'method' => 'handle_tool_call',
            'description' => 'Custom data processing tool',
            'requires_config' => false,
            'parameters' => [
                'input' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Data to process'
                ]
            ]
        ];
        return $tools;
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Validate parameters
        if (empty($parameters['input'])) {
            return [
                'success' => false,
                'error' => 'Missing input parameter',
                'tool_name' => 'custom_tool'
            ];
        }

        // Process data
        $result = $this->process_data($parameters['input']);

        return [
            'success' => true,
            'data' => ['processed_result' => $result],
            'tool_name' => 'custom_tool'
        ];
    }
}

// Self-register the tool
new CustomTool();
```