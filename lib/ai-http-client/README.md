# AI HTTP Client for WordPress

A streamlined WordPress library for **AI provider communication** using **pure filter architecture**.

## Why This Library?

Simplified AI integration for WordPress plugin developers.

**Pure Filter Architecture:**
- ✅ **WordPress Native** - Complete WordPress filter system integration
- ✅ **Self-Contained Providers** - Each provider handles its own format conversion
- ✅ **Shared API Keys** - Efficient key storage via filters
- ✅ **No Legacy Support** - Clean, modern implementation
- ✅ **Streaming Support** - Real-time streaming via centralized HTTP filter
- ✅ **Model Auto-Fetching** - Dynamic model discovery from provider APIs
- ✅ **Zero Styling** - Template-based UI components

## Installation

### Method 1: Git Subtree (Recommended)
```bash
# From your plugin root directory
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# To update later
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

### Method 2: Direct Download
Download and place in your plugin's `/lib/ai-http-client/` directory.

**Requirements**: Composer autoloader must be available (run `composer install` in library directory).

## Quick Start

### 1. Include the Library

```php
// In your plugin main file
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

### 2. Send AI Requests (Filter-Based)

#### Standard Requests
```php
// Simple AI request using filter system
$response = apply_filters('ai_request', [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello AI!']
    ],
    'max_tokens' => 100
]);

if ($response['success']) {
    echo $response['data']['content'];
}
```

#### Advanced Requests
```php
// With specific provider
$response = apply_filters('ai_request', $request, 'anthropic');

// With streaming callback
$response = apply_filters('ai_request', $request, null, function($chunk) {
    echo esc_html($chunk);
    flush();
});

// With tools (function calling)
$tools = [
    [
        'name' => 'get_weather',
        'description' => 'Get current weather',
        'parameters' => [
            'type' => 'object',
            'properties' => ['location' => ['type' => 'string']]
        ]
    ]
];
$response = apply_filters('ai_request', $request, null, null, $tools);
```

### 4. Modular Prompt System
```php
// Register tool definitions for your AI agent
AI_HTTP_Prompt_Manager::register_tool_definition(
    'edit_content',
    "Use this tool to edit content with specific instructions...",
    ['priority' => 1, 'category' => 'content']
);

// Build dynamic system prompts with context
$prompt = AI_HTTP_Prompt_Manager::build_modular_system_prompt(
    $base_prompt,
    ['post_id' => 123, 'user_role' => 'editor'],
    [
        'include_tools' => true,
        'tool_context' => 'my_plugin',
        'enabled_tools' => ['edit_content', 'read_content']
    ]
);
```

### 5. Continuation Support (For Agentic Systems)
```php
// Send initial request with tools
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'What is the weather?']],
    'tools' => $tool_schemas
]);

// Continue with tool results (OpenAI - use response ID)
$response_id = $client->get_last_response_id();
$continuation = $client->continue_with_tool_results($response_id, $tool_results);

// Continue with tool results (Anthropic - use conversation history)
$continuation = $client->continue_with_tool_results($conversation_history, $tool_results, 'anthropic');
```

## Supported Providers

All providers use individual classes with filter-based registration and support **dynamic model fetching** - no hardcoded model lists. Models are fetched live from each provider's API.

- **OpenAI** - GPT models via Chat Completions API, streaming, function calling, Files API
- **Anthropic** - Claude models, streaming, function calling
- **Google Gemini** - Gemini models, streaming, function calling
- **Grok/X.AI** - Grok models, streaming
- **OpenRouter** - 100+ models via unified API with provider routing

## Architecture

**Filter-Based Design** - WordPress-native filter system for provider registration and discovery

**Self-Contained Providers** - Each provider is fully self-contained with filter registration

**Shared API Keys** - Efficient storage across all plugins with zero duplication

**WordPress-Native** - Uses WordPress HTTP API, options system, and admin patterns

**Production-Ready** - Debug logging only enabled when `WP_DEBUG` is true, ensuring clean production logs

**Extensible** - Third-party plugins can easily register new providers via filters

### Multi-Plugin Benefits

- **Plugin Isolation**: Each plugin maintains separate provider/model configurations
- **Shared API Keys**: Efficient key storage across all plugins (no duplication)
- **No Conflicts**: Plugin A can use GPT-4, Plugin B can use Claude simultaneously
- **Independent Updates**: Each plugin's AI settings are completely isolated
- **Backwards Migration**: Existing configurations automatically become plugin-scoped

### Key Components

- **Filter System** - WordPress-native provider registration and discovery
- **Self-Contained Providers** - Each provider handles its own formatting and registration
- **Admin Filters** - Complete WordPress admin interface via `ai_render_component` filter
- **Shared API Storage** - Centralized API key management via `ai_provider_api_keys` filter

## Core Filters & Actions

The library provides WordPress-native filter patterns for all operations:

