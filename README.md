# Data Machine

AI-first WordPress plugin for content processing workflows. Visual pipeline builder with multi-provider AI integration.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- **Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter
- **Visual Pipeline Builder**: AJAX-driven workflow construction
- **Sequential Processing**: Chain AI models with cumulative context
- **Content Publishing**: Facebook, Twitter, Threads, WordPress, Bluesky, Google Sheets
- **Filter Architecture**: WordPress-native extensibility patterns
- **Two-Layer Design**: Pipeline templates + Flow instances

## Pipeline+Flow Architecture

**Pipeline Template**:
```
Step 1: Fetch (RSS)     → Content source
Step 2: Fetch (Reddit)  → Additional context  
Step 3: AI (Analysis)   → Process all inputs
Step 4: AI (Summary)    → Create final content
Step 5: Publish         → Distribute content
```

**Flow Instances**:
```
Flow A: Daily Tech News
├── RSS: TechCrunch
├── Reddit: r/technology
├── AI: GPT-4 analysis
├── AI: Claude summary
└── Publish: Twitter

Flow B: Weekly Gaming
├── RSS: Gaming news
├── Reddit: r/gaming  
├── AI: Same pipeline config
└── Publish: Facebook
```

**Architecture**:
- **Pipeline**: Reusable step templates
- **Flow**: Configured handler instances
- **AI Steps**: Pipeline-level configuration
- **Handlers**: Flow-level configuration

## Quick Start

### Installation
1. Clone repository to `/wp-content/plugins/data-machine/`
2. Run `composer install`
3. Activate plugin in WordPress admin
4. Configure AI provider in Data Machine → Settings

### Your First Pipeline+Flow
1. **Create Pipeline**: Data Machine → Pipelines → Create New
   - Auto-creates "Draft Flow" instance
   - Add steps via modal interface
   - Configure step settings
2. **Configure Flow**: Customize handlers and scheduling
   - Set RSS feeds, AI models, publish targets
   - Configure timing (daily, weekly, manual)
3. **Execute**: Run flow and monitor results

## Architecture

**Two-Layer System**:
- **Pipelines**: Reusable step templates (positions 0-99)
- **Flows**: Configured handler instances with scheduling

**Processing**:
- **Sequential Execution**: Steps run in order within each flow
- **Context Accumulation**: Each step receives ALL previous data
- **DataPacket Flow**: Uniform data contract between steps

```php
public function execute(string $flow_step_id, array $data, array $step_config): array {
    // Process data packets
    foreach ($data as $packet) {
        $content = $packet->content['body'];
    }
    return $data; // Updated data packet array
}
```

## Filter-Based Architecture

Pure discovery patterns with collection-based component registration:

```php
// Core actions
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
do_action('dm_run_flow_now', $flow_id);

// Service discovery
$all_databases = apply_filters('dm_db', []);
$all_handlers = apply_filters('dm_handlers', []);
$all_steps = apply_filters('dm_steps', []);

// HTTP requests
$response = apply_filters('dm_request', null, 'POST', $url, $args, 'Context');

// Template rendering
$content = apply_filters('dm_render_template', '', 'modal/settings', $data);
```

## Key Features

**Multi-Source Processing**: Sequential data collection from RSS, Reddit, WordPress, files with cumulative context.

**Multi-AI Workflows**: Chain different AI providers (GPT-4 → Claude → Gemini) with full context preservation.

**Universal Modal System**: Filter-based modals with template discovery and WordPress security.

**AJAX Pipeline Builder**: Real-time step configuration with handler discovery and validation.

### Handlers

**Fetch**: Files, Reddit, RSS, WordPress, Google Sheets
**Publish**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets  
**AI**: OpenAI, Anthropic, Google, Grok, OpenRouter



## Examples

### Service Discovery

```php
// Core actions
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
do_action('dm_run_flow_now', $flow_id);

// Service discovery
$all_databases = apply_filters('dm_db', []);
$all_handlers = apply_filters('dm_handlers', []);
$all_auth = apply_filters('dm_auth_providers', []);

// Type filtering
$fetch_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'fetch');
```




### Handler Examples


```php
// Handler registration
add_filter('dm_handlers', function($handlers) {
    $handlers['my_handler'] = [
        'type' => 'fetch',
        'class' => \MyPlugin\Handlers\MyFetchHandler::class,
        'label' => __('My Handler', 'my-plugin')
    ];
    return $handlers;
});
```




### Modal Registration

```php
add_filter('dm_modals', function($modals) {
    $modals['my-modal'] = [
        'template' => 'modal/my-modal',
        'title' => __('My Modal', 'my-plugin')
    ];
    return $modals;
});
```





### AJAX Integration

```javascript
requestTemplate(templateName, templateData) {
    return $.ajax({
        url: this.ajax_url, type: 'POST',
        data: {
            action: 'dm_get_template',
            template: templateName,
            template_data: JSON.stringify(templateData),
            nonce: this.nonce
        }
    }).then(response => response.data.html);
}
```


## Extension Development

### Custom Handler

```php
class MyFetchHandler {
    public function fetch_data(array $step_config): array {
        do_action('dm_log', 'debug', 'Fetching data');
        // Custom fetch logic
        return $data_packets;
    }
}

add_filter('dm_handlers', function($handlers) {
    $handlers['my_handler'] = [
        'type' => 'fetch',
        'class' => \MyPlugin\Handlers\MyFetchHandler::class,
        'label' => __('My Handler', 'my-plugin')
    ];
    return $handlers;
});
```

### Custom Step

```php
add_filter('dm_steps', function($steps) {
    $steps['custom_processing'] = [
        'label' => __('Custom Processing', 'my-plugin'),
        'class' => '\MyPlugin\Steps\CustomProcessingStep'
    ];
    return $steps;
});

class CustomProcessingStep {
    public function execute(int $job_id, array $data, array $step_config): array {
        do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
        // Custom processing logic
        return $data;
    }
}
```

## AI Integration

**Providers**: OpenAI, Anthropic, Google, Grok, OpenRouter

**Sequential Processing**: Chain different models with cumulative context.

```php
$ai_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);
```


## Development

**Requirements**: WordPress 5.0+, PHP 8.0+, Composer

**Setup**:
```bash
composer install && composer dump-autoload
composer test
```

**Debugging**:
```javascript
window.dmDebugMode = true;  // Browser debugging
```

```php
define('WP_DEBUG', true);  // PHP debugging
do_action('dm_log', 'debug', 'Processing step', ['job_id' => $job_id]);
```




## License

**GPL v2+** - [License](https://www.gnu.org/licenses/gpl-2.0.html)

**Developer**: [Chris Huber](https://chubes.net)
**Documentation**: `CLAUDE.md`