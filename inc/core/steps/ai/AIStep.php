<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

// Import extracted classes
require_once __DIR__ . '/AIConversationState.php';
require_once __DIR__ . '/AIStepTools.php';

// Pure array-based data packet system - no object dependencies

/**
 * AI processing step with tool-based execution
 *
 * Processes data packets and executes AI requests with available tools.
 * Supports multi-turn conversations and handler-specific tool discovery.
 */
class AIStep {


    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = [], ...$additional_parameters): array {
        // Extract engine-provided parameters by position (performance optimized)
        $source_url = $additional_parameters[0] ?? null;
        $image_url = $additional_parameters[1] ?? null;  
        $file_path = $additional_parameters[2] ?? null;
        $mime_type = $additional_parameters[3] ?? null;
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
            $messages = [];
            $data_reversed = array_reverse($data);
            
            foreach ($data_reversed as $input) {
                $metadata = $input['metadata'] ?? [];
                
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
                } else {
                    $content = '';
                    if (isset($input['content'])) {
                        if (!empty($input['content']['title'])) {
                            $content .= "Title: " . $input['content']['title'] . "\n\n";
                        }
                        if (!empty($input['content']['body'])) {
                            $content .= $input['content']['body'];
                        }
                    }

                    if (!empty($content)) {
                        $enhanced_content = trim($content);
                        
                        // Add clear source information for AI understanding
                        $input_type = $input['type'] ?? 'unknown';
                        $tool_name = $metadata['tool_name'] ?? null;
                        $source_type = $metadata['source_type'] ?? 'unknown';
                        
                        // Properly label content based on actual type
                        if ($input_type === 'tool_result' && $tool_name) {
                            $enhanced_content = "TOOL RESULT from {$tool_name}:\n{$enhanced_content}\n\n[Previous tool execution result - incorporate into your response]";
                        } elseif ($input_type === 'user_message') {
                            $enhanced_content = "TASK INSTRUCTIONS:\n{$enhanced_content}\n\n[This is your primary task - complete this objective]";
                        } elseif ($source_type !== 'unknown') {
                            $enhanced_content = "INPUT DATA from {$source_type}:\n{$enhanced_content}\n\n[End of input data - process according to user instructions above]";
                        } else {
                            $enhanced_content = "INPUT DATA:\n{$enhanced_content}\n\n[End of input data - process according to user instructions above]";
                        }
                        
                        $messages[] = [
                            'role' => 'user',
                            'content' => $enhanced_content
                        ];
                        
                    } else {
                    }
                }
            }
            
            // Add user message as additional context if provided (always functional)
            $user_message = trim($flow_step_config['user_message'] ?? '');
            if (!empty($user_message)) {
                array_unshift($messages, [
                    'role' => 'user',
                    'content' => $user_message
                ]);
                
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                do_action('dm_log', 'error', 'AI Agent: No processable content found in any data packet inputs', [
                    'flow_step_id' => $flow_step_id,
                    'total_inputs' => count($data)
                ]);
                return $data;
            }
            
            
            // Get pipeline_step_id for AI HTTP Client step-aware processing

            // Use pipeline step ID from pipeline configuration - required for AI HTTP Client step-aware configuration
            // Pipeline steps must have stable UUID4 pipeline_step_ids for consistent AI settings
            if (empty($flow_step_config['pipeline_step_id'])) {
                do_action('dm_log', 'error', 'AI Agent: Missing required pipeline_step_id from pipeline configuration', [
                    'flow_step_id' => $flow_step_id,
                    'flow_step_config' => $flow_step_config
                ]);
                throw new \RuntimeException("AI Agent requires pipeline_step_id from pipeline configuration for step-aware AI client operation");
            }
            $pipeline_step_id = $flow_step_config['pipeline_step_id'];
            
            // Get step configuration for AI request (need this before directive integration)
            $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
            
            // Add system prompt from pipeline configuration FIRST (before directive integration)
            if (!empty($step_ai_config['system_prompt'])) {
                $system_prompt_message = [
                    'role' => 'system',
                    'content' => $step_ai_config['system_prompt']
                ];
                
                // Add as first message since no system messages exist yet at this point
                array_unshift($messages, $system_prompt_message);
                
            }
            
            // Get all available tools for next step using extracted class
            // Need next step configuration to discover handler-specific tools
            $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
            if ($next_flow_step_id) {
                $next_step_config = apply_filters('dm_get_flow_step_config', [], $next_flow_step_id);
                $available_tools = AIStepTools::getAvailableToolsForNextStep($next_step_config, $pipeline_step_id);
            } else {
                // No next step - only general tools available
                $available_tools = AIStepTools::getAvailableToolsForNextStep([], $pipeline_step_id);
            }
            
            
            
            // Prepare AI request with messages and step configuration
            $ai_request = [
                'messages' => $messages
            ];
            
            // Add model parameter if configured
            if (!empty($step_ai_config['model'])) {
                $ai_request['model'] = $step_ai_config['model'];
            }
            
            // Make tools available to AI - let AI decide when to use them naturally
            // Removing tool_choice: 'required' allows AI to generate content first, then call tools
            
            
            // Get provider name from step configuration for AI request
            $provider_name = $step_ai_config['selected_provider'] ?? '';
            if (empty($provider_name)) {
                $error_message = 'AI step not configured: No provider selected';
                do_action('dm_log', 'error', 'AI Agent: No provider configured', [
                    'flow_step_id' => $flow_step_id,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
                throw new \Exception($error_message);
            }
            
            
            // Transform tools from Data Machine format to AI provider format  
            $ai_provider_tools = [];
            foreach ($available_tools as $tool_name => $tool_config) {
                $ai_provider_tools[] = [
                    'name' => $tool_name,
                    'description' => $tool_config['description'] ?? '',
                    'parameters' => $tool_config['parameters'] ?? []
                ];
            }
            
            
            // Local conversation state management - Data Machine handles ALL state
            // Initial conversation contains system messages, directives, and user prompt
            $conversation_messages = $messages;
            
            $conversation_complete = false;
            $max_turns = 5; // Safety limit to prevent infinite loops
            $turn_count = 0;
            

            do {
                $turn_count++;
                
                // Rebuild conversation from data packets to include tool results while preserving initial context
                if ($turn_count > 1) {
                    $conversation_messages = AIConversationState::buildFromDataPackets($data, $messages);
                    
                }
                
                
                // Make AI request for this turn
                $current_request = [
                    'messages' => $conversation_messages,
                    'model' => $step_ai_config['model'] ?? null
                ];
                
                // Tools available for AI to use naturally - no forced tool calling
                // AI can generate content and choose to call tools when appropriate
                
                // Add turn count awareness to help AI make efficient decisions
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
                
                // Send stateless request - library handles all provider-specific conversion
                $ai_response = apply_filters('ai_request', $current_request, $provider_name, null, $ai_provider_tools);

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
                
                
                // Store AI response in data packet for conversation continuity (claude.json pattern)
                // Store response even if content is empty but tool calls exist
                if (!empty($ai_content) || !empty($tool_calls)) {
                    if (!empty($ai_content)) {
                        $content_lines = explode("\n", trim($ai_content), 2);
                        $ai_title = (strlen($content_lines[0]) <= 100) ? $content_lines[0] : "AI Response - Turn {$turn_count}";
                        $response_body = $ai_content;
                    } else {
                        // AI made tool calls without explicit content
                        $ai_title = "AI Tool Execution - Turn {$turn_count}";
                        $tool_names = array_column($tool_calls, 'name');
                        $response_body = "AI executed " . count($tool_calls) . " tool(s): " . implode(', ', $tool_names);
                    }
                    
                    array_unshift($data, [
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
                        ],
                        'timestamp' => time()
                    ]);
                    
                }
                
                if (!empty($tool_calls)) {
                    
                    // AI chose to use tools - process each tool call
                    
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
                        
                        // Execute tool using extracted class
                        $tool_result = AIStepTools::executeTool($tool_name, $tool_parameters, $available_tools, $data, $flow_step_id, $source_url, $image_url);
                        
                        // Tool result will be stored as data packet entry if it's a general tool
                        
                        // Check if this is a handler tool (terminates step)
                        $tool_def = $available_tools[$tool_name] ?? null;
                        $is_handler_tool = $tool_def && isset($tool_def['handler']);
                        
                        if ($is_handler_tool && $tool_result['success']) {
                            
                            $handler_tool_executed = true;
                            
                            // UPSTREAM FIX: Separate clean AI parameters from handler config before storage
                            $clean_tool_parameters = $tool_parameters;
                            $handler_config = $tool_def['handler_config'] ?? [];
                            
                            // Remove nested handler config from tool parameters if present
                            $handler_key = $tool_def['handler'] ?? $tool_name;
                            if (isset($clean_tool_parameters[$handler_key])) {
                                unset($clean_tool_parameters[$handler_key]);
                            }
                            
                            // CRITICAL FIX: Create tool result entry that PublishStep can find
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
                                    'tool_parameters' => $clean_tool_parameters,  // ✅ Clean separated parameters
                                    'handler_config' => $handler_config,         // ✅ Separate handler config
                                    'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown',
                                    'flow_step_id' => $flow_step_id,
                                    'conversation_turn' => $turn_count,
                                    'ai_model' => $ai_response['data']['model'] ?? 'unknown',
                                    'ai_provider' => $ai_response['provider'] ?? 'unknown'
                                ],
                                'timestamp' => time()
                            ];
                            
                            // Add tool result entry to front of data packet
                            array_unshift($data, $tool_result_entry);
                            
                            
                            // Handler tool successful - conversation complete
                            $conversation_complete = true;
                            
                            
                            break; // Exit tool loop
                            
                        } else {
                            // General tool - add result as data packet entry and continue conversation
                            $tool_result_content = AIConversationState::formatToolResultForAI([
                                'tool_name' => $tool_name,
                                'data' => $tool_result['data'] ?? [],
                                'parameters' => $tool_parameters
                            ]);
                            
                            // Store tool result as simple data packet entry
                            array_unshift($data, [
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
                                ],
                                'timestamp' => time()
                            ]);
                            
                            // Add result to current conversation turn
                            $conversation_messages[] = [
                                'role' => 'user',
                                'content' => $tool_result_content
                            ];
                            
                        }
                    }
                    
                    if ($handler_tool_executed) {
                        break; // Exit main conversation loop
                    }
                    
                    // Tool results already stored as data packet entries - no separate conversation storage needed
                    
                } else {
                    
                    $conversation_complete = true;
                    
                    // AI response already stored in data packet above - no duplicate storage needed
                }
                
            } while (!$conversation_complete && $turn_count < $max_turns);
            
            // Check if we hit max turns limit
            if ($turn_count >= $max_turns && !$conversation_complete) {
                do_action('dm_log', 'warning', 'AI Agent: Conversation hit max turns limit', [
                    'flow_step_id' => $flow_step_id,
                    'max_turns' => $max_turns,
                    'final_turn_count' => $turn_count
                ]);
            }
            
            
            // Return updated data packet array
            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AI Agent: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure  
            return $data;
        }
    }

}


