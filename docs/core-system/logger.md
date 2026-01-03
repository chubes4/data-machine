# Logger System

**Implementation**: `\DataMachine\Services\LogsManager`
**Database Table**: `wp_datamachine_logs`
**Since**: 0.4.0 (Unified Database Logging)

## Overview

Data Machine uses a centralized database-backed logging system managed by the `LogsManager` service. This provides consistent, performant, and queryable logging across all agents (Pipeline, Chat, etc.).

## Architecture

**Design Pattern**: Service-based logging with direct database persistence.
**Integration**: Custom WordPress action `datamachine_log` for decoupled logging from any component.
**Log Storage**: Single dedicated database table with columns for level, message, context (JSON), and timestamps.
**Agent Context**: Automatically captures the executing agent type and associated job/session context.

## LogsManager Service

The `LogsManager` is the primary interface for interacting with the logging system.

### Methods

#### `log(string $level, string $message, array $context = [])`
Records a log entry in the database.
- **$level**: `info`, `warning`, `error`, `debug`.
- **$message**: Human-readable log message.
- **$context**: Array of metadata (e.g., `pipeline_id`, `flow_id`, `job_id`, `session_id`).

#### `get_logs(array $args = [])`
Retrieves logs with filtering and pagination. Supports filtering by `level`, `context`, `search` string, and date ranges.

#### `clear_logs(?int $days = null)`
Deletes log entries. If `$days` is provided, deletes logs older than that many days.

## Log Levels

- **Debug**: Detailed execution flow, AI processing steps, and tool validation logic.
- **Info**: Successful triggers, job completions, and handler operations.
- **Warning**: Potential issues, missing optional configuration, or deprecated usage.
- **Error**: Execution failures, API errors, and critical system issues.

## Integration

### Action-Based Logging

Components should ideally use the `datamachine_log` action to ensure loose coupling.

```php
// Record an error during pipeline execution
do_action('datamachine_log', 'error', 'Handler execution failed', [
    'handler' => 'twitter',
    'job_id' => $job_id,
    'pipeline_id' => $pipeline_id,
    'error' => $exception->getMessage()
]);

// Record info during a chat session
do_action('datamachine_log', 'info', 'Chat session started', [
    'session_id' => $session_id,
    'agent_type' => 'chat'
]);
```

## Admin Interface

The Data Machine admin UI provides a dedicated **Logs** page built with React. It features:
- **Real-time Monitoring**: Instant visibility into system activity.
- **Advanced Filtering**: Filter by severity, specific pipeline/flow IDs, or date ranges.
- **Search**: Full-text search across log messages and context.
- **Context Awareness**: Deep links from logs to associated [Jobs](../admin-interface/jobs-management.md) and Flows.

## Performance & Maintenance

### Database Efficiency
Logs are stored with indexed columns for rapid filtering. Context is stored as a JSON column to allow for flexible metadata without schema changes.

### Automated Cleanup
The system includes routine cleanup of old logs to prevent table bloat. This can be configured in settings or triggered via the `datamachine_clear_logs` action.

## Multisite Support

The logging system is multisite-aware, prefixing the `datamachine_logs` table with the site-specific database prefix to ensure log isolation across the network.

---

**Implementation**: `inc/Services/LogsManager.php`
**API Endpoints**: `/wp-json/datamachine/v1/logs`
**Related**: [Logs API](../api/logs.md)
