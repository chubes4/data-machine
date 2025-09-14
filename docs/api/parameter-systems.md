# Parameter Systems

Unified flat parameter architecture for all Data Machine components, providing consistent interfaces across steps, handlers, and tools while maintaining extensibility and simplicity.

## Architecture Overview

Data Machine uses a simplified two-parameter system that eliminates complex nested structures in favor of flat, extensible parameter arrays accessible to all components.

### Core Design Principles

1. **Flat Parameter Structure** - All parameters exist at the root level of a single array
2. **Filter-Based Extension** - Components declare parameter requirements through WordPress filters
3. **Unified Interface** - All steps, handlers, and tools use the same parameter format
4. **Extensible** - New parameter types automatically become available to all components

## Core Parameter Structure

### Universal Parameters
These parameters are provided to ALL steps and components:

```php
$core_parameters = [
    'job_id' => $job_id,                    // Unique job identifier
    'flow_step_id' => $flow_step_id,        // Flow step identifier ({pipeline_step_id}_{flow_id})
    'data' => $data,                        // Data packet array
    'flow_step_config' => $flow_step_config // Step configuration
];
```

### Extended Parameters
Additional parameters added through the parameter filter system:

```php
$parameters = apply_filters('dm_engine_parameters', [
    'job_id' => $job_id,
    'flow_step_id' => $flow_step_id,
    'flow_step_config' => $flow_step_config,
    'data' => $data,
    // Extended parameters added by filters
    'source_url' => $source_url,           // For Update handlers
    'pipeline_step_id' => $pipeline_step_id,
    'flow_id' => $flow_id,
    'execution_order' => $execution_order
], $data, $flow_step_config, $step_type, $flow_step_id);
```

## Step Implementation Pattern

All steps follow the same parameter extraction pattern:

```php
class MyStep {
    public function execute(array $parameters): array {
        // Extract core parameters
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];

        // Extract step-specific parameters
        $custom_setting = $parameters['custom_setting'] ?? null;
        $source_url = $parameters['source_url'] ?? null;

        // Step processing logic
        $result = $this->process_data($data, $flow_step_config);

        // Mark items processed
        do_action('dm_mark_item_processed', $flow_step_id, 'my_step', $item_id, $job_id);

        // Return updated data packet array
        return $result;
    }
}
```

## Handler Parameter Patterns

### Fetch Handlers
Receive core parameters plus pipeline-specific configuration:

```php
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        // Handler-specific implementation
        // Core parameters available through filter if needed

        return ['processed_items' => $items];
    }
}
```

### Publish Handlers (Tool-Based)
Use `AIStepToolParameters` for standardized parameter building:

```php
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Parameters built by AIStepToolParameters::buildParameters()
        // Contains: content, title, tool_name, handler_config, etc.

        $content = $parameters['content'] ?? '';
        $handler_config = $tool_def['handler_config'] ?? [];

        return ['success' => true, 'data' => ['id' => $id]];
    }
}
```

### Update Handlers (Engine Parameters)
Require `source_url` from engine parameters for content modification:

```php
class MyUpdateHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Parameters built by AIStepToolParameters::buildForHandlerTool()
        // Includes engine parameters like source_url

        if (empty($parameters['source_url'])) {
            return ['success' => false, 'error' => 'Missing required source_url parameter'];
        }

        $source_url = $parameters['source_url'];
        $content = $parameters['content'] ?? '';

        return ['success' => true, 'data' => ['updated_id' => $id]];
    }
}
```

## AI Tool Parameter Building

### AIStepToolParameters Class
Centralized parameter building for AI tool execution:

```php
// Standard tool parameter building
$parameters = AIStepToolParameters::buildParameters(
    $ai_tool_parameters,     // Parameters from AI tool call
    $unified_parameters,     // Engine parameter structure
    $tool_definition         // Tool definition array
);

// Handler tool parameter building (includes engine parameters)
$parameters = AIStepToolParameters::buildForHandlerTool(
    $ai_tool_parameters,     // AI tool call parameters
    $data,                   // Data packet array
    $tool_definition,        // Tool specification
    $engine_parameters,      // Engine parameters (source_url, etc.)
    $handler_config         // Handler configuration
);
```

