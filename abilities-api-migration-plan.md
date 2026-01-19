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
| `list-*` | Query/list multiple items | `datamachine/list-flows`, `datamachine/list-pipelines` |
| `get-*` | Get single item by ID | `datamachine/get-flow`, `datamachine/get-pipeline` |
| `create-*` | Create new item | `datamachine/create-flow`, `datamachine/create-pipeline` |
| `update-*` | Update existing item | `datamachine/update-flow`, `datamachine/update-pipeline` |
| `delete-*` | Delete item | `datamachine/delete-flow`, `datamachine/delete-pipeline` |
| `query-*` | Complex queries with filters | `datamachine/query-posts-by-handler` |
| Special actions | Domain-specific operations | `duplicate-*`, `schedule-*`, `run-*`, `clear-*` |

### Phase 1: Foundation Abilities ✅ (DONE)

**Completed Abilities:**
| Ability | File | Description |
|---------|------|-------------|
| `datamachine/list-flows` | `FlowAbilities.php` | List/query flows with filtering by pipeline, handler, flow_id |
| `datamachine/write-to-log` | `LogAbilities.php` | Write log entries with level routing |
| `datamachine/clear-logs` | `LogAbilities.php` | Clear log files by agent type |
| `datamachine/query-posts-by-handler` | `PostQueryAbilities.php` | Find posts created by a specific handler |
| `datamachine/query-posts-by-flow` | `PostQueryAbilities.php` | Find posts created by a specific flow |
| `datamachine/query-posts-by-pipeline` | `PostQueryAbilities.php` | Find posts created by a specific pipeline |

**Completed CLI Commands:**
| Command | Ability | Features |
|---------|---------|----------|
| `wp datamachine flows` | `datamachine/list-flows` | `--id`, `get` subcommand, pipeline filter, `--handler` filter, pagination |

### Phase 2: Pipeline CRUD Operations
**Abilities to Register:**
```php
// inc/Abilities/PipelineAbilities.php

add_action('wp_abilities_api_init', function() {
    // List pipelines (query multiple)
    wp_register_ability('datamachine/list-pipelines', [
        'label' => 'List Pipelines',
        'description' => 'List all pipelines with optional filtering',
        'category' => 'datamachine',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'per_page' => ['type' => 'integer', 'default' => 20],
                'offset' => ['type' => 'integer', 'default' => 0],
                'fields' => ['type' => 'string', 'default' => 'json'],
                'format' => ['type' => 'string', 'enum' => ['json', 'csv']],
            ]
        ],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::list',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Get single pipeline by ID
    wp_register_ability('datamachine/get-pipeline', [
        'label' => 'Get Pipeline',
        'description' => 'Retrieve a specific pipeline by ID',
        'category' => 'datamachine',
        'input_schema' => [
            'type' => 'object',
            'required' => ['pipeline_id'],
            'properties' => [
                'pipeline_id' => ['type' => 'integer'],
            ]
        ],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::get',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Create pipeline
    wp_register_ability('datamachine/create-pipeline', [
        'label' => 'Create Pipeline',
        'description' => 'Create a new data processing pipeline',
        'category' => 'datamachine',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'pipeline_name' => ['type' => 'string', 'description' => 'Pipeline name'],
                'steps' => ['type' => 'array', 'description' => 'Pipeline steps (for complete mode)'],
                'flow_config' => ['type' => 'array', 'description' => 'Flow configuration'],
                'batch_import' => ['type' => 'boolean', 'default' => false]
            ]
        ],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::create',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Update pipeline
    wp_register_ability('datamachine/update-pipeline', [
        'label' => 'Update Pipeline',
        'description' => 'Update pipeline configuration',
        'category' => 'datamachine',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::update',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Delete pipeline
    wp_register_ability('datamachine/delete-pipeline', [
        'label' => 'Delete Pipeline',
        'description' => 'Delete pipeline and associated data',
        'category' => 'datamachine',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::delete',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Duplicate pipeline
    wp_register_ability('datamachine/duplicate-pipeline', [
        'label' => 'Duplicate Pipeline',
        'description' => 'Duplicate a pipeline with all its flows',
        'category' => 'datamachine',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::duplicate',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Import pipelines
    wp_register_ability('datamachine/import-pipelines', [
        'label' => 'Import Pipelines',
        'description' => 'Import pipelines from JSON or CSV',
        'category' => 'datamachine',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::import',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);

    // Export pipelines
    wp_register_ability('datamachine/export-pipelines', [
        'label' => 'Export Pipelines',
        'description' => 'Export pipelines to JSON or CSV',
        'category' => 'datamachine',
        'input_schema' => [...],
        'execute_callback' => 'DataMachine\\Abilities\\PipelineAbilities::export',
        'permission_callback' => fn() => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true]
    ]);
});
```

