# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for multi-type AI provider communication with plugin-scoped configuration.

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

### Multi-Type AI System
The library supports three AI types via a single unified interface:
- **LLM** (`ai_type: 'llm'`) - Text generation (OpenAI, Anthropic, Gemini, Grok, OpenRouter)
- **Upscaling** (`ai_type: 'upscaling'`) - Image upscaling (planned)
- **Generative** (`ai_type: 'generative'`) - Image generation (planned)

**Critical Rule**: ALL instantiation requires explicit `ai_type` parameter - no defaults exist.

### "Round Plug" Design Pattern
```
Standard Input → Unified Normalizers → Provider APIs → Standard Output
```

1. **Standard Input**: Unified request format across all providers
2. **Unified Normalizers**: Convert standard format to provider-specific formats
3. **Provider Classes**: Individual implementations with standardized interfaces
4. **Standard Output**: Unified response format regardless of provider

### Provider Architecture (Filter-Based Registration)
**Individual Provider Classes**:
- `AI_HTTP_OpenAI_Provider` - Bearer token + OpenAI-Organization headers
- `AI_HTTP_Anthropic_Provider` - x-api-key + anthropic-version headers
- `AI_HTTP_Gemini_Provider` - Model-in-URL pattern + x-goog-api-key
- `AI_HTTP_Grok_Provider` - Standard Bearer token
- `AI_HTTP_OpenRouter_Provider` - Bearer token + HTTP-Referer + X-Title

**Registration System**: Providers register via WordPress filters (`ai_providers`) enabling third-party provider registration without code modification.

### Multi-Plugin Isolation System
**Configuration Scoping**:
```php
// Plugin-specific settings (isolated)
ai_http_client_providers_myplugin_llm = [...provider configs...]
ai_http_client_selected_provider_myplugin_llm = 'openai'

// Shared API keys (efficient, no duplication)
ai_http_client_shared_api_keys = ['openai' => 'sk-...', 'anthropic' => 'sk-...']
```

**Benefits**:
- Multiple plugins can use different providers simultaneously
- API keys shared across all plugins for efficiency
- Zero configuration conflicts between plugins
- Each plugin maintains independent AI settings

### Request Flow Architecture
```
AI_HTTP_Client
├── init_normalizers_for_type() -> Routes to LLM/Upscaling/Generative normalizers
├── send_request()
│   ├── validate_request()
│   ├── get_provider() -> Creates/caches provider instance via filter-based discovery
│   ├── request_normalizer->normalize() -> Converts to provider format
│   ├── provider->send_raw_request() -> Pure API call
│   └── response_normalizer->normalize() -> Converts to standard format
└── Plugin-scoped OptionsManager -> Retrieves configuration by plugin_context + ai_type
```

### Loading System
**Dual Loading Strategy**:
1. **Composer Autoloader** (preferred for modern development)
2. **Manual Loading** (WordPress compatibility via `ai_http_client_manual_load()`)

**Version Conflict Resolution**:
- Multiple plugins can include different versions
- Highest version wins globally via `ai_http_client_version_check()`
- Prevents duplicate class loading

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

### Component System (Component-Owned Architecture)
**Modular UI Components**:
- `AI_HTTP_ProviderManager_Component` - Complete admin interface
- Core components: provider_selector, api_key_input, model_selector
- Extended components: temperature_slider, system_prompt_field

**Component Registry Pattern**:
```php
AI_HTTP_Component_Registry::get_component('provider_selector')->render($args)
```

**Recent Implementation**: Component-owned architecture with auto-save, auto-fetch models, and conditional save button features.

### Debug Logging
- **Conditional Logging**: Debug logs only appear when `WP_DEBUG` is `true` in WordPress configuration
- **Production Safety**: Prevents unnecessary log generation in production environments
- **Comprehensive Coverage**: Provides detailed information for:
  - API request/response cycles in providers (OpenAI, Anthropic, etc.)
  - Tool execution and validation in ToolExecutor
  - Streaming SSE events and connection handling in WordPressSSEHandler
  - System events during development and troubleshooting
- **WordPress Native**: Uses WordPress native `error_log()` function for consistent logging
- **Performance Optimized**: Debug checks use `defined('WP_DEBUG') && WP_DEBUG` pattern to minimize overhead

