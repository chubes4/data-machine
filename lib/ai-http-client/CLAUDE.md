# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for multi-type AI provider communication with plugin-scoped configuration.

## Core Architecture (Multi-Type AI Parameter System)

### Distribution Pattern
- **Production**: Git subtree in plugin's `/lib/ai-http-client/`
- **Integration**: `require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';`
- **Multi-Plugin**: Each plugin maintains separate configuration while sharing API keys
- **Multi-Type**: Single library supports LLM, Upscaling, and Generative AI via `ai_type` parameter

### Multi-Type Architecture
The library uses a type-based parameter system for different AI capabilities:

1. **AI_HTTP_Client** - Single client class with `ai_type` parameter routing
2. **Type-Specific Normalizers** - Organized by AI type:
   - `src/Normalizers/LLM/` - Text generation normalizers
   - `src/Normalizers/Upscaling/` - Image upscaling normalizers  
   - `src/Normalizers/Generative/` - Image generation normalizers
3. **Type-Specific Providers** - Organized by AI type:
   - `src/Providers/LLM/` - Text AI providers (OpenAI, Anthropic, Gemini, Grok, OpenRouter)
   - `src/Providers/Upscaling/` - Image upscaling providers (Upsampler, etc.)
   - `src/Providers/Generative/` - Image generation providers (Stable Diffusion, etc.)

### AI Type Parameter System
All instantiation requires explicit `ai_type` parameter - **no defaults**:

```php
// LLM client
$llm_client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin',
    'ai_type' => 'llm'
]);

// Upscaling client  
$upscaling_client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin',
    'ai_type' => 'upscaling'
]);

// Multi-type plugins can use both
$generative_client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin', 
    'ai_type' => 'generative'
]);
```

## Multi-Plugin Configuration Architecture

### Plugin-Scoped Options Structure
```php
// Plugin-specific configuration (separate per plugin)
ai_http_client_providers_myplugin = [
    'openai' => ['model' => 'gpt-4', 'temperature' => 0.7, 'instructions' => '...'],
    'anthropic' => ['model' => 'claude-3-sonnet', 'temperature' => 0.5]
];

// Plugin-specific provider selection
ai_http_client_selected_provider_myplugin = 'openai';

// Shared API keys (efficient, secure, shared across all plugins)
ai_http_client_shared_api_keys = [
    'openai' => 'sk-...',
    'anthropic' => 'sk-...'
];
```

### Benefits of Multi-Plugin Architecture
- **Complete Plugin Isolation**: Each plugin can use different providers/models
- **Shared API Keys**: Reduce duplication and improve security
- **Independent Configuration**: Plugin A can use GPT-4, Plugin B can use Claude
- **No Conflicts**: Multiple plugins on same site work independently
- **Efficient Storage**: API keys stored once, settings stored per plugin

## Standardized Data Formats by AI Type

### LLM Request/Response Format
```php
// LLM Request
[
    'messages' => [['role' => 'user|assistant|system', 'content' => 'text']],
    'model' => 'provider-model-name', // Optional - falls back to configured model
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'stream' => false,
    'tools' => [] // Optional tool definitions
]

// LLM Response
[
    'success' => true|false,
    'data' => [
        'content' => 'response text',
        'usage' => ['prompt_tokens' => X, 'completion_tokens' => Y, 'total_tokens' => Z],
        'model' => 'actual-model-used',
        'finish_reason' => 'stop|length|tool_calls|error',
        'tool_calls' => null|[...], // Present when AI wants to call tools
        'response_id' => 'response-id' // For OpenAI continuation (when available)
    ],
    'error' => null|'error message',
    'provider' => 'openai|anthropic|gemini|grok|openrouter',
    'raw_response' => null|[...] // Present on errors for debugging
]
```

### Upscaling Request/Response Format
```php
// Upscaling Request
[
    'image_url' => 'https://example.com/image.jpg',
    'scale_factor' => '4x', // 2x, 4x, 8x
    'quality_settings' => [
        'creativity' => 7,
        'detail' => 8
    ],
    'webhook_url' => 'optional' // For async processing
]

// Upscaling Response  
[
    'success' => true|false,
    'data' => [
        'job_id' => 'abc123',
        'status' => 'processing|completed',
        'result_url' => 'https://upscaled-image.jpg', // When completed
        'webhook_url' => 'https://...'
    ],
    'error' => null|'error message',
    'provider' => 'upsampler',
    'raw_response' => null|[...] // Present on errors for debugging
]
```

### Generative Request/Response Format
```php
// Generative Request
[
    'prompt' => 'A beautiful landscape',
    'negative_prompt' => 'blurry, low quality',
    'style' => 'photorealistic',
    'dimensions' => '1024x1024',
    'count' => 1
]

// Generative Response
[
    'success' => true|false,
    'data' => [
        'images' => ['https://url1.jpg', 'https://url2.jpg'],
        'metadata' => ['seed' => 12345, 'steps' => 50]
    ],
    'error' => null|'error message',
    'provider' => 'stable-diffusion',
    'raw_response' => null|[...] // Present on errors for debugging
]
```

