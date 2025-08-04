# CLAUDE.md

Data Machine is an AI-first WordPress plugin that transforms WordPress sites into content processing platforms through a Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**"Plugins Within Plugins"**: Self-registering component system with 100% filter-based dependencies, eliminating traditional dependency injection while maintaining modularity.

**Pipeline+Flow System**: Pipelines define reusable workflow templates, Flows execute configured instances with handler settings and scheduling.

**Filter-Based Design**: Every service uses `apply_filters()` with parameter-based discovery. Components self-register via dedicated `*Filters.php` files.

**Two-Layer Architecture**:
- **Pipelines**: Reusable workflow templates with step sequences (positions 0-99)
- **Flows**: Configured instances with handler settings and scheduling (auto-created "Draft Flow" for new pipelines)

## Current Status

**Completed**: Core Pipeline+Flow architecture, universal AI integration, filter-based dependencies, AJAX pipeline builder, universal modal system, universal template rendering system, automatic "Draft Flow" creation, **universal step card template system with context-aware rendering**, **arrow rendering architecture with universal is_first_step pattern**, enhanced logger system with runtime configuration, flow deletion functionality, modal system improvements, template requesting architecture, admin page direct template rendering pattern, production deployment.

**Known Issues**: Expanding PHPUnit test coverage across components. Flows database schema contains references to user_id field that was removed - flows are now admin-only in this implementation.

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

// Universal template rendering
$template_content = apply_filters('dm_render_template', '', 'modal/handler-settings-form', $data);

// Template requesting via AJAX
$template_html = apply_filters('dm_get_template', '', 'page/pipeline-step-card', $data);
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

**Core Services**: Logger (3-level system: debug, error, none with runtime configuration), Database, Orchestrator, AI Client (multi-provider: OpenAI, Anthropic, Google, Grok, OpenRouter)

**Handlers**:
- Input: Files, Reddit, RSS, WordPress, Google Sheets
- Output: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets  
- Receiver: Webhook framework (stub implementation)

**Admin**: AJAX pipeline builder, job management, universal modal system, universal template rendering

**Key Principles**:
- Zero constructor injection - all services via `apply_filters()`
- Components self-register via `*Filters.php` files
- Engine agnostic - no hardcoded step types in `/inc/engine/`
- Position-based execution (0-99) with DataPacket flow
- Universal template rendering via filter-based discovery system

## Logger System

**Three-Level Logging**: Simplified system with configurable levels and runtime control.

**Log Levels**:
- `debug`: Full logging (all debug, info, warning, error, critical messages)
- `error`: Problems only (warning, error, critical messages)  
- `none`: Disable logging completely

**Runtime Configuration**:
```php
$logger = apply_filters('dm_get_logger', null);

// Get current level
$current_level = $logger->get_level(); // Returns 'debug', 'error', or 'none'

// Set new level
$logger->set_level('error'); // Changes effective immediately

// Available levels
$levels = Logger::get_available_log_levels(); // Returns array with descriptions
```

**Default Configuration**: Log level defaults to `error` (problems only) for production environments while providing full debugging capability when needed.

**Log Management**:
```php
// File operations
$log_path = $logger->get_log_file_path();
$log_size = $logger->get_log_file_size(); // Returns MB
$recent_logs = $logger->get_recent_logs(100); // Last 100 lines
$logger->clear_logs(); // Remove all log files
$logger->cleanup_log_files(10, 30); // Auto-cleanup based on size/age
```

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

## Universal Step Card Template System

**Single Template Architecture**: Consolidated step card rendering using one universal template (`step-card.php`) that handles both pipeline and flow contexts through a `context` parameter, eliminating template duplication and ensuring perfect consistency.

**Context-Aware Rendering**: Template dynamically adapts UI elements, actions, and content based on context:
- **Pipeline Context**: Shows "Add Step" buttons, delete/configure actions, structural-only display
- **Flow Context**: Shows "Add Handler" buttons, handler management UI, configuration details

