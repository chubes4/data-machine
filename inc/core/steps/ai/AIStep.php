<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal AI Step - AI processing with full pipeline context
 * 
 * ENGINE DATA FLOW:
 * - Engine passes cumulative data packet array to every step via execute() method
 * - AI steps process all data entries for complete pipeline awareness
 * - Pure array-based system with no object creation needed
 * 
 * CONFIGURATION ARCHITECTURE:
 * - Step Config: Pipeline-level configuration (AI prompts, models, step behavior)
 * - Handler Config: Flow-level configuration (AI steps don't use handlers)
 * - AI configuration is stable across all flows using the same pipeline
 * 
 * PURE CAPABILITY-BASED ARCHITECTURE:
 * - No interface implementation required
 * - No inheritance requirements (completely self-contained)
 * - External plugins can create completely independent AI step classes
 * - All functionality detected via method existence (execute, get_prompt_fields)
 * - Maximum external override capabilities through filter priority
 * 
 * EXTERNAL PLUGIN REQUIREMENTS (minimum):
 * - Class with parameter-less constructor
 * - execute(int $job_id, array $data, array $step_config): array method
 * - get_prompt_fields(): array static method for UI configuration (optional)
 * 
 * Supports any AI operation: summarization, fact-checking, enhancement, translation,
 * content analysis, research, writing assistance, and complex multi-step workflows.
 */
class AIStep {

    /**
     * Execute AI processing with pure array data packet system
     * 
     * PURE ARRAY SYSTEM:
     * - Receives the whole data packet array (cumulative job data)
     * - Processes latest input and adds AI response to the array
     * - Returns updated array with AI output added
     * 
     * @param string $flow_step_id The flow step ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $flow_step_config The merged step configuration including pipeline and flow settings
     * @return array Updated data packet array with AI output added
     */
    public function execute($flow_step_id, array $data = [], array $flow_step_config = []): array {
        try {
            // Pure filter architecture - no client instance needed

            do_action('dm_log', 'debug', 'AI Step: Starting AI processing with step config', ['flow_step_id' => $flow_step_id]);

            // Use step configuration directly - no pipeline introspection needed
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'AI Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $flow_step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            if (empty($data)) {
                do_action('dm_log', 'error', 'AI Step: No data found in data packet array', ['flow_step_id' => $flow_step_id]);
                return $data;
            }
            
            do_action('dm_log', 'debug', 'AI Step: Processing all data packet inputs', [
                'flow_step_id' => $flow_step_id,
                'total_inputs' => count($data)
            ]);

            // Build messages from all data packet entries (reverse order for oldest-to-newest)
            $messages = [];
            $data_reversed = array_reverse($data);
            
            foreach ($data_reversed as $index => $input) {
                $input_type = $input['type'] ?? 'unknown';
                $metadata = $input['metadata'] ?? [];
                
                do_action('dm_log', 'debug', 'AI Step: Processing data packet input', [
                    'flow_step_id' => $flow_step_id,
                    'index' => $index,
                    'input_type' => $input_type,
                    'has_file_path' => !empty($metadata['file_path'])
                ]);
                
                // Check if this input has a file to process
                $file_path = $metadata['file_path'] ?? '';
                if ($file_path && file_exists($file_path)) {
                    do_action('dm_log', 'debug', 'AI Step: Adding file message', [
                        'flow_step_id' => $flow_step_id,
                        'file_path' => $file_path,
                        'mime_type' => $metadata['mime_type'] ?? 'unknown'
                    ]);
                    
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
                        
                        // Add source information for multi-source context
                        $source_type = $metadata['source_type'] ?? 'unknown';
                        if ($source_type !== 'unknown') {
                            $enhanced_content = "Source ({$source_type}):\n{$enhanced_content}";
                        }
                        
                        $messages[] = [
                            'role' => 'user',
                            'content' => $enhanced_content
                        ];
                        
                        do_action('dm_log', 'debug', 'AI Step: Added text message', [
                            'flow_step_id' => $flow_step_id,
                            'source_type' => $source_type,
                            'content_length' => strlen($enhanced_content)
                        ]);
                    } else {
                        do_action('dm_log', 'debug', 'AI Step: Skipping empty content input', [
                            'flow_step_id' => $flow_step_id,
                            'input_type' => $input_type
                        ]);
                    }
                }
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                do_action('dm_log', 'error', 'AI Step: No processable content found in any data packet inputs', [
                    'flow_step_id' => $flow_step_id,
                    'total_inputs' => count($data)
                ]);
                return $data;
            }
            
            do_action('dm_log', 'debug', 'AI Step: All inputs processed into messages', [
                'flow_step_id' => $flow_step_id,
                'total_messages' => count($messages)
            ]);
            
            // Get pipeline_step_id for AI HTTP Client step-aware processing

            // Use pipeline step ID from pipeline configuration - required for AI HTTP Client step-aware configuration
            // Pipeline steps must have stable UUID4 pipeline_step_ids for consistent AI settings
            if (empty($flow_step_config['pipeline_step_id'])) {
                do_action('dm_log', 'error', 'AI Step: Missing required pipeline_step_id from pipeline configuration', [
                    'flow_step_id' => $flow_step_id,
                    'flow_step_config' => $flow_step_config
                ]);
                throw new \RuntimeException("AI Step requires pipeline_step_id from pipeline configuration for step-aware AI client operation");
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
                
                do_action('dm_log', 'debug', 'AI Step: Added system prompt from pipeline config', [
                    'flow_step_id' => $flow_step_id,
                    'system_prompt_length' => strlen($step_ai_config['system_prompt'])
                ]);
            }
            
            // Get available tools for next step handler
            do_action('dm_log', 'debug', 'AI Step: Calling tool discovery', ['flow_step_id' => $flow_step_id]);
            $available_tools = $this->get_next_step_tools($flow_step_config, $flow_step_id);
            
            do_action('dm_log', 'debug', 'AI Step: Tool discovery result', [
                'flow_step_id' => $flow_step_id,
                'tools_found' => count($available_tools),
                'tool_names' => array_keys($available_tools)
            ]);
            
            // Final message structure validation and logging
            do_action('dm_log', 'debug', 'AI Step: Final message structure before request', [
                'flow_step_id' => $flow_step_id,
                'total_messages' => count($messages),
                'message_roles' => array_column($messages, 'role'),
                'system_messages_count' => count(array_filter($messages, function($msg) { return $msg['role'] === 'system'; })),
                'first_message_role' => $messages[0]['role'] ?? 'none',
                'first_message_preview' => isset($messages[0]['content']) ? substr($messages[0]['content'], 0, 100) . '...' : 'none'
            ]);
            
            // Prepare AI request with messages and step configuration
            $ai_request = [
                'messages' => $messages
            ];
            
            // Add model parameter if configured
            if (!empty($step_ai_config['model'])) {
                $ai_request['model'] = $step_ai_config['model'];
            }
            
            // Add tool_choice when tools are available to force AI to use tools
            if (!empty($available_tools)) {
                $ai_request['tool_choice'] = 'required';
            }
            
            // Debug: Log step configuration details before AI request
            $step_debug_config = $step_ai_config;
            do_action('dm_log', 'debug', 'AI Step: Step configuration retrieved', [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'step_config_exists' => !empty($step_debug_config),
                'step_config_keys' => array_keys($step_debug_config),
                'configured_provider' => $step_debug_config['provider'] ?? 'NOT_SET',
                'configured_model' => $step_debug_config['model'] ?? 'NOT_SET'
            ]);
            
            // Get provider name from step configuration for AI request
            $provider_name = $step_debug_config['selected_provider'] ?? '';
            if (empty($provider_name)) {
                $error_message = 'AI step not configured: No provider selected';
                do_action('dm_log', 'error', 'AI Step: No provider configured', [
                    'flow_step_id' => $flow_step_id,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
                throw new \Exception($error_message);
            }
            
            // Debug: Log the exact request being sent to AI provider
            do_action('dm_log', 'debug', 'AI Step: Sending request to provider', [
                'flow_step_id' => $flow_step_id,
                'provider' => $provider_name,
                'request_structure' => [
                    'has_messages' => isset($ai_request['messages']),
                    'message_count' => count($ai_request['messages'] ?? []),
                    'has_model' => isset($ai_request['model']),
                    'model_value' => $ai_request['model'] ?? 'NOT_SET',
                    'all_request_keys' => array_keys($ai_request)
                ]
            ]);
            
            // Transform tools from Data Machine format to AI provider format  
            $ai_provider_tools = [];
            foreach ($available_tools as $tool_name => $tool_config) {
                $ai_provider_tools[] = [
                    'name' => $tool_name,
                    'description' => $tool_config['description'] ?? '',
                    'parameters' => $tool_config['parameters'] ?? []
                ];
            }
            
            // Execute AI request using pure filter with provider name and clean tools
            $ai_response = apply_filters('ai_request', $ai_request, $provider_name, null, $ai_provider_tools);

            if (!$ai_response['success']) {
                $error_message = 'AI processing failed: ' . ($ai_response['error'] ?? 'Unknown error');
                do_action('dm_log', 'error', 'AI Step: Processing failed', [
                    'flow_step_id' => $flow_step_id,
                    'error' => $ai_response['error'] ?? 'Unknown error',
                    'provider' => $ai_response['provider'] ?? 'Unknown'
                ]);
                return [];
            }


            // Process tool calls if present, otherwise handle as text response
            $tool_calls = $ai_response['data']['tool_calls'] ?? [];
            $ai_content = $ai_response['data']['content'] ?? '';
            
            if (!empty($tool_calls)) {
                // Process tool calls
                do_action('dm_log', 'debug', 'AI Step: Processing tool calls', [
                    'flow_step_id' => $flow_step_id,
                    'tool_call_count' => count($tool_calls),
                    'tool_names' => array_column($tool_calls, 'name')
                ]);
                
                foreach ($tool_calls as $tool_call) {
                    $tool_name = $tool_call['name'] ?? '';
                    $tool_parameters = $tool_call['parameters'] ?? [];
                    
                    if (empty($tool_name)) {
                        do_action('dm_log', 'warning', 'AI Step: Tool call missing name', [
                            'flow_step_id' => $flow_step_id,
                            'tool_call' => $tool_call
                        ]);
                        continue;
                    }
                    
                    // Execute tool using AI HTTP Client library
                    $tool_result = ai_http_execute_tool($tool_name, $tool_parameters);
                    
                    do_action('dm_log', 'debug', 'AI Step: Tool execution result', [
                        'flow_step_id' => $flow_step_id,
                        'tool_name' => $tool_name,
                        'tool_success' => $tool_result['success'] ?? false,
                        'tool_error' => $tool_result['error'] ?? null
                    ]);
                    
                    if ($tool_result['success']) {
                        // Create tool execution entry in data packet
                        $tool_entry = [
                            'type' => 'tool_result',
                            'content' => [
                                'title' => "Tool: {$tool_name}",
                                'body' => 'Tool executed successfully'
                            ],
                            'metadata' => [
                                'tool_name' => $tool_name,
                                'tool_parameters' => $tool_parameters,
                                'tool_result' => $tool_result['data'] ?? [],
                                'model' => $ai_response['data']['model'] ?? 'unknown',
                                'provider' => $ai_response['provider'] ?? 'unknown',
                                'step_title' => $title
                            ],
                            'timestamp' => time()
                        ];
                        
                        // Add tool result to data packet
                        array_unshift($data, $tool_entry);
                    } else {
                        do_action('dm_log', 'error', 'AI Step: Tool execution failed', [
                            'flow_step_id' => $flow_step_id,
                            'tool_name' => $tool_name,
                            'error' => $tool_result['error'] ?? 'Unknown error'
                        ]);
                    }
                }
            } else {
                // Handle as traditional text response
                do_action('dm_log', 'debug', 'AI Step: Processing text response', [
                    'flow_step_id' => $flow_step_id,
                    'content_length' => strlen($ai_content)
                ]);
                
                // Create AI response entry
                $content_lines = explode("\n", trim($ai_content), 2);
                $ai_title = (strlen($content_lines[0]) <= 100) ? $content_lines[0] : 'AI Generated Content';
                
                $ai_entry = [
                    'type' => 'ai',
                    'content' => [
                        'title' => $ai_title,
                        'body' => $ai_content
                    ],
                    'metadata' => [
                        'model' => $ai_response['data']['model'] ?? 'unknown',
                        'provider' => $ai_response['provider'] ?? 'unknown',
                        'usage' => $ai_response['data']['usage'] ?? [],
                        'step_title' => $title,
                        'source_type' => $data[0]['metadata']['source_type'] ?? 'unknown'
                    ],
                    'timestamp' => time()
                ];
                
                // Add AI response to front of data packet array (newest first)
                array_unshift($data, $ai_entry);
            }

            do_action('dm_log', 'debug', 'AI Step: Processing completed successfully', [
                'flow_step_id' => $flow_step_id,
                'ai_content_length' => strlen($ai_content),
                'model' => $ai_response['data']['model'] ?? 'unknown',
                'provider' => $ai_response['provider'] ?? 'unknown',
                'total_items_in_packet' => count($data)
            ]);
            
            // Return updated data packet array
            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AI Step: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure  
            return $data;
        }
    }
    
    /**
     * Get available tools for the next step handler in the pipeline
     * 
     * @param array $flow_step_config Flow step configuration containing pipeline info
     * @param string $flow_step_id Flow step ID for logging
     * @return array Available tools array for next step handler
     */
    private function get_next_step_tools(array $flow_step_config, string $flow_step_id): array {
        // Get current flow step ID from the step config
        $current_flow_step_id = $flow_step_config['flow_step_id'] ?? '';
        if (!$current_flow_step_id) {
            do_action('dm_log', 'debug', 'AI Step: No flow_step_id available for tool discovery', ['flow_step_id' => $flow_step_id]);
            return [];
        }
        
        // Use parameter-based filter to find next flow step
        $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $current_flow_step_id);
        if (!$next_flow_step_id) {
            do_action('dm_log', 'debug', 'AI Step: No next step found - end of pipeline', ['flow_step_id' => $flow_step_id]);
            return [];
        }
        
        // Use parameter-based filter to get next step configuration
        $next_step_config = apply_filters('dm_get_flow_step_config', [], $next_flow_step_id);
        if (!$next_step_config || !isset($next_step_config['handler']['handler_slug'])) {
            do_action('dm_log', 'debug', 'AI Step: Next step has no handler configured', [
                'flow_step_id' => $flow_step_id,
                'next_flow_step_id' => $next_flow_step_id
            ]);
            return [];
        }
        
        // Get all tools from AI HTTP Client library
        $all_tools = apply_filters('ai_tools', []);
        $handler_slug = $next_step_config['handler']['handler_slug'];
        
        // Filter tools for next step handler only
        $available_tools = [];
        foreach ($all_tools as $tool_name => $tool_config) {
            if (isset($tool_config['handler']) && $tool_config['handler'] === $handler_slug) {
                // Apply dynamic configuration if available
                $handler_config = $next_step_config['handler']['settings'] ?? [];
                $dynamic_tool = apply_filters('dm_generate_handler_tool', $tool_config, $handler_slug, $handler_config);
                
                $available_tools[$tool_name] = $dynamic_tool ?: $tool_config;
            }
        }
        
        do_action('dm_log', 'debug', 'AI Step: Tool discovery result', [
            'flow_step_id' => $flow_step_id,
            'next_flow_step_id' => $next_flow_step_id,
            'handler_slug' => $handler_slug,
            'tools_found' => count($available_tools),
            'tool_names' => array_keys($available_tools)
        ]);
        
        return $available_tools;
    }


}


