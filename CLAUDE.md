# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Data Machine is an AI-first WordPress plugin that transforms any WordPress site into a Universal Content Processing Platform. Built entirely on WordPress-native patterns, it enables sophisticated AI workflows through a two-layer Pipeline+Flow architecture and multi-provider AI integration (OpenAI, Anthropic, Google, Grok, OpenRouter).

**AI Innovation**: The first comprehensive AI content processing system designed specifically for WordPress, enabling complex AI workflows through familiar WordPress interfaces and patterns. The Pipeline+Flow architecture separates reusable workflow templates (Pipelines) from configured instances (Flows), enabling template reuse and independent workflow execution.

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
- ✅ **WordPress-Native AI Pipelines**: Visual pipeline builder using Gutenberg-inspired patterns
- ✅ **Engine Agnosticism**: Zero hardcoded step type assumptions enables unlimited extensibility
- ✅ **Modular Component System**: 26 self-registering components with dedicated *Filters.php files
- ✅ **Comprehensive Filter Architecture**: 100% filter-based dependency system
- ✅ **Universal DataPacket System**: Standardized data flow enabling seamless AI pipeline processing
- ✅ **Clean Architecture Implementation**: All architectural violations eliminated
- ✅ **Component-Owned Asset Architecture**: All CSS/JS assets moved to component-specific directories with filter-based registration
- ✅ **Handler Autoloader Fix**: Recursive directory scanning enables proper handler file loading
- ✅ **Bluesky Handler Restoration**: Complete AT Protocol integration restored with modern architecture
- ✅ **Google Sheets Integration**: Business intelligence output handler with OAuth 2.0 and structured data mapping

### Known Issues  
- **Missing Testing Framework**: No automated testing infrastructure exists
- **GIT SUBTREE MODIFIED LOCALLY**: Custom modifications need reconciliation with remote repository

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
composer install && composer dump-autoload
cd lib/ai-http-client/ && composer check
window.dmDebugMode = true;  # Browser debugging
define('WP_DEBUG', true);   # WordPress debugging
composer dump-autoload     # Fix "Class not found" errors
```

## Architecture Overview

### Core Components
- **Handlers**: Files, Reddit, RSS, WordPress (input); Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (output)
- **AI Integration**: Multi-provider client (OpenAI, Anthropic, Google, Grok, OpenRouter) with streaming and tool calling
- **Core Services**: Logger, Database (Jobs/Pipelines/Flows/ProcessedItems/RemoteLocations), Orchestrator, Security
- **Admin Interface**: Visual pipeline builder, job management, pure HTML modal system

### Filter-Based Architecture
**100% Filter-Based Dependencies**: Eliminates constructor injection, maintains WordPress compatibility with 5 service registration + 4 component autoloading functions.

### Handler System - Parameter-Based Registration
Handlers register as configuration arrays, auth/settings auto-link via parameter matching:

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

### Engine Architecture
**Engine Agnostic**: `/inc/engine/` contains zero hardcoded step/handler assumptions. Pure orchestration with parameter-based discovery.

**"Plugins Within Plugins"**: Components self-register in own class files via *Filters.php files.

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

### DataPacket Contract
**CRITICAL**: DataPacket is engine-exclusive. Components receive simple arrays:

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

### Modal System
**Universal HTML Popup Component**: Modal.php displays content via pure component-driven filters. Components register their own modal capabilities and handle their own save logic within injected content.

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

### Security
**Multi-Layer Nonce System**: Granular nonces for each AJAX action  
**Sanitization**: CRITICAL - `wp_unslash()` BEFORE `sanitize_text_field()`  
**Encryption**: AES-256-CBC with dynamic key hierarchy

### Performance
**Static Service Caching**: Lazy loading with static caches  
**Position-Based Execution**: Linear pipeline execution (0-99)

### CSS Architecture
**Component-Owned Assets**: All CSS/JS files organized in component-specific directories:
- Jobs: `inc/core/admin/pages/jobs/assets/{css,js}/`
- Pipelines: `inc/core/admin/pages/pipelines/assets/{css,js}/`
- Logs: `inc/core/admin/pages/logs/assets/css/`

**Filter-Based Asset Registration**: Components register assets via `dm_get_page_assets` filter
**Asset Loading**: AdminMenuAssets uses `'file'` key for component-owned asset paths
**Namespace Isolation**: All classes prefixed with `dm-`  
**Color Coding**: Input (green), AI (purple), Output (orange)  
**Responsive**: Mobile-first with touch optimization

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

**✅ COMPLETE**: All 21 core components have dedicated *Filters.php files for complete self-containment.

**Component Coverage**:
- **Admin** (4): Modal, Jobs, Pipelines, Logs
- **Handlers** (8): All input/output handlers  
- **Database** (5): Jobs, RemoteLocations, Flows, Pipelines, ProcessedItems
- **Steps** (3): AI, Input, Output
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

**Auto-Loading**: Four uniform functions - `dm_autoload_core_handlers()`, `dm_autoload_core_admin()`, `dm_autoload_core_steps()`, `dm_autoload_core_database()`

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

**Core Handlers**: Files, Reddit, RSS, WordPress (input); Facebook, Threads, Twitter, WordPress, Bluesky, Google Sheets (output); Multi-provider AI client

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