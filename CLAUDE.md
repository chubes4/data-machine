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

### Revolutionary Pipeline Architecture

**From Complex to Simple and Even More Powerful**: The plugin implements a revolutionary horizontal pipeline builder that transforms complex configuration into intuitive visual workflow construction.

#### Horizontal Pipeline Builder
- **Visual Card-Based Construction**: Drag-and-drop pipeline building with intuitive step cards
- **Fluid Context System**: AI steps automatically receive ALL previous DataPackets for enhanced context
- **Real-Time Configuration**: Configure steps through contextual modals without leaving pipeline view
- **Multi-Model Workflows**: Different AI providers/models per step (GPT-4 → Claude → Gemini chains)

#### Universal Modal Configuration System
- **Contextual Step Configuration**: Step-specific modals load appropriate configuration content
- **AI Step Integration**: Direct ProviderManagerComponent integration for seamless AI configuration
- **Filter-Based Content**: Modal content populated via `dm_get_modal_content` filter for infinite extensibility
- **Elimination of Config Pages**: All configuration happens through modals, removing complex navigation

#### Fluid Context Bridge
- **Enhanced AI Understanding**: FluidContextBridge aggregates pipeline context for superior AI comprehension
- **Multi-Model Support**: Different providers/models per AI step with shared context
- **Context Aggregation**: Automatic DataPacket aggregation and ai-http-client integration
- **Variable Templating**: Advanced prompt variable system with pipeline context injection

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

### Core Architecture
- **ServiceRegistry**: Pure filter-based dependency management with external override capabilities
- **DataPacket**: Standardized data format with content, metadata, processing, and attachments arrays
- **ProcessingOrchestrator**: Coordinates dynamic step execution with fluid context support
- **FluidContextBridge**: Bridges DataMachine pipeline data with ai-http-client context management

### User Experience Revolution
- **Horizontal Pipeline Builder**: Visual card-based pipeline construction with drag-and-drop
- **Universal Modal System**: Contextual configuration modals for all step types
- **ProjectPipelineConfigService**: Manages per-project pipeline configurations and step ordering
- **AiStepConfigService**: Handles step-specific AI provider/model configurations

### Extensibility Infrastructure
- **PipelineStepRegistry**: Manages extensible pipeline step registration
- **Handlers**: Extensible input/output processors with filter-based registration
- **Database Classes**: WordPress table abstractions with filter-based access
- **Modal Content Filters**: `dm_get_modal_content` and `dm_save_modal_config` for infinite extensibility

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

### Modal Content Extension
```php
// Register modal content for custom step types
add_filter('dm_get_modal_content', function($content, $step_type, $project_id, $step_position, $step_id) {
    if ($step_type === 'custom_step') {
        return '<div class="custom-step-config">Custom configuration form here</div>';
    }
    return $content;
}, 10, 5);

// Handle modal configuration saves
add_filter('dm_save_modal_config', function($result, $step_type, $config_data) {
    if ($step_type === 'custom_step') {
        // Handle custom step configuration save
        return update_option("custom_step_config_{$project_id}", $config_data);
    }
    return $result;
}, 10, 3);
```

### Multi-Model AI Workflow Configuration
```php
// Configure different AI models per step via AiStepConfigService
$ai_config_service = apply_filters('dm_get_service', null, 'ai_step_config_service');

// Step 1: GPT-4 for analysis
$ai_config_service->save_step_ai_config($project_id, 0, [
    'provider' => 'openai',
    'model' => 'gpt-4',
    'temperature' => 0.3
]);

// Step 2: Claude for creative writing
$ai_config_service->save_step_ai_config($project_id, 1, [
    'provider' => 'anthropic', 
    'model' => 'claude-3-opus',
    'temperature' => 0.8
]);

// Step 3: Gemini for factual verification
$ai_config_service->save_step_ai_config($project_id, 2, [
    'provider' => 'google',
    'model' => 'gemini-pro',
    'temperature' => 0.1
]);
```

### Fluid Context Bridge Integration
```php
class CustomAIStep extends \DataMachine\Engine\Steps\BasePipelineStep {
    
    public function process(\DataMachine\DataPacket $data_packet, array $pipeline_context = []): \DataMachine\DataPacket {
        $fluid_bridge = apply_filters('dm_get_service', null, 'fluid_context_bridge');
        $ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');
        
        // Aggregate all previous pipeline context
        $aggregated_context = $fluid_bridge->aggregate_pipeline_context($pipeline_context);
        
        // Build enhanced AI request with context
        $ai_request = $fluid_bridge->build_ai_request($aggregated_context, $this->step_config);
        
        // Send request with enhanced context
        $response = $ai_http_client->send_request($ai_request);
        
        // Process response and return enhanced DataPacket
        return $this->process_ai_response($response, $data_packet);
    }
}
```

## Available Services

The ServiceRegistry provides these core services (all accessible via `dm_get_service` filter):

