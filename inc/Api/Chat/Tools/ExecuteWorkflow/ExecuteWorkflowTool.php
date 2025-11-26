<?php
/**
 * Execute Workflow Tool
 *
 * Primary action tool for executing content automation workflows.
 * Validates, injects defaults, and executes via the internal REST API.
 *
 * @package DataMachine\Api\Chat\Tools\ExecuteWorkflow
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools\ExecuteWorkflow;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

class ExecuteWorkflowTool {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'execute_workflow', $this->getToolDefinition());
    }

    /**
     * Get tool definition with dynamic documentation.
     *
     * @return array Tool definition
     */
    private function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => DocumentationBuilder::build(),
            'parameters' => [
                'steps' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Workflow steps to execute. Each step needs: type (fetch|ai|publish|update), handler (for non-ai steps), config (handler settings), user_message (for ai steps)'
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

        $validation = WorkflowValidator::validate($steps);
        if (!$validation['valid']) {
            do_action('datamachine_log', 'error', 'ExecuteWorkflowTool: Validation failed', [
                'error' => $validation['error'],
                'steps' => $steps
            ]);
            return [
                'success' => false,
                'error' => $validation['error'],
                'tool_name' => 'execute_workflow'
            ];
        }

        $steps = DefaultsInjector::inject($steps);

        $workflow_steps = $this->transformSteps($steps);

        $request = new \WP_REST_Request('POST', '/datamachine/v1/execute');
        $request->set_body_params([
            'workflow' => [
                'steps' => $workflow_steps
            ]
        ]);

        $response = rest_do_request($request);

        if (is_wp_error($response)) {
            do_action('datamachine_log', 'error', 'ExecuteWorkflowTool: REST request failed', [
                'error' => $response->get_error_message(),
                'steps' => $workflow_steps
            ]);
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'tool_name' => 'execute_workflow'
            ];
        }

        $data = $response->get_data();
        $status = $response->get_status();

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

    /**
     * Transform simplified step format to full workflow JSON.
     *
     * @param array $steps Simplified steps
     * @return array Full workflow steps
     */
    private function transformSteps(array $steps): array {
        $transformed = [];

        foreach ($steps as $step) {
            $workflow_step = [
                'type' => $step['type']
            ];

            if ($step['type'] === 'ai') {
                $workflow_step['provider'] = $step['provider'] ?? 'anthropic';
                $workflow_step['model'] = $step['model'] ?? 'claude-sonnet-4-20250514';

                if (!empty($step['user_message'])) {
                    $workflow_step['user_message'] = $step['user_message'];
                }
                if (!empty($step['system_prompt'])) {
                    $workflow_step['system_prompt'] = $step['system_prompt'];
                }
            } else {
                $workflow_step['handler_slug'] = $step['handler'];
                $workflow_step['config'] = $step['config'] ?? [];
            }

            $transformed[] = $workflow_step;
        }

        return $transformed;
    }
}

new ExecuteWorkflowTool();
