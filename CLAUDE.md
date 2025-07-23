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
- **Manual DI**: Custom dependency injection in `data-machine.php` (lines 208+)
- **Handler Registry**: Dynamic filesystem scanning discovers handlers by naming convention
- **Unified Job Creation**: All jobs flow through `Data_Machine_Job_Creator` class
- **Action Scheduler**: Background processing with 2 max concurrent jobs

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
│   ├── HandlerFactory.php              # Hard-coded switch statement (lines 115-166)
│   ├── class-handler-registry.php      # Filesystem scanning discovery
│   ├── input/class-data-machine-base-input-handler.php  # Shared input logic
│   └── input/output/      # Handler implementations
├── database/              # Custom wp_dm_* tables (no migrations)
├── api/                   # OpenAI integration classes
└── helpers/               # Logger, encryption, memory guard
```

## Handler Development

**Adding New Handlers**:
1. Create class extending `Data_Machine_Base_Input_Handler` or `Data_Machine_Base_Output_Handler`
2. Implement required methods: `get_input_data()` or `handle_output()`
3. Add case to `HandlerFactory.php` switch statement (lines 113-164)
4. Update bootstrap dependencies in `data-machine.php` if needed

**Handler Pattern**:
```php
class Data_Machine_Input_Example extends Data_Machine_Base_Input_Handler {
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

**Handler Factory**: Must add explicit case to switch statement for new handlers (lines 115-166)
**Job Failures**: Check Action Scheduler status, jobs fail immediately with descriptive errors
**Large Content**: Stored in database step fields, not Action Scheduler args (8000 char limit)
**Asset Loading**: Use `DATA_MACHINE_PATH` constant for reliable file paths
**Global Container**: Access orchestrator/dependencies via `global $data_machine_container` in hooks

## Dependencies

- PHP 8.0+, WordPress tables (`$wpdb`), Action Scheduler, Composer autoloading
- Key packages: monolog, parsedown, twitteroauth, action-scheduler