# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### WordPress Plugin Development
- No build process required - changes take effect immediately
- Use `composer install` if dependencies are updated

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

Data Machine is a WordPress content automation plugin with a **5-step processing pipeline**:

1. **Input Collection** → 2. **AI Processing** → 3. **Fact Check** → 4. **Finalize** → 5. **Output Publishing**

### Core Architecture Patterns

**Manual Dependency Injection**: Custom DI system in `data-machine.php` (lines 230+) with global `$data_machine_container` for Action Scheduler access. No formal DI framework - all dependencies manually wired in bootstrap.

**Handler Registry Pattern**: Dynamic filesystem scanning discovers handlers by naming convention. Maps URL-friendly slugs to PHP classes with lazy loading and caching.

**Asynchronous Job Processing**: Steps 1-4 run synchronously, Step 5 queued via Action Scheduler. Job metadata stored in custom tables to avoid 8000 char Action Scheduler limit.

### Key Components

**Engine Directory** (`includes/engine/`):
- `class-job-executor.php` - Job lifecycle management (both scheduling AND execution)
- `class-job-filter.php` - Job-level concurrency control and stuck job cleanup
- `class-job-preparer.php` - Data fetching, filtering, and preparation
- `class-processing-orchestrator.php` - 5-step AI pipeline coordination

**Handler System**: 
- **Registry** (`includes/class-handler-registry.php`) - Dynamic handler discovery
- **Factory** (`module-config/HandlerFactory.php`) - Hard-coded instantiation with complex DI
- **Input Handlers** (`includes/input/`) - Implement `Data_Machine_Input_Handler_Interface`
- **Output Handlers** (`includes/output/`) - Implement `Data_Machine_Output_Handler_Interface`

**Database Layer** (`includes/database/`):
Custom WordPress tables with `wp_dm_` prefix. No migrations - created on activation only.

### Critical Dependencies & Failure Points

**Action Scheduler Integration**:
- Job concurrency limited to 2 maximum concurrent jobs
- Stuck jobs auto-failed after 6 hours via aggressive cleanup
- Custom job table duplicates some Action Scheduler functionality
- Race conditions possible between job status updates and item processing

**Handler Factory Complexity**:
Hard-coded switch statement in `HandlerFactory.php` lines 113-164. Each handler requires explicit case mapping with different constructor signatures. Adding new handlers requires updates in registry, factory, and bootstrap.

**Method Naming Confusion**:
- `execute_job()` doesn't execute - it schedules jobs
- `run_scheduled_job()` actually executes jobs  
- `get_input_handler()` vs `create_input_handler()` - registry vs factory
- `complete_job()` vs `update_job_status()` - different completion semantics

**JSON Encoding Vulnerabilities**:
Large content can exceed Action Scheduler's 8000 char limit or exhaust PHP memory. Critical job data stored in custom tables, not Action Scheduler args.

## Configuration & Constants

**Constants** (`includes/class-data-machine-constants.php`):
- `JOB_STUCK_TIMEOUT_HOURS = 6` - Job cleanup threshold
- `MAX_CONCURRENT_JOBS = 2` - Action Scheduler limit
- `ACTION_GROUP = 'data-machine'` - Job grouping

**Database Schema**:
```sql
wp_dm_projects - Project configurations
wp_dm_modules - Module settings  
wp_dm_jobs - Job queue with status tracking
wp_dm_processed_items - Deduplication by hash
wp_dm_remote_locations - Remote WordPress endpoints
```

## Important Implementation Notes

### Technical Debt & Complexity

**Monolithic Bootstrap**: `run_data_machine()` function (200+ lines) handles all dependency wiring, configuration, and hook registration. No component isolation for testing.

**Multiple Error Patterns**: Methods inconsistently return `WP_Error`, throw `Exception`, or return `false`. No standardized error codes.

**Global State Dependencies**: Heavy reliance on global variables (`$data_machine_container`, `$wpdb`) makes modular development challenging.

