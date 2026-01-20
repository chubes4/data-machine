# Abilities API

WordPress 6.9 Abilities API provides standardized capability discovery and execution for Data Machine operations. All REST API, CLI, and Chat tool operations delegate to registered abilities.

## Overview

The Abilities API in `inc/Abilities/` provides a unified interface for Data Machine operations. Each ability implements `execute_callback` with `permission_callback` for consistent access control across REST API, CLI commands, and Chat tools.

**Total registered abilities**: 49

## Registered Abilities

### Pipeline Management (8 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-pipelines` | List pipelines with pagination | `PipelineAbilities.php` |
| `datamachine/get-pipeline` | Get single pipeline by ID | `PipelineAbilities.php` |
| `datamachine/create-pipeline` | Create new pipeline | `PipelineAbilities.php` |
| `datamachine/update-pipeline` | Update pipeline properties | `PipelineAbilities.php` |
| `datamachine/delete-pipeline` | Delete pipeline and associated flows | `PipelineAbilities.php` |
| `datamachine/duplicate-pipeline` | Duplicate pipeline with flows | `PipelineAbilities.php` |
| `datamachine/import-pipelines` | Import pipelines from JSON | `PipelineAbilities.php` |
| `datamachine/export-pipelines` | Export pipelines to JSON | `PipelineAbilities.php` |

### Pipeline Steps (6 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-pipeline-steps` | List steps for a pipeline | `PipelineStepAbilities.php` |
| `datamachine/get-pipeline-step` | Get single pipeline step | `PipelineStepAbilities.php` |
| `datamachine/add-pipeline-step` | Add step to pipeline | `PipelineStepAbilities.php` |
| `datamachine/update-pipeline-step` | Update pipeline step config | `PipelineStepAbilities.php` |
| `datamachine/delete-pipeline-step` | Remove step from pipeline | `PipelineStepAbilities.php` |
| `datamachine/reorder-pipeline-steps` | Reorder pipeline steps | `PipelineStepAbilities.php` |

### Flow Management (5 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-flows` | List flows with filtering | `FlowAbilities.php` |
| `datamachine/create-flow` | Create new flow from pipeline | `FlowAbilities.php` |
| `datamachine/update-flow` | Update flow properties | `FlowAbilities.php` |
| `datamachine/delete-flow` | Delete flow and associated jobs | `FlowAbilities.php` |
| `datamachine/duplicate-flow` | Duplicate flow within pipeline | `FlowAbilities.php` |

### Flow Steps (4 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-flow-steps` | List steps for a flow | `FlowStepAbilities.php` |
| `datamachine/get-flow-step` | Get single flow step | `FlowStepAbilities.php` |
| `datamachine/update-flow-step` | Update flow step config | `FlowStepAbilities.php` |
| `datamachine/configure-flow-steps` | Bulk configure flow steps | `FlowStepAbilities.php` |

### Job Execution (6 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-jobs` | List jobs with filtering | `JobAbilities.php` |
| `datamachine/get-job` | Get single job details | `JobAbilities.php` |
| `datamachine/delete-jobs` | Delete jobs by criteria | `JobAbilities.php` |
| `datamachine/run-flow` | Execute flow immediately | `JobAbilities.php` |
| `datamachine/get-flow-health` | Get flow health metrics | `JobAbilities.php` |
| `datamachine/get-problem-flows` | List flows exceeding failure threshold | `JobAbilities.php` |

### File Management (5 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/list-files` | List files for a flow | `FileAbilities.php` |
| `datamachine/get-file` | Get single file details | `FileAbilities.php` |
| `datamachine/delete-file` | Delete specific file | `FileAbilities.php` |
| `datamachine/cleanup-files` | Clean up orphaned files | `FileAbilities.php` |
| `datamachine/upload-file` | Upload file to flow | `FileAbilities.php` |

### Processed Items (3 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/clear-processed-items` | Clear processed items for flow | `ProcessedItemsAbilities.php` |
| `datamachine/check-processed-item` | Check if item was processed | `ProcessedItemsAbilities.php` |
| `datamachine/has-processed-history` | Check if flow has processed history | `ProcessedItemsAbilities.php` |

### Settings (6 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-settings` | Get plugin settings | `SettingsAbilities.php` |
| `datamachine/update-settings` | Update plugin settings | `SettingsAbilities.php` |
| `datamachine/get-scheduling-intervals` | Get available scheduling intervals | `SettingsAbilities.php` |
| `datamachine/get-tool-config` | Get AI tool configuration | `SettingsAbilities.php` |
| `datamachine/get-handler-defaults` | Get handler default settings | `SettingsAbilities.php` |
| `datamachine/update-handler-defaults` | Update handler default settings | `SettingsAbilities.php` |

### Authentication (3 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/get-auth-status` | Get OAuth connection status | `AuthAbilities.php` |
| `datamachine/disconnect-auth` | Disconnect OAuth provider | `AuthAbilities.php` |
| `datamachine/save-auth-config` | Save OAuth API configuration | `AuthAbilities.php` |

### Logging (2 abilities)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/write-to-log` | Write log entry with level routing | `LogAbilities.php` |
| `datamachine/clear-logs` | Clear logs by agent type | `LogAbilities.php` |

### Post Query (1 ability)

| Ability | Description | Location |
|---------|-------------|----------|
| `datamachine/query-posts` | Query posts by handler, flow, or pipeline | `PostQueryAbilities.php` |

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

## Architecture

### Delegation Pattern

REST API endpoints, CLI commands, and Chat tools delegate to abilities for business logic:

```
REST API Endpoint → Ability → Service Manager → Database
CLI Command → Ability → Service Manager → Database
Chat Tool → Ability → Service Manager → Database
```

### Ability Registration

Each abilities class registers abilities on the `wp_abilities_api_init` hook:

```php
public function register(): void {
    add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
}
```

## Testing

Unit tests in `tests/Unit/Abilities/` verify ability registration, schema validation, permission checks, and execution logic:

- `AuthAbilitiesTest.php` - Authentication abilities
- `FileAbilitiesTest.php` - File management abilities
- `FlowAbilitiesTest.php` - Flow CRUD abilities
- `FlowStepAbilitiesTest.php` - Flow step abilities
- `JobAbilitiesTest.php` - Job execution abilities
- `LogAbilitiesTest.php` - Logging abilities
- `PipelineAbilitiesTest.php` - Pipeline CRUD abilities
- `PipelineStepAbilitiesTest.php` - Pipeline step abilities
- `PostQueryAbilitiesTest.php` - Post query abilities
- `ProcessedItemsAbilitiesTest.php` - Processed items abilities
- `SettingsAbilitiesTest.php` - Settings abilities

## WP-CLI Integration

CLI commands execute abilities directly. See individual command files in `inc/Cli/Commands/` for available commands.

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
