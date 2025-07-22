# Data Machine Development Plan

## Immediate: Create Unified Job Creator Architecture

### **Current Problem: 4 Different Job Entry Points**
Job creation is scattered across multiple classes with duplicated logic:

1. **"Run Now" Button** → `class-project-management-ajax.php:182` → `schedule_job()`
2. **Single Module File Upload** → `run-single-module-ajax.php:113` → `schedule_job_with_data()`  
3. **Single Module Config Run** → `run-single-module-ajax.php:115` → `schedule_job()`
4. **Scheduled Jobs** → `data-machine.php:286` → `execute_scheduled_job()`

**Note**: `prepare_and_schedule_job()` exists but appears unused (can be removed)

### **Solution: Dedicated Job Creator Class**

#### **Phase 1: Create Job Creator Class** ✅ COMPLETED
- [x] Create `includes/engine/class-job-creator.php`
- [x] Single method: `create_and_schedule_job(module, user_id, context, optional_data)`
- [x] Handles all job creation logic regardless of source
- [x] Always schedules `dm_input_job_event` for async pipeline
- [x] Absorbs logic from Job Executor's creation methods

#### **Phase 2: Update All Entry Points** ✅ COMPLETED  
- [x] Update "Run Now" AJAX to use Job Creator
- [x] Update file upload AJAX to use Job Creator
- [x] Update single module page entry point to use Job Creator
- [x] Update Module Config AJAX to use Job Creator
- [x] Wire Job Creator into dependency injection in bootstrap

#### **Phase 3: Clean Up Dependencies** 🔄 IN PROGRESS
- [x] Wire Job Creator into dependency injection in `data-machine.php`
- [x] Remove obsolete methods from Job Executor:
  - [x] Removed `prepare_and_schedule_job()`
  - [x] Removed `schedule_job()`
  - [x] Removed `schedule_job_with_data()`
  - [x] Removed `create_and_schedule_job_event()`
- [x] Remove old `dm_run_job_event` hook registration
- [x] Clean up duplicate Module Config AJAX file

#### **Phase 3b: Evaluate Remaining Components** ✅ COMPLETED
- [x] **Job Executor**: ❌ REMOVED - obsolete sync processing logic
- [x] **Job Preparer**: ❌ REMOVED - only used by Job Executor
- [x] **Job Filter**: ✅ KEPT - still needed for module concurrency & stuck job cleanup
- [x] Updated Scheduler to use Job Creator instead of Job Executor
- [x] Updated bootstrap dependencies to remove obsolete classes
- [x] Removed Job Executor require from main plugin class
- [x] Updated Composer autoload to remove deleted classes
- [x] Updated CLAUDE.md with new simplified architecture

#### **Phase 4: Fix Stuck Jobs Problem** ✅ COMPLETED
- [x] **Root Cause**: Processing Orchestrator returned `false` on errors but never marked jobs as failed
- [x] Added Job Status Manager dependency to Processing Orchestrator
- [x] Updated all step methods to properly mark jobs as failed on errors:
  - [x] `execute_input_step()` - proper failure handling with descriptive messages
  - [x] `execute_process_step()` - fails on input data missing, logic failure, or scheduling failure
  - [x] `execute_factcheck_step()` - fails on input data missing, logic failure, or scheduling failure  
  - [x] `execute_finalize_step()` - fails on input data missing, logic failure, or scheduling failure
- [x] Enhanced `schedule_next_step()` method with better error logging
- [x] Updated bootstrap to inject Job Status Manager into orchestrator
- [x] **Result**: Jobs now fail properly instead of getting stuck - proactive failure handling

#### **Phase 5: Testing** 🔄 IN PROGRESS
- [ ] Test all 4 entry points use unified Job Creator
- [ ] Verify all paths lead to same async pipeline and proper failure handling
- [ ] Confirm no code duplication in job creation
- [ ] Test that jobs fail gracefully instead of getting stuck

## Architecture Successfully Simplified ✅

**Before**: Mixed sync/async pipeline with scattered job creation and stuck job cleanup
**After**: 
- Unified Job Creator for all entry points
- Fully async 5-step pipeline  
- Proactive failure handling (jobs fail immediately instead of getting stuck)
- Clean separation of concerns

## Next: Optional Enhancements

### **Database Cleanup Implementation** (Optional)
- [ ] Create `dm_cleanup_job_event` scheduled job  
- [ ] Implement automatic pruning of large data fields after completion