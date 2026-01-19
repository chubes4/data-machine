# Services Layer Architecture

**OOP service managers replacing filter-based actions for 3x performance improvement** (@since v0.4.0)

## Overview

The Services Layer represents a fundamental architectural shift from filter-based action indirection to direct method calls through dedicated service managers. This eliminates redundant database queries, reduces complexity, and provides a 3x performance improvement for all core operations.

## Architecture Principles

- **Direct Method Calls**: Eliminate filter indirection overhead
- **Single Responsibility**: Each service manager handles one domain
- **Centralized Business Logic**: Consistent validation and error handling
- **Dependency Injection**: Clean separation of concerns
- **Performance Optimization**: Reduced database queries and caching

## Service Managers

### FlowManager

**Location**: `/inc/Services/FlowManager.php`

**Responsibilities**:
- Flow CRUD operations (create, read, update, delete)
- Flow duplication with step ID remapping
- Step synchronization from pipelines to flows
- Scheduling integration with Action Scheduler

**Key Methods**:
```php
public function create(int $pipeline_id, string $name, array $options = []): ?array
public function get(int $flow_id): ?array
public function delete(int $flow_id): bool
public function duplicate(int $source_flow_id): ?array
public function syncStepsToFlow(int $flow_id, int $pipeline_id, array $steps, array $pipeline_config = []): bool
```

### PipelineManager

**Location**: `/inc/Services/PipelineManager.php`

**Responsibilities**:
- Pipeline CRUD operations with two creation modes
- Complete pipeline creation with steps and handler configuration
- Simple pipeline creation (steps added later via builder)
- Flow cascade deletion when pipelines are deleted

**Key Methods**:
```php
public function create(string $name, array $options = []): ?array
public function createWithSteps(string $name, array $steps, array $options = []): ?array
public function get(int $pipeline_id): ?array
public function getWithFlows(int $pipeline_id): ?array
public function update(int $pipeline_id, array $data): bool
public function delete(int $pipeline_id): array|WP_Error
```

### JobManager

**Location**: `/inc/Services/JobManager.php`

**Responsibilities**:
- Job execution monitoring and status tracking
- Job lifecycle management (create, update, complete, fail)
- Performance metrics and execution statistics
- Integration with engine execution system

**Key Methods**:
```php
public function create(int $flow_id, int $pipeline_id = 0): ?int
public function get(int $job_id): ?array
public function getForFlow(int $flow_id): array
public function getForPipeline(int $pipeline_id): array
public function updateStatus(int $job_id, string $status, string $context = 'update', ?string $old_status = null): bool
public function start(int $job_id, string $status = 'processing'): bool
public function failJob(int $job_id, string $error_type, array $context = []): bool
```

### LogsManager

**Location**: `/inc/Services/LogsManager.php`

**Responsibilities**:
- Centralized log access and filtering
- Log level management (error, warning, info, debug)
- Search and pagination for log entries
- Log cleanup and retention policies

### ProcessedItemsManager

**Location**: `/inc/Services/ProcessedItemsManager.php`

**Responsibilities**:
- Deduplication tracking across all workflows
- Content hash generation and storage
- Processed item lookup and status checking
- Cleanup of old processed item records

### FlowStepManager

**Location**: `/inc/Services/FlowStepManager.php`

**Responsibilities**:
- Individual flow step configuration management
- Handler assignment and configuration updates
- Step validation and error handling
- Integration with handler registration system

### HandlerService

**Location**: `/inc/Services/HandlerService.php`

**Responsibilities**:
- Centralized handler discovery and validation
- Handler settings and schema lookup
- Site-wide handler defaults management
- Priority-based configuration merging (Explicit > Site > Schema)
- Request-level caching for performance

**Key Methods**:
```php
public function getAll(?string $step_type = null): array
public function get(string $handler_slug, ?string $step_type = null): ?array
public function getConfigFields(string $handler_slug): array
public function getSiteDefaults(): array
public function applyDefaults(string $handler_slug, array $config): array
```

### PipelineStepManager

**Location**: `/inc/Services/PipelineStepManager.php`

**Responsibilities**:
- Pipeline step template management
- Step type validation and configuration
- Step ordering and execution sequence
- Integration with step type registration

### CacheManager

**Location**: `/inc/Services/CacheManager.php`

**Responsibilities**:
- Centralized cache invalidation for handlers, step types, and tools
- Site context metadata caching and automatic invalidation
- Coordination with TanStack Query for client-side state synchronization

## Performance Improvements

### Before (Filter-Based Actions)
```php
// Multiple action calls with database queries
do_action('datamachine_create_pipeline', $pipeline_id, $data);
do_action('datamachine_create_flow', $flow_id, $flow_data);
do_action('datamachine_sync_steps_to_flow', $flow_id, $pipeline_id, $steps);
```

### After (Services Layer)
```php
// Single service instantiation with direct method calls
$pipeline_manager = new \DataMachine\Services\PipelineManager();
$flow_manager = new \DataMachine\Services\FlowManager();

$pipeline_result = $pipeline_manager->create($name, $options);
$flow_result = $flow_manager->create($pipeline_result['pipeline_id'], $flow_name);
```

## Integration Points

### REST API Endpoints
All API endpoints now use service managers instead of filter calls:

```php
// Old approach
$result = apply_filters('datamachine_create_pipeline', null, $request_data);

// New approach
$pipeline_manager = new \DataMachine\Services\PipelineManager();
$result = $pipeline_manager->create($request_data['name'], $request_data['options'] ?? []);
```

### WordPress Hooks
Service managers maintain WordPress hook compatibility for backward compatibility:

```php
// Service managers still trigger WordPress actions for extensibility
do_action('datamachine_pipeline_created', $pipeline_id, $pipeline_data);
do_action('datamachine_flow_created', $flow_id, $flow_data);
```

## Error Handling

Service managers provide consistent error handling:

- **Validation**: Input sanitization and validation before operations
- **Logging**: Comprehensive logging for debugging and monitoring
- **Graceful Failures**: Proper error responses without system crashes
- **Rollback**: Transaction-like behavior for complex operations

## Extensibility

The services layer maintains extensibility through:

- **WordPress Actions**: All operations trigger appropriate WordPress actions
- **Filter Integration**: Service managers respect existing filter configurations
- **Dependency Injection**: Easy to extend or override service behavior
- **Interface Segregation**: Clean interfaces for custom implementations

## Migration Notes

The services layer is fully backward compatible:

- Existing filter-based code continues to work
- REST API endpoints maintain same interfaces
- WordPress hooks and actions preserved
- No breaking changes for third-party integrations

## Future Enhancements

Planned improvements to the services layer:

- **Async Operations**: Background processing for heavy operations
- **Event Sourcing**: Audit trail and replay capabilities
- **Caching Layer**: Built-in intelligent caching
- **Rate Limiting**: API rate limiting and quota management
- **Metrics Collection**: Performance monitoring and analytics

## Related Documentation

- [Handler Defaults System](handler-defaults.md) - Configuration merging logic
- [Handler Registration Trait](handler-registration-trait.md) - Service integration
