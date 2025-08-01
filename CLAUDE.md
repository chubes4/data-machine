# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Data Machine is an AI-first WordPress plugin that transforms any WordPress site into a Universal Content Processing Platform. Built entirely on WordPress-native patterns, it enables sophisticated AI workflows through a two-layer Pipeline+Flow architecture and multi-provider AI integration (OpenAI, Anthropic, Google, Grok, OpenRouter).

**Architectural Innovation**: This plugin represents a paradigm shift in WordPress development through its "Plugins Within Plugins" architecture - a revolutionary self-registering component system that eliminates traditional dependency injection while maintaining complete modularity. The Pipeline+Flow architecture separates reusable workflow templates (Pipelines) from configured instances (Flows), enabling template reuse and independent workflow execution.

**Technical Sophistication**: Features position-based orchestration (0-99), universal DataPacket contracts, static service caching with lazy loading, parameter-based service discovery, and dynamic asset loading - all implemented through 100% filter-based dependencies.

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
- ✅ **Pipeline+Flow Architecture**: Two-layer system separating templates from configured instances
- ✅ **Universal AI Integration**: Multi-provider AI client with seamless switching between services
- ✅ **WordPress-Native AI Pipelines**: Production-quality visual pipeline builder with full AJAX integration
- ✅ **Engine Agnosticism**: Zero hardcoded step type assumptions enables unlimited extensibility
- ✅ **Modular Component System**: 23+ self-registering components with dedicated *Filters.php files
- ✅ **Comprehensive Filter Architecture**: 100% filter-based dependency system
- ✅ **Universal DataPacket System**: Standardized data flow enabling seamless AI pipeline processing
- ✅ **Clean Architecture Implementation**: All architectural violations eliminated
- ✅ **Component-Owned Asset Architecture**: All CSS/JS assets moved to component-specific directories with filter-based registration
- ✅ **Handler Architecture Reorganization**: All handlers moved to step-specific directories (`/inc/core/steps/{input|output}/handlers/`)
- ✅ **Receiver Step Framework**: Webhook reception system for external platform integrations
- ✅ **Bluesky Handler Restoration**: Complete AT Protocol integration restored with modern architecture
- ✅ **Google Sheets Integration**: Business intelligence output handler with OAuth 2.0 and structured data mapping
- ✅ **Pipeline Builder UI**: Clean two-column interface with dynamic step counting and professional card layout
- ✅ **PipelineAjax Backend**: Complete AJAX handler with security verification and content generation

### Known Issues  
- **Missing Testing Framework**: No automated testing infrastructure exists for main plugin (AI HTTP Client has PHPUnit + PHPStan)
- **Debug Logging Active**: Extensive `error_log()` calls throughout codebase should be conditional on WP_DEBUG or removed for production

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
$steps = apply_filters('dm_get_steps', null, 'input');
$context = apply_filters('dm_get_context', null, $job_id);
$page_config = apply_filters('dm_get_admin_page', null, 'jobs');
$page_assets = apply_filters('dm_get_page_assets', null, 'jobs');
```

### Development Commands
```bash
# Plugin Setup
composer install && composer dump-autoload

# AI HTTP Client Testing (Subtree Library)
cd lib/ai-http-client/
composer test      # PHPUnit tests
composer analyse   # PHPStan static analysis (level 5)  
composer check     # Both tests and analysis
cd ../..

# Debugging
window.dmDebugMode = true;  # Browser debugging
define('WP_DEBUG', true);   # WordPress debugging (enables extensive error_log output)

# Common Fixes
composer dump-autoload     # Fix "Class not found" errors
php -l file.php            # Check PHP syntax

