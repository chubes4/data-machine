# Changelog

All notable changes to Data Machine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.7] - 2025-12-17

### Improved
- **Fetch Handler Consistency**: Standardized empty response handling across all fetch handlers to return consistent empty arrays
- **API Documentation**: Updated processed-items API documentation to use "deduplication tracking" terminology for better clarity
- **React Component Reliability**: Added type-safe ID comparison utilities to prevent type coercion bugs in React components
- **Version Number Consistency**: Fixed inconsistent version numbers across plugin files and documentation

### Fixed
- **Type Safety**: Implemented ID utility functions (`isSameId`, `includesId`, `normalizeId`) to handle string/number ID comparisons reliably
- **Component Updates**: Updated FlowCard, FlowSteps, PipelineCheckboxTable, and flows query components to use type-safe ID utilities

### Technical Details
- **Code Cleanup**: Standardized empty array returns in GoogleSheetsFetch and WordPressAPI handlers
- **Documentation**: Comprehensive API documentation updates for deduplication tracking endpoints
- **React Improvements**: New utils/ids.js utility file with type-safe ID comparison functions

## [0.6.6] - 2025-12-16

### Improved
- **Deduplication Tracking**: Renamed "ProcessedItems" endpoints to "Deduplication Tracking" throughout documentation for better clarity
- **Fetch Handler Consistency**: Standardized empty response handling across GoogleSheets and WordPressAPI fetch handlers
- **API Settings Display**: Updated FlowSteps API to use improved handler settings display method for better configuration management

### Technical Details
- **Documentation**: Updated API documentation to use consistent "deduplication tracking" terminology
- **Code Cleanup**: Removed inconsistent empty array returns in fetch handler methods
- **API Enhancement**: Improved handler settings retrieval in flow step configuration endpoints

## [0.6.5] - 2025-12-16

### Changed
- **License Update**: Changed project license from proprietary to GPL-2.0-or-later for open-source compatibility
- **PHP Version Requirement**: Increased minimum PHP version from 8.0 to 8.2 for improved performance and features
- **Dependency Updates**: Updated composer dependencies including TwitterOAuth (^7.0 → ^8.1), PHPUnit (^10.0 → ^12.0), and WordPress stubs (^6.8 → ^6.9)
- **Node.js Dependencies**: Minor updates to @wordpress/scripts (^31.0.0 → ^31.1.0) and React Query (5.90.10 → 5.90.12)
- **File Repository Architecture**: Enhanced file cleanup directory naming to include flow ID for better isolation
- **Fetch Handler Refactoring**: Simplified base FetchHandler by removing unused successResponse() and emptyResponse() helper methods
- **Handler Compatibility**: Updated GoogleSheets and Reddit fetch handlers to use direct array returns instead of removed helper methods

### Technical Details
- **Code Reduction**: -19 lines from FetchHandler refactoring
- **Compatibility**: PHP 8.2+ now required; existing PHP 8.0/8.1 installations must upgrade
- **Security**: Updated dependencies address potential vulnerabilities in older versions

## [0.6.4] - 2025-12-15

### Added
- **Optimistic UI Updates**: Added optimistic updates and reconciliation logic for flow execution in FlowCard component

### Improved
- **React Query Integration**: Refactored flow components to use query hooks instead of direct API calls
- **Flow Execution UX**: Enhanced flow execution with queued status display and automatic result reconciliation
- **API Documentation**: Synchronized auth.md and handlers.md with actual response structures and authentication metadata
- **Code Quality**: Cleaned up excessive comments and improved prop handling across flow components

### Fixed
- **Stable Tag**: Updated README.md stable tag to reflect current version 0.6.3

## [0.6.3] - 2025-12-15

### Improved
- **React Components**: Code cleanup and refactoring in flow-related components (FlowCard, FlowStepHandler, FlowsSection)
- **Code Quality**: Removed excessive comments, improved prop handling, and fixed linting issues

## [0.6.2] - 2025-12-14

### Fixed
- **Build Process**: Fixed version extraction in build.sh to automatically parse from plugin file headers instead of requiring manual setting
- **Pipeline UI**: Fixed type consistency issues in React components where pipeline ID comparisons failed due to string/number mismatches

### Added
- **Optimistic Updates**: Added optimistic UI updates for pipeline creation providing immediate feedback while API requests process
- **Debug Logging**: Added debug logging to AI conversation loop for tool call execution and results tracking

### Improved
- **AI Chat Agent**: Enhanced chat agent directive with action bias guidance, configuration section, and improved context understanding
- **UI State Management**: Improved pipeline selection state handling with better null checking and string normalization

## [0.6.1] - 2025-12-10

### Added
- **AuthenticateHandler Chat Tool** - New conversational tool for managing handler authentication via natural language
  - Actions: list (all statuses), status (specific handler), configure (save credentials), get_oauth_url (OAuth providers), disconnect
  - Security-aware design with warnings about credential visibility in chat logs
- **OAuth URL REST Endpoint** - New `GET /auth/{handler_slug}/oauth-url` for programmatic OAuth URL retrieval
- **Config Status in Auth Responses** - Auth status now returns masked credential indicators for better UX

### Enhanced
- **OAuth Authentication Modal** - Added "Change API Configuration" button for connected handlers, improved form visibility management
- **Handler Registration** - Added `auth_provider_key` metadata for flexible handler/auth provider mapping
- **Social Media Handlers** - Fixed auth provider key registration for Twitter, Facebook, Threads, and Bluesky

