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
        'creativity' => 7,
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

All providers are **fully refactored** with unified architecture and support **dynamic model fetching** - no hardcoded model lists. Models are fetched live from each provider's API.

- **OpenAI** - GPT models via Responses API, streaming, function calling, vision
- **Anthropic** - Claude models, streaming, function calling, vision
- **Google Gemini** - Gemini models via 2025 API, streaming, function calling, multi-modal
- **Grok/X.AI** - Grok models with reasoning_effort parameter, streaming
- **OpenRouter** - 100+ models via unified API with provider routing

## Architecture

**"Round Plug" Design** - Standardized input → Black box processing → Standardized output

**Multi-Plugin Architecture** - Complete plugin isolation with shared API key efficiency

**Unified Architecture** - Shared normalizers handle all provider differences, simple providers handle pure API communication

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
- **Unified Normalizers** - Shared logic for request/response conversion, streaming, tools, and connection testing
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
    'system_prompt_field',   // System prompt textarea
    'max_tokens_input',      // Max tokens input
    'top_p_slider'          // Top P control
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
$client_a = new AI_HTTP_Client(['plugin_context' => 'content-editor']);
// Uses OpenAI GPT-4 with temperature 0.3

// Plugin B: Chat Bot using Claude  
$client_b = new AI_HTTP_Client(['plugin_context' => 'chat-bot']);
// Uses Anthropic Claude with temperature 0.8

// Both share the same API keys but have completely different configurations
```

## Distribution Model

Designed for **flexible distribution**:
- **Composer**: Standard package manager installation
- **Git Subtree**: Like Action Scheduler for WordPress plugins
- No external dependencies
- Version conflict resolution
- Multiple plugins can include different versions safely
- Automatic updates via `git subtree pull` or `composer update`

### Adding New Providers

1. Create simple provider class in `src/Providers/` (e.g., `newprovider.php`)
2. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
3. Add provider case to `AI_HTTP_Client::get_provider()`
4. Add provider loading to `ai-http-client.php`

Each provider needs only 4 methods:
- `send_raw_request()` - Send API request
- `send_raw_streaming_request()` - Send streaming request
- `get_raw_models()` - Fetch available models
- `is_configured()` - Check if provider is configured

## Breaking Changes

### v2.x.x - AI Type Scoping (Current)

**OptionsManager Constructor Change:**
```php
// OLD (no longer works)
$options_manager = new AI_HTTP_Options_Manager('my-plugin-slug');

// NEW (required)
$options_manager = new AI_HTTP_Options_Manager('my-plugin-slug', 'llm');
```

**Impact:** All plugins using `AI_HTTP_Options_Manager` directly must update their constructor calls to include the `ai_type` parameter.

**Migration:**
- Add `'llm'` as second parameter for existing LLM functionality
- Use `'upscaling'` or `'generative'` for new AI types
- Settings will be automatically scoped by AI type (no data loss)

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