- **Core Services**: `logger`, `encryption_helper`, `memory_guard`
- **Database Services**: `db_jobs`, `db_modules`, `db_projects`, `db_processed_items`, `db_remote_locations`
- **Engine Services**: `orchestrator`, `job_creator`, `job_status_manager`, `action_scheduler`
- **Handler Services**: `processed_items_manager`, `http_service`
- **AI Services**: `ai_http_client`, `prompt_builder`, `fluid_context_bridge`
- **OAuth Services**: `oauth_twitter`, `oauth_reddit`, `oauth_threads`, `oauth_facebook`
- **Pipeline Services**: `pipeline_step_registry`, `project_prompts_service`, `project_pipeline_config_service`
- **Configuration Services**: `ai_step_config_service` (step-specific AI configuration management)

## Revolutionary User Experience Transformation

### From Complex to Simple and Even More Powerful

The Data Machine architecture represents a revolutionary transformation: **complex configuration becomes intuitive visual workflow construction while gaining powerful new capabilities**.

#### Visual Pipeline Construction
- **Horizontal Card Layout**: Intuitive left-to-right flow showing data progression
- **Drag-and-Drop Reordering**: Natural pipeline step organization
- **Real-Time Configuration**: Configure steps without leaving the pipeline view
- **Visual Step Status**: Immediate feedback on configuration state

#### Universal Modal System
- **Contextual Configuration**: Each step type loads appropriate configuration content
- **AI Provider Integration**: Direct ProviderManagerComponent integration for seamless AI setup
- **Eliminates Page Navigation**: No more complex configuration page hierarchies
- **Filter-Based Extensibility**: External plugins add modal content using identical patterns

#### Multi-Model AI Workflows
- **Step-Specific AI Configuration**: Different providers/models per pipeline step
- **Enhanced Context Flow**: Fluid context system aggregates all previous step data
- **Advanced Prompt Templating**: Variable injection with pipeline context
- **Superior AI Understanding**: FluidContextBridge optimizes AI comprehension

## Architecture Migration Benefits

### Eliminated Problems
- **Constructor Injection Brittleness**: Removed complex dependency chains that could fail
- **Mixed Architecture Philosophy**: Achieved 100% consistency with WordPress patterns
- **Service Registration Complexity**: Simplified to pure filter-based registration
- **External Plugin Barriers**: Removed barriers to service overrides and extensions
- **Complex Configuration UI**: Eliminated confusing configuration page hierarchies
- **Limited AI Capabilities**: Removed single-model limitations and context constraints

### Achieved Goals
- **Zero Mixed Philosophy**: Complete alignment with WordPress-native patterns
- **Maximum Extensibility**: External plugins use identical patterns as core code
- **Simplified Service Access**: Single universal pattern throughout codebase
- **Override Capabilities**: Granular service override via filter priority system
- **Intuitive User Experience**: Visual pipeline building with contextual configuration
- **Enhanced AI Power**: Multi-model workflows with superior context aggregation

## Frontend Architecture

### JavaScript Component System
- **project-pipeline-builder.js**: Horizontal pipeline builder with visual card management
- **pipeline-modal.js**: AI step configuration modals with ProviderManagerComponent integration  
- **modal-config-handler.js**: Universal modal system for step configuration
- **Modular Design**: Each component handles specific functionality with clear separation

### AJAX Integration
- **PipelineManagementAjax.php**: Core pipeline step CRUD operations
- **ProjectPipelineStepsAjax.php**: Project-specific pipeline management
- **Modal Content Loading**: Dynamic content via `dm_get_modal_content` AJAX action
- **Real-Time Updates**: Pipeline changes reflect immediately without page refresh

### CSS Architecture
- **data-machine-admin.css**: Horizontal pipeline styles with visual card design
- **Responsive Design**: Pipeline builder adapts to different screen sizes
- **Visual Hierarchy**: Clear step progression with color-coded step types

## Commit Message Suggestions

Based on this revolutionary architectural transformation, I suggest the following commit structure:

### Major Architecture Commit
```
feat: Revolutionary horizontal pipeline builder with universal modal system

Transform complex configuration into intuitive visual workflow construction:
- Horizontal card-based pipeline builder with drag-and-drop
- Universal modal configuration system eliminating config pages  
- Multi-model AI workflows with step-specific provider configuration
- FluidContextBridge for enhanced AI context aggregation
- Pure filter-based modal content extensibility

BREAKING CHANGE: Configuration UI completely reimagined - all settings now 
configured through contextual modals instead of separate pages.

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Service Architecture Commit
```
feat: Enhanced service architecture with AI step configuration

Add specialized services for revolutionary pipeline management:
- ProjectPipelineConfigService for step ordering and configuration
- AiStepConfigService for per-step AI provider/model settings
- FluidContextBridge for ai-http-client integration and context aggregation
- Enhanced ServiceRegistry with new configuration services

Enables different AI providers per step (GPT-4 → Claude → Gemini workflows)
with superior context flow between pipeline steps.

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## Important Notes

- Uses PSR-4 namespacing with `DataMachine\` namespace
- All WordPress security patterns enforced (escaping, sanitization, capability checks)
- External plugins can extend functionality using identical patterns as core code
- AI integration supports multiple providers via `lib/ai-http-client/`
- Action Scheduler handles background processing for scalability
- **Critical**: All constructors must be parameter-less - services accessed via filters only
- **Testing**: External override capabilities verified via filter priority system
- **Modal Extensibility**: Use `dm_get_modal_content` and `dm_save_modal_config` filters for custom step types
- **Multi-Model Workflows**: Configure different AI providers per step via AiStepConfigService