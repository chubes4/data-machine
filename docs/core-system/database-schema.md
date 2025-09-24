# Database Schema

Data Machine uses four core tables for managing pipelines, flows, jobs, and deduplication tracking.

## Core Tables

### `wp_dm_pipelines`

**Purpose**: Reusable workflow templates

```sql
CREATE TABLE wp_dm_pipelines (
    pipeline_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    pipeline_name varchar(255) NOT NULL,
    pipeline_config longtext NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (pipeline_id),
    KEY pipeline_name (pipeline_name),
    KEY created_at (created_at),
    KEY updated_at (updated_at)
);
```

**Fields**:
- `pipeline_id` - Auto-increment primary key
- `pipeline_name` - Human-readable pipeline name
- `pipeline_config` - JSON configuration containing step definitions
- `created_at` - Creation timestamp
- `updated_at` - Last modification timestamp

### `wp_dm_flows`

**Purpose**: Scheduled instances of pipelines with specific configurations

```sql
CREATE TABLE wp_dm_flows (
    flow_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    pipeline_id bigint(20) unsigned NOT NULL,
    flow_name varchar(255) NOT NULL,
    flow_config longtext NULL,
    scheduling_config longtext NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (flow_id),
    KEY pipeline_id (pipeline_id),
    KEY flow_name (flow_name),
    FOREIGN KEY (pipeline_id) REFERENCES wp_dm_pipelines(pipeline_id) ON DELETE CASCADE
);
```

**Fields**:
- `flow_id` - Auto-increment primary key
- `pipeline_id` - Reference to parent pipeline
- `flow_name` - Instance-specific name
- `flow_config` - JSON configuration with flow-specific settings
- `scheduling_config` - Scheduling rules and automation settings

### `wp_dm_jobs`

**Purpose**: Individual execution records

```sql
CREATE TABLE wp_dm_jobs (
    job_id varchar(36) NOT NULL,
    flow_id bigint(20) unsigned NOT NULL,
    pipeline_id bigint(20) unsigned NOT NULL,
    status enum('pending','running','completed','failed','completed_no_items') NOT NULL DEFAULT 'pending',
    job_data_json longtext NULL,
    engine_data longtext NULL,
    started_at datetime NULL,
    completed_at datetime NULL,
    error_message text NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY flow_id (flow_id),
    KEY pipeline_id (pipeline_id),
    KEY status (status),
    KEY started_at (started_at),
    KEY completed_at (completed_at),
    FOREIGN KEY (flow_id) REFERENCES wp_dm_flows(flow_id) ON DELETE CASCADE
);
```

**Fields**:
- `job_id` - UUID4 string primary key
- `flow_id` - Reference to flow that created this job
- `pipeline_id` - Reference to source pipeline
- `status` - Current execution status
- `job_data_json` - Execution data and results
- `engine_data` - Engine parameters (source_url, image_url) stored by fetch handlers for downstream use
- `started_at` - Execution start timestamp
- `completed_at` - Completion timestamp
- `error_message` - Error details if failed

### `wp_dm_processed_items`

**Purpose**: Deduplication tracking to prevent duplicate processing

```sql
CREATE TABLE wp_dm_processed_items (
    item_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    flow_step_id varchar(255) NOT NULL,
    source_type varchar(50) NOT NULL,
    item_identifier varchar(255) NOT NULL,
    job_id varchar(36) NULL,
    processed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    UNIQUE KEY unique_item (flow_step_id, source_type, item_identifier),
    KEY flow_step_id (flow_step_id),
    KEY source_type (source_type),
    KEY processed_at (processed_at),
    FOREIGN KEY (job_id) REFERENCES wp_dm_jobs(job_id) ON DELETE SET NULL
);
```

