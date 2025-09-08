# Core Actions Reference

Comprehensive reference for all WordPress actions used by Data Machine for pipeline execution, data processing, and system operations.

## Pipeline Execution Actions

### `dm_run_flow_now`

**Purpose**: Entry point for all pipeline execution

**Parameters**:
- `$flow_id` (int) - Flow ID to execute
- `$context` (string, optional) - Execution context ('manual', 'scheduled', etc.)

**Usage**:
```php
do_action('dm_run_flow_now', $flow_id, 'manual');
```

**Process**:
1. Retrieves flow data from database
2. Creates job record for tracking
3. Identifies first step (execution_order = 0)
4. Schedules initial step execution

### `dm_execute_step`

**Purpose**: Core step execution orchestration

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Flow step identifier
- `$data` (array|null) - Data packet or storage reference

**Usage**:
```php
do_action('dm_execute_step', $job_id, $flow_step_id, $data);
```

**Internal Process**:
1. Retrieves data from storage if reference provided
2. Loads flow step configuration
3. Instantiates and executes step class
4. Schedules next step or completes pipeline

### `dm_schedule_next_step`

**Purpose**: Action Scheduler integration for step transitions

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Next step to execute
- `$data` (array) - Data packet to pass

**Usage**:
```php
do_action('dm_schedule_next_step', $job_id, $next_flow_step_id, $data);
```

**Process**:
1. Stores data packet in files repository
2. Creates Action Scheduler task
3. Schedules immediate execution

## Data Processing Actions

### `dm_mark_item_processed`

**Purpose**: Mark items as processed for deduplication

**Parameters**:
- `$flow_step_id` (string) - Flow step identifier
- `$source_type` (string) - Handler source type
- `$item_id` (mixed) - Item identifier
- `$job_id` (string) - Job identifier

**Usage**:
```php
do_action('dm_mark_item_processed', $flow_step_id, 'wordpress_local', $post_id, $job_id);
```

**Database Operation**:
- Inserts record into `wp_dm_processed_items` table
- Creates unique constraint on flow_step_id + source_type + item_identifier

## Job Management Actions

### `dm_update_job_status`

**Purpose**: Update job execution status

**Parameters**:
- `$job_id` (string) - Job identifier
- `$status` (string) - New status ('pending', 'running', 'completed', 'failed', 'completed_no_items')
- `$message` (string, optional) - Status message

**Usage**:
```php
do_action('dm_update_job_status', $job_id, 'completed', 'Pipeline executed successfully');
```

### `dm_fail_job`

**Purpose**: Mark job as failed with detailed error information

**Parameters**:
- `$job_id` (string) - Job identifier
- `$reason` (string) - Failure reason category
- `$context_data` (array) - Additional failure context

**Usage**:
```php
do_action('dm_fail_job', $job_id, 'step_execution_failure', [
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

### `dm_update_system_prompt`

**Purpose**: Update pipeline-level system prompts

**Parameters**:
- `$pipeline_step_id` (string) - Pipeline step UUID
- `$system_prompt` (string) - AI system prompt text

**Usage**:
```php
do_action('dm_update_system_prompt', $pipeline_step_id, $system_prompt);
```

**Storage**: Stored in pipeline_config as reusable template

### `dm_update_flow_user_message`

**Purpose**: Update flow-level user messages

**Parameters**:
- `$flow_step_id` (string) - Flow step composite ID
- `$user_message` (string) - AI user message text

**Usage**:
```php
do_action('dm_update_flow_user_message', $flow_step_id, $user_message);
```

**Storage**: Stored in flow_config for instance-specific customization

### `dm_save_tool_config`

**Purpose**: Save tool configuration data

**Parameters**:
- `$tool_id` (string) - Tool identifier
- `$config_data` (array) - Configuration data to save

**Usage**:
```php
do_action('dm_save_tool_config', 'google_search', [
    'api_key' => $api_key,
    'search_engine_id' => $search_engine_id
]);
```

## System Maintenance Actions

### `dm_auto_save`

**Purpose**: Automatic pipeline configuration saving

**Parameters**:
- `$pipeline_id` (int) - Pipeline to save

**Usage**:
```php
do_action('dm_auto_save', $pipeline_id);
```

### `dm_cleanup_old_files`

**Purpose**: File repository maintenance

**Usage**:
```php
do_action('dm_cleanup_old_files');
```

**Process**:
- Removes data packets from completed jobs
- Cleans up orphaned files
- Runs via Action Scheduler

## Import/Export Actions

### `dm_import`

**Purpose**: Import pipeline or flow data

**Parameters**:
- `$type` (string) - Import type ('pipelines', 'flows')
- `$data` (array) - Import data

**Usage**:
```php
do_action('dm_import', 'pipelines', $csv_data);
```

### `dm_export`

**Purpose**: Export pipeline or flow data

**Parameters**:
- `$type` (string) - Export type ('pipelines', 'flows')
- `$ids` (array) - IDs to export

**Usage**:
```php
do_action('dm_export', 'pipelines', [$pipeline_id]);
```

## Logging Action

### `dm_log`

**Purpose**: Centralized logging for all system operations

**Parameters**:
- `$level` (string) - Log level ('debug', 'info', 'warning', 'error')
- `$message` (string) - Log message
- `$context` (array) - Additional context data

**Usage**:
```php
do_action('dm_log', 'debug', 'AI Step Directive: Injected system directive', [
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

## Action Scheduler Integration

### Action Types

**Primary Actions**:
- `dm_execute_step` - Step execution
- `dm_cleanup_old_files` - Maintenance
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
    'dm_execute_step',
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
    $result = $step->execute($job_id, $flow_step_id, $data, $config);
    
    if (!empty($result)) {
        // Success - schedule next step
        do_action('dm_schedule_next_step', $job_id, $next_step_id, $result);
    } else {
        // Failure - fail job
        do_action('dm_fail_job', $job_id, 'empty_result', $context);
    }
} catch (\Throwable $e) {
    // Exception - log and fail
    do_action('dm_log', 'error', 'Step execution exception', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    do_action('dm_fail_job', $job_id, 'exception', $context);
}
```

### Logging Integration

All actions integrate with centralized logging:

```php
do_action('dm_log', 'info', 'Flow execution started successfully', [
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

### Nonce Validation

AJAX actions require nonce validation:

```php
if (!wp_verify_nonce($_POST['nonce'], 'dm_ajax_actions')) {
    wp_die(__('Security check failed'));
}
```