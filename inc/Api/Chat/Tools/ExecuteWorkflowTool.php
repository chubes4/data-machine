<?php
/**
 * Execute Workflow Tool
 *
 * Chat tool for executing content automation workflows.
 * Passes workflow steps directly to the Execute API endpoint.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

class ExecuteWorkflowTool {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'execute_workflow', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        $handler_docs = HandlerDocumentation::buildAllHandlersSections();
        $step_types = apply_filters('datamachine_step_types', []);
        $type_slugs = !empty($step_types) ? array_keys($step_types) : ['fetch', 'ai', 'publish', 'update'];
        $types_list = implode('|', $type_slugs);

        $description = <<<DESC
Execute a content automation workflow.

IMPORTANT: Only use handler_config keys listed in the handler documentation below.

{$handler_docs}
TAXONOMY CONFIGURATION (wordpress_publish handler):
For each taxonomy, use key: taxonomy_{taxonomy_name}_selection
Values:
- "skip": Don't assign this taxonomy
- "ai_decides": AI assigns based on content at runtime
- "Term Name": Pre-select this term (use exact term name from site context)

Example: taxonomy_category_selection: "ai_decides"
Example: taxonomy_location_selection: "Charleston"

WORKFLOW PATTERNS:
- Content syndication: fetch → ai → publish
- Content enhancement: fetch → ai → update
- Event import: event_import → ai → event_upsert
- Multi-platform: fetch → ai → publish → ai → publish

STEP FORMAT:
{
  "type": "{$types_list}",
  "handler_slug": "handler_slug",
  "handler_config": {...},
  "user_message": "...",
  "system_prompt": "..."
}

EXAMPLE:
steps: [
  {"type": "fetch", "handler_slug": "rss", "handler_config": {"feed_url": "https://example.com/feed"}},
  {"type": "ai", "user_message": "Summarize this content for social media"},
  {"type": "publish", "handler_slug": "wordpress_publish", "handler_config": {"post_type": "post", "post_status": "draft"}}
]
DESC;

        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => $description,
            'parameters' => [
                'steps' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Array of step objects. Each step requires: type, handler_slug (for non-AI steps), handler_config. AI steps use user_message instead.'
                ]
            ]
        ];
    }

    /**
     * Execute the workflow.
     *
     * @param array $parameters Tool parameters containing steps
     * @param array $tool_def Tool definition (unused)
     * @return array Execution result
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $steps = $parameters['steps'] ?? [];

        if (empty($steps)) {
            return [
                'success' => false,
                'error' => 'Workflow must contain at least one step',
                'tool_name' => 'execute_workflow'
            ];
        }

        $request = new \WP_REST_Request('POST', '/datamachine/v1/execute');
        $request->set_body_params([
            'workflow' => ['steps' => $steps]
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();
        $status = $response->get_status();

        if ($response->is_error()) {
            $error = $response->as_error();
            do_action('datamachine_log', 'error', 'ExecuteWorkflowTool: REST request failed', [
                'error' => $error->get_error_message(),
                'steps' => $steps
            ]);
            return [
                'success' => false,
                'error' => $error->get_error_message(),
                'tool_name' => 'execute_workflow'
            ];
        }

        if ($status >= 400) {
            $error_message = $data['message'] ?? 'Execution failed';
            do_action('datamachine_log', 'error', 'ExecuteWorkflowTool: Execution failed', [
                'status' => $status,
                'error' => $error_message,
                'data' => $data
            ]);
            return [
                'success' => false,
                'error' => $error_message,
                'tool_name' => 'execute_workflow'
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'tool_name' => 'execute_workflow'
        ];
    }
}

new ExecuteWorkflowTool();
