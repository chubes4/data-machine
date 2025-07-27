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

### Ultra-Direct Service Access

The new ultra-direct filter system provides the most efficient access pattern possible for critical services:

```php
// Core service registration with dependency resolution
$logger = apply_filters('dm_get_logger', null);
$db_jobs = apply_filters('dm_get_db_jobs', null);
$ai_client = apply_filters('dm_get_ai_http_client', null);
$orchestrator = apply_filters('dm_get_orchestrator', null);
$fluid_bridge = apply_filters('dm_get_fluid_context_bridge', null);

// External override capability via specific filters
add_filter('dm_get_logger', function($service) {
    return new CustomLoggerClass();
}, 20);
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

# AI HTTP Client library testing (in lib/ai-http-client/)
cd lib/ai-http-client/
composer test      # PHPUnit tests
composer analyse   # PHPstan static analysis 
composer check     # Run both test and analyse
```

No build process required - changes take effect immediately. Database schema is recreated on plugin activation/deactivation.

## Key Components

### Core Architecture
- **Ultra-Direct Service Filters**: Pure filter-based dependency management with external override capabilities
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

## Directory Structure

Based on PSR-4 autoloading configuration:

```
inc/
├── core/                    # DataMachine\Core\
│   ├── Constants.php        # Plugin constants
│   ├── CoreHandlerRegistry.php
│   ├── DataMachine.php      # Main plugin class
│   ├── DataPacket.php       # Standardized data format
│   ├── handlers/            # Core handler system
│   └── steps/               # Pipeline step implementations
├── admin/                   # DataMachine\Admin\
│   ├── AdminPage.php        # Main admin interface
│   ├── OAuth/               # OAuth integrations
│   ├── Projects/            # Project management
│   ├── ModuleConfig/        # Module configuration
│   └── RemoteLocations/     # Remote location management
├── engine/                  # DataMachine\Engine\
│   ├── ProcessingOrchestrator.php
│   ├── FluidContextBridge.php
│   ├── PipelineStepRegistry.php
│   └── filters/             # Processing filters
├── database/                # DataMachine\Database\
│   ├── Jobs.php             # Job management
│   ├── Projects.php         # Project data
│   └── ...                  # Other database classes
├── services/                # DataMachine\Services\
│   ├── ProjectPipelineConfigService.php
│   └── AiStepConfigService.php
└── helpers/                 # DataMachine\Helpers\
    ├── Logger.php           # Logging system
    └── ...                  # Utility classes
```

## Testing & Debugging

### Monitoring & Inspection
- **Jobs Monitoring**: Data Machine → Jobs in WordPress admin
- **Background Processing**: WordPress → Tools → Action Scheduler
- **Database**: `wp_dm_jobs` table contains step data in JSON format
- **Pipeline Flow Validation**: FlowValidationEngine validates step configurations

### Development Debugging
- **Browser Debug**: Enable `window.dmDebugMode = true` for verbose logging
- **WordPress Debug**: Enable WP_DEBUG for validation logging
- **Service Override Testing**: Use filter patterns like `dm_get_{service_name}` filters
- **Ultra-Direct Service Testing**: Test critical services via direct filters

### Testing Framework
- **Main Plugin**: No testing framework currently configured - manual testing via WordPress admin
- **AI HTTP Client**: PHPUnit tests available in `lib/ai-http-client/` directory
- **Service Architecture**: Filter-based override testing for external plugin integration

## Development Patterns

### Service Access (Universal Pattern)
```php
// 100% filter-based access - used throughout entire codebase
$logger = apply_filters('dm_get_logger', null);
$db_jobs = apply_filters('dm_get_db_jobs', null);
$ai_http_client = apply_filters('dm_get_ai_http_client', null);
```

### Service Override Capability
```php
// External plugins can override any service
add_filter('dm_get_logger', function($service) {
    return new MyCustomLogger();
}, 20);

// Service-specific override with higher priority
add_filter('dm_get_ai_http_client', function($service) {
    return new MyCustomAIClient();
}, 20);
```

### Universal 3-Step Pipeline Registration
```php
// Register the universal Input → AI → Output pipeline (auto-registered in core)
add_filter('dm_register_step_types', function($step_types) {
    $step_types['input'] = [
        'class' => 'DataMachine\\Core\\Steps\\InputStep',
        'label' => __('Input Step', 'data-machine'),
        'type' => 'input'
    ];
    
    $step_types['ai'] = [
        'class' => 'DataMachine\\Core\\Steps\\AIStep', 
        'label' => __('AI Processing Step', 'data-machine'),
        'type' => 'ai'
    ];
    
    $step_types['output'] = [
        'class' => 'DataMachine\\Core\\Steps\\OutputStep',
        'label' => __('Output Step', 'data-machine'),
        'type' => 'output'
    ];
    
    return $step_types;
}, 5);
```

### Handler Registration
```php
// Input handlers
add_filter('dm_register_input_handlers', function($handlers) {
    $handlers['custom_source'] = ['class' => 'MyPlugin\CustomHandler', 'label' => 'Custom Source'];
    return $handlers;
});

// Output handlers  
add_filter('dm_register_output_handlers', function($handlers) {
    $handlers['custom_destination'] = ['class' => 'MyPlugin\CustomHandler', 'label' => 'Custom Destination'];
    return $handlers;
});
```