# Git Subtree Operations (AI HTTP Client)
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
git subtree push --prefix=lib/ai-http-client origin main  # Push changes back
```

## Architecture Overview

### Core Components
- **Input Handlers**: Files, Reddit, RSS, WordPress (located in `/inc/core/steps/input/handlers/`)
- **Output Handlers**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (located in `/inc/core/steps/output/handlers/`)
- **Receiver Step**: Webhook reception framework for external platform integrations (located in `/inc/core/steps/receiver/`)
- **AI Integration**: Multi-provider client (OpenAI, Anthropic, Google, Grok, OpenRouter) with streaming and tool calling
- **Core Services**: Logger, Database (Jobs/Pipelines/Flows/ProcessedItems/RemoteLocations), Orchestrator, Security
- **Admin Interface**: Production-quality visual pipeline builder, job management, universal modal system

### Revolutionary Filter-Based Architecture
**Zero Constructor Injection**: Every service access uses `apply_filters()` with parameter-based discovery, creating unprecedented modularity while maintaining WordPress compatibility. Bootstrap sequence loads 5 core services + 4 component autoloading functions, then components self-register via dedicated `*Filters.php` files.

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

**"Plugins Within Plugins"**: Revolutionary architecture where components are completely self-contained with dedicated `*Filters.php` files. Each component registers its own services, handlers, auth providers, and assets - creating true modularity without traditional dependency injection.

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

### Universal Modal System - Component-Driven Content
**Zero-Configuration Modals**: Modal.php provides universal popup infrastructure where components register modal content via filters. The system handles display logic while components provide content and save logic.

**Dynamic Content Generation**: Modals discover content through `dm_get_modal_content` filter with component-specific parameters, enabling complex configuration interfaces without modal-specific code.

**AJAX Integration**: Built-in AJAX handling with automatic nonce verification and component-specific response routing.

```php
// Component registers its own modal capability in its *Filters.php file
add_filter('dm_get_modal_content', function($content, $component_id) {
    if ($component_id === 'my_component_' . $this->get_instance_id()) {
        return [
            'title' => 'My Component Configuration',
            'content' => '<form>...component-specific form with own AJAX...</form>'
        ];
    }
    return $content;
}, 10, 2);
```

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
**Component-Owned Assets**: Revolutionary asset organization where each component owns its CSS/JS files in dedicated directories. AdminMenuAssets dynamically discovers and loads assets via filter system.

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

### "Plugins Within Plugins" - Revolutionary Architecture

**✅ COMPLETE**: All 23+ core components have dedicated *Filters.php files for complete self-containment. This represents a fundamental shift from traditional WordPress plugin architecture.

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

### Receiver Step Framework - Webhook Integration Architecture

**Current Status**: The Receiver Step framework is currently a conceptual demonstration of the plugin's extensible step architecture. It exists as a fully-registered step but returns `false` when executed, serving as a reference implementation for adding new step types.

**Architectural Purpose**: Demonstrates the self-registration pattern and filter-based architecture used throughout Data Machine. Shows how new step types integrate with the Pipeline+Flow system while maintaining complete modularity.

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
            'description' => __('Webhook reception framework', 'data-machine'),
            'class' => '\DataMachine\Core\Steps\Receiver\ReceiverStep'
        ];
    }
    return $step_config;
}, 10, 2);
```

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
- **Input**: Files, Reddit, RSS, WordPress (in `/inc/core/steps/input/handlers/`)
- **Output**: Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (in `/inc/core/steps/output/handlers/`)
- **Receiver**: Webhook reception framework for external integrations (in `/inc/core/steps/receiver/`)
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

// Add modal content
add_filter('dm_get_modal_content', function($content, $modal_type, $context) {
    if ($modal_type === 'analytics_config') {
        return MyPlugin\AnalyticsConfig::render($context);
    }
    return $content;
}, 10, 3);
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

### AI-First Architecture Features
1. **Pipeline+Flow Architecture**: Reusable templates with independent configured instances
2. **Universal AI Integration**: Multi-provider client supporting OpenAI, Anthropic, Google, Grok, OpenRouter
3. **WordPress-Native AI Workflows**: Visual pipeline builder using familiar WordPress patterns
4. **Intelligent Content Processing**: Sophisticated AI workflows through standardized DataPacket flow
5. **Engine Agnostic Design**: Zero hardcoded assumptions enabling unlimited AI step extensibility
6. **Modular Component System**: Self-registering components with filter-based dependency management
7. **Gutenberg-Inspired Admin**: Extends WordPress block concepts to AI pipeline interfaces
8. **Parameter-Based Service Discovery**: Systematic service registration and discovery patterns
9. **Universal Modal System**: Streamlined configuration interfaces throughout
10. **Template Reuse System**: Build workflow templates once, deploy with multiple configurations

This represents a comprehensive AI content processing platform built entirely on WordPress architecture, enabling sophisticated AI workflows through a powerful Pipeline+Flow system while maintaining WordPress compatibility and familiar development patterns.