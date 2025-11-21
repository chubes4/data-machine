# Flow Scheduling

**Implementation**: `inc/Api/Flows/FlowScheduling.php` (integrated into Flows API)

**Base URL**: `/wp-json/datamachine/v1/flows`

## Overview

Flow scheduling is now integrated into the Flows API endpoints. Scheduling operations are handled through flow creation and updates rather than a separate endpoint. This provides better consistency and eliminates redundant API endpoints.

## Authentication

Requires WordPress authentication with `manage_options` capability. Two authentication methods supported:

1. **Application Password** (Recommended for external integrations)
2. **Cookie Authentication** (WordPress admin sessions)

See [Authentication Guide](authentication.md) for setup instructions.

## Capabilities

- **Recurring Schedules**: Set up automated flow execution (hourly, daily, etc.) via `scheduling_config` parameter
- **One-Time Delays**: Schedule single future executions via timestamp in `scheduling_config`
- **Schedule Management**: Update scheduling through flow configuration updates
- **Interval Validation**: Ensures only valid intervals are accepted
- **Action Scheduler Integration**: Uses WordPress Action Scheduler for reliable execution

## Scheduling Through Flows API

Scheduling is now handled through the Flows API endpoints rather than a separate schedule endpoint.

### Setting Up Recurring Schedules

Use the `POST /flows` endpoint with `scheduling_config`:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Daily RSS to Twitter",
    "scheduling_config": {"interval": "daily"}
  }'
```

### Setting Up One-Time Schedules

For one-time execution at a specific timestamp:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "One-time execution",
    "scheduling_config": {"interval": "one_time", "timestamp": 1704153600}
  }'
```

### Updating Scheduling

Update scheduling configuration through flow updates (implementation depends on specific flow update endpoints).

### Available Intervals

The following scheduling intervals are supported through the `datamachine_scheduler_intervals` filter:

- `manual` - No automatic execution (default)
- `hourly` - Every hour
- `daily` - Once per day
- `weekly` - Once per week
- `one_time` - Single execution at specified timestamp

### Manual Scheduling (Disable Automation)

To disable automatic scheduling and require manual execution:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Manual Flow",
    "scheduling_config": {"interval": "manual"}
  }'
```

### Removing Schedules

To unschedule a flow, update its scheduling configuration to manual:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Manual Flow",
    "scheduling_config": {"interval": "manual"}
  }'
```

## Implementation Notes

- Scheduling is handled through the `FlowScheduling` class integrated into the Flows API
- Uses WordPress Action Scheduler for reliable execution
- Schedules are automatically cleared when flows are deleted
- All scheduling operations require `manage_options` capability

## Migration from v0.2.3

In v0.2.4, the standalone `/wp-json/datamachine/v1/schedule` endpoint was removed and scheduling functionality was integrated into the Flows API. Update your API calls to use the Flows endpoints with the `scheduling_config` parameter instead of the old action-based schedule endpoint.
- Existing schedules continue working after plugin updates
- All scheduling operations are logged for debugging

## Related Endpoints

- [Execute](execute.md) - Immediate and delayed flow execution
- [Flows](flows.md) - Flow management and monitoring
- [Jobs](jobs.md) - Monitor scheduled execution results</content>
</xai:function_call">Now I need to create the intervals.md documentation file since I referenced it in the schedule.md. Let me create that. 

<xai:function_call name="write">
<parameter name="filePath">docs/api/intervals.md