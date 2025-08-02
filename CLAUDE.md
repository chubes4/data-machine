# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Data Machine is an AI-first WordPress plugin that transforms any WordPress site into a Universal Content Processing Platform. Built entirely on WordPress-native patterns, it enables sophisticated AI workflows through a two-layer Pipeline+Flow architecture and multi-provider AI integration (OpenAI, Anthropic, Google, Grok, OpenRouter).

**Architecture**: The plugin implements a "Plugins Within Plugins" architecture - a self-registering component system that eliminates traditional dependency injection while maintaining complete modularity. The Pipeline+Flow architecture separates reusable workflow templates (Pipelines) from configured instances (Flows), enabling template reuse and independent workflow execution.

**Technical Features**: Position-based orchestration (0-99), universal DataPacket contracts, static service caching with lazy loading, parameter-based service discovery, and dynamic asset loading - all implemented through 100% filter-based dependencies.

## Pipeline+Flow Architecture

### Core Concept
**Two-Layer System**: Pipelines define reusable workflow templates, Flows execute configured instances.

**Pipeline** (Template Layer):
- Reusable workflow definition with step sequence
- Contains `step_configuration` (array of step types and positions)
- No handler-specific configuration or scheduling
- Pure template for workflow structure

**Flow** (Instance Layer):
- Configured execution of a specific pipeline
- Contains `flow_config` (handler settings per step)
- Contains `scheduling_config` (timing and triggers)
- Links to `pipeline_id` for template structure

### Real-World Example
```
Pipeline: "Social Media Content Processing"
├── Step 1: Input Handler (position 0)
├── Step 2: AI Handler (position 1)
└── Step 3: Output Handler (position 2)

Flow A: Tech News (Daily)
├── RSS: TechCrunch feeds
├── AI: GPT-4 analysis
└── Output: Twitter @tech_account

Flow B: Gaming News (Weekly)
├── RSS: Gaming feeds
├── AI: Claude creative writing
└── Output: Facebook gaming page

Flow C: Custom Content (Manual)
├── Files: User uploads
├── AI: User-selected model
└── Output: Multiple platforms
```

## CURRENT STATUS & KNOWN ISSUES

### Recently Completed
- ✅ **Core Architecture**: Pipeline+Flow system, universal AI integration, engine agnosticism, modular components (23+)
- ✅ **Filter System**: 100% filter-based dependencies, universal DataPacket system, component-owned assets
- ✅ **Handler Integration**: Reorganized step-specific directories, Bluesky AT Protocol, bi-directional Google Sheets
- ✅ **Pipeline Builder**: AJAX-driven interface, dynamic step discovery, organized template structure
- ✅ **Universal Modal System**: Filter-based modal architecture with zero hardcoded types and complete component autonomy

### Known Issues  
- **Limited Testing Coverage**: Initial PHPUnit infrastructure established with Unit and Integration test suites
- **Ongoing Test Development**: Continuous expansion of test coverage for core components
- **Debug Logging Active**: Extensive `error_log()` calls throughout codebase should be conditional on WP_DEBUG or removed for production

### Future Plans
- **Webhook Integration**: Complete Receiver Step implementation with real-time data reception
- **Enhanced Testing**: Expand PHPUnit coverage across all components
- **Performance Optimization**: Conditional debug logging and additional caching strategies
- **API Documentation**: Comprehensive developer API reference
- **Third-Party Integrations**: Additional social media and content management platforms

## Quick Reference

### Core Filter Patterns
```php
// Core services
$logger = apply_filters('dm_get_logger', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);

// Parameter-based services (consistent architecture)
$db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
$handlers = apply_filters('dm_get_handlers', null, 'output');
$auth = apply_filters('dm_get_auth', null, 'twitter');
$settings = apply_filters('dm_get_handler_settings', null, 'twitter');
$context = apply_filters('dm_get_context', null, $job_id);
$page_config = apply_filters('dm_get_admin_page', null, 'jobs');
$page_assets = apply_filters('dm_get_page_assets', null, 'jobs');

// Dual-mode step discovery (critical for modal system)
$all_steps = apply_filters('dm_get_steps', []);           // Discovery mode - all step types
$step_config = apply_filters('dm_get_steps', null, 'input'); // Specific mode - single step type

// Universal Modal System
$modal_content = apply_filters('dm_get_modal_content', null, 'step-selection');
$step_config_modal = apply_filters('dm_get_step_config_modal', null, 'input', $context);
```

