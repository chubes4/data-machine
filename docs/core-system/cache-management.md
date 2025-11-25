# Cache Management

**Location**: `/inc/Engine/Actions/Cache.php`
**Since**: 0.2.0

## Overview

The Cache system provides centralized cache management with granular invalidation patterns for Data Machine. It uses WordPress transients for efficient data storage and supports pattern-based clearing for bulk operations.

## Architecture

**Design Pattern**: Static methods with action-based invalidation
**Storage**: WordPress transients
**Invalidation**: Granular and pattern-based cache clearing

## Cache Keys

### Pipeline Cache Keys

- `datamachine_pipeline_` - Individual pipeline data
- `datamachine_all_pipelines` - All pipelines list
- `datamachine_pipelines_list` - Pipelines list for admin
- `datamachine_pipeline_config_` - Pipeline configuration
- `datamachine_pipeline_count` - Total pipeline count
- `datamachine_pipeline_export` - Pipeline export data

### Flow Cache Keys

- `datamachine_flow_config_` - Individual flow configuration
- `datamachine_pipeline_flows_` - Flows for specific pipeline
- `datamachine_flow_scheduling_` - Flow scheduling data

### Job Cache Keys

- `datamachine_job_` - Individual job data
- `datamachine_job_status_` - Job status information
- `datamachine_total_jobs_count` - Total jobs count
- `datamachine_flow_jobs_` - Jobs for specific flow
- `datamachine_recent_jobs_` - Recent jobs list

### System Cache Keys

- `datamachine_due_flows_` - Flows due for execution

## Cache Patterns

### Wildcard Patterns

- `datamachine_pipeline_*` - All pipeline-related cache
- `datamachine_flow_*` - All flow-related cache
- `datamachine_job_*` - All job-related cache
- `datamachine_recent_jobs*` - Recent jobs cache
- `datamachine_flow_jobs*` - Flow jobs cache

## Action-Based Invalidation

### Pipeline Actions

#### `datamachine_clear_pipeline_cache`

Clear all cache for specific pipeline.

```php
do_action('datamachine_clear_pipeline_cache', $pipeline_id);
```

**Parameters**:
- `$pipeline_id` (int) - Pipeline ID to clear

**Cleared Keys**:
- Individual pipeline data
- Pipeline configuration
- Pipeline flows
- Pipeline export data

#### `datamachine_clear_pipelines_list_cache`

Clear pipelines list cache.

```php
do_action('datamachine_clear_pipelines_list_cache');
```

**Cleared Keys**:
- All pipelines list
- Pipelines count
- Admin pipelines list

### Flow Actions

#### `datamachine_clear_flow_cache`

Clear all cache for specific flow.

```php
do_action('datamachine_clear_flow_cache', $flow_id);
```

**Parameters**:
- `$flow_id` (int) - Flow ID to clear

**Cleared Keys**:
- Flow configuration
- Flow scheduling data
- Flow jobs list

#### `datamachine_clear_flow_config_cache`

Clear flow configuration cache.

```php
do_action('datamachine_clear_flow_config_cache', $flow_id);
```

**Parameters**:
- `$flow_id` (int) - Flow ID to clear

#### `datamachine_clear_flow_scheduling_cache`

Clear flow scheduling cache.

```php
do_action('datamachine_clear_flow_scheduling_cache', $flow_id);
```

**Parameters**:
- `$flow_id` (int) - Flow ID to clear

#### `datamachine_clear_flow_steps_cache`

Clear flow steps cache.

```php
do_action('datamachine_clear_flow_steps_cache', $flow_id);
```

**Parameters**:
- `$flow_id` (int) - Flow ID to clear

### Job Actions

#### `datamachine_clear_job_cache`

Clear specific job cache.

```php
do_action('datamachine_clear_job_cache', $job_id);
```

**Parameters**:
- `$job_id` (string) - Job ID to clear

**Cleared Keys**:
- Job data
- Job status

#### `datamachine_clear_jobs_cache`

Clear job-related cache.

```php
do_action('datamachine_clear_jobs_cache');
```

**Cleared Keys**:
- Total jobs count
- Recent jobs list
- Due flows cache

### System Actions

#### `datamachine_clear_all_cache`

Clear all Data Machine cache.

```php
do_action('datamachine_clear_all_cache');
```

**Cleared Keys**:
- All pipeline cache
- All flow cache
- All job cache
- System cache

## Core Methods

### Cache Setting

#### `datamachine_cache_set()`

