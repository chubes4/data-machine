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
     * @param int $job_id The job ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $step_config The merged step configuration including pipeline and flow settings
     * @return array Updated data packet array with AI output added
     */
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        $job_id = $step_config['job_id'] ?? 0;
        try {
            // Create AI HTTP client directly
            if (!class_exists('\AI_HTTP_Client')) {
                do_action('dm_log', 'error', 'AI Step: AI HTTP Client class not available', ['job_id' => $job_id]);
                return [];
            }
            
            $ai_http_client = new \AI_HTTP_Client(['plugin_context' => 'data-machine', 'ai_type' => 'llm']);

            do_action('dm_log', 'debug', 'AI Step: Starting AI processing with step config', ['job_id' => $job_id]);

            // Use step configuration directly - no pipeline introspection needed
            if (empty($step_config)) {
                do_action('dm_log', 'error', 'AI Step: No step configuration provided', ['job_id' => $job_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            if (empty($data)) {
                do_action('dm_log', 'error', 'AI Step: No data found in data packet array', ['job_id' => $job_id]);
                return $data;
            }
            
            do_action('dm_log', 'debug', 'AI Step: Processing all data packet inputs', [
                'job_id' => $job_id,
                'total_inputs' => count($data)
            ]);

            // Build messages from all data packet entries (reverse order for oldest-to-newest)
            $messages = [];
            $data_reversed = array_reverse($data);
            
            foreach ($data_reversed as $index => $input) {
                $input_type = $input['type'] ?? 'unknown';
                $metadata = $input['metadata'] ?? [];
                
                do_action('dm_log', 'debug', 'AI Step: Processing data packet input', [
                    'job_id' => $job_id,
                    'index' => $index,
                    'input_type' => $input_type,
                    'has_file_path' => !empty($metadata['file_path'])
                ]);
                
                // Check if this input has a file to process
                $file_path = $metadata['file_path'] ?? '';
                if ($file_path && file_exists($file_path)) {
                    do_action('dm_log', 'debug', 'AI Step: Adding file message', [
                        'job_id' => $job_id,
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
                            'job_id' => $job_id,
                            'source_type' => $source_type,
                            'content_length' => strlen($enhanced_content)
                        ]);
                    } else {
                        do_action('dm_log', 'debug', 'AI Step: Skipping empty content input', [
                            'job_id' => $job_id,
                            'input_type' => $input_type
                        ]);
                    }
                }
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                do_action('dm_log', 'error', 'AI Step: No processable content found in any data packet inputs', [
                    'job_id' => $job_id,
                    'total_inputs' => count($data)
                ]);
                return $data;
            }
            
            do_action('dm_log', 'debug', 'AI Step: All inputs processed into messages', [
                'job_id' => $job_id,
                'total_messages' => count($messages)
            ]);
            
            // Get pipeline_step_id for AI HTTP Client step-aware processing

            // Use pipeline step ID from pipeline configuration - required for AI HTTP Client step-aware configuration
            // Pipeline steps must have stable UUID4 pipeline_step_ids for consistent AI settings
            if (empty($step_config['pipeline_step_id'])) {
                do_action('dm_log', 'error', 'AI Step: Missing required pipeline_step_id from pipeline configuration', [
                    'job_id' => $job_id,
                    'step_config' => $step_config
                ]);
                throw new \RuntimeException("AI Step requires pipeline_step_id from pipeline configuration for step-aware AI client operation");
            }
            $pipeline_step_id = $step_config['pipeline_step_id'];
            
            // Add handler directive for next step if available
            do_action('dm_log', 'debug', 'AI Step: Calling directive discovery', ['job_id' => $job_id]);
            $handler_directive = $this->get_next_step_directive($step_config, $job_id);
            
            do_action('dm_log', 'debug', 'AI Step: Directive integration', [
                'job_id' => $job_id,
                'directive_received' => !empty($handler_directive),
                'directive_length' => strlen($handler_directive),
                'directive_content' => $handler_directive
            ]);
            
            if (!empty($handler_directive)) {
                // Add directive to system message or create one if none exists
                $system_message_found = false;
                foreach ($messages as &$message) {
                    if ($message['role'] === 'system') {
                        $original_content = $message['content'];
                        $message['content'] .= "\n\n" . $handler_directive;
                        do_action('dm_log', 'debug', 'AI Step: Added directive to existing system message', [
                            'job_id' => $job_id,
                            'original_system_message' => $original_content,
                            'final_system_message' => $message['content']
                        ]);
                        $system_message_found = true;
                        break;
                    }
                }
                
                // If no system message exists, create one with the directive
                if (!$system_message_found) {
                    array_unshift($messages, [
                        'role' => 'system',
                        'content' => $handler_directive
                    ]);
                    do_action('dm_log', 'debug', 'AI Step: Created new system message with directive', [
                        'job_id' => $job_id,
                        'directive_content' => $handler_directive
                    ]);
                }
                
                do_action('dm_log', 'debug', 'AI Step: Handler directive successfully integrated', [
                    'job_id' => $job_id,
                    'directive_length' => strlen($handler_directive),
                    'system_message_found' => $system_message_found
                ]);
            } else {
                do_action('dm_log', 'debug', 'AI Step: No handler directive to integrate', ['job_id' => $job_id]);
            }
            
            // Prepare AI request with messages for step-aware processing
            $ai_request = [
                'messages' => $messages
            ];
            
            // Convert pipeline_step_id to step_id for AI HTTP Client interface boundary
            // AI HTTP Client expects step_id parameter - direct assignment since both are UUID4 strings
            $step_id = $pipeline_step_id;
            
            // Debug: Log step configuration details before AI request
            $step_debug_config = $ai_http_client->get_step_configuration($step_id);
            do_action('dm_log', 'debug', 'AI Step: Step configuration retrieved', [
                'job_id' => $job_id,
                'pipeline_step_id' => $pipeline_step_id,
                'step_id' => $step_id,
                'step_config_exists' => !empty($step_debug_config),
                'step_config_keys' => array_keys($step_debug_config),
                'configured_provider' => $step_debug_config['provider'] ?? 'NOT_SET',
                'configured_model' => $step_debug_config['model'] ?? 'NOT_SET'
            ]);
            
            // Execute AI request using AI HTTP Client's step-aware method
            // This automatically uses step-specific configuration (provider, model, temperature, etc.)
            $ai_response = $ai_http_client->send_step_request($step_id, $ai_request);

            if (!$ai_response['success']) {
                $error_message = 'AI processing failed: ' . ($ai_response['error'] ?? 'Unknown error');
                do_action('dm_log', 'error', 'AI Step: Processing failed', [
                    'job_id' => $job_id,
                    'error' => $ai_response['error'] ?? 'Unknown error',
                    'provider' => $ai_response['provider'] ?? 'Unknown'
                ]);
                return [];
            }


            // Extract AI content and add to data packet array
            $ai_content = $ai_response['data']['content'] ?? '';
            
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
                    'source_type' => $latest_input['metadata']['source_type'] ?? 'unknown'
                ],
                'timestamp' => time()
            ];
            
            // Add AI response to front of data packet array (newest first)
            array_unshift($data, $ai_entry);

            do_action('dm_log', 'debug', 'AI Step: Processing completed successfully', [
                'job_id' => $job_id,
                'ai_content_length' => strlen($ai_content),
                'model' => $ai_response['data']['model'] ?? 'unknown',
                'provider' => $ai_response['provider'] ?? 'unknown',
                'total_items_in_packet' => count($data)
            ]);
            
            // Return updated data packet array
            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AI Step: Exception during processing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure  
            return $data;
        }
    }
    
    /**
     * Get handler directive for the next step in the pipeline
     * 
     * @param array $step_config Step configuration containing pipeline info
     * @param int $job_id Job ID for logging
     * @return string Handler directive or empty string
     */
    private function get_next_step_directive(array $step_config, int $job_id): string {
        // Get current flow step ID from the step config
        $current_flow_step_id = $step_config['flow_step_id'] ?? '';
        if (!$current_flow_step_id) {
            do_action('dm_log', 'debug', 'AI Step: No flow_step_id available for directive discovery', ['job_id' => $job_id]);
            return '';
        }
        
        // Use parameter-based filter to find next flow step
        $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $current_flow_step_id);
        if (!$next_flow_step_id) {
            do_action('dm_log', 'debug', 'AI Step: No next step found - end of pipeline', ['job_id' => $job_id]);
            return '';
        }
        
        // Use parameter-based filter to get next step configuration
        $next_step_config = apply_filters('dm_get_flow_step_config', [], $next_flow_step_id);
        if (!$next_step_config || !isset($next_step_config['handler']['handler_slug'])) {
            do_action('dm_log', 'debug', 'AI Step: Next step has no handler configured', [
                'job_id' => $job_id,
                'next_flow_step_id' => $next_flow_step_id
            ]);
            return '';
        }
        
        // Use discovery pattern to get all handler directives
        $all_directives = apply_filters('dm_handler_directives', []);
        $handler_slug = $next_step_config['handler']['handler_slug'];
        $directive = $all_directives[$handler_slug] ?? '';
        
        do_action('dm_log', 'debug', 'AI Step: Handler directive discovery result', [
            'job_id' => $job_id,
            'next_flow_step_id' => $next_flow_step_id,
            'handler_slug' => $handler_slug,
            'has_directive' => !empty($directive)
        ]);
        
        return $directive;
    }


}


