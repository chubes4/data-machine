# Abilities API

WordPress 6.9 Abilities API provides standardized capability discovery and execution for Data Machine operations. Centralizes flow queries, logging operations, and post filtering through registered abilities.

## Overview

The Abilities API primitives in `inc/Abilities/` provide a unified interface for Data Machine operations across REST API, CLI, and Chat tools. Each ability implements `execute_callback` with `permission_callback` for consistent access control.

## Registered Abilities

### datamachine/list-flows

Lists flows with optional filtering by pipeline ID or handler slug.

**Category**: datamachine

**Input Schema**:
- `pipeline_id` (integer|null): Filter flows by pipeline ID
- `handler_slug` (string|null): Filter flows using this handler slug (any step that uses this handler)
- `per_page` (integer): Number of flows per page (default: 20, min: 1, max: 100)
- `offset` (integer): Offset for pagination (default: 0)

**Output Schema**:
- `flows` (array): Formatted flow data with latest job status
- `total` (integer): Total flow count matching filters
- `per_page` (integer): Items per page
- `offset` (integer): Pagination offset
- `filters_applied` (object): Active filter values

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/FlowAbilities.php`

### datamachine/write-to-log

Write log entries with level routing to system, pipeline, or chat logs.

**Category**: datamachine

**Input Schema**:
- `level` (string, required): Log level severity - `debug`, `info`, `warning`, `error`, or `critical`
- `message` (string, required): Log message content
- `context` (object): Additional context including `agent_type`, `job_id`, `flow_id`, etc.

**Output Schema**:
- `success` (boolean): Write operation status
- `message` (string): Status message

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/LogAbilities.php`

### datamachine/clear-logs

Clear log files for specified agent type or all logs.

**Category**: datamachine

**Input Schema**:
- `agent_type` (string, required): Agent type log to clear - `pipeline`, `chat`, `system`, or `all`

**Output Schema**:
- `success` (boolean): Clear operation status
- `message` (string): Status message
- `files_cleared` (array): List of cleared agent types

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/LogAbilities.php`

### datamachine/query-posts-by-handler

Query posts by handler slug with pagination support.

**Category**: datamachine

**Input Schema**:
- `handler_slug` (string, required): Handler slug to filter by (e.g., "universal_web_scraper")
- `post_type` (string): Post type to query (default: "any")
- `post_status` (string): Post status to query (default: "publish")
- `per_page` (integer): Number of posts to return (default: 20, min: 1, max: 100)

**Output Schema**:
- `posts` (array): Array of post objects with ID, title, post_type, post_status, handler_slug, flow_id, pipeline_id, and post_date
- `total` (integer): Total posts matching filter

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/PostQueryAbilities.php`

### datamachine/query-posts-by-flow

Query posts by flow ID with pagination support.

**Category**: datamachine

**Input Schema**:
- `flow_id` (integer, required): Flow ID to filter by
- `post_type` (string): Post type to query (default: "any")
- `post_status` (string): Post status to query (default: "publish")
- `per_page` (integer): Number of posts to return (default: 20, min: 1, max: 100)

**Output Schema**:
- `posts` (array): Array of post objects with ID, title, post_type, post_status, handler_slug, flow_id, pipeline_id, and post_date
- `total` (integer): Total posts matching filter

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/PostQueryAbilities.php`

### datamachine/query-posts-by-pipeline

Query posts by pipeline ID with pagination support.

**Category**: datamachine

**Input Schema**:
- `pipeline_id` (integer, required): Pipeline ID to filter by
- `post_type` (string): Post type to query (default: "any")
- `post_status` (string): Post status to query (default: "publish")
- `per_page` (integer): Number of posts to return (default: 20, min: 1, max: 100)

**Output Schema**:
- `posts` (array): Array of post objects with ID, title, post_type, post_status, handler_slug, flow_id, pipeline_id, and post_date
- `total` (integer): Total posts matching filter

**Permission**: `manage_options` or WP_CLI

**Location**: `inc/Abilities/PostQueryAbilities.php`

## Category Registration

The `datamachine` category is registered via `wp_register_ability_category()` on the `wp_abilities_api_categories_init` hook:

```php
wp_register_ability_category(
    'datamachine',
    array(
        'label' => 'Data Machine',
        'description' => 'Data Machine flow and pipeline operations',
    )
);
```

## Permission Model

All abilities support both WordPress admin and WP-CLI contexts:

```php
'permission_callback' => function () {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        return true;
    }
    return current_user_can( 'manage_options' );
}
```

## WP-CLI Integration

### Posts Command

The `PostsCommand` in `inc/Cli/Commands/PostsCommand.php` provides CLI access to post query abilities.

**Available Commands**:

- `wp datamachine posts by-handler <handler_slug>` - Query posts by handler
- `wp datamachine posts by-flow <flow_id>` - Query posts by flow ID
- `wp datamachine posts by-pipeline <pipeline_id>` - Query posts by pipeline ID

**Options**:
- `--post_type=<type>`: Post type to query (default: any)
- `--post_status=<status>`: Post status to query (default: publish)
- `--per_page=<number>`: Number of posts to return (default: 20, min: 1, max: 100)
- `--format=<format>`: Output format - `table` or `json` (default: table)

**Examples**:
```bash
# Query posts by handler
wp datamachine posts by-handler universal_web_scraper

# Query posts by handler with custom post type
wp datamachine posts by-handler universal_web_scraper --post_type=datamachine_event

# Query posts by handler with custom limit
wp datamachine posts by-handler wordpress_publish --per_page=50

# JSON output
wp datamachine posts by-handler wordpress_publish --format=json

# Query posts by flow
wp datamachine posts by-flow 7

# Query posts by pipeline
wp datamachine posts by-pipeline 42
```

## Post Tracking

The `PostTrackingTrait` in `inc/Core/WordPress/PostTrackingTrait.php` provides post tracking functionality for handlers creating WordPress posts.

**Meta Keys**:
- `_datamachine_post_handler`: Handler slug that created the post
- `_datamachine_post_flow_id`: Flow ID associated with the post
- `_datamachine_post_pipeline_id`: Pipeline ID associated with the post

**Usage**:
```php
use PostTrackingTrait;

// After creating a post
$this->storePostTrackingMeta($post_id, $handler_config);
```

## Testing

Unit tests in `tests/Unit/Abilities/` verify ability registration, schema validation, permission checks, and execution logic:

- `FlowAbilitiesTest.php` - Tests `datamachine/list-flows` ability
- `LogAbilitiesTest.php` - Tests `datamachine/write-to-log` and `datamachine/clear-logs` abilities
- `PostQueryAbilitiesTest.php` - Tests `datamachine/query-posts-by-handler`, `datamachine/query-posts-by-flow`, and `datamachine/query-posts-by-pipeline` abilities

## System Log Type

The `system` agent type is used for infrastructure operations including:
- OAuth authentication flows
- Database operations
- File storage and retrieval
- Credential refresh
- Background task execution

System logs are accessible via the logging system and can be cleared using the `datamachine/clear-logs` ability with `agent_type: 'system'`.
