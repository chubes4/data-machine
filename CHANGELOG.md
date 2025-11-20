# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.4] - 2025-11-20

### Added
- **Modernized React State Management**: Complete migration from context-based state to TanStack Query (server state) + Zustand (UI state)
- **Performance Improvements**: Eliminated global re-renders, implemented granular component updates with intelligent caching
- **Enhanced User Experience**: Optimistic UI updates, better error handling, and cleaner state separation
- **Architecture Cleanup**: Removed legacy context system, streamlined component architecture, and improved maintainability
- **Code Standardization**: Fixed React bugs, standardized patterns across components, and improved code quality
- **API Layer Enhancements**: Server-side single source of truth with improved API access patterns
- **Handler Registration**: Standardized handler registration using HandlerRegistrationTrait across all handlers
- **Tool Management**: Centralized tool management with ToolRegistrationTrait for extensible agent support
- **Directive System**: Cleaned up legacy compatibility code and simplified directive architecture
- **WordPress Integration**: Simplified WordPress settings and removed overengineered global defaults logic
- **New Files**: Added .eslintrc.js for React code linting, FailJob.php for job failure handling

### Changed
- **React Components**: Updated 50+ React components for new state management architecture
- **API Endpoints**: Streamlined API layer with improved access patterns
- **Handler Architecture**: Migrated all handlers to use standardized registration traits
- **Directive System**: Simplified and cleaned up legacy compatibility code
- **WordPress Settings**: Removed overengineered global defaults logic

### Removed
- **Legacy Context System**: Removed old React context-based state management
- **Redundant API Code**: Cleaned up duplicate API endpoint implementations
- **Legacy Compatibility**: Removed backward compatibility code from directive system
- **Overengineered Settings**: Simplified WordPress handler settings logic

### Fixed
- **React Bugs**: Fixed various React component bugs and standardized patterns
- **State Management**: Resolved issues with global re-renders and state synchronization
- **API Access**: Improved server-side single source of truth implementation

## [0.2.3] - 2025-11-19

### Added
- **Complete REST API Migration**: All AJAX endpoints converted to REST API (16 endpoints)
- **Modern React Admin Interface**: Full Pipelines page rebuild with 6,591 lines of React code
- **TanStack Query Integration**: Server state management for optimal performance
- **Zustand State Management**: Client-side state management for UI components
- **Universal Engine Architecture**: Shared AI infrastructure for Pipeline and Chat agents
- **Filter-Based Service Discovery**: Auto-registration system for all components
- **Centralized OAuth Handlers**: Unified OAuth1 and OAuth2 implementations
- **Tool-First AI Integration**: Multi-provider AI support with conversation management
- **Visual Pipeline Builder**: Real-time updates with modern React components
- **Chat API**: Conversational interface for workflow building
- **Schedule API**: Recurring and one-time flow scheduling
- **Ephemeral Workflows**: Execute workflows without database persistence

### Changed
- **Architecture**: Major refactoring with base classes and modular components
- **Frontend**: Zero jQuery/AJAX dependencies, complete REST API integration
- **State Management**: Modern TanStack Query + Zustand architecture
- **Handler Registration**: Standardized traits for all handlers and tools
- **Directive System**: Hierarchical AI directive application (global → agent → type-specific)

### Removed
- **Legacy AJAX**: All admin-ajax.php endpoints eliminated
- **jQuery Dependencies**: Complete removal of jQuery from admin interface
- **Legacy Context**: Old React context system replaced
- **Redundant Code**: Eliminated code duplication through inheritance

### Fixed
- **Performance**: 50% query reduction in handler settings operations
- **State Synchronization**: Improved flow and pipeline state management
- **Error Handling**: Better error handling throughout the application
- **Code Quality**: Fixed React bugs and standardized component patterns

## [0.2.2] - 2025-11-15

### Added
- **Server-Side Single Source of Truth**: API as access layer for all operations
- **Tool Registration Trait**: Extensible agent support infrastructure
- **Fatal Error Fixes**: Resolved tool registration trait issues

### Changed
- **Architecture**: Improved separation between server and client state
- **API Layer**: Enhanced access patterns and error handling

### Fixed
- **Tool Registration**: Fixed fatal errors with trait implementation
- **State Management**: Better synchronization between components

## [0.2.1] - 2025-11-10

### Added
- **Base Class Architecture**: Unified payload handling across all step types
- **Modular Components**: FilesRepository and WordPress shared components
- **Centralized Engine Data**: Unified access to workflow parameters
- **Universal Handler Filters**: Shared functionality across handlers
- **AutoSave System**: Complete pipeline persistence and flow synchronization

### Changed
- **Handler Architecture**: Standardized base classes for all handler types
- **Component Structure**: Modular design reducing code duplication
- **Data Access**: Centralized engine data filter system

### Removed
- **Code Duplication**: Eliminated redundant implementations
- **Legacy Patterns**: Cleaned up scattered array constructions

## [0.2.0] - 2025-11-01

### Added
- **REST API Implementation**: 16 core endpoints for complete API coverage
- **OAuth Architecture**: Centralized OAuth handlers for all providers
- **Multi-Provider AI**: OpenAI, Anthropic, Google, Grok, OpenRouter support
- **Pipeline+Flow Architecture**: Reusable templates with configured instances
- **Visual Pipeline Builder**: React-based interface for workflow creation
- **Chat Interface**: Conversational AI for workflow building
- **Tool Integration**: Google Search, Local Search, WebFetch, WordPress tools
- **Handler Ecosystem**: Fetch, Publish, Update handlers with auto-discovery

### Changed
- **Architecture**: Complete rewrite with modern patterns
- **Frontend**: React-based admin interface
- **Backend**: PSR-4 autoloading with Composer dependencies

### Removed
- **Legacy Code**: Complete architectural overhaul

## [0.1.0] - 2025-10-01

### Added
- Initial release
- Basic WordPress plugin structure
- Core functionality foundation