**Template Usage Pattern**:
```php
// Pipeline step card
$content = apply_filters('dm_render_template', '', 'page/step-card', [
    'step' => $step_data,
    'context' => 'pipeline',
    'pipeline_id' => $pipeline_id,
    'is_first_step' => $is_first_step
]);

// Flow step card  
$content = apply_filters('dm_render_template', '', 'page/step-card', [
    'step' => $step_data,
    'context' => 'flow', 
    'flow_id' => $flow_id,
    'flow_config' => $flow_config,
    'is_first_step' => $is_first_step
]);
```

**Universal Container Architecture**: Uses `dm-step-container` wrapper with consistent data attributes and internal `dm-step-card` structure that adapts content based on context while maintaining identical arrow and layout logic.

**Handler Discovery Integration**: Flow context uses parameter-based filter discovery (`apply_filters('dm_get_handlers', null, $step_type)`) for dynamic handler availability, while pipeline context focuses on step configuration discovery.

## Arrow Rendering Architecture

**Universal is_first_step Pattern**: Simplified arrow logic that eliminates positioning complexity and ensures perfect consistency across all contexts.

**Core Arrow Logic**: Every step uses the same universal pattern:
- **First step in container**: `is_first_step: true` → **NO arrow**
- **All other steps**: `is_first_step: false` → **arrow rendered**
- **Empty steps at end**: `is_first_step: false` → **arrow** (shows flow continuation)

### Universal Template Implementation

**Single Template Arrow Logic**: Universal `step-card.php` template uses simplified arrow pattern with WordPress dashicons:
```php
<?php
// Universal arrow logic - before every step except the very first step in the container
$is_first_step = $is_first_step ?? false;
if (!$is_first_step): ?>
    <div class="dm-step-arrow">
        <span class="dashicons dashicons-arrow-right-alt"></span>
    </div>
<?php endif; ?>
```

### JavaScript is_first_step Calculation

**Consistent Logic for Template Requests**:
```javascript
// Check if this is the first real step (only empty steps exist)
const nonEmptySteps = $container.find('.dm-step:not(.dm-step-card--empty)').length;
const isFirstRealStep = nonEmptySteps === 0;

// Pass to template for consistent arrow rendering
this.requestTemplate('page/step-card', {
    step: stepData,
    is_first_step: isFirstRealStep,
    pipeline_id: this.pipelineId
});
```

### Architecture Benefits

✅ **Eliminates Double Arrows**: No complex position calculations or index tracking  
✅ **Universal Pattern**: Same logic works for pipelines, flows, and all contexts  
✅ **Perfect Consistency**: Initial page load and AJAX updates identical  
✅ **Simplified Logic**: Binary `is_first_step` replaces complex step_index calculations  
✅ **Empty Step Support**: Handles empty steps at end with proper flow continuation arrows  
✅ **Single Template**: Universal `step-card.php` consolidates all arrow rendering logic
✅ **WordPress Integration**: Uses native dashicons for consistent styling

### Critical Implementation Rules

**Always Calculate is_first_step**: JavaScript must calculate this value when requesting step templates
**Universal Template**: Single `step-card.php` template handles all step contexts via `context` parameter
**Template Pattern**: Universal template uses `if (!$is_first_step)` for arrow rendering
**No Position Math**: Never use step positions, indices, or counts for arrow logic
**Context Awareness**: Template adapts UI and actions based on 'pipeline' or 'flow' context parameter

## Template Rendering Architecture

**JavaScript Template Requesting**: Critical architectural pattern that eliminates HTML generation inconsistencies between initial page load and AJAX updates by maintaining PHP templates as the single source of HTML structure.

**Core Principle**: JavaScript requests pre-rendered templates from PHP instead of generating HTML directly, ensuring perfect consistency across all UI updates.

### Template Requesting Pattern

