# Changelog

All notable changes to Data Machine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.4] - 2025-11-20

### Added
- **FlowScheduling.php**: New dedicated API endpoint for advanced flow scheduling operations
- **ModalManager.jsx**: Centralized modal rendering system for improved UI consistency
- **useFormState.js**: Generic form state management hook for React components
- **FailJob.php**: Dedicated action class for handling job failure scenarios

### Changed
- **Handler Architecture Refactoring**: Consolidated handler registration by removing individual filter files
  - Eliminated 14 separate filter files (FilesFilters.php, GoogleSheetsFetchFilters.php, RedditFilters.php, etc.)
  - Integrated filter logic directly into handler classes for cleaner architecture
  - Reduced code duplication and improved maintainability
- **Schedule API Consolidation**: Removed standalone Schedule.php endpoint, integrated scheduling into Flows API
- **React Component Updates**: Enhanced modal components with improved state management and error handling
- **OAuth System Cleanup**: Removed OAuthFilters.php, consolidated OAuth functionality
- **Engine Actions Optimization**: Streamlined Engine.php and improved job execution flow

### Removed
- **Schedule.php API Endpoint**: Eliminated redundant scheduling endpoint (292 lines removed)
- **Handler Filter Files**: Removed 14 individual filter files (~800 lines) in favor of direct integration
- **OAuthFilters.php**: Consolidated OAuth filter logic into core handlers
- **Redundant API Methods**: Cleaned up duplicate functionality in various API endpoints

### Fixed
- **React Component Bugs**: Fixed various issues in modal components and form handling
- **API Consistency**: Improved endpoint standardization and error handling
- **State Management**: Enhanced React component state synchronization

### Technical Details
- **Architecture Simplification**: Reduced codebase by ~500 lines through consolidation
- **Performance Improvements**: Streamlined API calls and reduced handler registration overhead
- **Code Quality**: Improved maintainability through centralized functionality

## [0.2.3] - 2025-11-20

### Added
- **TanStack Query + Zustand Architecture** - Complete modernization of React state management
  - Replaced context-based state management with TanStack Query for server state
  - Implemented Zustand for client-side UI state management
  - Eliminated global refresh patterns for granular component updates
  - Added intelligent caching with automatic background refetching
  - Optimistic UI updates for improved user experience

### Improved
- **Performance Enhancements** - No more global component re-renders on data changes
  - Granular updates: only affected components re-render when their data changes
  - Intelligent caching prevents unnecessary API calls
  - Better error handling and loading states throughout the UI
  - Cleaner separation of server state (TanStack Query) and UI state (Zustand)

### Removed
- **Legacy Context System** - Complete removal of PipelineContext and FlowContext
  - Eliminated context brittleness and complex provider hierarchies
  - Removed old hook files that are no longer needed
  - Streamlined component architecture for better maintainability

## [0.2.2] - 2025-11-19

### Added
- **HandlerRegistrationTrait** (`/inc/Core/Steps/HandlerRegistrationTrait.php`) - Eliminates ~70% of boilerplate code across all handler registration files
  - Standardized `registerHandler()` method for consistent registration patterns
  - Automatic filter registration for handlers, auth providers, settings, and AI tools
  - Refactored all 14 handler filter files to use the trait
- **ToolRegistrationTrait** (`/inc/Engine/AI/Tools/ToolRegistrationTrait.php`) - Standardized AI tool registration functionality
  - Agent-agnostic tool registration with dynamic filter creation
  - Helper methods for global tools, chat tools, and configuration handlers
  - Extensible architecture for future agent types

### Improved
- **Server-Side Single Source of Truth** - Enhanced API as access layer for pipeline builder operations
- **Centralized ToolManager** - Consolidated tool management to reduce execution errors
- **Simplified WordPress Settings** - Removed overengineered global defaults logic from WordPress handlers
- **Directive System Cleanup** - Removed legacy compatibility code for cleaner architecture

### Removed
- **Overengineered WordPress Settings Tab** - Eliminated confusing global default functionality
- **Legacy Directive Compatibility** - Cleaned up deprecated directive system code

### Technical Details
- **Handler Registration Standardization**: All handler filter files now use HandlerRegistrationTrait, reducing code duplication by ~70%
- **Tool Registration Extensibility**: ToolRegistrationTrait enables unlimited agent specialization while maintaining consistent patterns
- **Architecture Simplification**: Removed complex WordPress settings logic in favor of programmatic workflow creation via chat endpoint

## [0.2.1] - 2025-11-18

