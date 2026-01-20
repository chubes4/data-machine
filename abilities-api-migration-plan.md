# Data Machine Abilities API Migration Plan

## Executive Summary

**Clean Break Migration to WordPress 6.9 Abilities API**

Eliminate entire service layer (`inc/Services/`) and migrate all business logic to WordPress 6.9 Abilities API. REST endpoints delegate to abilities. Chat tools call abilities directly. No backward compatibility.

**Current Architecture:**
```
CLI Commands ─┐
REST API ────┼──→ Services Layer (partial) ──→ Database
Chat Tools ──┘    + Abilities (partial)
```

**Target Architecture:**
```
CLI Commands ─┐
REST API ────┼──→ Abilities Layer (50+) ──→ Database
Chat Tools ──┘
```

**Abilities as Universal Primitive:** All three consumer layers (CLI, REST, Chat) call the same abilities. This ensures consistent behavior, permissions, and business logic across all interfaces.

---

## Current Codebase Analysis

### Service Layer (To Eliminate)

| File | Lines | Key Methods | Dependencies | Status |
|------|-------|--------------|--------------|--------|
| `HandlerService.php` | ~120 | `getAll()`, `get()`, `exists()`, `validate()`, `clearCache()` | `datamachine_handlers` filter | ✅ **DELETED** (v0.11.7) |
| `StepTypeService.php` | 105 | `get()`, `getAll()` | `datamachine_step_types` filter | ✅ **DELETED** (v0.11.7) |
| `PipelineManager.php` | 367 | `create()`, `duplicate()`, `import()`, `export()`, `delete()` | FlowManager, PipelineStepManager, PipelinesDB | ✅ **DELETED** - PipelineAbilities is self-contained |
| `PipelineStepManager.php` | 453 | `add()`, `delete()`, `reorder()`, `updateHandler()`, `updateUserMessage()` | PipelinesDB, FlowManager, StepTypeAbilities | ✅ **DELETED** - PipelineStepAbilities is self-contained |
| `FlowManager.php` | 681 | `create()`, `delete()`, `update()`, `duplicate()`, `syncStepsToFlow()` | FlowsDB, PipelinesDB, FlowStepManager | ✅ **DELETED** - FlowAbilities is self-contained |
| `JobManager.php` | 270 | `create()`, `get()`, `getForFlow()`, `getForPipeline()`, `updateStatus()`, `fail()`, `delete()` | JobsDB, ProcessedItemsManager | ⚠️ Used by JobAbilities (migration incomplete) |
| `FlowStepManager.php` | 393 | `add()`, `delete()`, `update()`, `configure()` | FlowsDB, FlowManager, HandlerAbilities | ⚠️ Used by FlowStepAbilities (migration incomplete) |
| `ProcessedItemsManager.php` | 120 | `getForJob()`, `deleteForJob()`, `clearForCriteria()` | ProcessedItemsDB | ⚠️ Used by ProcessedItemsAbilities (migration incomplete) |
| `AuthProviderService.php` | ~150 | `getAll()`, `get()`, `exists()`, `validate()`, `clearCache()` | `datamachine_auth_providers` filter | ⚠️ Used by AuthAbilities (complex OAuth logic) |
| `CacheManager.php` | ~75 | `invalidate()`, `clear()` | HandlerAbilities, StepTypeAbilities | Retained (utility) |
| `LogsManager.php` | ~100 | `getLogPath()`, `clear()`, `getSize()`, `cleanup()` | DATAMACHINE_LOG_DIR | Retained (file operations) |

**Service Layer Status:** 5 of 11 files deleted (HandlerService, StepTypeService, PipelineManager, PipelineStepManager, FlowManager). 4 services still used internally by abilities (migration incomplete). 2 retained as utilities (CacheManager, LogsManager).

**Architectural Note:** The Services layer was Data Machine's internal implementation of an "abilities-style" API before WordPress 6.9 shipped the Abilities API. The migration consolidates this custom implementation into the WordPress-native standard.

### Abilities Self-Containment Status

| Ability Class | Services Used | Status |
|---------------|---------------|--------|
| `FlowAbilities` | None | ✅ Self-contained |
| `PipelineAbilities` | None | ✅ Self-contained |
| `PipelineStepAbilities` | None | ✅ Self-contained (includes `syncStepsToFlow()`) |
| `FileAbilities` | None | ✅ Self-contained |
| `SettingsAbilities` | None | ✅ Self-contained |
| `HandlerAbilities` | None | ✅ Self-contained |
| `StepTypeAbilities` | None | ✅ Self-contained |
| `LogAbilities` | None | ✅ Self-contained |
| `PostQueryAbilities` | None | ✅ Self-contained |
| `JobAbilities` | JobManager | ⚠️ Delegates to service |
| `FlowStepAbilities` | FlowStepManager | ⚠️ Delegates to service |
| `ProcessedItemsAbilities` | ProcessedItemsManager | ⚠️ Delegates to service |
| `AuthAbilities` | AuthProviderService | ⚠️ Delegates to service |

**Summary:** 9 of 13 ability classes (69%) are fully self-contained. 4 ability classes still delegate to services.

### REST API Layer (To Update)

**Delegation Points Identified:**
- `Pipelines.php`: 8+ calls to `PipelineManager::create()`, `PipelineManager::delete()`, etc.
- `Flows.php`: 7+ calls to `FlowManager::create()`, `FlowManager::delete()`, etc.
- `PipelineSteps.php`: 6+ calls to `PipelineStepManager::add()`, `PipelineStepManager::delete()`, etc.
- `FlowSteps.php`: 6+ calls to `FlowStepManager::add()`, `FlowStepManager::delete()`, etc.
- `Execute.php`: Direct call to job execution engine
- `Jobs.php`: 3+ calls to `JobManager::get()`, `JobManager::delete()`
- `ProcessedItems.php`: 2+ calls to `ProcessedItemsManager::clearForCriteria()`
- `Settings.php`: Multiple calls to `CacheManager::invalidate()`, `LogsManager::clear()`
- `Auth.php`: 4+ calls to `AuthProviderService`, `HandlerService`
- `Files.php`: Direct calls to file repository
- `Tools.php`: Tool registration via filters
- `Handlers.php`: Handler listing via `HandlerService`
- `StepTypes.php`: Step type listing via `StepTypeService`
- `Providers.php`: Direct WP-AI client calls
- `Users.php`: User management
- `Logs.php`: Log listing via `LogsManager`

### Chat Tools (To Migrate)