**JavaScript Template Requests with Arrow Logic**:
```javascript
// New architecture - JavaScript requests templates with data
// Calculate is_first_step for consistent arrow rendering
const nonEmptySteps = $container.find('.dm-step:not(.dm-step-card--empty)').length;
const isFirstRealStep = nonEmptySteps === 0;

// Universal step card template with context awareness
this.requestTemplate('page/step-card', {
    step: stepData,
    context: 'pipeline', // or 'flow'
    pipeline_id: pipelineId,
    is_first_step: isFirstRealStep  // Critical for arrow consistency
}).then((stepHtml) => {
    // Insert rendered template into DOM
    $(container).append(stepHtml);
});

// Universal template requesting method
requestTemplate(templateName, templateData) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: dmPipelineBuilder.ajax_url,
            type: 'POST',
            data: {
                action: 'dm_pipeline_ajax',
                pipeline_action: 'get_template',
                template: templateName,
                template_data: JSON.stringify(templateData),
                nonce: dmPipelineBuilder.pipeline_ajax_nonce
            },
            success: (response) => {
                if (response.success) {
                    resolve(response.data.html);
                } else {
                    reject(response.data.message);
                }
            },
            error: (xhr, status, error) => {
                reject(error);
            }
        });
    });
}
```

**AJAX Handler Data-Only Responses**:
```php
// AJAX handlers return structured data only
public function add_step_to_pipeline() {
    // Process step addition logic
    wp_send_json_success([
        'step_data' => $step_data,  // NOT step_html
        'message' => __('Step added successfully', 'data-machine')
    ]);
}

public function add_flow_to_pipeline() {
    // Process flow addition logic  
    wp_send_json_success([
        'flow_data' => $flow_data,  // NOT flow_card_html
        'message' => __('Flow added successfully', 'data-machine')
    ]);
}
```

**Template Rendering Endpoint**:
```php
public function get_template() {
    $template = sanitize_text_field($_POST['template']);
    $data = json_decode(wp_unslash($_POST['data']), true);
    
    $html = apply_filters('dm_render_template', '', $template, $data);
    
    wp_send_json_success(['html' => $html]);
}
```

### Architecture Benefits

✅ **Eliminates Inconsistencies**: HTML structure identical between page load and AJAX updates  
✅ **Single Source of Truth**: PHP templates control all HTML generation  
✅ **Clean Separation**: JavaScript becomes pure DOM manipulation layer  
✅ **Perfect Integration**: Seamless integration with `dm_render_template` filter system  
✅ **Maintainability**: Template changes automatically apply to all contexts  
✅ **Arrow Consistency**: Universal `is_first_step` pattern eliminates double arrows and positioning issues  

### JavaScript Architecture Principles

**Pure DOM Manipulation**: JavaScript handles only data processing and DOM insertion - never HTML generation
**Template Requesting**: All HTML comes from PHP templates via AJAX template requests
**Data-Driven Updates**: AJAX responses contain structured data, templates requested separately
**Consistency Guarantee**: Identical rendering logic for initial load and dynamic updates

## Modal System

**Three-File Architecture**: Clean separation between universal modal lifecycle, content-specific interactions, and page content management.

### Core Components