```php
// Provider Discovery
$providers = apply_filters('ai_providers', []);

// API Key Management  
$keys = apply_filters('ai_provider_api_keys', null);           // Get all keys
apply_filters('ai_provider_api_keys', $updated_keys);          // Update keys

// Configuration Access
$config = apply_filters('ai_config', null);                   // Get provider config

// Model Fetching
$models = apply_filters('ai_models', $provider_name, $config);

// Admin Interface
echo apply_filters('ai_render_component', '', [
    'selected_provider' => 'openai',
    'temperature' => true,
    'system_prompt' => true
]);
```

## Modular Prompt System

Build dynamic AI prompts with context awareness and tool management:

```php
// Register tool definitions that can be dynamically included
AI_HTTP_Prompt_Manager::register_tool_definition(
    'tool_name',
    'Tool description and usage instructions...',
    ['priority' => 1, 'category' => 'content_editing']
);

// Set which tools are enabled for different contexts
AI_HTTP_Prompt_Manager::set_enabled_tools(['tool1', 'tool2'], 'my_plugin_context');

// Build complete system prompts with context and tools
$prompt = AI_HTTP_Prompt_Manager::build_modular_system_prompt(
    $base_prompt,
    $context_data,
    [
        'include_tools' => true,
        'tool_context' => 'my_plugin_context',
        'enabled_tools' => ['specific_tool'],
        'sections' => ['custom_section' => 'Additional content...']
    ]
);
```

**Features:**
- **Tool Registration** - Register tool descriptions that can be dynamically included
- **Context Awareness** - Inject dynamic context data into prompts
- **Granular Control** - Enable/disable tools per plugin or use case
- **Filter Integration** - WordPress filters for prompt customization
- **Variable Replacement** - Template variable substitution

## Multi-Plugin Configuration

### How It Works

```php
// Plugin-specific configuration (isolated per plugin)
ai_http_client_providers_myplugin = [
    'openai' => ['model' => 'gpt-4', 'temperature' => 0.7],
    'anthropic' => ['model' => 'claude-3-sonnet']
];

// Plugin-specific provider selection  
ai_http_client_selected_provider_myplugin = 'openai';

// Shared API keys (efficient, no duplication)
ai_http_client_shared_api_keys = [
    'openai' => 'sk-...',
    'anthropic' => 'sk-...'
];
```

### Real-World Example

```php
// Plugin A: Content Editor using GPT-4
$client_a = new AI_HTTP_Client([
    'plugin_context' => 'content-editor',
    'ai_type' => 'llm'  // REQUIRED
]);
// Uses OpenAI GPT-4 with temperature 0.3

// Plugin B: Chat Bot using Claude  
$client_b = new AI_HTTP_Client([
    'plugin_context' => 'chat-bot',
    'ai_type' => 'llm'  // REQUIRED
]);
// Uses Anthropic Claude with temperature 0.8

// Both share the same API keys but have completely different configurations
```

## AI Tools Registration System

The library includes a comprehensive tool registration and discovery system that enables plugins to register AI-compatible tools that other plugins can discover and use.

### Tool Registration Pattern

Follow the same self-registration pattern as AI providers:

```php
// Register tools in your plugin file
add_filter('ai_tools', function($tools) {
    $tools['file_processor'] = [
        'class' => 'DataMachine_FileProcessor_Tool',
        'plugin_context' => 'data-machine',
        'category' => 'file_handling',
        'description' => 'Process uploaded files and extract content',
        'method' => 'execute', // Optional, defaults to 'execute'
        'parameters' => [
            'file_path' => ['type' => 'string', 'required' => true],
            'format' => ['type' => 'string', 'options' => ['text', 'json'], 'required' => false]
        ]
    ];
    
    $tools['content_generator'] = [
        'class' => 'DataMachine_ContentGenerator_Tool',
        'plugin_context' => 'data-machine', 
        'category' => 'content_processing',
        'description' => 'Generate content based on templates and data',
        'parameters' => [
            'template' => ['type' => 'string', 'required' => true],
            'data' => ['type' => 'array', 'required' => true]
        ]
    ];
    
    return $tools;
});
```

### Tool Discovery and Usage

```php
// Discovery - get all available tools
$all_tools = apply_filters('ai_tools', []);

// Discovery - plugin-scoped (auto-detected context)
$my_tools = ai_http_get_tools();

// Discovery - by category across all plugins
$file_tools = ai_http_get_tools(null, 'file_handling');

// Discovery - specific plugin's tools
$data_machine_tools = ai_http_get_tools('data-machine');

// Check availability
if (ai_http_has_tool('file_processor')) {
    // Tool is available
}

// Execute tools
$result = ai_http_execute_tool('file_processor', [
    'file_path' => '/uploads/document.pdf',
    'format' => 'text'
]);

if ($result['success']) {
    $processed_content = $result['data'];
}
```

### AI Requests with Tools