### Improved
- **FileCleanup Architecture** - Replaced `wp_delete_directory()` with native PHP recursive deletion for better cross-platform reliability

### Removed
- **Redundant OAuth Filter** - Removed `datamachine_oauth_url` filter (functionality moved to REST API)

### Documentation
- Added AuthenticateHandler tool documentation
- Updated Auth API documentation with OAuth URL endpoint

## [0.6.0] - 2025-12-08

### Milestone Release
- **WordPress Plugin Check Compliance**: Complete code cleanup and modifications to pass WordPress plugin directory standards
- **Testing Phase**: Version prepared for comprehensive testing prior to WordPress.org release

### Enhanced
- **Core Architecture**: Services layer refinements and function name standardization for better code organization
- **API Improvements**: Enhanced Files API with expanded functionality, Jobs API updates, and improved endpoint consistency
- **Chat Tools**: Updated ConfigureFlowSteps tool with improved bulk operations, ExecuteWorkflowTool enhancements, and ApiQuery refinements
- **Database Operations**: Chat database improvements, job operations enhancements, and pipeline data handling updates
- **OAuth System**: Authentication handler updates across Google Sheets, Reddit, Facebook, Threads, and Twitter providers
- **File Management**: Enhanced FileCleanup and RemoteFileDownloader with improved error handling and validation

### Changed
- **Function Naming**: Standardized function names in main plugin file (datamachine_run_datamachine_plugin, datamachine_activate_plugin_defaults)
- **Documentation**: Updated AGENTS.md to reflect ConfigureFlowSteps tool improvements
- **Admin Interface**: Settings page refinements and UI component updates for better user experience

### Technical Details
- **Code Changes**: 54 files modified with 707 insertions and 691 deletions
- **Architecture**: Maintained backward compatibility while improving code quality and WordPress standards compliance
- **Performance**: Optimized database queries and API operations for better reliability

## [0.5.8] - 2025-12-05

### Security
- Additional package security updates and dependency fixes

### Enhanced
- **RunFlow Tool**: Improved timestamp validation logic for more reliable flow execution scheduling
- **React UI Components**: Added Zustand hydration checks to prevent initialization race conditions
- **State Persistence**: Enhanced UI store with better persistence configuration for cross-session memory

### Fixed
- **Package Version Mismatch**: Corrected package-lock.json version to match current release

## [0.5.7] - 2025-12-05

### Security
- Updated ai-http-client dependency from v2.0.3 to v2.0.7 for security fixes
- Updated node-forge from 1.3.1 to 1.3.3 in package dependencies

### Fixed
- React hydration timing issue in PipelinesApp component that could cause race conditions during initialization

### Documentation
- Added comprehensive AI Directives System documentation
- Added Import/Export System documentation
- Updated version references in documentation

## [0.5.6] - 2025-12-03

### Enhanced
- **ConfigureFlowSteps Tool**: Renamed from ConfigureFlowStep (singular) and enhanced with bulk pipeline-scoped operations for configuring multiple flow steps across all flows in a pipeline
- **FlowStepManager**: Handler configuration updates now merge into existing config instead of replacing, enabling incremental configuration changes
- **AIStep Image Handling**: Improved vision image processing to use engine data as single source of truth for file paths and metadata
- **UI State Persistence**: Added localStorage persistence for selected pipeline in React UI store for cross-session memory

### Added
- **HttpClient Documentation**: Comprehensive documentation for the centralized HTTP client architecture at `/docs/core-system/http-client.md`

### Technical Details
- **Bulk Operations**: ConfigureFlowSteps tool now supports both single-step and pipeline-wide bulk configuration modes
- **Merge Behavior**: Handler config updates use array_merge for incremental changes rather than full replacement
- **UI Enhancement**: Pipeline selection persists across browser sessions using Zustand persist middleware

## [0.5.5] - 2025-12-02

### Fixed
- **Timezone Handling**: Standardized UTC timezone usage across DateFormatter, Chat database, Flows scheduling, and file operations for better cross-timezone compatibility
- **API Parameter Bug**: Fixed pipeline title update endpoint to use correct `pipeline_id` parameter instead of `id`
- **Date Consistency**: Improved GMT timestamp handling using `current_time('mysql', true)` for consistent timezone-aware operations

### Removed
- **DynamicToolProvider.php**: Removed unused abstract base class that was relocated to datamachine-events extension

### Technical Details
- **Timezone Standardization**: All database date operations now use UTC timezone for consistent storage and retrieval
- **API Reliability**: Fixed parameter handling bug in pipeline management endpoints

## [0.5.4] - 2025-12-02

### Removed
- **DynamicToolProvider Base Class** - Removed unused abstract base class from core
  - Pattern relocated to datamachine-events where it's actually used
  - See datamachine-events `DynamicToolParametersTrait` for the active implementation

### Enhanced
- **OAuth Authentication UX**: Added redirect URL display in authentication modals for OAuth providers
- **Handlers API**: Enhanced OAuth provider metadata with callback URL information
- **WordPress Settings**: Improved taxonomy field label formatting in settings handlers

## [0.5.3] - 2025-12-02

### Fixed
- **Date Handling**: Improved timezone-aware date parsing across DateFormatter, Chat database, and Flows scheduling using DateTime instead of strtotime
- **DateFormatter**: Removed inconsistent relative time functionality for consistent absolute date display
- **Session Management**: Enhanced chat session expiration checking with proper error handling for invalid dates
- **Flow Scheduling**: Better timestamp calculations for scheduling intervals with timezone support

