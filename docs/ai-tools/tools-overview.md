# AI Tools Overview

AI tools provide capabilities to AI agents for interacting with external services, processing data, and performing research tasks. Data Machine supports both general-purpose tools and handler-specific tools.

## Tool Categories

### General Tools (Universal)

Available to all AI steps regardless of next pipeline step:

**Google Search** (`google_search`)
- **Purpose**: Web search for current information and context
- **Configuration**: API key + Custom Search Engine ID required
- **Use Cases**: Fact-checking, research, external context gathering

**Local Search** (`local_search`)
- **Purpose**: Search existing WordPress posts for internal linking
- **Configuration**: None required (uses WordPress core)
- **Use Cases**: Content discovery, internal link suggestions

**Read Post** (`read_post`)
- **Purpose**: Retrieve full content of specific WordPress posts by ID
- **Configuration**: None required (uses WordPress core)
- **Use Cases**: Content analysis, detailed content retrieval

**Google Search Console** (`google_search_console`)
- **Purpose**: SEO performance analysis and optimization opportunities
- **Configuration**: OAuth2 authentication required
- **Use Cases**: SEO research, keyword analysis, performance insights

### Handler-Specific Tools

Available only when next step matches the handler type:

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

**General Tools** (no `handler` property):
```php
add_filter('ai_tools', function($tools) {
    $tools['google_search'] = [
        'class' => 'DataMachine\\Core\\Steps\\AI\\Tools\\GoogleSearch',
        'method' => 'handle_tool_call',
        'description' => 'Search Google for information',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query'
            ]
        ]
    ];
    return $tools;
});
```

**Handler-Specific Tools** (with `handler` property):
```php
add_filter('ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
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
$tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
```

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
- General tools: WordPress options table
- Handler tools: Handler-specific configuration
- OAuth tools: Separate OAuth storage system

### Configuration Validation

```php
add_filter('dm_tool_configured', function($configured, $tool_id) {
    switch ($tool_id) {
        case 'google_search':
            $config = get_option('dm_search_config', []);
            $google_config = $config['google_search'] ?? [];
            return !empty($google_config['api_key']) && !empty($google_config['search_engine_id']);
        
        case 'google_search_console':
            $oauth_config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
            $account = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
            return !empty($oauth_config['client_id']) && !empty($account['access_token']);
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

**Tool Results Formatting**:
- Structured data converted to readable format
- Search results formatted for AI consumption
- Error messages provide actionable feedback

**Multi-Turn Support**:
- Tool results preserved in conversation history
- Context maintained across multiple tool calls
- Conversation state managed automatically

## Tool Implementation Examples

### General Tool (Google Search)

```php
class GoogleSearch {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Get configuration
        $config = apply_filters('dm_get_tool_config', [], 'google_search');
        $api_key = $config['api_key'] ?? '';
        $search_engine_id = $config['search_engine_id'] ?? '';
        
        // Validate configuration
        if (empty($api_key) || empty($search_engine_id)) {
            return [
                'success' => false,
                'error' => 'Google Search not configured',
                'tool_name' => 'google_search'
            ];
        }
        
        // Execute search
        $query = $parameters['query'];
        $max_results = $parameters['max_results'] ?? 5;
        
        $results = $this->perform_search($query, $api_key, $search_engine_id, $max_results);
        
        return [
            'success' => true,
            'data' => [
                'results' => $results,
                'query' => $query,
                'total_results' => count($results)
            ],
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

### Custom General Tool

```php
class CustomTool {
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

// Register tool
add_filter('ai_tools', function($tools) {
    $tools['custom_tool'] = [
        'class' => 'CustomTool',
        'method' => 'handle_tool_call',
        'description' => 'Custom data processing tool',
        'parameters' => [
            'input' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Data to process'
            ]
        ]
    ];
    return $tools;
});
```