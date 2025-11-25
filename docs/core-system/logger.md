# Logger

**Location**: `/inc/Engine/Logger.php`
**Since**: 0.1.0

## Overview

The Logger provides centralized logging utilities for the Data Machine system using Monolog with WordPress integration. It offers configurable log levels, file-based logging, and request-level caching for optimal performance.

## Architecture

**Design Pattern**: Pure functions with static variable caching
**Integration**: WordPress actions for state modification
**Log Storage**: File-based logging with configurable levels

## Core Functions

### datamachine_get_monolog_instance()

Retrieve Monolog instance with request-level caching.

```php
function datamachine_get_monolog_instance($force_refresh = false): MonologLogger
```

**Parameters**:
- `$force_refresh` (bool): Force recreation of Monolog instance

**Returns**: Configured Monolog instance

**Features**:
- Request-level caching using static variable
- Configurable log levels from WordPress options
- Conditional handler creation (only when logging enabled)

### Log Level Management

#### datamachine_get_monolog_level()

Convert string log level to Monolog Level constant.

```php
function datamachine_get_monolog_level(string $level): ?Level
```

**Supported Levels**:
- `debug` - Detailed debugging information
- `info` - General information messages
- `warning` - Warning conditions
- `error` - Error conditions (default)
- `none` - Logging disabled

#### datamachine_get_log_file_path()

Get full path to log file based on WordPress configuration.

```php
function datamachine_get_log_file_path(): string
```

**Features**:
- Uses WordPress `wp_upload_dir()` for proper file paths
- Creates log directory if needed
- Handles multisite configurations

## Configuration

### WordPress Options

**datamachine_log_level**: Default log level setting
- Default: `error`
- Stored in WordPress options table
- Configurable via admin interface

### Log File Location

**Default Path**: `/wp-content/uploads/datamachine-logs/datamachine.log`
- Automatically created if missing
- Proper file permissions handled
- Multisite-aware paths

## Integration

### Action-Based Logging

The Logger integrates with WordPress actions for state modification:

```php
// Log messages via action
do_action('datamachine_log', 'error', 'Error message', ['context' => 'data']);

// Clear logs via action
do_action('datamachine_clear_logs');

// Set log level via action
do_action('datamachine_set_log_level', 'warning');
```

### Handler Integration

All handlers use the centralized logging system:

```php
// Standard logging pattern across handlers
do_action('datamachine_log', 'error', 'Handler execution failed', [
    'handler' => 'handler_name',
    'error' => $error_message,
    'context' => $context_data
]);
```

## Performance Considerations

### Request-Level Caching

- Monolog instance cached per request using static variable
- Avoids repeated instantiation cost
- Force refresh available for testing scenarios

### Conditional Handler Creation

- Log handlers only created when logging enabled
- `none` level prevents handler creation entirely
- Reduces overhead when logging disabled

### File System Efficiency

- Single log file per installation
- Efficient file appending via Monolog
- Automatic log rotation not included (external rotation recommended)

## Error Handling

### Log File Permissions

- Automatic directory creation with proper permissions
- Graceful fallback if log directory unwritable
- WordPress file permission handling

### Monolog Exceptions

- Exception handling for log file creation
- Fallback to error logging if Monolog unavailable
- Graceful degradation for production stability

## Usage Examples

### Basic Logging

```php
// Log error with context
do_action('datamachine_log', 'error', 'Pipeline execution failed', [
    'pipeline_id' => $pipeline_id,
    'flow_id' => $flow_id,
    'error' => $exception->getMessage()
]);

// Log info message
do_action('datamachine_log', 'info', 'Pipeline completed successfully', [
    'pipeline_id' => $pipeline_id,
    'execution_time' => $execution_time
]);
```

### Debug Logging

```php
// Enable debug level temporarily
do_action('datamachine_set_log_level', 'debug');

// Log detailed debug information
do_action('datamachine_log', 'debug', 'Handler execution details', [
    'handler' => 'handler_name',
    'config' => $handler_config,
    'processing_time' => $processing_time
]);
```

### Log Management

```php
// Clear all logs
do_action('datamachine_clear_logs');

// Check current log level
$current_level = get_option('datamachine_log_level', 'error');

// Set new log level
do_action('datamachine_set_log_level', 'warning');
```

## Security Considerations

### Log File Access

- Log files stored in WordPress uploads directory
- Respects WordPress file permissions
- No direct web access to log files
- Sensitive data filtering in log context

### Log Content

- No passwords or API keys logged
- User data sanitized before logging
- Context data limited to non-sensitive information
- Error messages filtered for security

## Integration with Development

### Debug Mode

Enhanced logging available during development:

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    do_action('datamachine_set_log_level', 'debug');
}
```

### Testing

Force refresh available for testing scenarios:

```php
// Get fresh instance for testing
$logger = datamachine_get_monolog_instance(true);
```

---

**Implementation**: Monolog-based logging with WordPress integration
**Configuration**: Via WordPress admin interface and options
**Performance**: Request-level caching and conditional handler creation