### Development Commands
```bash
# Plugin Setup
composer install && composer dump-autoload

# Testing Commands (Main Plugin)
composer test                # Run all PHPUnit tests
composer test:unit           # Run only unit tests  
composer test:integration    # Run only integration tests
composer test:coverage       # Generate HTML test coverage report
composer test:verbose        # Run tests with verbose output

# AI HTTP Client Testing (Subtree Library)
cd lib/ai-http-client/
composer test      # PHPUnit tests
composer analyse   # PHPStan static analysis (level 5)  
composer check     # Both tests and analysis
cd ../..

# Debugging
window.dmDebugMode = true;  # Browser debugging (enables AJAX and modal debugging)
define('WP_DEBUG', true);   # WordPress debugging (enables extensive error_log output)

# Pipeline Builder Development
# Template structure: /inc/core/admin/pages/pipelines/templates/modal/ and /templates/page/
# AJAX endpoints: wp_ajax_dm_pipeline_ajax (pipeline operations), wp_ajax_dm_get_modal_content (universal modal)
# Nonce verification: dm_pipeline_ajax (pipelines), dm_get_modal_content (modals)

# Common Fixes
composer dump-autoload     # Fix "Class not found" errors
php -l file.php            # Check PHP syntax

# Git Subtree Operations (AI HTTP Client)
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
git subtree push --prefix=lib/ai-http-client origin main  # Push changes back
```

## Architecture Overview

### Core Components
- **Input Handlers**: Files, Reddit, RSS, WordPress, Google Sheets (located in `/inc/core/steps/input/handlers/`)
- **Output Handlers**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (located in `/inc/core/steps/output/handlers/`)
- **Receiver Step**: Stub framework for future webhook integrations - demonstrates step registration pattern (located in `/inc/core/steps/receiver/`)
- **AI Integration**: Multi-provider client (OpenAI, Anthropic, Google, Grok, OpenRouter) with streaming and tool calling
- **Core Services**: Logger, Database (Jobs/Pipelines/Flows/ProcessedItems/RemoteLocations), Orchestrator, Security
- **Admin Interface**: Production-quality AJAX-driven pipeline builder, job management, universal modal system with organized template architecture

### Filter-Based Architecture
**Zero Constructor Injection**: Every service access uses `apply_filters()` with parameter-based discovery, providing modularity while maintaining WordPress compatibility. Bootstrap sequence loads 5 core services + 4 component autoloading functions, then components self-register via dedicated `*Filters.php` files.

**Simple Instance Creation**: Each filter call creates fresh instances - no caching complexity, prioritizing clean code over micro-optimizations.

### Handler System - Intelligent Parameter Matching
Handlers register as configuration arrays with automatic auth/settings linking via parameter matching. The system uses slug-based association to automatically connect handlers with their authentication and settings providers:

```php
// Handler registration
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'output') {
        $handlers['twitter'] = ['class' => Twitter::class, 'label' => __('Twitter', 'data-machine')];
    }
    return $handlers;
}, 10, 2);

// Auto-linked auth
add_filter('dm_get_auth', function($auth, $handler_slug) {
    return ($handler_slug === 'twitter') ? new TwitterAuth() : $auth;
}, 10, 2);
```

### Engine Architecture - Pure Orchestration
**Engine Agnostic**: `/inc/engine/` contains zero hardcoded step/handler assumptions. ProcessingOrchestrator uses position-based execution (0-99) with DataPacket flow between steps. The engine discovers components dynamically through filters.

