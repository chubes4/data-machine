# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Composer
```bash
# Install dependencies (if composer.json is updated)
composer install

# Update dependencies  
composer update
```

### WordPress Development
This is a WordPress plugin - no specific build process required. Changes take effect immediately when files are saved.

#### Database Table Management
```bash
# Plugin activation automatically creates/updates tables via:
# Data_Machine_Database_Projects::create_table()
# Data_Machine_Database_Modules::create_table()
# Data_Machine_Database_Jobs::create_table()
# Data_Machine_Database_Remote_Locations::create_table()
```

#### Manual Testing
```bash
# Test single module execution via admin interface:
# WordPress Admin → Data Machine → Run Single Module

# Monitor Action Scheduler jobs:
# WordPress Admin → Tools → Action Scheduler
```

## Project Architecture

### Core Workflow
The Data Machine plugin follows a structured 5-step processing pipeline:
1. **Input Collection** - Gather data from various sources (files, RSS, Reddit, REST APIs)
2. **Initial Processing** - Send to OpenAI API for analysis and transformation  
3. **Fact Checking** - AI-powered content validation (optional, can be skipped)
4. **Content Finalization** - Generate final output using project-specific prompts
5. **Output Publishing** - Distribute to configured destinations (WordPress, social media, exports)

### Key Components

#### Main Plugin File (`data-machine.php`)
- Plugin initialization and dependency injection
- Defines constants: `DATA_MACHINE_VERSION`, `DATA_MACHINE_PATH`
- Orchestrates all service instantiation and dependencies
- Uses extensive manual dependency injection (no container)

#### Database Layer (`includes/database/`)
- **Projects**: Main organizational unit for workflows
- **Modules**: Individual processing configurations within projects  
- **Jobs**: Queue management for processing tasks
- **Processed Items**: Deduplication and history tracking
- **Remote Locations**: Remote WordPress publishing endpoints

#### Processing Engine (`includes/engine/`)
- **`class-processing-orchestrator.php`**: Main workflow coordinator - handles 5-step pipeline
- **`class-job-executor.php`**: Job lifecycle management and Action Scheduler integration
- **`class-job-worker.php`**: Individual job processing execution with retry logic
- **`class-process-data.php`**: OpenAI API integration for initial data processing

#### Handler System
**Input Handlers** (`includes/input/`): Data collection from various sources
- Files, RSS feeds, Reddit, REST APIs, Airdrop helper
- All implement `Input_Handler_Interface`
- Use `trait-data-machine-base-input-handler.php` for common functionality

**Output Handlers** (`includes/output/`): Content publishing to destinations  
- WordPress (local/remote), Twitter, Facebook, Threads, Bluesky, data export
- All implement `Data_Machine_Output_Handler_Interface`
- Use `trait-data-machine-base-output-handler.php` for common functionality

#### Centralized Prompt System (`includes/helpers/class-data-machine-prompt-builder.php`)
Single source of truth for all AI prompt construction:
- `build_system_prompt()`: Project prompts with context
- `build_process_data_prompt()`: Initial processing instructions
- `build_fact_check_prompt()`: Content validation prompts
- `build_finalize_prompt()`: Output-specific formatting
- Replaces scattered prompt logic across multiple files

#### Authentication & API Integration
- **OAuth Handlers** (`admin/oauth/`): Social media authentication (Twitter, Reddit, Facebook, Threads)
- **API Classes** (`includes/api/`): OpenAI integration for content processing
- **Encryption Helper**: Secure storage of API keys and credentials

### Database Schema
Uses WordPress database with custom tables:
- `wp_dm_projects`: Project configurations and prompts
- `wp_dm_modules`: Module settings with input/output handler configs
- `wp_dm_jobs`: Processing queue with status tracking
- `wp_dm_processed_items`: Content deduplication by hash
- `wp_dm_remote_locations`: Remote publishing endpoints

