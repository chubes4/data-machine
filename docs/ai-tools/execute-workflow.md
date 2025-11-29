# ExecuteWorkflow Tool

**Primary action tool for executing content automation workflows with modular architecture** (@since v0.3.0)

## Overview

The ExecuteWorkflow tool enables AI agents to execute complete multi-step workflows through the chat interface. It provides a simplified interface for creating and running workflows without requiring users to first create pipelines and flows through the admin interface.

## Architecture

The ExecuteWorkflow tool uses a modular architecture with four main components:

### ExecuteWorkflowTool

**Location**: `/inc/Api/Chat/Tools/ExecuteWorkflow/ExecuteWorkflowTool.php`

**Responsibilities**:
- Tool registration and definition
- Request handling and validation
- REST API integration for workflow execution
- Error handling and response formatting

**Key Methods**:
```php
public function handle_tool_call(array $parameters, array $tool_def = []): array
private function transformSteps(array $steps): array
```

### DocumentationBuilder

**Location**: `/inc/Api/Chat/Tools/ExecuteWorkflow/DocumentationBuilder.php`

**Responsibilities**:
- Dynamic tool documentation generation
- Handler discovery and configuration documentation
- Real-time synchronization with registered handlers
- Comprehensive workflow pattern examples

**Key Methods**:
```php
public static function build(): string
private static function buildFetchHandlersSection(): string
private static function buildPublishHandlersSection(): string
private static function buildUpdateHandlersSection(): string
private static function formatHandlerEntry(string $slug, array $handler): string
```

### WorkflowValidator

**Location**: `/inc/Api/Chat/Tools/ExecuteWorkflow/WorkflowValidator.php`

**Responsibilities**:
- Step structure validation
- Handler existence verification
- Configuration schema validation
- Error message generation

**Key Methods**:
```php
public static function validate(array $steps): array
private static function validateStep(array $step): array
private static function validateHandlerConfig(string $handler_slug, array $config): array
```

### DefaultsInjector

**Location**: `/inc/Api/Chat/Tools/ExecuteWorkflow/DefaultsInjector.php`

**Responsibilities**:
- Automatic default value injection
- Provider and model defaults for AI steps
- Handler configuration defaults
- Workflow optimization

**Key Methods**:
```php
public static function inject(array $steps): array
private static function injectAIStepDefaults(array $step): array
private static function injectHandlerDefaults(array $step): array
```

## Usage Patterns

### Basic Content Syndication
```json
{
  "steps": [
    {
      "type": "fetch",
      "handler": "rss",
      "config": {
        "feed_url": "https://example.com/feed.xml"
      }
    },
    {
      "type": "ai",
      "user_message": "Summarize this content and make it engaging for social media"
    },
    {
      "type": "publish",
      "handler": "twitter",
      "config": {}
    }
  ]
}
```

### Content Enhancement
```json
{
  "steps": [
    {
      "type": "fetch",
      "handler": "wordpress_local",
      "config": {
        "post_type": "post",
        "posts_per_page": 5
      }
    },
    {
      "type": "ai",
      "user_message": "Update these posts with better SEO titles and meta descriptions"
    },
    {
      "type": "update",
      "handler": "wordpress_update",
      "config": {
        "update_title": true,
        "update_content": false
      }
    }
  ]
}
```

### Multi-Platform Publishing
```json
{
  "steps": [
    {
      "type": "fetch",
      "handler": "files",
      "config": {
        "file_path": "/content/article.txt"
      }
    },
    {
      "type": "ai",
      "user_message": "Adapt this content for different social media platforms"
    },
    {
      "type": "publish",
      "handler": "twitter",
      "config": {}
    },
    {
      "type": "ai",
      "user_message": "Create a longer version for Facebook"
    },
    {
      "type": "publish",
      "handler": "facebook",
      "config": {}
    }
  ]
}
```

## Step Configuration

### Fetch Steps
```json
{
  "type": "fetch",
  "handler": "handler_slug",
  "config": {
    "required_field": "value",
    "optional_field": "value"
  }
}
```

### AI Steps
```json
{
  "type": "ai",
  "provider": "anthropic",  // Optional: defaults to site default
  "model": "claude-sonnet-4-20250514",  // Optional: defaults to site default
  "user_message": "Instruction for AI processing",
  "system_prompt": "Optional system context"
}
```