### Added
- **Complete Base Class Architecture**: Major OOP refactoring with standardized inheritance patterns
  - **Step** (`/inc/Core/Steps/Step.php`) - Abstract base for all step types with unified payload handling, validation, logging
  - **FetchHandler** (`/inc/Core/Steps/Fetch/Handlers/FetchHandler.php`) - Base for fetch handlers with deduplication, engine data storage, filtering, logging
  - **PublishHandler** (`/inc/Core/Steps/Publish/Handlers/PublishHandler.php`) - Base for publish handlers with engine data retrieval, image validation, response formatting
  - **SettingsHandler** (`/inc/Core/Steps/Settings/SettingsHandler.php`) - Base for all handler settings with auto-sanitization based on field schema
  - **SettingsDisplayService** (`/inc/Core/Steps/Settings/SettingsDisplayService.php`) - Settings display logic with smart formatting
  - **PublishHandlerSettings** (`/inc/Core/Steps/Publish/Handlers/PublishHandlerSettings.php`) - Base settings for publish handlers with common fields
  - **FetchHandlerSettings** (`/inc/Core/Steps/Fetch/Handlers/FetchHandlerSettings.php`) - Base settings for fetch handlers with common fields
  - **DataPacket** (`/inc/Core/DataPacket.php`) - Standardized data packet creation replacing scattered array construction
- **FilesRepository Architecture**: Modular component structure at `/inc/Core/FilesRepository/`
  - **DirectoryManager** - Directory creation and path management
  - **FileStorage** - File operations and flow-isolated storage
  - **FileCleanup** - Retention policy enforcement and cleanup
  - **ImageValidator** - Image validation and metadata extraction
  - **RemoteFileDownloader** - Remote file downloading with validation
- **WordPress Shared Components**: Centralized WordPress functionality at `/inc/Core/WordPress/`
  - **FeaturedImageHandler** - Image processing and media library integration
  - **TaxonomyHandler** - Taxonomy selection and term creation
  - **SourceUrlHandler** - URL attribution with Gutenberg blocks
  - **WordPressSettingsHandler** - Shared WordPress settings fields
  - **WordPressFilters** - Service discovery registration
- **Enhanced Universal Engine**: Additional conversation management utilities
  - **ConversationManager** - Message formatting and conversation utilities
  - **ToolResultFinder** - Universal tool result search utility for data packet interpretation

### Improved
- **Code Consistency**: Standardized execution method across all step type extension files
- **Architectural Clarity**: Eliminated code duplication through inheritance patterns
- **Maintainability**: Centralized common functionality in reusable base classes

## [0.2.0] - 2025-11-14

### Added
- **Complete REST API Implementation**: Brand new REST API architecture with 10+ endpoints (Auth, Execute, Files, Flows, Jobs, Logs, Pipelines, ProcessedItems, Settings, Users) - did not exist in 0.1.2
- Complete Chat API implementation with session management, conversation persistence, and tool integration
- New REST API endpoints: Handlers, Providers, StepTypes, Tools for enhanced frontend integration
- **Universal Engine Architecture**: Shared AI infrastructure layer at `/inc/Engine/AI/` for Pipeline and Chat agents
- **AIConversationLoop**: Multi-turn conversation execution with automatic tool execution and completion detection
- **RequestBuilder**: Centralized AI request construction with hierarchical directive application
- **ToolExecutor**: Universal tool discovery, enablement validation, and execution with error handling
- **ToolParameters**: Unified parameter building for standard tools and handler tools with engine data integration
- **ConversationManager**: Message formatting and validation utilities for standardized conversation management
- **Filter-Based Directive System**: `datamachine_global_directives` and `datamachine_agent_directives` filters for extensible AI behavior
- AdminRootFilters.php for centralized admin functionality
- Standardized execution method across all step type extension files

### Changed
- **Complete migration from jQuery/AJAX to React**: Full removal of jQuery dependencies and AJAX handlers, replaced with modern React components and REST API integration
- **Massive prefix standardization**: Complete migration from `dm_` to `datamachine_` across ALL filters, actions, functions, CSS classes, database operations, and API endpoints
- **Major cache system overhaul**: Implemented granular WordPress action-based clearing system with targeted invalidation methods
- **Settings Architecture Refactoring**: Moved `SettingsHandler` to `/inc/Core/Steps/Settings/` and extracted complex display logic into `SettingsDisplayService` for better OOP organization
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

### Deprecated
- **AIStepConversationManager**: Replaced by Universal Engine components (AIConversationLoop + ConversationManager)
- **AIStepToolParameters**: Replaced by ToolParameters class in Universal Engine
- **Direct ai-http-client calls**: Use RequestBuilder::build() instead for consistent directive application

### Fixed
- **Chat endpoint refinements**: Improved chat API functionality and error handling
- **Security updates**: Fixed webpack-dev-server vulnerabilities and upgraded js-yaml to address Dependabot alerts
- **Package updates**: Updated all packages to latest stable versions for security and compatibility

### Improved
- **Code consistency**: Standardized execution method across all step type extension files
- **Performance optimizations**: Removed dead pipeline status CSS file and cleaned up unused assets
- **Documentation accuracy**: Corrected changelog entries and updated @since comments in Chat API files

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