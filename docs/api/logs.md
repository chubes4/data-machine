# Logs API

Centralized system logging via the `LogsManager` service.

## Overview

As of v0.4.0, Data Machine uses a centralized `LogsManager` service and a dedicated database table (`wp_datamachine_logs`) for consistent, performant logging across all agents (Pipeline, Chat, etc.). This replaces the previous file-based logging system.

## Endpoints

### GET /datamachine/v1/logs

Retrieves a paginated list of system logs with advanced filtering.

**Permission**: `manage_options` capability required

**Parameters**:

- `per_page` (int): Number of items (default: 20)
- `page` (int): Page number
- `level` (string): Filter by log level (`info`, `warning`, `error`, `debug`)
- `context` (string): Filter by context (e.g., `flow_id`, `pipeline_id`, `handler_slug`)
- `search` (string): Search within log messages
- `date_start` (string): ISO 8601 start date
- `date_end` (string): ISO 8601 end date

**Returns**: Standardized list of log entries with metadata.

### DELETE /datamachine/v1/logs

Clears log entries.

**Permission**: `manage_options` capability required

**Parameters**:
- `days` (int): Optional. Clear logs older than X days.

## Implementation

For technical details on the logging architecture and the service layer, see the [Logger System Documentation](../core-system/logger.md).

### LogsManager Service

The `LogsManager` service provides direct method calls for logging and retrieval:

```php
$logs_manager = new \DataMachine\Services\LogsManager();

// Log a message
$logs_manager->log('info', 'Executing flow', ['flow_id' => 123]);

// Retrieve logs
$logs = $logs_manager->get_logs([
    'level' => 'error',
    'per_page' => 50
]);
```

### Log Levels

- **Debug**: Detailed execution flow, AI processing steps, tool validation.
- **Info**: Flow triggers, job completions, handler operations.
- **Warning**: Deprecated functionality, missing optional configuration.
- **Error**: Execution failures, API errors, database errors.

## React Interface (@since v0.8.0)

The Data Machine admin UI includes a dedicated Logs page built with React that consumes these endpoints to provide:
- Real-time monitoring of system activity
- Powerful filtering by context and severity
- Deep links to associated jobs and flows
- Visual status indicators for rapid troubleshooting
