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
- **`class-processing-orchestrator.php`**: Main workflow coordinator
- **`class-job-executor.php`**: Job lifecycle management and WP-Cron integration
- **`class-job-worker.php`**: Individual job processing execution

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
- **Handler Templates**: PHP templates for each handler type's configuration UI
- **Factory Pattern**: `HandlerFactory.php` for dependency injection
- **Settings Registration**: WordPress Settings API integration
- **AJAX System**: Real-time configuration management

### Admin Interface (`admin/`)
- **Project Management**: CRUD operations for projects and modules
- **Remote Locations**: Management of remote WordPress endpoints  
- **API Keys**: Secure credential management interface
- **OAuth Integration**: Social media authentication flows

## Important Implementation Details

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
- Admin functionality separated from core logic
- Templates separated from business logic
- Extensive use of traits for shared functionality

### Testing & Debugging
- Uses `Data_Machine_Logger` class for centralized logging
- Admin notices displayed for user feedback
- No formal test suite - manual testing via admin interface

### WordPress Integration
- Follows WordPress coding standards and security practices
- Uses WordPress database layer (`$wpdb`) exclusively
- Integrates with WordPress media library and user system
- Employs WordPress cron for background job processing