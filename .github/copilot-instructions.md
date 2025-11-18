# Data Machine Copilot Instructions

## Architecture & Core Concepts
- **Pipeline Stack**: Pipelines (templates) → Flows (instances) → Jobs (executions). Use repositories in `inc/Core/Database/`; never access `wp_datamachine_*` tables directly.
- **Execution Loop**: `inc/Engine/Actions/DataMachineActions.php` drives `datamachine_run_flow_now` → `datamachine_execute_step` → `datamachine_schedule_next_step`. Requires Action Scheduler.
- **Base Classes**: Extend `Step`, `FetchHandler`, `PublishHandler` in `inc/Core/Steps/` for unified validation, deduplication, and logging.
- **Universal AI Engine**: `inc/Engine/AI/` (`AIConversationLoop`, `ToolExecutor`, `RequestBuilder`) powers both Pipelines and Chat. Extend these, don't bypass.

## Data & State Management
- **Data Packets**: Use `DataPacket` class for AI payloads. Keep URLs/media separate in engine data.
- **Engine Data**: Store/retrieve job metadata (`source_url`, `image_url`) via `datamachine_engine_data` filter (`inc/Engine/Filters/EngineData.php`).
- **File Handling**: Use `inc/Core/FilesRepository/` for flow-isolated storage and automatic cleanup.
- **IDs**: `pipeline_step_id` is immutable. `flow_step_id` is derived. Sync order via `datamachine_auto_save`.

## Integration & Extension
- **Service Discovery**: Register via filters: `datamachine_handlers`, `chubes_ai_tools`, `datamachine_auth_providers`.
- **Handler Contracts**: AI tools must implement `handle_tool_call`. Use `ToolParameters::buildForHandlerTool()` for config.
- **WordPress Publisher**: Modular components in `inc/Core/WordPress/` (`FeaturedImageHandler`, etc.). System settings override handler config.
- **Update Handlers**: Require `source_url` in engine data (supplied by fetch handlers or search tools).

## Development Workflows
- **React Admin**: `inc/Core/Admin/Pages/Pipelines` is a React SPA consuming `inc/Api/` REST endpoints. No `admin-ajax.php`.
- **Build & Test**: `./build.sh` for prod zip. `npm run start` for React dev. `composer test` for PHPUnit.
- **Logging**: `do_action('datamachine_log', $level, $message, $context)`. Writes to `/wp-content/uploads/datamachine-logs`.
- **Conventions**: Prefix PHP/Hooks with `datamachine_`, CSS with `datamachine-`. No `dm_`.