**core-modal.js**: Universal modal lifecycle management only
- Modal open/close/loading states
- AJAX content loading via `dm_get_modal_content` action
- Universal `.dm-modal-open` button handling with `data-template` and `data-context` attributes (uses `dm-modal-active` CSS class for state management)
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
- Manages pipeline state and UI updates via template requesting pattern
- Direct AJAX operations return data only - HTML via `requestTemplate()` method
- Arrow rendering handled by PHP templates with universal `is_first_step` logic
- Never calls modal APIs directly - clean separation maintained
- Pure DOM manipulation layer - zero HTML generation

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
        return apply_filters('dm_render_template', '', 'modal/my-modal', $context);
    }
    return $content;
}, 10, 2);
```

### Modal Lifecycle Improvements

**Automatic Modal Closure**: Action buttons (like delete confirmations) now include `dm-modal-close` class for automatic modal dismissal after action completion, eliminating manual modal management in JavaScript. Modal state managed via `dm-modal-active` CSS class for enhanced accessibility and focus management.

**Enhanced Confirm-Delete Modal**: Universal confirmation modal supporting pipeline, step, AND flow deletion with context-aware messaging and automatic action execution upon confirmation.

### Universal Reusability

Any admin page can use the modal system by:
1. Including `core-modal.js` via admin page asset filter
2. Adding `.dm-modal-open` buttons in PHP templates with proper data attributes
3. Registering modal content via `dm_get_modal` filter
4. Zero page-specific modal JavaScript required


## Critical Rules

**Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory  
**Service Access**: Always use `apply_filters('dm_get_service', null)` - never `new ServiceClass()`  
**Template Rendering**: Always use `apply_filters('dm_render_template', '', $template, $data)` - never direct template methods  
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)  
**CSS Namespace**: All admin CSS must use `dm-` prefix  

**Database Tables**:
- `wp_dm_pipelines`: Template definitions with step sequences
- `wp_dm_flows`: Configured instances with handler settings (auto-created \"Draft Flow\" for new pipelines)
- `wp_dm_jobs`: Execution records

## Pipeline+Flow Lifecycle

**Automatic Flow Creation**: Every new pipeline automatically creates a \"Draft Flow\" instance, eliminating empty state complexity and providing immediate workflow execution capability.

**Pipeline Creation Workflow**:
1. User creates new pipeline template via \"Add New Pipeline\" action
2. System automatically generates \"Draft Flow\" instance for immediate use
3. Flow inherits pipeline structure but maintains independent configuration
4. Additional flows can be created manually for different configurations

**Template Architecture Migration**:
- **Arrow Rendering**: Moved from JavaScript HTML generation to PHP templates with universal `is_first_step` logic
- **Modal Content**: Eliminated hardcoded placeholder HTML in favor of universal template system
- **Step Cards**: All HTML generation now handled by PHP templates via `dm_render_template` filter
- **Template Requesting**: JavaScript requests pre-rendered templates instead of generating HTML
- **AJAX Consistency**: Perfect HTML consistency between initial page load and AJAX updates
- **Arrow Consistency**: Universal `is_first_step` pattern eliminates double arrows and positioning issues

## Flow Management System

**Complete Flow Deletion**: Flows can be deleted with confirmation modals and cascade cleanup of associated jobs.

**Jobs Table Enhancement**: Displays "Pipeline → Flow" format instead of generic "Module" for clear relationship visibility.

**Flow Operations**:
```php
// Flow deletion with cascade cleanup
$db_flows = apply_filters('dm_get_database_service', null, 'flows');
$success = $db_flows->delete_flow($flow_id); // Automatically removes associated jobs