**"Plugins Within Plugins"**: Architecture where components are completely self-contained with dedicated `*Filters.php` files. Each component registers its own services, handlers, auth providers, and assets - creating true modularity without traditional dependency injection.

**Position-Based Orchestration**: Steps execute in numerical order (0-99) with DataPacket transformation between each step, enabling complex AI workflows with guaranteed execution sequence.

### Admin Architecture
**Simple Callback Pattern**: All admin pages use `dm_admin_page_callback` function with parameter-based discovery and clean content filter system.

```php
// Page registration via *Filters.php (parameter-based pattern)
add_filter('dm_get_admin_page', function($config, $page_slug) {
    if ($page_slug === 'my_page') {
        return [
            'page_title' => __('My Page', 'plugin'),
            'menu_title' => __('My Page', 'plugin'),
            'capability' => 'manage_options',
            'position' => 25
        ];
    }
    return $config;
}, 10, 2);

// Content provision via filter
add_filter('dm_render_admin_page', function($content, $page_slug) {
    if ($page_slug === 'my_page') {
        $instance = new MyPageClass();
        ob_start();
        $instance->render_content();
        return ob_get_clean();
    }
    return $content;
}, 10, 2);
```

**Flow**: Page Registration → AdminMenuAssets → Simple Callback → dm_admin_page_callback() → Content Filter → Page Content

### Testing Architecture

**PHPUnit Test Suite**: Comprehensive testing framework with two primary test types
- **Unit Tests** (`tests/Unit/`): Test individual components and functions in isolation
- **Integration Tests** (`tests/Integration/`): Test component interactions and WordPress environment integration