### Technical Details
- **Date Consistency**: Standardized DateTime usage with wp_timezone() across core database operations
- **Error Handling**: Added try/catch blocks for invalid date formats in critical paths

## [0.5.2] - 2025-12-02

### Fixed
- **ExecuteWorkflowTool**: Improved REST request error handling for better reliability
- **Flow Scheduling**: Preserve last_run_at and last_run_status when updating schedule configuration
- **Flow Data**: Include last_run_status in flow API responses for better status tracking
- **Date Formatting**: Enhanced display formatting to show status indicators for failed or no-items runs
- **Handler Settings Modal**: Prevent duplicate settings enrichment when handler details load asynchronously
- **Job Status Updates**: Properly update flow last_run_status when jobs complete
- **Pipeline Configuration**: Use array format instead of JSON strings for pipeline_config consistency

### Technical Details
- **Data Contract Consistency**: Standardized pipeline_config as arrays across service layer
- **Error Handling**: Improved REST API error response handling in chat tools
- **UI Reliability**: Fixed race conditions in React modal settings enrichment

## [0.5.1] - 2025-12-01

### Changed
- **ExecuteWorkflow Tool Architecture** - Consolidated modular directory structure into streamlined single-file architecture
  - Removed `ExecuteWorkflow/` subdirectory with 4 separate files (DefaultsInjector, DocumentationBuilder, ExecuteWorkflowTool, WorkflowValidator)
  - Added consolidated `ExecuteWorkflowTool.php` that delegates execution to the Execute API
  - Added shared `HandlerDocumentation.php` utility for dynamic handler documentation generation
- **Data Contract Standardization** - Centralized JSON encoding at database layer
  - Service managers now pass arrays to database operations
  - Database layer exclusively handles JSON encoding via `wp_json_encode()`
  - Eliminated dual-support fallbacks for string vs array input across 6 files
- **ConfigureFlowStep Tool** - Enhanced with dynamic handler documentation in tool description

### Fixed
- **Ephemeral Workflow Execution** - Fixed parameter key from `config` to `handler_config` in Execute API step processing

### Technical Details
- **Code Reduction**: -612 lines from ExecuteWorkflow modular structure
- **Code Addition**: +300 lines for consolidated architecture
- **Net Change**: -645 lines for cleaner, more maintainable codebase
- **Architecture**: Single source of truth for JSON encoding eliminates type ambiguity

## [0.5.0] - 2025-12-01

### Added
- **HttpClient Class** - New centralized HTTP client (`/inc/Core/HttpClient.php`) providing standardized request handling, error management, and logging across all handlers
- **@since 0.5.0** - Added version annotation to HttpClient class documentation

### Changed
- **HTTP Request Architecture** - Complete migration from filter-based HTTP requests to centralized HttpClient usage across all fetch and publish handlers
- **Handler Base Classes** - Updated `FetchHandler` and `PublishHandler` to integrate HttpClient for consistent HTTP operations
- **OAuth and Authentication** - Enhanced OAuth2Handler and provider classes with improved HTTP client integration

### Improved
- **Error Handling** - Standardized HTTP error responses and logging across all external API interactions
- **Code Consistency** - Unified HTTP request patterns eliminating duplication across 16+ handler files
- **Performance** - Optimized HTTP operations with consistent timeout handling and browser simulation capabilities

### Technical Details
- **New File**: +251 lines in HttpClient.php
- **Refactored Files**: 18 handlers updated to use HttpClient
- **Code Reduction**: -114 lines removed from DataMachineFilters.php HTTP filter logic
- **Compatibility**: No breaking changes, fully backward compatible

## [0.4.9] - 2025-11-30

### Enhanced
- **UI Layout Improvements** - Restructured flow header and footer components for better information hierarchy
  - Moved Flow ID display from header title area to footer for cleaner header layout
  - Improved flow card header structure with better action button organization
  - Enhanced FlowHeader and FlowFooter component layouts

### Improved
- **Chat Tool Documentation** - Enhanced RunFlow tool parameter descriptions for clearer immediate vs scheduled execution
  - Improved timestamp parameter documentation to prevent confusion
  - Better tool description clarity for AI agent usage

### Added
- **Comprehensive Chat Tool Documentation** - Created complete documentation for all 8 specialized chat tools
  - AddPipelineStep - Pipeline step management with automatic flow synchronization
  - ApiQuery - REST API discovery and query tool with comprehensive endpoint documentation
  - ConfigureFlowStep - Flow step configuration for handlers and AI messages
  - ConfigurePipelineStep - Pipeline-level AI settings configuration
  - CreateFlow - Flow creation from existing pipelines
  - CreatePipeline - Pipeline creation with optional predefined steps
  - RunFlow - Flow execution and scheduling tool
  - UpdateFlow - Flow property update tool

### Fixed
- **Modal Navigation** - Added "Back to Settings" button option in OAuth authentication modal
- **CSS Layout** - Improved flow header alignment and modal width handling for better responsive design
- **Documentation Version Synchronization** - Updated all version references from v0.4.6 to v0.4.9 across documentation