## Key Components

### Core Architecture
- **AI_HTTP_Client** - Main orchestrator (`src/class-client.php`)
- **UnifiedRequestNormalizer** - Converts standard format to any provider format
- **UnifiedResponseNormalizer** - Converts any provider format to standard format
- **UnifiedStreamingNormalizer** - Handles streaming for all providers
- **UnifiedToolResultsNormalizer** - Handles tool results continuation
- **UnifiedConnectionTestNormalizer** - Handles connection testing
- **UnifiedModelFetcher** - Fetches models from all providers

### Provider Classes
- **AI_HTTP_OpenAI_Provider** - Pure OpenAI API communication
- **AI_HTTP_Anthropic_Provider** - Pure Anthropic API communication
- **AI_HTTP_Gemini_Provider** - Pure Google Gemini API communication
- **AI_HTTP_Grok_Provider** - Pure Grok/X.AI API communication
- **AI_HTTP_OpenRouter_Provider** - Pure OpenRouter API communication

### WordPress Integration
- **OptionsManager** - Plugin-scoped WordPress options storage (`src/Utils/OptionsManager.php`)
- **PromptManager** - Modular prompt building (`src/Utils/PromptManager.php`)
- **WordPressSSEHandler** - WordPress-native SSE streaming endpoint (`src/Utils/WordPressSSEHandler.php`)
- **ProviderManagerComponent** - Complete admin UI with plugin context support (`src/Components/ProviderManagerComponent.php`)

## Development Commands

### Composer Scripts
```bash
composer test          # Run PHPUnit test suite
composer analyse       # Run PHPStan static analysis (level 5)
composer check         # Run both tests and analysis
composer dump-autoload # Regenerate autoloader after adding new classes
```

### Testing
- PHPUnit configured for automated testing
- PHPStan static analysis at level 5
- Testing requires WordPress environment with library loaded
- Use the TestConnection component (`src/Components/Extended/TestConnection.php`) for provider connectivity testing

### Development Workflow (Multi-Plugin Architecture)
```php
// Basic testing setup in WordPress - REQUIRES plugin context
require_once 'ai-http-client.php';
$client = new AI_HTTP_Client(['plugin_context' => 'my-plugin-slug']);
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'test']]
    // Model uses plugin-scoped configuration
]);
var_dump($response);

// Test streaming with plugin context
$client->send_streaming_request([
    'messages' => [['role' => 'user', 'content' => 'test']]
    // Model uses plugin-scoped configuration
]);

// Test connection with plugin context
$test_result = $client->test_connection('openai');
var_dump($test_result);

// Get available models using plugin-scoped configuration
$models = $client->get_available_models('openai');
var_dump($models);

// Override configured model per request
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'test']],
    'model' => 'gpt-4o' // Override plugin-scoped model
]);
```

### Version Management
- Version defined in `ai-http-client.php` line 24: `AI_HTTP_CLIENT_VERSION`
- Multiple plugins can include different versions safely
- Highest version wins via `ai_http_client_version_check()`

## Adding New Providers

1. Create simple provider class in `src/Providers/` (e.g., `newprovider.php`)
2. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
3. Add provider case to `AI_HTTP_Client::get_provider()`
4. Add provider loading to `ai-http-client.php`

### New Provider Template
```php
// src/Providers/newprovider.php
class AI_HTTP_NewProvider_Provider {
    public function __construct($config) { /* ... */ }
    public function send_raw_request($provider_request) { /* ... */ }
    public function send_raw_streaming_request($provider_request, $callback) { /* ... */ }
    public function get_raw_models() { /* ... */ }
    public function is_configured() { /* ... */ }
}
```

## Loading System

The library uses a dual-loading system in `ai-http-client.php`:

### Composer-First Loading
1. **Composer Detection** - Checks for `/vendor/autoload.php`
2. **Automatic Classmap** - Uses Composer's classmap autoloader for all classes
3. **File Autoloading** - Main entry point loaded via Composer files directive

### Manual Loading Fallback
When Composer unavailable, uses manual `require_once` in dependency order:
1. Shared utilities (FileUploadClient, ToolExecutor)
2. **Unified Normalizers** - All normalization logic
3. **Simple Providers** - Pure API communication
4. **Main Client** - Unified orchestrator
5. WordPress management components
6. UI Components system

### Version Management
- `ai_http_client_version_check()` ensures highest version wins
- Multiple plugins can safely include different versions
- `AI_HTTP_CLIENT_VERSION` constant prevents conflicts

## Critical Rules