### Module Configuration System (`module-config/`)
Dynamic UI system for configuring input/output handlers:
- **Handler Templates**: PHP templates for each handler type's configuration UI (`handler-templates/`)
- **Factory Pattern**: `HandlerFactory.php` for dependency injection and handler instantiation
- **Settings Registration**: WordPress Settings API integration (`RegisterSettings.php`)
- **AJAX System**: Real-time configuration management with ES6 modules (`js/`)
- **State Management**: Centralized UI state with `module-config-state.js` and `module-state-controller.js`

### Admin Interface (`admin/`)
- **Project Management**: CRUD operations for projects and modules
- **Remote Locations**: Management of remote WordPress endpoints  
- **API Keys**: Secure credential management interface
- **OAuth Integration**: Social media authentication flows

## Important Implementation Details

### Action Scheduler Integration
The plugin uses Action Scheduler for async job processing:
- **Action Group**: `data-machine` for all plugin jobs
- **Concurrency**: Maximum 2 concurrent jobs (`MAX_CONCURRENT_JOBS`)
- **Retry Logic**: 3 attempts for failed output jobs with exponential backoff
- **Hook**: `dm_output_job_event` for output processing jobs
- **Status Tracking**: Jobs tracked in `wp_dm_jobs` table with Action Scheduler integration

### Dependency Injection
All major classes use constructor injection managed through `Dependency_Injection_Handler_Factory`. No formal DI container - dependencies manually wired in `data-machine.php`.

### Error Handling & Authentication  
Enhanced authentication error detection in `class-job-executor.php`:
- Pattern-based detection of auth failures
- Service-specific user guidance messages
- Improved job status tracking (`failed` vs `failed_auth`)

### Skip Fact Check Feature
Conditional validation system allowing users to bypass fact-checking:
- Backend validation only requires fact check prompt when feature is disabled
- Frontend UI provides visual feedback when fact checking is skipped

### Character Limit Optimizations
Bluesky handler uses correct URL character counting (22 chars) to maximize content space.

### Security Implementation  
- API key encryption using WordPress salts
- Nonce verification for all AJAX requests
- Input sanitization and capability checks
- OAuth token secure storage

## Development Notes

### Naming Conventions
- **PHP Classes**: PascalCase with underscores (`Data_Machine_Class_Name`)
- **Database Keys**: snake_case (`input_config`, `output_config`) 
- **JavaScript**: camelCase (`moduleConfigState`)
- **CSS**: kebab-case (`data-machine-admin`)

### Code Organization
- Handler classes grouped by functionality (`input/`, `output/`)
- Admin functionality separated from core logic (`admin/`)
- Templates separated from business logic (`admin/templates/`)
- Extensive use of traits for shared functionality (`trait-data-machine-base-*-handler.php`)
- Module configuration system isolated in `module-config/` directory
- Third-party libraries bundled in `libraries/` (Action Scheduler)
- Vendor dependencies via Composer autoloader

### Testing & Debugging
- Uses `Data_Machine_Logger` class for centralized logging
- Admin notices displayed for user feedback
- No formal test suite - manual testing via admin interface

### JavaScript Architecture
- **ES6 Modules**: Modern module system with import/export
- **State Management**: Centralized state in `module-config-state.js`
- **AJAX Handling**: Dedicated AJAX classes (`module-config-ajax.js`)
- **UI Controllers**: Separate controllers for different UI sections
- **Event-Driven**: Pub/sub pattern for state changes and UI updates
- **Template Management**: Dynamic loading of handler configuration templates

### Handler Development
When creating new input/output handlers:
- **Interface Implementation**: Must implement `Data_Machine_Input_Handler_Interface` or `Data_Machine_Output_Handler_Interface`
- **Settings Fields**: Define configuration UI via `get_settings_fields()` method
- **Templates**: Create PHP template in `module-config/handler-templates/`
- **Registration**: Register in `Data_Machine_Handler_Registry`
- **Sanitization**: Implement `sanitize_settings()` method for user input
- **Traits**: Use base traits for common functionality

### WordPress Integration
- Follows WordPress coding standards and security practices
- Uses WordPress database layer (`$wpdb`) exclusively
- Integrates with WordPress media library and user system
- Employs Action Scheduler for background job processing (replacing WP-Cron)