### Technical Details
- **Documentation**: Added 8 new comprehensive tool documentation files
- **Code Changes**: +15 lines added, -25 lines removed across 10 modified files
- **UI Components**: Enhanced FlowHeader, FlowFooter, and OAuth modal components
- **Compatibility**: No breaking changes, fully backward compatible
- **Components**: Improved React component structure and CSS styling

## [0.4.8] - 2025-11-30

### Enhanced
- **Chat Tools**: Improved CreatePipeline tool with AI step parameter support (provider, model, system_prompt) and clearer flow creation messaging to prevent duplicate flow creation
- **UI Improvements**: Added flow ID display in flow headers for better identification and enhanced flow card layouts with improved header structure
- **API Messaging**: Updated execution success messages for better async operation clarity and job status tracking

### Technical Details
- **Code Changes**: +47 lines for UI enhancements and chat tool improvements
- **Compatibility**: No breaking changes, fully backward compatible
- **Components**: Enhanced FlowHeader and FlowCard React components with better layout and information display

## [0.4.7] - 2025-11-30

### Enhanced
- **AI System Extensibility** - Improved dynamic provider and step type validation across chat and execution APIs
  - Chat API now validates AI providers dynamically using `chubes_ai_providers` filter instead of hardcoded enum
  - Execute API validates step types dynamically using `datamachine_step_types` filter instead of hardcoded array
  - Flow scheduling intervals now use dynamic validation via `datamachine_scheduler_intervals` filter

### Improved
- **PromptBuilder Architecture** - Streamlined directive application process with simplified build method
  - Removed verbose directive tracking and logging for cleaner code execution
  - Maintained core functionality while reducing code complexity
  - Improved performance through reduced overhead in directive processing

### Technical Details
- **Code Reduction**: -31 lines net change in PromptBuilder.php for improved maintainability
- **API Flexibility**: Dynamic validation enables easier extension of providers, step types, and scheduling intervals
- **Architecture**: Enhanced filter-based extensibility for core system components

## [0.4.6] - 2025-11-30

### Removed
- **Centralized Cache System** - Eliminated `inc/Engine/Actions/Cache.php` (329 lines) and `docs/core-system/cache-management.md` (398 lines)
  - Removed Cache action class with granular invalidation patterns
  - Removed cache management documentation and architectural patterns
  - Simplified DataMachineActions.php by removing cache registration

### Changed
- **Architecture Simplification** - Streamlined codebase through distributed caching approach
  - Maintained essential caching in PluginSettings, SiteContext, and EngineData
  - Eliminated centralized cache management in favor of component-level caching
  - Reduced code complexity and maintenance overhead

### Improved
- **Codebase Efficiency** - Net reduction of 1,602 lines across 27 files
  - Simplified database operation files and service managers
  - Consolidated documentation and removed redundant content
  - Enhanced maintainability through architectural simplification

### Technical Details
- **Code Reduction**: -1,602 lines net change across 27 modified files
- **Architecture**: Transition from centralized to distributed caching model
- **Performance**: Maintained caching benefits while reducing system complexity
- **Compatibility**: No breaking changes to APIs or user-facing functionality

## [0.4.5] - 2025-11-30

### Changed
- **ChatAgentDirective** - Simplified system prompt by removing verbose API documentation (-219 lines)
- **Chat Agent UX** - Streamlined directive from detailed handler tables to focused workflow guidance
- **System Prompt Architecture** - Shifted from comprehensive API reference to high-level workflow assistance

### Improved
- **Chat Agent Performance** - Reduced system prompt complexity for better AI agent focus
- **Documentation Separation** - API discovery now handled via `api_query` tool instead of system prompt
- **Maintainability** - Simplified directive structure easier to maintain and update

### Technical Details
- **Code Reduction**: -212 lines net change in ChatAgentDirective
- **Architecture**: Cleaner separation between system guidance and API discovery
- **Focus**: Chat agent now emphasizes workflow configuration over API documentation

## [0.4.4] - 2025-11-29

### Added
- **ConfigurePipelineStep Chat Tool** - Specialized tool for configuring pipeline-level AI step settings including system prompt, provider, model, and enabled tools
- **RunFlow Chat Tool** - Dedicated tool for executing existing flows immediately or scheduling delayed execution with proper validation
- **UpdateFlow Chat Tool** - Focused tool for updating flow-level properties including title and scheduling configuration

### Enhanced
- **ChatAgentDirective** - Updated system prompt documentation to include new specialized tools and improved workflow patterns
- **ApiQuery Tool** - Enhanced REST API query tool with comprehensive endpoint documentation for better discovery
- **ExecuteWorkflowTool** - Improved workflow execution with better error handling and response formatting
- **DocumentationBuilder** - Enhanced dynamic documentation generation from registered handlers

### Improved
- **Chat Tool Architecture** - Expanded specialized tool ecosystem for better AI agent performance and task separation
- **Tool Validation** - Added comprehensive parameter validation across all new chat tools
- **Error Handling** - Standardized error responses and success messages across new tools
- **Documentation** - Updated tool descriptions and parameter documentation for clarity

### Technical Details
- **Tool Specialization**: 3 new specialized tools added to chat agent toolkit
- **Code Addition**: +447 lines of new specialized chat tool functionality
- **Enhanced Capabilities**: Better AI agent performance through focused, operation-specific tools

## [0.4.3] - 2025-11-29

