# Flows Endpoints

**Implementation**: `inc/Api/Flows.php`

**Base URL**: `/wp-json/datamachine/v1/flows`

## Overview

Flow endpoints manage flow instances - configured, scheduled executions of pipeline templates. Flows represent the instance layer of the Pipeline+Flow architecture.

## Authentication

Requires `manage_options` capability. See [Authentication Guide](authentication.md).

## Endpoints

### POST /flows

Create a new flow from an existing pipeline.

**Permission**: `manage_options` capability required

**Parameters**:
- `pipeline_id` (integer, required): Parent pipeline ID
- `flow_name` (string, optional): Flow name (default: "Flow")
- `flow_config` (array, optional): Handler settings per step
- `scheduling_config` (array, optional): Scheduling configuration

**Example Request**:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{
    "pipeline_id": 5,
    "flow_name": "My Custom Flow",
    "scheduling_config": {"interval": "daily"}
  }'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "flow_id": 42,
  "flow_name": "My Custom Flow",
  "pipeline_id": 5,
  "synced_steps": 3,
  "flow_data": {
    "flow_id": 42,
    "pipeline_id": 5,
    "flow_name": "My Custom Flow",
    "flow_config": "{}",
    "scheduling_config": "{\"interval\":\"daily\"}"
  }
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `flow_id` (integer): Newly created flow ID
- `flow_name` (string): Flow name
- `pipeline_id` (integer): Parent pipeline ID
- `synced_steps` (integer): Number of steps synced from pipeline
- `flow_data` (object): Complete flow record

### DELETE /flows/{flow_id}

Delete a flow and all associated jobs.

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_id` (integer, required): Flow ID to delete (in URL path)

**Example Request**:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/flows/42 \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "flow_id": 42,
  "message": "Flow deleted successfully.",
  "deleted_jobs": 5
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `flow_id` (integer): Deleted flow ID
- `message` (string): Confirmation message
- `deleted_jobs` (integer): Number of associated jobs deleted

**Error Response (404 Not Found)**:

```json
{
  "code": "flow_not_found",
  "message": "Flow not found.",
  "data": {"status": 404}
}
```

### POST /flows/{flow_id}/duplicate

Duplicate an existing flow with all configuration.

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_id` (integer, required): Source flow ID to duplicate (in URL path)

**Example Request**:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/flows/42/duplicate \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "source_flow_id": 42,
  "new_flow_id": 43,
  "flow_name": "My Custom Flow (Copy)",
  "pipeline_id": 5,
  "flow_data": {...},
  "pipeline_steps": [...]
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `source_flow_id` (integer): Original flow ID
- `new_flow_id` (integer): Newly created duplicate flow ID
- `flow_name` (string): Duplicate flow name (appends "(Copy)")
- `pipeline_id` (integer): Parent pipeline ID
- `flow_data` (object): Complete flow record
- `pipeline_steps` (array): Pipeline step configuration

**Error Response (404 Not Found)**:

```json
{
  "code": "flow_not_found",
  "message": "Flow not found.",
  "data": {"status": 404}
}
```

## Flow Configuration

### Handler Settings

Flow configuration stores handler-specific settings per step:

```json
{
  "flow_step_id_123": {
    "handler_slug": "rss",
    "handler_config": {
      "feed_url": "https://example.com/feed/",
      "max_items": 10
    }
  },
  "flow_step_id_456": {
    "handler_slug": "twitter",
    "handler_config": {
      "max_length": 280
    }
  }
}
```

### Scheduling Configuration

Scheduling configuration defines execution intervals:

```json
{
  "interval": "hourly"
}
```

**Available Intervals**:
- `manual` - No automatic execution
- `hourly` - Every hour
- `daily` - Once per day
- `weekly` - Once per week
- Custom intervals via WordPress cron system

## Common Workflows

### Create and Execute Flow

```bash
# 1. Create flow
FLOW_ID=$(curl -X POST https://example.com/wp-json/datamachine/v1/flows \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"pipeline_id": 5}' | jq -r '.flow_id')

# 2. Execute flow immediately
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d "{\"flow_id\": $FLOW_ID}"
```

### Duplicate and Modify

```bash
# 1. Duplicate existing flow
NEW_FLOW=$(curl -X POST https://example.com/wp-json/datamachine/v1/flows/42/duplicate \
  -u username:application_password)

# 2. Modify configuration via admin interface or additional API calls
# 3. Execute new flow independently
```

### Cleanup Old Flows

```bash
# Delete flow and associated jobs
curl -X DELETE https://example.com/wp-json/datamachine/v1/flows/42 \
  -u username:application_password
```

## Integration Examples

### Python Flow Management

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/flows"
auth = HTTPBasicAuth("username", "application_password")

# Create flow
payload = {
    "pipeline_id": 5,
    "flow_name": "Automated RSS to Twitter",
    "scheduling_config": {"interval": "hourly"}
}

response = requests.post(url, json=payload, auth=auth)
flow_data = response.json()

print(f"Created flow {flow_data['flow_id']}: {flow_data['flow_name']}")

# Duplicate flow
duplicate_url = f"{url}/{flow_data['flow_id']}/duplicate"
duplicate_response = requests.post(duplicate_url, auth=auth)
duplicate_data = duplicate_response.json()

print(f"Duplicated to flow {duplicate_data['new_flow_id']}")
```

### JavaScript Flow Operations

```javascript
const axios = require('axios');

const flowAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/flows',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Create flow
async function createFlow(pipelineId, flowName) {
  const response = await axios.post(flowAPI.baseURL, {
    pipeline_id: pipelineId,
    flow_name: flowName
  }, { auth: flowAPI.auth });

  return response.data.flow_id;
}

// Delete flow
async function deleteFlow(flowId) {
  const response = await axios.delete(
    `${flowAPI.baseURL}/${flowId}`,
    { auth: flowAPI.auth }
  );

  return response.data.deleted_jobs;
}

// Usage
const flowId = await createFlow(5, 'My Flow');
const deletedJobs = await deleteFlow(flowId);
```

## Related Documentation

- [Pipelines Endpoints](pipelines.md) - Pipeline template management
- [Execute Endpoint](execute.md) - Flow execution
- [Jobs Endpoints](jobs.md) - Job monitoring
- [Authentication](authentication.md) - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/flows`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Flows.php`
