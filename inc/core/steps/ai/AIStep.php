<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AIStepTools.php';

/**
 * AI Processing Step
 *
 * Executes multi-turn AI conversations with dynamic tool discovery and workflow integration.
 * Supports standalone execution via flow-level user messages or data-driven processing.
 * 
 * Features:
 * - Tool discovery based on next step handler type
 * - Multi-turn conversation management with 8-turn limit
 * - Automatic parameter building via AIStepToolParameters
 * - Handler tool completion detection
 * - Context-aware message building from data packet history
 * - AI directive injection for workflow guidance
 */
class AIStep {


    /**
     * Execute AI processing step with tool-based execution
     * 
     * Processes data packets through multi-turn AI conversations with automatic tool discovery
     * and workflow-aware directive injection.
     * 
     * @param array $parameters Flat parameter structure from dm_engine_parameters filter:
     *   - job_id: Job execution identifier
     *   - flow_step_id: Flow step identifier
     *   - flow_step_config: Step configuration data
     *   - data: Data packet array for processing
     *   - Additional parameters: source_url, image_url, file_path, mime_type (as available)
     * @return array Updated data packet array with AI responses and tool execution results
     */
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        try {
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'AI Agent: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            if (empty($data)) {
                $user_message = trim($flow_step_config['user_message'] ?? '');
                if (empty($user_message)) {
                    do_action('dm_log', 'error', 'AI Agent: No data found and no user message configured', ['flow_step_id' => $flow_step_id]);
                    return $data;
                }
            }
            // Build simple message structure with raw data packets
            $user_message = trim($flow_step_config['user_message'] ?? '');
            $file_path = $parameters['file_path'] ?? null;
            $mime_type = $parameters['mime_type'] ?? null;
            
            $messages = [];
            
            // Handle file input
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
            
            // Add user message if provided
            if (!empty($user_message)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $user_message
                ];
            }
            
            // Add data packets as structured JSON
            if (!empty($data)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => json_encode(['data_packets' => $data], JSON_PRETTY_PRINT)
                ];
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                do_action('dm_log', 'error', 'AI Agent: No processable content found', [
                    'flow_step_id' => $flow_step_id,
                    'has_data' => !empty($data),
                    'has_user_message' => !empty($user_message),
                    'has_file' => !empty($file_path)
                ]);
                return $data;
            }
            
            
            // Pipeline step ID required for AI HTTP Client step-aware configuration
            if (empty($flow_step_config['pipeline_step_id'])) {
                do_action('dm_log', 'error', 'AI Agent: Missing required pipeline_step_id from pipeline configuration', [
                    'flow_step_id' => $flow_step_id,
                    'flow_step_config' => $flow_step_config
                ]);
                throw new \RuntimeException("AI Agent requires pipeline_step_id from pipeline configuration for step-aware AI client operation");
            }
            $pipeline_step_id = $flow_step_config['pipeline_step_id'];
            
            $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
            $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
            if ($next_flow_step_id) {
                $next_step_config = apply_filters('dm_get_flow_step_config', [], $next_flow_step_id);
                $available_tools = AIStepTools::getAvailableToolsForNextStep($next_step_config, $pipeline_step_id);
            } else {
                $available_tools = AIStepTools::getAvailableToolsForNextStep([], $pipeline_step_id);
            }
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
            
            // Transform tools to AI provider format  
            $ai_provider_tools = [];
            foreach ($available_tools as $tool_name => $tool_config) {
                $ai_provider_tools[] = [
                    'name' => $tool_name,
                    'description' => $tool_config['description'] ?? '',
                    'parameters' => $tool_config['parameters'] ?? []
                ];
            }
            
            // Local conversation state management
            $conversation_messages = $messages;
            
            $conversation_complete = false;
            $max_turns = 25;
            $turn_count = 0;

            do {
                $turn_count++;
                
                if ($turn_count > 1) {
                    // Rebuild messages with updated data packets for multi-turn
                    $conversation_messages = [];
                    
                    // Handle file input
                    if ($file_path && file_exists($file_path)) {
                        $conversation_messages[] = [
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
                    
                    // Add user message if provided
                    if (!empty($user_message)) {
                        $conversation_messages[] = [
                            'role' => 'user',
                            'content' => $user_message
                        ];
                    }
                    
                    // Add updated data packets as structured JSON
                    if (!empty($data)) {
                        $conversation_messages[] = [
                            'role' => 'user',
                            'content' => json_encode(['data_packets' => $data], JSON_PRETTY_PRINT)
                        ];
                    }
                }
                
                $current_request = [
                    'messages' => $conversation_messages,
                    'model' => $step_ai_config['model'] ?? null
                ];
                if ($turn_count > 1) {
                    $turn_context = "Turn {$turn_count}/{$max_turns}. ";
                    if ($turn_count >= 3) {
                        $turn_context .= "Complete your task using available tools.";
                    } else {
                        $turn_context .= "Use tools efficiently to complete your task.";
                    }
                    
                    $current_request['messages'][] = [
                        'role' => 'system',
                        'content' => $turn_context
                    ];
                }
                
                $ai_response = apply_filters('ai_request', $current_request, $provider_name, null, $ai_provider_tools, $pipeline_step_id);

                if (!$ai_response['success']) {
                    $error_message = 'AI processing failed: ' . ($ai_response['error'] ?? 'Unknown error');
                    do_action('dm_log', 'error', 'AI Agent: Processing failed on turn ' . $turn_count, [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'error' => $ai_response['error'] ?? 'Unknown error',
                        'provider' => $ai_response['provider'] ?? 'Unknown'
                    ]);
                    
                    // Fail the job when AI processing fails
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
                        
                        $tool_result = AIStepTools::executeTool($tool_name, $tool_parameters, $available_tools, $data, $flow_step_id, $parameters);
                        $tool_def = $available_tools[$tool_name] ?? null;
                        $is_handler_tool = $tool_def && isset($tool_def['handler']);
                        
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
                            $conversation_complete = true;
                            break;
                            
                        } else {
                            $tool_result_data = $tool_result['data'] ?? [];
                            $tool_result_content = json_encode([
                                'tool_name' => $tool_name,
                                'data' => $tool_result_data,
                                'parameters' => $tool_parameters
                            ], JSON_PRETTY_PRINT);
                            
                            $data = apply_filters('dm_data_packet', $data, [
                                'type' => 'tool_result',
                                'tool_name' => $tool_name,
                                'content' => [
                                    'title' => ucwords(str_replace('_', ' ', $tool_name)) . ' Result',
                                    'body' => $tool_result_content
                                ],
                                'metadata' => [
                                    'tool_name' => $tool_name,
                                    'tool_parameters' => $tool_parameters,
                                    'tool_success' => $tool_result['success'] ?? false,
                                    'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'
                                ]
                            ], $flow_step_id, 'ai');
                            
                            $conversation_messages[] = [
                                'role' => 'user',
                                'content' => $tool_result_content
                            ];
                        }
                    }
                    
                    if ($handler_tool_executed) {
                        break;
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