**Current Tools (inc/Api/Chat/Tools/):**
- `CreateFlow.php` - Calls `FlowManager::create()` via service
- `RunFlow.php` - Calls REST API `/execute` (calls JobManager internally)
- `DeleteFlow.php` - Calls `FlowManager::delete()`
- `UpdateFlow.php` - Calls `FlowManager::update()`
- `CopyFlow.php` - Calls `FlowManager::duplicate()`
- `CreatePipeline.php` - Calls `PipelineManager::create()`
- `DeletePipeline.php` - Calls `PipelineManager::delete()`
- `AddPipelineStep.php` - Calls `PipelineStepManager::add()`
- `DeletePipelineStep.php` - Calls `PipelineStepManager::delete()`
- `ReorderPipelineSteps.php` - Calls `PipelineStepManager::reorder()`
- `ConfigurePipelineStep.php` - Calls `PipelineStepManager::updateHandler()`
- `ConfigureFlowSteps.php` - Calls `PipelineStepManager::configure()`
- `SetHandlerDefaults.php` - Calls `HandlerService`
- `GetHandlerDefaults.php` - Calls `HandlerService`
- `AssignTaxonomyTerm.php`, `UpdateTaxonomyTerm.php`, etc. - Calls taxonomy handlers
- `ReadLogs.php` - Calls `LogsManager`
- `ManageLogs.php` - Calls `LogsManager`

**Total Chat Tools:** ~30 tools

### Test Files (Current)

| File | Tests | Coverage |
|------|--------|----------|
| `Services/PipelineManagerTest.php` | ~300 lines | PipelineManager |
| `Services/FlowManagerTest.php` | ~250 lines | FlowManager |
| `AI/Tools/PipelineToolsAvailabilityTest.php` | ~200 lines | Tool registration |
| `AI/Tools/ChatToolsAvailabilityTest.php` | ~200 lines | Chat tools |
| `WordPress/WordPressPublishHelperTest.php` | ~100 lines | Publishing |
| `Events/UniversalWebScraperFlowTest.php` | ~150 lines | Events extension |

**Total Tests:** 6 test files, ~1200 lines

---

## Target Architecture

### New Directory Structure

```
inc/
├── Abilities/                    ← ABILITIES LAYER (not inc/Engine/Abilities/)
│   ├── FlowAbilities.php        (Flow CRUD) ✅ EXISTS
│   ├── LogAbilities.php         ✅ EXISTS
│   ├── PostQueryAbilities.php   ✅ EXISTS
│   ├── PipelineAbilities.php    (Pipeline CRUD)
│   ├── PipelineStepAbilities.php
│   ├── FlowStepAbilities.php
│   ├── JobAbilities.php
│   ├── ProcessedItemsAbilities.php
│   ├── FileAbilities.php
│   ├── AuthAbilities.php
│   └── SettingsAbilities.php
├── Cli/
│   └── Commands/                ← CLI LAYER (thin wrappers calling abilities)
│       ├── FlowsCommand.php     ✅ EXISTS (uses FlowAbilities)
│       ├── PipelinesCommand.php (future)
│       ├── JobsCommand.php      (future)
│       ├── LogsCommand.php      (future)
│       └── ...
├── Api/                         ← REST LAYER (delegate to abilities)
│   ├── Pipelines/
│   │   └── Pipelines.php
│   ├── Flows/
│   │   ├── Flows.php
│   │   ├── FlowSteps.php
│   │   └── FlowScheduling.php
│   ├── Chat/
│   │   ├── Tools/           ← CHAT LAYER (call abilities via wp_get_ability())
│   │   │   ├── CreateFlow.php
│   │   │   ├── RunFlow.php
│   │   │   ├── DeleteFlow.php
│   │   │   └── ...
│   │   └── Chat.php
│   ├── Jobs.php
│   ├── ProcessedItems.php
│   ├── Files.php
│   ├── Settings.php
│   ├── Auth.php
│   ├── Tools.php
│   ├── Handlers.php
│   ├── StepTypes.php
│   ├── Providers.php
│   ├── Users.php
│   └── Logs.php
├── Services/                    ← ELIMINATE ENTIRELY
│   ├── PipelineManager.php
│   ├── FlowManager.php
│   ├── PipelineStepManager.php
│   ├── FlowStepManager.php
│   ├── JobManager.php
│   ├── ProcessedItemsManager.php
│   ├── StepTypeService.php
│   ├── AuthProviderService.php
│   ├── HandlerService.php
│   ├── CacheManager.php
│   └── LogsManager.php
└── Engine/                      ← EXECUTION ENGINE (kept separate from abilities)
    ├── AI/
    │   └── Tools/
    │       ├── ToolExecutor.php
    │       └── ToolManager.php
    ├── Logger.php
    ├── Actions/
    │   └── DataMachineActions.php
    ├── ConversationManager.php
    ├── RequestBuilder.php
    └── AIConversationLoop.php
```

---

## Abilities Registry

### Naming Convention

Abilities follow action-based naming for clarity and consistency:

| Prefix | Purpose | Example |
|--------|---------|---------|
| `get-*` | Query/list items (single or multiple) | `datamachine/get-flows`, `datamachine/get-pipelines` |
| `get-*` (single) | Get single item by ID (deprecated pattern) | Use `get-flows` with `flow_id` param instead |
| `create-*` | Create new item | `datamachine/create-flow`, `datamachine/create-pipeline` |
| `update-*` | Update existing item | `datamachine/update-flow`, `datamachine/update-pipeline` |
| `delete-*` | Delete item | `datamachine/delete-flow`, `datamachine/delete-pipeline` |
| `query-*` | Complex queries with filters | `datamachine/query-posts-by-handler` |
| Special actions | Domain-specific operations | `duplicate-*`, `schedule-*`, `run-*`, `clear-*` |

### Phase 1: Foundation Abilities ✅ (DONE)

**Completed Abilities:**
| Ability | File | Description |
|---------|------|-------------|
| `datamachine/get-flows` | `FlowAbilities.php` | Get/query flows with filtering by pipeline, handler, flow_id, output_mode |
| `datamachine/write-to-log` | `LogAbilities.php` | Write log entries with level routing |
| `datamachine/clear-logs` | `LogAbilities.php` | Clear log files by agent type |
| `datamachine/query-posts-by-handler` | `PostQueryAbilities.php` | Find posts created by a specific handler |
| `datamachine/query-posts-by-flow` | `PostQueryAbilities.php` | Find posts created by a specific flow |
| `datamachine/query-posts-by-pipeline` | `PostQueryAbilities.php` | Find posts created by a specific pipeline |
| `datamachine/create-flow` | `FlowAbilities.php` | Create a new flow for a pipeline |
| `datamachine/update-flow` | `FlowAbilities.php` | Update flow name or scheduling |
| `datamachine/delete-flow` | `FlowAbilities.php` | Delete flow and unschedule actions |
| `datamachine/duplicate-flow` | `FlowAbilities.php` | Duplicate flow, optionally cross-pipeline |

### Handler & Step Type Abilities ✅ (DONE - v0.11.6)