### Pipeline Extension
```php
add_filter('dm_register_step_types', function($step_types) {
    $step_types['custom_step'] = ['class' => 'MyPlugin\CustomStep', 'type' => 'custom'];
    return $step_types;
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
        $logger = apply_filters('dm_get_logger', null);
        $http_service = apply_filters('dm_get_http_service', null);
        
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
$ai_config_service = apply_filters('dm_get_ai_step_config_service', null);

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
class CustomAIStep extends \DataMachine\Core\Steps\BasePipelineStep {
    
    public function process(\DataMachine\Core\DataPacket $data_packet, array $pipeline_context = []): \DataMachine\Core\DataPacket {
        $fluid_bridge = apply_filters('dm_get_fluid_context_bridge', null);
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);
        
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

The ultra-direct filter system provides these core services (all accessible via direct filter patterns):

- **Core Services**: `dm_get_logger`, `dm_get_encryption_helper`
- **Database Services**: `dm_get_db_jobs`, `dm_get_db_modules`, `dm_get_db_projects`, `dm_get_db_processed_items`, `dm_get_db_remote_locations`
- **Engine Services**: `dm_get_orchestrator`, `dm_get_job_creator`, `dm_get_job_status_manager`
- **Handler Services**: `dm_get_processed_items_manager`, `dm_get_http_service`
- **AI Services**: `dm_get_ai_http_client`, `dm_get_prompt_builder`, `dm_get_fluid_context_bridge`
- **OAuth Services**: `dm_get_oauth_twitter`, `dm_get_oauth_reddit`, `dm_get_oauth_threads`, `dm_get_oauth_facebook`
- **Pipeline Services**: `dm_get_pipeline_step_registry`, `dm_get_project_prompts_service`, `dm_get_project_pipeline_config_service`
- **Configuration Services**: `dm_get_ai_step_config_service` (step-specific AI configuration management)

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

## Contributing

### Simplified Two-Pattern API

Data Machine uses a revolutionary simplified API architecture with just two core patterns for infinite extensibility. No interfaces, inheritance, or complex requirements - just direct filter registration.

### Input Handler Registration

Register input handlers using the `dm_register_input_handlers` filter:

```php
add_filter('dm_register_input_handlers', function($handlers) {
    $handlers['my_custom_source'] = [
        'class' => 'MyPlugin\CustomInputHandler',
        'label' => 'My Custom Data Source'
    ];
    return $handlers;
});
```

### Output Handler Registration

Register output handlers using the `dm_register_output_handlers` filter:

```php
add_filter('dm_register_output_handlers', function($handlers) {
    $handlers['my_custom_destination'] = [
        'class' => 'MyPlugin\CustomOutputHandler', 
        'label' => 'My Custom Destination'
    ];
    return $handlers;
});
```

### Minimal Handler Implementation

Handlers need only implement the required method and return/accept DataPackets:

#### Input Handler Example
```php
namespace MyPlugin;

class CustomInputHandler {
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Access services via filters
        $logger = apply_filters('dm_get_logger', null);
        $http_service = apply_filters('dm_get_http_service', null);
        
        // Your custom logic here
        $items = [/* your data */];
        
        return ['processed_items' => $items];
    }
}
```

#### Output Handler Example
```php
namespace MyPlugin;

class CustomOutputHandler {
    public function handle_output(\DataMachine\Core\DataPacket $data_packet, array $destination_config, int $user_id): bool {
        // Access services via filters
        $logger = apply_filters('dm_get_logger', null);
        
        // Process the DataPacket
        $content = $data_packet->content;
        $metadata = $data_packet->metadata;
        
        // Your custom output logic here
        
        return true; // Success
    }
}
```

### DataPacket Compliance

The only structural requirement is DataPacket compliance. DataPackets contain:

- **content**: Primary content array
- **metadata**: Associated metadata  
- **processing**: Processing history and context
- **attachments**: File attachments

### Development Flow

1. **Fork Repository**: Create your own fork of the Data Machine repository
2. **Create Feature Branch**: `git checkout -b feature/my-custom-handler`
3. **Direct Filter Registration**: Use `dm_register_input_handlers` or `dm_register_output_handlers`
4. **Implement Handler Class**: Follow minimal structure with DataPacket compliance
5. **Test Functionality**: Verify handler works in Data Machine pipelines
6. **Submit Pull Request**: Submit your contribution for review

### No Complex Requirements

- **No Interfaces**: Direct class implementation without interface requirements
- **No Inheritance**: No mandatory base class extensions
- **No Constructor Dependencies**: Parameter-less constructors only
- **No Registration Complexity**: Simple filter-based registration
- **Barrier-Free**: External plugins use identical patterns as core code

### Filter-Based Service Access

All handlers access services via the universal filter pattern:

```php
// Universal service access pattern
$service = apply_filters('dm_get_{service_name}', null);

// Available core services
$logger = apply_filters('dm_get_logger', null);
$db_jobs = apply_filters('dm_get_db_jobs', null);
$http_service = apply_filters('dm_get_http_service', null);
$ai_http_client = apply_filters('dm_get_ai_http_client', null);
```

## Important Notes

- Uses PSR-4 namespacing with `DataMachine\` namespace structure
- All WordPress security patterns enforced (escaping, sanitization, capability checks)
- External plugins can extend functionality using identical patterns as core code
- AI integration supports multiple providers via `lib/ai-http-client/`
- Action Scheduler handles background processing for scalability
- **Critical**: All constructors must be parameter-less - services accessed via filters only
- **Testing**: External override capabilities verified via filter priority system
- **Modal Extensibility**: Use `dm_get_modal_content` and `dm_save_modal_config` filters for custom step types
- **Multi-Model Workflows**: Configure different AI providers per step via AiStepConfigService