### Added
- **Specialized Chat Tools System** - Complete refactoring replacing generic MakeAPIRequest with focused, operation-specific tools:
  - **AddPipelineStep Tool** - Adds steps to existing pipelines with automatic flow synchronization
  - **ApiQuery Tool** - Dedicated REST API query tool with comprehensive endpoint documentation
  - **CreatePipeline Tool** - Enhanced pipeline creation with optional predefined steps
  - **CreateFlow Tool** - Streamlined flow creation from existing pipelines
  - **ConfigureFlowStep Tool** - Focused tool for configuring handlers and AI messages

### Changed
- **Chat Agent Architecture** - Migrated from generic API tool to specialized tools for improved AI agent performance
- **Tool Separation of Concerns** - Clear division between workflow operations and API management
- **Composer Autoload Configuration** - Updated to include new specialized tools

### Removed
- **MakeAPIRequest Tool** - Eliminated generic tool in favor of specialized, focused tools

### Technical Details
- **Tool Specialization**: 5 specialized tools replace 1 generic tool for better operation accuracy
- **Code Optimization**: Net +400 lines for dramatically improved AI agent capabilities

## [0.4.2] - 2025-11-29

### Added
- **CreateFlow Chat Tool** - Specialized tool for creating flow instances from existing pipelines with automatic step synchronization
  - Validates pipeline_id and scheduling configuration
  - Returns flow_step_ids for subsequent configuration
  - Supports all scheduling intervals (manual, hourly, daily, weekly, monthly, one_time)
- **ConfigureFlowStep Chat Tool** - Focused tool for configuring flow step handlers and AI user messages
  - Supports handler configuration for fetch/publish/update steps
  - Supports user_message configuration for AI steps
  - Uses flow_step_ids returned from create_flow tool

### Enhanced
- **Chat API Response Structure** - Added completion status and warning system
  - Added `completed` field to indicate conversation completion status
  - Added `warning` field for conversation turn limit notifications
  - Improved response data organization for better client handling

### Changed
- **MakeAPIRequest Tool Documentation** - Updated to reference specialized tools for cleaner separation of concerns
  - Removed flow creation and step configuration endpoints from tool scope
  - Added clear references to create_flow and configure_flow_step tools
  - Simplified endpoint documentation to focus on monitoring and management operations

### Improved
- **Chat Tool Architecture** - Better specialization with dedicated tools for specific workflow operations
- **Conversation Management** - Enhanced warning system when maximum conversation turns are reached
- **Tool Separation** - Clearer division between workflow creation/configuration (specialized tools) and API management (MakeAPIRequest)

## [0.4.1] - 2025-11-29

### Added
- **DynamicToolProvider Base Class** - New abstract base class for engine-aware tool parameter providers
  - Prevents AI from requesting parameters that already exist in engine data
  - Centralized pattern for dynamic tool parameter filtering

### Enhanced
- **ToolExecutor** - Added engine_data parameter to getAvailableTools() for dynamic tool generation
- **HandlerRegistrationTrait** - Updated AI tools filter registration to pass 4 parameters (added engine_data)
- **AIStep** - Enhanced to pass engine data to tool executor for better tool availability determination

### Technical Details
- **AI Tool Intelligence**: Tools now dynamically adjust parameters based on existing engine data values
- **Parameter Filtering**: Prevents redundant parameter requests in AI conversations
- **Engine Awareness**: Tool system now considers workflow context for parameter requirements

## [0.4.0] - 2025-11-29

### BREAKING CHANGES
- **Complete Service Layer Migration** - Replaced filter-based action system with direct OOP service manager architecture
  - Removed `inc/Engine/Actions/Delete.php` (581 lines) - Use `DataMachine\Services\*Manager` classes instead
  - Removed `inc/Engine/Actions/Update.php` (396 lines) - Use dedicated service managers
  - Removed `inc/Engine/Actions/FailJob.php` (94 lines) - Integrated into service managers
  - Removed `inc/Engine/Filters/Create.php` (540 lines) - Use service manager create methods
  - **Note**: All REST API endpoints maintain identical signatures - no breaking changes for frontend consumers

### Added
- **Services Layer Architecture** (`/inc/Services/`) - New centralized business logic with 7 dedicated manager classes:
  - **FlowManager** - Complete flow CRUD operations with validation and error handling
  - **FlowStepManager** - Flow step management with pipeline synchronization
  - **PipelineManager** - Pipeline operations with cascade handling
  - **PipelineStepManager** - Pipeline step management and ordering
  - **JobManager** - Job lifecycle management and status tracking
  - **LogsManager** - Centralized logging operations with filtering
  - **ProcessedItemsManager** - Processed items tracking and cleanup operations
- **Enhanced React Components** - Improved form handling and modal management:
  - **HandlerSettingField.jsx** - Better form validation and state management
  - **HandlerSettingsModal.jsx** - Enhanced modal architecture with cleaner state handling
  - **FlowCard.jsx & FlowHeader.jsx** - Improved UI consistency and interaction patterns

### Changed
- **API Layer Refactoring** - All REST endpoints now use service managers instead of filter indirection:
  - Direct service instantiation: `$manager = new FlowManager()`
  - Eliminated filter-based action calls: `do_action('datamachine_delete_flow', $flow_id)`
  - Improved error handling with proper WP_Error objects
  - Enhanced input validation and sanitization
- **Performance Optimization** - 3x faster execution paths through direct method calls vs filter resolution
- **Code Organization** - Centralized business logic in dedicated service classes following single responsibility principle
- **Enhanced FetchHandler** - Improved base class with better error handling and logging
- **Streamlined Engine Actions** - Removed redundant cache management and simplified action handling

