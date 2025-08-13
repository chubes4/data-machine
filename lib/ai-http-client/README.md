# AI HTTP Client for WordPress

A professional WordPress library for **multi-type AI provider communication** with plugin-scoped configuration. Supports LLM, Upscaling, and Generative AI in a single unified library.

## Why This Library?

This is for WordPress plugin developers who want to ship AI features fast across multiple AI types.

**Complete Multi-Type Solution:**
- ✅ **Multi-Type AI Support** - LLM, Upscaling, Generative AI via `ai_type` parameter
- ✅ **Multi-Plugin Support** - Multiple plugins can use different AI providers simultaneously
- ✅ **Shared API Keys** - Efficient key management across plugins and AI types
- ✅ **No Hardcoded Defaults** - Library fails fast with clear errors when not configured
- ✅ **Type-Specific Features** - Streaming for LLM, async processing for upscaling
- ✅ **Unified Interface** - Same client class for all AI types
- ✅ **WordPress-Native** - Uses `wp_remote_post`, plugin-scoped options
- ✅ **Zero Styling** - You control the design

## Installation

### Method 1: Composer (New)
```bash
composer require chubes4/ai-http-client
```

Then in your code:
```php
require_once __DIR__ . '/vendor/autoload.php';
// Library automatically loads via Composer autoloader
```

### Method 2: Git Subtree (Recommended for WordPress)
Install as a subtree in your plugin for automatic updates:

```bash
# From your plugin root directory
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# To update later
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

### Method 3: Direct Download
Download and place in your plugin's `/lib/ai-http-client/` directory.

## Quick Start

### 1. Include the Library

**With Composer:**
```php
require_once __DIR__ . '/vendor/autoload.php';
// No additional includes needed
```

**Without Composer (Git Subtree/Manual):**
```php
// In your plugin
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

### 2. Add Admin UI Component (Multi-Type AI System)
```php
// LLM Admin UI - REQUIRES both plugin_context AND ai_type
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug',  // REQUIRED
    'ai_type' => 'llm'  // REQUIRED: 'llm', 'upscaling', 'generative'
]);

// Upscaling Admin UI 
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug',  // REQUIRED
    'ai_type' => 'upscaling'  // REQUIRED
]);

// Customized LLM component
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug',  // REQUIRED
    'ai_type' => 'llm',  // REQUIRED
    'components' => [
        'core' => ['provider_selector', 'api_key_input', 'model_selector'],
        'extended' => ['temperature_slider', 'system_prompt_field']
    ]
]);
```

### 3. Send AI Requests (Multi-Type AI System)

#### LLM Requests
```php
// REQUIRES both plugin_context AND ai_type
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm'  // REQUIRED
]);
$response = $client->send_request([
    'messages' => [
        ['role' => 'user', 'content' => 'Hello AI!']
    ],
    'max_tokens' => 100
]);

if ($response['success']) {
    echo $response['data']['content'];
}
```

#### Upscaling Requests
```php
// Upscaling client
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'upscaling'  // REQUIRED
]);
$response = $client->send_request([
    'image_url' => 'https://example.com/image.jpg',
    'scale_factor' => '4x',
    'quality_settings' => [
        'temperature' => 0.7,
        'detail' => 8
    ]
]);

if ($response['success']) {
    $job_id = $response['data']['job_id'];
    // Handle async processing
}
```

