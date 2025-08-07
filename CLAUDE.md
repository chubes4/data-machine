# CLAUDE.md

Data Machine is an AI-first WordPress plugin that transforms WordPress sites into content processing platforms through a Pipeline+Flow architecture and multi-provider AI integration.

## Core Architecture

**"Plugins Within Plugins"**: Self-registering component system with 100% filter-based dependencies, eliminating traditional dependency injection while maintaining modularity.

**Pipeline+Flow System**: Pipelines define reusable workflow templates, Flows execute configured instances with handler settings and scheduling.

**Filter-Based Design**: Every service uses `apply_filters()` with pure discovery patterns. Components self-register via dedicated `*Filters.php` files.

**Architectural Consistency**: Comprehensive cleanup eliminated legacy patterns, user-scoped code, and mixed architectural approaches. All components follow consistent admin-only patterns aligned with established standards.

**Two-Layer Architecture**:
- **Pipelines**: Reusable workflow templates with step sequences (positions 0-99)
- **Flows**: Configured instances with handler settings and scheduling (auto-created "Draft Flow" for new pipelines)

## AJAX Handler Architecture

**Specialized Handler Separation**: Clean separation between modal UI operations and page business logic:

- **PipelinePageAjax.php**: Business logic operations (add_step, delete_step, save_pipeline, delete_pipeline, delete_flow)
- **PipelineModalAjax.php**: UI/template operations (get_modal, get_template, configure-step-action, add-handler-action)

**Routing Logic**: PipelinesFilters.php routes based on action type:
```php
// Modal actions route to PipelineModalAjax
$modal_actions = ['get_modal', 'get_template', 'get_flow_step_card', 'get_flow_config', 
                  'configure-step-action', 'add-location-action', 'add-handler-action'];

if (in_array($action, $modal_actions)) {
    $ajax_handler = new PipelineModalAjax();
} else {
    $ajax_handler = new PipelinePageAjax();
}
```

**Benefits**:
- Clear separation between UI and business logic
- No method duplication across handlers
- Predictable routing based on action type

## Current Status

**Production Ready**: All core systems operational and validated.

**Features**: Pipeline+Flow architecture, multi-provider AI integration, filter-based dependencies, AJAX pipeline builder, universal modal system, template rendering, automatic "Draft Flow" creation, specialized AJAX handlers.

**Quality**: Zero known critical issues, robust security patterns, consistent architecture throughout.

## Database Schema

**Core Tables**:
- **wp_dm_jobs**: job_id, pipeline_id, flow_id, status, flow_config (longtext NULL), error_details (longtext NULL), created_at, started_at, completed_at
- **wp_dm_pipelines**: pipeline_id, pipeline_name, step_configuration (longtext NULL), created_at, updated_at
- **wp_dm_flows**: flow_id, pipeline_id, flow_name, flow_config (longtext NOT NULL), scheduling_config (longtext NOT NULL), created_at, updated_at
- **wp_dm_processed_items**: id, flow_id, source_type, item_identifier, processed_timestamp
- **wp_dm_remote_locations**: location_id, location_name, target_site_url, target_username, encrypted_password, synced_site_info (JSON), enabled_post_types (JSON), enabled_taxonomies (JSON), last_sync_time, created_at, updated_at

**Table Relationships**:
- Flows reference Pipelines (many-to-one): `flows.pipeline_id → pipelines.pipeline_id`
- Jobs reference Flows (many-to-one): `jobs.flow_id → flows.flow_id`
- Admin-only architecture: No user_id columns in any table

## Filter Reference