```php
// Include tools in AI requests
$tools_to_use = ai_http_get_tools(null, 'file_handling');

$response = apply_filters('ai_request', [
    'messages' => [
        ['role' => 'user', 'content' => 'Process this file and summarize it']
    ]
], null, null, null, array_keys($tools_to_use));
```

### Tool Implementation

Tools must implement an executable method (default: `execute`):

```php
class DataMachine_FileProcessor_Tool {
    
    public function execute($parameters) {
        $file_path = $parameters['file_path'];
        $format = $parameters['format'] ?? 'text';
        
        // Validate file exists
        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$file_path}");
        }
        
        // Process file based on format
        switch ($format) {
            case 'text':
                return file_get_contents($file_path);
            case 'json':
                return json_decode(file_get_contents($file_path), true);
            default:
                throw new Exception("Unsupported format: {$format}");
        }
    }
}
```

### Benefits

- **Plugin Independence**: Each plugin registers its own tools
- **Auto-Discovery**: Plugins can discover and use each other's tools
- **Category Organization**: Tools grouped by functionality
- **Parameter Validation**: Built-in parameter validation
- **WordPress Native**: Uses standard filter system
- **Zero Configuration**: Tools are automatically available once registered

## Distribution Model

Designed for **flexible distribution**:
- **Composer**: Standard package manager installation
- **Git Subtree**: Like Action Scheduler for WordPress plugins
- No external dependencies
- Version conflict resolution
- Multiple plugins can include different versions safely
- Automatic updates via `git subtree pull` or `composer update`

### Adding New Providers

Creating new providers is trivial with the filter-based architecture:

```php
// 1. Create provider class (e.g., Claude)
class AI_HTTP_Claude_Provider {
    public function __construct($config) { /* ... */ }
    public function is_configured() { /* ... */ }
    public function request($request) { /* ... */ }
    public function streaming_request($request, $callback) { /* ... */ }
    public function get_raw_models() { /* ... */ }
    public function get_normalized_models() { /* ... */ }
    public function upload_file($file_path, $purpose) { /* ... */ }
}

// 2. Self-register via filter (in your provider file)
add_filter('ai_providers', function($providers) {
    $providers['claude'] = [
        'class' => 'AI_HTTP_Claude_Provider',
        'type' => 'llm',
        'name' => 'Claude'
    ];
    return $providers;
});
```

**That's it!** The provider is now:
- ✅ Auto-discoverable by all plugins
- ✅ Available in admin UI dropdowns  
- ✅ Model fetching works via AJAX
- ✅ Filter-based access for all operations
- ✅ Shared API key storage

## Current Version: 1.2.0

### Filter Patterns (WordPress Native)

**Provider Discovery:**
```php
// Get all available providers
$providers = apply_filters('ai_providers', []);

// Get provider configuration
$config = apply_filters('ai_config', null);
```

**API Key Management:**
```php
// Get all API keys
$keys = apply_filters('ai_provider_api_keys', null);

// Update API keys (automatically saves to WordPress options)
apply_filters('ai_provider_api_keys', $updated_keys);
```

**Model Fetching:**
```php
// Get models for a provider (with API key)
$models = apply_filters('ai_models', $provider_name, ['api_key' => $api_key]);
```

**AI Requests:**
```php
// Standard request
$response = apply_filters('ai_request', $request);

// With specific provider  
$response = apply_filters('ai_request', $request, $provider_name);

// With streaming callback
$response = apply_filters('ai_request', $request, null, $streaming_callback);

// With tools
$response = apply_filters('ai_request', $request, null, null, $tools);
```

**Admin Interface:**
```php
// Render AI provider UI components
echo apply_filters('ai_render_component', '', $config);
```

**Current Features:**
- Auto-save settings functionality
- Auto-fetch models from providers
- Conditional save button display
- Component-owned architecture for UI consistency
- Filter-based provider registration
- Files API integration across providers

## Examples

WordPress plugins using this library:

- **[Data Machine](https://github.com/chubes4/data-machine)** - Automated content pipeline with AI processing and multi-platform publishing
- **[AI Bot for bbPress](https://github.com/chubes4/ai-bot-for-bbpress)** - Multi-provider AI bot for bbPress forums with context-aware responses
- **[WordSurf](https://github.com/chubes4/wordsurf)** - Agentic WordPress content editor with AI assistant and tool integration

## Troubleshooting

### Debug Logging
Enable detailed debug logging for development and troubleshooting:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

When enabled, the library provides comprehensive logging for:
- API request/response cycles
- Tool execution and validation  
- Streaming connection handling
- System events and error conditions

**Production Note**: Always set `WP_DEBUG` to `false` in production environments to prevent debug log generation.

## Contributing

Built by developers, for developers. PRs welcome for:
- New provider implementations
- Performance improvements
- WordPress compatibility fixes

## License

GPL v2 or later

---

**[Chris Huber](https://chubes.net)**
