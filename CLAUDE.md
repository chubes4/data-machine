# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### WordPress Plugin Development
```bash
# No build process - changes take effect immediately
composer install           # Install dependencies
composer dump-autoload     # After adding/removing classes

# Database changes (no migrations - recreated on activation):
# Deactivate → Reactivate plugin to recreate tables
```

### Debugging & Testing
```bash
# Enable verbose browser logging:
window.dmDebugMode = true

# Test jobs manually:
# WordPress Admin → Data Machine → Projects → Run Now

# Monitor job status:
# WordPress Admin → Data Machine → Jobs
# WordPress Admin → Tools → Action Scheduler

# Debug specific job:
# Check wp_dm_jobs table for step data (input_data, processed_data, etc.)
# Action Scheduler logs show hook execution details
```

### Development Workflow
```bash
# Typical development cycle:
1. Edit handler files (changes immediate)
2. composer dump-autoload (if new files)
3. Test via admin interface
4. Check Action Scheduler for async job status
5. Monitor wp_dm_jobs table for step progression
```

## Core Architecture

**5-Step Async Pipeline**: Input Collection → AI Processing → Fact Check → Finalize → Output Publishing

**Key Patterns**:
- **PSR-4 Namespacing**: Modern namespace structure with `DataMachine\` root namespace
- **Manual DI**: Custom dependency injection in `data-machine.php` (lines 270+)
- **Handler Registry**: Dynamic filesystem scanning discovers handlers by naming convention
- **Unified Job Creation**: All jobs flow through `DataMachine\Engine\JobCreator` class
- **Action Scheduler**: Background processing with 2 max concurrent jobs

**Namespace Structure**:
```
DataMachine\
├── Admin\{Projects,ModuleConfig,RemoteLocations,OAuth}\
├── Database\{Modules,Projects,Jobs,ProcessedItems,RemoteLocations}\
├── Engine\{JobCreator,ProcessingOrchestrator,JobStatusManager}\
├── Handlers\{HandlerFactory,HandlerRegistry,Input\*,Output\*}\
├── Helpers\{Logger,ActionScheduler,HttpService,Encryption}\
└── Api\{OpenAI integration classes}\
```

## Critical File Locations

```
data-machine.php           # Bootstrap: 270+ lines of manual DI setup

admin/
├── page-templates/         # Pure view templates (no business logic)
├── module-config/          # Handler configuration system
│   ├── handler-templates/  # Input/output form templates
│   └── js/                # Frontend state management
├── projects/               # Job creation & scheduling
├── remote-locations/       # Remote WordPress management
└── oauth/                 # Social media authentication

includes/
├── engine/                 # Core processing pipeline
│   ├── class-job-creator.php           # Single entry point for all jobs
│   ├── class-processing-orchestrator.php # 5-step async coordinator
│   ├── class-job-status-manager.php    # Centralized status updates
│   └── filters/           # AI utilities (prompt builder, parser, etc.)
├── handlers/              # Input/Output handlers
│   ├── HandlerFactory.php              # Hard-coded switch statement (lines 115-141)
│   ├── class-handler-registry.php      # Filesystem scanning discovery
│   ├── input/class-data-machine-base-input-handler.php  # Shared input logic
│   └── input/output/      # Handler implementations
├── database/              # Custom wp_dm_* tables (no migrations)
├── api/                   # OpenAI integration classes
└── helpers/               # Logger, encryption, memory guard
```

## Handler Development

**Adding New Handlers**:
1. Create class extending `DataMachine\Handlers\Input\BaseInputHandler` or `DataMachine\Handlers\Output\BaseOutputHandler`
2. Implement required methods: `get_input_data()` or `handle_output()`
3. **Critical**: Add case to `HandlerFactory.php` switch statement (lines 115-141)
4. Add proper `use` statements for all dependencies
5. Update bootstrap dependencies in `data-machine.php` if needed

**Handler Pattern**:
```php
namespace DataMachine\Handlers\Input;