### Improved
- **Type Safety** - Properly typed method signatures with return type declarations
- **Debugging** - Direct method calls provide clearer stack traces vs filter chain execution
- **Testing** - Service classes enable easier unit testing and mocking
- **Documentation** - Self-documenting service methods with comprehensive PHPDoc blocks
- **Memory Efficiency** - Service instances instantiated only when needed vs persistent filter hooks
- **Error Handling** - Consistent error patterns across all service managers

### Technical Details
- **Code Reduction**: Eliminated 1,611 lines of legacy filter-based code
- **Code Addition**: Added 1,901 lines of clean, maintainable service layer code
- **Net Change**: +290 lines for dramatically better architecture
- **Performance**: Direct method invocation replaces WordPress filter indirection
- **Compatibility**: All REST API endpoints maintain identical signatures for backward compatibility
- **Architecture**: Migrated from WordPress hook-based patterns to clean OOP service architecture where appropriate

### Documentation
- Updated 15+ documentation files to reflect service layer architecture
- Enhanced API documentation for auth and chat endpoints
- Improved fetch-handler and taxonomy-handler documentation
- Updated React component documentation for new patterns

## [0.3.1] - 2025-11-26

### Added
- **Auth API** - Added `requires_auth` check in `inc/Api/Auth.php` to bypass authentication validation for handlers that don't require it (e.g., public scrapers).
- **FetchHandler** - Added `applyExcludeKeywords()` method to base `FetchHandler` class for negative keyword filtering.

### Changed
- **Job Data Handling** - Changed `JobsOperations::get_job()` to return `ARRAY_A` (associative array) instead of object, and updated `retrieve_engine_data()` to match.
- **WordPress Publishing** - Simplified `WordPressPublishHelper::applySourceAttribution()` to use standard HTML paragraph tags instead of Gutenberg separator blocks.
- **Source Attribution** - Removed `generateSourceBlock()` method in favor of cleaner HTML output.

## [0.3.0] - 2025-11-26

### Added
- **ExecuteWorkflow Tool** (`/inc/Api/Chat/Tools/ExecuteWorkflow/`) - New specialized chat tool for workflow execution with modular architecture:
  - **ExecuteWorkflowTool.php** - Main tool class, registers as `execute_workflow` chat tool with simplified step parameter structure
  - **DocumentationBuilder.php** - Dynamically builds tool description from registered handlers via `datamachine_handlers` filter
  - **WorkflowValidator.php** - Validates step structure, handler existence, and step type correctness before execution
  - **DefaultsInjector.php** - Injects provider/model/post_author defaults from plugin settings automatically
- **Dynamic Handler Documentation** - Tool descriptions now auto-generate from registered handlers, ensuring documentation stays in sync with actual capabilities

### Changed
- **ChatAgentDirective Refactoring** - Slimmed from ~450 lines to ~255 lines with pattern-based approach:
  - Handler selection tables replace verbose endpoint documentation
  - Taxonomy configuration pattern with clear three-mode options (skip, pre-selected, ai_decides)
  - Strategic guidance for ephemeral vs persistent workflow decisions
- **MakeAPIRequest Tool** - Enhanced with comprehensive API documentation for pipeline/flow management, monitoring, and troubleshooting (excludes `/execute` endpoint now handled by `execute_workflow`)
- **TaxonomyHandler Backend** - `processPreSelectedTaxonomy()` now accepts term name/slug instead of requiring numeric ID, enabling direct use of term names from site context

### Improved
- **Tool Separation of Concerns** - Clear division between workflow execution (`execute_workflow`) and API management (`make_api_request`)
- **Chat Agent Architecture** - Extensible pattern for adding new specialized tools without bloating the system directive

## [0.2.10] - 2025-11-25

### Added
- **PluginSettings Class** (`/inc/Core/PluginSettings.php`) - Centralized settings accessor with request-level caching
  - `PluginSettings::all()` - Retrieve all plugin settings with automatic caching
  - `PluginSettings::get($key, $default)` - Type-safe getter for individual settings
  - `PluginSettings::clearCache()` - Manual cache invalidation (auto-clears on option update)
- **EngineData::getPipelineStepConfig()** - New method to retrieve configuration for specific pipeline steps, distinguishing between pipeline-level AI settings and flow-level overrides
- **Cache Management Documentation** (`/docs/core-system/cache-management.md`) - Comprehensive documentation for the cache system including action-based invalidation patterns
- **Logger Documentation** (`/docs/core-system/logger.md`) - Comprehensive documentation for the Monolog-based logging system

### Changed
- **Settings Access Architecture** - Migrated from scattered `get_option('datamachine_settings')` calls to centralized `PluginSettings::get()` pattern across 15+ files:
  - API layer: Chat.php, Providers.php, Settings.php
  - Engine layer: AIStep.php, ToolManager.php, FailJob.php
  - Directives: GlobalSystemPromptDirective.php, SiteContextDirective.php
  - Core services: WordPressSettingsResolver.php, FileCleanup.php
- **Social Media Handler Defaults** - Changed `include_images` default from `true` to `false` for Twitter, Bluesky, and Threads publish handlers (aligns with Facebook handler behavior)
- **FlowSteps API** - Simplified settings extraction logic to handle empty/null settings gracefully without unnecessary conditional branches
- **AIStep Provider Resolution** - Now falls back to default provider from PluginSettings when pipeline step provider is not configured, with improved error messaging

