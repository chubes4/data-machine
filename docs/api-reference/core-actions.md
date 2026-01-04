# Core Actions Reference

Comprehensive reference for all WordPress actions used by Data Machine for pipeline execution, data processing, and system operations.

**Note**: Since v0.4.0, most core operations use the Services Layer architecture (`\DataMachine\Services\`) for direct method calls, replacing filter-based actions for 3x performance improvement. These actions remain primarily for extensibility and backward compatibility.

## Service Manager Integration

Direct service method calls are preferred over actions for system operations:
- `PipelineManager` -> `create()`, `delete()`, `duplicate()`
- `FlowManager` -> `create()`, `delete()`, `duplicate()`
- `JobManager` -> `updateStatus()`, `failJob()`
- `LogsManager` -> `log()`, `get_logs()`, `clear_logs()`

## Pipeline Execution Actions

### `datamachine_run_flow_now`

**Purpose**: Entry point for all pipeline execution

**Parameters**:
- `$flow_id` (int) - Flow ID to execute
- `$context` (string, optional) - Execution context ('manual', 'scheduled', etc.)

**Usage**:
```php
do_action('datamachine_run_flow_now', $flow_id, 'manual');
```

**Process**:
1. Retrieves flow data from database
2. Creates job record for tracking
3. Identifies first step (execution_order = 0)
4. Schedules initial step execution

### `datamachine_execute_step`

**Purpose**: Core step execution orchestration

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Flow step identifier
- `$dataPackets` (array|null) - Data packets or storage reference

**Usage**:
```php
do_action('datamachine_execute_step', $job_id, $flow_step_id, $dataPackets);
```

**Internal Process**:
1. Retrieves data from storage if reference provided
2. Loads flow step configuration
3. Instantiates and executes step class
4. Schedules next step or completes pipeline

### `datamachine_schedule_next_step`

**Purpose**: Action Scheduler integration for step transitions

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Next step to execute
- `$dataPackets` (array) - Data packets to pass

**Usage**:
```php
do_action('datamachine_schedule_next_step', $job_id, $next_flow_step_id, $dataPackets);
```

**Process**:
1. Stores data packet in files repository
2. Creates Action Scheduler task
3. Schedules immediate execution

## Data Processing Actions

### `datamachine_mark_item_processed`

**Purpose**: Mark items as processed for deduplication

**Parameters**:
- `$flow_step_id` (string) - Flow step identifier
- `$source_type` (string) - Handler source type
- `$item_id` (mixed) - Item identifier
- `$job_id` (string) - Job identifier

**Usage**:
```php
do_action('datamachine_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
```

**Database Operation**:
- Inserts record into `wp_datamachine_processed_items` table
- Creates unique constraint on flow_step_id + source_type + item_identifier

## Job Management Actions

### `datamachine_update_job_status`

**Purpose**: Update job execution status

**Services Integration**: Primarily handled by JobManager::updateStatus() since v0.4.0

**Parameters**:
- `$job_id` (string) - Job identifier
- `$status` (string) - New status ('pending', 'running', 'completed', 'failed', 'completed_no_items')
- `$message` (string, optional) - Status message

**Usage**:
```php
// Services Layer (recommended since v0.4.0)
$job_manager = new \DataMachine\Services\JobManager();
$job_manager->updateStatus($job_id, 'completed', 'Pipeline executed successfully');

// Action Hook (for extensibility)
do_action('datamachine_update_job_status', $job_id, 'completed', 'Pipeline executed successfully');
```

### `datamachine_fail_job`

**Purpose**: Mark job as failed with detailed error information

**Services Integration**: Primarily handled by JobManager::failJob() since v0.4.0

**Parameters**:
- `$job_id` (string) - Job identifier
- `$reason` (string) - Failure reason category
- `$context_data` (array) - Additional failure context

**Usage**:
```php
// Services Layer (recommended since v0.4.0)
$job_manager = new \DataMachine\Services\JobManager();
$job_manager->failJob($job_id, 'step_execution_failure', [
    'flow_step_id' => $flow_step_id,
    'exception_message' => $e->getMessage(),
    'reason' => 'detailed_error_reason'
]);

// Action Hook (for extensibility)
do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
    'flow_step_id' => $flow_step_id,
    'exception_message' => $e->getMessage(),
    'reason' => 'detailed_error_reason'
]);
```

**Process**:
1. Updates job status to 'failed'
2. Logs detailed error information
3. Optionally cleans up job data files
4. Stops pipeline execution

## Configuration Actions

### `datamachine_update_system_prompt`

**Purpose**: Update pipeline-level system prompts

**Parameters**:
- `$pipeline_step_id` (string) - Pipeline step UUID
- `$system_prompt` (string) - AI system prompt text

**Usage**:
```php
do_action('datamachine_update_system_prompt', $pipeline_step_id, $system_prompt);
```

**Storage**: Stored in pipeline_config as reusable template

### `datamachine_update_flow_user_message`

**Purpose**: Update flow-level user messages

**Parameters**:
- `$flow_step_id` (string) - Flow step composite ID
- `$user_message` (string) - AI user message text

**Usage**:
```php
do_action('datamachine_update_flow_user_message', $flow_step_id, $user_message);
```

**Storage**: Stored in flow_config for instance-specific customization

### `datamachine_save_tool_config`

**Purpose**: Save tool configuration data

**Parameters**:
- `$tool_id` (string) - Tool identifier
- `$config_data` (array) - Configuration data to save

**Usage**:
```php
do_action('datamachine_save_tool_config', 'google_search', [
    'api_key' => $api_key,
    'search_engine_id' => $search_engine_id
]);
```

## System Maintenance Actions

### `datamachine_cleanup_old_files`

**Purpose**: File repository maintenance

**Usage**:
```php
do_action('datamachine_cleanup_old_files');
```

**Process**:
- Removes data packets from completed jobs
- Cleans up orphaned files
- Runs via Action Scheduler

## Import/Export Actions

### `datamachine_import`

**Purpose**: Import pipeline or flow data

**Parameters**:
- `$type` (string) - Import type ('pipelines', 'flows')
- `$data` (array) - Import data

**Usage**:
```php
do_action('datamachine_import', 'pipelines', $csv_data);
```

### `datamachine_export`

**Purpose**: Export pipeline or flow data

**Parameters**:
- `$type` (string) - Export type ('pipelines', 'flows')
- `$ids` (array) - IDs to export

**Usage**:
```php
do_action('datamachine_export', 'pipelines', [$pipeline_id]);
```

## Logging Action

### `datamachine_log`

**Purpose**: Centralized logging for all system operations

**Parameters**:
- `$level` (string) - Log level ('debug', 'info', 'warning', 'error')
- `$message` (string) - Log message
- `$context` (array) - Additional context data

**Usage**:
```php
do_action('datamachine_log', 'debug', 'AI Step Directive: Injected system directive', [
    'tool_count' => count($tools),
    'available_tools' => array_keys($tools),
    'directive_length' => strlen($directive)
]);
```

**Log Levels**:
- **debug** - Development and troubleshooting information
- **info** - General operational information  
- **warning** - Non-critical issues that should be noted
- **error** - Critical errors that affect functionality

## REST Endpoints

### `GET /datamachine/v1/status`

**Purpose**: Consolidated status refresh for flows and pipelines

**Handler Class**: `DataMachine\Api\Status`

**Query Parameters**:
- `flow_id` (int|string|array) - One or more flow IDs (supports `flow_id[]=1&flow_id[]=2` or comma-separated string)
- `pipeline_id` (int|string|array) - One or more pipeline IDs (supports `pipeline_id[]=3&pipeline_id[]=4` or comma-separated string)

**Response**:
```json
{
    "success": true,
    "requested": {
        "flows": [123],
        "pipelines": [456]
    },
    "flows": {
        "123": {
            "step_statuses": {
                "flow_step_id_1": "green",
                "flow_step_id_2": "yellow"
            }
        }
    },
    "pipelines": {
        "456": {
            "step_statuses": {
                "pipeline_step_id_1": "green",
                "pipeline_step_id_2": "red"
            }
        }
    }
}
```

**Use Cases**:
- Flow handler configuration changes
- Pipeline template modifications
- Batch polling for multiple flows/pipelines in UI dashboards

**Status Contexts**:
- `flow_step_status` for flow-scoped validation (handler configuration, scheduling, settings)
- `pipeline_step_status` for pipeline-wide validation (template architecture, AI cascade effects)

**Security**:
- Requires `manage_options` capability
- Requires REST nonce (`X-WP-Nonce`) when called from authenticated admin JavaScript

## Action Scheduler Integration

### Action Types

**Primary Actions**:
- `datamachine_execute_step` - Step execution
- `datamachine_cleanup_old_files` - Maintenance
- Custom actions for scheduled flows

**Queue Management**:
- Group: `data-machine`
- Immediate scheduling: `time()` timestamp
- WordPress cron integration

### Usage Pattern

```php
// Schedule action
$action_id = as_schedule_single_action(
    time(), // Immediate execution
    'datamachine_execute_step',
    [
        'job_id' => $job_id,
        'flow_step_id' => $flow_step_id,
        'data' => $data_reference
    ],
    'data-machine'
);
```

## Error Handling Actions

### Exception Handling Pattern

```php
try {
    // Step execution
    $payload = [
        'job_id' => $job_id,
        'flow_step_id' => $flow_step_id,
        'flow_step_config' => $config,
        'data' => $data,
        'engine_data' => apply_filters('datamachine_engine_data', [], $job_id)
    ];

    $result = $step->execute($payload);
    
    if (!empty($result)) {
        // Success - schedule next step
        do_action('datamachine_schedule_next_step', $job_id, $next_step_id, $result);
    } else {
        // Failure - fail job
        do_action('datamachine_fail_job', $job_id, 'empty_result', $context);
    }
} catch (\Throwable $e) {
    // Exception - log and fail
    do_action('datamachine_log', 'error', 'Step execution exception', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    do_action('datamachine_fail_job', $job_id, 'exception', $context);
}
```

### Logging Integration

All actions integrate with centralized logging:

```php
do_action('datamachine_log', 'info', 'Flow execution started successfully', [
    'flow_id' => $flow_id,
    'job_id' => $job_id,
    'first_step' => $first_flow_step_id
]);
```

## Security Considerations

### Capability Requirements

All actions require `manage_options` capability:

```php
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
```

### Data Sanitization

Input data is sanitized before processing:

```php
$pipeline_name = sanitize_text_field($data['pipeline_name'] ?? '');
$config_json = wp_json_encode($config_data);
```