use DataMachine\Database\{Modules, Projects, ProcessedItems};
use DataMachine\Helpers\Logger;

class ExampleHandler extends BaseInputHandler {
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Use $this->http_service for API calls
        // Use $this->filter_processed_items() for deduplication
    }
}
```

## Job Processing Flow

**Job Creation**: 
```php
$job_creator->create_and_schedule_job($module, $user_id, $context, $optional_data);
// Returns: ['success' => bool, 'message' => string, 'job_id' => int]
```

**Async Steps**: Each step stores data in `wp_dm_jobs` table and schedules next step
- Step 1: `dm_input_job_event` → `input_data` 
- Step 2: `dm_process_job_event` → `processed_data`
- Step 3: `dm_factcheck_job_event` → `fact_checked_data`
- Step 4: `dm_finalize_job_event` → `finalized_data`
- Step 5: `dm_output_job_event` → `result_data`

## Configuration System

**Module Config**: Handler-specific templates loaded via AJAX with state management
**Remote Locations**: Form submissions (no AJAX) for reliability
**Database Schema**: Custom `wp_dm_*` tables - no migrations, created on activation

## Common Issues

**Handler Factory**: Must add explicit case to switch statement for new handlers (lines 115-141)
**Job Failures**: Check Action Scheduler status, jobs fail immediately with descriptive errors
**Large Content**: Stored in database step fields, not Action Scheduler args (8000 char limit)
**Asset Loading**: Use `DATA_MACHINE_PATH` constant for reliable file paths
**Global Container**: Access orchestrator/dependencies via `global $data_machine_container` in hooks

## Dependencies

- PHP 7.4+, WordPress 5.0+, MySQL 5.6+
- Composer packages: monolog, parsedown, twitteroauth, action-scheduler
- OpenAI API key required for AI processing steps

## Storage Architecture

**Configuration Storage**: All app-level credentials (API keys, Bluesky credentials) use global WordPress options
- OpenAI: `openai_api_key`
- Bluesky: `bluesky_username`, `bluesky_app_password`
- Twitter/Facebook/Threads: OAuth tokens stored per-user in user meta

**Database Tables**: Custom `wp_dm_*` tables managed without migrations
- Recreated on plugin activation/deactivation cycle
- `wp_dm_jobs` stores step data for async pipeline (no 8000 char limit like Action Scheduler)
- Encrypted passwords for remote locations using `EncryptionHelper`

## Critical Integration Points

**Action Scheduler Hooks**: Background processing limited to 2 concurrent jobs
```php
'dm_input_job_event', 'dm_process_job_event', 'dm_factcheck_job_event', 
'dm_finalize_job_event', 'dm_output_job_event'
```

**Remote Locations**: Enhanced with system access for automated jobs
```php
$location = $this->db_locations->get_location($location_id, null, true, true); // System access
```

**PSR-4 Type Safety**: All constructor parameters use proper namespaced types
- Always add `use` statements for dependencies  
- Namespace declaration must come before `ABSPATH` check
- Missing imports cause fatal "Class not found" errors
- **Naming Conventions**: PascalCase for classes/files (PSR-4), snake_case for slugs/identifiers/database (WordPress standard)

**Bootstrap Container**: Access dependencies via `global $data_machine_container` in hooks

## File Organization & PSR-4 Migration

The codebase has been fully migrated to PSR-4 namespacing with PascalCase filenames:

**New Structure**:
- Old: `class-data-machine-*.php` files → New: PascalCase `*.php` files
- Classes use full namespaces: `DataMachine\Database\Jobs` instead of `Data_Machine_Database_Jobs`
- File paths match namespace structure: `includes/database/Jobs.php` for `DataMachine\Database\Jobs`

**Current State**: All classes use PSR-4 autoloading via Composer with proper namespace declarations