# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for AI provider communication using pure filter architecture.

## Development Commands

### Static Analysis
```bash
# Run PHPStan static analysis (level 5)
composer analyse

# Check PHP syntax for individual files
php -l src/Providers/LLM/openai.php
```

### Debug Logging
```bash
# Enable debug logging in WordPress (wp-config.php)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

# View debug logs (typical location)
tail -f /wp-content/debug.log

# Disable debug logging for production
define('WP_DEBUG', false);
```

### Git Subtree Operations (Primary Distribution Method)
```bash
# Add as subtree to a WordPress plugin
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Update existing subtree
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Push changes back (from main repo)
git subtree push --prefix=lib/ai-http-client origin main
```

## Core Architecture

### Pure Filter Architecture
The library uses WordPress filters exclusively for all operations:

**Core Filters**:
```php
// Provider discovery
$providers = apply_filters('ai_providers', []);

// HTTP requests 
$result = apply_filters('ai_http', [], 'POST', $url, $args, 'context');

// AI requests
$result = apply_filters('ai_request', null, $request_data, $provider);

// Model discovery
$models = apply_filters('ai_models', $provider, $config);

// API key management
$keys = apply_filters('ai_provider_api_keys', null);
```

### Self-Contained Provider Architecture
**Individual Provider Classes**:
- `AI_HTTP_OpenAI_Provider` - OpenAI Responses API
- `AI_HTTP_Anthropic_Provider` - Anthropic Messages API  
- `AI_HTTP_Gemini_Provider` - Google Gemini API
- `AI_HTTP_Grok_Provider` - xAI Grok API
- `AI_HTTP_OpenRouter_Provider` - OpenRouter API

**Key Design Principles**:
- Self-contained format conversion within each provider
- No external normalizers or utilities
- Filter-based registration for provider discovery
- Standardized interface across all providers

### Simplified Loading System
**Single Loading Strategy**:
- **Composer Autoloader Required** - No fallback loading
- Direct file inclusion for providers and filters
- Error logging if Composer not available

### Request Flow Architecture
```
Filter Request → Provider Class → API Call → Standardized Response

apply_filters('ai_request', null, $request_data, $provider)
├── Provider::request() -> Self-contained format conversion
├── Provider HTTP call via ai_http filter
└── Provider::format_response() -> Standard format return
```

**Simplified Flow**:
- Direct filter-based provider access
- Self-contained format conversion within providers
- No external normalizers or complex routing
- Standardized response format across all providers

## Key Implementation Patterns

### Error Handling Philosophy
- **Fail Fast**: No defaults, explicit configuration required
- **Clear Errors**: Provider-specific error messages with context
- **Graceful Degradation**: Plugins log errors rather than fatal exceptions

### WordPress Integration
- Uses `wp_remote_post()` for HTTP requests (with cURL fallback for streaming)
- WordPress options system for configuration storage
- Plugin-aware admin components with zero styling
- Follows WordPress coding standards and security practices

### Template System
**WordPress-Native Templates**:
- Template-based UI components using PHP includes
- Located in `src/templates/` directory
- Standard WordPress template variables and escaping
- Core templates: core.php

**Template Rendering**:
```php
// Template variables extracted from configuration
extract($template_vars);
include AI_HTTP_CLIENT_PATH . '/src/templates/core.php';
```

### Error Reporting
- **No Built-in Logging**: Library does not log by default - clean and lightweight
- **Hook-Based Errors**: Uses `ai_api_error` action hook for error reporting
- **Plugin Integration**: Consuming plugins choose how to handle errors
- **Raw Error Data**: Provides complete error context including provider responses
- **Production Ready**: No debug output unless plugins explicitly add logging

### Security Considerations
- API keys stored in WordPress options with proper sanitization
- No hardcoded credentials or defaults
- Input sanitization via WordPress functions
- Plugin context validation prevents unauthorized access

## Usage Patterns

### Filter-Based Access
```php
// Core filters for library access
$providers = apply_filters('ai_providers', []);
$result = apply_filters('ai_request', null, $request_data, $provider);
$models = apply_filters('ai_models', $provider, $config);
$api_keys = apply_filters('ai_provider_api_keys', null);
```

### Direct Provider Access
```php
// Get provider instance directly
$providers = apply_filters('ai_providers', []);
if (isset($providers['openai'])) {
    $provider_class = $providers['openai']['class'];
    $provider = new $provider_class($config);
    $result = $provider->request($standard_request);
}
```

## Optional Parameters & Model Compatibility

### Temperature Parameter
- **Support**: Optional across all providers
- **Default**: Not sent to API unless explicitly configured
- **Model Restrictions**: 
  - OpenAI reasoning models (o1*, o3*, o4*): Not supported - will cause API errors if sent
  - All other models: Supported (typically 0.0 - 2.0 range)
- **Recommendation**: Only configure if you need specific creativity control