**Completed Abilities:**
| Ability | File | Description |
|---------|------|-------------|
| `datamachine/get-handlers` | `HandlerAbilities.php` | List handlers with optional step_type filter |
| `datamachine/get-handler` | `HandlerAbilities.php` | Get single handler by slug |
| `datamachine/validate-handler` | `HandlerAbilities.php` | Validate handler slug exists |
| `datamachine/get-handler-config-fields` | `HandlerAbilities.php` | Get config field definitions for handler |
| `datamachine/apply-handler-defaults` | `HandlerAbilities.php` | Apply site defaults to handler config |
| `datamachine/get-handler-site-defaults` | `HandlerAbilities.php` | Get site-wide handler defaults |
| `datamachine/get-step-types` | `StepTypeAbilities.php` | List all registered step types |
| `datamachine/get-step-type` | `StepTypeAbilities.php` | Get single step type by slug |
| `datamachine/validate-step-type` | `StepTypeAbilities.php` | Validate step type slug exists |

**Migration Completed:**
- All HandlerService consumers migrated to HandlerAbilities
- All StepTypeService consumers migrated to StepTypeAbilities
- REST API endpoints, Chat tools, and Core classes now use abilities
- **`HandlerService.php` DELETED** - no external consumers
- **`StepTypeService.php` DELETED** - no external consumers

**Files Updated in Final Cleanup (v0.11.7):**
- `Api/Pipelines/PipelineSteps.php` - Replaced StepTypeService and PipelineStepManager calls with abilities
- `Api/Flows/FlowSteps.php` - Fixed broken HandlerService reference, removed unused import
- `Services/FlowStepManager.php` - Replaced HandlerService with HandlerAbilities
- `Services/PipelineManager.php` - Replaced StepTypeService with StepTypeAbilities
- `Services/PipelineStepManager.php` - Replaced StepTypeService with StepTypeAbilities
- `Services/CacheManager.php` - Updated to use ability clearCache() methods
- `Services/AuthProviderService.php` - Replaced HandlerService with HandlerAbilities
- `Engine/Actions/Engine.php` - Replaced StepTypeService with StepTypeAbilities

**Completed CLI Commands:**
| Command | Ability | Features |
|---------|---------|----------|
| `wp datamachine flows` | `datamachine/get-flows` | `--id`, `get` subcommand, pipeline filter, `--handler` filter, `--output` mode, pagination |

### Phase 2: Pipeline CRUD Operations ✅ (DONE)
**Status:** COMPLETE - Abilities registered AND self-contained (no service dependency)

**Completed Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/get-pipelines` | Get/query pipelines with filtering and pagination |
| `datamachine/get-pipeline` | Retrieve a specific pipeline by ID |
| `datamachine/create-pipeline` | Create a new data processing pipeline |
| `datamachine/update-pipeline` | Update pipeline configuration |
| `datamachine/delete-pipeline` | Delete pipeline and associated data |
| `datamachine/duplicate-pipeline` | Duplicate a pipeline with all its flows |
| `datamachine/import-pipelines` | Import pipelines from JSON or CSV |
| `datamachine/export-pipelines` | Export pipelines to JSON or CSV |

**Implementation Details:**
- `PipelineAbilities.php` contains 8 self-contained abilities with business logic
- REST API endpoints delegate to abilities via `wp_get_ability()`
- Chat tools call abilities directly
- `PipelineManager.php` potentially unused (verify before deletion)

### Phase 3: Flow CRUD Operations ✅ (DONE)
**Status:** COMPLETE

**Completed Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/get-flows` | Get/query flows with filtering (existed from Phase 1) |
| `datamachine/create-flow` | Create a new flow for a pipeline |
| `datamachine/update-flow` | Update flow name or scheduling configuration |
| `datamachine/delete-flow` | Delete flow and unschedule associated actions |
| `datamachine/duplicate-flow` | Duplicate flow, optionally to a different pipeline |

**Implementation Details:**
- Business logic migrated from `FlowManager.php` into `FlowAbilities.php`
- REST handlers in `Flows.php` now delegate to abilities via `wp_get_ability()`
- Chat tools (CreateFlow, DeleteFlow, UpdateFlow, CopyFlow) now call abilities directly
- 18 new tests added to `FlowAbilitiesTest.php` covering all CRUD operations

**Note:** `schedule-flow` and `unschedule-flow` abilities are deferred to a future phase as scheduling is currently handled inline within `create-flow` and `update-flow` via the `scheduling_config` parameter.

### Phase 4: Pipeline Steps Operations ✅ (DONE)
**Status:** COMPLETE - Abilities registered AND self-contained (no service dependency)

**Completed Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/get-pipeline-steps` | List steps for a pipeline |
| `datamachine/get-pipeline-step` | Get single pipeline step |
| `datamachine/add-pipeline-step` | Add step to pipeline |
| `datamachine/update-pipeline-step` | Update pipeline step config |
| `datamachine/delete-pipeline-step` | Delete pipeline step |
| `datamachine/reorder-pipeline-steps` | Reorder pipeline steps |

**Implementation Details:**
- All PipelineStepManager business logic inlined into `PipelineStepAbilities.php`
- Added private `syncStepsToFlow()` method for flow step synchronization
- `ImportExport.php` updated to use `datamachine/add-pipeline-step` ability
- **`PipelineStepManager.php` DELETED** (~453 lines)

### Phase 5: Flow Steps Operations ⚠️ (ABILITIES REGISTERED)
**Status:** Abilities exist but `FlowStepManager` still used internally

**Registered Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/get-flow-steps` | List steps for a flow |
| `datamachine/get-flow-step` | Get single flow step |
| `datamachine/update-flow-step` | Update flow step config |
| `datamachine/configure-flow-steps` | Bulk configure flow steps |

**Remaining Work:**
- Migrate `FlowStepManager` business logic into `FlowStepAbilities`
- Update abilities to be self-contained (no service delegation)
- Verify and delete `FlowStepManager.php`

### Phase 6: Execution Operations ⚠️ (ABILITIES REGISTERED)
**Status:** Abilities exist but `JobManager` still used internally

**Registered Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/run-flow` | Execute flow immediately or schedule for later |
| `datamachine/get-jobs` | List jobs with filtering |
| `datamachine/get-job` | Get single job by ID |
| `datamachine/delete-jobs` | Delete jobs |
| `datamachine/get-flow-health` | Get flow execution health metrics |
| `datamachine/get-problem-flows` | Get flows exceeding failure threshold |

**Remaining Work:**
- Migrate `JobManager` business logic into `JobAbilities`
- Update abilities to be self-contained (no service delegation)
- Verify and delete `JobManager.php`

### Phase 7: File Management ✅ (DONE)
**Status:** COMPLETE - Abilities registered AND self-contained (no service dependency)

**Completed Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/list-files` | List files for a flow |
| `datamachine/get-file` | Get single file metadata |
| `datamachine/upload-file` | Upload new file |
| `datamachine/delete-file` | Delete file |
| `datamachine/cleanup-files` | Clean up orphaned/old files |

