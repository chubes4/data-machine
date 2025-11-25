<?php

namespace DataMachine\Core\Steps\AI;

use DataMachine\Core\DataPacket;
use DataMachine\Core\PluginSettings;
use DataMachine\Core\Steps\Step;
use DataMachine\Engine\AI\AIConversationLoop;
use DataMachine\Engine\AI\ConversationManager;
use DataMachine\Engine\AI\Tools\ToolExecutor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multi-turn conversational AI agent with tool execution and completion detection.
 *
 * @package DataMachine
 */
class AIStep extends Step {

    /**
     * Initialize AI step.
     */
    public function __construct() {
        parent::__construct('ai');
    }

    /**
     * Validate AI step configuration requirements.
     *
     * @return bool
     */
    protected function validateStepConfiguration(): bool {
        if (!isset($this->flow_step_config['pipeline_step_id']) || empty($this->flow_step_config['pipeline_step_id'])) {
            $this->log('error', 'Missing pipeline_step_id in AI step configuration', [
                'flow_step_config' => $this->flow_step_config
            ]);
            return false;
        }

        $pipeline_step_id = $this->flow_step_config['pipeline_step_id'];

        $pipeline_step_config = $this->engine->getPipelineStepConfig($pipeline_step_id);
        $provider_name = $pipeline_step_config['provider'] ?? PluginSettings::get('default_provider', '');
        if (empty($provider_name)) {
            do_action('datamachine_fail_job', $this->job_id, 'ai_provider_missing', [
                'flow_step_id' => $this->flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'error_message' => 'AI step requires provider configuration. Please configure an AI provider in step settings or set a default provider in plugin settings.',
                'solution' => 'Configure AI provider in pipeline step settings or set default provider in Data Machine settings'
            ]);
            return false;
        }

        return true;
    }

    /**
     * Execute AI step logic.
     *
     * @return array
     */
    protected function executeStep(): array {
        $user_message = trim($this->flow_step_config['user_message'] ?? '');

        $file_path = null;
        $mime_type = null;
        if (!empty($this->dataPackets)) {
            $first_item = $this->dataPackets[0] ?? [];
            $file_info = $first_item['data']['file_info'] ?? null;

            if ($file_info && isset($file_info['file_path']) && file_exists($file_info['file_path'])) {
                $file_path = $file_info['file_path'];
                $mime_type = $file_info['mime_type'] ?? '';
            }
        }

        $messages = [];

        if (!empty($this->dataPackets)) {
            $messages[] = [
                'role' => 'user',
                'content' => json_encode(['data_packets' => $this->dataPackets], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ];
        }

        if ($file_path && file_exists($file_path)) {
            $messages[] = [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'file',
                        'file_path' => $file_path,
                        'mime_type' => $mime_type ?? ''
                    ]
                ]
            ];
        }

        if (!empty($user_message)) {
            $messages[] = [
                'role' => 'user',
                'content' => $user_message
            ];
        }

        $pipeline_step_id = $this->flow_step_config['pipeline_step_id'];

        $pipeline_step_config = $this->engine->getPipelineStepConfig($pipeline_step_id);

        $max_turns = PluginSettings::get('max_turns', 12);

        $payload = [
            'job_id' => $this->job_id,
            'flow_step_id' => $this->flow_step_id,
            'step_id' => $pipeline_step_id,
            'data' => $this->dataPackets,
            'engine' => $this->engine
        ];

        $navigator = new \DataMachine\Engine\StepNavigator();
        $previous_flow_step_id = $navigator->get_previous_flow_step_id($this->flow_step_id, $payload);

        $previous_step_config = $previous_flow_step_id ? $this->engine->getFlowStepConfig($previous_flow_step_id) : null;

        $next_flow_step_id = $navigator->get_next_flow_step_id($this->flow_step_id, $payload);
        $next_step_config = $next_flow_step_id ? $this->engine->getFlowStepConfig($next_flow_step_id) : null;

        $available_tools = ToolExecutor::getAvailableTools($previous_step_config, $next_step_config, $pipeline_step_id);

        $provider_name = $pipeline_step_config['provider'] ?? $settings['default_provider'] ?? '';

        // Execute conversation loop
        $loop = new AIConversationLoop();
        $loop_result = $loop->execute(
            $messages,
            $available_tools,
            $provider_name,
            $pipeline_step_config['model'] ?? $settings['default_model'] ?? '',
            'pipeline',
            $payload,
            $max_turns
        );

        // Check for errors
        if (isset($loop_result['error'])) {
            do_action('datamachine_fail_job', $this->job_id, 'ai_processing_failed', [
                'flow_step_id' => $this->flow_step_id,
                'ai_error' => $loop_result['error'],
                'ai_provider' => $provider_name
            ]);
            return [];
        }