```php
// Core services - Direct discovery
$logger = apply_filters('dm_get_logger', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);
$encryption = apply_filters('dm_get_encryption_helper', null);
$scheduler = apply_filters('dm_get_action_scheduler', null);
$http_service = apply_filters('dm_get_http_service', null);
$constants = apply_filters('dm_get_constants', null);
$pipeline_context = apply_filters('dm_get_pipeline_context', null);

// Database services - Pure discovery with filtering
$all_databases = apply_filters('dm_get_database_services', []);
$db_jobs = $all_databases['jobs'] ?? null;
$db_flows = $all_databases['flows'] ?? null;
$db_pipelines = $all_databases['pipelines'] ?? null;
$db_processed_items = $all_databases['processed_items'] ?? null;
$db_remote_locations = $all_databases['remote_locations'] ?? null;

// Handler discovery - Pure discovery with type filtering
$all_handlers = apply_filters('dm_get_handlers', []);
$input_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'input');
$output_handlers = array_filter($all_handlers, fn($h) => ($h['type'] ?? '') === 'output');
$specific_handler = $all_handlers['twitter'] ?? null;

// Authentication - Pure discovery with filtering
$all_auth = apply_filters('dm_get_auth_providers', []);
$twitter_auth = $all_auth['twitter'] ?? null;

// Handler settings - Pure discovery with filtering  
$all_settings = apply_filters('dm_get_handler_settings', []);
$twitter_settings = $all_settings['twitter'] ?? null;

// Context services - Parameter-based discovery
$job_context = apply_filters('dm_get_context', null, $job_id);

// Step discovery - Pure discovery only
$all_steps = apply_filters('dm_get_steps', []);
$ai_step = $all_steps['ai'] ?? null;
$input_step = $all_steps['input'] ?? null;

// Step configuration - Pure discovery with context
$step_configs = apply_filters('dm_get_step_configs', []);
$step_config = $step_configs[$step_type] ?? null;

// DataPacket creation - Handler-based creation
$datapacket = apply_filters('dm_create_datapacket', null, $source_data, $source_type, $context);

// ProcessedItems service discovery
$processed_items_manager = apply_filters('dm_get_processed_items_manager', null);

// Job services - Pure discovery
$job_status_manager = apply_filters('dm_get_job_status_manager', null);
$job_creator = apply_filters('dm_get_job_creator', null);

// Additional AI services - Pure discovery
$all_ai_services = apply_filters('dm_get_ai_services', []);
$fluid_context_bridge = apply_filters('dm_get_fluid_context_bridge', null);
$ai_response_parser = apply_filters('dm_get_ai_response_parser', null);
$prompt_builder = apply_filters('dm_get_prompt_builder', null);

// Modal system - Pure discovery
$all_modals = apply_filters('dm_get_modals', []);
$step_selection_modal = $all_modals['step-selection'] ?? null;
$handler_settings_modal = $all_modals['handler-settings'] ?? null;
$confirmation_modal = $all_modals['confirmation'] ?? null;

// Admin page discovery - Pure discovery
$admin_pages = apply_filters('dm_get_admin_pages', []);
$jobs_page = $admin_pages['jobs'] ?? null;
$pipelines_page = $admin_pages['pipelines'] ?? null;

// Universal template rendering
$template_content = apply_filters('dm_render_template', '', 'modal/handler-settings-form', $data);

// Template requesting via AJAX - Uses dm_pipeline_ajax action with get_template sub-action
$template_html = apply_filters('dm_render_template', '', $template, $data);

// Admin page template rendering
$page_content = apply_filters('dm_render_template', '', 'page/jobs-page', $data);

// Admin pages discovered and managed by AdminMenuAssets.php
$all_pages = apply_filters('dm_get_admin_pages', []);
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

# Service Discovery Validation
define('WP_DEBUG', true); # Enable debugging
error_log('Database services: ' . print_r(apply_filters('dm_get_database_services', []), true));
error_log('Handlers: ' . print_r(apply_filters('dm_get_handlers', []), true));
error_log('Steps: ' . print_r(apply_filters('dm_get_steps', []), true));
```

## Components

**Core Services**: Logger (3-level system: debug, error, none with runtime configuration), Database, Orchestrator, AI Client (multi-provider: OpenAI, Anthropic, Google, Grok, OpenRouter with step-aware configuration), ActionScheduler

**Handlers**:
- Input: Files, Reddit, RSS, WordPress, Google Sheets (5 handlers)
- Output: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (6 handlers)
- Receiver: Webhook framework (stub implementation)
- Total: 11 active handlers with unified authentication and settings patterns

**Admin**: AJAX pipeline builder, job management, universal modal system, universal template rendering

**Key Principles**:
- Zero constructor injection - all services via `apply_filters()`
- Components self-register via `*Filters.php` files
- Engine agnostic - no hardcoded step types in `/inc/engine/`
- Position-based execution (0-99) with DataPacket flow
- Universal template rendering via filter-based discovery system

## Logger System

**Three-Level Logging**: Log system with configurable levels and runtime control.

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

## Context-Specific Step Card Templates

**Dedicated Template Architecture**: Step card rendering uses context-specific templates that provide specialized functionality for pipeline and flow contexts.

