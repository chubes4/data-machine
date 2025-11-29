# Engine Execution System

The Data Machine engine uses a services layer architecture (@since v0.4.0) that orchestrates all pipeline workflows through WordPress Action Scheduler with direct method calls for optimal performance.

**Services Layer Integration**: The execution system now uses dedicated service managers instead of filter-based actions, providing 3x performance improvement through direct method calls while maintaining full backward compatibility with existing WordPress action hooks.

## Execution Cycle

### 1. Flow Initiation

**Purpose**: Entry point for all pipeline execution

**Services Integration**: FlowManager and JobManager handle flow initiation

**Process**:
1. FlowManager retrieves flow data from database
2. JobManager creates job record for tracking
3. Identifies first step (execution_order = 0)
4. Schedules initial step execution

**Usage**:
```php
do_action('datamachine_run_flow_now', $flow_id, 'manual');
```

### 2. Step Execution

**Purpose**: Core functional pipeline orchestration - processes individual steps

**Services Integration**: JobManager and FlowStepManager handle step execution

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Flow step identifier
- `$data` (array|null) - Data packet or storage reference

**Process**:
1. Retrieves data from storage if reference provided
2. FlowStepManager loads complete flow step configuration
3. Discovers step class via `datamachine_step_types` filter
4. Creates step instance and executes with parameters
5. Uses `StepNavigator` (@since v0.2.1) to determine next step or completes pipeline

**Step Execution Pattern**:
```php
$payload = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data,
    'engine_data' => apply_filters('datamachine_engine_data', [], $job_id)
];

$data = $flow_step->execute($payload);
```

### 3. Step Scheduling

**Purpose**: Action Scheduler integration for step transitions

**Services Integration**: JobManager handles step scheduling

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Next step to execute
- `$data` (array) - Data packet to pass

**Process**:
1. Stores data packet in files repository
2. Creates lightweight reference for Action Scheduler
3. Schedules immediate execution of next step

## Step Navigation

The engine uses **StepNavigator** (@since v0.2.1) to determine step transitions during execution:

```php
use DataMachine\Engine\StepNavigator;

$step_navigator = new StepNavigator();

// Determine next step after current step completes
$next_flow_step_id = $step_navigator->get_next_flow_step_id($flow_step_id, [
    'engine_data' => $engine_data
]);

if ($next_flow_step_id) {
    // Schedule next step execution
    do_action('datamachine_schedule_next_step', $job_id, $next_flow_step_id, $data);
} else {
    // Pipeline complete
    do_action('datamachine_update_job_status', $job_id, 'completed');
}
```

**Benefits**:
- Centralized step navigation logic
- Support for complex step ordering
- Rollback capability via `get_previous_flow_step_id()`
- Performance optimized via engine_data context

**See**: StepNavigator Documentation for complete details

## Data Storage

### Files Repository

**Purpose**: Flow-isolated data packet storage with UUID-based organization

**Key Methods**:
- `store_data_packet($data, $job_id, $flow_step_id)` - Store with reference
- `retrieve_data_packet($reference)` - Retrieve by reference
- `cleanup_job_data_packets($job_id)` - Clean completed jobs
- `is_data_reference($data)` - Detect storage references

**Directory Structure**:
```
wp-content/uploads/datamachine/files/
└── {flow_id}/
    └── {job_id}/
        └── {uuid}.json
```

## Step Discovery

### Step Registration

Steps register via `datamachine_step_types` filter:

```php
add_filter('datamachine_step_types', function($steps) {
    $steps['my_step'] = [
        'name' => __('My Step'),
        'class' => 'MyStep',
        'position' => 50
    ];
    return $steps;
});
```

### Step Implementation

All steps implement the same payload contract:

```php
class MyStep {
    public function execute(array $payload): array {
        $job_id = $payload['job_id'];
        $flow_step_id = $payload['flow_step_id'];
        $data = $payload['data'] ?? [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        $engine_data = $payload['engine_data'] ?? [];

        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        array_unshift($data, [
            'type' => 'my_step',
            'content' => ['title' => $title, 'body' => $content],
            'metadata' => ['source_type' => 'my_source'],
            'timestamp' => time()
        ]);

        return $data;
    }
}
```

## Parameter Passing

### Unified Step Payload

Engine now delivers a documented payload array to every step:

```php
$payload = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data,
    'engine_data' => apply_filters('datamachine_engine_data', [], $job_id)
];
```

**Benefits**:
- ✅ **Explicit Dependencies**: Steps read everything from a single payload without relying on shared globals
- ✅ **Consistent Evolvability**: New metadata can be appended to the payload without changing method signatures
- ✅ **Pure Testing**: Steps are testable via simple array fixtures, enabling isolated unit tests

**Step Implementation Pattern** remains identical to the example above—extract what you need from `$payload`, process data, and return the updated packet.

## Job Management

### Job Status

- `pending` - Created but not started
- `running` - Currently executing
- `completed` - Successfully finished
- `failed` - Error occurred
- `completed_no_items` - Finished with no items processed

### Job Operations

**Create Job**:
```php
$job_id = $db_jobs->create_job([
    'pipeline_id' => $pipeline_id,
    'flow_id' => $flow_id
]);
```

**Update Status**:
```php
$job_manager = new \DataMachine\Services\JobManager();
$job_manager->updateStatus($job_id, 'completed', 'Pipeline executed successfully');
```

**Fail Job**:
```php
$job_manager = new \DataMachine\Services\JobManager();
$job_manager->failJob($job_id, 'step_execution_failure', [
    'flow_step_id' => $flow_step_id,
    'reason' => 'detailed_error_reason'
]);
```

## Error Handling

### Exception Management

All step execution is wrapped in try-catch blocks:

```php
try {
    $data = $flow_step->execute($parameters);
    return !empty($data); // Success = non-empty data packet
} catch (\Throwable $e) {
    $logs_manager = new \DataMachine\Services\LogsManager();
    $logs_manager->log('error', 'Step execution failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    $job_manager = new \DataMachine\Services\JobManager();
    $job_manager->failJob($job_id, 'step_execution_failure', $context);
    return false;
}
```

### Failure Actions

**Job Failure**:
- Updates job status to 'failed'
- Logs detailed error information
- Optionally cleans up job data files
- Stops pipeline execution

## Performance Considerations

### Action Scheduler Integration

- **Asynchronous Processing** - Steps run in background via WP cron
- **Immediate Scheduling** - `time()` for next step execution
- **Queue Management** - WordPress handles scheduling and retry logic

### Data Storage Optimization

- **Reference-Based Passing** - Large data stored in files, not database
- **Automatic Cleanup** - Completed jobs cleaned from storage
- **Flow Isolation** - Each flow maintains separate storage directory

### Memory Management

- **Minimal Data Retention** - Only current step data in memory
- **Garbage Collection** - Automatic cleanup after completion