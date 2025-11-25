# Scheduling Intervals

Available scheduling intervals for Data Machine flow automation using WordPress Action Scheduler.

## Overview

Data Machine uses WordPress Action Scheduler for recurring flow execution. Scheduling is handled through the Flows API with `scheduling_config` parameter, supporting standard WordPress cron intervals plus custom intervals for workflow automation.

## Standard Intervals

### Hourly
```php
'interval' => 'hourly'
```
- **Frequency**: Every hour
- **Timestamp**: `time() + HOUR_IN_SECONDS`
- **Use Case**: High-frequency content processing, social media updates

### Daily
```php
'interval' => 'daily'
```
- **Frequency**: Every 24 hours
- **Timestamp**: `time() + DAY_IN_SECONDS`
- **Use Case**: Daily content aggregation, blog post scheduling

### Twicedaily
```php
'interval' => 'twicedaily'
```
- **Frequency**: Every 12 hours
- **Timestamp**: `time() + (12 * HOUR_IN_SECONDS)`
- **Use Case**: Semi-daily processing, moderate frequency updates

## Weekly Intervals

### Weekly
```php
'interval' => 'weekly'
```
- **Frequency**: Every 7 days
- **Timestamp**: `time() + WEEK_IN_SECONDS`
- **Use Case**: Weekly reports, content roundups, maintenance tasks

## Custom Intervals

Data Machine supports custom intervals for specific workflow needs:

### Every 2 Hours
```php
'interval' => 'every_2_hours'
```
- **Frequency**: Every 2 hours
- **Timestamp**: `time() + (2 * HOUR_IN_SECONDS)`
- **Use Case**: Frequent processing without hourly overhead

### Every 6 Hours
```php
'interval' => 'every_6_hours'
```
- **Frequency**: Every 6 hours
- **Timestamp**: `time() + (6 * HOUR_IN_SECONDS)`
- **Use Case**: Business-hour processing, moderate frequency

### Every 3 Days
```php
'interval' => 'every_3_days'
```
- **Frequency**: Every 3 days
- **Timestamp**: `time() + (3 * DAY_IN_SECONDS)`
- **Use Case**: Multi-day content processing, periodic updates

### Monthly
```php
'interval' => 'monthly'
```
- **Frequency**: Every 30 days
- **Timestamp**: `time() + (30 * DAY_IN_SECONDS)`
- **Use Case**: Monthly reports, archive processing, cleanup tasks

## Usage Examples

### Flows API Integration

```bash
# Schedule flow to run daily
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Daily Flow",
    "scheduling_config": {"interval": "daily"}
  }'

# Schedule flow to run every 2 hours
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Bi-hourly Flow",
    "scheduling_config": {"interval": "every_2_hours"}
  }'
```

### Programmatic Scheduling

```php
// Schedule flow with custom interval
$result = apply_filters('datamachine_schedule_flow', [
    'flow_id' => 123,
    'interval' => 'every_6_hours',
    'start_time' => time() + (6 * HOUR_IN_SECONDS)
]);

// Schedule one-time execution
$result = apply_filters('datamachine_schedule_flow', [
    'flow_id' => 123,
    'action' => 'schedule_once',
    'timestamp' => strtotime('2024-12-31 23:59:59')
]);
```

## Interval Selection Guidelines

### High Frequency (Hourly to Every 2 Hours)
- **Best For**: Social media posting, news monitoring, real-time data processing
- **Considerations**: Higher server load, API rate limits
- **Examples**: Twitter feeds, breaking news alerts, live data updates

### Medium Frequency (Every 6 Hours to Daily)
- **Best For**: Content aggregation, blog posting, regular data processing
- **Considerations**: Balanced server load, sufficient content accumulation
- **Examples**: RSS feed processing, email newsletters, content curation

### Low Frequency (Every 3 Days to Monthly)
- **Best For**: Reports, archives, maintenance tasks, bulk processing
- **Considerations**: Lower server impact, content batching opportunities
- **Examples**: Monthly reports, content audits, archive processing, cleanup tasks

## Performance Considerations

### Server Load
- Higher frequency intervals increase server resource usage
- Consider content volume and processing complexity
- Monitor job execution times and system performance

### API Rate Limits
- External APIs (Twitter, Facebook, etc.) have rate limits
- Schedule intervals should respect API limitations
- Build in buffer time for API failures

### Content Availability
- Schedule based on content update patterns
- Avoid running when no new content is available
- Use timeframe filtering to optimize processing

## Error Handling

### Missed Schedules
Action Scheduler automatically handles missed schedules:
- **Backlog Processing**: Runs missed executions on next WordPress cron
- **Failure Recovery**: Failed jobs are logged and retried on next schedule
- **Overlap Prevention**: Prevents multiple executions of same flow

### Schedule Conflicts
- **Resource Management**: Action Scheduler queues overlapping executions
- **Priority Handling**: Earlier schedules take precedence
- **Timeout Protection**: Long-running jobs don't block subsequent schedules

## Monitoring and Management

### Schedule Status
```bash
# Check current schedule status
curl -X GET https://example.com/wp-json/datamachine/v1/flows/123 \
  -u username:application_password

# Response includes scheduling information
{
  "flow_id": 123,
  "scheduling_config": {
    "interval": "daily",
    "next_run": "2024-01-02 12:00:00",
    "last_run": "2024-01-01 12:00:00"
  }
}
```

### Schedule Modification

Scheduling modifications are handled through flow updates. To change scheduling for an existing flow, you would typically delete and recreate the flow with new scheduling configuration, or use flow update endpoints if available.

```bash
# Create flow with updated scheduling
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Weekly Flow",
    "scheduling_config": {"interval": "weekly"}
  }'

# Pause scheduling (set to manual)
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Manual Flow",
    "scheduling_config": {"interval": "manual"}
  }'

# Pause scheduling (set to manual)
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 123,
    "flow_name": "Paused Flow",
    "scheduling_config": {"interval": "manual"}
  }'
```

## Related Documentation

- Flow Scheduling - Scheduling integrated into Flows API
- Execute API - Immediate flow execution
- Flows API - Flow management and configuration
- Jobs API - Job monitoring and execution history

---

**Implementation**: WordPress Action Scheduler integration  
**Supported Intervals**: 10+ standard and custom intervals  
**Error Handling**: Automatic missed schedule recovery  
**Performance**: Configurable frequency based on workflow needs