**Template Structure**:
- **Pipeline Context**: `pipeline-step-card.php` - Shows "Add Step" buttons, delete/configure actions, structural-only display
- **Flow Context**: `flow-step-card.php` - Shows "Add Handler" buttons, handler management UI, configuration details

**Template Usage Pattern**:
```php
// Pipeline step card
$content = apply_filters('dm_render_template', '', 'page/pipeline-step-card', [
    'step' => $step_data,
    'pipeline_id' => $pipeline_id,
    'is_first_step' => $is_first_step
]);

// Flow step card  
$content = apply_filters('dm_render_template', '', 'page/flow-step-card', [
    'step' => $step_data,
    'flow_id' => $flow_id,
    'flow_config' => $flow_config,
    'is_first_step' => $is_first_step
]);
```

**Consistent Container Architecture**: Both templates use `dm-step-container` wrapper with consistent data attributes and internal `dm-step-card` structure while providing context-specific functionality.

**Step Type Architecture**: 
- **AI Steps** (`'ai'`): No handlers, pipeline-level configuration only, `consume_all_packets: true`
- **Input/Output Steps** (`'input'`, `'output'`): Use handlers, flow-level handler selection and configuration
- **Handler Logic**: `$step_uses_handlers = ($step_type !== 'ai')` determines whether step shows handler management UI

## Arrow Rendering Architecture

**is_first_step Pattern**: Simplified arrow logic using binary flag for consistent rendering across all contexts.

**Universal Arrow Logic**: Single template pattern with context validation:
```php
// Universal arrow logic with contextual validation
if (!isset($is_first_step)) {
    if ($is_empty) {
        // Empty steps default to showing arrow
        $is_first_step = false;
    } else {
        // Populated steps require proper arrow logic
        throw new \InvalidArgumentException('Step card template requires is_first_step parameter for populated steps');
    }
}
if (!$is_first_step): ?>
    <div class="dm-step-arrow">
        <span class="dashicons dashicons-arrow-right-alt"></span>
    </div>
<?php endif;
```

**JavaScript Template Integration**: Calculate `is_first_step` when requesting templates:
```javascript
// Calculate is_first_step for template requests
const nonEmptySteps = $container.find('.dm-step:not(.dm-step-card--empty)').length;
const isFirstRealStep = nonEmptySteps === 0;

this.requestTemplate('page/pipeline-step-card', {
    step: stepData,
    is_first_step: isFirstRealStep,
    pipeline_id: this.pipelineId
});
```

## Template Rendering Architecture

**JavaScript Template Requesting**: Critical architectural pattern that eliminates HTML generation inconsistencies between initial page load and AJAX updates by maintaining PHP templates as the single source of HTML structure.

**Core Principle**: JavaScript requests pre-rendered templates from PHP instead of generating HTML directly, ensuring consistency across UI updates.

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

- **Eliminates Inconsistencies**: HTML structure identical between page load and AJAX updates
- **Single Source of Truth**: PHP templates control all HTML generation
- **Clean Separation**: JavaScript becomes pure DOM manipulation layer
- **Integration**: Seamless integration with `dm_render_template` filter system
- **Maintainability**: Template changes automatically apply to all contexts
- **Arrow Consistency**: Universal `is_first_step` pattern eliminates double arrows and positioning issues  

### JavaScript Architecture Principles

**Pure DOM Manipulation**: JavaScript handles only data processing and DOM insertion - never HTML generation
**Template Requesting**: All HTML comes from PHP templates via AJAX template requests
**Data-Driven Updates**: AJAX responses contain structured data, templates requested separately
**Consistency**: Identical rendering logic for initial load and dynamic updates

## Modal System

**Four-File Architecture**: Clean separation between universal modal lifecycle, content-specific interactions, page content management, and flow-specific operations.

### Core Components

**core-modal.js**: Universal modal lifecycle management only
- Modal open/close/loading states
- AJAX content loading via `wp_ajax_dm_get_modal_content` action
- Universal `.dm-modal-open` button handling with `data-template` and `data-context` attributes (uses `dm-modal-active` CSS class for state management)
- Focus management and accessibility
- Provides `dmCoreModal` API for programmatic modal operations

**pipeline-modal.js**: Pipeline-specific modal content interactions
- Handles buttons and forms created by PHP modal templates
- OAuth connections, tab switching, form submissions (legacy compatibility only)
- Visual feedback for card selections
- Triggers `dm-pipeline-modal-saved` events for page updates
- No modal opening/closing - only content interaction

