<?php
/**
 * Configure Pipeline Step Tool
 *
 * Tool for configuring pipeline-level AI step settings including
 * system prompt, provider, model, and enabled tools.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class ConfigurePipelineStep {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'configure_pipeline_step', [$this, 'getToolDefinition']);
    }

    /**
     * Get tool definition.
     * Called lazily when tool is first accessed to ensure translations are loaded.
     *
     * @return array Tool definition array
     */
    public function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Configure pipeline-level AI step settings including system prompt, provider, model, and enabled tools. Use this for AI steps after creating a pipeline. For flow-level settings (handler, handler_config, user_message), use configure_flow_step instead.',
            'parameters' => [
                'pipeline_step_id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Pipeline step ID to configure (e.g., "123_uuid4")'
                ],
                'system_prompt' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'System prompt for the AI step - defines the AI persona and instructions'
                ],
                'provider' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'AI provider slug (e.g., "anthropic", "openai")'
                ],
                'model' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'AI model identifier (e.g., "claude-sonnet-4-20250514", "gpt-4o")'
                ],
                'enabled_tools' => [
                    'type' => 'array',
                    'required' => false,
                    'description' => 'Array of tool slugs to enable for this AI step'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $pipeline_step_id = $parameters['pipeline_step_id'] ?? null;

        if (empty($pipeline_step_id) || !is_string($pipeline_step_id)) {
            return [
                'success' => false,
                'error' => 'pipeline_step_id is required and must be a string',
                'tool_name' => 'configure_pipeline_step'
            ];
        }

        $system_prompt = $parameters['system_prompt'] ?? null;
        $provider = $parameters['provider'] ?? null;
        $model = $parameters['model'] ?? null;
        $enabled_tools = $parameters['enabled_tools'] ?? null;

        if (empty($system_prompt) && empty($provider) && empty($model) && empty($enabled_tools)) {
            return [
                'success' => false,
                'error' => 'At least one of system_prompt, provider, model, or enabled_tools is required',
                'tool_name' => 'configure_pipeline_step'
            ];
        }

        $body_params = [];

        if (!empty($system_prompt)) {
            $body_params['system_prompt'] = $system_prompt;
        }
        if (!empty($provider)) {
            $body_params['provider'] = $provider;
        }
        if (!empty($model)) {
            $body_params['model'] = $model;
        }
        if (!empty($enabled_tools) && is_array($enabled_tools)) {
            $body_params['enabled_tools'] = $enabled_tools;
        }

        $request = new \WP_REST_Request('PATCH', '/datamachine/v1/pipelines/steps/' . $pipeline_step_id . '/config');
        $request->set_body_params($body_params);

        $response = rest_do_request($request);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'tool_name' => 'configure_pipeline_step'
            ];
        }

        $data = $response->get_data();
        $status = $response->get_status();

        if ($status >= 400) {
            $error_message = $data['message'] ?? 'Failed to configure pipeline step';
            return [
                'success' => false,
                'error' => $error_message,
                'tool_name' => 'configure_pipeline_step'
            ];
        }

        $response_data = [
            'pipeline_step_id' => $pipeline_step_id,
            'message' => 'Pipeline step configured successfully.'
        ];

        if (!empty($system_prompt)) {
            $response_data['system_prompt_updated'] = true;
        }
        if (!empty($provider)) {
            $response_data['provider'] = $provider;
        }
        if (!empty($model)) {
            $response_data['model'] = $model;
        }
        if (!empty($enabled_tools)) {
            $response_data['enabled_tools_updated'] = true;
        }

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'configure_pipeline_step'
        ];
    }
}
