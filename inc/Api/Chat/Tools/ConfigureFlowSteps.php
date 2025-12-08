<?php
/**
 * Configure Flow Steps Tool
 *
 * Configures handler settings or AI user messages on flow steps.
 * Supports both single-step and bulk pipeline-scoped operations.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.4.2
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowStepManager;
use DataMachine\Core\Database\Flows\Flows as FlowsDB;

class ConfigureFlowSteps {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'configure_flow_steps', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        $handler_docs = HandlerDocumentation::buildAllHandlersSections();

        $description = 'Configure flow steps with handlers or AI user messages. Supports single-step or bulk pipeline-scoped operations.' . "\n\n"
            . 'MODES:' . "\n"
            . '- Single: Provide flow_step_id to configure one step' . "\n"
            . '- Bulk: Provide pipeline_id + (step_type and/or handler_slug) to configure all matching steps across all flows' . "\n\n"
            . 'IMPORTANT: Only use handler_config fields documented below. Do not invent parameters.' . "\n\n"
            . $handler_docs;

        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => $description,
            'parameters' => [
                'flow_step_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Flow step ID for single-step mode (e.g., "pipeline_step_123_42")'
                ],
                'pipeline_id' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Pipeline ID for bulk mode - applies to all matching steps across all flows'
                ],
                'step_type' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Filter by step type (fetch, publish, update, ai) - required for bulk mode unless handler_slug provided'
                ],
                'handler_slug' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Handler slug to set (single mode) or filter by (bulk mode)'
                ],
                'handler_config' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Handler-specific configuration to merge into existing config'
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
        $pipeline_id = isset($parameters['pipeline_id']) ? (int) $parameters['pipeline_id'] : null;
        $step_type = $parameters['step_type'] ?? null;
        $handler_slug = $parameters['handler_slug'] ?? null;
        $handler_config = $parameters['handler_config'] ?? [];
        $user_message = $parameters['user_message'] ?? null;

        // Validation: One of flow_step_id OR pipeline_id required
        if (empty($flow_step_id) && empty($pipeline_id)) {
            return [
                'success' => false,
                'error' => 'Either flow_step_id (single mode) or pipeline_id (bulk mode) is required',
                'tool_name' => 'configure_flow_steps'
            ];
        }

        // Validation: At least one of handler_config or user_message required
        if (empty($handler_config) && empty($user_message)) {
            return [
                'success' => false,
                'error' => 'At least one of handler_config or user_message is required',
                'tool_name' => 'configure_flow_steps'
            ];
        }

        // Validation: When pipeline_id provided, need step_type or handler_slug
        if (!empty($pipeline_id) && empty($step_type) && empty($handler_slug)) {
            return [
                'success' => false,
                'error' => 'When using pipeline_id (bulk mode), at least one of step_type or handler_slug is required',
                'tool_name' => 'configure_flow_steps'
            ];
        }

        // Route to appropriate handler
        if (!empty($flow_step_id)) {
            return $this->handleSingleMode($flow_step_id, $handler_slug, $handler_config, $user_message);
        }

        return $this->handleBulkMode($pipeline_id, $step_type, $handler_slug, $handler_config, $user_message);
    }

    /**
     * Handle single flow step configuration.
     */
    private function handleSingleMode(string $flow_step_id, ?string $handler_slug, array $handler_config, ?string $user_message): array {
        $flow_step_manager = new FlowStepManager();
        $results = [];

        if (!empty($handler_slug) || !empty($handler_config)) {
            // If handler_config provided but no handler_slug, we need to get existing handler_slug
            $effective_handler_slug = $handler_slug;
            if (empty($effective_handler_slug) && !empty($handler_config)) {
                $existing_step = $flow_step_manager->get($flow_step_id);
                if ($existing_step) {
                    $effective_handler_slug = $existing_step['handler_slug'] ?? null;
                }
            }

            if (empty($effective_handler_slug)) {
                return [
                    'success' => false,
                    'error' => 'handler_slug is required when setting handler_config on a step without an existing handler',
                    'tool_name' => 'configure_flow_steps'
                ];
            }

            $handler_success = $flow_step_manager->updateHandler($flow_step_id, $effective_handler_slug, $handler_config);
            if (!$handler_success) {
                return [
                    'success' => false,
                    'error' => 'Failed to update handler. Verify flow_step_id is valid.',
                    'tool_name' => 'configure_flow_steps'
                ];
            }
            $results['handler_updated'] = true;
            $results['handler_slug'] = $effective_handler_slug;
        }

        if (!empty($user_message)) {
            $message_success = $flow_step_manager->updateUserMessage($flow_step_id, $user_message);
            if (!$message_success) {
                return [
                    'success' => false,
                    'error' => 'Failed to update user message. Verify flow_step_id is valid and belongs to an AI step.',
                    'tool_name' => 'configure_flow_steps'
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
            'tool_name' => 'configure_flow_steps'
        ];
    }

    /**
     * Handle bulk pipeline-scoped configuration.
     */
    private function handleBulkMode(int $pipeline_id, ?string $step_type, ?string $handler_slug, array $handler_config, ?string $user_message): array {
        $flows_db = new FlowsDB();
        $flow_step_manager = new FlowStepManager();

        // Get all flows for this pipeline
        $flows = $flows_db->get_flows_for_pipeline($pipeline_id);
        if (empty($flows)) {
            return [
                'success' => false,
                'error' => 'No flows found for pipeline_id ' . $pipeline_id,
                'tool_name' => 'configure_flow_steps'
            ];
        }

        $updated_details = [];
        $errors = [];

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_name = $flow['flow_name'] ?? __('Unnamed Flow', 'datamachine');
            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => $step_config) {
                // Filter by step_type if provided
                if (!empty($step_type)) {
                    $config_step_type = $step_config['step_type'] ?? null;
                    if ($config_step_type !== $step_type) {
                        continue;
                    }
                }

                // Filter by handler_slug if provided (for filtering, not setting)
                if (!empty($handler_slug)) {
                    $config_handler_slug = $step_config['handler_slug'] ?? null;
                    if ($config_handler_slug !== $handler_slug) {
                        continue;
                    }
                }

                // Apply handler_config update
                if (!empty($handler_config)) {
                    $effective_handler_slug = $handler_slug ?? ($step_config['handler_slug'] ?? null);
                    if (empty($effective_handler_slug)) {
                        $errors[] = [
                            'flow_step_id' => $flow_step_id,
                            'error' => 'Step has no handler_slug configured'
                        ];
                        continue;
                    }

                    $success = $flow_step_manager->updateHandler($flow_step_id, $effective_handler_slug, $handler_config);
                    if (!$success) {
                        $errors[] = [
                            'flow_step_id' => $flow_step_id,
                            'error' => 'Failed to update handler'
                        ];
                        continue;
                    }
                }

                // Apply user_message update
                if (!empty($user_message)) {
                    $success = $flow_step_manager->updateUserMessage($flow_step_id, $user_message);
                    if (!$success) {
                        $errors[] = [
                            'flow_step_id' => $flow_step_id,
                            'error' => 'Failed to update user message'
                        ];
                        continue;
                    }
                }

                $updated_details[] = [
                    'flow_id' => $flow_id,
                    'flow_name' => $flow_name,
                    'flow_step_id' => $flow_step_id
                ];
            }
        }

        $flows_updated = count(array_unique(array_column($updated_details, 'flow_id')));
        $steps_modified = count($updated_details);

        if ($steps_modified === 0 && !empty($errors)) {
            return [
                'success' => false,
                'error' => 'No steps were updated. ' . count($errors) . ' error(s) occurred.',
                'errors' => $errors,
                'tool_name' => 'configure_flow_steps'
            ];
        }

        if ($steps_modified === 0) {
            return [
                'success' => false,
                'error' => 'No matching steps found for the specified criteria',
                'tool_name' => 'configure_flow_steps'
            ];
        }

        $response = [
            'success' => true,
            'data' => [
                'pipeline_id' => $pipeline_id,
                'flows_updated' => $flows_updated,
                'steps_modified' => $steps_modified,
                'details' => $updated_details,
                'message' => sprintf('Updated %d step(s) across %d flow(s).', $steps_modified, $flows_updated)
            ],
            'tool_name' => 'configure_flow_steps'
        ];

        if (!empty($errors)) {
            $response['data']['errors'] = $errors;
        }

        return $response;
    }
}

new ConfigureFlowSteps();
