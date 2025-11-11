# Engine Execution System

The Data Machine engine uses a three-action execution cycle that orchestrates all pipeline workflows through WordPress Action Scheduler.

## Execution Cycle

### 1. Flow Initiation (`datamachine_run_flow_now`)

**Purpose**: Entry point for all pipeline execution

**Parameters**:
- `$flow_id` (int) - Flow ID to execute

**Process**:
1. Retrieves flow data from database
2. Creates job record for tracking
3. Identifies first step (execution_order = 0)
4. Schedules initial step execution

**Usage**:
```php
do_action('datamachine_run_flow_now', $flow_id, 'manual');
```

### 2. Step Execution (`datamachine_execute_step`)

**Purpose**: Core functional pipeline orchestration - processes individual steps

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Flow step identifier
- `$data` (array|null) - Data packet or storage reference

**Process**:
1. Retrieves data from storage if reference provided
2. Loads complete flow step configuration
3. Discovers step class via `datamachine_step_types` filter
4. Creates step instance and executes with parameters
5. Determines next step or completes pipeline

**Step Execution Pattern**:
```php
$parameters = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data
];
// Engine data accessed by steps via centralized datamachine_engine_data filter
$data = $flow_step->execute($parameters);
```

### 3. Step Scheduling (`datamachine_schedule_next_step`)

**Purpose**: Action Scheduler integration for step transitions

**Parameters**:
- `$job_id` (string) - Job identifier
- `$flow_step_id` (string) - Next step to execute
- `$data` (array) - Data packet to pass

**Process**:
1. Stores data packet in files repository
2. Creates lightweight reference for Action Scheduler
3. Schedules immediate execution of next step

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
wp-content/uploads/data-machine/files/
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

All steps implement the same interface:

```php
class MyStep {
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        
        // Access engine data via centralized datamachine_engine_data filter
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;
        
        // Process data packet
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

### Flat Parameter Architecture

Engine uses unified flat parameter passing with centralized engine data access via `EngineData.php` filter:

**Core Parameters** (always provided):
```php
$parameters = [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data
];

// Centralized engine data access via datamachine_engine_data filter
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
```

**Benefits**:
- ✅ **Simple Interface**: Core parameters always provided, engine data accessed via centralized filter
- ✅ **Architectural Consistency**: EngineData.php filter maintains filter-based service discovery pattern
- ✅ **Unified Access**: Single filter replaces direct database access patterns
- ✅ **Consistent**: Same pattern across all step types

**Step Implementation Pattern**:
```php
class MyStep {
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];

        // Access engine data via centralized datamachine_engine_data filter
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        // Process data packet
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
do_action('datamachine_update_job_status', $job_id, 'completed', 'Pipeline executed successfully');
```

**Fail Job**:
```php
do_action('datamachine_fail_job', $job_id, 'step_execution_failure', [
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
    do_action('datamachine_log', 'error', 'Step execution failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    do_action('datamachine_fail_job', $job_id, 'step_execution_failure', $context);
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

- **Lightweight Step Instances** - Parameter-less constructors
- **Minimal Data Retention** - Only current step data in memory
- **Garbage Collection** - Automatic cleanup after completion

## AutoSave System

### Centralized Auto-Save Operations

**Action**: `dm_auto_save`
**Purpose**: Complete pipeline persistence with synchronization and cache management

**Features**:
- **Complete Pipeline Persistence** - Saves pipeline data, all flows, flow configurations, and scheduling
- **Execution Order Synchronization** - Synchronizes execution_order between pipeline and flow steps
- **Data Consistency** - Ensures all related data remains synchronized
- **Cache Integration** - Automatic cache clearing after successful auto-save operations

**Usage**:
```php
do_action('datamachine_auto_save', $pipeline_id);
```

**Process**:
1. Validates database services availability
2. Retrieves current pipeline data and configuration
3. Saves pipeline data using database services
4. Iterates through all flows for the pipeline
5. Synchronizes execution_order from pipeline to flow steps
6. Updates flow configurations and scheduling data
7. Clears pipeline cache for fresh data on subsequent loads