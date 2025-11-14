# Parameter Systems

Unified flat parameter architecture for all Data Machine components, providing consistent interfaces across steps, handlers, and tools while maintaining extensibility and simplicity.

## Architecture Overview

Data Machine uses an engine data filter architecture that provides clean data separation and consistent parameter access across all components.

### Core Design Principles

1. **Engine Data Propagation** - Fetch handlers store engine data via centralized filters; engine bundles the data into the payload passed to every step and tool
2. **Clean Data Separation** - AI receives clean data packets without URLs; handlers receive engine parameters via the payload-supplied engine data
3. **Unified Interface** - All steps, handlers, and tools use consistent parameter formats
4. **Tool-Based Parameter Building** - AIStepToolParameters class provides standardized parameter construction

## Core Payload Structure

### Universal Payload Keys
These payload keys are provided to ALL steps and components:

```php
$payload = [
    'job_id' => $job_id,                    // Unique job identifier
    'flow_step_id' => $flow_step_id,        // Flow step identifier ({pipeline_step_id}_{flow_id})
    'data' => $data,                        // Data packet array
    'flow_step_config' => $flow_step_config,// Step configuration
    'engine_data' => $engine_data           // Engine metadata stored by fetch handlers
];
```

### Engine Data
Engine data is stored in the database by fetch handlers using the centralized `datamachine_engine_data` filter. During execution the engine collects that data and injects it into the payload, so every step receives the same metadata packet:

```php
// 1. Fetch handlers store data for later payload injection
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $source_url,
        'image_url' => $image_url
    ]);
}

// 2. Steps read the injected engine data directly from the payload
public function execute(array $payload): array {
    $engine_data = $payload['engine_data'] ?? [];

    // Optional: refresh if handler updated engine data mid-flow
    if (!$engine_data) {
        $engine_data = apply_filters('datamachine_engine_data', [], $payload['job_id']);
    }

    $source_url = $engine_data['source_url'] ?? null;
    $image_url = $engine_data['image_url'] ?? null;

    // ...
}
```

## Step Implementation Pattern

All steps follow the same payload extraction pattern:

```php
class MyStep {
    public function execute(array $payload): array {
        // Extract core parameters
        $job_id = $payload['job_id'];
        $flow_step_id = $payload['flow_step_id'];
        $data = $payload['data'] ?? [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        $engine_data = $payload['engine_data'] ?? [];

        // Extract step-specific parameters
        $custom_setting = $payload['custom_setting'] ?? null;
        $source_url = $engine_data['source_url'] ?? null;

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
            apply_filters('datamachine_engine_data', null, $job_id, [
                'source_url' => $source_url,
                'image_url' => $image_url
            ]);
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
        // Parameters built by AIStepToolParameters::buildParameters()
        // Already contain: content, title, job_id, flow_step_id, engine_data

        $content = $parameters['content'] ?? '';
        $handler_config = $tool_def['handler_config'] ?? [];

        $engine_data = $parameters['engine_data'] ?? [];
        $source_url = $engine_data['source_url'] ?? null;
        $image_url = $engine_data['image_url'] ?? null;

        return ['success' => true, 'data' => ['id' => $id]];
    }
}
```

### Update Handlers (Engine Data)
Require `source_url` from engine data stored by fetch handlers and delivered on the payload:

```php
class MyUpdateHandler {
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        // Engine data already provided on the payload
        $engine_data = $parameters['engine_data'] ?? [];
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
    $handler_config          // Handler configuration
);
```

### Parameter Building Process

1. **Start with Payload Context** - Core job/flow identifiers and engine data copied directly from the incoming payload
2. **Extract Content** - Pull content/title from data packets based on tool specs
3. **Add Tool Metadata** - Include tool_definition, tool_name, handler_config
4. **Merge AI Parameters** - Add AI-provided parameters (overwrites conflicts)
5. **Preserve Engine Data** - Engine metadata stays attached for handler tools and downstream consumers

### Example Built Parameters

```php
[
    // Core engine parameters
    'job_id' => 'uuid-job-123',
    'flow_step_id' => 'uuid-step-1_456',
    'data' => [...], // Data packet array
    'flow_step_config' => [...],
    'engine_data' => [
        'source_url' => 'https://example.com/post/123',
        'image_url' => 'https://example.com/post/123/cover.jpg'
    ],

    // Extracted content (from data packets)
    'content' => 'Article content from data packet',
    'title' => 'Article title from data packet',

    // Tool metadata
    'tool_definition' => [...],
    'tool_name' => 'twitter_publish',
    'handler_config' => ['include_images' => true],

    // AI-provided parameters
    'content' => 'AI-modified tweet content', // Overwrites extracted content
    'hashtags' => '#ai #automation'
]
```

## Handler-Specific Engine Parameters

### Database Storage by Fetch Handlers
Each fetch handler stores specific engine parameters in the database using array storage:

```php
// Reddit Handler - stores via centralized filter (array storage)
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => 'https://reddit.com' . $item_data['permalink'],
        'image_url' => $stored_image['url'] ?? ''
    ]);
}

// WordPress Local Handler - stores via centralized filter (array storage)
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => get_permalink($post_id),
        'image_url' => $this->extract_image_url($post_id)
    ]);
}

// RSS Handler - stores via centralized filter (array storage)
if ($job_id) {
    apply_filters('datamachine_engine_data', null, $job_id, [
        'source_url' => $item_link,
        'image_url' => $enclosure_url
    ]);
}
```

### Parameter Validation
Steps can validate required parameters:

```php
public function execute(array $payload): array {
    $required = ['job_id', 'flow_step_id', 'custom_required_param'];

    foreach ($required as $param) {
        if (!isset($payload[$param])) {
            throw new InvalidArgumentException("Missing required parameter: {$param}");
        }
    }

    return $this->process($payload);
}
```

## Flow Step Configuration Access

Payloads include complete flow step configuration with pipeline inheritance:

```php
$flow_step_config = $payload['flow_step_config'];

// Available configuration keys:
$step_type = $flow_step_config['step_type'];           // From pipeline
$execution_order = $flow_step_config['execution_order']; // From pipeline
$system_prompt = $flow_step_config['system_prompt'];   // From pipeline (AI steps)
$user_message = $flow_step_config['user_message'];     // From flow (AI steps)
$handler_config = $flow_step_config['handler_config']; // Handler settings
```

## Data Packet Integration

The `data` key contains the complete workflow history:

```php
$data = $payload['data']; // Array of data packets

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
- Single payload extraction pattern across all components
- No complex nested structure navigation
- Clear parameter availability and access

### Extensibility
- New parameter types automatically available to all components
- Filter-based parameter injection
- No signature changes required for new functionality

### Consistency
- Unified payload interface across steps, handlers, and tools
- Consistent parameter naming and structure
- Predictable parameter availability

### Debugging
- Easy parameter inspection and logging
- Clear parameter source identification
- Simplified debugging of parameter flow

This parameter system enables flexible, extensible component development while maintaining consistency and simplicity across the entire Data Machine architecture.