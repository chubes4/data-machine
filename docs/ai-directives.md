# AI Directive System

Data Machine uses a modular directive system to provide context and guidance to AI agents. These directives are combined to form the system prompt for every AI request.

## Architecture

The directive system is built on a modular architecture using the following core components:

- **DirectiveInterface**: Standard interface for all directive classes.
- **PromptBuilder**: Unified manager that collects and orders directives for AI requests.
- **DirectiveRenderer**: Renders directives into the final prompt structure.
- **DirectiveOutputValidator**: Ensures directive output follows the expected schema.

## Directive Priority & Layering

Directives are layered by priority (lowest number = highest priority) to create a cohesive context:

1. **Plugin Core Directive** (Priority 10): Defines the agent's identity as the Data Machine Assistant and sets core operational principles.
2. **Global System Prompt** (Priority 20): Provides high-level system instructions and safety guidelines.
3. **Pipeline System Prompt** (Priority 30): Instructions specific to pipeline operations and step execution.
4. **Pipeline Context** (Priority 35): Injects metadata about the current pipeline (ID, name, description, steps).
5. **Site Context** (Priority 50): Provides information about the WordPress site (name, URL, active plugins, theme).

## Specialized Directives

### Chat Agent Directive
Specialized directive for the conversational chat interface. It instructs the agent on discovery and configuration patterns, emphasizing querying existing workflows before creating new ones.

### Chat Pipelines Directive
Provides the conversational agent with an inventory of available pipelines. When a pipeline is selected in the UI, `selected_pipeline_id` is used to prioritize and expand context for that specific pipeline, including its flow summaries and handler configurations.

## Registration

Directives are registered via WordPress filters:

```php
add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'id'       => 'my-custom-directive',
        'priority' => 40,
        'class'    => MyCustomDirective::class,
    ];
    return $directives;
});
```

## Implementation Notes

- Directives should be read-only and never mutate the AI request structure directly.
- Use `DirectiveOutputValidator` to ensure responses from the AI follow the correct `system_text` or `system_json` formats.
- Context injection should be minimal and focused on what the agent needs for the current task.
