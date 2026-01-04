# Pipeline Builder Interface

Modern React-based interface for creating and managing Pipeline+Flow configurations with TanStack Query + Zustand architecture for optimal performance, real-time updates, zero page reloads, and complete REST API integration.

## Pipeline Management

**Pipeline Selection**: Dropdown selector with user preference persistence and automatic selection of newest pipeline when none specified.

**Add New Pipeline**: Creates new pipeline templates with configurable step sequences and handler assignments.

**Import/Export**: Built-in pipeline configuration import/export functionality for sharing and backup workflows.

## Architecture (@since v0.2.3)

**TanStack Query + Zustand**: Modern state management architecture providing optimal performance and user experience:

- **TanStack Query**: Server state management with intelligent caching, automatic background refetching, and optimistic updates
- **Zustand**: Client-side UI state management for modals, selections, and form data
- **Benefits**: Granular component updates, no global refreshes, better error handling, improved loading states

**Query Patterns**:
- Pipeline data: Cached queries with automatic invalidation on changes
- Flow data: Optimistic updates for immediate UI feedback
- Settings data: Background synchronization with conflict resolution

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

### Handler Settings Data Contract

Handler configuration follows a simplified request/response contract:

- **Schema Source**: `GET /datamachine/v1/handlers/{slug}` returns `handler.settings` where each field contains `type`, `label`, `default`, `description`, `options` (for select fields), and `current_value`.
- **Flow Scope Values**: All settings are stored at the flow level as flat key/value pairs in `PUT /flows/steps/{id}/handler`.
- **Settings Resolution**: Values are resolved as `saved_value > default_value` with no global overrides.

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

**Modern React Interface**: Component-based architecture with instant visual feedback and zero page reloads.

**Keyboard Navigation**: Full keyboard accessibility with tab navigation and shortcut keys.

**Responsive Design**: Adapts to different screen sizes while maintaining functionality.

**Help Integration**: Contextual help tooltips and documentation links throughout interface.

## Integrated Chat Sidebar (@since v0.8.0)

The Pipeline Builder includes a built-in React-based AI assistant accessible via a collapsible right sidebar.

**Features:**
- **Context Awareness**: Automatically passes the `selected_pipeline_id` to the AI agent, allowing it to provide specific advice and configurations for the active pipeline.
- **Session Persistence**: Chat conversations are persisted across page refreshes via session storage.
- **Workflow Automation**: The chat agent can use specialized tools to create pipelines, add steps, configure handlers, and run flows directly from the conversation.
- **UI Integration**: Toggleable sidebar that integrates seamlessly with the React application state.
- **Context-Aware Design**: Leverages `selected_pipeline_id` for targeted interactions within the React interface.

## Performance Optimization

**React-Based Performance**: Component-level optimizations with:
- Lazy loading of modal content
- Efficient re-renders via React reconciliation
- Client-side caching of configuration data
- Optimistic UI updates reducing perceived latency

**REST API Operations**: All operations use REST endpoints:
- Pipeline and flow CRUD operations
- Configuration saving and retrieval
- Status updates via `/datamachine/v1/status`
- Authentication flows
- Import/export operations

**State Management**: TanStack Query for server state management and Zustand for client-side UI state provide efficient state synchronization across components.

## Error Handling

**Validation Feedback**: Real-time validation with specific error messages and correction suggestions.

**Graceful Degradation**: Fallback interfaces when JavaScript disabled or connectivity issues occur.

**Error Recovery**: Automatic retry mechanisms and user-guided error resolution workflows.

## React Architecture

### Overview

The Pipelines page uses modern React architecture built with WordPress components, eliminating all jQuery/AJAX dependencies in favor of a clean, maintainable component-based system with complete REST API integration.

**Code Organization:**
- Comprehensive React implementation
- Extensive component library with specialized components

### Component Structure

**Core Components:**

- `PipelinesApp.jsx` - Root application component managing global application state
- Zustand stores for client-side state management
- Custom hooks for data fetching and state management:
  - `usePipelines` - Pipeline data and operations
  - `useFlows` - Flow instance management
  - `useStepTypes` - Available step types discovery
  - `useHandlers` - Handler discovery and configuration
  - `useStepSettings` - Step configuration management
  - `useModal` - Modal state and operations

**Card Components:**

