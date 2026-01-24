# Jobs Endpoints

**Implementation**: `inc/Api/Jobs.php`

**Base URL**: `/wp-json/datamachine/v1/jobs`

## Overview

Jobs endpoints provide monitoring and management of workflow executions. Jobs represent individual execution instances of flows.

## Authentication

Requires `manage_options` capability. See Authentication Guide.

## React Interface

The Jobs interface is a React-based management dashboard built on `@wordpress/components` and TanStack Query.

## Endpoints

### GET /jobs

Retrieve jobs with filtering, sorting, and pagination.

**Permission**: `manage_options` capability required

**Parameters**:
- `orderby` (string, optional): Order jobs by field (default: `job_id`)
- `order` (string, optional): Sort order - `ASC` or `DESC` (default: `DESC`)
- `per_page` (integer, optional): Number of jobs per page (default: 50, max: 100)
- `offset` (integer, optional): Offset for pagination (default: 0)
- `pipeline_id` (integer, optional): Filter by pipeline ID
- `flow_id` (integer, optional): Filter by flow ID
- `status` (string, optional): Filter by job status (`completed`, `failed`, `processing`, etc.)

**Example Requests**:

```bash
# Get all jobs (recent first)
curl https://example.com/wp-json/datamachine/v1/jobs \
  -u username:application_password

# Get failed jobs only
curl https://example.com/wp-json/datamachine/v1/jobs?status=failed \
  -u username:application_password

# Get jobs for specific flow with pagination
curl https://example.com/wp-json/datamachine/v1/jobs?flow_id=42&per_page=25&offset=0 \
  -u username:application_password

# Get jobs for specific pipeline
curl https://example.com/wp-json/datamachine/v1/jobs?pipeline_id=5 \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "data": [
    {
      "job_id": 1523,
      "flow_id": 42,
      "pipeline_id": 5,
      "status": "completed",
      "started_at": "2024-01-02 14:30:00",
      "completed_at": "2024-01-02 14:30:15",
      "error_message": null
    },
    {
      "job_id": 1522,
      "flow_id": 42,
      "pipeline_id": 5,
      "status": "failed",
      "started_at": "2024-01-02 14:00:00",
      "completed_at": "2024-01-02 14:00:05",
      "error_message": "Handler configuration missing"
    }
  ],
  "total": 1523,
  "per_page": 50,
  "offset": 0
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `data` (array): Array of job objects
- `total` (integer): Total number of jobs matching filters
- `per_page` (integer): Number of jobs per page
- `offset` (integer): Pagination offset

**Job Object Fields**:
- `job_id` (integer): Unique job identifier
- `flow_id` (integer): Associated flow ID
- `pipeline_id` (integer): Associated pipeline ID
- `status` (string): Job status (`pending`, `processing`, `completed`, `failed`, `completed_no_items`, `agent_skipped`, etc.)
- `started_at` (string): Job start timestamp
- `completed_at` (string|null): Job completion timestamp
- `error_message` (string|null): Error message if failed

**Job Statuses**:
- `pending` - Job queued but not started
- `processing` - Currently executing
- `completed` - Successfully completed
- `completed_no_items` - Completed successfully but no new items are found to process
- `agent_skipped` - Completed intentionally without processing the current item (supports compound statuses like `agent_skipped - {reason}`)
- `failed` - Execution failed with error

### DELETE /jobs

Clear jobs from the database.

**Permission**: `manage_options` capability required

**Parameters**:
- `type` (string, required): Which jobs to clear - `all` or `failed`
- `cleanup_processed` (boolean, optional): Also clear processed items tracking (default: false)

**Example Requests**:

```bash
# Clear all jobs
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "all"}'

# Clear failed jobs only
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "failed"}'

