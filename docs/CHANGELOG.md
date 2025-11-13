# Changelog

All notable changes to Data Machine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.2] - 2025-10-10

### Security
- Updated all packages to latest stable versions to address security vulnerabilities
- Added package overrides and force resolutions for remaining moderate vulnerabilities

### Fixed
- Bluesky authentication issues preventing successful OAuth flow
- Flow step card CSS styling inconsistencies in pipeline interface
- Vision/file info handling in fetch step data packets for proper image processing
- Type bug in AIStepConversationManager affecting conversation state

### Changed
- Renamed plugin directory from `data-machine` to `datamachine` for consistency with function prefixes
- Completed migration from `dm_` to `datamachine_` prefix across all remaining code components
- Updated gitignore patterns for production React assets
- Continued React migration and jQuery removal across admin interfaces
- Refined WordPress publishing system with improved component integration

### Improved
- WordPress Update handler now performs granular content updates instead of full post replacement
- AI directive system enhanced with pipeline context visualization and "YOU ARE HERE" marker
- Filter system database load optimizations reducing query overhead
- Comprehensive AI error logging and optimized fetch handler logging
- Bluesky settings interface aligned with ecosystem standards

### Removed
- Removed outdated `next-steps.md` file

### Added
- Multisite context directive override support for dm-multisite plugin integration
- React component library for Pipelines page:
  - Card components (PipelineCard, FlowCard, PipelineStepCard, FlowStepCard)
  - Modal components (ConfigureStepModal, HandlerSettingsModal, OAuthAuthenticationModal, StepSelectionModal, HandlerSelectionModal, FlowScheduleModal, ImportExportModal)
  - Context management (PipelineContext) for global state
  - Custom hooks for data fetching and state management
  - Shared components (LoadingSpinner, StepTypeIcon, DataFlowArrow, PipelineSelector)
- REST API import/export documentation with curl and Python examples
- REST API settings endpoints documentation with curl examples
- CSV format parameter support on pipelines endpoint for flexible export
- Batch import mode with CSV validation and error handling
- Tool configuration REST endpoint for programmatic settings management
- Cache clearing REST endpoint for external integrations
- Comprehensive logging for all REST API operations

### Technical Details
- Updated ai-http-client library from v1.1.3 to v1.1.4 (filter priority fix for proper directive execution order)
- Actions and filters cleanup for improved code organization
- Documentation alignment and build system verification
- AI directive system fine-tuning to reduce duplicate tool call behavior
- Extended existing `/pipelines` endpoint following REST resource-based design
- Reused existing ImportExport business logic for consistency
- Added proper CSV response headers with timestamped filenames
- Maintained WordPress authentication standards with wpApiSettings.nonce

## [0.1.1] - 2025-09-24

### Removed
- Flow reordering system including drag & drop arrows, database display_order column, and complex position management
- PipelineReorderAjax class and associated JavaScript reordering methods
- CSS styles for flow reordering arrows and drag interactions
- Database methods: increment_existing_flow_orders, update_flow_display_orders, move_flow_up, move_flow_down

### Improved
- Flow creation performance: reduced from 21 database operations to 1 operation per new flow
- Simplified flow ordering using natural newest-first behavior (ORDER BY flow_id DESC)
- Eliminated unnecessary cache clearing operations during flow management
- Streamlined database schema by removing unused display_order column and index

### Added
- Admin notice for optional database cleanup of legacy display_order column
- User-controlled migration path instead of automatic database modifications
- Proper logging for database cleanup operations

### Technical Details
- Maintained newest-at-top flow display behavior without complex reordering UI
- Followed WordPress native patterns for admin notices and AJAX security
- Implemented KISS principle by eliminating over-engineered cosmetic feature

## [0.1.0] - 2025-09-20

### Added
- Initial release of Data Machine
- Pipeline+Flow architecture for AI-powered content workflows
- Multi-provider AI integration (OpenAI, Anthropic, Google, Grok, OpenRouter)
- Visual pipeline builder with drag & drop functionality
- Fetch handlers: RSS, Reddit, Google Sheets, WordPress Local/API/Media, Files
- Publish handlers: Twitter, Bluesky, Threads, Facebook, WordPress, Google Sheets
- Update handlers: WordPress Update with source URL matching
- AI tools: Google Search, Local Search, WebFetch, WordPress Post Reader
- OAuth integration for social media and external services
- Job scheduling and execution engine
- Admin interface with settings, logging, and status monitoring
- Import/export functionality for pipelines
- WordPress plugin architecture with PSR-4 autoloading