- `PipelineCard` - Pipeline template visualization with step display
- `FlowCard` - Flow instance display with scheduling and status
- `PipelineStepCard` - Individual pipeline step cards with handler info
- `FlowStepCard` - Configured flow step display with settings
- `EmptyStepCard` - Add new step interface
- `EmptyFlowCard` - Create new flow interface

**Modal Components:**

- `ConfigureStepModal` - AI configuration, system prompts, and tool selection
- `HandlerSettingsModal` - Handler-specific configuration with dynamic field rendering
- `OAuthAuthenticationModal` - OAuth provider authentication with popup handling
- `StepSelectionModal` - Step type selection interface
- `HandlerSelectionModal` - Handler selection with capability display
- `FlowScheduleModal` - Flow scheduling configuration
- `ImportExportModal` - Pipeline import/export operations with CSV handling

**Shared Components:**

- `LoadingSpinner` - Loading state visualization
- `StepTypeIcon` - Step type icons with consistent styling
- `DataFlowArrow` - Visual data flow indicators between steps
- `PipelineSelector` - Pipeline selection dropdown with preferences
- `ModalManager` - Centralized modal rendering logic (@since v0.2.3)
- `ModalSwitch` - Centralized modal routing component (@since v0.2.5)

**Specialized Sub-Components:**

- OAuth components: `ConnectionStatus`, `AccountDetails`, `APIConfigForm`, `OAuthPopupHandler`
- Files handler components: `FilesHandlerSettings`, `FileUploadInterface`, `FileStatusTable`, `AutoCleanupOption`
- Configure step components: `AIToolsSelector`, `ToolCheckbox`, `ConfigurationWarning`
- Import/export components: `ImportTab`, `ExportTab`, `CSVDropzone`, `PipelineCheckboxTable`
- File management components: `FileUploadDropzone`, `FileStatusTable`, `AutoCleanupOption`

### State Management

**Server State (TanStack Query):**
- Automatic data fetching and caching
- Background refetching for real-time updates
- Optimistic updates for instant UI feedback
- Intelligent cache invalidation

**Client State (Zustand):**
- Modal state management
- Selected pipeline tracking
- UI interaction states
- Form state management

**Custom Hooks Pattern:**

All data operations use custom hooks that provide:
- Automatic loading states
- Error handling with WordPress notices
- Data caching
- Real-time updates
- Optimistic UI updates

**Available Hooks:**
- `usePipelines` - Pipeline data and operations
- `useFlows` - Flow instance management
- `useStepTypes` - Available step types discovery
- `useHandlers` - Handler discovery and configuration
- `useStepSettings` - Step configuration management
- `useModal` - Modal state and operations
- `useFormState` - Generic form state management (@since v0.2.3)
- `useHandlerModel` - Handler model integration (@since v0.2.5)

Example hook structure:
```javascript
const { pipelines, loading, error, refetch } = usePipelines();
const { flows, createFlow, deleteFlow, duplicateFlow } = useFlows(pipelineId);
const { data, updateField, handleSubmit, isSubmitting } = useFormState({
  initialData: {},
  validate: validateForm,
  onSubmit: submitForm
});
```

### REST API Integration

**Complete REST API Usage:**

All operations consume REST API endpoints with zero jQuery/AJAX dependencies:

*Pipeline Operations:*
- `GET /datamachine/v1/pipelines` - Fetch pipelines list
- `POST /datamachine/v1/pipelines` - Create pipeline
- `DELETE /datamachine/v1/pipelines/{id}` - Delete pipeline
- `POST /datamachine/v1/pipelines/{id}/steps` - Add step
- `DELETE /datamachine/v1/pipelines/{id}/steps/{step_id}` - Remove step

*Flow Operations:*
- `POST /datamachine/v1/flows` - Create flow
- `DELETE /datamachine/v1/flows/{id}` - Delete flow
- `POST /datamachine/v1/flows/{id}/duplicate` - Duplicate flow
- `GET /datamachine/v1/flows/{id}/config` - Flow configuration
- `GET /datamachine/v1/flows/steps/{flow_step_id}/config` - Flow step config

*Status Operations:*
- `GET /datamachine/v1/status` - Flow and pipeline status with query batching

**Authentication:**
All requests use WordPress REST API nonce from `wpApiSettings.nonce` with `manage_options` capability validation.