- **Use Unified Architecture** - All providers use the same normalizers
- **No Provider-Specific Logic in Core** - All provider differences handled in unified normalizers
- **Single Responsibility** - Providers only handle API communication, normalizers handle format conversion
- **WordPress-Native** - Use WordPress APIs and security practices
- **Plugin Context Required** - All AI_HTTP_Client and ProviderManagerComponent instances MUST include plugin context
- **No Hardcoded Defaults** - All configuration should come from plugin-scoped WordPress options
- **Shared API Keys** - API keys are shared across plugins for efficiency, other settings are plugin-specific

## Supported Providers

All providers are fully refactored and support the complete feature set:

1. **OpenAI** - GPT models, Responses API, streaming, function calling, vision
2. **Anthropic** - Claude models, streaming, function calling, vision
3. **Google Gemini** - 2025 API, streaming, function calling, multi-modal
4. **Grok/X.AI** - All models, reasoning_effort parameter, streaming
5. **OpenRouter** - 100+ models, provider routing, fallbacks

## WordPress Integration

- **HTTP API**: `wp_remote_post()` for requests, cURL for streaming
- **Options**: Single `ai_http_client_providers` option stores all settings
- **Security**: WordPress sanitization functions throughout
- **Filters**: Extensible via WordPress filter system

## Git Subtree Commands

```bash
# Add library to plugin (first time)
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Update library in plugin
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Push changes back to library
git subtree push --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main
```

## Quick Integration (Multi-Type AI Parameter System)

### LLM Integration
```php
// 1. Include library
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// 2. Add LLM admin UI
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug', // REQUIRED
    'ai_type' => 'llm' // REQUIRED
]);

// 3. Send LLM request
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug', // REQUIRED
    'ai_type' => 'llm' // REQUIRED
]);
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']],
    'max_tokens' => 100
]);
```

### Upscaling Integration
```php
// 1. Include same library
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// 2. Add upscaling admin UI
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug', // REQUIRED
    'ai_type' => 'upscaling' // REQUIRED
]);

// 3. Send upscaling request
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug', // REQUIRED
    'ai_type' => 'upscaling' // REQUIRED
]);
$response = $client->send_request([
    'image_url' => 'https://example.com/image.jpg',
    'scale_factor' => '4x'
]);
```

### Multi-Type Plugin Usage
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

// Use both in same plugin
$text_analysis = $llm_client->send_request([
    'messages' => [['role' => 'user', 'content' => 'Analyze this image']]
]);

$enhanced_image = $upscaling_client->send_request([
    'image_url' => 'https://example.com/image.jpg',
    'scale_factor' => '4x'
]);
```

## Continuation Support (Agentic Systems)

```php
// OpenAI: Use response ID from Responses API
$response_id = $client->get_last_response_id();
$continuation = $client->continue_with_tool_results($response_id, $tool_results);

// Anthropic: Use conversation history
$continuation = $client->continue_with_tool_results($conversation_history, $tool_results, 'anthropic');

// All providers support continuation through unified architecture
```

## Breaking Changes (Multi-Type AI Parameter System)

### Constructor Changes
```php
// OLD (no longer supported)
$client = new AI_HTTP_Client();
$client = new AI_HTTP_Client(['plugin_context' => 'my-plugin-slug']);
$component = AI_HTTP_ProviderManager_Component::render(['plugin_context' => 'my-plugin-slug']);

// NEW (required - ai_type parameter mandatory)
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm' // REQUIRED: 'llm', 'upscaling', or 'generative'
]);
$component = AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm' // REQUIRED
]);
```

### No Hardcoded Defaults Policy
- **No default ai_type** - must be explicitly specified
- **No default provider** - must be configured via admin UI
- **No default timeouts** - use WordPress defaults
- **Library fails fast** with clear error messages when configuration missing

### Migration from Previous Version
1. **Add ai_type parameter** to all AI_HTTP_Client instantiations
2. **API keys automatically migrate** to shared storage on first save
3. **Plugin-specific settings** remain isolated per plugin
4. **No data loss** - existing configurations continue working per plugin

## Error Handling

- **Logging Requirements**: All providers log their raw error responses for debugging
- **Fallback System**: `AI_HTTP_Client` supports automatic fallback to other providers
- **Standardized Errors**: All errors return consistent format via `create_error_response()`
- **WordPress Integration**: Uses WordPress error logging functions

## API-Specific Notes

### OpenAI
- Uses **Responses API** (not Chat Completions)
- Converts `messages` → `input` and `max_tokens` → `max_output_tokens`
- Handles tool calling via flat format (not nested)

### Anthropic
- Extracts system messages from messages array to `system` parameter
- Temperature constrained to 0-1 range

### Google Gemini
- Converts messages to `contents` format with `role` and `parts`
- Uses `generationConfig` for temperature and maxOutputTokens

### Grok/X.AI
- OpenAI-compatible format with optional `reasoning_effort` parameter
- Supports all standard OpenAI features

### OpenRouter
- OpenAI-compatible format with provider routing
- Supports 100+ models from multiple providers