**Implementation Details:**
- `FileAbilities.php` contains 5 self-contained abilities
- Direct FilesRepository access (no intermediate service layer)
- REST API endpoints delegate to abilities

### Phase 8: Processed Items ⚠️ (ABILITIES REGISTERED)
**Status:** Abilities exist but `ProcessedItemsManager` still used internally

**Registered Abilities:**
| Ability | Description |
|---------|-------------|
| `datamachine/clear-processed-items` | Clear processed item history |
| `datamachine/check-processed-item` | Check if item was already processed |
| `datamachine/has-processed-history` | Check if flow has processing history |

**Remaining Work:**
- Migrate `ProcessedItemsManager` business logic into `ProcessedItemsAbilities`
- Update abilities to be self-contained (no service delegation)
- Verify and delete `ProcessedItemsManager.php`

---

### Phase 9: Settings & Auth ⚠️ (PARTIAL)
**Status:** HandlerAbilities/StepTypeAbilities complete and self-contained; AuthAbilities still uses AuthProviderService

**Completed Abilities (Self-Contained):**
| Ability | File | Description |
|---------|------|-------------|
| `datamachine/get-settings` | SettingsAbilities | Get current settings |
| `datamachine/update-settings` | SettingsAbilities | Update settings |
| `datamachine/get-scheduling-intervals` | SettingsAbilities | Get available scheduling intervals |
| `datamachine/get-tool-config` | SettingsAbilities | Get tool configuration |
| `datamachine/get-handler-defaults` | SettingsAbilities | Get handler default config |
| `datamachine/update-handler-defaults` | SettingsAbilities | Set handler default config |
| `datamachine/get-handlers` | HandlerAbilities | List handlers with filtering |
| `datamachine/get-handler` | HandlerAbilities | Get single handler |
| `datamachine/validate-handler` | HandlerAbilities | Validate handler exists |
| `datamachine/get-handler-config-fields` | HandlerAbilities | Get handler config fields |
| `datamachine/apply-handler-defaults` | HandlerAbilities | Apply site defaults to config |
| `datamachine/get-handler-site-defaults` | HandlerAbilities | Get site-wide handler defaults |

**Abilities With Service Dependency:**
| Ability | File | Service Used | Description |
|---------|------|--------------|-------------|
| `datamachine/get-auth-status` | AuthAbilities | AuthProviderService | Check handler auth status |
| `datamachine/disconnect-auth` | AuthAbilities | AuthProviderService | Remove auth credentials |
| `datamachine/save-auth-config` | AuthAbilities | AuthProviderService | Save authentication config |

**Remaining Work:**
- Decide: migrate AuthProviderService logic into AuthAbilities OR retain service (complex OAuth flows)

---

## REST API Updates

### Pattern: Ability Wrapper Method

```php
// inc/Api/Pipelines/Pipelines.php

use DataMachine\Services\PipelineManager; // ← DELETE THIS IMPORT

class Pipelines {
    
    // NEW HELPER METHOD
    private static function executeAbility(string $abilityName, array $params): array {
        $ability = wp_get_ability($abilityName);
        $result = $ability->execute($params);
        
        if (!$result['success']) {
            return new WP_Error(
                $result['error_code'] ?? 'ability_execution_failed',
                $result['message'] ?? 'Ability execution failed',
                ['status' => 403]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $result['data']
        ]);
    }
    
    public static function handle_create_pipeline($request) {
        // OLD: $manager = new PipelineManager();
        // OLD: $result = $manager->create(...);
        
        // NEW:
        return self::executeAbility('datamachine/create-pipeline', [
            'pipeline_name' => $request->get_param('pipeline_name'),
            'steps' => $request->get_param('steps'),
            'flow_config' => $request->get_param('flow_config'),
            'batch_import' => $request->get_param('batch_import', false)
        ]);
    }
    
    public static function handle_list_pipelines($request) {
        return self::executeAbility('datamachine/get-pipelines', [
            'per_page' => $request->get_param('per_page'),
            'offset' => $request->get_param('offset'),
            'fields' => $request->get_param('fields'),
            'format' => $request->get_param('format'),
        ]);
    }

    public static function handle_get_pipeline($request) {
        return self::executeAbility('datamachine/get-pipeline', [
            'pipeline_id' => (int) $request->get_param('pipeline_id'),
        ]);
    }
    
    public static function handle_delete_pipeline($request) {
        return self::executeAbility('datamachine/delete-pipeline', [
            'pipeline_id' => (int) $request->get_param('pipeline_id')
        ]);
    }
    
    // ... all other endpoints follow same pattern
}
```

### All REST Endpoints to Update

**Files:**
1. `Pipelines.php` - 8 endpoints → `PipelineAbilities`
2. `Flows.php` - 7 endpoints → `FlowAbilities`
3. `PipelineSteps.php` - 6 endpoints → `PipelineStepAbilities`
4. `FlowSteps.php` - 6 endpoints → `FlowStepAbilities`
5. `Execute.php` - 2 endpoints → `JobAbilities`
6. `Jobs.php` - 5 endpoints → `JobAbilities`
7. `ProcessedItems.php` - 2 endpoints → `ProcessedItemsAbilities`
8. `Settings.php` - 8 endpoints → `SettingsAbilities`
9. `Auth.php` - 4 endpoints → `AuthAbilities`
10. `Tools.php` - 2 endpoints → `StepTypeAbilities`
11. `Handlers.php` - 2 endpoints → (Handler discovery moved to abilities)
12. `StepTypes.php` - 2 endpoints → (Step type listing moved to abilities)
13. `Providers.php` - 2 endpoints → (WP-AI client calls remain)
14. `Users.php` - 3 endpoints → (User management remains)
15. `Logs.php` - 4 endpoints → `LogsAbilities` (or use `datamachine/clear-logs` ability)

---

## CLI Commands

### Pattern: Command Delegates to Ability

CLI commands are thin wrappers that:
1. Parse arguments and flags from command line
2. Call ability's `executeAbility()` method
3. Format output for terminal (table, JSON, etc.)

### Existing Implementation: FlowsCommand

```php
// inc/Cli/Commands/FlowsCommand.php (✅ EXISTS)

class FlowsCommand extends WP_CLI_Command {

    public function __invoke(array $args, array $assoc_args): void {
        // 1. Parse arguments
        $flow_id = isset($assoc_args['id']) ? (int) $assoc_args['id'] : null;
        $pipeline_id = !empty($args) ? (int) $args[0] : null;
        $handler_slug = $assoc_args['handler'] ?? null;
        $per_page = (int) ($assoc_args['per_page'] ?? 20);
        $offset = (int) ($assoc_args['offset'] ?? 0);
        $format = $assoc_args['format'] ?? 'table';

        // 2. Call ability
        $ability = new \DataMachine\Abilities\FlowAbilities();
        $result = $ability->executeAbility([
            'flow_id'      => $flow_id,
            'pipeline_id'  => $pipeline_id,
            'handler_slug' => $handler_slug,
            'per_page'     => $per_page,
            'offset'       => $offset,
        ]);

        // 3. Format output
        $this->outputResult($result, $format);
    }
}
```

