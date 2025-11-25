# ProcessedItems Endpoints

**Implementation**: `inc/Api/ProcessedItems.php`

**Base URL**: `/wp-json/datamachine/v1/processed-items`

## Overview

ProcessedItems endpoints manage deduplication tracking records for workflows. These records prevent duplicate processing of content items across flow executions.

## Authentication

Requires `manage_options` capability. See Authentication Guide documentation.

## Endpoints

### GET /processed-items

Retrieve processed item records with pagination and filtering.

**Permission**: `manage_options` capability required

**Purpose**: Monitor deduplication tracking for debugging and workflow optimization

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
- `items` (array): Array of processed item records
- `total` (integer): Total number of processed items matching filters
- `page` (integer): Current page number
- `per_page` (integer): Number of items per page

**Processed Item Fields**:
- `id` (integer): Unique processed item ID
- `flow_step_id` (string): Flow step identifier (format: `{pipeline_step_id}_{flow_id}`)
- `source_type` (string): Handler type (e.g., `rss`, `reddit`, `wordpress-local`)
- `item_identifier` (string): Unique identifier for the processed item (URL, post ID, etc.)
- `job_id` (integer): Associated job ID
- `processed_at` (string): Timestamp when item was processed

### DELETE /processed-items/{id}

Delete a specific processed item record to allow reprocessing.

**Permission**: `manage_options` capability required

**Purpose**: Remove specific processed item to force reprocessing of that item

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
  "message": "Processed item deleted successfully.",
  "id": 1523
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `message` (string): Confirmation message
- `id` (integer): Deleted processed item ID

**Error Response (404 Not Found)**:

```json
{
  "code": "processed_item_not_found",
  "message": "Processed item not found.",
  "data": {"status": 404}
}
```

### DELETE /processed-items

Clear processed items in bulk by pipeline or flow.

**Permission**: `manage_options` capability required

**Purpose**: Reset deduplication tracking to allow items to be processed again

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
  "message": "Processed items cleared successfully."
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

## Deduplication System

### How It Works

1. **Fetch Handler**: Records item identifier when fetching content
2. **Database Storage**: Stores `flow_step_id`, `source_type`, `item_identifier`, `job_id`
3. **Future Executions**: Checks if item was previously processed
4. **Skip Duplicates**: Prevents reprocessing of same item

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
# 1. Clear processed items for flow
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'

# 2. Execute flow again
curl -X POST https://example.com/wp-json/datamachine/v1/execute \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"flow_id": 42}'
```

### Debug Deduplication Behavior

```bash
# Check what items have been processed
curl https://example.com/wp-json/datamachine/v1/processed-items?flow_id=42&per_page=100 \
  -u username:application_password
```

### Reset Pipeline Tracking

```bash
# Clear all processed items for pipeline
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

# Get processed items for flow
params = {"flow_id": 42, "per_page": 100}
response = requests.get(url, params=params, auth=auth)

if response.status_code == 200:
    data = response.json()
    print(f"Found {len(data['items'])} processed items")

    for item in data['items']:
        print(f"Processed: {item['item_identifier']} at {item['processed_at']}")

# Clear processed items
clear_response = requests.delete(url, json={
    "clear_type": "flow",
    "target_id": 42
}, auth=auth)

if clear_response.status_code == 200:
    print("Processed items cleared")
```

### JavaScript Item Management

```javascript
const axios = require('axios');

const itemsAPI = {
  baseURL: 'https://example.com/wp-json/datamachine/v1/processed-items',
  auth: {
    username: 'admin',
    password: 'application_password'
  }
};

// Get processed items count
async function getProcessedCount(flowId) {
  const response = await axios.get(itemsAPI.baseURL, {
    params: { flow_id: flowId, per_page: 1 },
    auth: itemsAPI.auth
  });

  return response.data.total;
}

// Clear by flow
async function clearFlowItems(flowId) {
  const response = await axios.delete(itemsAPI.baseURL, {
    data: {
      clear_type: 'flow',
      target_id: flowId
    },
    auth: itemsAPI.auth
  });

  return response.data.success;
}

// Delete specific item
async function deleteItem(itemId) {
  const response = await axios.delete(
    `${itemsAPI.baseURL}/${itemId}`,
    { auth: itemsAPI.auth }
  );

  return response.data.success;
}

// Usage
const count = await getProcessedCount(42);
console.log(`Flow 42 has processed ${count} items`);

await clearFlowItems(42);
console.log('Flow items cleared');
```

## Use Cases

### Reprocess RSS Feed Items

Clear processed items to force re-import of previously skipped content:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "flow", "target_id": 42}'
```

### Debug Handler Configuration

After fixing handler configuration, reset tracking to reprocess items with new settings:

```bash
curl -X DELETE https://example.com/wp-json/datamachine/v1/processed-items \
  -H "Content-Type: application/json" \
  -u username:application_password \
  -d '{"clear_type": "pipeline", "target_id": 5}'
```

### Monitor Workflow Progress

Track how many items have been processed over time:

```bash
curl https://example.com/wp-json/datamachine/v1/processed-items?flow_id=42 \
  -u username:application_password
```

### Remove Specific Failed Item

Delete tracking for specific item to allow retry:

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