### Parameter Building Process

1. **Start with Engine Parameters** - Core job/flow context as base
2. **Extract Content** - Pull content/title from data packets based on tool specs
3. **Add Tool Metadata** - Include tool_definition, tool_name, handler_config
4. **Merge AI Parameters** - Add AI-provided parameters (overwrites conflicts)
5. **Include Engine Context** - For Update handlers, merge source_url and context

### Example Built Parameters

```php
[
    // Core engine parameters
    'job_id' => 'uuid-job-123',
    'flow_step_id' => 'uuid-step-1_456',
    'data' => [...], // Data packet array
    'flow_step_config' => [...],

    // Extracted content (from data packets)
    'content' => 'Article content from data packet',
    'title' => 'Article title from data packet',

    // Tool metadata
    'tool_definition' => [...],
    'tool_name' => 'twitter_publish',
    'handler_config' => ['include_images' => true],

    // AI-provided parameters
    'content' => 'AI-modified tweet content', // Overwrites extracted content
    'hashtags' => '#ai #automation',

    // Engine parameters (Update handlers only)
    'source_url' => 'https://example.com/post/123'
]
```

## Parameter Extension

### Adding Custom Parameters
Components can extend the parameter system through filters:

```php
add_filter('dm_engine_parameters', function($parameters, $data, $flow_step_config, $step_type, $flow_step_id) {
    // Add custom parameters based on step type
    if ($step_type === 'my_custom_step') {
        $parameters['custom_setting'] = $flow_step_config['custom_setting'] ?? 'default';
        $parameters['external_api_key'] = get_option('my_api_key');
    }

    // Add parameters based on flow configuration
    if (!empty($flow_step_config['requires_source_url'])) {
        $parameters['source_url'] = $this->extract_source_url_from_data($data);
    }

    return $parameters;
}, 10, 5);
```

### Parameter Validation
Steps can validate required parameters:

```php
public function execute(array $parameters): array {
    $required = ['job_id', 'flow_step_id', 'custom_required_param'];

    foreach ($required as $param) {
        if (!isset($parameters[$param])) {
            throw new InvalidArgumentException("Missing required parameter: {$param}");
        }
    }

    return $this->process($parameters);
}
```

## Flow Step Configuration Access

Parameters include complete flow step configuration with pipeline inheritance:

```php
$flow_step_config = $parameters['flow_step_config'];

// Available configuration keys:
$step_type = $flow_step_config['step_type'];           // From pipeline
$execution_order = $flow_step_config['execution_order']; // From pipeline
$system_prompt = $flow_step_config['system_prompt'];   // From pipeline (AI steps)
$user_message = $flow_step_config['user_message'];     // From flow (AI steps)
$handler_config = $flow_step_config['handler_config']; // Handler settings
```

## Data Packet Integration

The data parameter contains the complete workflow history:

```php
$data = $parameters['data']; // Array of data packets

// Each data packet structure:
[
    'type' => 'fetch|ai|publish|update',
    'handler' => 'rss|twitter|etc',
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => ['source_type' => $type, 'source_url' => $url],
    'timestamp' => time()
]
```

## Benefits of Flat Parameter Architecture

### Simplicity
- Single parameter extraction pattern across all components
- No complex nested structure navigation
- Clear parameter availability and access

### Extensibility
- New parameter types automatically available to all components
- Filter-based parameter injection
- No signature changes required for new functionality

### Consistency
- Unified interface across steps, handlers, and tools
- Consistent parameter naming and structure
- Predictable parameter availability

### Debugging
- Easy parameter inspection and logging
- Clear parameter source identification
- Simplified debugging of parameter flow

This parameter system enables flexible, extensible component development while maintaining consistency and simplicity across the entire Data Machine architecture.