**Key Testing Features**:
- PSR-4 autoloading for test namespaces (`DataMachine\Tests\`)
- Separate test suites for granular testing control
- WordPress debug environment configuration
- HTML coverage report generation (`composer test:coverage`)
- Yoast PHPUnit Polyfills for WordPress compatibility

**Test Directory Structure**:
```
tests/
├── Unit/                  # Isolated component tests
├── Integration/           # WordPress environment tests
├── Mock/                  # Mock objects and test helpers
└── bootstrap.php          # PHPUnit test environment setup
```

**Testing Best Practices**:
- Focus on testing critical paths and business logic
- Mock external dependencies (APIs, WordPress core functions)
- Maintain test isolation and minimize side effects
- Use WordPress-specific testing patterns for hooks and filters
- Complement the AI HTTP Client's comprehensive test suite

### Universal DataPacket Contract
**Engine-Exclusive Processing**: DataPacket provides universal data transformation between pipeline steps with standardized `content`, `metadata`, and `context` structure. Components receive simple arrays while engine handles sophisticated data flow:

**DataPacket Structure**:
```php
[
    'content' => ['body' => $content, 'title' => $title],
    'metadata' => ['source' => $source, 'timestamp' => $time],
    'context' => ['job_id' => $id, 'step_position' => $pos]
]
```

```php
class MyCustomStep {
    public function execute(int $job_id, array $data_arrays = []): bool {
        foreach ($data_arrays as $data) {
            $content = $data['content']['body'] ?? '';
            $metadata = $data['metadata'] ?? [];
        }
        return true;
    }
}
```

### Universal Modal System Architecture - Filter-Based Design

**Core Design**: 100% filter-based modal content generation with zero hardcoded modal types, providing extensibility through WordPress filter patterns. Any component can register modal content via the `dm_get_modal_content` filter without modifying core modal code.

**Architectural Principles**:
- **Template-Based Interface**: Modals identified by template names (e.g., "step-selection", "delete-step", "handler-selection") rather than component IDs
- **Pure Filter Discovery**: Zero hardcoding - all content generated via filter system with consistent 2-parameter pattern
- **Standard WordPress AJAX**: Single `ModalAjax.php` processes all modal requests with standard WordPress security
- **Component Autonomy**: Each component registers its own modal content generators independently via *Filters.php files
- **Infrastructure-Only Core**: Modal system provides pure infrastructure with zero business logic

**Dual-Mode Step Discovery**: The `dm_get_steps` filter operates in two distinct modes enabling both UI generation and configuration lookups:
- **Discovery Mode**: `apply_filters('dm_get_steps', [])` - Returns ALL registered step types for UI generation
- **Specific Mode**: `apply_filters('dm_get_steps', null, 'input')` - Returns specific step type configuration

**WordPress Security Implementation**:
- **Nonce Verification**: `check_ajax_referer('dm_get_modal_content', 'nonce', false)` - standard WordPress AJAX security
- **Capability Checks**: `current_user_can('manage_options')` - standard WordPress capability check
- **Input Sanitization**: `sanitize_text_field(wp_unslash($_POST['template']))` - standard WordPress sanitization
- **Context Parsing**: JSON parsing with fallback to empty array on malformed data
- **Parameter Validation**: Basic validation before filter execution

**Filter-Based Content Generation Pattern**:
```php
// Component registers modal content via consistent 2-parameter filter pattern
add_filter('dm_get_modal_content', function($content, $template) {
    switch ($template) {
        case 'step-selection':
            // Discovery Mode - gets ALL registered step types
            $all_steps = apply_filters('dm_get_steps', []);
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            
            return $this->render_template('modal/step-selection-cards', array_merge($context, [
                'all_steps' => $all_steps
            ]));
            
        case 'handler-selection':
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            $step_type = $context['step_type'] ?? 'unknown';
            
            // Parameter-based handler discovery
            $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
            
            return $this->render_template('modal/handler-selection-cards', [
                'step_type' => $step_type,
                'handlers' => $available_handlers
            ]);
            
        case 'delete-step':
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            // Enhanced context with affected flows analysis
            $affected_flows = $this->get_affected_flows($context['pipeline_id'] ?? null);
            
            return $this->render_template('modal/delete-step-warning', array_merge($context, [
                'affected_flows' => $affected_flows
            ]));
    }
    return $content;
}, 10, 2);
```

**Template Architecture Reorganization**: Clean separation of concerns with organized directory structure:
- **Modal Templates** (`/inc/core/admin/pages/pipelines/templates/modal/`):
  - `step-selection-cards.php` - Dynamic step type discovery with real-time handler availability
  - `handler-selection-cards.php` - Grid-based handler selection with button-style interface  
  - `handler-settings-form.php` - Handler-specific configuration forms
  - `delete-step-warning.php` - Comprehensive deletion warnings with affected flows analysis
- **Page Templates** (`/inc/core/admin/pages/pipelines/templates/page/`):
  - `pipeline-step-card.php` - Individual step cards with drag-and-drop functionality
  - `flow-instance-card.php` - Flow configuration cards with pipeline template linking
  - `new-pipeline-card.php` - Pipeline creation interface
- **Pure Rendering Focus**: Templates contain zero business logic - all data provided via filter context

**WordPress AJAX Integration**:
- **Endpoint**: `wp_ajax_dm_get_modal_content` handled by `ModalAjax.php`
- **Security**: Standard WordPress nonce verification and capability checks
- **Parameter Structure**: 
  - `template` (string) - Modal template identifier (required)
  - `context` (JSON string) - Component-specific parameters (optional, defaults to '{}')
  - `nonce` (string) - Security verification token (required)
- **Response Format**: WordPress standard `wp_send_json_success()` and `wp_send_json_error()` with consistent structure
- **Error Handling**: Comprehensive error states with user-friendly messaging and detailed logging

**JavaScript Integration Pattern**:
```javascript
// Universal modal trigger - any component can use with consistent interface
dmCoreModal.open('step-selection', {
    pipeline_id: 123,
    step_type: 'input',
    title: 'Select Step Type'  // Optional title override
});

// Advanced modal with custom context
dmCoreModal.open('configure-step', {
    step_type: 'ai',
    pipeline_id: 456,
    step_position: 1,
    existing_config: { model: 'gpt-4', temperature: 0.7 }
});

// WordPress object preservation (critical for debugging)
window.dmCoreModal = window.dmCoreModal || {};
Object.assign(window.dmCoreModal, {
    // Extends WordPress-localized data without overwriting
    ajax_url: dmCoreModal.ajax_url,
    get_modal_content_nonce: dmCoreModal.get_modal_content_nonce,
    strings: dmCoreModal.strings
});
```

**Component Self-Registration Infrastructure**: Modal system achieves complete modularity through infrastructure-only implementation:
```php
// ModalFilters.php - Pure infrastructure with zero component knowledge
function dm_register_modal_system_filters() {
    // Universal asset registration for modal-enabled pages
    add_filter('dm_get_page_assets', function($assets, $page_slug) {
        $modal_pages = ['pipelines', 'jobs', 'logs', 'settings'];
        
        if (in_array($page_slug, $modal_pages)) {
            $assets['css']['dm-core-modal'] = [
                'file' => 'inc/core/admin/modal/assets/css/core-modal.css',
                'deps' => [],
                'media' => 'all'
            ];
            
            $assets['js']['dm-core-modal'] = [
                'file' => 'inc/core/admin/modal/assets/js/core-modal.js',
                'deps' => ['jquery'],
                'in_footer' => true,
                'localize' => [
                    'object' => 'dmCoreModal',
                    'data' => [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'get_modal_content_nonce' => wp_create_nonce('dm_get_modal_content'),
                        'strings' => [
                            'loading' => __('Loading...', 'data-machine'),
                            'error' => __('Error', 'data-machine'),
                            'close' => __('Close', 'data-machine')
                        ]
                    ]
                ]
            ];
        }
        return $assets;
    }, 5, 2); // Priority 5 loads before component assets
    
    // Universal AJAX handler registration
    $modal_ajax_handler = new ModalAjax();
}
```

**Extension Pattern**: Use `dm_get_modal_content` filter to register custom modal content with template-based routing.

**Implementation Notes**:
- Method visibility: `render_template()` changed to public for filter access
- Type safety: Explicit casting for database operations
- Context access: Components access `$_POST['context']` during AJAX
- Asset dependencies: Modal assets load with proper dependency chain

**Performance Features**:
- Conditional asset loading based on page context
- Priority-based loading (core assets first)
- Single AJAX handler eliminates competing handlers
- Template caching available at component level

This architecture provides complete extensibility through pure filter patterns while maintaining WordPress compatibility. The system enables unlimited modal types without core modifications.

## System Architecture
- **Entry Point**: `data-machine.php` with bootstrap sequence
- **Service Registry**: `inc/engine/DataMachineFilters.php`
- **PSR-4 Namespaces**: `DataMachine\Core\` → `inc/core/`, `DataMachine\Engine\` → `inc/engine/`
- **Dependencies**: Monolog, TwitterOAuth, Action Scheduler, AI HTTP Client (`/lib/ai-http-client/`)
- **Database**: Static methods under `DataMachine\Core\Database\`
- **Pipeline Execution**: Position-based orchestrator (0-99) with DataPacket flow

## Critical Implementation Patterns

### Advanced Security Implementation
**Multi-Layer Nonce System**: Granular nonces for each AJAX action with automatic verification
**Sanitization Pattern**: CRITICAL - `wp_unslash()` BEFORE `sanitize_text_field()` (reverse order fails)
**Encryption**: AES-256-CBC with dynamic key hierarchy and secure key derivation
**Parameter Validation**: Comprehensive input validation with type checking and range validation

### Performance Optimization
**Position-Based Execution**: Linear pipeline execution (0-99) with guaranteed order
**Conditional Asset Loading**: Assets only load on relevant admin pages
**Database Optimization**: Static methods with prepared statements and query optimization
**Clean Instance Management**: Fresh service instances per call - simple and maintainable

### Dynamic Asset Architecture
**Component-Owned Assets**: Asset organization where each component owns its CSS/JS files in dedicated directories. AdminMenuAssets dynamically discovers and loads assets via filter system.

**Dynamic Asset Discovery**: 
```php
// Components register assets via filter
add_filter('dm_get_page_assets', function($assets, $page_slug) {
    return [
        'css' => ['handle' => ['file' => $path, 'deps' => []]],
        'js' => ['handle' => ['file' => $path, 'deps' => ['jquery']]]
    ];
}, 10, 2);
```

**Smart Loading System**: AdminMenuAssets uses `'file'` key for component-owned paths with automatic dependency resolution and version management.
**Design System**: Color-coded step types (Input: green, AI: purple, Output: orange) with consistent `dm-` namespace isolation.
**Performance**: Conditional loading based on admin page context, preventing unnecessary asset bloat.

### Database Patterns
**Pipeline+Flow Tables**: Two-layer architecture with separate concerns
- `wp_dm_pipelines`: Template definitions with step sequences
- `wp_dm_flows`: Configured instances with handler settings and scheduling
- `wp_dm_jobs`: Execution records linking to both pipeline_id and flow_id

**Job Status State Machine**: Strict transitions, immutable final states

## Developer Guidelines

### Critical Rules (🚨 NEVER VIOLATE)
1. **Engine Agnosticism**: NEVER hardcode step types in `/inc/engine/` directory
2. **Modular Architecture**: Steps MUST self-register in own class files
3. **Component Filters**: Components register via *Filters.php files - never modify bootstrap
4. **Service Access**: Always use `apply_filters('dm_get_service', null)` - never `new ServiceClass()`
5. **Sanitization**: `wp_unslash()` BEFORE `sanitize_text_field()` - reverse order fails
6. **Context Access**: Requires job_id: `apply_filters('dm_get_context', null, $job_id)`
7. **CSS Namespace**: All admin CSS must use `dm-` prefix

### "Plugins Within Plugins" Architecture

**✅ COMPLETE**: All 23+ core components have dedicated *Filters.php files for complete self-containment. This provides a modular approach to WordPress plugin architecture.

**Self-Registration Pattern**: Each component is completely autonomous:
```php
// Component registers ALL its services in one place
function dm_register_twitter_filters() {
    add_filter('dm_get_handlers', function($handlers, $type) {
        if ($type === 'output') {
            $handlers['twitter'] = [
                'class' => Twitter::class, 
                'label' => __('Twitter', 'data-machine')
            ];
        }
        return $handlers;
    }, 10, 2);
    
    add_filter('dm_get_auth', function($auth, $handler_slug) {
        return ($handler_slug === 'twitter') ? new TwitterAuth() : $auth;
    }, 10, 2);
    
    add_filter('dm_get_handler_settings', function($settings, $handler_slug) {
        return ($handler_slug === 'twitter') ? new TwitterSettings() : $settings;
    }, 10, 2);
}
dm_register_twitter_filters(); // Immediate execution
```

**Component Coverage**:
- **Admin** (4): Modal, Jobs, Pipelines, Logs
- **Input Handlers** (4): Files, Reddit, RSS, WordPress
- **Output Handlers** (6): Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets
- **Database** (5): Jobs, RemoteLocations, Flows, Pipelines, ProcessedItems
- **Steps** (4): AI, Input, Output, Receiver
- **Engine** (1): DataMachineFilters

**Pattern Example**:
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

**Sophisticated Autoloading**: Four uniform functions with recursive directory scanning:
- `dm_autoload_core_admin()` - Admin pages and interfaces
- `dm_autoload_core_steps()` - Step types and their handlers (recursive)
- `dm_autoload_core_database()` - Database services
- Core handlers integrated into step autoloading via reorganized `/inc/core/steps/{type}/handlers/` structure

**Recursive Discovery**: Autoloader uses `RecursiveDirectoryIterator` to find `*Filters.php` files at any depth, enabling flexible component organization.

### Advanced Pipeline Builder System

**Current Status**: AJAX-driven interface with organized template structure and modal system integration.

**Key Features**:
- **AJAX Integration**: PipelineAjax class handles all operations with WordPress security
- **Modal System**: Universal modal infrastructure integrated with pipeline operations
- **Dynamic Discovery**: Real-time step and handler discovery through filter system
- **Template Organization**: Separate modal and page templates with pure rendering focus
- **JavaScript Architecture**: Event-driven design with proper error handling

### Receiver Step Framework - Webhook Integration Architecture

**Current Status**: The Receiver Step framework is a fully integrated stub implementation demonstrating the plugin's extensible step architecture. It registers via the filter system and appears in the step selection modal as "Framework for webhook integration (coming soon)", but returns `false` when executed since no handlers are implemented yet.

**Architectural Purpose**: Demonstrates the self-registration pattern and filter-based architecture used throughout Data Machine. Shows how new step types integrate with the Pipeline+Flow system while maintaining complete modularity. The step appears in the dynamic step selection interface with appropriate messaging.

**Future Implementation Plans**:
- **Webhook Handlers**: Real-time data reception from external services
- **API Polling Handlers**: Services without webhook support
- **Authentication Framework**: OAuth, API keys, webhook verification
- **Handler Organization**: Following the same `/inc/core/steps/receiver/handlers/` pattern as input/output handlers

**Step Registration Pattern**:
```php
// Receiver Step self-registers like all other steps
add_filter('dm_get_steps', function($step_config, $step_type) {
    if ($step_type === 'receiver') {
        return [
            'label' => __('Receiver', 'data-machine'),
            'description' => __('Framework for webhook integration (coming soon)', 'data-machine'),
            'class' => '\DataMachine\Core\Steps\Receiver\ReceiverStep'
        ];
    }
    return $step_config;
}, 10, 2);
```

**UI Integration**: The Receiver Step integrates seamlessly with the advanced pipeline builder:
- **Step Selection Modal**: Appears in the dynamic step selection interface
- **Handler Discovery**: Shows "(no handlers available yet)" in the interface
- **Coming Soon Messaging**: Professional presentation indicating future implementation
- **Filter-Based Display**: Uses the same discovery patterns as all other steps

**Integration Benefits**: When implemented, the Receiver Step will enable:
- **External Triggers**: Pipelines triggered by external services
- **Real-time Processing**: Immediate data processing on webhook receipt
- **Bi-directional Workflows**: Services can both send data to and receive data from Data Machine
- **Event-Driven Architecture**: Pipeline execution based on external events rather than just scheduling

**Developer Reference**: The Receiver Step serves as the canonical example of how to add new step types to Data Machine while following all architectural patterns and maintaining complete system compatibility.

### Engine Agnosticism Rules
```php
// ❌ NEVER hardcode in engine:
if ($step_type === 'input') { /* WRONG */ }

// ✅ ALWAYS use filters:
$step_config = apply_filters('dm_get_steps', null, $step_type);
$handlers = apply_filters('dm_get_handlers', null, $step_type);
```

## External Integration Guide

### Core Development Rules
- **Services**: Register in bootstrap functions (engine services only)
- **Handlers**: Self-register in organized directories
- **Steps**: MUST self-register in own class files
- **Engine Files**: NEVER contain hardcoded type checks

### External Plugin Integration

**Core Handlers**: 
- **Input**: Files, Reddit, RSS, WordPress, Google Sheets (in `/inc/core/steps/input/handlers/`)
- **Output**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (in `/inc/core/steps/output/handlers/`)
- **Receiver**: Stub framework for future webhook integrations - demonstrates extension pattern (in `/inc/core/steps/receiver/`)
- **AI Integration**: Multi-provider client (OpenAI, Anthropic, Google, Grok, OpenRouter)

**Extension Examples**:

#### Handler Extension Example
```php
// Add custom handler
add_filter('dm_get_handlers', function($handlers, $type) {
    if ($type === 'input') {
        $handlers['google_sheets'] = [
            'class' => \MyPlugin\Handlers\GoogleSheetsInput::class,
            'label' => __('Google Sheets', 'my-plugin')
        ];
    }
    return $handlers;
}, 10, 2);

// Auto-linked auth and settings
add_filter('dm_get_auth', function($auth, $handler_slug) {
    return ($handler_slug === 'google_sheets') ? new \MyPlugin\GoogleSheetsAuth() : $auth;
}, 10, 2);
```

#### Custom Steps
```php
class MyTransformationStep {
    public function execute(int $job_id, array $data_arrays = []): bool {
        $latest_data = $data_arrays[0] ?? null;
        if ($latest_data) {
            $content = $latest_data['content']['body'] ?? '';
            $transformed = $this->transform_data($content);
        }
        return true;
    }
}

// Register step
add_filter('dm_get_steps', function($step_config, $step_type) {
    if ($step_type === 'transformation') {
        return ['label' => __('Transformation'), 'class' => '\MyPlugin\Steps\TransformationStep'];
    }
    return $step_config;
}, 10, 2);
```

#### Database Extensions
```php
// Add custom database service
add_filter('dm_get_database_service', function($service, $type) {
    if ($type === 'analytics') return new MyPlugin\Analytics();
    return $service;
}, 10, 2);

// Override core service
add_filter('dm_get_database_service', function($service, $type) {
    if ($type === 'jobs') return new MyPlugin\EnhancedJobs();
    return $service;
}, 20, 2);
```

#### Admin Extensions
```php
// Add custom admin page using parameter-based pattern
add_filter('dm_get_admin_page', function($config, $page_slug) {
    if ($page_slug === 'analytics') {
        return [
            'page_title' => __('Analytics Dashboard', 'my-plugin'),
            'menu_title' => __('Analytics', 'my-plugin'),
            'capability' => 'manage_options',
            'position' => 35
        ];
    }
    return $config;
}, 10, 2);

// Provide page content via filter system
add_filter('dm_render_admin_page', function($content, $page_slug) {
    if ($page_slug === 'analytics') {
        $analytics_instance = new MyPlugin\AnalyticsPage();
        ob_start();
        $analytics_instance->render_content();
        return ob_get_clean();
    }
    return $content;
}, 10, 2);

// Register component-owned assets
add_filter('dm_get_page_assets', function($assets, $page_slug) {
    if ($page_slug === 'analytics') {
        return [
            'css' => [
                'dm-admin-analytics' => [
                    'file' => 'path/to/your/plugin/assets/css/admin-analytics.css',
                    'deps' => [],
                    'media' => 'all'
                ]
            ],
            'js' => [
                'dm-analytics-admin' => [
                    'file' => 'path/to/your/plugin/assets/js/analytics-admin.js',
                    'deps' => ['jquery'],
                    'in_footer' => true
                ]
            ]
        ];
    }
    return $assets;
}, 10, 2);

// Universal Modal System - Register custom modal content
add_filter('dm_get_modal_content', function($content, $template) {
    switch ($template) {
        case 'analytics-config':
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            return MyPlugin\AnalyticsConfig::render($context);
        case 'configure-step':
            $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
            if (($context['step_type'] ?? '') === 'my_custom_step') {
                return MyPlugin\CustomStep::render_config($context);
            }
            break;
    }
    return $content;
}, 10, 2);
```

#### Service Overrides
```php
// Override core services
add_filter('dm_get_orchestrator', function($service) {
    return new MyPlugin\CustomOrchestrator();
}, 20);

add_filter('dm_get_logger', function($service) {
    return new MyPlugin\CustomLogger();
}, 20);
```

### Architecture Summary
**Core Features**: Pipeline+Flow architecture, universal AI integration (OpenAI, Anthropic, Google, Grok, OpenRouter), WordPress-native workflows, engine agnostic design, modular components, parameter-based service discovery, universal modal system, and template reuse.

**Platform**: Comprehensive AI content processing built on WordPress architecture with Pipeline+Flow system maintaining compatibility and familiar development patterns.