**Interface vs Implementation Gaps**: Handlers must implement interfaces but factory instantiation requires specific constructor signatures not enforced by interfaces.

### Handler Development

**Adding New Handlers Requires**:
1. Create handler class implementing interface (`includes/input/` or `includes/output/`)
2. Add case to factory switch statement (`HandlerFactory.php` lines 113-164)
3. Update bootstrap dependencies if new services needed (`data-machine.php`)
4. Handler will be auto-discovered by registry filesystem scan

**Critical**: Handler Factory uses hard-coded switch statement. Each handler needs explicit case mapping with exact constructor signature.

### Security
- API keys encrypted using WordPress salts
- OAuth tokens securely stored
- Input sanitization and capability checks
- Nonce verification for all AJAX requests

### WordPress Integration
- Uses WordPress database layer (`$wpdb`) exclusively
- Action Scheduler for background processing (replaces WP-Cron)
- WordPress Settings API for configuration
- Standard WordPress hooks and filters

## Common Issues & Solutions

**Jobs Getting Stuck in "Running" State**: 
- Check Action Scheduler status: WordPress Admin → Tools → Action Scheduler
- Common cause: Method name mismatches (`get_input_handler()` vs `create_input_handler()`)
- Common cause: Missing `user_id` parameter in database calls (strict PHP 8+ typing)
- Aggressive cleanup runs automatically every 6 hours
- Manual cleanup via "Run Now" button

**Handler Factory Failures**:
- Error: `Call to undefined method` - check method names match between caller and factory
- New handlers fail: must add explicit case to `HandlerFactory.php` switch statement
- Constructor signature mismatch: factory expects specific parameter order per handler

**Large Content Processing**:
- Action Scheduler has 8000 char limit for arguments
- Large content stored in `wp_dm_jobs` table, not Action Scheduler args
- Memory Guard prevents file processing issues for >100MB files

**Race Conditions**:
- Items can be processed multiple times if jobs overlap
- Job status checks happen before item deduplication  
- No database transactions ensure atomicity between job updates and item marking

## Job Processing Flow (Actual Implementation)

### Job Creation Pipeline
1. **Job Executor** receives module + user context via `schedule_job_from_config()`
2. **Job Filter** checks for existing active jobs via `can_schedule_job()`
3. **Job Preparer** fetches and filters input data via `prepare_job_packet()`
4. **Database Jobs** creates job record with "pending" status
5. **Action Scheduler** queues `dm_run_job_event` with job ID

### Job Execution Pipeline
1. **Action Scheduler** triggers `run_scheduled_job(job_id)` hook
2. **Job Executor** loads job, updates status to "running"
3. **Processing Orchestrator** runs 5-step AI pipeline synchronously
4. **Action Scheduler** queues separate `dm_output_job_event` for step 5
5. **Output Handler** processes final result asynchronously
6. **Database** marks job complete and item as processed

### Critical Race Condition
Items marked as processed **after** output job completes, not after main job completes. Creates window where same item can be picked up by concurrent jobs.

## File Organization

```
├── includes/engine/          # Core processing logic
├── includes/database/        # Database abstraction layer  
├── includes/input/          # Input data handlers
├── includes/output/         # Output publishing handlers
├── includes/interfaces/     # Handler interfaces
├── includes/helpers/        # Utility classes (Logger, Memory Guard, etc.)
├── admin/                   # WordPress admin interface
├── module-config/           # Dynamic configuration system + Handler Factory
└── vendor/                  # Composer dependencies (Action Scheduler)
```

## WordPress.org Submission Status

**Ready for submission** - critical fixes applied:
- ✅ Action Scheduler text domain conflicts resolved via Composer
- ✅ Job execution pipeline stabilized  
- ✅ Handler Factory method name mismatches fixed
- ✅ PHP 8+ strict typing issues resolved
- ✅ Dead code removed (Job Worker class)
- ⚠️ Technical debt remains but system functional