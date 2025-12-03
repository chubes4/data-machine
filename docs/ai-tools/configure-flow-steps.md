# Configure Flow Steps Tool

Configures handler settings or AI user messages on flow steps. Supports both single-step and bulk pipeline-scoped operations.

## Overview

The `configure_flow_steps` tool enables configuration of flow steps after creation:

1. **Single Mode**: Configure one specific flow step by ID
2. **Bulk Mode**: Configure all matching steps across all flows in a pipeline

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `flow_step_id` | string | No* | Flow step ID for single-step mode |
| `pipeline_id` | integer | No* | Pipeline ID for bulk mode |
| `step_type` | string | No** | Filter by step type (fetch, publish, update, ai) |
| `handler_slug` | string | No | Handler slug to set (single) or filter by (bulk) |
| `handler_config` | object | No*** | Handler config to merge into existing config |
| `user_message` | string | No*** | User message/prompt for AI steps |

**Validation Rules:**
- *One of `flow_step_id` OR `pipeline_id` required
- **When `pipeline_id` provided, at least one of `step_type` or `handler_slug` required
- ***At least one of `handler_config` or `user_message` required

## Usage Examples

### Single Mode: Configure One Step

```json
{
  "flow_step_id": "pipeline_step_123_456",
  "handler_slug": "rss",
  "handler_config": {
    "feed_url": "https://example.com/feed.xml",
    "timeframe_limit": "24_hours"
  }
}
```

### Single Mode: Update Existing Handler Config

```json
{
  "flow_step_id": "pipeline_step_123_456",
  "handler_config": {
    "taxonomy_category_selection": "skip"
  }
}
```

### Bulk Mode: Update All WordPress Publish Steps in Pipeline

```json
{
  "pipeline_id": 42,
  "handler_slug": "wordpress",
  "handler_config": {
    "taxonomy_category_selection": "skip"
  }
}
```

### Bulk Mode: Update All Publish Steps by Type

```json
{
  "pipeline_id": 42,
  "step_type": "publish",
  "handler_config": {
    "post_status": "draft"
  }
}
```

### Bulk Mode: Update AI Prompts Across Pipeline

```json
{
  "pipeline_id": 42,
  "step_type": "ai",
  "user_message": "Summarize the content in 2-3 sentences"
}
```

## Response Format

### Single Mode Response

```json
{
  "success": true,
  "data": {
    "flow_step_id": "pipeline_step_123_456",
    "handler_updated": true,
    "handler_slug": "rss",
    "message": "Flow step configured successfully."
  }
}
```

### Bulk Mode Response

```json
{
  "success": true,
  "data": {
    "pipeline_id": 42,
    "flows_updated": 5,
    "steps_modified": 5,
    "details": [
      {"flow_id": 101, "flow_name": "Music Festival Feed", "flow_step_id": "ps_15_101"},
      {"flow_id": 102, "flow_name": "Food Festival Feed", "flow_step_id": "ps_15_102"}
    ],
    "message": "Updated 5 step(s) across 5 flow(s)."
  }
}
```

## Handler Config Merge Behavior

The `handler_config` parameter merges into existing configuration rather than replacing it entirely. This allows targeted updates:

**Existing config:**
```json
{
  "post_type": "post",
  "post_status": "publish",
  "taxonomy_category_selection": "15"
}
```

**Update with:**
```json
{
  "handler_config": {
    "taxonomy_category_selection": "skip"
  }
}
```

**Result:**
```json
{
  "post_type": "post",
  "post_status": "publish",
  "taxonomy_category_selection": "skip"
}
```

## Integration Workflow

1. Query pipelines with `api_query` to find pipeline ID
2. Use `configure_flow_steps` with `pipeline_id` for bulk updates
3. Or use `flow_step_id` for targeted single-step configuration

## Error Handling

Returns structured error responses for:
- Missing required parameters
- Invalid pipeline_id or flow_step_id
- No matching steps found for bulk criteria
- Handler configuration validation failures