# Clear all jobs and processed items
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "all", "cleanup_processed": true}'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "message": "Jobs cleared successfully."
}
```

**Error Response (400 Bad Request)**:

```json
{
  "code": "invalid_type",
  "message": "Invalid type parameter. Must be 'all' or 'failed'.",
  "data": {"status": 400}
}
```

## Common Workflows

### Monitor Flow Execution

```bash
# Get recent jobs for specific flow
curl https://example.com/wp-json/datamachine/v1/jobs?flow_id=42&per_page=10 \
  -u username:application_password
```

### Debug Failed Executions

```bash
# Get all failed jobs
curl https://example.com/wp-json/datamachine/v1/jobs?status=failed \
  -u username:application_password
```

### Cleanup Job History

```bash
# Clear failed jobs to reset execution history
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "failed"}'
```

## Integration Examples

### Python Job Monitoring

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/jobs"
auth = HTTPBasicAuth("username", "application_password")

# Get failed jobs
params = {"status": "failed", "per_page": 100}
response = requests.get(url, params=params, auth=auth)

if response.status_code == 200:
    data = response.json()
    print(f"Found {len(data['data'])} failed jobs")

    for job in data['data']: 
        print(f"Job {job['job_id']}: {job['error_message']}")
else:
    print(f"Error: {response.json()['message']}")
```

### JavaScript Job Dashboard

```javascript
const axios = require('axios');

const jobAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/jobs',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Get job statistics
async function getJobStats(flowId) {
  const params = { flow_id: flowId, per_page: 100 };
  const response = await axios.get(jobAPI.baseURL, {
    params,
    auth: jobAPI.auth
  });

  const jobs = response.data.data;
  const stats = {
    total: jobs.length,
    completed: jobs.filter(j => j.status === 'completed').length,
    failed: jobs.filter(j => j.status === 'failed').length,
    processing: jobs.filter(j => j.status === 'processing').length
  };

  return stats;
}

// Clear old jobs
async function clearJobs(type = 'failed') {
  const response = await axios.delete(jobAPI.baseURL, {
    data: { type },
    auth: jobAPI.auth
  });

  return response.data.success;
}

// Usage
const stats = await getJobStats(42);
console.log(`Flow 42: ${stats.completed} completed, ${stats.failed} failed`);

await clearJobs('failed');
console.log('Failed jobs cleared');
```

### PHP Job Monitoring

```php
$url = 'https://example.com/wp-json/datamachine/v1/jobs';
$auth = base64_encode('username:application_password');

// Get jobs for specific flow
$params = http_build_query([
    'flow_id' => 42,
    'status' => 'failed',
    'per_page' => 50
]);

$response = wp_remote_get($url . '?' . $params, [
    'headers' => [
        'Authorization' => 'Basic ' . $auth
    ]
]);

if (!is_wp_error($response)) {
    $data = json_decode(wp_remote_retrieve_body($response), true);

    foreach ($data['data'] as $job) {
        error_log(sprintf(
            'Job %d failed: %s',
            $job['job_id'],
            $job['error_message']
        ));
    }
}
```

## Use Cases

### Execution Monitoring

Monitor workflow success rates and identify failing patterns:

```bash
# Get all jobs ordered by completion time
curl https://example.com/wp-json/datamachine/v1/jobs?orderby=completed_at&order=DESC \
  -u username:application_password
```

### Performance Analysis

Analyze execution duration and identify bottlenecks:

```bash
# Get recent completed jobs
curl https://example.com/wp-json/datamachine/v1/jobs?status=completed&per_page=100 \
  -u username:application_password
```

### Cleanup and Maintenance

Regularly clear old job records to maintain database performance:

```bash
# Clear all jobs and reset processed items tracking
curl -X DELETE https://example.com/wp-json/datamachine/v1/jobs \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"type": "all", "cleanup_processed": true}'
```

## Related Documentation

- [Execute](execute.md) - Flow execution
- [Flows](flows.md) - Flow management
- [Processed Items](processed-items.md) - Deduplication tracking
- [Logs](logs.md) - Detailed execution logs

---

**Base URL**: `/wp-json/datamachine/v1/jobs`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Jobs.php`
