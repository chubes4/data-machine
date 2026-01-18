# Data Machine Abilities API Migration Plan

## Executive Summary

**Clean Break Migration to WordPress 6.9 Abilities API**

Eliminate entire service layer (`inc/Services/`) and migrate all business logic to WordPress 6.9 Abilities API. REST endpoints delegate to abilities. Chat tools call abilities directly. No backward compatibility.

**Current Architecture:**
```
REST API (41 endpoints) → Service Layer (10 files, 2809 lines) → Database Layer
```

**Target Architecture:**
```
REST API (41 endpoints) → Abilities Layer (50+ abilities) → Database Layer
```

---

## Current Codebase Analysis

### Service Layer (To Eliminate)

| File | Lines | Key Methods | Dependencies |
|------|-------|--------------|--------------|
| `PipelineManager.php` | 367 | `create()`, `duplicate()`, `import()`, `export()`, `delete()` | FlowManager, PipelineStepManager, PipelinesDB |
| `FlowManager.php` | 393 | `create()`, `delete()`, `update()`, `duplicate()`, `syncStepsToFlow()` | FlowsDB, PipelinesDB, FlowStepManager |
| `PipelineStepManager.php` | 393 | `add()`, `delete()`, `reorder()`, `updateHandler()`, `updateUserMessage()` | PipelinesDB, FlowManager, StepTypeService |
| `FlowStepManager.php` | 393 | `add()`, `delete()`, `update()`, `configure()` | FlowsDB, FlowManager, HandlerService |
| `JobManager.php` | 270 | `create()`, `get()`, `getForFlow()`, `getForPipeline()`, `updateStatus()`, `fail()`, `delete()` | JobsDB, ProcessedItemsManager |
| `ProcessedItemsManager.php` | 120 | `getForJob()`, `deleteForJob()`, `clearForCriteria()` | ProcessedItemsDB |
| `StepTypeService.php` | 105 | `get()`, `getAll()` | `datamachine_step_types` filter |
| `AuthProviderService.php` | ~150 | `getAll()`, `get()`, `exists()`, `validate()`, `clearCache()` | `datamachine_auth_providers` filter |
| `HandlerService.php` | ~120 | `getAll()`, `get()`, `exists()`, `validate()`, `clearCache()` | `datamachine_handlers` filter |
| `CacheManager.php` | ~75 | `invalidate()`, `clear()` | `datamachine_cache_invalidated` filter |
| `LogsManager.php` | ~100 | `getLogPath()`, `clear()`, `getSize()`, `cleanup()` | DATAMACHINE_LOG_DIR |

**Total Service Layer:** 10 files, 2809 lines

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
├── Engine/
│   ├── Abilities/                 ← NEW DIRECTORY
│   │   ├── AbilityRegistry.php    (Central registration)
│   │   ├── PipelineAbilities.php (Pipeline CRUD)
│   │   ├── FlowAbilities.php     (Flow CRUD)
│   │   ├── PipelineStepAbilities.php (Pipeline steps)
│   │   ├── FlowStepAbilities.php  (Flow steps)
│   │   ├── JobAbilities.php      (Job lifecycle)
│   │   ├── ProcessedItemsAbilities.php
│   │   ├── FileAbilities.php
│   │   ├── AuthAbilities.php
│   │   ├── SettingsAbilities.php
│   │   ├── StepTypeAbilities.php
│   │   └── Tools/
│   │       ├── CreateFlow.php
│   │       ├── RunFlow.php
│   │       ├── DeleteFlow.php
│   │       ├── UpdateFlow.php
│   │       ├── CopyFlow.php
│   │       ├── CreatePipeline.php
│   │       ├── DeletePipeline.php
│   │       └── ... (all 30+ tools)
│   ├── AI/
│   │   └── Tools/            ← UPDATE
│   │       ├── ToolExecutor.php (Uses abilities)
│   │       └── ToolManager.php
│   ├── Logger.php                 ← UPDATE
│   ├── Actions/
│   │   └── DataMachineActions.php  ← KEEP (action hook wrapper)
│   ├── ConversationManager.php
│   ├── RequestBuilder.php
│   └── AIConversationLoop.php
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
└── Api/                         ← UPDATE (delegate to abilities)
    ├── Pipelines/
    │   └── Pipelines.php
    ├── Flows/
    │   ├── Flows.php
    │   ├── FlowSteps.php
    │   └── FlowScheduling.php
    ├── Chat/
    │   ├── Tools/           ← UPDATE (use wp_get_ability())
    │   │   ├── CreateFlow.php
    │   │   ├── RunFlow.php
    │   │   ├── DeleteFlow.php
    │   │   ├── ...
    │   └── Chat.php
    ├── Jobs.php
    ├── ProcessedItems.php
    ├── Files.php
    ├── Settings.php
    ├── Auth.php
    ├── Tools.php
    ├── Handlers.php
    ├── StepTypes.php
    ├── Providers.php
    ├── Users.php
    └── Logs.php
