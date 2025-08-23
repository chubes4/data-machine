<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal AI Step - AI processing with dual tool discovery
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
                do_action('dm_log', 'error', 'AI Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $flow_step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            // Note: User messages are now handled separately and always functional
            if (empty($data)) {
                $user_message = trim($flow_step_config['user_message'] ?? '');
                if (empty($user_message)) {
                    do_action('dm_log', 'error', 'AI Step: No data found and no user message configured', ['flow_step_id' => $flow_step_id]);
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
                        
                        // Add source information for multi-source context
                        $source_type = $metadata['source_type'] ?? 'unknown';
                        if ($source_type !== 'unknown') {
                            $enhanced_content = "Source ({$source_type}):\n{$enhanced_content}";
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
                
                do_action('dm_log', 'debug', 'AI Step: Prepended user message as additional context', [
                    'flow_step_id' => $flow_step_id,
                    'message_length' => strlen($user_message),
                    'total_messages' => count($messages)
                ]);
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                do_action('dm_log', 'error', 'AI Step: No processable content found in any data packet inputs', [
                    'flow_step_id' => $flow_step_id,
                    'total_inputs' => count($data)
                ]);
                return $data;
            }
            
            
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
                
            }
            
            // Get handler tools for next step + user-enabled general tools
            $handler_tools = $this->get_next_step_tools($flow_step_config, $flow_step_id);
            $general_tools = $this->get_allowed_general_tools($step_ai_config);
            $available_tools = array_merge($handler_tools, $general_tools);
            
            
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
            
            
            // Get provider name from step configuration for AI request
            $provider_name = $step_ai_config['selected_provider'] ?? '';
            if (empty($provider_name)) {
                $error_message = 'AI step not configured: No provider selected';
                do_action('dm_log', 'error', 'AI Step: No provider configured', [
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
            
            
            // Execute AI request using pure filter with provider name and full tools for execution
            $ai_response = apply_filters('ai_request', $ai_request, $provider_name, null, $ai_provider_tools, $available_tools);

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
                    
                    // Execute tool directly - plugin handles tool execution, not library
                    $tool_def = $available_tools[$tool_name] ?? null;
                    if (!$tool_def) {
                        $tool_result = [
                            'success' => false,
                            'error' => "Tool '{$tool_name}' not found",
                            'tool_name' => $tool_name
                        ];
                    } else {
                        // Extract additional parameters from data packet
                        $latest_data = !empty($data) ? $data[0] : [];
                        $handler_config = $tool_def['handler_config'] ?? [];
                        $data_packet_parameters = $this->extract_tool_parameters_from_data($latest_data, $handler_config);
                        
                        // Merge AI analysis parameters with data packet parameters
                        // AI parameters take precedence over data packet parameters
                        $complete_parameters = array_merge($data_packet_parameters, $tool_parameters);
                        
                        // Direct tool execution following established pattern
                        $class_name = $tool_def['class'];
                        if (class_exists($class_name)) {
                            $tool_handler = new $class_name();
                            $tool_result = $tool_handler->handle_tool_call($complete_parameters, $tool_def);
                        } else {
                            $tool_result = [
                                'success' => false,
                                'error' => "Tool class '{$class_name}' not found",
                                'tool_name' => $tool_name
                            ];
                        }
                    }
                    
                    
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
                                'tool_handler' => $tool_def['handler'] ?? '',
                                'tool_parameters' => $complete_parameters,
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
     * Get available handler tools for immediate next step only
     * 
     * Handler tools require 'handler' property matching next step's handler.
     * Does NOT include general tools - those are handled separately via get_allowed_general_tools().
     * 
     * @param array $flow_step_config Flow step configuration containing pipeline info
     * @param string $flow_step_id Flow step ID for logging
     * @return array Available handler tools for next step only
     */
    private function get_next_step_tools(array $flow_step_config, string $flow_step_id): array {
        // Get current flow step ID from the step config
        $current_flow_step_id = $flow_step_config['flow_step_id'] ?? '';
        if (!$current_flow_step_id) {
            return [];
        }
        
        // Use parameter-based filter to find next flow step
        $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $current_flow_step_id);
        if (!$next_flow_step_id) {
            return [];
        }
        
        // Use parameter-based filter to get next step configuration
        $next_step_config = apply_filters('dm_get_flow_step_config', [], $next_flow_step_id);
        if (!$next_step_config || !isset($next_step_config['handler']['handler_slug'])) {
            return [];
        }
        
        // Get all tools from AI HTTP Client library with handler context
        $handler_slug = $next_step_config['handler']['handler_slug'];
        $handler_config = $next_step_config['handler']['settings'] ?? [];
        
        
        // Pass handler context to ai_tools filter for dynamic tool generation
        $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
        
        // Filter tools for current handler
        $available_tools = [];
        foreach ($all_tools as $tool_name => $tool_config) {
            // Handler tools: Only available when next step matches handler
            if (isset($tool_config['handler']) && $tool_config['handler'] === $handler_slug) {
                $available_tools[$tool_name] = $tool_config;
            }
        }
        
        
        return $available_tools;
    }

    /**
     * Get user-enabled general tools that are properly configured
     * 
     * General tools are available to all AI steps but only when:
     * 1. User explicitly enabled them via checkbox in AI step modal
     * 2. Tool is properly configured (has required API keys, settings, etc.)
     * 
     * @param array $step_ai_config AI step configuration containing enabled_tools
     * @return array Allowed general tools filtered by user choice and configuration
     */
    private function get_allowed_general_tools(array $step_ai_config): array {
        // Get tools filtered by plugin settings (removes disabled tools completely)
        $available_general_tools = dm_get_enabled_general_tools();
        $enabled_tools = $step_ai_config['enabled_tools'] ?? [];
        $allowed_tools = [];
        
        foreach ($available_general_tools as $tool_name => $tool_config) {
            // Only process general tools (no handler property)
            if (!isset($tool_config['handler'])) {
                // Check if user enabled this tool AND it's properly configured
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
                
                if (in_array($tool_name, $enabled_tools) && $tool_configured) {
                    $allowed_tools[$tool_name] = $tool_config;
                }
            }
        }
        
        return $allowed_tools;
    }

    /**
     * Extract tool parameters from data entry for tool calling
     * 
     * @param array $data_entry Latest data entry from data packet array
     * @param array $handler_settings Handler configuration settings
     * @return array Tool parameters extracted from data entry
     */
    private function extract_tool_parameters_from_data(array $data_entry, array $handler_settings): array {
        $parameters = [];
        
        // Extract content from data entry
        $content_data = $data_entry['content'] ?? [];
        
        if (isset($content_data['title'])) {
            $parameters['title'] = $content_data['title'];
        }
        
        if (isset($content_data['body'])) {
            $parameters['content'] = $content_data['body'];
        }
        
        // Extract metadata - CRITICAL: Include original_id for updates
        $metadata = $data_entry['metadata'] ?? [];
        if (isset($metadata['original_id'])) {
            $parameters['original_id'] = $metadata['original_id'];
        }
        if (isset($metadata['source_url'])) {
            $parameters['source_url'] = $metadata['source_url'];
        }
        
        // Extract attachments/media if available
        $attachments = $data_entry['attachments'] ?? [];
        if (!empty($attachments)) {
            // Look for image attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['type']) && $attachment['type'] === 'image') {
                    $parameters['image_url'] = $attachment['url'] ?? null;
                    break;
                }
            }
        }
        
        // Merge any additional parameters from handler settings
        // This allows handler-specific configuration to be passed through
        if (!empty($handler_settings)) {
            // Filter out internal settings, only pass through tool-relevant ones
            $tool_relevant_settings = array_filter($handler_settings, function($key) {
                return !in_array($key, ['handler_slug', 'auth_config', 'internal_config']);
            }, ARRAY_FILTER_USE_KEY);
            
            $parameters = array_merge($parameters, $tool_relevant_settings);
        }
        
        return $parameters;
    }


}


