# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Data Machine is a WordPress plugin that transforms sites into Universal Content Processing Platforms using a pure filter-based dependency architecture. The plugin implements an extensible pipeline system where data flows through configurable processing steps using exclusively WordPress-native patterns.

## Core Architecture

### Pure Filter-Based Dependency System

**Revolutionary Change**: Complete migration from mixed dependency injection to 100% WordPress-native filter patterns. This architecture eliminates brittleness by removing constructor dependencies and achieving maximum WordPress compatibility.

All services are accessed via WordPress filters instead of traditional dependency injection:

```php
$service = apply_filters('dm_get_service', null, 'service_name');
```

**Key Benefits**:
- **Zero Constructor Dependencies**: All classes use parameter-less constructors
- **Maximum Extensibility**: External plugins can override any service via filter priority
- **WordPress-Native**: Complete alignment with WordPress architectural patterns
- **Eliminates Brittleness**: No complex dependency chains or injection failures

### ServiceRegistry Architecture

The new `ServiceRegistry` class provides centralized service management with lazy loading and external override capabilities:

```php
// Core service registration with dependency resolution
self::register('db_jobs', function() {
    $db_projects = self::get('db_projects');
    $logger = self::get('logger');
    return new \DataMachine\Database\Jobs($db_projects, $logger);
});

// External override capability via specific filters
add_filter('dm_service_override_logger', function($service) {
    return new CustomLoggerClass();
});
```

### Pipeline Architecture

The plugin uses a closed-door pipeline where each step operates on data from the previous step only:
- Steps registered via `dm_register_pipeline_steps` filter
- Each step processes a standardized DataPacket format
- Dynamic execution coordinated by ProcessingOrchestrator
- Universal 3-step architecture: Input → AI → Output

### Handler System

Input/output handlers are registered via `dm_register_handlers` filter, enabling infinite extensibility without core modifications. All handlers use identical patterns whether core or external.

## Development Commands

```bash
# Install/update dependencies
composer install
composer dump-autoload    # After adding new classes
```

No build process required - changes take effect immediately. Database schema is recreated on plugin activation/deactivation.

## Key Components

- **ServiceRegistry**: Pure filter-based dependency management with override capabilities
- **DataPacket**: Standardized data format with content, metadata, processing, and attachments arrays
- **ProcessingOrchestrator**: Coordinates dynamic step execution (includes/engine/)
- **PipelineStepRegistry**: Manages extensible pipeline step registration
- **Handlers**: Extensible input/output processors (includes/handlers/)
- **Database Classes**: WordPress table abstractions (includes/database/)
- **Admin Interface**: Dynamic module configuration and project management (admin/)

## Testing & Debugging

- **Jobs Monitoring**: Data Machine → Jobs in WordPress admin
- **Background Processing**: WordPress → Tools → Action Scheduler
- **Database**: `wp_dm_jobs` table contains step data in JSON format
- **Browser Debug**: Enable `window.dmDebugMode = true` for verbose logging
- **Service Override Testing**: Use filter patterns like `dm_service_override_{service_name}`

## Development Patterns

### Service Access (Universal Pattern)
```php
// 100% filter-based access - used throughout entire codebase
$logger = apply_filters('dm_get_service', null, 'logger');
$db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
$ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');
```

### Service Override Capability
```php
// External plugins can override any service
add_filter('dm_service_override_logger', function($service) {
    return new MyCustomLogger();
});

// Service-specific override with higher priority
add_filter('dm_service_override_ai_http_client', function($service) {
    return new MyCustomAIClient();
}, 20);
```

### Handler Registration
```php
add_filter('dm_register_handlers', function($handlers) {
    $handlers['input']['custom'] = ['class' => 'MyPlugin\CustomHandler', 'label' => 'Custom Source'];
    return $handlers;
});
```

### Pipeline Extension
```php
add_filter('dm_register_pipeline_steps', function($steps) {
    $steps['custom_step'] = ['class' => 'MyPlugin\CustomStep', 'next' => 'ai'];
    return $steps;
});
```

### Creating Filter-Based Classes
```php
namespace MyPlugin;

class CustomHandler extends BaseInputHandler {
    // Parameter-less constructor - pure filter-based architecture
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }
    
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // All service access via filters
        $logger = apply_filters('dm_get_service', null, 'logger');
        $http_service = apply_filters('dm_get_service', null, 'http_service');
        
        return ['processed_items' => $items];
    }
}
```

## Available Services

The ServiceRegistry provides these core services (all accessible via `dm_get_service` filter):

- **Core Services**: `logger`, `encryption_helper`, `memory_guard`
- **Database Services**: `db_jobs`, `db_modules`, `db_projects`, `db_processed_items`, `db_remote_locations`
- **Engine Services**: `orchestrator`, `job_creator`, `job_status_manager`, `action_scheduler`
- **Handler Services**: `handler_factory`, `processed_items_manager`, `http_service`
- **AI Services**: `ai_http_client`, `prompt_builder`
- **OAuth Services**: `oauth_twitter`, `oauth_reddit`, `oauth_threads`, `oauth_facebook`
- **Pipeline Services**: `pipeline_step_registry`, `project_prompts_service`, `project_pipeline_config_service`

## Architecture Migration Benefits

### Eliminated Problems
- **Constructor Injection Brittleness**: Removed complex dependency chains that could fail
- **Mixed Architecture Philosophy**: Achieved 100% consistency with WordPress patterns
- **Service Registration Complexity**: Simplified to pure filter-based registration
- **External Plugin Barriers**: Removed barriers to service overrides and extensions

### Achieved Goals
- **Zero Mixed Philosophy**: Complete alignment with WordPress-native patterns
- **Maximum Extensibility**: External plugins use identical patterns as core code
- **Simplified Service Access**: Single universal pattern throughout codebase
- **Override Capabilities**: Granular service override via filter priority system

## Important Notes

- Uses PSR-4 namespacing with `DataMachine\` namespace
- All WordPress security patterns enforced (escaping, sanitization, capability checks)
- External plugins can extend functionality using identical patterns as core code
- AI integration supports multiple providers via `lib/ai-http-client/`
- Action Scheduler handles background processing for scalability
- **Critical**: All constructors must be parameter-less - services accessed via filters only
- **Testing**: External override capabilities verified via filter priority system