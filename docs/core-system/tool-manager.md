# Tool Manager

Centralized tool management system introduced in v0.2.1 that replaces distributed tool discovery and validation logic.

## Overview

ToolManager (`/inc/Engine/AI/Tools/ToolManager.php`) provides centralized methods for tool discovery, enablement validation, and configuration management across the entire system.

## Architecture

- **Location**: `/inc/Engine/AI/Tools/ToolManager.php`
- **Purpose**: Centralize all tool-related operations
- **Replaces**: Distributed filter logic for tool availability
- **Benefits**:
  - Single source of truth for tool availability
  - Consistent validation patterns
  - Performance optimization through centralized logic
  - Easy extensibility for new validation rules

## Key Methods

### getAvailableTools()

Discovers all tools available for a given agent type and execution context.

```php
public function getAvailableTools(
    string $agent_type,
    ?string $handler_slug = null,
    array $enabled_tool_ids = []
): array
```

**Parameters**:
- `$agent_type`: Agent type ('pipeline', 'chat', etc.)
- `$handler_slug`: Optional handler identifier for handler-specific tools
- `$enabled_tool_ids`: Array of tool IDs enabled for specific step

**Returns**: Array of available tool definitions

**Usage**:
```php
$tool_manager = new ToolManager();
$tools = $tool_manager->getAvailableTools('pipeline', 'twitter', ['google_search']);
```

### isToolEnabled()

Validates if a tool is globally enabled and optionally checks step-specific configuration.

```php
public function isToolEnabled(
    string $tool_id,
    array $enabled_tool_ids = []
): bool
```

**Parameters**:
- `$tool_id`: Tool identifier to validate
- `$enabled_tool_ids`: Optional array of step-specific enabled tools

**Returns**: Boolean indicating tool availability

**Usage**:
```php
$tool_manager = new ToolManager();
$is_enabled = $tool_manager->isToolEnabled('google_search', ['google_search', 'web_fetch']);
```

**Validation Layers**:
1. Global settings check (tool must be enabled system-wide)
2. Step configuration check (tool must be selected for specific step, if provided)

### isToolConfigured()

Checks if a tool has required configuration (API keys, OAuth, etc).

```php
public function isToolConfigured(string $tool_id): bool
```

**Parameters**:
- `$tool_id`: Tool identifier to check

**Returns**: Boolean indicating configuration status

**Usage**:
```php
$tool_manager = new ToolManager();
$is_configured = $tool_manager->isToolConfigured('google_search');
```

**Configuration Checks**:
- API keys for third-party services
- OAuth account credentials
- Required settings validation
- Auto-passes for WordPress-native tools (see getOptOutTools)

### getOptOutTools()

Returns tools that don't require configuration (WordPress-native functionality).

```php
public function getOptOutTools(): array
```

**Returns**: Array of tool IDs that are always considered "configured" if enabled

**Default Opt-Out Tools**:
- `local_search` - WordPress internal search
- `wordpress_post_reader` - WordPress post content retrieval
- `web_fetch` - Web page content fetching (no API required)

**Usage**:
```php
$tool_manager = new ToolManager();
$opt_out_tools = $tool_manager->getOptOutTools();
// Returns: ['local_search', 'wordpress_post_reader', 'web_fetch']
```

### getToolsForUI()

Aggregates tool data for admin interface display.

```php
public function getToolsForUI(): array
```

**Returns**: Array of tool metadata for React components

**Usage**:
```php
$tool_manager = new ToolManager();
$ui_data = $tool_manager->getToolsForUI();

// Returns structured data:
// [
//     'google_search' => [
//         'id' => 'google_search',
//         'label' => 'Google Search',
//         'description' => 'Web search with Custom Search API',
//         'is_enabled' => true,
//         'is_configured' => true,
//         'requires_config' => true
//     ],
//     // ...
// ]
```

## Tool Enablement Architecture

### Three-Layer Validation

ToolManager implements a three-layer validation system for tool availability:

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

### Validation Flow

```php
// Complete validation flow
$tool_manager = new ToolManager();

// 1. Check global enablement
$is_globally_enabled = $tool_manager->isToolEnabled('google_search');

// 2. Check step-specific selection
$is_step_enabled = $tool_manager->isToolEnabled('google_search', ['google_search']);

// 3. Check configuration requirements
$is_configured = $tool_manager->isToolConfigured('google_search');

// 4. Final availability determination
$is_available = $is_globally_enabled && $is_step_enabled && $is_configured;
```

## Opt-Out Pattern

WordPress-native tools use an "opt-out" pattern for configuration:

```php
// Opt-out tools are always considered "configured"
$tool_manager = new ToolManager();
$opt_out_tools = $tool_manager->getOptOutTools();

// Configuration check auto-passes for these tools
$is_configured = $tool_manager->isToolConfigured('local_search');
// Returns: true (WordPress-native, no configuration needed)

$is_configured = $tool_manager->isToolConfigured('google_search');
// Returns: false (requires API key and Search Engine ID)
```

**Rationale**:
- WordPress-native tools use built-in functionality
- No external API keys or services required
- Always available when WordPress is running
- Reduces configuration burden for common tools

