# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for multi-type AI provider communication with plugin-scoped configuration.

## Development Commands

### Testing & Analysis
```bash
# Run PHPUnit tests
composer test

# Run PHPStan static analysis (level 5)
composer analyse

# Run both tests and analysis
composer check

# Check PHP syntax for individual files
php -l src/Providers/LLM/openai.php
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
3. **Provider Classes**: Pure API communication (recently refactored with Base_LLM_Provider)
4. **Standard Output**: Unified response format regardless of provider

### Provider Architecture (Recently Refactored)
**Base Class Hierarchy**:
- `Base_LLM_Provider` - Common HTTP operations, auth patterns, error handling
- Provider-specific classes extend base class with minimal customization:
  - `AI_HTTP_OpenAI_Provider` - OpenAI Responses API + organization headers
  - `AI_HTTP_Anthropic_Provider` - x-api-key + anthropic-version headers
  - `AI_HTTP_Gemini_Provider` - Model-in-URL pattern + x-goog-api-key
  - `AI_HTTP_Grok_Provider` - Standard Bearer token (simplest implementation)
  - `AI_HTTP_OpenRouter_Provider` - Bearer token + HTTP-Referer + X-Title

**Refactoring Impact**: Eliminated 330+ lines of duplicated code (31% reduction) while maintaining 100% API compatibility.

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
│   ├── get_provider() -> Creates/caches provider instance from Base_LLM_Provider
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

### Component System
**Modular UI Components**:
- `AI_HTTP_ProviderManager_Component` - Complete admin interface
- Core components: provider_selector, api_key_input, model_selector, test_connection
- Extended components: temperature_slider, system_prompt_field, max_tokens_input

**Component Registry Pattern**:
```php
AI_HTTP_Component_Registry::get_component('provider_selector')->render($args)
```

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
// REQUIRED parameters - constructor updated in v2.x.x
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
**Recommended Pattern** (like Action Scheduler):
```php
// In plugin main file
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// Usage in plugin
$client = new AI_HTTP_Client([
    'plugin_context' => get_option('stylesheet'), // or plugin slug
    'ai_type' => 'llm'
]);
```

### Version Management
- Library manages its own versioning and conflicts
- Multiple plugins can safely include different versions
- No external dependencies beyond WordPress

## Breaking Changes History

### v2.x.x - AI Type Scoping
**Constructor Changes**:
- `AI_HTTP_Options_Manager($plugin_context, $ai_type)` - added required ai_type parameter
- `AI_HTTP_Client(['plugin_context' => '...', 'ai_type' => '...'])` - added required ai_type
- All component rendering requires ai_type parameter

**Migration**: Add 'llm' as second parameter for existing LLM functionality.

## Architecture Scalability

### Multi-Type Readiness
Current architecture supports future AI types:
- `src/Normalizers/LLM/` - Text AI normalizers (implemented)
- `src/Normalizers/Upscaling/` - Image upscaling normalizers (planned)
- `src/Normalizers/Generative/` - Image generation normalizers (planned)
- `src/Providers/LLM/` - Text AI providers (implemented with Base_LLM_Provider)
- `src/Providers/Upscaling/` - Image upscaling providers (planned)
- `src/Providers/Generative/` - Image generation providers (planned)

### Adding New Providers
1. Create provider class extending `Base_LLM_Provider`
2. Override `get_default_base_url()`, `get_provider_name()`, `get_auth_headers()`
3. Implement `send_raw_request()`, `send_raw_streaming_request()`, `get_raw_models()`
4. Add provider routing to `AI_HTTP_Client::create_llm_provider()`
5. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
6. Update manual loading in `ai-http-client.php`

**Provider Requirements**: ~80 lines of code (vs ~220 before base class refactoring)

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

This architecture enables WordPress plugin developers to integrate multiple AI providers with minimal code while maintaining complete isolation between plugins and AI types.