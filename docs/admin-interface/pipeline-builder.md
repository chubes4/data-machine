# Pipeline Builder Interface

Visual drag-and-drop interface for creating and managing Pipeline+Flow configurations with real-time step arrangement, handler selection, and configuration.

## Pipeline Management

**Pipeline Selection**: Dropdown selector with user preference persistence and automatic selection of newest pipeline when none specified.

**Add New Pipeline**: Creates new pipeline templates with configurable step sequences and handler assignments.

**Import/Export**: Built-in pipeline configuration import/export functionality for sharing and backup workflows.

## Visual Interface Components

**Pipeline Cards**: Visual representation of pipeline templates showing step sequence, handler types, and configuration status.

**Flow Instance Cards**: Display configured flow instances with scheduling information, status indicators, and execution controls.

**Step Selection**: Modal interface for choosing step types (fetch, AI, publish, update) with contextual handler options.

## Step Configuration

**Handler Selection**: Context-aware handler selection based on step type with visual cards showing available options and their capabilities.

**Configuration Modals**: Universal handler settings template system eliminates code duplication with:
- Single template (`handler-settings.php`) handling all handler types
- Dynamic field rendering through Settings classes
- Context-aware authentication integration
- Input validation and real-time feedback
- Authentication status indicators
- Field-specific help text and examples
- Auto-save functionality with change tracking
- Global WordPress settings notification with direct configuration links

**Settings Validation**: Real-time validation of handler configurations with visual status indicators and error messaging.

## Flow Management

**Flow Creation**: Converts pipeline templates into configured flow instances with:
- Custom naming and descriptions
- Schedule configuration with Action Scheduler integration
- Handler-specific configuration overrides

**Schedule Configuration**: Visual scheduling interface supporting:
- One-time execution
- Recurring schedules (hourly, daily, weekly)
- Custom cron expressions
- Execution context (manual vs. automatic)

## Authentication Interface

**OAuth Integration**: Streamlined OAuth flow with popup windows for:
- Facebook/Meta (Pages API integration)
- Google (Sheets and Search Console)
- Reddit (OAuth2 with subreddit access)
- Twitter (OAuth 1.0a with posting permissions)
- Bluesky (app password authentication)

**Authentication Status**: Real-time authentication status indicators with:
- Connected account information
- Permission level validation
- Re-authentication prompts when needed
- Token expiration warnings

## AI Tool Management

**Tool Selection Modal**: Interface for enabling/disabling AI tools per pipeline step with:
- Global tool enablement controls
- Per-step tool activation checkboxes
- Configuration requirement warnings
- Tool capability descriptions

**Configuration Warnings**: Visual indicators for tools requiring additional setup:
- API key requirements
- OAuth authentication status
- Configuration completeness validation
- Setup instruction links

## Visual Feedback Systems

**Status Indicators**: Color-coded status system for:
- Green: Ready and properly configured
- Yellow: Warning or requiring attention
- Red: Error or missing configuration

**Auto-Save**: Automatic saving with visual feedback:
- Save progress indicators
- Change detection with unsaved changes warnings
- Conflict resolution for concurrent edits
- Undo/redo capability

## Modal System

**Standardized Modals**: Universal template architecture with consistent modal interface pattern:
- Single universal handler settings template eliminating code duplication
- Step configuration and editing through unified Settings class system
- Handler authentication setup with automatic detection
- Import/export operations
- Admin tools and utilities

**Context-Aware Content**: Modal content adapts based on:
- Selected step type and position
- Current authentication state
- Available handler options
- Existing configuration data

## User Experience Features

**Drag & Drop**: Visual step arrangement with live preview and validation.

**Keyboard Navigation**: Full keyboard accessibility with tab navigation and shortcut keys.

**Responsive Design**: Adapts to different screen sizes while maintaining functionality.

**Help Integration**: Contextual help tooltips and documentation links throughout interface.

## Performance Optimization

**Lazy Loading**: Dynamic loading of configuration interfaces and modal content.

**AJAX Operations**: Asynchronous operations for:
- Configuration saving
- Status updates
- Authentication flows
- Import/export operations

**Caching**: Client-side caching of configuration data and interface states.

## Error Handling

**Validation Feedback**: Real-time validation with specific error messages and correction suggestions.

**Graceful Degradation**: Fallback interfaces when JavaScript disabled or AJAX failures occur.

**Error Recovery**: Automatic retry mechanisms and user-guided error resolution workflows.