### CLI Commands Roadmap

| Command | Ability | Status |
|---------|---------|--------|
| `wp datamachine flows` | `datamachine/get-flows` | ✅ Done |
| `wp datamachine flows get <id>` | `datamachine/get-flows` (with flow_id) | ✅ Done |
| `wp datamachine pipelines` | `datamachine/get-pipelines` | Planned |
| `wp datamachine pipelines get <id>` | `datamachine/get-pipeline` | Planned |
| `wp datamachine jobs` | `datamachine/get-jobs` | Planned |
| `wp datamachine jobs get <id>` | `datamachine/get-job` | Planned |
| `wp datamachine logs` | `datamachine/read-logs` | Planned |
| `wp datamachine logs clear` | `datamachine/clear-logs` | Planned |
| `wp datamachine run <flow_id>` | `datamachine/run-flow` | Planned |

### CLI Command Features

Commands support:
- **Positional args:** `wp datamachine flows 5` (pipeline_id)
- **Named flags:** `--handler=rss`, `--per_page=10`
- **Subcommands:** `flows get 42`
- **Output formats:** `--format=table` (default), `--format=json`
- **Pagination:** `--per_page=10 --offset=20`

---

## Chat Tools Updates

### Architecture: Tools CALL Abilities (Not Duplicate)

**CRITICAL:** Chat tools should call existing abilities via `wp_get_ability()`, NOT register separate `-tool` abilities. This ensures:
- Single source of truth for business logic
- Consistent permissions across CLI, REST, and Chat
- No code duplication

### CORRECT Pattern: Chat Tool Calls Ability

```php
// inc/Api/Chat/Tools/CreateFlow.php

namespace DataMachine\Api\Chat\Tools;

class CreateFlow {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'create_flow', [$this, 'getToolDefinition']);
    }

    public static function handle_tool_call(array $input): array {
        // Validate input specific to chat context
        $pipeline_id = $input['pipeline_id'] ?? null;
        $flow_name = $input['flow_name'] ?? 'Flow';

        if (!$pipeline_id) {
            return ['success' => false, 'error' => 'pipeline_id is required'];
        }

        // CORRECT: Call the ability directly
        $ability = wp_get_ability('datamachine/create-flow');
        $result = $ability->execute([
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'scheduling_config' => $input['scheduling_config'] ?? ['interval' => 'manual'],
        ]);

        // Format response for chat interface
        if ($result['success']) {
            return [
                'success' => true,
                'message' => "Created flow '{$result['data']['flow_name']}' (ID: {$result['data']['flow_id']})",
                'data' => $result['data'],
            ];
        }

        return $result;
    }
}
```

### WRONG Pattern (DO NOT USE)

```php
// ❌ WRONG: Registering duplicate ability for chat
wp_register_ability('datamachine/create-flow-tool', [...]); // ← DELETE THIS PATTERN

// ❌ WRONG: Duplicating business logic in tool
class CreateFlow {
    public static function execute(array $input): array {
        // Duplicated validation logic...
        // Duplicated database calls...
        // This creates maintenance burden and inconsistency
    }
}
```

### Chat Tools Migration Strategy

**Keep existing `inc/Api/Chat/Tools/` structure.** Tools remain thin wrappers that:
1. Define tool schema for AI agents
2. Validate chat-specific requirements
3. Call abilities via `wp_get_ability()`
4. Format responses for chat interface

### Tools to Update (Call Abilities)

| Chat Tool | Calls Ability | Status |
|-----------|---------------|--------|
| `CreateFlow.php` | `datamachine/create-flow` | ✅ DONE |
| `RunFlow.php` | `datamachine/run-flow` | Planned |
| `DeleteFlow.php` | `datamachine/delete-flow` | ✅ DONE |
| `UpdateFlow.php` | `datamachine/update-flow` | ✅ DONE |
| `CopyFlow.php` | `datamachine/duplicate-flow` | ✅ DONE |
| `CreatePipeline.php` | `datamachine/create-pipeline` | Planned |
| `DeletePipeline.php` | `datamachine/delete-pipeline` | Planned |
| `AddPipelineStep.php` | `datamachine/add-step` | Planned |
| `DeletePipelineStep.php` | `datamachine/delete-step` | Planned |
| `ReorderPipelineSteps.php` | `datamachine/reorder-steps` | Planned |
| `ConfigurePipelineStep.php` | `datamachine/update-step` | Planned |
| `ConfigureFlowSteps.php` | `datamachine/update-flow-step` | Planned |
| `SetHandlerDefaults.php` | `datamachine/set-handler-defaults` | Planned |
| `GetHandlerDefaults.php` | `datamachine/get-handler-defaults` | Planned |
| `ReadLogs.php` | `datamachine/read-logs` | Planned |
| `ManageLogs.php` | `datamachine/clear-logs` | Planned |

**Note:** Taxonomy tools (`AssignTaxonomyTerm`, `SearchTaxonomyTerms`, etc.) may call WordPress native functions directly since they operate on WP core data.

---

## Testing Strategy

### Test Structure

```
tests/Unit/Abilities/
├── FlowAbilitiesTest.php        ✅ EXISTS
├── LogAbilitiesTest.php         ✅ EXISTS
├── PostQueryAbilitiesTest.php   ✅ EXISTS
├── PipelineAbilitiesTest.php
│   ├── testCreatePipeline()
│   ├── testReadPipelines()
│   ├── testUpdatePipeline()
│   ├── testDeletePipeline()
│   ├── testDuplicatePipeline()
│   ├── testImportPipelines()
│   └── testExportPipelines()
├── FlowAbilitiesTest.php
│   ├── testCreateFlow()
│   ├── testReadFlows()
│   ├── testUpdateFlow()
│   ├── testDeleteFlow()
│   ├── testScheduleFlow()
│   └── testUnscheduleFlow()
├── PipelineStepAbilitiesTest.php
│   ├── testAddStep()
│   ├── testUpdateStep()
│   ├── testDeleteStep()
│   ├── testReorderSteps()
│   └── testConfigureSteps()
├── FlowStepAbilitiesTest.php
│   ├── testAddFlowStep()
│   ├── testUpdateFlowStep()
│   ├── testDeleteFlowStep()
│   └── testConfigureFlowSteps()
├── JobAbilitiesTest.php
│   ├── testRunFlow()
│   ├── testListJobs()
│   ├── testGetJob()
│   ├── testCancelJob()
│   ├── testRetryJob()
│   └── testDeleteJobs()
├── FileAbilitiesTest.php
│   ├── testListFiles()
│   ├── testGetFile()
│   ├── testUploadFile()
│   ├── testDeleteFile()
│   └── testDownloadFile()
├── SettingsAbilitiesTest.php
│   ├── testGetSettings()
│   ├── testUpdateSettings()
│   ├── testAuthenticateHandler()
│   ├── testDisconnectHandler()
│   ├── testGetAuthStatus()
│   └── testSetHandlerDefaults()
└── AuthAbilitiesTest.php
    ├── testAuthenticateHandler()
    ├── testDisconnectHandler()
    └── testCheckAuthStatus()
```

