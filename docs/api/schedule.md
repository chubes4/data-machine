# Schedule Endpoint

**Implementation**: `inc/Api/Schedule/Schedule.php`

**Base URL**: `/wp-json/datamachine/v1/schedule`

## Overview

The Schedule endpoint provides dedicated scheduling operations for database flows. Handles recurring schedules, one-time delays, and schedule management.

## Authentication

Requires WordPress authentication with `manage_options` capability. Two authentication methods supported:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See [Authentication Guide](authentication.md) for setup instructions.

## Capabilities

- **Recurring Schedules**: Set up automated flow execution (hourly, daily, etc.)
- **One-Time Delays**: Schedule single future executions
- **Schedule Management**: Update, clear, or modify existing schedules
- **Interval Validation**: Ensures only valid intervals are accepted
- **Action Scheduler Integration**: Uses WordPress Action Scheduler for reliable execution

## Request Format

**Method**: `POST`

**Content-Type**: `application/json`

**Required Parameters**:
- `action` (string): Scheduling action (`schedule`, `unschedule`, `update`, `get_intervals`)

**Conditional Parameters**:
- `flow_id` (integer): Required for `schedule`, `unschedule`, `update` actions
- `interval` (string): Required for recurring schedules (hourly, daily, etc.)
- `timestamp` (integer): Required for one-time schedules

## Actions

### Get Intervals Action

Retrieve the list of available scheduling intervals.

**Parameters**:
- `action`: `"get_intervals"`

**Example**:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "action": "get_intervals"
  }'
```

**Response**:
```json
{
  "success": true,
  "intervals": [
    {
      "value": "manual",
      "label": "Manual only"
    },
    {
      "value": "every_5_minutes",
      "label": "Every 5 Minutes"
    },
    {
      "value": "hourly",
      "label": "Every hour"
    }
  ]
}
```

### Schedule Action

Create a new schedule for a flow.

**Parameters**:
- `flow_id` (required): Flow ID
- `action`: `"schedule"`
- `interval` (optional): Recurring interval
- `timestamp` (optional): One-time execution timestamp

**Examples**:

#### Recurring Schedule (Hourly)

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "flow_id": 123,
    "action": "schedule",
    "interval": "hourly"
  }'
```

**Response**:
```json
{
  "success": true,
  "action": "schedule",
  "type": "recurring",
  "flow_id": 123,
  "flow_name": "My Flow",
  "interval": "hourly",
  "interval_seconds": 3600,
  "first_run": "2024-01-15T14:00:00+00:00",
  "message": "Flow scheduled to run hourly"
}
```

#### One-Time Delayed Execution

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "flow_id": 123,
    "action": "schedule",
    "timestamp": 1704153600
  }'
```

**Response**:
```json
{
  "success": true,
  "action": "schedule",
  "type": "one_time",
  "flow_id": 123,
  "flow_name": "My Flow",
  "timestamp": 1704153600,
  "scheduled_time": "2024-01-02T00:00:00+00:00",
  "message": "Flow scheduled for one-time execution at Tue, 02 Jan 2024 00:00:00 +0000"
}
```

### Unschedule Action

Remove any existing schedule for a flow.

**Parameters**:
- `flow_id` (required): Flow ID
- `action`: `"unschedule"`

**Example**:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "flow_id": 123,
    "action": "unschedule"
  }'
```

**Response**:
```json
{
  "success": true,
  "action": "unschedule",
  "flow_id": 123,
  "message": "Flow schedule cleared"
}
```

### Update Action

Modify an existing schedule or set flow to manual execution.

**Parameters**:
- `flow_id` (required): Flow ID
- `action`: `"update"`
- `interval` (optional): New recurring interval, or `"manual"` to clear schedule
- `timestamp` (optional): New one-time execution timestamp

**Examples**:

#### Change to Daily Schedule

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "flow_id": 123,
    "action": "update",
    "interval": "daily"
  }'
```

#### Set to Manual (Clear Schedule)

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/schedule \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "flow_id": 123,
    "action": "update",
    "interval": "manual"
  }'
```

## Available Intervals

The endpoint accepts any interval defined in the system. Common intervals include:

- `every_5_minutes`
- `hourly`
- `every_2_hours`
- `every_4_hours`
- `qtrdaily` (every 6 hours)
- `twicedaily` (every 12 hours)
- `daily`
- `weekly`

Use the [Intervals endpoint](intervals.md) to get the complete list of available intervals.

## Error Responses

### Invalid Flow ID

```json
{
  "code": "flow_not_found",
  "message": "Flow 123 not found",
  "data": { "status": 404 }
}
```

### Invalid Interval

```json
{
  "code": "invalid_interval",
  "message": "Invalid interval: invalid_interval",
  "data": { "status": 400 }
}
```

### Action Scheduler Unavailable

```json
{
  "code": "scheduler_unavailable",
  "message": "Action Scheduler not available",
  "data": { "status": 500 }
}
```

## Implementation Notes

- Uses WordPress Action Scheduler for reliable execution
- Schedules are automatically cleared when flows are deleted
- Existing schedules continue working after plugin updates
- All scheduling operations are logged for debugging

## Related Endpoints

- [Execute](execute.md) - Immediate and delayed flow execution
- [Flows](flows.md) - Flow management and monitoring
- [Jobs](jobs.md) - Monitor scheduled execution results</content>
</xai:function_call">Now I need to create the intervals.md documentation file since I referenced it in the schedule.md. Let me create that. 

<xai:function_call name="write">
<parameter name="filePath">docs/api/intervals.md