// Flow-aware job display
// Jobs table now shows clear pipeline/flow relationships for better tracking
```

**Enhanced Error Handling**: Improved status display and error details for all job states (running/pending/failed) with better tracking of pipeline/flow relationships.

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


## Admin Page Architecture

**Direct Template Rendering Pattern**: AdminMenuAssets.php uses standardized template name pattern for unified page rendering without content_callback dependencies.

**Template Name Convention**: Admin pages render using pattern `"page/{$page_slug}-page"`:
```php
// AdminMenuAssets.php - Direct template rendering
private function render_admin_page_content($page_config, $page_slug) {
    $content = apply_filters('dm_render_template', '', "page/{$page_slug}-page", [
        'page_slug' => $page_slug,
        'page_config' => $page_config
    ]);
    
    if (!empty($content)) {
        echo $content;
    } else {
        // Default empty state
        echo '<div class="wrap"><h1>' . esc_html($page_config['page_title'] ?? ucfirst($page_slug)) . '</h1>';
        echo '<p>' . esc_html__('Page content not configured.', 'data-machine') . '</p></div>';
    }
}
```

**Unified Asset Management**: Admin pages register assets directly in their filter configuration, eliminating separate asset management systems:
```php
add_filter('dm_get_admin_page', function($config, $page_slug) {
    if ($page_slug === 'jobs') {
        return [
            'page_title' => __('Jobs', 'data-machine'),
            'templates' => __DIR__ . '/templates/', // Template discovery
            'assets' => [
                'css' => ['dm-admin-jobs' => ['file' => 'path/to/style.css']],
                'js' => ['dm-jobs-admin' => ['file' => 'path/to/script.js', 'deps' => ['jquery']]]
            ]
        ];
    }
    return $config;
}, 10, 2);
```

**Parameter-Based Discovery**: AdminMenuAssets discovers pages dynamically via parameter-based filters, maintaining architectural consistency with all other services.

## Universal Template Rendering

**Filter-Based Template Discovery**: Templates are discovered from admin page registration and rendered through the universal `dm_render_template` filter system.

**Template Registration**: Admin pages register template directories through the `dm_get_admin_page` filter:

```php
add_filter('dm_get_admin_page', function($config, $page_slug) {
    if ($page_slug === 'my_page') {
        return [
            'page_title' => __('My Page'),
            'content_callback' => [new MyPage(), 'render'],
            'templates' => __DIR__ . '/templates/', // Template directory registration
            'assets' => [...]
        ];
    }
    return $config;
}, 10, 2);
```

**Template Usage**: Components use the universal filter for all template rendering:

```php
// Universal template rendering - discovers from registered admin pages
$content = apply_filters('dm_render_template', '', 'modal/handler-settings-form', [
    'handler_slug' => 'twitter',
    'settings_data' => $settings
]);

// Template discovery searches all registered admin page template directories
// Returns error message if template not found in any registered location
```

**Template Discovery Process**:
1. Filter searches all registered admin page template directories
2. Constructs full path: `{template_dir}/{template_name}.php`
3. Returns rendered content with extracted data variables
4. Displays error if template not found in any location

**Critical**: NEVER use legacy `render_template()` methods - always use the universal filter system.

### Template Requesting in JavaScript

**JavaScript Template Integration**: Use the template requesting pattern for all dynamic HTML updates:

```javascript
// Template requesting in page scripts
class PipelineBuilder {
    requestTemplate(template, data) {
        return $.ajax({
            url: ajaxurl,
            method: 'POST', 
            data: {
                action: 'dm_get_template',
                template: template,
                data: JSON.stringify(data),
                _ajax_nonce: this.nonce
            }
        }).then(response => response.data.html);
    }
    
    // Usage example with arrow logic
    addStepToUI(stepData) {
        // Calculate is_first_step for consistent arrow rendering
        const nonEmptySteps = $('.dm-pipeline-steps').find('.dm-step:not(.dm-step-card--empty)').length;
        const isFirstRealStep = nonEmptySteps === 0;
        
        this.requestTemplate('page/pipeline-step-card', {
            step: stepData,
            pipeline_id: this.pipelineId,
            is_first_step: isFirstRealStep  // Critical for arrow consistency
        }).then(stepHtml => {
            $('.dm-pipeline-steps').append(stepHtml);
        });
    }
}
```

**AJAX Handler Pattern**: Return data only, never HTML:

```php
public function ajax_handler() {
    // Process business logic
    $result_data = $this->process_action();
    
    // Return structured data only - NO HTML
    wp_send_json_success([
        'data' => $result_data,
        'message' => __('Action completed', 'data-machine')
    ]);
    
    // JavaScript will request template separately:
    // this.requestTemplate('page/result-card', result_data)
}
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
            'templates' => __DIR__ . '/templates/', // Required for template rendering
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
        return apply_filters('dm_render_template', '', 'modal/my-modal', $context);
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
    
    // Process action and request template for UI update
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: { action: 'my_action', context: contextData }
    }).then(response => {
        // Request template with response data
        return this.requestTemplate('page/result-card', response.data);
    }).then(html => {
        // Insert rendered template
        $(container).append(html);
    });
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