Set cache value with automatic key prefixing.

```php
datamachine_cache_set($key, $value, $expiration = HOUR_IN_SECONDS);
```

**Parameters**:
- `$key` (string) - Cache key (without prefix)
- `$value` (mixed) - Value to cache
- `$expiration` (int) - Expiration time in seconds

**Features**:
- Automatic key prefixing
- Default expiration: 1 hour
- WordPress transient integration

### Cache Retrieval

#### `datamachine_cache_get()`

Retrieve cached value.

```php
$value = datamachine_cache_get($key, $default = null);
```

**Parameters**:
- `$key` (string) - Cache key (without prefix)
- `$default` (mixed) - Default value if not found

**Returns**: Cached value or default

### Cache Deletion

#### `datamachine_cache_delete()`

Delete specific cache key.

```php
datamachine_cache_delete($key);
```

**Parameters**:
- `$key` (string) - Cache key (without prefix)

## Pattern-Based Operations

### Wildcard Clearing

#### `clear_cache_pattern()`

Clear cache keys matching pattern.

```php
Cache::clear_cache_pattern($pattern);
```

**Parameters**:
- `$pattern` (string) - Wildcard pattern (supports `*`)

**Example**:
```php
// Clear all pipeline cache
Cache::clear_cache_pattern('datamachine_pipeline_*');

// Clear all flow cache
Cache::clear_cache_pattern('datamachine_flow_*');
```

## Performance Considerations

### Expiration Strategy

- **Default**: 1 hour for most cache
- **Short-term**: 5 minutes for job status
- **Long-term**: 24 hours for configuration data

### Memory Efficiency

- Transients stored in WordPress database
- Automatic cleanup by WordPress
- No in-memory cache persistence

### Database Optimization

- Indexed cache keys for efficient lookup
- Batch operations for pattern clearing
- Minimal database queries per operation

## Integration Points

### Pipeline Operations

```php
// After pipeline update
do_action('datamachine_clear_pipeline_cache', $pipeline_id);
do_action('datamachine_clear_pipelines_list_cache');

// After pipeline creation
do_action('datamachine_clear_pipelines_list_cache');
```

### Flow Operations

```php
// After flow update
do_action('datamachine_clear_flow_cache', $flow_id);

// After flow execution
do_action('datamachine_clear_jobs_cache');
```

### Job Operations

```php
// After job completion
do_action('datamachine_clear_job_cache', $job_id);
do_action('datamachine_clear_jobs_cache');
```

## Usage Examples

### Basic Cache Operations

```php
// Set pipeline cache
datamachine_cache_set('pipeline_config_' . $pipeline_id, $config);

// Get pipeline cache
$config = datamachine_cache_get('pipeline_config_' . $pipeline_id);

// Clear specific pipeline cache
do_action('datamachine_clear_pipeline_cache', $pipeline_id);
```

### Batch Operations

```php
// Clear all pipeline cache
Cache::clear_cache_pattern(Cache::PIPELINE_PATTERN);

// Clear all flow cache
Cache::clear_cache_pattern(Cache::FLOW_PATTERN);

// Clear everything
do_action('datamachine_clear_all_cache');
```

### Custom Cache Integration

```php
// Custom cache with automatic invalidation
function my_custom_function($pipeline_id) {
    // Clear related cache first
    do_action('datamachine_clear_pipeline_cache', $pipeline_id);
    
    // Perform operation
    $result = perform_expensive_operation($pipeline_id);
    
    // Cache result
    datamachine_cache_set('my_custom_result_' . $pipeline_id, $result);
    
    return $result;
}
```

## Debugging

### Cache Inspection

```php
// Check if cache exists
$cached = datamachine_cache_get('my_key');
if ($cached !== null) {
    // Cache hit
} else {
    // Cache miss
}

// Log cache operations
do_action('datamachine_log', 'debug', 'Cache operation', [
    'operation' => 'set',
    'key' => $key,
    'expiration' => $expiration
]);
```

### Cache Statistics

```php
// Monitor cache performance
$start_time = microtime(true);
$value = datamachine_cache_get($key);
$cache_time = microtime(true) - $start_time;

do_action('datamachine_log', 'debug', 'Cache performance', [
    'key' => $key,
    'time' => $cache_time,
    'hit' => $value !== null
]);
```

---

**Implementation**: WordPress transients with action-based invalidation
**Performance**: Granular clearing with pattern support
**Integration**: Automatic cache management for all operations