        // Process loop results into data packets
        return self::processLoopResults($loop_result, $this->dataPackets, $payload, $available_tools);
    }

    /**
     * Process AI conversation loop results into pipeline data packets.
     *
     * @param array $loop_result Result from AIConversationLoop
     * @param array $dataPackets Current data packet array
     * @param array $payload Step payload
     * @param array $available_tools Available tool definitions
     * @return array Updated data packet array
     */
    /**
     * Process AI conversation loop results into data packets.
     *
     * @param array $loop_result Results from AIConversationLoop
     * @param array $data Current data packet array
     * @param array $payload Step payload
     * @param array $available_tools Tools available during conversation
     * @return array Updated data packet array
     */
    private static function processLoopResults(array $loop_result, array $dataPackets, array $payload, array $available_tools): array {
        if (!isset($payload['flow_step_id']) || empty($payload['flow_step_id'])) {
            throw new \InvalidArgumentException('Flow step ID is required in AI step payload');
        }

        $flow_step_id = $payload['flow_step_id'];
        $messages = $loop_result['messages'] ?? [];
        $tool_execution_results = $loop_result['tool_execution_results'] ?? [];
        $turn_count = 0;
        $handler_completed = false;

        // Process conversation messages to build data packets
        foreach ($messages as $message) {
            $role = $message['role'] ?? '';

            // Track turns by counting assistant messages
            if ($role === 'assistant') {
                $turn_count++;
            }

            // Process assistant responses (AI content or tool calls)
            if ($role === 'assistant') {
                $content = $message['content'] ?? '';
                $tool_calls = $message['tool_calls'] ?? [];

                if (!empty($content) || !empty($tool_calls)) {
                    if (!empty($content)) {
                        $content_lines = explode("\n", trim($content), 2);
                        $ai_title = (strlen($content_lines[0]) <= 100) ? $content_lines[0] : "AI Response - Turn {$turn_count}";
                        $response_body = $content;
                    } else {
                        $ai_title = "AI Tool Execution - Turn {$turn_count}";
                        $tool_names = array_column($tool_calls, 'name');
                        $response_body = "AI executed " . count($tool_calls) . " tool(s): " . implode(', ', $tool_names);
                    }

                    $packet = new DataPacket(
                        [
                            'title' => $ai_title,
                            'body' => $response_body
                        ],
                        [
                            'source_type' => 'ai_response',
                            'flow_step_id' => $flow_step_id,
                            'conversation_turn' => $turn_count,
                            'has_tool_calls' => !empty($tool_calls),
                            'tool_count' => count($tool_calls)
                        ],
                        'ai_response'
                    );
                    $dataPackets = $packet->addTo($dataPackets);
                }
            }

        }

        // Process tool execution results into data packets
        foreach ($tool_execution_results as $tool_result_data) {
            $tool_name = $tool_result_data['tool_name'] ?? '';
            $tool_result = $tool_result_data['result'] ?? [];
            $tool_parameters = $tool_result_data['parameters'] ?? [];
            $is_handler_tool = $tool_result_data['is_handler_tool'] ?? false;
            $result_turn_count = $tool_result_data['turn_count'] ?? $turn_count;

            if (empty($tool_name)) {
                continue;
            }

            $tool_def = $available_tools[$tool_name] ?? null;

            if ($is_handler_tool && ($tool_result['success'] ?? false)) {
                // Handler tool succeeded - mark completion
                $clean_tool_parameters = $tool_parameters;
                $handler_config = $tool_def['handler_config'] ?? [];

                $handler_key = $tool_def['handler'] ?? $tool_name;
                if (isset($clean_tool_parameters[$handler_key])) {
                    unset($clean_tool_parameters[$handler_key]);
                }

                $packet = new DataPacket(
                    [
                        'title' => 'Handler Tool Executed: ' . $tool_name,
                        'body' => 'Tool executed successfully by AI agent in ' . $result_turn_count . ' conversation turns'
                    ],
                    [
                        'tool_name' => $tool_name,
                        'handler_tool' => $tool_def['handler'] ?? null,
                        'tool_parameters' => $clean_tool_parameters,
                        'handler_config' => $handler_config,
                        'source_type' => $dataPackets[0]['metadata']['source_type'] ?? 'unknown',
                        'flow_step_id' => $flow_step_id,
                        'conversation_turn' => $result_turn_count,
                        'tool_result' => $tool_result
                    ],
                    'ai_handler_complete'
                );
                $dataPackets = $packet->addTo($dataPackets);

                $handler_completed = true;
            } else {
                // Non-handler tool or failed tool - add tool result data packet
                $success_message = ConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);

                $packet = new DataPacket(
                    [
                        'title' => ucwords(str_replace('_', ' ', $tool_name)) . ' Result',
                        'body' => $success_message
                    ],
                    [
                        'tool_name' => $tool_name,
                        'handler_tool' => $tool_def['handler'] ?? null,
                        'tool_parameters' => $tool_parameters,
                        'tool_success' => $tool_result['success'] ?? false,
                        'tool_result' => $tool_result['data'] ?? [],
                        'source_type' => $dataPackets[0]['metadata']['source_type'] ?? 'unknown'
                    ],
                    'tool_result'
                );
                $dataPackets = $packet->addTo($dataPackets);
            }
        }

        return $dataPackets;
    }
}
