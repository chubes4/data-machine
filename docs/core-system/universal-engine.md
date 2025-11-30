# Universal Engine Architecture

**Since**: 0.2.0

The Universal Engine is a shared AI infrastructure layer that provides consistent request building, tool execution, and conversation management for both Pipeline AI and Chat API agents in Data Machine.

## Overview

Prior to v0.2.0, Pipeline AI and Chat agents maintained separate implementations of conversation loops, tool execution, and request building. This architectural duplication created maintenance overhead and potential behavioral drift between agent types.

The Universal Engine consolidates this shared functionality into a centralized layer at `/inc/Engine/AI/`, enabling both agent types to leverage identical AI infrastructure while maintaining their specialized behaviors through filter-based integration. Since v0.2.2, it includes ToolManager for centralized tool management and ToolRegistrationTrait for standardized tool registration patterns.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│          Universal Engine (/inc/Engine/AI/)         │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ AIConversationLoop│      │ RequestBuilder   │   │
│  │ Multi-turn loops │      │ Centralized AI   │   │
│  │ Tool coordination│      │ request building │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ ToolManager      │      │ ToolExecutor     │   │
│  │ Centralized tool │      │ Tool discovery   │   │
│  │ management       │      │ Tool execution   │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ ToolParameters   │      │ ToolResultFinder │   │
│  │ Parameter        │      │ Result search    │   │
│  │ building         │      │ utility          │   │
│  └──────────────────┘      └──────────────────┘   │
│                                                      │
│  ┌──────────────────┐      ┌──────────────────┐   │
│  │ConversationManager│      │ ToolRegistration│   │
│  │ Message utilities│      │ Trait            │   │
│  │ and validation   │      │ (@since v0.2.2)  │   │
│  └──────────────────┘      └──────────────────┘   │
└─────────────────────────────────────────────────────┘
                        │
        ┌───────────────┴───────────────┐
        │                               │
        ▼                               ▼
┌──────────────────┐          ┌──────────────────┐
│  Pipeline Agent  │          │    Chat Agent    │
│  (/inc/Core/     │          │   (/inc/Api/     │
│   Steps/AI/)     │          │    Chat/)        │
│                  │          │                  │
│ • PipelineCore   │          │ • ChatAgent      │
│   Directive      │          │   Directive      │
│ • Pipeline       │          │ • Specialized    │
│   SystemPrompt   │          │   tool           │
│   Directive      │          │ • Session        │
│ • PipelineContext│          │   management     │
│   Directive      │          │                  │
└──────────────────┘          └──────────────────┘
```

## Core Components

The Universal Engine consists of eight core components that provide shared AI infrastructure:

- **AIConversationLoop** - Multi-turn conversation execution with automatic tool coordination
- **RequestBuilder** - Centralized AI request construction with directive application
- **ToolExecutor** - Universal tool discovery, validation, and execution
- **ToolManager** - Centralized tool management and validation (@since v0.2.1)
- **ToolParameters** - Standardized parameter building for tool handlers
- **ConversationManager** - Message formatting, validation, and conversation utilities
- **ToolResultFinder** - Universal tool result search and interpretation
- **ToolRegistrationTrait** - Agent-agnostic tool registration pattern (@since v0.2.2)

Each component is documented individually in the core system documentation.

### ToolManager (@since v0.2.1)

**Location**: `/inc/Engine/AI/Tools/ToolManager.php`

Centralized tool management system that replaces distributed tool discovery and validation logic throughout the codebase.

#### Key Responsibilities

- **Tool Discovery**: Discovers all tools available for a given agent type and execution context
- **Enablement Validation**: Three-layer validation (global settings → step configuration → runtime validation)
- **Configuration Management**: Checks if tools have required configuration (API keys, OAuth credentials)
- **Opt-Out Pattern**: WordPress-native tools without configuration requirements
- **UI Data Aggregation**: Processes tool metadata for admin interface display

#### Core Methods

```php
// Tool discovery and validation
$tool_manager = new ToolManager();
$global_tools = $tool_manager->get_global_tools();

// Check tool availability (includes enablement and configuration)
$is_available = $tool_manager->is_tool_available('google_search', $step_context_id);

// Configuration checking
$is_configured = $tool_manager->is_tool_configured('google_search');

// WordPress-native tools (no config needed)
$opt_out_tools = $tool_manager->get_opt_out_defaults();
// Returns: ['local_search', 'wordpress_post_reader', 'web_fetch']

// UI data aggregation
$ui_data = $tool_manager->getToolsForUI();
```

#### Three-Layer Validation

```
┌─────────────────────────────────────────────┐
│ Layer 1: Global Settings                    │
│ - System-wide tool enablement toggle        │
│ - Tools can be disabled globally            │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│ Layer 2: Step Configuration                 │
│ - Per-step tool selection in builder        │
│ - Only selected tools available in step     │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│ Layer 3: Runtime Validation                 │
│ - Configuration requirements check          │
│ - API key/OAuth credential validation       │
│ - Tool execution readiness verification     │
└─────────────────────────────────────────────┘
```

#### Benefits

- **Code Reduction**: Eliminates ~60% of tool validation code throughout codebase
- **Consistency**: Ensures uniform tool validation across all components
- **Performance**: Implements caching for tool discovery and validation
- **Maintainability**: Single location for tool management logic
- **Extensibility**: Filter-based architecture for custom tools

See Tool Manager for complete documentation.

### ToolRegistrationTrait (@since v0.2.2)

**Location**: `/inc/Engine/AI/Tools/ToolRegistrationTrait.php`

Agent-agnostic tool registration pattern for global tools that provides standardized registration with dynamic filter creation.

#### Key Features

- **Dynamic Filter Creation**: Automatically creates filter callbacks based on agent type
- **Future-Proof**: Supports current and future agent types (pipeline, chat, frontend, supportbot)
- **Consistent Patterns**: Standardized registration across all global tools
- **Configuration Integration**: Automatic configuration handler registration

#### Usage in Global Tools

```php
use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class GoogleSearchFilters {
    use ToolRegistrationTrait;

    public static function register(): void {
        self::registerTool(
            'google_search',
            GoogleSearch::class,
            __('Google Search', 'datamachine'),
            __('Web search with Custom Search API', 'datamachine'),
            'datamachine_google_search_tool',
            GoogleSearchConfigHandler::class
        );
    }
}
```

#### Global Tools Using Trait

- **GoogleSearch** - Web search with Custom Search API
- **LocalSearch** - WordPress internal search
- **WebFetch** - Web page content retrieval
- **WordPressPostReader** - Single post analysis

#### Benefits

- **Agent Agnostic**: Dynamic filter creation per agent type
- **Extensibility**: Easy to add new agent types without updating tools
- **Consistency**: Uniform registration patterns across global tools
- **Maintainability**: Centralized registration logic for all global tools

See individual tool documentation in `/docs/ai-tools/` for usage examples.
