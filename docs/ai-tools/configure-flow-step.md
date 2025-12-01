# Configure Flow Step Tool

Focused tool for configuring handler settings or AI user messages on individual flow steps.

## Overview

The `configure_flow_step` tool enables precise configuration of flow steps after creation. It supports two primary configuration types:

1. **Handler Configuration**: Setting up fetch, publish, or update handlers with their specific parameters
2. **AI Message Configuration**: Setting user prompts/messages for AI processing steps

## Parameters

- **flow_step_id** (string, required): Flow step ID returned from `create_flow` or `create_pipeline` (format: "pipeline_step_123_456")
- **handler_slug** (string, optional): Handler slug for fetch/publish/update steps (e.g., "rss", "wordpress", "bluesky")
- **handler_config** (object, optional): Handler-specific configuration settings
- **user_message** (string, optional): User message/prompt for AI steps

## Usage Examples

### Configure Handler
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

### Configure AI Message
```json
{
  "flow_step_id": "pipeline_step_123_789",
  "user_message": "Summarize the content and create an engaging social media post"
}
```

## Response

Returns confirmation of successful configuration:

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

## Handler Configuration

### Fetch Handlers
- **rss**: `feed_url`, `timeframe_limit`, `search_keywords`
- **wordpress_local**: `post_type`, `timeframe_limit`, `search_keywords`
- **reddit**: `subreddit`, `timeframe_limit`, `search_keywords`
- **google_sheets_fetch**: `spreadsheet_id`, `sheet_name`
- **files**: `file_path`, `file_type`

### Publish Handlers
- **wordpress**: `post_type`, `post_status`, `author_id`, `include_images`
- **twitter**: `include_images`, `tweet_length`
- **bluesky**: `include_images`, `post_length`
- **facebook**: `include_images`, `post_visibility`
- **google_sheets_output**: `spreadsheet_id`, `sheet_name`

### Update Handlers
- **wordpress_update**: `match_criteria`, `update_fields`

## AI Step Configuration

For AI steps, provide a `user_message` that defines the processing instructions:

```json
{
  "flow_step_id": "pipeline_step_123_ai_1",
  "user_message": "Analyze the content and generate a compelling headline with SEO optimization"
}
```

## Integration Workflow

1. Create pipeline with `create_pipeline` or flow with `create_flow`
2. Add steps with `add_pipeline_step` (if needed)
3. Configure each step using `configure_flow_step` with the returned `flow_step_ids`
4. Execute flow with `run_flow`

## Error Handling

Returns structured error responses for:
- Invalid flow_step_id format or non-existent step
- Missing required parameters (handler_slug OR user_message)
- Handler configuration validation failures
- Permission issues

## Validation

- Validates flow_step_id format and existence
- Ensures either handler_slug or user_message is provided
- Validates handler_slug against available handlers
- Performs handler-specific configuration validation