**pipeline-builder.js**: Page content management for pipeline operations
- Handles data-attribute-driven actions (`data-template="add-step-action"`, `data-template="delete-action"`)
- **Direct Handler Action Pattern**: Handler configuration uses direct AJAX calls eliminating form submission complexity
- Manages pipeline state and UI updates via template requesting pattern
- Direct AJAX operations return data only - HTML via `requestTemplate()` method
- Arrow rendering handled by PHP templates with universal `is_first_step` logic
- Never calls modal APIs directly - clean separation maintained
- Pure DOM manipulation layer - zero HTML generation

**flow-builder.js**: Flow-specific operations and handler management
- Handles flow configuration management (`data-template="add-handler-action"`, `data-template="configure-step"`)
- Manages flow instances, handler configuration, and flow execution
- Direct data attribute handler for adding handlers to flow steps
- Add Flow button click handler and Run now button operations
- Flow deletion operations (`data-template="delete-action"`)
- Listens for handler saving events to refresh UI
- Specialized for flow-level operations while maintaining architectural consistency

### Data-Attribute Communication

Pipeline system uses data attributes for clean separation between modal content and page actions:

```javascript
// Modal content cards with data attributes trigger page actions
<div class="dm-step-selection-card dm-modal-close" 
     data-template="add-step-action"
     data-context='{"step_type":"input","pipeline_id":"123"}'>

// Direct handler action pattern - no form submission
<button class="button button-primary dm-modal-close" 
        data-template="add-handler-action"
        data-context='{"handler_slug":"twitter","step_type":"output","flow_id":"123"}'>

// Pipeline-builder.js listens for data-attribute clicks
$(document).on('click', '[data-template="add-step-action"]', this.handleAddStepAction.bind(this));
$(document).on('click', '[data-template="add-handler-action"]', this.handleAddHandlerAction.bind(this));
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
add_filter('dm_get_modals', function($modals) {
    $modals['my-modal'] = [
        'content' => apply_filters('dm_render_template', '', 'modal/my-modal', $context),
        'title' => __('My Modal', 'my-plugin')
    ];
    return $modals;
});
```

### Modal Lifecycle Improvements

**Automatic Modal Management**: Action buttons include `dm-modal-close` class for automatic modal dismissal after action completion. Modal state managed via `dm-modal-active` CSS class for accessibility and focus management.

**Universal Confirmation Modal**: Context-aware confirmation modal supports pipeline, step, and flow deletion with automatic action execution.

### Direct Action Handler Pattern

**Architectural Alignment**: Handler configuration implements direct action patterns consistent with established system architecture.

**Implementation Pattern**:
```javascript
// Direct handler action in pipeline-builder.js
handleAddHandlerAction: function(e) {
    const contextData = $(e.currentTarget).data('context');
    
    // Direct AJAX call with handler configuration
    $.ajax({
        url: dmPipelineBuilder.ajax_url,
        data: {
            action: 'dm_save_handler_settings',
            handler_slug: contextData.handler_slug,
            step_type: contextData.step_type,
            flow_id: contextData.flow_id
        }
    });
}
```

**Template Integration**:
```php
<!-- Direct action button in handler settings modal -->
<button type="button" class="button button-primary dm-modal-close" 
        data-template="add-handler-action"
        data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
    <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
</button>
```

### Universal Reusability

Any admin page can use the modal system by:
1. Including `core-modal.js` via admin page asset filter
2. Adding `.dm-modal-open` buttons in PHP templates with proper data attributes
3. Registering modal content via `dm_get_modal` filter
4. Zero page-specific modal JavaScript required

### AJAX Handler Routing

**Specialized Handler Selection**: PipelinesFilters.php implements clean routing logic based on action type for optimal separation of concerns:

```php
// Modal actions route to PipelineModalAjax
$modal_actions = [
    'get_modal', 'get_template', 'get_flow_step_card', 'get_flow_config',
    'configure-step-action', 'add-location-action', 'add-handler-action'
];

// Business logic actions route to PipelinePageAjax
if (in_array($action, $modal_actions)) {
    $ajax_handler = new PipelineModalAjax();
} else {
    $ajax_handler = new PipelinePageAjax();
}
```