### Security Considerations
- API keys stored in WordPress options with proper sanitization
- No hardcoded credentials or defaults
- Input sanitization via WordPress functions
- Plugin context validation prevents unauthorized access

## Critical Configuration Requirements

### Client Instantiation
```php
// REQUIRED parameters - will fail without both
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',  // Plugin isolation
    'ai_type' => 'llm'                     // AI type routing
]);
```

### OptionsManager Usage
```php
// REQUIRED parameters - both plugin_context and ai_type required
$options = new AI_HTTP_Options_Manager('my-plugin-slug', 'llm');
```

### Component Rendering
```php
// REQUIRED parameters for admin UI
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm'
]);
```

## Distribution & Integration

### WordPress Plugin Integration
**Pure WordPress Filter Pattern** (Recommended):
```php
// In plugin main file
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// Usage anywhere in plugin - auto-discovers plugin context
$client = apply_filters('ai_client', null);           // Get AI client
$config = apply_filters('ai_config', null);           // Get global config
$step_config = apply_filters('ai_config', $step_id);  // Get step-specific config
```

**Legacy Pattern** (Manual Parameters):
```php
// Old approach - no longer needed
$client = new AI_HTTP_Client([
    'plugin_context' => 'my-plugin-slug',
    'ai_type' => 'llm'
]);
```

### Version Management
- Library manages its own versioning and conflicts
- Multiple plugins can safely include different versions
- No external dependencies beyond WordPress

### Production Deployment
**WordPress Configuration Requirements**:
- **Set `WP_DEBUG` to `false`** in production environments to disable debug logging
- Ensure proper WordPress security settings and API key protection
- Verify all provider API keys are properly configured before deployment

**Recent Features**:
- Auto-save settings functionality
- Auto-fetch models from providers
- Conditional save button display
- Component-owned architecture for UI consistency

## Current Version: 1.2.0

### Usage Patterns
**Recommended (Auto-Discovery)**:
- `apply_filters('ai_client', null)` - Returns configured AI client
- `apply_filters('ai_config', null)` - Returns global configuration  
- `apply_filters('ai_config', $step_id)` - Returns step-specific configuration

**Legacy (Manual Parameters)**:
- `AI_HTTP_Client(['plugin_context' => '...', 'ai_type' => '...'])` - Manual instantiation
- `AI_HTTP_Options_Manager($plugin_context, $ai_type)` - Manual options access

**Auto-Discovery**: Plugin context automatically detected from call stack, AI type defaults to 'llm'.

## Architecture Scalability

### Multi-Type Readiness
Current architecture supports future AI types:
- `src/Normalizers/LLM/` - Text AI normalizers (implemented)
- `src/Normalizers/Upscaling/` - Image upscaling normalizers (planned)
- `src/Normalizers/Generative/` - Image generation normalizers (planned)
- `src/Providers/LLM/` - Text AI providers (implemented)
- `src/Providers/Upscaling/` - Image upscaling providers (planned)
- `src/Providers/Generative/` - Image generation providers (planned)

### Adding New Providers
1. Create provider class implementing standardized interface methods:
   - `is_configured()` - Check if provider has required configuration
   - `send_raw_request($provider_request)` - Send non-streaming request
   - `send_raw_streaming_request($provider_request, $callback)` - Send streaming request
   - `get_raw_models()` - Retrieve available models
   - `upload_file($file_path, $purpose)` - Files API integration
2. Register provider via `ai_providers` WordPress filter in `src/Filters.php`
3. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
4. Update manual loading in `ai-http-client.php`

**Provider Implementation**: Individual classes with standardized interface (~220 lines typical)

## Streaming & Advanced Features

### Streaming Support
- Uses cURL for real-time streaming responses
- WordPress-native fallback for non-streaming requests
- Provider-agnostic streaming via unified normalizers

### Tool/Function Calling
- Unified tool format across all providers
- Provider-specific tool normalization (OpenAI vs Anthropic formats)
- Continuation support for multi-turn tool interactions

### Step-Aware Configuration
- Plugin-specific step configurations for different AI use cases
- Automatic prompt/parameter injection based on step context
- Dynamic tool enabling per step

### File Upload System
- Files API integration across all providers
- NO base64 encoding - direct file uploads
- Multi-provider file upload support

This architecture enables WordPress plugin developers to integrate multiple AI providers with minimal code while maintaining complete isolation between plugins and AI types.