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
            if (empty($data)) {
                do_action('dm_log', 'error', 'AI Step: No data found in data packet array', ['flow_step_id' => $flow_step_id]);
                return $data;
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
            
            // Get available tools for next step handler
            $available_tools = $this->get_next_step_tools($flow_step_config, $flow_step_id);
            
            
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
     * Get available tools using dual discovery system
     * 
     * Discovers handler tools (filtered by immediate next step) and general tools (when implemented).
     * Handler tools require 'handler' property matching next step's handler.
     * General tools have no 'handler' property and would be available to all AI steps when implemented.
     * 
     * @param array $flow_step_config Flow step configuration containing pipeline info
     * @param string $flow_step_id Flow step ID for logging
     * @return array Available tools array (handler tools + general tools when implemented)
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
        
        // Get all tools from AI HTTP Client library
        $all_tools = apply_filters('ai_tools', []);
        $handler_slug = $next_step_config['handler']['handler_slug'];
        
        // Dual tool discovery: handler tools (next step) + general tools (when implemented)
        $available_tools = [];
        foreach ($all_tools as $tool_name => $tool_config) {
            // Handler tools: Only available when next step matches handler
            if (isset($tool_config['handler']) && $tool_config['handler'] === $handler_slug) {
                // Apply dynamic configuration if available
                $handler_config = $next_step_config['handler']['settings'] ?? [];
                $dynamic_tool = apply_filters('dm_generate_handler_tool', $tool_config, $handler_slug, $handler_config);
                
                $available_tools[$tool_name] = $dynamic_tool ?: $tool_config;
            }
            // General tools: Would be available to all AI steps when implemented (no handler property)
            elseif (!isset($tool_config['handler'])) {
                $available_tools[$tool_name] = $tool_config;
            }
        }
        
        
        return $available_tools;
    }


}