### Benefits of React Architecture

**User Experience:**
- Zero page reloads for all operations
- Instant visual feedback
- Optimistic UI updates
- Real-time status updates
- Modern, responsive interface

**Developer Experience:**
- Component reusability
- Clear separation of concerns
- Testable code structure
- Maintainable state management
- Type-safe operations (via PropTypes)

**Performance:**
- Client-side caching
- Efficient re-renders via React optimization
- Lazy loading of modal content
- Reduced server load (REST API vs repeated page loads)

**Extensibility:**
- Easy to add new features
- Filter-based handler discovery
- Dynamic field rendering
- Plugin-friendly architecture

### Migration Impact

**Eliminated Code:**


**Simplified Maintenance:**
- Single responsibility components
- Declarative UI rendering
- Centralized state management
- Consistent error handling patterns

## Advanced Architecture Patterns (@since v0.2.5)

### Model-View Pattern

The Pipelines interface implements a model-view separation pattern for handler state management:

**HandlerProvider** (`context/HandlerProvider.jsx`):
- React context providing handler state across components
- Centralizes handler selection and configuration state
- Reduces prop drilling for handler-related data

**HandlerModel** (`models/HandlerModel.js`):
- Abstract model layer for handler data operations
- Provides consistent interface for handler state management
- Separates business logic from UI components

**HandlerFactory** (`models/HandlerFactory.js`):
- Factory pattern for handler model instantiation
- Creates appropriate handler models based on handler type
- Centralizes handler model creation logic

**Individual Handler Models** (`models/handlers/`):
- Type-specific handler models (e.g., TwitterHandlerModel, GoogleSheetsHandlerModel)
- Encapsulate handler-specific behavior and validation
- Provide handler-specific methods and computed properties

### Service Layer Architecture

**handlerService** (`services/handlerService.js`):
- Service abstraction for handler-related API operations
- Separates API communication from component logic
- Provides reusable handler operation methods
- Centralizes error handling for handler operations

**Benefits**:
- Clear separation between API calls and UI logic
- Testable service layer independent of components
- Consistent error handling patterns
- Easy to mock for testing

### Modal Management System

**ModalSwitch** (`components/shared/ModalSwitch.jsx`):
- Centralized modal rendering component
- Routes modal types to appropriate modal components
- Replaces scattered conditional modal logic
- Single source of truth for modal rendering

**Pattern**:
```javascript
// Before: Multiple conditional modal renders scattered in components
{showHandlerModal && <HandlerSettingsModal />}
{showConfigModal && <ConfigureStepModal />}
{showOAuthModal && <OAuthAuthenticationModal />}

// After: Single centralized modal switch
<ModalSwitch activeModal={activeModal} />
```

**Benefits**:
- Reduced code duplication
- Easier to add new modal types
- Centralized modal state management
- Consistent modal behavior

### Component Directory Structure

```
assets/react/
├── context/              # React context providers
│   └── HandlerProvider.jsx
├── models/               # Handler models & factory
│   ├── HandlerModel.js
│   ├── HandlerFactory.js
│   └── handlers/         # Type-specific models
├── services/             # API service layer
│   └── handlerService.js
├── hooks/                # Custom React hooks
│   ├── useHandlerModel.js
│   └── useFormState.js
├── queries/              # TanStack Query definitions
│   ├── flows.js
│   └── handlers.js
├── stores/               # Zustand stores
│   └── modalStore.js
└── components/           # React components
    ├── modals/
    ├── flows/
    ├── pipelines/
    └── shared/
```

### Pattern Benefits

**Model-View Separation**:
- Business logic isolated from UI rendering
- Easier testing of handler operations
- Reusable handler logic across components

**Service Layer**:
- API calls abstracted from components
- Consistent error handling patterns
- Easy to switch API implementations

**Centralized Modal Management**:
- Single modal rendering location
- Reduced conditional logic in components
- Easier modal state debugging

**Custom Hooks**:
- Reusable state management logic
- Consistent data fetching patterns
- Simplified component logic

**Implemented Features:**
React architecture provides modern features including:
- Drag-and-drop step reordering (implemented)
- Real-time collaboration (possible)
- Advanced validation UI (easy to implement)
- Undo/redo functionality (state-based)
- Keyboard shortcuts (event-based)