# Parameter Systems

Unified flat parameter architecture for all Data Machine components, providing consistent interfaces across steps, handlers, and tools while maintaining extensibility and simplicity.

## Architecture Overview

Data Machine uses an engine data filter architecture that provides clean data separation and consistent parameter access across all components.

### Core Design Principles

1. **Engine Data Filter Access** - Fetch handlers store engine data in database; steps retrieve via centralized datamachine_engine_data filter
2. **Clean Data Separation** - AI receives clean data packets without URLs; handlers receive engine parameters via filter access
3. **Unified Interface** - All steps, handlers, and tools use consistent parameter formats
4. **Tool-Based Parameter Building** - AIStepToolParameters class provides standardized parameter construction

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

### Engine Data
Engine data is stored in database by fetch handlers and retrieved via centralized datamachine_engine_data filter:

```php
// 1. Fetch handlers store in database via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, $source_url, $image_url);
}

// 2. Steps retrieve engine data via centralized filter
$engine_data = apply_filters('datamachine_engine_data', [], $job_id);
$source_url = $engine_data['source_url'] ?? null;
$image_url = $engine_data['image_url'] ?? null;
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
        do_action('datamachine_mark_item_processed', $flow_step_id, 'my_step', $item_id, $job_id);

        // Return updated data packet array
        return $result;
    }
}
```

## Handler Parameter Patterns

### Fetch Handlers
Generate clean data packets and store engine parameters in database:

```php
class MyFetchHandler {
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        $flow_step_id = $handler_config['flow_step_id'] ?? null;

        // Create clean data packet (no URLs)
        $clean_data = [
            'data' => ['content_string' => $content],
            'metadata' => ['source_type' => 'my_handler', 'original_id' => $item_id]
        ];

        // Store engine parameters in database via centralized datamachine_engine_data filter
        if ($job_id) {
            apply_filters('datamachine_engine_data', null, $job_id, $source_url, $image_url);
        }

        return ['processed_items' => [$clean_data]];
    }
}
```

### Publish Handlers (Tool-Based)
Use `AIStepToolParameters::buildForHandlerTool()` for parameter building with engine data access:

```php
class MyPublishHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Parameters built by AIStepToolParameters::buildForHandlerTool()
        // Contains: content, title, tool_name, handler_config, engine data (source_url, image_url)

        $content = $parameters['content'] ?? '';
        $handler_config = $tool_def['handler_config'] ?? [];

        // Access engine data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        return ['success' => true, 'data' => ['id' => $id]];
    }
}
```

### Update Handlers (Engine Data)
Require `source_url` from engine data stored by fetch handlers and retrieved via datamachine_engine_data filter:

```php
class MyUpdateHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Access engine data via centralized filter pattern
        $job_id = $parameters['job_id'] ?? null;
        $engine_data = apply_filters('datamachine_engine_data', [], $job_id);
        $source_url = $engine_data['source_url'] ?? null;

        if (empty($source_url)) {
            return ['success' => false, 'error' => 'Missing required source_url from engine data'];
        }

        $content = $parameters['content'] ?? '';

        // Update existing content at source_url
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

// Handler tool parameter building (includes engine data)
$parameters = AIStepToolParameters::buildForHandlerTool(
    $ai_tool_parameters,     // AI tool call parameters
    $data,                   // Data packet array
    $tool_definition,        // Tool specification
    $engine_parameters,      // Engine data (source_url, etc.)
    $handler_config         // Handler configuration
);
```

### Parameter Building Process

1. **Start with Engine Parameters** - Core job/flow context as base
2. **Extract Content** - Pull content/title from data packets based on tool specs
3. **Add Tool Metadata** - Include tool_definition, tool_name, handler_config
4. **Merge AI Parameters** - Add AI-provided parameters (overwrites conflicts)
5. **Include Engine Data** - For handler tools, merge source_url and context from engine data

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

    // Engine data (Update handlers only)
    'source_url' => 'https://example.com/post/123'
]
```

## Handler-Specific Engine Parameters

### Database Storage by Fetch Handlers
Each fetch handler stores specific engine parameters in the database:

```php
// Reddit Handler - stores via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id,
        'https://reddit.com' . $item_data['permalink'],
        $stored_image['url'] ?? ''
    );
}

// WordPress Local Handler - stores via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id,
        get_permalink($post_id),
        $this->extract_image_url($post_id)
    );
}

// RSS Handler - stores via centralized filter
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, $item_link, $enclosure_url);
}
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