```

---

## Abilities Registry

### Phase 1: Logging Primitives ✅ (DONE)
- `datamachine/write-to-log` - Write log entries
- `datamachine/clear-logs` - Clear log files

### Phase 2: Pipeline CRUD Operations
**Abilities to Register:**
```php
// inc/Engine/Abilities/PipelineAbilities.php

add_action('wp_abilities_api_init', function() {
    // Create pipeline
    wp_register_ability('datamachine/create-pipeline', [
        'label' => 'Create Pipeline',
        'description' => 'Create a new data processing pipeline',
        'category' => 'pipeline',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'pipeline_name' => [
                    'type' => 'string',
                    'description' => 'Pipeline name'
                ],
                'steps' => [
                    'type' => 'array',
                    'description' => 'Pipeline steps (for complete mode)'
                ],
                'flow_config' => [
                    'type' => 'array',
                    'description' => 'Flow configuration'
                ],
                'batch_import' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Enable batch import mode'
                ]
            ]
        ],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::create',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Read pipelines
    wp_register_ability('datamachine/read-pipelines', [
        'label' => 'Read Pipelines',
        'description' => 'Retrieve all pipelines or specific pipeline by ID',
        'category' => 'pipeline',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'pipeline_id' => ['type' => 'integer'],
                'fields' => ['type' => 'string', 'default' => 'json'],
                'format' => ['type' => 'string', 'enum' => ['json', 'csv']],
                'ids' => ['type' => 'string']
            ]
        ],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::read',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Update pipeline
    wp_register_ability('datamachine/update-pipeline', [
        'label' => 'Update Pipeline',
        'description' => 'Update pipeline configuration',
        'category' => 'pipeline',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::update',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Delete pipeline
    wp_register_ability('datamachine/delete-pipeline', [
        'label' => 'Delete Pipeline',
        'description' => 'Delete pipeline and associated data',
        'category' => 'pipeline',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::delete',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Duplicate pipeline
    wp_register_ability('datamachine/duplicate-pipeline', [
        'label' => 'Duplicate Pipeline',
        'description' => 'Duplicate a pipeline with all its flows',
        'category' => 'pipeline',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::duplicate',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Import pipelines
    wp_register_ability('datamachine/import-pipelines', [
        'label' => 'Import Pipelines',
        'description' => 'Import pipelines from JSON or CSV',
        'category' => 'pipeline',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::import',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
    
    // Export pipelines
    wp_register_ability('datamachine/export-pipelines', [
        'label' => 'Export Pipelines',
        'description' => 'Export pipelines to JSON or CSV',
        'category' => 'pipeline',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Engine\\Abilities\\PipelineAbilities::export',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
});
```

### Phase 3: Flow CRUD Operations
**Abilities to Register:**
```php
wp_register_ability('datamachine/create-flow', [
    'label' => 'Create Flow',
    'description' => 'Create a new flow for a pipeline',
    'category' => 'flow',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pipeline_id' => ['type' => 'integer', 'required' => true],
            'flow_name' => ['type' => 'string', 'default' => 'Flow'],
            'flow_config' => ['type' => 'array'],
            'scheduling_config' => ['type' => 'array']
        ]
    ],
    'execute_callback' => 'DataMachine\\Engine\\Abilities\\FlowAbilities::create',
    'permission_callback' => fn() => current_user_can('manage_options'),
    'meta' => ['show_in_rest' => true]
]);

wp_register_ability('datamachine/read-flows', [...]);
wp_register_ability('datamachine/update-flow', [...]);
wp_register_ability('datamachine/delete-flow', [...]);
wp_register_ability('datamachine/duplicate-flow', [...]);
wp_register_ability('datamachine/schedule-flow', [...]);
wp_register_ability('datamachine/unschedule-flow', [...]);
```

### Phase 4: Pipeline Steps Operations
**Abilities to Register:**
```php
wp_register_ability('datamachine/add-step', [...]);
wp_register_ability('datamachine/read-steps', [...]);
wp_register_ability('datamachine/update-step', [...]);
wp_register_ability('datamachine/delete-step', [...]);
wp_register_ability('datamachine/reorder-steps', [...]);
wp_register_ability('datamachine/configure-steps', [...]);
```

### Phase 5: Flow Steps Operations
**Abilities to Register:**
```php
wp_register_ability('datamachine/add-flow-step', [...]);
wp_register_ability('datamachine/read-flow-steps', [...]);
wp_register_ability('datamachine/update-flow-step', [...]);
wp_register_ability('datamachine/delete-flow-step', [...]);
wp_register_ability('datamachine/configure-flow-steps', [...]);
```

### Phase 6: Execution Operations
**Abilities to Register:**
```php
wp_register_ability('datamachine/run-flow', [
    'label' => 'Run Flow',
    'description' => 'Execute flow immediately or schedule for later',
    'category' => 'execution',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'flow_id' => ['type' => 'integer', 'required' => true],
            'count' => ['type' => 'integer', 'default' => 1],
            'timestamp' => ['type' => 'integer']
        ]
    ],
    'execute_callback' => 'DataMachine\\Engine\\Abilities\\JobAbilities::runFlow',
    'permission_callback' => fn() => current_user_can('manage_options'),
    'meta' => ['show_in_rest' => true]
]);

wp_register_ability('datamachine/execute-job', [...]);
wp_register_ability('datamachine/cancel-job', [...]);
wp_register_ability('datamachine/retry-job', [...]);
wp_register_ability('datamachine/read-jobs', [...]);
wp_register_ability('datamachine/read-job', [...]);
wp_register_ability('datamachine/delete-jobs', [...]);
```

### Phase 7: File Management
**Abilities to Register:**
```php
wp_register_ability('datamachine/read-files', [...]);
wp_register_ability('datamachine/upload-file', [...]);
wp_register_ability('datamachine/delete-file', [...]);
wp_register_ability('datamachine/download-file', [...]);
wp_register_ability('datamachine/list-files', [...]);
```

### Phase 8: Settings & Auth
**Abilities to Register:**
```php
wp_register_ability('datamachine/read-settings', [...]);
wp_register_ability('datamachine/update-settings', [...]);
wp_register_ability('datamachine/authenticate-handler', [...]);
wp_register_ability('datamachine/disconnect-handler', [...]);
wp_register_ability('datamachine/check-auth-status', [...]);
wp_register_ability('datamachine/set-handler-defaults', [...]);
wp_register_ability('datamachine/get-handler-defaults', [...]);
```

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
    
    public static function handle_read_pipelines($request) {
        return self::executeAbility('datamachine/read-pipelines', [
            'pipeline_id' => $request->get_param('pipeline_id'),
            'fields' => $request->get_param('fields'),
            'format' => $request->get_param('format'),
            'ids' => $request->get_param('ids')
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

## Chat Tools Updates

### Pattern: Direct Ability Execution

```php
// inc/Engine/Abilities/Tools/CreateFlow.php

namespace DataMachine\Engine\Abilities\Tools;

use DataMachine\Api\Chat\Tools; // ← Import for backward compat reference

class CreateFlow {
    
    public static function register() {
        // Register tool via abilities API instead of ToolRegistrationTrait
        wp_register_ability('datamachine/create-flow-tool', [
            'label' => 'Create Flow (Chat Tool)',
            'description' => 'Create a new flow from existing pipeline via chat interface',
            'category' => 'chat',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'pipeline_id' => ['type' => 'integer', 'required' => true],
                    'flow_name' => ['type' => 'string', 'default' => 'Flow'],
                    'scheduling_config' => ['type' => 'array'],
                    'step_configs' => ['type' => 'array']
                ]
            ],
            'execute_callback' => [self::class, 'execute'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }
    
    public static function execute(array $input): array {
        // Direct logic migration from CreateFlow.php handle_tool_call()
        $pipeline_id = $input['pipeline_id'];
        $flow_name = $input['flow_name'] ?? 'Flow';
        $scheduling_config = $input['scheduling_config'] ?? ['interval' => 'manual'];
        
        // Get flows for existing flow name validation
        $flows_db = new \DataMachine\Core\Database\Flows\Flows();
        $existing_flows = $flows_db->get_flows_for_pipeline($pipeline_id);
        
        foreach ($existing_flows as $existing_flow) {
            if (strcasecmp($existing_flow['flow_name'], $flow_name) === 0) {
                return [
                    'success' => false,
                    'error' => "Flow '{$existing_flow['flow_name']}' already exists"
                ];
            }
        }
        
        // Call ability to create flow
        $createFlowAbility = wp_get_ability('datamachine/create-flow');
        $result = $createFlowAbility->execute([
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'scheduling_config' => $scheduling_config
        ]);
        
        // Apply step configs
        $flow_id = $result['data']['flow_id'];
        $step_configs = $input['step_configs'] ?? [];
        $config_results = ['applied' => [], 'errors' => []];
        
        foreach ($step_configs as $pipeline_step_id => $config) {
            // Use abilities for step configuration
            $updateFlowStepAbility = wp_get_ability('datamachine/update-flow-step');
            $updateResult = $updateFlowStepAbility->execute([
                'flow_step_id' => $pipeline_step_id . '_' . $flow_id,
                ...$config
            ]);
            
            if (!$updateResult['success']) {
                $config_results['errors'][] = [
                    'pipeline_step_id' => $pipeline_step_id,
                    'error' => $updateResult['message']
                ];
            } else {
                $config_results['applied'][] = $pipeline_step_id;
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'flow_id' => $flow_id,
                'flow_name' => $result['data']['flow_name'],
                'applied_steps' => $config_results['applied'],
                'errors' => $config_results['errors']
            ]
        ];
    }
}
```

### All Chat Tools to Migrate

**Files to Create in `inc/Engine/Abilities/Tools/`:**
1. `CreateFlow.php` (Create from pipeline)
2. `RunFlow.php` (Execute existing flow)
3. `DeleteFlow.php`
4. `UpdateFlow.php`
5. `CopyFlow.php`
6. `CreatePipeline.php`
7. `DeletePipeline.php`
8. `AddPipelineStep.php`
9. `DeletePipelineStep.php`
10. `ReorderPipelineSteps.php`
11. `ConfigurePipelineStep.php`
12. `ConfigureFlowSteps.php`
13. `SetHandlerDefaults.php`
14. `GetHandlerDefaults.php`
15. `AssignTaxonomyTerm.php`
16. `UpdateTaxonomyTerm.php`
17. `MergeTaxonomyTerms.php`
18. `SearchTaxonomyTerms.php`
19. `ReadLogs.php` → Use `datamachine/read-logs` ability
20. `ManageLogs.php` → Use `datamachine/clear-logs` ability
21-30+ (All other tools)

---

## Testing Strategy

### Test Structure

```
tests/Unit/Engine/Abilities/
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
│   ├── testExecuteJob()
│   ├── testCancelJob()
│   ├── testRetryJob()
│   ├── testReadJobs()
│   ├── testReadJob()
│   └── testDeleteJobs()
├── FileAbilitiesTest.php
│   ├── testReadFiles()
│   ├── testUploadFile()
│   ├── testDeleteFile()
│   ├── testDownloadFile()
│   └── testListFiles()
├── SettingsAbilitiesTest.php
│   ├── testReadSettings()
│   ├── testUpdateSettings()
│   ├── testAuthenticateHandler()
│   ├── testDisconnectHandler()
│   ├── testCheckAuthStatus()
│   └── testSetHandlerDefaults()
└── AuthAbilitiesTest.php
    ├── testAuthenticateHandler()
    ├── testDisconnectHandler()
    └── testCheckAuthStatus()
```

### Test Pattern

```php
class PipelineAbilitiesTest extends \WP_UnitTestCase {
    
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
    
    public function testCreatePipeline_withValidData_returnsSuccess(): void {
        $ability = wp_get_ability('datamachine/create-pipeline');
        $result = $ability->execute([
            'pipeline_name' => 'Test Pipeline',
            'steps' => []
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('pipeline_id', $result['data']);
    }
    
    public function testCreatePipeline_withoutPermissions_returnsPermissionDenied(): void {
        wp_set_current_user(0); // Subscriber
        
        $ability = wp_get_ability('datamachine/create-pipeline');
        $result = $ability->execute([
            'pipeline_name' => 'Test Pipeline',
            'steps' => []
        ]);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error_code', $result);
        $this->assertEquals('permission_denied', $result['error_code']);
    }
    
    public function testCreatePipeline_withInvalidData_returnsValidationError(): void {
        $ability = wp_get_ability('datamachine/create-pipeline');
        $result = $ability->execute([
            'pipeline_name' => '', // Invalid: empty
            'steps' => []
        ]);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error_code', $result);
        $this->assertEquals('invalid_input', $result['error_code']);
    }
    
    // ... more tests
}
```

---

## Migration Phases

### Phase 1: Logging Primitives ✅ (DONE)
**Status:** COMPLETE
- `datamachine/write-to-log` ability registered
- `datamachine/clear-logs` ability registered
- Action hook wraps abilities
- 0 breaking changes

---

### Phase 2: Pipeline CRUD Abilities (2 weeks)
**Tasks:**
1. Create `inc/Engine/Abilities/PipelineAbilities.php`
2. Register 7 abilities: `create-pipeline`, `read-pipelines`, `update-pipeline`, `delete-pipeline`, `duplicate-pipeline`, `import-pipelines`, `export-pipelines`
3. Migrate business logic from `PipelineManager.php` (367 lines)
4. Update `inc/Api/Pipelines/Pipelines.php` endpoints to delegate
5. Delete `inc/Services/PipelineManager.php`
6. Write `tests/Unit/Engine/Abilities/PipelineAbilitiesTest.php`

**Outcome:** Service layer eliminated. REST endpoints delegate to abilities.

---

### Phase 3: Flow CRUD Abilities (2 weeks)
**Tasks:**
1. Create `inc/Engine/Abilities/FlowAbilities.php`
2. Register 7 abilities: `create-flow`, `read-flows`, `update-flow`, `delete-flow`, `duplicate-flow`, `schedule-flow`, `unschedule-flow`
3. Migrate business logic from `FlowManager.php` (393 lines)
4. Update `inc/Api/Flows/Flows.php` endpoints
5. Update `inc/Api/Flows/FlowScheduling.php` endpoints
6. Delete `inc/Services/FlowManager.php`
7. Write `tests/Unit/Engine/Abilities/FlowAbilitiesTest.php`

---

### Phase 4: Pipeline Steps Abilities (1 week)
**Tasks:**
1. Create `inc/Engine/Abilities/PipelineStepAbilities.php`
2. Register 6 abilities
3. Migrate business logic from `PipelineStepManager.php` (393 lines)
4. Update `inc/Api/Pipelines/PipelineSteps.php` endpoints
5. Delete `inc/Services/PipelineStepManager.php`
6. Write tests

---

### Phase 5: Flow Steps Abilities (1 week)
**Tasks:**
1. Create `inc/Engine/Abilities/FlowStepAbilities.php`
2. Register 5 abilities
3. Migrate business logic from `FlowStepManager.php` (393 lines)
4. Update `inc/Api/Flows/FlowSteps.php` endpoints
5. Delete `inc/Services/FlowStepManager.php`
6. Write tests

---

### Phase 6: Job Execution Abilities (2 weeks)
**Tasks:**
1. Create `inc/Engine/Abilities/JobAbilities.php`
2. Register 8 abilities: `run-flow`, `execute-job`, `cancel-job`, `retry-job`, `read-jobs`, `read-job`, `delete-jobs`, `get-job-stats`
3. Migrate business logic from `JobManager.php` (270 lines)
4. Update `inc/Api/Execute.php` endpoints
5. Update `inc/Api/Jobs.php` endpoints
6. Delete `inc/Services/JobManager.php`
7. Write tests

---

### Phase 7: File Management Abilities (1 week)
**Tasks:**
1. Create `inc/Engine/Abilities/FileAbilities.php`
2. Register 5 abilities
3. Update `inc/Api/Files.php` endpoints
4. Write tests

---

### Phase 8: Processed Items Abilities (3 days)
**Tasks:**
1. Create `inc/Engine/Abilities/ProcessedItemsAbilities.php`
2. Register 3 abilities: `read-processed-items`, `clear-processed-items`, `get-processed-stats`
3. Update `inc/Api/ProcessedItems.php` endpoints
4. Delete `inc/Services/ProcessedItemsManager.php`
5. Write tests

---

### Phase 9: Settings & Auth Abilities (1 week)
**Tasks:**
1. Create `inc/Engine/Abilities/SettingsAbilities.php`
2. Create `inc/Engine/Abilities/AuthAbilities.php`
3. Register 9 abilities
4. Update `inc/Api/Settings.php` and `inc/Api/Auth.php` endpoints
5. Delete `inc/Services/CacheManager.php`, `inc/Services/LogsManager.php`
6. Delete `inc/Services/StepTypeService.php`, `inc/Services/HandlerService.php`, `inc/Services/AuthProviderService.php`
7. Write tests

---

### Phase 10: Chat Tools Migration (2 weeks)
**Tasks:**
1. Create `inc/Engine/Abilities/Tools/` directory
2. Register 30+ abilities for each tool
3. Migrate all tool logic to abilities
4. Update all tools to use `wp_get_ability()` directly
5. Delete `inc/Api/Chat/Tools/` directory (30+ files)
6. Write tests for chat tool abilities

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

| Phase | Duration | Lines Changed | Files Added | Files Deleted |
|--------|----------|---------------|--------------|---------------|
| 1. Logging ✅ | DONE | ~50 | 2 | 0 |
| 2. Pipeline CRUD | 2 weeks | ~367 + ~100 | 1 + 1 | 1 |
| 3. Flow CRUD | 2 weeks | ~393 + ~150 | 1 + 1 | 1 |
| 4. Pipeline Steps | 1 week | ~393 + ~100 | 1 + 1 | 1 |
| 5. Flow Steps | 1 week | ~393 + ~100 | 1 + 1 | 1 |
| 6. Job Execution | 2 weeks | ~270 + ~200 | 1 + 1 | 1 |
| 7. File Mgmt | 1 week | ~200 | 1 | 0 |
| 8. Processed Items | 3 days | ~120 + ~150 | 1 | 0 |
| 9. Settings & Auth | 1 week | ~600 + ~300 | 2 | 3 |
| 10. Chat Tools | 2 weeks | ~2000 | 30 | 30 |
| 11. Extension Notify | 1 week | ~0 | 3 | 0 |
| 12. Testing | 2 weeks | ~0 | ~40 | 0 |
| 13. Documentation | 1 week | ~0 | 5 | 0 |
| **TOTAL** | **15-16 weeks** | **~6409** | **~92** | **~36** |

**Net Impact:**
- Delete: 36 files (entire `inc/Services/` + 30 chat tools)
- Create: 92 files (abilities + tests + docs)
- Modify: ~41 REST API files
- Lines of code migrated: ~6409 lines

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

### 4. CLI Zero-Code Integration
```bash
# Direct ability execution from CLI
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

### 6. AI Agent Integration
```php
// AI agents can request Data Machine abilities directly
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