**Fields**:
- `item_id` - Auto-increment primary key
- `flow_step_id` - Composite identifier: `{pipeline_step_id}_{flow_id}`
- `source_type` - Handler type (rss, wordpress_local, reddit, etc.)
- `item_identifier` - Unique identifier within source type
- `job_id` - Job that processed this item
- `processed_at` - Processing timestamp

## Relationships

### Primary Relationships

```
Pipeline (1) → Flow (many) → Job (many)
                ↓
            ProcessedItems (many)
```

### Key Identifiers

**Pipeline Step ID**: UUID4 for cross-flow step referencing
```php
$pipeline_step_id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
```

**Flow Step ID**: Composite identifier for flow-specific tracking
```php
$flow_step_id = $pipeline_step_id . '_' . $flow_id;
```

## Database Operations

### Pipeline Operations

**Create Pipeline**:
```php
$pipeline_id = $db_pipelines->create_pipeline([
    'pipeline_name' => 'RSS to Twitter',
    'pipeline_config' => $config_json
]);
```

**Get Pipeline Config**:
```php
$config = $db_pipelines->get_pipeline_config($pipeline_id);
```

### Flow Operations

**Create Flow**:
```php
$flow_id = $db_flows->create_flow([
    'pipeline_id' => $pipeline_id,
    'flow_name' => 'Morning Posts',
    'flow_config' => $flow_config_json
]);
```

**Get Flow Config**:
```php
$config = apply_filters('dm_get_flow_config', [], $flow_id);
```

### Job Operations

**Create Job**:
```php
$job_id = $db_jobs->create_job([
    'pipeline_id' => $pipeline_id,
    'flow_id' => $flow_id
]);
```

**Update Status**:
```php
do_action('dm_update_job_status', $job_id, 'completed', 'Success message');
```

### Processed Items

**Mark Item Processed**:
```php
do_action('dm_mark_item_processed', $flow_step_id, 'rss', $item_id, $job_id);
```

**Check If Processed**:
```php
$is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'rss', $item_id);
```

## Configuration Storage

### Pipeline Config Structure

```json
{
    "step_uuid_1": {
        "step_type": "fetch",
        "handler": "rss",
        "execution_order": 0,
        "system_prompt": "AI instructions...",
        "handler_config": {
            "rss_url": "https://example.com/feed.xml"
        }
    },
    "step_uuid_2": {
        "step_type": "publish",
        "handler": "twitter",
        "execution_order": 1,
        "handler_config": {
            "twitter_include_source": true
        }
    }
}
```

### Flow Config Structure

```json
{
    "step_uuid_1_123": {
        "user_message": "Custom prompt for this flow instance...",
        "execution_order": 0
    },
    "step_uuid_2_123": {
        "execution_order": 1
    }
}
```

## Data Access Patterns

### Service Discovery

All database operations use filter-based discovery:

```php
$all_databases = apply_filters('dm_db', []);
$db_pipelines = $all_databases['pipelines'] ?? null;
$db_flows = $all_databases['flows'] ?? null;
$db_jobs = $all_databases['jobs'] ?? null;
$db_processed_items = $all_databases['processed_items'] ?? null;
```

### Transactional Operations

Database operations maintain referential integrity through foreign key constraints and cascading deletes.

**Pipeline Deletion**: Automatically removes associated flows, jobs, and processed items
**Flow Deletion**: Automatically removes associated jobs and processed items
**Job Deletion**: Sets processed items job_id to NULL

## Indexing Strategy

### Performance Indexes

- **Pipeline Name** - Fast pipeline lookups by name
- **Flow Pipeline ID** - Efficient flow-to-pipeline joins
- **Job Status** - Quick job status filtering
- **Processed Items Composite** - Fast deduplication checks
- **Timestamp Indexes** - Chronological queries and cleanup

### Query Optimization

- **Prepared Statements** - All queries use wpdb::prepare()
- **Selective Columns** - Only required columns retrieved
- **Proper Limits** - Pagination for large result sets
- **Index Hints** - Strategic use of composite indexes