### Publish Steps
```json
{
  "type": "publish",
  "handler": "handler_slug",
  "config": {
    "required_field": "value",
    "optional_field": "value"
  }
}
```

### Update Steps
```json
{
  "type": "update",
  "handler": "handler_slug",
  "config": {
    "required_field": "value",
    "optional_field": "value"
  }
}
```

## Handler Configuration

### WordPress Publish Handler
```json
{
  "type": "publish",
  "handler": "wordpress_publish",
  "config": {
    "post_type": "post",
    "status": "publish",
    "author": 1,
    "taxonomy_category_selection": "ai_decides",
    "taxonomy_tags_selection": "Technology, AI"
  }
}
```

### Social Media Handlers
```json
{
  "type": "publish",
  "handler": "twitter",
  "config": {}
}
```

## Error Handling

The ExecuteWorkflow tool provides comprehensive error handling:

### Validation Errors
- Missing required fields
- Invalid handler slugs
- Incorrect configuration schemas
- Invalid step sequences

### Execution Errors
- Handler authentication failures
- Network connectivity issues
- API rate limiting
- Content processing errors

### Error Response Format
```json
{
  "success": false,
  "error": "Human-readable error message",
  "tool_name": "execute_workflow"
}
```

## Integration Points

### REST API
The tool integrates with the internal REST API at `/datamachine/v1/execute`:

```php
$request = new \WP_REST_Request('POST', '/datamachine/v1/execute');
$request->set_body_params([
    'workflow' => [
        'steps' => $workflow_steps
    ]
]);
$response = rest_do_request($request);
```

### Handler Discovery
Dynamic handler discovery via WordPress filters:

```php
$handlers = apply_filters('datamachine_handlers', [], 'fetch');
$handlers = apply_filters('datamachine_handlers', [], 'publish');
$handlers = apply_filters('datamachine_handlers', [], 'update');
```

### Settings Integration
Handler configuration via settings classes:

```php
$all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
$settings_class = $all_settings[$handler_slug] ?? null;
$fields = $settings_class::get_fields();
```

## Performance Considerations

### Validation Optimization
- Early validation to prevent unnecessary processing
- Cached handler configurations
- Efficient schema validation

### Execution Efficiency
- Direct REST API calls
- Minimal data transformation
- Parallel processing where possible

### Memory Management
- Streamlined data structures
- Efficient error handling
- Resource cleanup

## Security Features

### Input Sanitization
- All user inputs sanitized through WordPress functions
- Configuration validation against schemas
- Handler permission checks

### Execution Isolation
- Separate execution context for each workflow
- Error isolation between steps
- Secure handler communication

### Authentication Integration
- Handler-specific authentication requirements
- OAuth token validation
- API key management

## Extensibility

### Custom Handlers
New handlers automatically available to the tool through registration:

```php
// Handler registration automatically includes in ExecuteWorkflow documentation
add_filter('datamachine_handlers', function($handlers, $type) {
    $handlers['my_custom_handler'] = [
        'name' => 'My Custom Handler',
        'description' => 'Custom handler description',
        'requires_auth' => false
    ];
    return $handlers;
}, 10, 2);
```

### Custom Validation
Extend validation logic for specific requirements:

```php
add_filter('datamachine_execute_workflow_validate_step', function($validation, $step) {
    // Custom validation logic
    return $validation;
}, 10, 2);
```

### Documentation Customization
Modify generated documentation:

```php
add_filter('datamachine_execute_workflow_documentation', function($documentation) {
    // Add custom documentation sections
    return $documentation . "\n## Custom Section\nCustom content here.";
});
```

## Future Enhancements

Planned improvements to the ExecuteWorkflow tool:

- **Workflow Templates**: Pre-built workflow templates for common use cases
- **Conditional Logic**: Support for conditional step execution
- **Loop Support**: Iterative processing capabilities
- **Variable Substitution**: Dynamic value injection between steps
- **Workflow Scheduling**: Schedule workflow execution
- **Progress Tracking**: Real-time execution progress updates
- **Rollback Capabilities**: Automatic rollback on failure
- **Performance Metrics**: Execution analytics and optimization suggestions