### Fixed
- **Settings Option Key Typo** - Corrected `job_data_cleanup_on_failure` to `cleanup_job_data_on_failure` in activation defaults

### Removed
- **Redundant Debug Logging** - Removed verbose handler configuration logging from Bluesky and Threads publish handlers that provided no diagnostic value

### Documentation
- Updated 45+ documentation files to reflect v0.2.10 changes
- Added comprehensive EngineData documentation for `getPipelineStepConfig()` method
- Enhanced engine-data.md with pipeline vs flow configuration distinction

## [0.2.9] - 2025-11-25

### Removed
- **Legacy AutoSave Hook** - Completely removed the redundant `datamachine_auto_save` action hook and AutoSave.php class. The REST API architecture now handles all persistence directly, eliminating duplicate database writes and improving performance.

### Changed
- **Delete Step Execution Order Sync** - Fixed execution_order synchronization gap in pipeline step deletion by implementing inline sync logic, ensuring flow steps maintain correct execution order after step removal.
- **Code Cleanup** - Removed all AutoSave references from core action registrations and documentation.
- **UI Form Validation** - Simplified form validation logic in ConfigureStepModal and FlowScheduleModal by removing unnecessary hasChanged checks, improving user experience and reducing code complexity.

### Technical
- **Performance Improvement** - Eliminated redundant database operations that were causing unnecessary writes during pipeline modifications.
- **Architecture Simplification** - Streamlined persistence logic by removing legacy hook-based approach in favor of direct REST API handling.

## [0.2.8] - 2025-11-25

### Added
- **Standardized Publish Fields** - Added `WordPressSettingsHandler::get_standard_publish_fields()` to centralize common WordPress publishing settings configuration.

### Changed
- **WordPress Settings Refactoring** - Updated `WordPressSettings` to use the new centralized standard fields method, reducing code duplication.
- **OAuth Modal UX** - Improved authentication modal to actively fetch and sync connection status, ensuring the UI always reflects the true backend state.
- **Date Formatting** - Moved date formatting logic from frontend (JS) to backend (PHP) for Jobs and Flows, ensuring consistency and respecting WordPress settings.

### Documentation
- **Architecture Updates** - Comprehensive updates to documentation reflecting v0.2.7 architectural changes (EngineData, WordPressPublishHelper).
- **Link Fixes** - Resolved broken internal links across handler documentation.

## [0.2.7] - 2025-11-24

### BREAKING CHANGES
- **EngineData API** - Removed WordPress-specific methods violating platform-agnostic architecture
  - Removed `EngineData::attachImageToPost()` (use `WordPressPublishHelper::attachImageToPost()` instead)
  - Removed `EngineData::applySourceAttribution()` (use `WordPressPublishHelper::applySourceAttribution()` instead)
  - Removed private `EngineData::generateSourceBlock()` method
- **Removed WordPressSharedTrait** - Extensions using this trait must migrate to direct EngineData usage and WordPressSettingsResolver
  - Affected: datamachine-events v0.3.0 and earlier
  - Fixed in: datamachine-events v0.3.1+

### Added
- **WordPressPublishHelper** - New centralized WordPress publishing utilities (`/inc/Core/WordPress/WordPressPublishHelper.php`)
  - `WordPressPublishHelper::attachImageToPost($post_id, $image_path, $config)` - Attach images to WordPress posts
  - `WordPressPublishHelper::applySourceAttribution($content, $source_url, $config)` - Apply source URL attribution
  - `WordPressPublishHelper::generateSourceBlock($url)` - Generate Gutenberg source blocks
- **WordPressSettingsResolver** (`/inc/Core/WordPress/WordPressSettingsResolver.php`) - Centralized utility for WordPress settings resolution with system defaults override
  - `getPostStatus()` - Single source of truth for post status resolution
  - `getPostAuthor()` - Single source of truth for post author resolution
  - Eliminates ~120 lines of duplicated code across handlers

### Removed
- **WordPressSharedTrait** (`/inc/Core/WordPress/WordPressSharedTrait.php`) - Eliminated architectural bloat by removing trait wrapper layer

### Changed
- **WordPress Publish Handler** - Migrated to use `WordPressPublishHelper` for image and source attribution operations
- **WordPress Update Handler** - Refactored to use direct EngineData instantiation
- **EngineData Architecture** - Restored platform-agnostic design, now provides only data access methods (`getImagePath()`, `getSourceUrl()`)
- **Single Source of Truth Architecture** - All handlers now use direct EngineData pattern for consistent, predictable data access
- **EngineData Configuration Keys** - Updated parameter names for consistency
  - `include_source` → `link_handling` (values: 'none', 'append')
  - `enable_images` → `include_images`
- **Handlers API** (`/inc/Api/Handlers.php`) - Enhanced with authentication status
  - Added `is_authenticated` boolean to handler metadata
  - Added `account_details` when authenticated
- **TaxonomyHandler** - Enhanced taxonomy retrieval with post type filtering
  - `getPublicTaxonomies()` now accepts optional `$post_type` parameter
- **WordPressSettingsHandler** - Added post type and exclude taxonomies support
  - Taxonomy fields now support `post_type` and `exclude_taxonomies` configuration options

