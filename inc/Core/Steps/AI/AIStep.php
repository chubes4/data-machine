<?php

namespace DataMachine\Core\Steps\AI;

use DataMachine\Core\Steps\AI\AIStepConversationManager;

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
     * Execute AI step with multi-turn conversation processing.
     *
     * Processes user messages and data packets through AI agents with tool execution,
     * maintaining conversation state across multiple turns until completion or max turns reached.
     *
     * @param array $parameters Flat parameter structure containing:
     *                         - job_id: Current job identifier
     *                         - flow_step_id: Flow step identifier
     *                         - data: Data packet array for processing
     *                         - flow_step_config: Step configuration including user_message and pipeline_step_id
     *                         - file_path: Optional file for multimodal input
     *                         - mime_type: File MIME type when file_path provided
     * @return array Updated data packet array with AI processing results
     */
    public function execute(array $parameters): array {
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        try {
            $user_message = trim($flow_step_config['user_message'] ?? '');
            $file_path = $parameters['file_path'] ?? null;
            $mime_type = $parameters['mime_type'] ?? null;
            
            $messages = [];
            
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
                    'content' => 'ORIGINAL REQUEST (for context): ' . $user_message
                ];
            }
            
            if (!empty($data)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => json_encode(['data_packets' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ];
            }
            
            
            if (empty($flow_step_config['pipeline_step_id'])) {
                do_action('dm_log', 'error', 'AI Agent: Missing required pipeline_step_id from pipeline configuration', [
                    'flow_step_id' => $flow_step_id,
                    'flow_step_config' => $flow_step_config
                ]);
                throw new \RuntimeException("AI Agent requires pipeline_step_id from pipeline configuration for step-aware AI client operation");
            }
            $pipeline_step_id = $flow_step_config['pipeline_step_id'];
            
            $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);

            $previous_flow_step_id = apply_filters('dm_get_previous_flow_step_id', null, $flow_step_id);
            $previous_step_config = $previous_flow_step_id ? apply_filters('dm_get_flow_step_config', [], $previous_flow_step_id) : null;

            $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
            $next_step_config = $next_flow_step_id ? apply_filters('dm_get_flow_step_config', [], $next_flow_step_id) : null;
            
            $available_tools = AIStepTools::getAvailableTools($previous_step_config, $next_step_config, $pipeline_step_id);
            $ai_request = [
                'messages' => $messages
            ];
            
            if (!empty($step_ai_config['model'])) {
                $ai_request['model'] = $step_ai_config['model'];
            }
            $provider_name = $step_ai_config['selected_provider'] ?? '';
            if (empty($provider_name)) {
                $error_message = 'AI step not configured: No provider selected';
                do_action('dm_log', 'error', 'AI Agent: No provider configured', [
                    'flow_step_id' => $flow_step_id,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
                throw new \Exception($error_message);
            }
            // Format tools for AI provider API
            $ai_provider_tools = [];
            foreach ($available_tools as $tool_name => $tool_config) {
                $ai_provider_tools[] = [
                    'name' => $tool_name,
                    'description' => $tool_config['description'] ?? '',
                    'parameters' => $tool_config['parameters'] ?? []
                ];
            }
            
            $conversation_messages = $messages;
            
            $conversation_complete = false;
            $max_turns = 8;
            $turn_count = 0;

            do {
                $turn_count++;
                
                if ($turn_count > 1) {
                    $conversation_messages = AIStepConversationManager::updateDataPacketMessages($conversation_messages, $data);
                }
                
                
                $current_request = [
                    'messages' => $conversation_messages,
                    'model' => $step_ai_config['model'] ?? null
                ];
                
                do_action('dm_log', 'debug', 'AI Agent: Full conversation being sent to AI', [
                    'flow_step_id' => $flow_step_id,
                    'turn_count' => $turn_count,
                    'message_count' => count($current_request['messages']),
                    'messages' => $current_request['messages']
                ]);
                
                $ai_response = apply_filters('ai_request', $current_request, $provider_name, null, $ai_provider_tools, $pipeline_step_id);

                if (!$ai_response['success']) {
                    $error_message = 'AI processing failed: ' . ($ai_response['error'] ?? 'Unknown error');
                    do_action('dm_log', 'error', 'AI Agent: Processing failed on turn ' . $turn_count, [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'error' => $ai_response['error'] ?? 'Unknown error',
                        'provider' => $ai_response['provider'] ?? 'Unknown'
                    ]);
                    
                    do_action('dm_fail_job', $job_id, 'ai_processing_failed', [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'ai_error' => $ai_response['error'] ?? 'Unknown error',
                        'ai_provider' => $ai_response['provider'] ?? 'Unknown'
                    ]);
                    
                    return [];
                }

                $tool_calls = $ai_response['data']['tool_calls'] ?? [];
                $ai_content = $ai_response['data']['content'] ?? '';
                if (!empty($ai_content) || !empty($tool_calls)) {
                    if (!empty($ai_content)) {
                        $content_lines = explode("\n", trim($ai_content), 2);
                        $ai_title = (strlen($content_lines[0]) <= 100) ? $content_lines[0] : "AI Response - Turn {$turn_count}";
                        $response_body = $ai_content;
                    } else {
                        $ai_title = "AI Tool Execution - Turn {$turn_count}";
                        $tool_names = array_column($tool_calls, 'name');
                        $response_body = "AI executed " . count($tool_calls) . " tool(s): " . implode(', ', $tool_names);
                    }
                    
                    $data = apply_filters('dm_data_packet', $data, [
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
                            'tool_count' => count($tool_calls),
                            'ai_model' => $ai_response['data']['model'] ?? 'unknown',
                            'ai_provider' => $ai_response['provider'] ?? 'unknown'
                        ]
                    ], $flow_step_id, 'ai');
                    
                    if (!empty($ai_content)) {
                        array_push($conversation_messages, AIStepConversationManager::buildConversationMessage('assistant', $ai_content));
                    }
                }
                
                if (!empty($tool_calls)) {
                    $handler_tool_executed = false;
                    
                    foreach ($tool_calls as $tool_call) {
                        $tool_name = $tool_call['name'] ?? '';
                        $tool_parameters = $tool_call['parameters'] ?? [];
                        if (empty($tool_name)) {
                            do_action('dm_log', 'warning', 'AI Agent: Tool call missing name', [
                                'flow_step_id' => $flow_step_id,
                                'turn_count' => $turn_count,
                                'tool_call' => $tool_call
                            ]);
                            continue;
                        }

                        // Validate for duplicate tool calls with identical parameters
                        $validation_result = AIStepConversationManager::validateToolCall(
                            $tool_name, $tool_parameters, $conversation_messages
                        );

                        if ($validation_result['is_duplicate']) {
                            // Add gentle correction message and skip tool execution
                            $correction_message = AIStepConversationManager::generateDuplicateToolCallMessage($tool_name);
                            array_push($conversation_messages, $correction_message);

                            do_action('dm_log', 'info', 'AI Agent: Duplicate tool call prevented', [
                                'flow_step_id' => $flow_step_id,
                                'turn_count' => $turn_count,
                                'tool_name' => $tool_name,
                                'duplicate_prevention' => 'soft_rejection_applied'
                            ]);

                            continue; // Skip this tool execution, let AI try again
                        }

                        $tool_call_message = AIStepConversationManager::formatToolCallMessage(
                            $tool_name, $tool_parameters, $turn_count
                        );
                        array_push($conversation_messages, $tool_call_message);

                        // Build unified parameters - handler tools get engine parameters, general tools get clean parameters
                        $unified_parameters = [
                            'data' => $data,
                            'flow_step_config' => $flow_step_config,
                            'job_id' => $parameters['job_id'],
                            'flow_step_id' => $parameters['flow_step_id']
                        ];

                        if (isset($available_tools[$tool_name]['handler'])) {
                            $unified_parameters = array_merge($unified_parameters, $parameters);
                        }

                        $tool_result = AIStepTools::executeTool($tool_name, $tool_parameters, $available_tools, $data, $flow_step_id, $unified_parameters);
                        $tool_def = $available_tools[$tool_name] ?? null;
                        $is_handler_tool = $tool_def && isset($tool_def['handler']);
                        
                        $tool_result_message = AIStepConversationManager::formatToolResultMessage(
                            $tool_name, $tool_result, $tool_parameters, $is_handler_tool, $turn_count
                        );
                        array_push($conversation_messages, $tool_result_message);
                        
                        if ($is_handler_tool && $tool_result['success']) {
                            $handler_tool_executed = true;
                            $clean_tool_parameters = $tool_parameters;
                            $handler_config = $tool_def['handler_config'] ?? [];
                            
                            $handler_key = $tool_def['handler'] ?? $tool_name;
                            if (isset($clean_tool_parameters[$handler_key])) {
                                unset($clean_tool_parameters[$handler_key]);
                            }
                            $tool_result_entry = [
                                'type' => 'ai_handler_complete',
                                'content' => [
                                    'title' => 'Handler Tool Executed: ' . $tool_name,
                                    'body' => 'Tool executed successfully by AI agent in ' . $turn_count . ' conversation turns'
                                ],
                                'metadata' => [
                                    'tool_name' => $tool_name,
                                    'handler_tool' => $tool_def['handler'] ?? null,
                                    'tool_result' => $tool_result['data'] ?? [],
                                    'tool_parameters' => $clean_tool_parameters,
                                    'handler_config' => $handler_config,
                                    'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown',
                                    'flow_step_id' => $flow_step_id,
                                    'conversation_turn' => $turn_count,
                                    'ai_model' => $ai_response['data']['model'] ?? 'unknown',
                                    'ai_provider' => $ai_response['provider'] ?? 'unknown'
                                ],
                                'timestamp' => time()
                            ];
                            
                            $data = apply_filters('dm_data_packet', $data, $tool_result_entry, $flow_step_id, 'ai');
                            
                        } else {
                            $success_message = AIStepConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);
                            
                            $data = apply_filters('dm_data_packet', $data, [
                                'type' => 'tool_result',
                                'tool_name' => $tool_name,
                                'content' => [
                                    'title' => ucwords(str_replace('_', ' ', $tool_name)) . ' Result',
                                    'body' => $success_message
                                ],
                                'metadata' => [
                                    'tool_name' => $tool_name,
                                    'tool_parameters' => $tool_parameters,
                                    'tool_success' => $tool_result['success'] ?? false,
                                    'tool_result' => $tool_result['data'] ?? [],
                                    'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'
                                ]
                            ], $flow_step_id, 'ai');
                        }
                    }
                } else {
                    $conversation_complete = true;
                }
                
            } while (!$conversation_complete && $turn_count < $max_turns);
            
            if ($turn_count >= $max_turns && !$conversation_complete) {
                do_action('dm_log', 'warning', 'AI Agent: Conversation hit max turns limit', [
                    'flow_step_id' => $flow_step_id,
                    'max_turns' => $max_turns,
                    'final_turn_count' => $turn_count
                ]);
            }

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AI Agent: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $data;
        }
    }
}