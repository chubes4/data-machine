# Logs Endpoints

**Implementation**: `inc/Api/Logs.php`

**Base URL**: `/wp-json/datamachine/v1/logs`

## Overview

Logs endpoints provide access to Data Machine's centralized logging system for monitoring, debugging, and troubleshooting workflow executions.

## Authentication

Requires `manage_options` capability. See Authentication Guide.

## Endpoints

### GET /logs

Get log file metadata and configuration.

**Permission**: `manage_options` capability required

**Parameters**: None

**Returns**: Log file information, size, current log level, and available log levels

**Example Request**:

```bash
curl https://example.com/wp-json/datamachine/v1/logs \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "log_file": {
    "path": "/var/www/html/wp-content/uploads/data-machine.log",
    "exists": true,
    "size": 1048576,
    "size_formatted": "1 MB"
  },
  "configuration": {
    "current_level": "debug",
    "available_levels": {
      "debug": "Debug",
      "info": "Info",
      "warning": "Warning",
      "error": "Error"
    }
  }
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `log_file` (object): Log file information
  - `path` (string): Absolute path to log file
  - `exists` (boolean): Whether log file exists
  - `size` (integer): File size in bytes
  - `size_formatted` (string): Human-readable file size
- `configuration` (object): Logging configuration
  - `current_level` (string): Active log level
  - `available_levels` (object): Available log levels with labels

### GET /logs/content

Get log file content with optional filtering.

**Permission**: `manage_options` capability required

**Parameters**:
- `mode` (string, optional): Content mode - `full` (default) or `recent`
- `limit` (integer, optional): Number of recent entries when mode=recent (default: 200, max: 10000)

**Returns**: Log file content with newest entries first

**Example Requests**:

```bash
# Get full log content
curl https://example.com/wp-json/datamachine/v1/logs/content \
  -u username:application_password

# Get recent 100 entries
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=100 \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "content": "[2024-01-02 14:30:00] INFO: Flow triggered via REST API...\n[2024-01-02 14:25:00] DEBUG: Pipeline loaded from cache...",
  "total_lines": 5420,
  "mode": "recent",
  "message": "Loaded 100 recent log entries."
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `content` (string): Log file content (newest first)
- `total_lines` (integer): Total number of lines in log file
- `mode` (string): Content mode used
- `message` (string): Descriptive message

**Error Response (404 Not Found)**:

```json
{
  "code": "log_file_not_found",
  "message": "Log file does not exist.",
  "data": {"status": 404}
}
```

### PUT /logs/level

Update the current log level.

**Permission**: `manage_options` capability required

**Also Accepts**: `POST /logs/level`

**Parameters**:
- `level` (string, required): Log level to set (`debug`, `info`, `warning`, `error`)

**Example Request**:

```bash
curl -X PUT https://example.com/wp-json/datamachine/v1/logs/level \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"level": "info"}'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "level": "info",
  "message": "Log level updated to Info."
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `level` (string): New log level
- `message` (string): Confirmation message

**Error Response (400 Bad Request)**:

```json
{
  "code": "invalid_log_level",
  "message": "Invalid log level. Must be one of: debug, info, warning, error",
  "data": {"status": 400}
}
```

### DELETE /logs

Clear all log entries.

**Permission**: `manage_options` capability required

**Parameters**: None

**Example Request**:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/logs \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "message": "Logs cleared successfully."
}
```

## Log Levels

### Debug

**Level**: `debug`

**Use Cases**:
- Detailed execution flow
- AI processing steps
- Tool validation
- Cache operations
- Configuration loading

**Example Entries**:
```
[2024-01-02 14:30:00] DEBUG: AI Step Directive: Injected system directive
[2024-01-02 14:30:01] DEBUG: Pipeline loaded from cache
[2024-01-02 14:30:02] DEBUG: Tool validation completed
```

### Info

**Level**: `info`

**Use Cases**:
- Flow execution triggers
- Job completions
- Handler operations
- User actions

**Example Entries**:
```
[2024-01-02 14:30:00] INFO: Flow triggered via REST API
[2024-01-02 14:30:15] INFO: Job completed successfully
[2024-01-02 14:30:20] INFO: Pipeline created
```