## Usage in Components

### ToolExecutor Integration

```php
use DataMachine\Engine\AI\Tools\ToolManager;

class ToolExecutor {
    private ToolManager $tool_manager;

    public function __construct() {
        $this->tool_manager = new ToolManager();
    }

    public function execute_tool(string $tool_id, array $parameters): array {
        // Validate tool is enabled and configured
        if (!$this->tool_manager->isToolEnabled($tool_id)) {
            return $this->error_response('Tool not enabled');
        }

        if (!$this->tool_manager->isToolConfigured($tool_id)) {
            return $this->error_response('Tool not configured');
        }

        // Execute tool...
    }
}
```

### Settings UI Integration

```php
use DataMachine\Engine\AI\Tools\ToolManager;

class SettingsPage {
    public function render_tools_section(): void {
        $tool_manager = new ToolManager();
        $tools = $tool_manager->getToolsForUI();

        foreach ($tools as $tool_id => $tool_data) {
            // Render UI with:
            // - Enable toggle (based on is_enabled)
            // - Config button (if requires_config && !is_configured)
            // - Status indicator (configured vs. needs setup)
        }
    }
}
```

### Pipeline Builder Integration

```php
use DataMachine\Engine\AI\Tools\ToolManager;

class AIStepModal {
    public function get_available_tools(string $handler_slug): array {
        $tool_manager = new ToolManager();

        // Get all tools for this handler
        $all_tools = $tool_manager->getAvailableTools('pipeline', $handler_slug);

        // Filter to only enabled and configured tools
        return array_filter($all_tools, function($tool) use ($tool_manager) {
            return $tool_manager->isToolEnabled($tool['id'])
                && $tool_manager->isToolConfigured($tool['id']);
        });
    }
}
```

## Extension Integration

Extensions can integrate with ToolManager through standard filter patterns:

```php
// Register custom tool
add_filter('datamachine_global_tools', function($tools) {
    $tools['my_custom_tool'] = [
        'id' => 'my_custom_tool',
        'class' => MyCustomTool::class,
        'method' => 'execute',
        'description' => 'Custom tool description',
        'parameters' => [/* ... */]
    ];
    return $tools;
});

// ToolManager automatically discovers and validates
$tool_manager = new ToolManager();
$is_enabled = $tool_manager->isToolEnabled('my_custom_tool');
$is_configured = $tool_manager->isToolConfigured('my_custom_tool');
```

## Performance Considerations

### Caching

ToolManager uses WordPress transients for performance:

```php
// Tool availability cached per agent type
$cache_key = "datamachine_tools_{$agent_type}";
$tools = get_transient($cache_key);

if (false === $tools) {
    $tools = $this->discover_tools($agent_type);
    set_transient($cache_key, $tools, HOUR_IN_SECONDS);
}
```

### Cache Invalidation

Tool cache is automatically invalidated when:
- Settings are updated
- Tools are enabled/disabled
- Tool configuration changes
- Handler registration changes

```php
// Manual cache clearing
do_action('datamachine_clear_tools_cache');
```

## Error Handling

ToolManager provides consistent error handling:

```php
try {
    $tools = $tool_manager->getAvailableTools('pipeline');
} catch (Exception $e) {
    do_action('datamachine_log', 'error', 'Tool discovery failed', [
        'error' => $e->getMessage()
    ]);
    return [];
}
```

## Migration from Legacy Patterns

### Before (Distributed Logic)

```php
// Old pattern: scattered tool validation
$enabled_tools = get_option('datamachine_enabled_tools', []);
$is_enabled = in_array('google_search', $enabled_tools);

$google_config = get_option('datamachine_google_search_config', []);
$is_configured = !empty($google_config['api_key']);

// Validation logic duplicated across multiple files
```

### After (Centralized ToolManager)

```php
// New pattern: centralized validation
$tool_manager = new ToolManager();
$is_enabled = $tool_manager->isToolEnabled('google_search');
$is_configured = $tool_manager->isToolConfigured('google_search');

// Single source of truth, consistent behavior
```

## Benefits

### Code Reduction

- Eliminates ~60% of tool validation code throughout codebase
- Centralizes tool discovery logic from multiple components
- Reduces maintenance burden for tool-related operations

### Consistency

- Ensures uniform tool validation across all components
- Prevents inconsistent enablement checking
- Standardizes configuration validation patterns

### Performance

- Implements caching for tool discovery
- Reduces redundant filter calls
- Optimizes database queries for tool data

### Maintainability

- Single location for tool management logic
- Easy to add new validation requirements
- Simplified debugging of tool availability issues

### Extensibility

- Filter-based architecture for custom tools
- Clear integration points for extensions
- Supports future tool types and validation patterns

## See Also

- [Universal Engine](universal-engine.md) - Engine architecture overview
- [Tool Execution Architecture](tool-execution.md) - Tool execution patterns
- [AI Tools Overview](../ai-tools/tools-overview.md) - Available tools catalog
- [Core Filters](../api-reference/core-filters.md) - Filter reference
