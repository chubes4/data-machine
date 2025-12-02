<?php
/**
 * Configure Flow Step Tool
 *
 * Focused tool for configuring handler settings or AI user messages on flow steps.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowStepManager;

class ConfigureFlowStep {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'configure_flow_step', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        $handler_docs = HandlerDocumentation::buildAllHandlersSections();
        
        $description = <<<DESC
Configure a flow step with a handler (for fetch/publish/update steps) or user message (for AI steps). Use flow_step_ids returned from create_flow.

IMPORTANT: Only use handler_config fields documented below. Do not invent parameters.

{$handler_docs}
DESC;

        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => $description,
            'parameters' => [
                'flow_step_id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Flow step ID (e.g., "pipeline_step_123_42")'
                ],
                'handler_slug' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Handler slug for fetch/publish/update steps'
                ],
                'handler_config' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Handler-specific configuration. Use only the fields documented above for each handler.'
                ],
                'user_message' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'User message/prompt for AI steps'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $flow_step_id = $parameters['flow_step_id'] ?? null;

        if (empty($flow_step_id) || !is_string($flow_step_id)) {
            return [
                'success' => false,
                'error' => 'flow_step_id is required and must be a string',
                'tool_name' => 'configure_flow_step'
            ];
        }

        $handler_slug = $parameters['handler_slug'] ?? null;
        $handler_config = $parameters['handler_config'] ?? [];
        $user_message = $parameters['user_message'] ?? null;

        if (empty($handler_slug) && empty($user_message)) {
            return [
                'success' => false,
                'error' => 'Either handler_slug (for fetch/publish/update steps) or user_message (for AI steps) is required',
                'tool_name' => 'configure_flow_step'
            ];
        }

        $flow_step_manager = new FlowStepManager();
        $results = [];

        if (!empty($handler_slug)) {
            $handler_success = $flow_step_manager->updateHandler($flow_step_id, $handler_slug, $handler_config);
            if (!$handler_success) {
                return [
                    'success' => false,
                    'error' => 'Failed to update handler. Verify flow_step_id is valid.',
                    'tool_name' => 'configure_flow_step'
                ];
            }
            $results['handler_updated'] = true;
            $results['handler_slug'] = $handler_slug;
        }

        if (!empty($user_message)) {
            $message_success = $flow_step_manager->updateUserMessage($flow_step_id, $user_message);
            if (!$message_success) {
                return [
                    'success' => false,
                    'error' => 'Failed to update user message. Verify flow_step_id is valid and belongs to an AI step.',
                    'tool_name' => 'configure_flow_step'
                ];
            }
            $results['user_message_updated'] = true;
        }

        return [
            'success' => true,
            'data' => array_merge([
                'flow_step_id' => $flow_step_id,
                'message' => 'Flow step configured successfully.'
            ], $results),
            'tool_name' => 'configure_flow_step'
        ];
    }
}

new ConfigureFlowStep();