### Warning

**Level**: `warning`

**Use Cases**:
- Deprecated functionality
- Missing optional configuration
- Performance issues
- Rate limiting

**Example Entries**:
```
[2024-01-02 14:30:00] WARNING: Tool configuration missing
[2024-01-02 14:30:05] WARNING: API rate limit approaching
[2024-01-02 14:30:10] WARNING: Large file size detected
```

### Error

**Level**: `error`

**Use Cases**:
- Execution failures
- API errors
- Database errors
- Configuration errors

**Example Entries**:
```
[2024-01-02 14:30:00] ERROR: Job execution failed
[2024-01-02 14:30:05] ERROR: API authentication failed
[2024-01-02 14:30:10] ERROR: Database connection lost
```

## Log Format

### Standard Format

```
[TIMESTAMP] LEVEL: MESSAGE
```

**Example**:
```
[2024-01-02 14:30:00] INFO: Flow triggered via REST API
```

### With Context

```
[TIMESTAMP] LEVEL: MESSAGE {"context_key": "value"}
```

**Example**:
```
[2024-01-02 14:30:00] DEBUG: AI Step Directive: Injected system directive {"tool_count": 5, "directive_length": 1234}
```

## Integration Examples

### Python Log Monitoring

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/logs/content"
auth = HTTPBasicAuth("username", "application_password")

# Get recent logs
params = {"mode": "recent", "limit": 100}
response = requests.get(url, params=params, auth=auth)

if response.status_code == 200:
    data = response.json()
    print(f"Retrieved {data['total_lines']} log entries")
    print(data['content'])
```

### JavaScript Log Management

```javascript
const axios = require('axios');

const logsAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/logs',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Get log metadata
async function getLogInfo() {
  const response = await axios.get(logsAPI.baseURL, {
    auth: logsAPI.auth
  });

  return response.data.log_file;
}

// Set log level
async function setLogLevel(level) {
  const response = await axios.put(
    `${logsAPI.baseURL}/level`,
    { level },
    { auth: logsAPI.auth }
  );

  return response.data.success;
}

// Clear logs
async function clearLogs() {
  const response = await axios.delete(logsAPI.baseURL, {
    auth: logsAPI.auth
  });

  return response.data.success;
}

// Usage
const logInfo = await getLogInfo();
console.log(`Log size: ${logInfo.size_formatted}`);

await setLogLevel('debug');
console.log('Log level set to debug');

await clearLogs();
console.log('Logs cleared');
```

## Common Workflows

### Debug Workflow Execution

```bash
# 1. Set log level to debug
curl -X PUT https://example.com/wp-json/datamachine/v1/logs/level \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"level": "debug"}'

# 2. Execute workflow
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 42}'

# 3. Check logs
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=50 \
  -u username:application_password
```

### Monitor Production System

```bash
# Set log level to info for production
curl -X PUT https://example.com/wp-json/datamachine/v1/logs/level \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"level": "info"}'

# Check recent logs periodically
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=100 \
  -u username:application_password
```

### Cleanup Old Logs

```bash
# Clear logs to free disk space
curl -X DELETE https://example.com/wp-json/datamachine/v1/logs \
  -u username:application_password
```

## Use Cases

### Troubleshooting Failed Jobs

Review error logs to identify job failure causes:

```bash
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=200 \
  -u username:application_password | grep ERROR
```

### Performance Analysis

Monitor debug logs to identify bottlenecks:

```bash
curl https://example.com/wp-json/datamachine/v1/logs/content \
  -u username:application_password | grep "execution time"
```

### Audit Trail

Review info logs for user actions and system events:

```bash
curl https://example.com/wp-json/datamachine/v1/logs/content?mode=recent&limit=500 \
  -u username:application_password | grep INFO
```

## Related Documentation

- Jobs Endpoints - Job monitoring
- Execute Endpoint - Workflow execution
- Settings Endpoints - Configuration management
- Authentication - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/logs`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Logs.php`
**Log File**: `wp-content/uploads/data-machine.log`
