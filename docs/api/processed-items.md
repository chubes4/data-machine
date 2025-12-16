# Deduplication Tracking Endpoints

**Implementation**: `inc/Api/ProcessedItems.php`

**Base URL**: `/wp-json/datamachine/v1/processed-items`

## Overview

Deduplication tracking endpoints manage item tracking records to prevent duplicate processing of content items across flow executions. When a fetch handler processes an item (like an RSS post or Reddit comment), a record is stored to mark it as processed so future flow runs skip it.

## Authentication

Requires `manage_options` capability. See Authentication Guide documentation.

## Endpoints

### GET /processed-items

Retrieve deduplication tracking records with pagination and filtering.

**Permission**: `manage_options` capability required

**Purpose**: Monitor what items have been processed to prevent duplicates, useful for debugging and workflow optimization

**Parameters**:
- `page` (integer, optional): Page number for pagination (default: 1)
- `per_page` (integer, optional): Items per page (default: 20, max: 100)
- `flow_id` (integer, optional): Filter by specific flow ID

**Example Requests**:

```bash
# Get all processed items (paginated)
curl https://example.com/wp-json/datamachine/v1/processed-items \
  -u username:application_password

# Get processed items for specific flow
curl https://example.com/wp-json/datamachine/v1/processed-items?flow_id=42 \
  -u username:application_password

# Get specific page
curl https://example.com/wp-json/datamachine/v1/processed-items?page=2&per_page=50 \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "items": [
    {
      "id": 1523,
      "flow_step_id": "step_uuid_42",
      "source_type": "rss",
      "item_identifier": "https://example.com/post-123",
      "job_id": 789,
      "processed_at": "2024-01-02 14:30:00"
    },
    {
      "id": 1522,
      "flow_step_id": "step_uuid_42",
      "source_type": "rss",
      "item_identifier": "https://example.com/post-122",
      "job_id": 788,
      "processed_at": "2024-01-02 14:00:00"
    }
  ],
  "total": 1523,
  "page": 1,
  "per_page": 20
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `items` (array): Array of deduplication tracking records
- `total` (integer): Total number of tracked items matching filters
- `page` (integer): Current page number
- `per_page` (integer): Number of items per page

**Tracked Item Fields**:
- `id` (integer): Unique processed item ID
- `flow_step_id` (string): Flow step identifier (format: `{pipeline_step_id}_{flow_id}`)
- `source_type` (string): Handler type (e.g., `rss`, `reddit`, `wordpress-local`)
- `item_identifier` (string): Unique identifier for the processed item (URL, post ID, etc.)
- `job_id` (integer): Associated job ID
- `processed_at` (string): Timestamp when item was processed

### DELETE /processed-items/{id}

Delete a specific deduplication tracking record to allow reprocessing that item.

**Permission**: `manage_options` capability required

**Purpose**: Remove tracking for a specific item to force it to be processed again on next flow execution

**Parameters**:
- `id` (integer, required): Processed item ID (in URL path)

**Example Request**:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items/1523 \
  -u username:application_password
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "message": "Deduplication tracking record deleted successfully.",
  "id": 1523
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `message` (string): Confirmation message
- `id` (integer): Deleted tracking record ID

**Error Response (404 Not Found)**:

```json
{
  "code": "processed_item_not_found",
  "message": "Processed item not found.",
  "data": {"status": 404}
}
```

### DELETE /processed-items

Clear deduplication tracking records in bulk by pipeline or flow.

**Permission**: `manage_options` capability required

**Purpose**: Reset deduplication tracking to allow items to be processed again on next execution

**Parameters**:
- `clear_type` (string, required): Clear scope - `pipeline` or `flow`
- `target_id` (integer, required): Pipeline ID or Flow ID depending on clear_type

**Example Requests**:

```bash
# Clear processed items for entire pipeline
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "pipeline", "target_id": 5}'

# Clear processed items for specific flow
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'
```

**Success Response (200 OK)**:

```json
{
  "success": true,
  "data": null,
  "message": "Deleted 42 deduplication tracking records.",
  "items_deleted": 42
}
```

**Error Response (400 Bad Request)**:

```json
{
  "code": "invalid_clear_type",
  "message": "Invalid clear type. Must be 'pipeline' or 'flow'.",
  "data": {"status": 400}
}
```

## Deduplication Tracking System

### How It Works

1. **Fetch Handler**: Records item identifier when fetching content from a source
2. **Database Storage**: Stores `flow_step_id`, `source_type`, `item_identifier`, and `job_id`
3. **Future Executions**: Checks if an item was previously processed before fetching
4. **Skip Duplicates**: Prevents reprocessing of the same item across flow runs

### Item Identifiers by Handler

**RSS Handler**:
- Identifier: RSS item link URL
- Example: `https://example.com/post-123`

