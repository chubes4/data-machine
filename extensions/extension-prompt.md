# Data Machine Extension Generator

Replace the placeholders below with your requirements, then copy this entire prompt to an LLM to generate your extension.

## Your Extension Requirements

```
EXTENSION_NAME = "Your Extension Name"
EXTENSION_DESCRIPTION = "What your extension does"
EXTENSION_TYPE = "handler|step|admin|tool|service"
PLUGIN_SLUG = "your-plugin-slug"
HANDLER_SLUG = "your_handler"
HANDLER_CLASS = "YourHandler"
AUTHOR_NAME = "Your Name"
AUTHOR_URI = "https://yourwebsite.com"

// For handlers only:
HANDLER_TYPE = "fetch|publish|ai"
REQUIRES_AUTH = true|false

// For AI tools:
TOOL_NAME = "your_tool_name"
TOOL_DESCRIPTION = "What the tool does"

// Additional functionality:
NEEDS_ADMIN_PAGE = true|false
NEEDS_DATABASE_TABLE = true|false
NEEDS_OAUTH = true|false
NEEDS_PIPELINE_CREATION = true|false

SPECIFIC_REQUIREMENTS = "
Describe exactly what your extension should do, what APIs it connects to, 
what data it processes, how users will configure it, etc.
"
```

---

# LLM Instructions

You are creating a WordPress plugin that extends Data Machine. Generate ALL necessary files with complete implementations.

## Data Machine Integration Requirements

### Filter-Based Discovery
Data Machine discovers extensions through WordPress filters. Your extension MUST register with these filters:

```php
// Register handlers (fetch, publish, ai step types)
add_filter('dm_handlers', [$this, 'register_handlers']);

// Register AI tools (for agentic tool calling)
add_filter('ai_tools', [$this, 'register_ai_tools']); 

// Register custom step types (if creating new step type)
add_filter('dm_steps', [$this, 'register_steps']);

// Register database services (if creating database functionality)
add_filter('dm_db', [$this, 'register_database']);
```

### Required Interfaces

**Fetch Handlers:**
```php
public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
    return ['processed_items' => $items];
}
```

**Publish Handlers (Tool-First Architecture):**
```php
public function handle_tool_call(array $parameters, array $tool_def = []): array {
    return ['success' => true, 'data' => $result, 'tool_name' => 'tool_name'];
}
```

**AI Agents:**
```php
public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
    // Process data array, add new entry to front
    array_unshift($data, $processed_entry);
    return $data;
}
```

**Custom Steps:**
```php
public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
    // Your custom processing logic
    return $data; // Always return the data array
}
```

### Plugin Structure Requirements

1. **Main plugin file** with proper WordPress headers including `Requires Plugins: data-machine`
2. **Class-based architecture** with `plugins_loaded` hook initialization  
3. **Conditional loading** of includes based on requirements
4. **Filter registration using class methods**, not anonymous functions
5. **Proper WordPress security** - nonces, sanitization, capability checks

### Data Flow Patterns

**DataPacket Array Structure:**
```php
[
    'type' => 'fetch|ai|publish|custom',
    'handler' => 'handler_slug', // if applicable
    'content' => ['title' => $title, 'body' => $content],
    'metadata' => ['source_type' => 'type', 'job_id' => $job_id],
    'timestamp' => time()
]
```

**Available Filters for Data Access:**
- `apply_filters('dm_get_pipelines', [], $pipeline_id)`
- `apply_filters('dm_get_flow_config', [], $flow_id)`
- `apply_filters('dm_create_pipeline', null, $data)`
- `apply_filters('dm_create_step', null, $data)`
- `apply_filters('dm_oauth', [], 'operation', 'handler')`

**Available Actions:**
- `do_action('dm_log', $level, $message, $context)`
- `do_action('dm_mark_item_processed', $flow_step_id, $source_type, $item_id, $job_id)`
- `do_action('dm_run_flow_now', $flow_id, 'manual')`

## Implementation Instructions

1. **Analyze the requirements above** and determine exactly what files and functionality are needed
2. **Generate complete, working implementations** - no TODO comments or stub methods
3. **Follow WordPress coding standards** exactly
4. **Implement proper error handling** with dm_log actions
5. **Use WordPress security best practices** throughout
6. **Test integration points** - ensure your extension will be discovered by Data Machine
7. **Generate only what's needed** - don't add unnecessary complexity

The result should be a complete WordPress plugin that properly integrates with Data Machine's filter-based architecture and implements the requested functionality.