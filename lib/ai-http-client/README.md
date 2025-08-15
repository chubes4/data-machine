# AI HTTP Client for WordPress

WordPress-native AI provider integration via pure filter architecture.

**Features:**
- WordPress filter system integration
- Self-contained provider classes
- Shared API key storage
- Streaming support
- Dynamic model discovery
- Template-based UI components

## Installation

**Git Subtree** (recommended):
```bash
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

**Direct Download**: Place in `/lib/ai-http-client/`

**Requirements**: `composer install` in library directory

## Usage

**Include Library**:
```php
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

**Basic Request**:
```php
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']]
]);
```

**Advanced Options**:
```php
// Specific provider
$response = apply_filters('ai_request', $request, 'anthropic');

// With streaming
$response = apply_filters('ai_request', $request, null, $callback);

// With tools
$response = apply_filters('ai_request', $request, null, null, $tools);
```

## Providers

Dynamic model fetching from provider APIs:

- **OpenAI** - GPT models, streaming, function calling, Files API
- **Anthropic** - Claude models, streaming, function calling
- **Google Gemini** - Gemini models, streaming, function calling
- **Grok/X.AI** - Grok models, streaming
- **OpenRouter** - 200+ models via unified API

## Architecture

- **Filter-Based**: WordPress-native provider registration
- **Self-Contained**: Providers handle own formatting
- **Shared Storage**: Efficient API key management
- **WordPress-Native**: HTTP API, options, admin patterns
- **Extensible**: Third-party provider registration

### Multi-Plugin Support

- Plugin-isolated configurations
- Shared API key storage
- No provider conflicts
- Independent AI settings

### Components

- Filter-based provider discovery
- Self-contained provider classes
- Admin interface via `ai_render_component`
- Centralized API key storage

## Core Filters

```php
// Provider Discovery
$providers = apply_filters('ai_providers', []);

// API Keys
$keys = apply_filters('ai_provider_api_keys', null);

// Models
$models = apply_filters('ai_models', $provider, $config);

// Tools
$tools = apply_filters('ai_tools', []);

// Admin UI
echo apply_filters('ai_render_component', '', $config);
```

## Multi-Plugin Configuration

**Plugin-Isolated Settings**:
```php
ai_http_client_providers_myplugin = [
    'openai' => ['model' => 'gpt-4', 'temperature' => 0.7]
];
```

**Shared API Keys**:
```php
ai_http_client_shared_api_keys = ['openai' => 'sk-...'];
```

## AI Tools System

**Registration**:
```php
add_filter('ai_tools', function($tools) {
    $tools['file_processor'] = [
        'class' => 'FileProcessor_Tool',
        'category' => 'file_handling',
        'description' => 'Process files and extract content',
        'parameters' => ['file_path' => ['type' => 'string', 'required' => true]]
    ];
    return $tools;
});
```

**Discovery & Usage**:
```php
$all_tools = apply_filters('ai_tools', []);
$result = ai_http_execute_tool('file_processor', ['file_path' => '/path']);
```

## Distribution

- Composer package installation
- Git subtree integration
- No external dependencies
- Version conflict resolution
- Multiple plugin support

### Adding Providers

```php
class AI_HTTP_MyProvider {
    public function request($request) { /* ... */ }
    public function get_models() { /* ... */ }
}

add_filter('ai_providers', function($providers) {
    $providers['myprovider'] = ['class' => 'AI_HTTP_MyProvider', 'name' => 'My Provider'];
    return $providers;
});
```

## Version 1.2.0

**Core Features**:
- Filter-based provider registration
- Auto-fetch models from APIs
- Component-based admin UI
- Files API integration
- Streaming support
- Multi-plugin configuration

## Examples

- **Data Machine** - Automated content pipelines
- **AI Bot for bbPress** - Forum AI responses
- **WordSurf** - AI content editor

## Debug

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs: API requests, tool execution, streaming, system events

## Contributing

PRs welcome for new providers, performance improvements, WordPress compatibility.

## License

GPL v2 or later - **[Chris Huber](https://chubes.net)**
