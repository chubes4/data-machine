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

**Known Issues**: Expanding PHPUnit test coverage across components. Modal system architecture is complete and documented.

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

**Three-File Architecture**: Clean separation between universal modal lifecycle, content-specific interactions, and page content management.

### Core Components

**core-modal.js**: Universal modal lifecycle management only
- Modal open/close/loading states
- AJAX content loading via `dm_get_modal_content` action
- Universal `.dm-modal-open` button handling with `data-template` and `data-context` attributes
- Focus management and accessibility
- Provides `dmCoreModal` API for programmatic modal operations

**pipeline-modal.js**: Pipeline-specific modal content interactions
- Handles buttons and forms created by PHP modal templates
- OAuth connections, tab switching, form submissions
- Visual feedback for card selections
- Triggers `dm-pipeline-modal-saved` events for page updates
- No modal opening/closing - only content interaction

**pipeline-builder.js**: Page content management only
- Handles data-attribute-driven actions (`data-template="add-step-action"`, `data-template="delete-action"`)
- Manages pipeline state and UI updates
- Direct AJAX operations for pipeline operations
- Never calls modal APIs directly - clean separation maintained

### Data-Attribute Communication

Pipeline system uses data attributes for clean separation between modal content and page actions:

```javascript
// Modal content cards with data attributes trigger page actions
<div class="dm-step-selection-card dm-modal-close" 
     data-template="add-step-action"
     data-context='{"step_type":"input","pipeline_id":"123"}'>

// Pipeline-builder.js listens for data-attribute clicks
$(document).on('click', '[data-template="add-step-action"]', this.handleAddStepAction.bind(this));
$(document).on('click', '[data-template="delete-action"]', this.handleDeleteAction.bind(this));
```

### PHP Template Integration

Automatic modal triggers using data attributes - no JavaScript required:

```php
<!-- Trigger button in PHP template -->
<button type="button" class="button dm-modal-open" 
        data-template="step-selection"
        data-context='{"pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
    <?php esc_html_e('Add Step', 'data-machine'); ?>
</button>
```

**Critical**: Individual modal cards need context data attributes, not just containers:

```php
<!-- CORRECT: Each card has required data -->
<div class="dm-step-selection-card" 
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">

<!-- INCORRECT: Only container has context -->
<div class="dm-container" data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">
    <div class="dm-step-selection-card" data-step-type="<?php echo esc_attr($step_type); ?>">
```

### Modal Content Registration

```php
add_filter('dm_get_modal', function($content, $template) {
    if ($template === 'my-modal') {
        return $this->render_template('modal/my-modal', $context);
    }
    return $content;
}, 10, 2);
```

### Universal Reusability

Any admin page can use the modal system by:
1. Including `core-modal.js` via admin page asset filter
2. Adding `.dm-modal-open` buttons in PHP templates with proper data attributes
3. Registering modal content via `dm_get_modal` filter
4. Zero page-specific modal JavaScript required


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
        return $this->render_template('modal/my-modal', $context);
    }
    return $content;
}, 10, 2);
```

**Modal Trigger in PHP Template**:
```php
<button type="button" class="button dm-modal-open" 
        data-template="my-modal"
        data-context='{"item_id":"<?php echo esc_attr($item_id); ?>"}'>
    <?php esc_html_e('Open Modal', 'my-plugin'); ?>
</button>
```

**Page-Modal Communication**:
```javascript
// Modal content with data attributes triggers page actions
<div class="dm-selection-card dm-modal-close" 
     data-template="my-action"
     data-context='{"item_id":"123","action_type":"process"}'>

// Page script listens for data-attribute actions
$(document).on('click', '[data-template="my-action"]', function(e) {
    const contextData = $(e.currentTarget).data('context');
    // Handle page action with context data
    console.log('Modal triggered action:', contextData);
});

// Optional: Modal form events for complex interactions
$(document).trigger('dm-pipeline-modal-saved', [responseData]);
```

**Service Override**:
```php
add_filter('dm_get_orchestrator', function($service) {
    return new MyPlugin\CustomOrchestrator();
}, 20);
```