#### Multi-Type Plugin Usage
```php
// Single plugin using multiple AI types
$llm_client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm'
]);

$upscaling_client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'upscaling'
]);

// Use text AI to analyze image
$analysis = $llm_client->send_request([
    'messages' => [['role' => 'user', 'content' => 'Describe this image for enhancement']]
]);

// Use upscaling AI to enhance image
$enhanced = $upscaling_client->send_request([
    'image_url' => 'https://example.com/image.jpg',
    'scale_factor' => '4x'
]);
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

**"Round Plug" Design** - Standardized input → Black box processing → Standardized output

**Multi-Plugin Architecture** - Complete plugin isolation with shared API key efficiency

**Filter-Based Architecture** - Individual provider classes register via WordPress filters, shared normalizers handle all provider differences

**WordPress-Native** - Uses WordPress HTTP API, options system, and admin patterns

**Production-Ready** - Debug logging only enabled when `WP_DEBUG` is true, ensuring clean production logs

**Modular Prompts** - Dynamic prompt building with tool registration, context injection, and granular control

### Multi-Plugin Benefits

- **Plugin Isolation**: Each plugin maintains separate provider/model configurations
- **Shared API Keys**: Efficient key storage across all plugins (no duplication)
- **No Conflicts**: Plugin A can use GPT-4, Plugin B can use Claude simultaneously
- **Independent Updates**: Each plugin's AI settings are completely isolated
- **Backwards Migration**: Existing configurations automatically become plugin-scoped

### Key Components

- **AI_HTTP_Client** - Main orchestrator using unified normalizers
- **Unified Normalizers** - Shared logic for request/response conversion, streaming, and tools
- **Simple Providers** - Pure API communication classes (one per provider)
- **Admin UI** - Complete WordPress admin interface with zero styling

## Component Configuration

The admin UI component is fully configurable:

```php
// Available core components
'core' => [
    'provider_selector',  // Dropdown to select provider
    'api_key_input',     // Secure API key input
    'model_selector'     // Dynamic model dropdown
]

// Available extended components  
'extended' => [
    'temperature_slider',    // Temperature control (0-1)
    'system_prompt_field'    // System prompt textarea
]

// Component-specific configs
'component_configs' => [
    'temperature_slider' => [
        'min' => 0,
        'max' => 1, 
        'step' => 0.1,
        'default_value' => 0.7
    ]
]
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

1. Create provider class in `src/Providers/LLM/` (e.g., `newprovider.php`)
2. Register provider via `ai_providers` WordPress filter in `src/Filters.php`
3. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
4. Add provider loading to `ai-http-client.php`

Each provider implements standardized interface:
- `send_raw_request($provider_request)` - Send API request
- `send_raw_streaming_request($provider_request, $callback)` - Send streaming request
- `get_raw_models()` - Fetch available models
- `is_configured()` - Check if provider is configured
- `upload_file($file_path, $purpose)` - Files API integration

## Current Version: 1.1.0

### Filter & Action Patterns (Recommended)

**Reading Configuration:**
```php
// Global configuration
$config = apply_filters('ai_config', null);

// Get models for a provider
$models = apply_filters('ai_models', $provider_name);
```

**Tool Discovery & Management:**
```php
// Get all registered tools
$all_tools = apply_filters('ai_tools', []);

// Get plugin-specific tools (auto-detected context)
$my_tools = ai_http_get_tools();

// Get tools by category
$file_tools = ai_http_get_tools(null, 'file_handling');

// Check if specific tool is available
$has_processor = ai_http_has_tool('file_processor');

// Execute a tool
$result = ai_http_execute_tool('file_processor', [
    'file_path' => '/path/to/file.txt',
    'format' => 'json'
]);
```

**AI Requests:**
```php
// Standard AI request
$response = apply_filters('ai_request', $request);

// With specific provider
$response = apply_filters('ai_request', $request, $provider_name);

// With streaming callback
$response = apply_filters('ai_request', $request, null, $streaming_callback);

// With tools
$response = apply_filters('ai_request', $request, null, null, null, $tools);
```

**Note:** Configuration saving is now handled by individual plugins rather than the library itself.

### Legacy Direct Instantiation (Not Recommended)

**OptionsManager Constructor:**
```php
// REQUIRED - both parameters required
$options_manager = new AI_HTTP_Options_Manager('my-plugin-slug', 'llm');
```

**Note:** Direct instantiation is only needed for advanced use cases. Use filters and actions for standard operations.

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