### Improved
- **Architectural Consistency** - EngineData now matches pattern used by all social media handlers (Twitter, Threads, Bluesky, Facebook)
- **Single Responsibility** - WordPress-specific operations centralized in dedicated helper class
- **KISS Principle** - Eliminated WordPress dependencies from core data container class
- **UI/UX** - Pipeline Builder horizontal scrolling layout for better step navigation
- **CSS Architecture** - Moved common UI components to centralized `/inc/Core/Admin/assets/css/root.css`
  - Status badge styles centralized
  - Common admin notice component styles added
  - Hidden utility class added
- **Empty State Actions** - Added action buttons to empty state displays

### Documentation
- **WordPressSharedTrait Documentation** - Updated to mark as removed with migration guide
- **EngineData Documentation** - Removed dual-path "or via trait" references, established single direct usage pattern
- **Architecture Documentation** - Updated to reflect removal of WordPressSharedTrait and direct EngineData integration
- **WordPress Components Documentation** - Updated integration patterns to show direct EngineData usage
- **Featured Image Handler Documentation** - Removed trait wrapper references
- **Source URL Handler Documentation** - Removed trait wrapper references

## [0.2.6] - 2025-11-23

### Added
- **Base Authentication Provider Architecture** (`/inc/Core/OAuth/`) - Complete authentication provider inheritance system
  - **BaseAuthProvider** - Abstract base for all auth providers with option storage/retrieval (@since 0.2.6)
  - **BaseOAuth1Provider** - Base class for OAuth 1.0a providers extending BaseAuthProvider (@since 0.2.6)
  - **BaseOAuth2Provider** - Base class for OAuth 2.0 providers extending BaseAuthProvider (@since 0.2.6)

- **React Architecture Enhancements** (`/datamachine/src/`) - Advanced state management patterns
  - **HandlerModel.js** - Abstract model layer for handler data operations
  - **HandlerFactory.js** - Factory pattern for handler model instantiation
  - **useHandlerModel.js** - Custom hook for handler model integration
  - **ModalSwitch.jsx** - Centralized modal routing component
  - **HandlerProvider.jsx** - React context for handler state management

### Changed
- **OAuth Provider Migration** - All authentication providers now extend base classes
  - TwitterAuth extends BaseOAuth1Provider (migrated from custom implementation)
  - RedditAuth extends BaseOAuth2Provider (migrated from custom implementation)
  - FacebookAuth extends BaseOAuth2Provider (migrated from custom implementation)
  - ThreadsAuth extends BaseOAuth2Provider (migrated from custom implementation)
  - BlueskyAuth extends BaseAuthProvider (migrated from custom implementation)
  - GoogleSheetsAuth extends BaseOAuth2Provider and moved to `/inc/Core/OAuth/Providers/` directory
- **Flow Ordering** - Changed default flow sorting from newest-first to oldest-first (ASC) to ensure new flows appear at the bottom of the list
- **Pipeline Builder React Architecture** - Modernized state management and component patterns
  - Implemented model-view separation pattern for handler state management
  - Added service layer abstraction for handler-related API operations
  - Centralized modal rendering through ModalSwitch component
  - Enhanced component directory structure with models/, services/, context/ directories

### Improved
- **Code Consistency** - Unified authentication patterns across all providers through base class inheritance
- **Maintainability** - Centralized option storage logic eliminates duplication across providers
- **Extensibility** - New authentication providers integrate easily via base class extension

### Fixed
- **Taxonomy Handling** - Resolved bug in taxonomy processing and removed redundant filter registrations
- **WordPressAPI Type Safety** - Fixed TypeError by ensuring fetch_from_endpoint returns array not null
- **Engine Data Architecture** - Implemented single source of truth for execution context, removed redundant methods

### Documentation
- **EngineData Documentation** - Created comprehensive documentation for EngineData class consolidating featured image and source URL operations
- **Deprecated Handler Documentation** - Updated FeaturedImageHandler and SourceUrlHandler documentation to reflect deprecation and migration to EngineData
- **WordPress Components Documentation** - Updated to reflect v0.2.6 architecture with EngineData consolidation
- **OAuth Handlers Documentation** - Removed BaseSimpleAuthProvider references (class was never implemented), updated to reflect 3-class base architecture
- **Architecture Documentation** - Updated core architecture documentation to reflect EngineData consolidation and component evolution
- **Internal Link Cleanup** - Removed internal .md links throughout documentation per WordPress navigation handling requirements

## [0.2.5] - 2025-11-20

### Added
- **PromptBuilder.php**: New unified directive management system for AI requests
  - Centralized directive injection with priority-based ordering
  - Replaces scattered filter applications with structured builder pattern
  - Ensures consistent prompt structure across all AI agent types

### Changed
- **RequestBuilder.php**: Updated to integrate PromptBuilder for directive application
  - Streamlined AI request construction with centralized directive management
  - Improved consistency between Chat and Pipeline agent request building

## [0.2.4] - 2025-11-20

### Added
- **FlowScheduling.php**: New dedicated API endpoint for advanced flow scheduling operations
- **ModalManager.jsx**: Centralized modal rendering system for improved UI consistency
- **useFormState.js**: Generic form state management hook for React components
- **FailJob.php**: Dedicated action class for handling job failure scenarios
- **WordPressSharedTrait** (`/inc/Core/WordPress/WordPressSharedTrait.php`) - Shared functionality trait for WordPress handlers with content updates, taxonomy processing, and image handling (removed in v0.2.7)

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