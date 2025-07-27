# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Data Machine is a WordPress plugin that transforms sites into Universal Content Processing Platforms using a pure filter-based dependency architecture. The plugin implements an extensible pipeline system where data flows through configurable processing steps using exclusively WordPress-native patterns.

## Core Architecture

### Pure Filter-Based Dependency System

**Revolutionary Implementation**: The plugin has successfully migrated to 95%+ pure WordPress-native filter patterns. This architecture eliminates brittleness by removing constructor dependencies and achieving maximum WordPress compatibility.

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

The implemented ultra-direct filter system provides the most efficient access pattern possible for critical services. All 32+ core services are registered with lazy loading and static caching:

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

## Current Implementation Status

### Migration Completion: 95%+
The filter-based dependency system is fully implemented and operational. Only minor cleanup remaining:
- One constructor injection call in ModuleConfigAjax needs migration (`new RemoteLocationService()` vs. `new RemoteLocationService($db)`)
- Some orphaned files from the migration may need cleanup (check git status)

### Design Philosophy
- This is a revolutionary proprietary system with no backward compatibility constraints
- Built for maximum clean architecture and WordPress integration

## Key Components

### Plugin Entry Point & Initialization
- **Main File**: `data-machine.php` - Entry point with `run_data_machine()` function
- **Service Registry**: `inc/DataMachineFilters.php` - Central filter-based service registration
- **Auto-loading**: PSR-4 compliant with Composer autoloader for `DataMachine\` namespace

### Database Architecture
All database classes use static methods and PSR-4 namespacing under `DataMachine\Database\`:
- **Projects**: Pipeline configuration and definitions
- **Modules**: Individual pipeline instances  
- **Jobs**: Execution tracking and DataPacket storage
- **ProcessedItems**: Duplicate prevention system
- **RemoteLocations**: Multi-site publishing configuration

Table creation during activation: `\DataMachine\Database\Projects::create_table()`

### Pipeline Execution System
- **Orchestrator**: `inc/engine/ProcessingOrchestrator.php` - Position-based step execution using `dm_step_position_{N}_job_event` hooks
- **Data Flow**: `inc/core/DataPacket.php` - Standardized data structure with content, metadata, processing, and attachments arrays
- **Context Bridge**: `inc/engine/FluidContextBridge.php` - Aggregates previous pipeline DataPackets for AI context
- **Step Requirements**: All steps must implement `execute(int $job_id): bool` method

### Handler System ("Plugins Within Plugins")
Handlers auto-load from organized directories and self-register via `dm_register_handlers` filter:

**Directory Structure**:
```
inc/core/handlers/
├── input/
│   ├── files/Files.php
│   ├── reddit/Reddit.php
│   ├── rss/Rss.php
│   └── wordpress/WordPress.php
└── output/
    ├── facebook/Facebook.php
    ├── threads/Threads.php
    ├── twitter/Twitter.php
    └── wordpress/WordPress.php
```

### Admin Interface Architecture
- **Filter-Based Page Registration**: All admin pages register via `dm_register_admin_pages` filter (including core pages)
- **Base Classes**: `inc/admin/AdminPage.php` and `inc/admin/AdminMenuAssets.php` 
- **Page Templates**: `inc/admin/page-templates/` - Individual admin pages
- **JavaScript**: `assets/js/admin/` - Pipeline builder, modal system, state management  
- **Universal Modals**: All step configuration through contextual modals, eliminating complex navigation

### AI Integration
- **Multi-Provider Library**: `lib/ai-http-client/` - Separate library supporting OpenAI, Anthropic, Google, Grok, OpenRouter
- **Per-Step Configuration**: Different AI providers/models per pipeline step
- **Context Aggregation**: FluidContextBridge provides full pipeline history to AI steps

## Extension Patterns

### For Core Development
- **Add Services**: Register new services in `DataMachineFilters.php` with `dm_get_service_name` filters
- **Create Handlers**: Follow existing handler patterns in organized directories
- **Add Pipeline Steps**: Implement `execute(int $job_id): bool` method, register via hooks
- **Database Changes**: Use static methods in PSR-4 database classes

### For External Plugins
- **Override Services**: Use higher priority filters on `dm_get_*` patterns
- **Register Handlers**: Use `dm_register_handlers` filter with identical patterns to core
- **Add Step Types**: Register via `dm_register_step_types` filter
- **Modal Content**: Extend via `dm_get_modal_content` filter

## Critical Architectural Decisions

### Position-Based Pipeline Execution
Pipelines execute linearly by position (0-99) rather than complex dependency graphs. This enables intuitive drag-and-drop visual building while supporting unlimited step combinations.

### Zero Constructor Dependencies
All classes use parameter-less constructors. Services accessed exclusively via filters for maximum WordPress compatibility and external override capability.

### Universal Modal Configuration
All step configuration happens through contextual modals rather than separate admin pages, eliminating complex navigation while maintaining full functionality.