**Architecture Benefits**:
- **Clear Separation**: Modal UI operations isolated from business logic
- **Predictable Routing**: Action type determines handler selection
- **No Method Duplication**: Each method exists in only one handler
- **Maintainability**: Changes scoped to appropriate handler context


## Critical Rules

**Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory  
**Discovery Pattern**: Prefer collection-based discovery `$all_services = apply_filters('dm_get_services', []); $service = $all_services[$key] ?? null;` for most services. Parameter-based filters are acceptable for context-specific services like `dm_get_context` which requires job_id parameter  
**Service Access**: Always use filter-based discovery patterns - never `new ServiceClass()` direct instantiation  
**Template Rendering**: Always use `apply_filters('dm_render_template', '', $template, $data)` - never direct template methods  
**Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)  
**CSS Namespace**: All admin CSS must use `dm-` prefix  

**Database Tables**:
- `wp_dm_pipelines`: Template definitions with step sequences
- `wp_dm_flows`: Configured instances with handler settings (auto-created \"Draft Flow\" for new pipelines)
- `wp_dm_jobs`: Execution records

## ProcessedItems Architecture

**Database Layer Location**: ProcessedItems system located in `/inc/core/database/ProcessedItems/` following consistent database component architecture.

**Components**:
- **ProcessedItems.php**: Core database operations and table management
- **ProcessedItemsManager.php**: Business logic and duplicate tracking services
- **ProcessedItemsFilters.php**: Self-registration via filter-based discovery patterns
- **ProcessedItemsOperations.php**: CRUD operations and data manipulation
- **ProcessedItemsQueries.php**: Query builders and data retrieval logic
- **ProcessedItemsCleanup.php**: Automated maintenance and cleanup operations

**Discovery Registration**: Uses standard database service pattern with manager service for business operations:
```php
// ProcessedItems discovery pattern
$all_databases = apply_filters('dm_get_database_services', []);
$processed_items_service = $all_databases['processed_items'] ?? null;
$processed_items_manager = apply_filters('dm_get_processed_items_manager', null);
```

## Pipeline+Flow Lifecycle

**Automatic Flow Creation**: Every new pipeline automatically creates a "Draft Flow" instance for immediate workflow execution.

## Flow Management

**Flow Operations**: Flows support deletion with cascade cleanup of associated jobs. Jobs table displays "Pipeline → Flow" format for clear relationship visibility.

## Component Registration

**Self-Registration Pattern**: Each component registers all services in dedicated `*Filters.php` files using consistent filter-based discovery patterns. See Critical Rules section for complete collection-based discovery guidelines.


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

**Unified Registration and Discovery**: Admin pages register with assets, templates, and configuration in a single filter, providing complete self-contained page definitions:

```php
// Complete admin page registration with all components
add_filter('dm_get_admin_pages', function($pages) {
    $pages['jobs'] = [
        'page_title' => __('Jobs', 'data-machine'),
        'menu_title' => __('Jobs', 'data-machine'),
        'capability' => 'manage_options',
        'position' => 20,
        'templates' => __DIR__ . '/templates/',
        'assets' => [
            'css' => ['dm-admin-jobs' => ['file' => 'path/to/style.css']],
            'js' => ['dm-jobs-admin' => ['file' => 'path/to/script.js', 'deps' => ['jquery']]]
        ]
    ];
    return $pages;
});

// Discovery and access pattern throughout plugin
$all_pages = apply_filters('dm_get_admin_pages', []);
$specific_page = $all_pages[$page_slug] ?? null;

// AdminMenuAssets automatically discovers and processes all registered pages
// Pages sorted by position, then alphabetically for consistent menu order
```

## Universal Template Rendering

**Filter-Based Template Discovery**: Templates are discovered from admin page registration and rendered through the universal `dm_render_template` filter system.

**Template Registration**: Admin pages register template directories via their `dm_get_admin_pages` filter configuration (see Admin Page Architecture section for complete registration examples).

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
                action: 'dm_pipeline_ajax',
                pipeline_action: 'get_template',
                template: template,
                template_data: JSON.stringify(data),
                nonce: this.nonce
            }
        }).then(response => response.data.html);
    }
    
    // Usage example with arrow logic
    addStepToUI(stepData) {
        // Calculate is_first_step for consistent arrow rendering
        const nonEmptySteps = $('.dm-pipeline-steps').find('.dm-step:not(.dm-step-card--empty)').length;
        const isFirstRealStep = nonEmptySteps === 0;
        
        // Context-specific step card template
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

