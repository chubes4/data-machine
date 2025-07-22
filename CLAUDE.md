# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### WordPress Plugin Development
- No build process required - changes take effect immediately
- Use `composer install` if dependencies are updated
- Run `composer dump-autoload` after adding/removing classes

### Debugging & Testing
```bash
# Enable verbose browser logging:
window.dmDebugMode = true

# Test via admin interface:
# WordPress Admin → Data Machine → Run Single Module

# Monitor jobs:
# WordPress Admin → Data Machine → Jobs
# WordPress Admin → Tools → Action Scheduler
```

## Architecture Overview

Data Machine is a WordPress content automation plugin with a **fully async 5-step processing pipeline**:

1. **Input Collection** → 2. **AI Processing** → 3. **Fact Check** → 4. **Finalize** → 5. **Output Publishing**

**IMPORTANT**: System uses complete async pipeline via Action Scheduler. Each step runs as an independent Action Scheduler job.

### Core Architecture Patterns

**Manual Dependency Injection**: Custom DI system in `data-machine.php` (lines 208+) with global `$data_machine_container` for Action Scheduler access. No formal DI framework - all dependencies manually wired in bootstrap.

**Handler Registry Pattern**: Dynamic filesystem scanning discovers handlers by naming convention. Maps URL-friendly slugs to PHP classes with lazy loading and caching.

**Unified Job Creation**: All job creation flows through single `Data_Machine_Job_Creator` class regardless of entry point (Run Now, file upload, single module, scheduled jobs).

**Clean Separation of Concerns**: Following WordPress core patterns with distinct admin/, includes/ structure. Handler system separated from engine logic.

### Key Components

**Engine Directory** (`includes/engine/`):
- `class-job-creator.php` - Unified job creation entry point for all sources
- `class-job-filter.php` - Module-level concurrency control and stuck job cleanup
- `class-job-status-manager.php` - Centralized job status management
- `class-processing-orchestrator.php` - 5-step AI pipeline coordination
- `class-process-data.php` - Core data processing logic

**Handler System** (Pluggable Components):
- **Registry** (`includes/class-handler-registry.php`) - Dynamic handler discovery
- **Factory** (`admin/module-config/HandlerFactory.php`) - Hard-coded instantiation with complex DI
- **Input Handlers** (`includes/handlers/input/`) - Duck-typed, no interfaces
- **Output Handlers** (`includes/handlers/output/`) - Duck-typed, no interfaces

**Admin Configuration** (`admin/module-config/`):
- Settings fields and form templates for handler configuration
- AJAX handlers for admin interface
- JavaScript for dynamic configuration UI
- Remote location sync services

**Database Layer** (`includes/database/`):
Custom WordPress tables with `wp_dm_` prefix. No migrations - created on activation only.

### Critical Dependencies & Integration Points

**Action Scheduler Integration**:
- Job concurrency limited to 2 maximum concurrent jobs
- **Proactive failure handling**: Jobs fail immediately on errors (no more stuck jobs)
- Custom job table stores step-wise data for async pipeline
- Each step schedules the next step automatically

**Handler Factory Complexity**:
Hard-coded switch statement in `admin/module-config/HandlerFactory.php` lines 113-164. Each handler requires explicit case mapping with different constructor signatures. Adding new handlers requires updates in registry, factory, and bootstrap.

**Job Creator Method Signature**:
```php
create_and_schedule_job(array $module, int $user_id, string $context, ?array $optional_data = null): array
```
- Returns: `['success' => bool, 'message' => string, 'job_id' => int]`
- Always schedules `dm_input_job_event` to start async pipeline
- Contexts: 'run_now', 'file_upload', 'single_module', 'cron_project', 'cron_module'
- Output job handler fetches finalized data from database (not Action Scheduler args)

**Remote Location Management**: All remote location operations use form submissions (no AJAX) for reliability:
- Add/Edit/Delete operations via `admin/remote-locations/class-data-machine-remote-locations-form-handler.php`
- Sync service at `admin/remote-locations/class-sync-remote-locations.php` handles data synchronization
- Simple form submissions with proper nonce validation and redirect handling

**Action Scheduler Validation**:
System must validate both `action_id === false` AND `action_id === 0` as scheduling failure indicators.

## Configuration & Constants

**Constants** (`includes/class-data-machine-constants.php`):
- `JOB_STUCK_TIMEOUT_HOURS = 6` - Job cleanup threshold
- `MAX_CONCURRENT_JOBS = 2` - Action Scheduler limit
- `ACTION_GROUP = 'data-machine'` - Job grouping

**Database Schema** (with async step storage):
```sql
wp_dm_projects - Project configurations
wp_dm_modules - Module settings  
wp_dm_jobs - Job queue with step-wise data storage:
  - current_step (1-5)
  - input_data (Step 1 results)
  - processed_data (Step 2 results) 
  - fact_checked_data (Step 3 results)
  - finalized_data (Step 4 results)
  - result_data (Step 5 results)
wp_dm_processed_items - Deduplication by hash
wp_dm_remote_locations - Remote WordPress endpoints
```

## Important Implementation Notes

### Job Creation Entry Points (All use Job Creator)

1. **"Run Now" Button** → `includes/ajax/class-project-management-ajax.php` → `job_creator->create_and_schedule_job()`
2. **File Upload** → `includes/ajax/run-single-module-ajax.php` → `job_creator->create_and_schedule_job()`  
3. **Single Module** → `includes/ajax/run-single-module-ajax.php` → `job_creator->create_and_schedule_job()`
4. **Scheduled Jobs** → `includes/class-data-machine-scheduler.php` → `job_creator->create_and_schedule_job()`