### Test Pattern

Tests verify ability registration, schema validation, permission checks, and execution logic.

```php
// tests/Unit/Abilities/FlowAbilitiesTest.php (✅ EXISTS)

class FlowAbilitiesTest extends \WP_UnitTestCase {

    private $original_user_id;

    public function setUp(): void {
        parent::setUp();
        $this->original_user_id = get_current_user_id();
        wp_set_current_user(1); // Administrator
    }

    public function tearDown(): void {
        wp_set_current_user($this->original_user_id);
        parent::tearDown();
    }

    public function testGetFlows_abilityIsRegistered(): void {
        $ability = wp_get_ability('datamachine/get-flows');
        $this->assertNotNull($ability);
        $this->assertEquals('datamachine', $ability->get_category());
    }

    public function testGetFlows_withValidFilters_returnsFlows(): void {
        $ability = new \DataMachine\Abilities\FlowAbilities();
        $result = $ability->executeAbility([
            'per_page' => 10,
            'offset' => 0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flows', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testGetFlows_withPipelineFilter_filtersCorrectly(): void {
        $ability = new \DataMachine\Abilities\FlowAbilities();
        $result = $ability->executeAbility([
            'pipeline_id' => 1,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['filters_applied']['pipeline_id']);
    }

    public function testGetFlows_withoutPermissions_returnsFalse(): void {
        wp_set_current_user(0);

        // Permission callback should prevent execution
        $ability = wp_get_ability('datamachine/get-flows');
        $this->assertFalse($ability->can_execute());
    }
}
```

### CLI Integration Tests

```php
// tests/Unit/Cli/FlowsCommandTest.php

class FlowsCommandTest extends \WP_UnitTestCase {

    public function testFlowsCommand_callsFlowAbilities(): void {
        // Verify CLI command delegates to ability
        $command = new \DataMachine\Cli\Commands\FlowsCommand();
        // Test via WP_CLI::runcommand() or direct invocation
    }
}
```

---

## Migration Phases

### Phase 1: Foundation Abilities ✅ (DONE)
**Status:** COMPLETE

**Abilities Registered:**
- `datamachine/get-flows` - Get/query flows with filtering and output modes (FlowAbilities.php)
- `datamachine/write-to-log` - Write log entries (LogAbilities.php)
- `datamachine/clear-logs` - Clear log files (LogAbilities.php)
- `datamachine/query-posts-by-handler` - Query posts by handler (PostQueryAbilities.php)
- `datamachine/query-posts-by-flow` - Query posts by flow (PostQueryAbilities.php)
- `datamachine/query-posts-by-pipeline` - Query posts by pipeline (PostQueryAbilities.php)

**CLI Commands:**
- `wp datamachine flows` - Uses FlowAbilities (supports --id, get subcommand, pipeline filter, handler filter)

**Tests:**
- `tests/Unit/Abilities/FlowAbilitiesTest.php`
- `tests/Unit/Abilities/LogAbilitiesTest.php`
- `tests/Unit/Abilities/PostQueryAbilitiesTest.php`

---

### Phase 2: Pipeline CRUD Abilities ✅ (DONE)
**Completed:**
1. ✅ Created `inc/Abilities/PipelineAbilities.php`
2. ✅ Registered 8 abilities (all self-contained)
3. ✅ Business logic in abilities (not delegating to PipelineManager)
4. ⏳ Create `inc/Cli/Commands/PipelinesCommand.php` (future)
5. ✅ REST API endpoints delegate to abilities
6. ✅ **`PipelineManager.php` DELETED** (~367 lines)
7. ✅ Tests exist in `tests/Unit/Abilities/PipelineAbilitiesTest.php`

**Outcome:** Abilities self-contained. Service eliminated.

---

### Phase 3: Flow CRUD Abilities ✅ (DONE)
**Completed:**
1. Extended `inc/Abilities/FlowAbilities.php` with 4 new CRUD abilities
2. Registered abilities: `create-flow`, `update-flow`, `delete-flow`, `duplicate-flow`
3. Business logic migrated from `FlowManager.php` into `FlowAbilities.php`
4. Updated `inc/Api/Flows/Flows.php` endpoints to delegate to abilities
5. Updated chat tools (CreateFlow, DeleteFlow, UpdateFlow, CopyFlow) to call abilities
6. Added 18 new tests to `tests/Unit/Abilities/FlowAbilitiesTest.php`
7. ✅ **`FlowManager.php` DELETED** (~681 lines)
8. ✅ All test files updated to use `datamachine/create-flow` ability

**Note:** Scheduling abilities (`schedule-flow`, `unschedule-flow`) deferred as scheduling is handled via `scheduling_config` parameter in create/update.

---

### Phase 4: Pipeline Steps Abilities ✅ (DONE)
**Completed:**
1. ✅ Created `inc/Abilities/PipelineStepAbilities.php`
2. ✅ Registered 6 abilities (all self-contained)
3. ✅ Business logic inlined from `PipelineStepManager` into abilities
4. ✅ Added private `syncStepsToFlow()` method for flow synchronization
5. ✅ REST API endpoints delegate to abilities
6. ✅ **`PipelineStepManager.php` DELETED** (~453 lines)
7. ✅ Tests exist in `tests/Unit/Abilities/PipelineStepAbilitiesTest.php`

**Outcome:** Abilities self-contained. Service eliminated.

---

### Phase 5: Flow Steps Abilities ⚠️ (ABILITIES REGISTERED)
**Completed:**
1. ✅ Created `inc/Abilities/FlowStepAbilities.php`
2. ✅ Registered 4 abilities
3. ⚠️ Abilities delegate to `FlowStepManager` (migration incomplete)
4. ✅ REST API endpoints delegate to abilities
5. ❌ Cannot delete `FlowStepManager.php` yet (abilities depend on it)
6. ✅ Tests exist in `tests/Unit/Abilities/FlowStepAbilitiesTest.php`

**Remaining:** Migrate FlowStepManager logic into abilities, then delete service.

---