### Max Tokens Parameter  
- **Support**: Optional across all providers
- **Default**: Not sent to API unless explicitly configured
- **Model Restrictions**:
  - OpenAI reasoning models (o1*, o3*, o4*): Not supported - will cause API errors if sent
  - Some models: Have different parameter names (auto-converted by providers)
- **Provider Handling**: Automatically converted to provider-specific format when provided

### Safe Usage Pattern
```php
// RECOMMENDED: Only include optional parameters when explicitly needed
$request = [
    'messages' => [['role' => 'user', 'content' => 'Hello']],
    'model' => 'gpt-4'  // Required parameters only
    // temperature: not included - uses provider default
    // max_tokens: not included - uses provider default
];

$response = apply_filters('ai_request', $request, 'openai');

// ADVANCED: Include optional parameters only when necessary
$request_with_options = [
    'messages' => [['role' => 'user', 'content' => 'Hello']],
    'model' => 'gpt-4',
    'temperature' => 0.7,  // Only include if you need specific temperature
    'max_tokens' => 1000   // Only include if you need token limit
];

// Library will only send parameters that are explicitly provided and not empty
$response = apply_filters('ai_request', $request_with_options, 'openai');
```

### Model-Specific Behavior
- **Traditional Models** (gpt-4, claude, gemini): Accept temperature and max_tokens
- **Reasoning Models** (o1*, o3*, o4*): Reject temperature and max_tokens with API errors
- **Provider Handling**: Library only sends parameters if explicitly provided and non-empty

## Distribution & Integration

### WordPress Plugin Integration
**Simple Include Pattern**:
```php
// In plugin main file
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// Usage via filters
$result = apply_filters('ai_request', null, [
    'messages' => [['role' => 'user', 'content' => 'Hello']],
    'model' => 'gpt-4',
    'max_tokens' => 100
], 'openai');
```

### Requirements
- Composer autoloader must be available
- WordPress environment for filter system
- Provider-specific API keys via filter configuration

### Production Deployment
**WordPress Configuration Requirements**:
- Ensure proper WordPress security settings and API key protection
- Verify all provider API keys are properly configured before deployment

## Error Handling & Logging

### ai_api_error Hook
The library provides a clean error handling hook instead of debug logging:

**Hook Structure**:
```php
do_action('ai_api_error', [
    'message' => 'Human-readable error message',
    'provider' => 'provider_name', // e.g., 'OpenAI', 'Anthropic'
    'error_type' => 'error_category', // 'api_error', 'connection_error', 'curl_error'
    'http_code' => 400, // For HTTP errors
    'response_body' => 'raw_response', // For API errors
    'details' => 'additional_context' // For connection errors
]);
```

**Error Types**:
- `api_error`: HTTP 4xx/5xx responses from providers (includes raw response body)
- `connection_error`: WordPress HTTP errors (timeouts, network issues)  
- `curl_error`: cURL failures in streaming requests
- `http_error`: HTTP status errors in streaming

**Usage Examples**:
```php
// Plugin integration - log to custom system
add_action('ai_api_error', function($error_data) {
    error_log("AI Error: " . $error_data['message']);
    // Or integrate with your plugin's logging system
    do_action('my_plugin_log', 'error', $error_data['message'], $error_data);
});

// Debug mode - detailed logging
add_action('ai_api_error', function($error_data) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("AI API Error Details: " . wp_json_encode($error_data));
    }
});
```

**Benefits**:
- ✅ No built-in logging - clean library by default
- ✅ Plugin developers choose how to handle errors
- ✅ Raw provider responses available for debugging
- ✅ Structured error data for programmatic handling
- ✅ Hook fires only on actual errors, not successful requests

## Current Version: 1.1.1

### Adding New Providers
1. Create provider class implementing standardized interface:
   - `is_configured()` - Check if provider has required configuration
   - `request($standard_request)` - Send non-streaming request with internal format conversion
   - `streaming_request($standard_request, $callback)` - Send streaming request
   - `get_raw_models()` - Retrieve available models
   - `upload_file($file_path, $purpose)` - Files API integration
2. Register provider via `ai_providers` WordPress filter
3. Self-contained format conversion within provider class
4. Add provider file to loading in `ai-http-client.php`

**Provider Implementation**: Self-contained classes with internal format conversion (~300-400 lines typical)

## Streaming & Advanced Features

### Streaming Support
- Uses cURL for real-time streaming responses via `ai_http` filter
- WordPress `wp_remote_post()` fallback for non-streaming requests
- Provider-specific streaming implementation within each provider class

### Tool/Function Calling
- Unified tool format across all providers
- Provider-specific tool normalization within each provider
- Self-contained tool format conversion (OpenAI vs Anthropic formats)

### File Upload System
- Files API integration for providers that support it
- Direct file uploads without base64 encoding
- Provider-specific file upload implementation

This simplified architecture enables WordPress plugin developers to integrate AI providers with minimal configuration while maintaining clean separation between providers and standardized responses.