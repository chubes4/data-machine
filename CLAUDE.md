# CLAUDE.md

Data Machine is an AI-first WordPress plugin that transforms WordPress sites into content processing platforms through a Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**"Plugins Within Plugins"**: Self-registering component system with 100% filter-based dependencies, eliminating traditional dependency injection while maintaining modularity.

**Pipeline+Flow System**: Pipelines define reusable workflow templates, Flows execute configured instances with handler settings and scheduling.

**Filter-Based Design**: Every service uses `apply_filters()` with parameter-based discovery. Components self-register via dedicated `*Filters.php` files.

**Two-Layer Architecture**:
- **Pipelines**: Reusable workflow templates with step sequences (positions 0-99)
- **Flows**: Configured instances with handler settings and scheduling

## Current Status

**Completed**: Core Pipeline+Flow architecture, universal AI integration, filter-based dependencies, AJAX pipeline builder, universal modal system, production deployment.

**Known Issues**: Expanding PHPUnit test coverage across components.

**Future Plans**: Webhook integration (Receiver Step), enhanced testing, additional platform integrations.

## Filter Reference

```php
// Core services
$logger = apply_filters('dm_get_logger', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);

// Parameter-based services
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$handlers = apply_filters('dm_get_handlers', null, 'output');
$auth = apply_filters('dm_get_auth', null, 'twitter');
$context = apply_filters('dm_get_context', null, $job_id);

// Step discovery (dual-mode)
$all_steps = apply_filters('dm_get_steps', []);              // All step types
$step_config = apply_filters('dm_get_steps', null, 'input'); // Specific type

// Modal system
$modal_content = apply_filters('dm_get_modal', null, 'step-selection');
```

## Development Commands

```bash
# Setup
composer install && composer dump-autoload

# Testing
composer test                # All PHPUnit tests
composer test:unit           # Unit tests only
composer test:integration    # Integration tests only
composer test:coverage       # HTML coverage report

# AI HTTP Client (subtree)
cd lib/ai-http-client/ && composer test && composer analyse

# Debugging
window.dmDebugMode = true;   # Browser debugging
define('WP_DEBUG', true);    # Enable conditional error_log

# Common fixes
composer dump-autoload      # Fix class loading
php -l file.php             # Syntax check
```

## Components

**Core Services**: Logger, Database, Orchestrator, AI Client (multi-provider: OpenAI, Anthropic, Google, Grok, OpenRouter)

**Handlers**:
- Input: Files, Reddit, RSS, WordPress, Google Sheets
- Output: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets  
- Receiver: Webhook framework (stub implementation)

**Admin**: AJAX pipeline builder, job management, universal modal system

**Key Principles**:
- Zero constructor injection - all services via `apply_filters()`
- Components self-register via `*Filters.php` files
- Engine agnostic - no hardcoded step types in `/inc/engine/`
- Position-based execution (0-99) with DataPacket flow

## DataPacket Structure

Universal data contract between pipeline steps:
```php
[
    'content' => ['body' => $content, 'title' => $title],
    'metadata' => ['source' => $source, 'timestamp' => $time],
    'context' => ['job_id' => $id, 'step_position' => $pos]
]
```

**Step Implementation**:
```php
class MyStep {
    public function execute(int $job_id, array $data_arrays = []): bool {
        foreach ($data_arrays as $data) {
            $content = $data['content']['body'] ?? '';
            // Process content
        }
        return true;
    }
}
```

## Modal System

**Universal Architecture**: Server-side PHP templates via `dm_get_modal` filter with single AJAX endpoint.

**Usage**:
```javascript
dmCoreModal.open('step-selection', { pipeline_id: 123 });
```

**Registration**:
```php
add_filter('dm_get_modal', function($content, $template) {
    if ($template === 'my-modal') {
        return $this->render_template('modal/my-modal', $context);
    }
    return $content;
}, 10, 2);
```


## Critical Rules

**Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory  
**Service Access**: Always use `apply_filters('dm_get_service', null)` - never `new ServiceClass()`  
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)  
**CSS Namespace**: All admin CSS must use `dm-` prefix  

**Database Tables**:
- `wp_dm_pipelines`: Template definitions with step sequences
- `wp_dm_flows`: Configured instances with handler settings  
- `wp_dm_jobs`: Execution records

## Component Registration

**Self-Registration Pattern**: Each component registers all services in dedicated `*Filters.php` files:

```php
function dm_register_twitter_filters() {
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['twitter'] = ['class' => Twitter::class, 'label' => __('Twitter', 'data-machine')];
        }
        return $handlers;
    }, 10, 2);
    
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        return ($handler_slug === 'twitter') ? new TwitterAuth() : $auth;
    }, 10, 2);
}
dm_register_twitter_filters();
```


## Extension Examples

**Custom Handler**:
```php
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input') {
        $handlers['my_handler'] = [
            'class' => \MyPlugin\MyHandler::class,
            'label' => __('My Handler', 'my-plugin')
        ];
    }
    return $handlers;
}, 10, 2);

add_filter('dm_get_auth', function($auth, $handler_slug) {
    return ($handler_slug === 'my_handler') ? new \MyPlugin\MyAuth() : $auth;
}, 10, 2);
```

**Custom Step**:
```php
class MyStep {
    public function execute(int $job_id, array $data_arrays = []): bool {
        // Process data
        return true;
    }
}

add_filter('dm_get_steps', function($config, $step_type) {
    if ($step_type === 'my_step') {
        return ['label' => __('My Step'), 'class' => '\MyPlugin\MyStep'];
    }
    return $config;
}, 10, 2);
```

**Admin Page**:
```php
add_filter('dm_get_admin_page', function($config, $page_slug) {
    if ($page_slug === 'my_page') {
        return [
            'page_title' => __('My Page'),
            'content_callback' => [new MyPage(), 'render'],
            'assets' => [
                'css' => ['my-css' => ['file' => 'path/to/style.css']],
                'js' => ['my-js' => ['file' => 'path/to/script.js', 'deps' => ['jquery']]]
            ]
        ];
    }
    return $config;
}, 10, 2);
```

**Modal Content**:
```php
add_filter('dm_get_modal', function($content, $template) {
    if ($template === 'my-modal') {
        return '<div>My modal content</div>';
    }
}, 10, 2);
```

**Service Override**:
```php
add_filter('dm_get_orchestrator', function($service) {
    return new MyPlugin\CustomOrchestrator();
}, 20);
```