### Phase 6: Job Execution Abilities ⚠️ (ABILITIES REGISTERED)
**Completed:**
1. ✅ Created `inc/Abilities/JobAbilities.php`
2. ✅ Registered 6 abilities (run-flow, get-jobs, get-job, delete-jobs, get-flow-health, get-problem-flows)
3. ⚠️ Abilities delegate to `JobManager` (migration incomplete)
4. ⏳ Create `inc/Cli/Commands/JobsCommand.php` (future)
5. ✅ REST API endpoints delegate to abilities
6. ❌ Cannot delete `JobManager.php` yet (abilities depend on it)
7. ✅ Tests exist in `tests/Unit/Abilities/JobAbilitiesTest.php`

**Remaining:** Migrate JobManager logic into abilities, then delete service.

---

### Phase 7: File Management Abilities ✅ (DONE)
**Completed:**
1. ✅ Created `inc/Abilities/FileAbilities.php`
2. ✅ Registered 5 abilities (list-files, get-file, upload-file, delete-file, cleanup-files)
3. ✅ Abilities are self-contained (direct FilesRepository access)
4. ✅ REST API endpoints delegate to abilities
5. ✅ Tests exist in `tests/Unit/Abilities/FileAbilitiesTest.php`

**Outcome:** No service layer for files - abilities access repository directly.

---

### Phase 8: Processed Items Abilities ⚠️ (ABILITIES REGISTERED)
**Completed:**
1. ✅ Created `inc/Abilities/ProcessedItemsAbilities.php`
2. ✅ Registered 3 abilities (clear-processed-items, check-processed-item, has-processed-history)
3. ⚠️ Abilities delegate to `ProcessedItemsManager` (migration incomplete)
4. ✅ REST API endpoints delegate to abilities
5. ❌ Cannot delete `ProcessedItemsManager.php` yet (abilities depend on it)
6. ✅ Tests exist in `tests/Unit/Abilities/ProcessedItemsAbilitiesTest.php`

**Remaining:** Migrate ProcessedItemsManager logic into abilities, then delete service.

---

### Phase 9: Settings & Auth Abilities ⚠️ (PARTIAL - v0.11.7)
**Completed:**
1. ✅ `inc/Abilities/SettingsAbilities.php` - 6 abilities (self-contained)
2. ✅ `inc/Abilities/HandlerAbilities.php` - 6 abilities (self-contained, replaces HandlerService)
3. ✅ `inc/Abilities/StepTypeAbilities.php` - 3 abilities (self-contained, replaces StepTypeService)
4. ✅ **`inc/Services/HandlerService.php` DELETED** (v0.11.7)
5. ✅ **`inc/Services/StepTypeService.php` DELETED** (v0.11.7)
6. ⚠️ `inc/Abilities/AuthAbilities.php` - 3 abilities (delegates to AuthProviderService)
7. ⚠️ `inc/Services/AuthProviderService.php` retained (complex OAuth1/OAuth2 flows)

**Remaining Decision:** Migrate AuthProviderService into AuthAbilities or retain as internal service.

---

### Phase 10: Chat Tools Update ✅ (DONE)
**Status:** COMPLETE - All 29 chat tools call abilities (not services directly)

**Implementation Details:**
- All chat tools updated to call abilities via `wp_get_ability()`
- Tools are thin wrappers that validate input and format responses
- Tools don't need changes when ability internals are migrated
- Business logic centralized in abilities, not duplicated in tools

**Tools calling abilities:**
- CreateFlow, DeleteFlow, UpdateFlow, CopyFlow → FlowAbilities
- CreatePipeline, DeletePipeline → PipelineAbilities
- AddPipelineStep, DeletePipelineStep, ReorderPipelineSteps, ConfigurePipelineStep → PipelineStepAbilities
- ConfigureFlowSteps → FlowStepAbilities
- RunFlow, GetFlowHealth, GetProblemFlows → JobAbilities
- SetHandlerDefaults, GetHandlerDefaults → SettingsAbilities/HandlerAbilities
- ReadLogs, ManageLogs → LogAbilities
- ApiQuery → Multiple abilities (read-only discovery)

---

### Phase 11: Extension Migration Notification (1 week)
**Tasks:**
1. Document breaking changes for datamachine-events and datamachine-recipes
2. Identify which services they currently call
3. Provide migration guide for updating to abilities
4. Update README in extensions

---

### Phase 12: Testing & Validation (2 weeks)
**Tasks:**
1. Run all ability unit tests
2. Run integration tests for REST endpoints
3. Manual QA of all flows and pipelines
4. Load testing
5. Security audit (all permission checks)

---

### Phase 13: Documentation (1 week)
**Tasks:**
1. Update CLAUDE.md with new architecture
2. Update CHANGELOG.md
3. Document abilities API usage
4. Create migration guide
5. Update README.md

---

## Timeline Summary

| Phase | Abilities Status | Service Status | Notes |
|-------|------------------|----------------|-------|
| 1. Foundation ✅ | 6 registered | N/A | CLI command exists |
| 2. Pipeline CRUD ✅ | 8 registered | **PipelineManager DELETED** | Self-contained abilities |
| 3. Flow CRUD ✅ | 5 registered | **FlowManager DELETED** | Self-contained abilities |
| 4. Pipeline Steps ✅ | 6 registered | **PipelineStepManager DELETED** | Self-contained abilities |
| 5. Flow Steps ⚠️ | 4 registered | FlowStepManager **in use** | Migration incomplete |
| 6. Job Execution ⚠️ | 6 registered | JobManager **in use** | Migration incomplete |
| 7. File Mgmt ✅ | 5 registered | No service needed | Self-contained abilities |
| 8. Processed Items ⚠️ | 3 registered | ProcessedItemsManager **in use** | Migration incomplete |
| 9. Settings & Auth ⚠️ | 15 registered | AuthProviderService **in use** | Partial - Handler/StepType done |
| 10. Chat Tools ✅ | N/A | Tools call abilities | All 29 tools migrated |
| Handler/StepType ✅ | 9 registered | **Both DELETED** | Full migration complete |
| **TOTALS** | **49 abilities** | **5 of 11 deleted** | |

### Migration Progress Summary

| Metric | Count | Percentage |
|--------|-------|------------|
| Total abilities registered | 49 | 100% |
| Self-contained ability classes | 9 of 13 | 69% |
| Services eliminated | 5 of 11 | 45% |
| Services requiring migration | 4 | 36% |
| Utilities retained | 2 | 18% |
| Chat tools calling abilities | 29 of 29 | 100% |

### True Migration Remaining

To achieve the target architecture (eliminate service layer), the following work remains:

**Services Already Deleted:**
- ✅ `HandlerService.php` - Replaced by HandlerAbilities
- ✅ `StepTypeService.php` - Replaced by StepTypeAbilities
- ✅ `PipelineManager.php` - Replaced by PipelineAbilities
- ✅ `PipelineStepManager.php` - Replaced by PipelineStepAbilities
- ✅ `FlowManager.php` - Replaced by FlowAbilities

