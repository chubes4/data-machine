<?php

namespace DataMachine\Core\Steps\AI;

use DataMachine\Engine\AI\AIConversationLoop;
use DataMachine\Engine\AI\ConversationManager;
use DataMachine\Engine\AI\ToolExecutor;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AIStepTools.php';

/**
 * Multi-turn conversational AI agent with tool execution and completion detection.
 *
 * @package DataMachine
 */
class AIStep {

    /**
     * Execute multi-turn AI conversation with tool calling support.
     *
     * @param array $payload Unified step payload
     * @return array Updated data packet array
     */
    public function execute(array $payload): array {
        $job_id = $payload['job_id'] ?? 0;
        $flow_step_id = $payload['flow_step_id'] ?? '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        $engine_data = $payload['engine_data'] ?? [];

        $user_message = trim($flow_step_config['user_message'] ?? '');

        $file_path = null;
        $mime_type = null;
        if (!empty($data)) {
            $first_item = $data[0] ?? [];
            $file_info = $first_item['content']['file_info'] ?? null;

            if ($file_info && isset($file_info['file_path']) && file_exists($file_info['file_path'])) {
                $file_path = $file_info['file_path'];
                $mime_type = $file_info['mime_type'] ?? '';
            }
        }

        $messages = [];

        if (!empty($data)) {
            $messages[] = [
                'role' => 'user',
                'content' => json_encode(['data_packets' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
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

        if (empty($flow_step_config['pipeline_step_id'])) {
            do_action('datamachine_log', 'warning', 'AI Agent: Missing pipeline_step_id, skipping AI processing', [
                'flow_step_id' => $flow_step_id,
                'flow_step_config' => $flow_step_config
            ]);
            return $data;
        }
        $pipeline_step_id = $flow_step_config['pipeline_step_id'];

        $step_ai_config = apply_filters('datamachine_ai_config', [], $pipeline_step_id, $payload);

        $previous_flow_step_id = apply_filters('datamachine_get_previous_flow_step_id', null, $flow_step_id, $payload);
        $previous_step_config = $previous_flow_step_id ? apply_filters('datamachine_get_flow_step_config', [], $previous_flow_step_id) : null;

        $next_flow_step_id = apply_filters('datamachine_get_next_flow_step_id', null, $flow_step_id, $payload);
        $next_step_config = $next_flow_step_id ? apply_filters('datamachine_get_flow_step_config', [], $next_flow_step_id) : null;

        $available_tools = ToolExecutor::getAvailableTools($previous_step_config, $next_step_config, $pipeline_step_id);

        $provider_name = $step_ai_config['selected_provider'] ?? '';
        if (empty($provider_name)) {
            do_action('datamachine_fail_job', $job_id, 'ai_provider_missing', [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'error_message' => 'AI step requires provider configuration. Please configure an AI provider in step settings.',
                'solution' => 'Configure AI provider in pipeline step settings'
            ]);
            return $data;
        }

        // Execute conversation loop
        $loop = new AIConversationLoop();
        $loop_result = $loop->execute(
            $messages,
            $available_tools,
            $provider_name,
            $step_ai_config['model'] ?? '',
            'pipeline',
            $payload
        );

        // Check for errors
        if (isset($loop_result['error'])) {
            do_action('datamachine_fail_job', $job_id, 'ai_processing_failed', [
                'flow_step_id' => $flow_step_id,
                'ai_error' => $loop_result['error'],
                'ai_provider' => $provider_name
            ]);
            return [];
        }

        // Process loop results into data packets
        return self::processLoopResults($loop_result, $data, $payload, $available_tools);
    }

    /**
     * Process AIConversationLoop results into pipeline data packets.
     *
     * @param array $loop_result Result from AIConversationLoop
     * @param array $data Current data packet array
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
    private static function processLoopResults(array $loop_result, array $data, array $payload, array $available_tools): array {
        $flow_step_id = $payload['flow_step_id'] ?? '';
        $messages = $loop_result['messages'] ?? [];
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

                    $data = apply_filters('datamachine_data_packet', $data, [
                        'type' => 'ai_response',
                        'content' => [
                            'title' => $ai_title,
                            'body' => $response_body
                        ],
                        'metadata' => [
                            'source_type' => 'ai_response',
                            'flow_step_id' => $flow_step_id,
                            'conversation_turn' => $turn_count,
                            'has_tool_calls' => !empty($tool_calls),
                            'tool_count' => count($tool_calls)
                        ]
                    ], $flow_step_id, 'ai');
                }
            }

            // Process tool result messages
            if ($role === 'tool_result') {
                $tool_name = $message['tool_name'] ?? '';
                $tool_result = $message['result'] ?? [];
                $tool_parameters = $message['parameters'] ?? [];

                if (empty($tool_name)) {
                    continue;
                }

                $tool_def = $available_tools[$tool_name] ?? null;
                $is_handler_tool = $tool_def && isset($tool_def['handler']);

                if ($is_handler_tool && ($tool_result['success'] ?? false)) {
                    // Handler tool succeeded - mark completion
                    $clean_tool_parameters = $tool_parameters;
                    $handler_config = $tool_def['handler_config'] ?? [];

                    $handler_key = $tool_def['handler'] ?? $tool_name;
                    if (isset($clean_tool_parameters[$handler_key])) {
                        unset($clean_tool_parameters[$handler_key]);
                    }

                    $data = apply_filters('datamachine_data_packet', $data, [
                        'type' => 'ai_handler_complete',
                        'content' => [
                            'title' => 'Handler Tool Executed: ' . $tool_name,
                            'body' => 'Tool executed successfully by AI agent in ' . $turn_count . ' conversation turns'
                        ],
                        'metadata' => [
                            'tool_name' => $tool_name,
                            'handler_tool' => $tool_def['handler'] ?? null,
                            'tool_parameters' => $clean_tool_parameters,
                            'handler_config' => $handler_config,
                            'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown',
                            'flow_step_id' => $flow_step_id,
                            'conversation_turn' => $turn_count
                        ],
                        'timestamp' => time()
                    ], $flow_step_id, 'ai');

                    $handler_completed = true;
                } else {
                    // Non-handler tool or failed tool - add tool result data packet
                    $success_message = ConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);

                    $data = apply_filters('datamachine_data_packet', $data, [
                        'type' => 'tool_result',
                        'tool_name' => $tool_name,
                        'content' => [
                            'title' => ucwords(str_replace('_', ' ', $tool_name)) . ' Result',
                            'body' => $success_message
                        ],
                        'metadata' => [
                            'tool_name' => $tool_name,
                            'handler_tool' => $tool_def['handler'] ?? null,
                            'tool_parameters' => $tool_parameters,
                            'tool_success' => $tool_result['success'] ?? false,
                            'tool_result' => $tool_result['data'] ?? [],
                            'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'
                        ]
                    ], $flow_step_id, 'ai');
                }
            }
        }

        return $data;
    }
}