### Phase 3: Flow CRUD Operations
**Abilities to Register:**
```php
// inc/Abilities/FlowAbilities.php

// List flows (query multiple) - ✅ EXISTS
wp_register_ability('datamachine/list-flows', [
    'label' => 'List Flows',
    'description' => 'List flows with optional filtering by pipeline ID or handler slug',
    'category' => 'datamachine',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'pipeline_id' => ['type' => 'integer'],
            'handler_slug' => ['type' => 'string'],
            'per_page' => ['type' => 'integer', 'default' => 20],
            'offset' => ['type' => 'integer', 'default' => 0],
        ]
    ],
    'execute_callback' => 'DataMachine\\Abilities\\FlowAbilities::executeAbility',
    'permission_callback' => fn() => current_user_can('manage_options'),
    'meta' => ['show_in_rest' => true]
]);

// Get single flow by ID (can use list-flows with flow_id param, or create dedicated ability)
wp_register_ability('datamachine/get-flow', [
    'label' => 'Get Flow',
    'description' => 'Retrieve a specific flow by ID',
    'category' => 'datamachine',
    'input_schema' => [
        'type' => 'object',
        'required' => ['flow_id'],
        'properties' => [
            'flow_id' => ['type' => 'integer'],
        ]
    ],
    'execute_callback' => 'DataMachine\\Abilities\\FlowAbilities::get',
    'permission_callback' => fn() => current_user_can('manage_options'),
    'meta' => ['show_in_rest' => true]
]);

// Create flow
wp_register_ability('datamachine/create-flow', [
    'label' => 'Create Flow',
    'description' => 'Create a new flow for a pipeline',
    'category' => 'datamachine',
    'input_schema' => [
        'type' => 'object',
        'required' => ['pipeline_id'],
        'properties' => [
            'pipeline_id' => ['type' => 'integer'],
            'flow_name' => ['type' => 'string', 'default' => 'Flow'],
            'flow_config' => ['type' => 'array'],
            'scheduling_config' => ['type' => 'array']
        ]
    ],
    'execute_callback' => 'DataMachine\\Abilities\\FlowAbilities::create',
    'permission_callback' => fn() => current_user_can('manage_options'),
    'meta' => ['show_in_rest' => true]
]);

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
| `wp datamachine flows` | `datamachine/list-flows` | ✅ Done |
| `wp datamachine flows get <id>` | `datamachine/list-flows` (with flow_id) | ✅ Done |
| `wp datamachine pipelines` | `datamachine/list-pipelines` | Planned |
| `wp datamachine pipelines get <id>` | `datamachine/get-pipeline` | Planned |
| `wp datamachine jobs` | `datamachine/list-jobs` | Planned |
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

| Chat Tool | Calls Ability |
|-----------|---------------|
| `CreateFlow.php` | `datamachine/create-flow` |
| `RunFlow.php` | `datamachine/run-flow` |
| `DeleteFlow.php` | `datamachine/delete-flow` |
| `UpdateFlow.php` | `datamachine/update-flow` |
| `CopyFlow.php` | `datamachine/duplicate-flow` |
| `CreatePipeline.php` | `datamachine/create-pipeline` |
| `DeletePipeline.php` | `datamachine/delete-pipeline` |
| `AddPipelineStep.php` | `datamachine/add-step` |
| `DeletePipelineStep.php` | `datamachine/delete-step` |
| `ReorderPipelineSteps.php` | `datamachine/reorder-steps` |
| `ConfigurePipelineStep.php` | `datamachine/update-step` |
| `ConfigureFlowSteps.php` | `datamachine/update-flow-step` |
| `SetHandlerDefaults.php` | `datamachine/set-handler-defaults` |
| `GetHandlerDefaults.php` | `datamachine/get-handler-defaults` |
| `ReadLogs.php` | `datamachine/read-logs` |
| `ManageLogs.php` | `datamachine/clear-logs` |

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

    public function testListFlows_abilityIsRegistered(): void {
        $ability = wp_get_ability('datamachine/list-flows');
        $this->assertNotNull($ability);
        $this->assertEquals('datamachine', $ability->get_category());
    }

    public function testListFlows_withValidFilters_returnsFlows(): void {
        $ability = new \DataMachine\Abilities\FlowAbilities();
        $result = $ability->executeAbility([
            'per_page' => 10,
            'offset' => 0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flows', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testListFlows_withPipelineFilter_filtersCorrectly(): void {
        $ability = new \DataMachine\Abilities\FlowAbilities();
        $result = $ability->executeAbility([
            'pipeline_id' => 1,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['filters_applied']['pipeline_id']);
    }

    public function testListFlows_withoutPermissions_returnsFalse(): void {
        wp_set_current_user(0);

        // Permission callback should prevent execution
        $ability = wp_get_ability('datamachine/list-flows');
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
- `datamachine/list-flows` - List/query flows with filtering (FlowAbilities.php)
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

### Phase 2: Pipeline CRUD Abilities
**Tasks:**
1. Create `inc/Abilities/PipelineAbilities.php`
2. Register 8 abilities: `list-pipelines`, `get-pipeline`, `create-pipeline`, `update-pipeline`, `delete-pipeline`, `duplicate-pipeline`, `import-pipelines`, `export-pipelines`
3. Migrate business logic from `PipelineManager.php` (367 lines)
4. Create `inc/Cli/Commands/PipelinesCommand.php`
5. Update `inc/Api/Pipelines/Pipelines.php` endpoints to delegate
6. Delete `inc/Services/PipelineManager.php`
7. Write `tests/Unit/Abilities/PipelineAbilitiesTest.php`

**Outcome:** Service layer eliminated. REST, CLI, and Chat all call abilities.

---

### Phase 3: Flow CRUD Abilities
**Tasks:**
1. Extend `inc/Abilities/FlowAbilities.php` (already has `list-flows`)
2. Register 6 more abilities: `get-flow`, `create-flow`, `update-flow`, `delete-flow`, `duplicate-flow`, `schedule-flow`, `unschedule-flow`
3. Migrate business logic from `FlowManager.php` (393 lines)
4. Update `inc/Api/Flows/Flows.php` endpoints
5. Update `inc/Api/Flows/FlowScheduling.php` endpoints
6. Delete `inc/Services/FlowManager.php`
7. Update `tests/Unit/Abilities/FlowAbilitiesTest.php`

---

### Phase 4: Pipeline Steps Abilities
**Tasks:**
1. Create `inc/Abilities/PipelineStepAbilities.php`
2. Register 6 abilities: `list-steps`, `get-step`, `add-step`, `update-step`, `delete-step`, `reorder-steps`
3. Migrate business logic from `PipelineStepManager.php` (393 lines)
4. Update `inc/Api/Pipelines/PipelineSteps.php` endpoints
5. Delete `inc/Services/PipelineStepManager.php`
6. Write `tests/Unit/Abilities/PipelineStepAbilitiesTest.php`

---

### Phase 5: Flow Steps Abilities
**Tasks:**
1. Create `inc/Abilities/FlowStepAbilities.php`
2. Register 5 abilities: `list-flow-steps`, `get-flow-step`, `update-flow-step`, `configure-flow-steps`
3. Migrate business logic from `FlowStepManager.php` (393 lines)
4. Update `inc/Api/Flows/FlowSteps.php` endpoints
5. Delete `inc/Services/FlowStepManager.php`
6. Write `tests/Unit/Abilities/FlowStepAbilitiesTest.php`

---

### Phase 6: Job Execution Abilities
**Tasks:**
1. Create `inc/Abilities/JobAbilities.php`
2. Register 8 abilities: `run-flow`, `list-jobs`, `get-job`, `cancel-job`, `retry-job`, `delete-jobs`, `get-job-stats`
3. Migrate business logic from `JobManager.php` (270 lines)
4. Create `inc/Cli/Commands/JobsCommand.php`
5. Update `inc/Api/Execute.php` endpoints
6. Update `inc/Api/Jobs.php` endpoints
7. Delete `inc/Services/JobManager.php`
8. Write `tests/Unit/Abilities/JobAbilitiesTest.php`

---

### Phase 7: File Management Abilities
**Tasks:**
1. Create `inc/Abilities/FileAbilities.php`
2. Register 5 abilities: `list-files`, `get-file`, `upload-file`, `delete-file`, `download-file`
3. Update `inc/Api/Files.php` endpoints
4. Write `tests/Unit/Abilities/FileAbilitiesTest.php`

---

### Phase 8: Processed Items Abilities
**Tasks:**
1. Create `inc/Abilities/ProcessedItemsAbilities.php`
2. Register 3 abilities: `list-processed-items`, `clear-processed-items`, `get-processed-stats`
3. Update `inc/Api/ProcessedItems.php` endpoints
4. Delete `inc/Services/ProcessedItemsManager.php`
5. Write `tests/Unit/Abilities/ProcessedItemsAbilitiesTest.php`

---

### Phase 9: Settings & Auth Abilities
**Tasks:**
1. Create `inc/Abilities/SettingsAbilities.php`
2. Create `inc/Abilities/AuthAbilities.php`
3. Register 9 abilities
4. Update `inc/Api/Settings.php` and `inc/Api/Auth.php` endpoints
5. Delete `inc/Services/CacheManager.php`, `inc/Services/LogsManager.php`
6. Delete `inc/Services/StepTypeService.php`, `inc/Services/HandlerService.php`, `inc/Services/AuthProviderService.php`
7. Write tests

---

### Phase 10: Chat Tools Update
**Tasks:**
1. Update existing `inc/Api/Chat/Tools/` to call abilities via `wp_get_ability()`
2. Remove duplicated business logic from tools
3. Tools remain as thin wrappers for AI agent interface
4. **DO NOT create separate `-tool` abilities**
5. **DO NOT delete `inc/Api/Chat/Tools/` directory** - tools still needed for AI agent schema
6. Write integration tests for chat tools calling abilities

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

| Phase | Status | Abilities | CLI Commands | Files Modified |
|-------|--------|-----------|--------------|----------------|
| 1. Foundation ✅ | DONE | 6 | 1 | 3 ability files + 1 CLI + 3 tests |
| 2. Pipeline CRUD | Planned | 8 | 1 | 1 ability + 1 CLI + 1 test |
| 3. Flow CRUD | Planned | 7 | (extend existing) | 1 ability (extend) + 1 test |
| 4. Pipeline Steps | Planned | 6 | 0 | 1 ability + 1 test |
| 5. Flow Steps | Planned | 5 | 0 | 1 ability + 1 test |
| 6. Job Execution | Planned | 7 | 1 | 1 ability + 1 CLI + 1 test |
| 7. File Mgmt | Planned | 5 | 0 | 1 ability + 1 test |
| 8. Processed Items | Planned | 3 | 0 | 1 ability + 1 test |
| 9. Settings & Auth | Planned | 9 | 0 | 2 abilities + 2 tests |
| 10. Chat Tools Update | Planned | 0 | 0 | ~20 tool files updated |
| 11. Extension Notify | Planned | 0 | 0 | Documentation |
| 12. Testing | Planned | 0 | 0 | Integration tests |
| 13. Documentation | Planned | 0 | 0 | CLAUDE.md, docs/ |
| **TOTAL** | | **~56** | **~4** | |

**Net Impact:**
- Delete: ~10 files (entire `inc/Services/`)
- Create: ~12 ability files + ~4 CLI commands + ~12 test files
- Modify: ~41 REST API files + ~20 Chat tools
- Abilities registered: ~56 abilities
- CLI commands: 4+ commands wrapping abilities

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
wp datamachine flows                    # Uses datamachine/list-flows ability
wp datamachine flows 5                  # Filter by pipeline_id
wp datamachine flows --handler=rss      # Filter by handler
wp datamachine flows get 42             # Get specific flow

# Future CLI commands
wp datamachine pipelines                # Uses datamachine/list-pipelines ability
wp datamachine jobs                     # Uses datamachine/list-jobs ability
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
$ability = wp_get_ability('datamachine/list-flows');
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