**Migrate Business Logic (4 remaining):**
- `FlowStepManager.php` → Migrate logic into `FlowStepAbilities.php`
- `JobManager.php` → Migrate logic into `JobAbilities.php`
- `ProcessedItemsManager.php` → Migrate logic into `ProcessedItemsAbilities.php`
- `AuthProviderService.php` → Migrate OAuth1/OAuth2 logic into `AuthAbilities.php`

**Utilities Retained (Not Migrated):**
- `CacheManager.php` - Cross-cutting concern, not business logic
- `LogsManager.php` - File operations, LogAbilities delegates appropriately

**Current Progress:**
- Deleted: 5 files from `inc/Services/` (HandlerService, StepTypeService, PipelineManager, PipelineStepManager, FlowManager)
- Remaining: 4 services to migrate (FlowStepManager, JobManager, ProcessedItemsManager, AuthProviderService)
- Utilities retained: 2 (CacheManager, LogsManager)

**Net Impact (When Complete):**
- Delete: 9 files from `inc/Services/` (retain 2 utilities)
- Abilities: 49 registered (all with proper callbacks)
- Architecture: CLI, REST, and Chat all call abilities as universal primitive

---

## Risk Assessment & Mitigation

### Risks

| Risk | Severity | Impact | Mitigation |
|------|----------|--------|------------|
| Extension compatibility | High | Events/Recipes break immediately | Document breaking changes, provide migration guide, force upgrade requirement |
| Permission edge cases | Medium | Complex authorization scenarios | Comprehensive testing, audit all permission checks |
| Performance regression | Medium | Ability execution overhead | Benchmark before/after, cache ability instances |
| Data migration loss | High | Incomplete database state | Backup databases, test migrations in staging |
| Ability registration timing | Low | Wrong initialization order | Test with WordPress 6.9 RCs |

### Rollback Plan

If critical issues discovered:

1. Git revert to pre-migration commit
2. Restore `inc/Services/` directory from git
3. Restore `inc/Api/Chat/Tools/` directory from git
4. Revert REST API files from git
5. Keep rollback branch for 30 days

---

## Extension Migration Guide

### Breaking Changes Document

**For datamachine-events and datamachine-recipes maintainers:**

**Before (Deprecated):**
```php
use DataMachine\Services\PipelineManager;
use DataMachine\Services\FlowManager;

$manager = new PipelineManager();
$result = $manager->create($pipeline_id, $name, $options);
```

**After (Required):**
```php
// Option 1: Use abilities directly
$ability = wp_get_ability('datamachine/create-pipeline');
$result = $ability->execute([
    'pipeline_id' => $pipeline_id,
    'pipeline_name' => $name,
    ...$options
]);

// Option 2: Use REST API (backward compatible)
$response = wp_remote_post(rest_url('datamachine/v1/pipelines'), [
    'body' => json_encode([
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $name,
        ...$options
    ])
]);
$result = json_decode(wp_remote_retrieve_body($response['http_response']), true);
```

**Services to Update:**
- `PipelineManager` → Use `datamachine/create-pipeline` ability
- `FlowManager` → Use `datamachine/create-flow` ability
- `PipelineStepManager` → Use `datamachine/add-step` ability
- `JobManager` → Use `datamachine/run-flow` ability
- `HandlerService` → Use `datamachine/get-handler-defaults` ability
- `StepTypeService` → No replacement (step types registered via filter)
- `CacheManager` → No replacement (cache invalidation via filter)
- `LogsManager` → Use `datamachine/clear-logs` ability

---

## Questions Before Implementation

1. **WordPress 6.9 Minimum Version**: Do we require WordPress 6.9.0 minimum, or can abilities be conditionally used?

2. **Ability Registry**: Should we create a centralized `AbilityRegistry` class or use direct `wp_register_ability()` calls?

3. **Test Coverage**: Should we aim for 100% test coverage of abilities, or focus on critical paths first?

4. **Extension Timeline**: When do we deprecate service layer for extensions? Immediately upon merge, or give X months notice?

5. **Documentation Format**: Should abilities be documented in CLAUDE.md, separate Abilities.md, or inline code comments?

---

## Benefits When Complete

### 1. Unified Permission Model
- All 74 permission checks consolidated under `permission_callback`
- Single source of truth for "who can do what"
- AI agents inherit WordPress permission model

### 2. Automatic Discoverability
```bash
wp ability list | grep datamachine
# Shows 50+ datamachine abilities
```

### 3. Built-in REST API
With `'meta' => ['show_in_rest' => true]`:
- 50+ REST endpoints created automatically
- Swagger/OpenAPI documentation auto-generated
- No custom endpoint registration code needed

### 4. CLI Commands Wrap Abilities
```bash
# Existing CLI commands (wrap abilities)
wp datamachine flows                    # Uses datamachine/get-flows ability
wp datamachine flows 5                  # Filter by pipeline_id
wp datamachine flows --handler=rss      # Filter by handler
wp datamachine flows get 42             # Get specific flow

# Future CLI commands
wp datamachine pipelines                # Uses datamachine/get-pipelines ability
wp datamachine jobs                     # Uses datamachine/get-jobs ability
wp datamachine run 42                   # Uses datamachine/run-flow ability

# Direct ability execution (WordPress 6.9 built-in)
wp ability execute datamachine/run-flow --flow_id=123
wp ability execute datamachine/clear-logs --agent_type=all
```

### 5. Composability
```php
// Higher-level workflows compose lower-level abilities
wp_register_ability('datamachine/create-and-run-flow', [
    'execute_callback' => function($input) {
        $create = wp_get_ability('datamachine/create-flow')->execute($input);
        $run = wp_get_ability('datamachine/run-flow')->execute([
            'flow_id' => $create['data']['flow_id']
        ]);
        return $run;
    }
]);
```

### 6. Universal Consumer Pattern

All three consumer layers (CLI, REST, Chat) call the same abilities:

```php
// CLI Command
$ability = new \DataMachine\Abilities\FlowAbilities();
$result = $ability->executeAbility(['flow_id' => 42]);

// REST Endpoint
$ability = wp_get_ability('datamachine/get-flows');
$result = $ability->execute(['pipeline_id' => 5]);

// Chat Tool
$ability = wp_get_ability('datamachine/create-flow');
$result = $ability->execute(['pipeline_id' => 5, 'flow_name' => 'New Flow']);

// AI Agent (via WP-AI)
AI_Client::prompt()
    ->usingAbility('datamachine/create-pipeline')
    ->usingAbility('datamachine/run-flow')
    ->withMessages($conversation)
    ->generateTextResult();
```

### 7. Cleaner Codebase
- 36 fewer files (delete entire service layer)
- ~6409 lines of code migrated to abilities
- Consistent permission model across all operations
- Unified error handling and validation

### 8. Better Extensibility
- Extensions can register their own abilities
- Extensions can call Data Machine abilities via `wp_get_ability()`
- Compose higher-level workflows using Data Machine as primitive layer
