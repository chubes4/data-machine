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
 * Universal AI Agent - AI processing with dual tool discovery
 * 
 * Processes cumulative data packet array and adds AI response.
 * Uses pure filter-based architecture with no interface requirements.
 * 
 * Tool Discovery:
 * - Handler tools: Available only when next step matches handler (publishing tools)
 * - General tools: Architecture ready but no tools currently implemented
 */
class AIStep {


    /**
     * Execute AI processing
     * 
     * Processes cumulative data packet array and user message (if provided).
     * User messages are prepended as additional context before pipeline data.
     * 
     * @param string $job_id The job ID for context tracking
     * @param string $flow_step_id The flow step ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $flow_step_config The merged step configuration
     * @return array Updated data packet array with AI output added
     */
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        try {
            // Pure filter architecture - no client instance needed


            // Use step configuration directly - no pipeline introspection needed
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'AI Agent: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $flow_step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            // Note: User messages are now handled separately and always functional
            if (empty($data)) {
                $user_message = trim($flow_step_config['user_message'] ?? '');
                if (empty($user_message)) {
                    do_action('dm_log', 'error', 'AI Agent: No data found and no user message configured', ['flow_step_id' => $flow_step_id]);
                    return $data;
                }
                // Continue processing - user message will be added to empty messages array
            }
            

            // Build messages from all data packet entries (reverse order for oldest-to-newest)
            $messages = [];
            $data_reversed = array_reverse($data);
            
            foreach ($data_reversed as $index => $input) {
                $input_type = $input['type'] ?? 'unknown';
                $metadata = $input['metadata'] ?? [];
                
                
                // Check if this input has a file to process
                $file_path = $metadata['file_path'] ?? '';
                if ($file_path && file_exists($file_path)) {
                    
                    // Add file as user message
                    $messages[] = [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'file',
                                'file_path' => $file_path,
                                'mime_type' => $metadata['mime_type'] ?? ''
                            ]
                        ]
                    ];
                    
                } else {
                    // Process text content
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
                
                do_action('dm_log', 'debug', 'AI Agent: Added user message as context', [
                    'flow_step_id' => $flow_step_id,
                    'message_length' => strlen($user_message),
                    'total_messages' => count($messages)
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
            
            // Debug logging for tool availability
            do_action('dm_log', 'debug', 'AI Agent: Tool discovery completed', [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'enabled_tools_config' => $step_ai_config['enabled_tools'] ?? [],
                'total_available_tools' => count($available_tools),
                'available_tool_names' => array_keys($available_tools)
            ]);
            
            
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
            
            do_action('dm_log', 'debug', 'AI Agent: Starting conversational loop', [
                'flow_step_id' => $flow_step_id,
                'initial_messages_count' => count($conversation_messages),
                'available_tools' => array_keys($available_tools),
                'max_turns' => $max_turns
            ]);

            do {
                $turn_count++;
                
                // Rebuild conversation from data packets to include tool results while preserving initial context
                if ($turn_count > 1) {
                    $conversation_messages = AIConversationState::buildFromDataPackets($data, $messages);
                    
                    do_action('dm_log', 'info', 'AI: Rebuilt conversation with context preservation', [
                        'turn' => $turn_count,
                        'total_messages' => count($conversation_messages)
                    ]);
                }
                
                do_action('dm_log', 'debug', 'AI Agent: Conversation turn starting', [
                    'flow_step_id' => $flow_step_id,
                    'turn_count' => $turn_count,
                    'messages_count' => count($conversation_messages)
                ]);
                
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
                        $turn_context .= "Focus on task completion - avoid repetitive tool usage.";
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
                    return [];
                }

                $tool_calls = $ai_response['data']['tool_calls'] ?? [];
                $ai_content = $ai_response['data']['content'] ?? '';
                
                // Enhanced logging for natural tool calling behavior
                do_action('dm_log', 'debug', 'AI Agent: Turn response analysis', [
                    'flow_step_id' => $flow_step_id,
                    'turn_count' => $turn_count,
                    'has_tool_calls' => !empty($tool_calls),
                    'tool_calls_count' => count($tool_calls),
                    'has_ai_content' => !empty($ai_content),
                    'ai_content_length' => strlen($ai_content),
                    'ai_chose_tools_naturally' => !empty($tool_calls)
                ]);
                
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
                    
                    do_action('dm_log', 'debug', 'AI Agent: Stored AI response in data packet', [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'response_length' => strlen($response_body),
                        'has_tool_calls' => !empty($tool_calls),
                        'data_packet_count' => count($data)
                    ]);
                }
                
                if (!empty($tool_calls)) {
                    do_action('dm_log', 'debug', 'AI Agent: Processing tool calls (natural selection)', [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'tool_calls_count' => count($tool_calls),
                        'tool_names' => array_column($tool_calls, 'name')
                    ]);
                    
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
                        $tool_result = AIStepTools::executeTool($tool_name, $tool_parameters, $available_tools, $data, $flow_step_id);
                        
                        // Tool result will be stored as data packet entry if it's a general tool
                        
                        // Check if this is a handler tool (terminates step)
                        $tool_def = $available_tools[$tool_name] ?? null;
                        $is_handler_tool = $tool_def && isset($tool_def['handler']);
                        
                        if ($is_handler_tool && $tool_result['success']) {
                            // Handler tool executed successfully - step complete
                            do_action('dm_log', 'debug', 'AI Agent: Handler tool executed - step complete', [
                                'flow_step_id' => $flow_step_id,
                                'turn_count' => $turn_count,
                                'handler_tool' => $tool_name,
                                'total_conversation_turns' => $turn_count
                            ]);
                            
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
                            
                            do_action('dm_log', 'debug', 'AI Agent: Handler tool result added to data packet', [
                                'flow_step_id' => $flow_step_id,
                                'tool_name' => $tool_name,
                                'entry_type' => 'ai_handler_complete',
                                'data_packet_count' => count($data)
                            ]);
                            
                            // Handler tool successful - conversation complete
                            $conversation_complete = true;
                            
                            do_action('dm_log', 'info', 'AI: Handler tool executed successfully - step complete', [
                                'turn' => $turn_count,
                                'tool' => $tool_name,
                                'handler' => $tool_def['handler'] ?? 'unknown'
                            ]);
                            
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
                            
                            do_action('dm_log', 'info', 'AI: Tool result received and added to data packet', [
                                'turn' => $turn_count,
                                'tool' => $tool_name,
                                'success' => $tool_result['success'] ?? false
                            ]);
                        }
                    }
                    
                    if ($handler_tool_executed) {
                        break; // Exit main conversation loop
                    }
                    
                    // Tool results already stored as data packet entries - no separate conversation storage needed
                    
                } else {
                    // No tool calls - AI chose not to use tools (natural behavior)
                    do_action('dm_log', 'debug', 'AI Agent: No tool calls - AI chose not to use tools', [
                        'flow_step_id' => $flow_step_id,
                        'turn_count' => $turn_count,
                        'has_content' => !empty($ai_content),
                        'content_length' => strlen($ai_content),
                        'tools_were_available' => !empty($available_tools),
                        'available_tool_count' => count($available_tools),
                        'ai_natural_choice' => 'no_tools'
                    ]);
                    
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
            
            do_action('dm_log', 'debug', 'AI Agent: Conversational loop complete', [
                'flow_step_id' => $flow_step_id,
                'total_turns' => $turn_count,
                'conversation_complete' => $conversation_complete,
                'final_data_count' => count($data)
            ]);
            
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


