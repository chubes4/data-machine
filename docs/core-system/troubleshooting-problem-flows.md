# Troubleshooting Problem Flows

The Data Machine ecosystem includes automated monitoring to help identify flows that are consistently failing or returning no results. These are categorized as **Problem Flows**.

## What is a Problem Flow?

A flow is flagged as a "Problem Flow" when it exceeds the `problem_flow_threshold` (default is 3) for either:
1. **Consecutive Failures**: The flow has encountered an actual execution error (status `failed`) multiple times in a row.
2. **Consecutive No Items**: The flow has successfully checked for new items but found nothing (status `completed_no_items`) multiple times in a row.

## Monitoring Problem Flows

### Admin Interface
The Jobs dashboard provides visual indicators for flows that are experiencing issues. Look for flows with repeated "Failed" or "No Items" statuses.

### REST API
You can retrieve a list of currently flagged flows via the following endpoint:
`GET /wp-json/datamachine/v1/flows/problems`

### AI Chat Agent
The chat agent can help you identify and troubleshoot problem flows using the `get_problem_flows` tool. 

**Example prompt:**
> "Are there any problem flows currently flagged?"

## Common Causes & Solutions

### Consecutive Failures
*   **Authentication Issues**: OAuth tokens may have expired or been revoked. Use the `AuthenticateHandler` tool or visit the handler settings to reconnect.
*   **Handler Configuration**: The source URL (RSS feed, API endpoint) may be invalid or down.
*   **Provider Limits**: You may have reached rate limits or exhausted credits with your AI provider (OpenAI, Anthropic, etc.).

### Consecutive No Items
*   **Deduplication**: The system is working correctly and has already processed all available items from the source.
*   **Fetch Settings**: The `max_items` or date filters in your fetch handler might be too restrictive.
*   **Source Inactivity**: The source feed or site simply hasn't updated recently.

## Adjusting Thresholds

If you find that flows are being flagged too quickly (e.g., a feed that only updates once a week), you can increase the threshold in the **Settings** panel or via the API:

```json
{
  "problem_flow_threshold": 5
}
```

## Resetting Metrics
The `consecutive_failures` and `consecutive_no_items` counters are automatically reset to 0 as soon as the flow completes a successful execution with status `completed` (meaning at least one item was processed).