### Handler Development

**Adding New Handlers Requires**:
1. Create handler class with required methods (`includes/handlers/input/` or `includes/handlers/output/`)
2. Add case to factory switch statement (`admin/module-config/HandlerFactory.php` lines 113-164)
3. Update bootstrap dependencies if new services needed (`data-machine.php`)
4. Handler will be auto-discovered by registry filesystem scan

**Critical**: Handler Factory uses hard-coded switch statement. Each handler needs explicit case mapping with exact constructor signature.

### WordPress Integration
- Uses WordPress database layer (`$wpdb`) exclusively
- Action Scheduler for background processing (replaces WP-Cron)
- WordPress Settings API for configuration
- Standard WordPress hooks and filters

## Common Issues & Solutions

**Job Failure Handling**: 
- Jobs now fail immediately on errors instead of getting stuck
- Processing Orchestrator uses Job Status Manager to mark failed jobs with descriptive error messages
- Check Action Scheduler status: WordPress Admin → Tools → Action Scheduler
- Failed jobs show specific error messages for easier debugging

**Handler Factory Failures**:
- Error: `Call to undefined method` - check method names match between caller and factory
- New handlers fail: must add explicit case to `admin/module-config/HandlerFactory.php` switch statement
- Constructor signature mismatch: factory expects specific parameter order per handler

**Large Content Processing**:
- Action Scheduler has 8000 char limit for arguments
- Large content stored in step-wise data fields in `wp_dm_jobs` table
- Memory Guard prevents file processing issues for >100MB files

**Remote Location Configuration**:
- All operations use form submissions (admin-post.php) for reliability - no AJAX complexity
- Add Location: "Sync Now" button → auto-sync → redirect to edit page with nonce
- Re-sync: Simple GET request → sync service → redirect back to edit page
- Delete: Simple confirmation → form submission → redirect to list
- All redirect URLs must include proper `_wpnonce` parameter for edit page access

## Fully Async Job Processing Flow

### Job Creation Pipeline
1. **Job Creator** receives module + user context via `create_and_schedule_job()`
2. **Job Filter** checks for existing active jobs via `can_schedule_job()`
3. **Database Jobs** creates job record with "pending" status and module config
4. **Action Scheduler** queues `dm_input_job_event` with job ID (fully async from start)

### 5-Step Async Execution Pipeline
1. **Step 1**: `dm_input_job_event` → Input Data Collection → Store in `input_data` → Schedule Step 2
2. **Step 2**: `dm_process_job_event` → AI Processing → Store in `processed_data` → Schedule Step 3  
3. **Step 3**: `dm_factcheck_job_event` → Fact Checking → Store in `fact_checked_data` → Schedule Step 4
4. **Step 4**: `dm_finalize_job_event` → Finalization → Store in `finalized_data` → Schedule Step 5
5. **Step 5**: `dm_output_job_event` → Output Publishing → Store in `result_data` → Mark complete

### Action Scheduler Hook Registration
```php
// All registered in data-machine.php:
add_action('dm_input_job_event', [$orchestrator, 'execute_input_step'], 10, 1);
add_action('dm_process_job_event', [$orchestrator, 'execute_process_step'], 10, 1);  
add_action('dm_factcheck_job_event', [$orchestrator, 'execute_factcheck_step'], 10, 1);
add_action('dm_finalize_job_event', [$orchestrator, 'execute_finalize_step'], 10, 1);
// dm_output_job_event registered by Action Scheduler helper
```

### Architecture Benefits
- Each step independent and retryable
- No data bloat in Action Scheduler arguments
- Clean step-wise data storage in database
- Single job creation path eliminates duplication
- Race conditions eliminated through proper async sequencing
- **Proactive failure handling**: Jobs fail immediately with descriptive errors instead of getting stuck

## File Organization

**Current Clean Architecture** (follows WordPress core patterns):
```
admin/                       # WordPress admin interface (follows wp-admin/ pattern)
├── module-config/           # Handler configuration UI
│   ├── ajax/               # Admin AJAX handlers
│   ├── handler-templates/  # Admin form templates
│   └── js/                 # Admin JavaScript
├── oauth/                  # OAuth authentication handlers & AJAX
├── remote-locations/       # Remote location form handlers & services
│   ├── class-data-machine-remote-locations-form-handler.php
│   ├── class-sync-remote-locations.php
│   └── class-remote-locations-list-table.php
├── templates/              # Admin page templates  
└── utilities/              # Admin utility classes

includes/                    # Core functionality (follows wp-includes/ pattern)
├── engine/                 # Core processing logic
│   ├── job-creator.php
│   ├── processing-orchestrator.php
│   └── job-status-manager.php
├── handlers/               # Pluggable input/output components
│   ├── input/              # Input data handlers
│   └── output/             # Output publishing handlers
├── ajax/                   # Core AJAX handlers
├── database/               # Database abstraction layer
├── helpers/                # Utility classes (Logger, Memory Guard, etc.)
└── api/                    # External API integrations

assets/                     # Frontend assets
└── vendor/                 # Composer dependencies (Action Scheduler)
```

**Key Architectural Decisions**:
- **Separation of Concerns**: Engine doesn't know about admin UI, handlers are self-contained
- **WordPress Conventions**: admin/ and includes/ mirror WordPress core structure
- **Dependency Flow**: admin → handlers ← engine (clean one-way dependencies)
- **Modularity**: Handlers can be added without touching core engine code