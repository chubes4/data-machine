# Changelog

All notable changes to Data Machine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-11-14

### Added
- Complete Chat API implementation with session management, conversation persistence, and tool integration
- New REST API endpoints: Handlers, Providers, StepTypes, Tools for enhanced frontend integration
- AdminRootFilters.php for centralized admin functionality
- Standardized execution method across all step type extension files

### Changed
- **Complete migration from jQuery/AJAX to React**: Full removal of jQuery dependencies and AJAX handlers, replaced with modern React components and REST API integration
- **Massive prefix standardization**: Complete migration from `dm_` to `datamachine_` across ALL filters, actions, functions, CSS classes, database operations, and API endpoints
- **Major cache system overhaul**: Implemented granular WordPress action-based clearing system with targeted invalidation methods
- **AI HTTP Client updates**: Refined and updated existing AI HTTP client integration with improved execution context handling
- **Security updates**: Latest stable package versions with vulnerability resolutions and package overrides
- **Enhanced modal system**: Resolved legacy modal conflicts and improved modal architecture
- **Directory restructuring**: Plugin directory renamed from `data-machine` to `datamachine` for ecosystem consistency

### Improved
- **Performance optimizations**: 50% reduction in modal load queries and database operations
- **Filter system enhancements**: Reduced database load and query overhead through optimized patterns
- **Codebase streamlining**: Removal of dead PHP template files, unused CSS, and outdated documentation
- **Documentation updates**: Comprehensive API reference and architectural documentation alignment

### Removed
- **Complete jQuery removal**: All jQuery dependencies, AJAX handlers, and legacy JavaScript patterns
- **Legacy prefix cleanup**: All remaining `dm_` prefixed code components
- **Dead code elimination**: Unused PHP templates, CSS files, and development artifacts
- **Outdated files**: Removed next-steps.md and other obsolete documentation

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

## [0.1.1] - 2025-09-24

### Added
- Admin notice for optional database cleanup of legacy display_order column
- User-controlled migration path instead of automatic database modifications
- Proper logging for database cleanup operations

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