**Reddit Handler**:
- Identifier: Reddit post ID
- Example: `t3_abc123`

**WordPress Local Handler**:
- Identifier: WordPress post ID
- Example: `456`

**WordPress API Handler**:
- Identifier: Post link URL
- Example: `https://external-site.com/post-789`

**WordPress Media Handler**:
- Identifier: Attachment ID
- Example: `789`

**Google Sheets Handler**:
- Identifier: Row index
- Example: `5`

### Flow Step ID Format

Processed items are tracked per flow step using composite ID:

```
{pipeline_step_id}_{flow_id}
```

**Example**: `abc-123-def-456_42`

This allows:
- Same pipeline step in different flows to maintain independent tracking
- Pipeline-wide clearing when pipeline is deleted
- Flow-specific clearing when flow is deleted

## Common Workflows

### Force Reprocessing of RSS Feed

```bash
# 1. Clear deduplication tracking for flow
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'

# 2. Execute flow again - items will be processed since tracking was cleared
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 42}'
```

### Debug Deduplication Behavior

```bash
# Check what items have been tracked as processed
curl https://example.com/wp-json/datamachine/v1/processed-items?flow_id=42&per_page=100 \
  -u username:application_password
```

### Reset Pipeline Tracking

```bash
# Clear all deduplication tracking for pipeline
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "pipeline", "target_id": 5}'
```

## Integration Examples

### Python Deduplication Management

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/processed-items"
auth = HTTPBasicAuth("username", "application_password")

# Get deduplication tracking records for flow
params = {"flow_id": 42, "per_page": 100}
response = requests.get(url, params=params, auth=auth)

if response.status_code == 200:
    data = response.json()
    print(f"Found {len(data['items'])} tracked items")

    for item in data['items']:
        print(f"Processed: {item['item_identifier']} at {item['processed_at']}")

# Clear deduplication tracking
clear_response = requests.delete(url, json={
    "clear_type": "flow",
    "target_id": 42
}, auth=auth)

if clear_response.status_code == 200:
    print("Deduplication tracking cleared")
```

### JavaScript Item Tracking Management

```javascript
const axios = require('axios');

const deduplicationAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/processed-items',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Get tracked items count
async function getTrackedCount(flowId) {
  const response = await axios.get(deduplicationAPI.baseURL, {
    params: { flow_id: flowId, per_page: 1 },
    auth: deduplicationAPI.auth
  });

  return response.data.total;
}

// Clear deduplication tracking by flow
async function clearFlowTracking(flowId) {
  const response = await axios.delete(deduplicationAPI.baseURL, {
    data: {
      clear_type: 'flow',
      target_id: flowId
    },
    auth: deduplicationAPI.auth
  });

  return response.data.success;
}

// Delete specific tracking record
async function deleteTrackingRecord(itemId) {
  const response = await axios.delete(
    `${deduplicationAPI.baseURL}/${itemId}`,
    { auth: deduplicationAPI.auth }
  );

  return response.data.success;
}

// Usage
const count = await getTrackedCount(42);
console.log(`Flow 42 has ${count} tracked items`);

await clearFlowTracking(42);
console.log('Flow deduplication tracking cleared');
```

## Use Cases

### Reprocess RSS Feed Items

Clear deduplication tracking to force re-import of previously skipped content:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'
```

### Debug Handler Configuration

After fixing handler configuration, reset deduplication tracking to reprocess items with new settings:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "pipeline", "target_id": 5}'
```

### Monitor Workflow Progress

Track how many items have been processed over time by checking deduplication records:

```bash
curl https://example.com/wp-json/datamachine/v1/processed-items?flow_id=42 \
  -u username:application_password
```

### Remove Specific Deduplication Record

Delete tracking for a specific item to allow it to be processed again:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items/1523 \
  -u username:application_password
```

## Related Documentation

- Execute Endpoint - Workflow execution
- Jobs Endpoints - Job monitoring
- Handlers Endpoint - Available handlers
- Authentication - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/processed-items`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/ProcessedItems.php`
**Database Table**: `wp_datamachine_processed_items`
**Service**: `DataMachine\Services\ProcessedItemsManager`
