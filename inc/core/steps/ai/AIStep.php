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
 * - execute(int $job_id, array $data_packet, array $step_config): array method
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
     * @param array $data_packet The cumulative data packet array for this job
     * @param array $step_config The merged step configuration including pipeline and flow settings
     * @return array Updated data packet array with AI output added
     */
    public function execute(int $job_id, array $data_packet = [], array $step_config = []): array {
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);

        try {
            // Validate required services
            if (!$ai_http_client) {
                do_action('dm_log', 'error', 'AI Step: AI HTTP client service unavailable', ['job_id' => $job_id]);
                return [];
            }

            do_action('dm_log', 'debug', 'AI Step: Starting AI processing with step config', ['job_id' => $job_id]);

            // Use step configuration directly - no pipeline introspection needed
            if (empty($step_config)) {
                do_action('dm_log', 'error', 'AI Step: No step configuration provided', ['job_id' => $job_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            if (empty($data_packet)) {
                do_action('dm_log', 'error', 'AI Step: No data found in data packet array', ['job_id' => $job_id]);
                return $data_packet;
            }
            
            do_action('dm_log', 'debug', 'AI Step: Processing all data packet inputs', [
                'job_id' => $job_id,
                'total_inputs' => count($data_packet)
            ]);

            // Build messages from all data packet entries (reverse order for oldest-to-newest)
            $messages = [];
            $data_packet_reversed = array_reverse($data_packet);
            
            foreach ($data_packet_reversed as $index => $input) {
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
                    'total_inputs' => count($data_packet)
                ]);
                return $data_packet;
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
            array_unshift($data_packet, $ai_entry);

            do_action('dm_log', 'debug', 'AI Step: Processing completed successfully', [
                'job_id' => $job_id,
                'ai_content_length' => strlen($ai_content),
                'model' => $ai_response['data']['model'] ?? 'unknown',
                'provider' => $ai_response['provider'] ?? 'unknown',
                'total_items_in_packet' => count($data_packet)
            ]);
            
            // Return updated data packet array
            return $data_packet;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AI Step: Exception during processing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure  
            return $data_packet;
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
        // Enhanced debug logging - capture complete step configuration
        do_action('dm_log', 'debug', 'AI Step: Starting directive discovery', [
            'job_id' => $job_id,
            'step_config_keys' => array_keys($step_config),
            'has_pipeline_config' => isset($step_config['pipeline_step_config']),
            'has_flow_config' => isset($step_config['flow_config']),
            'flow_id' => $step_config['flow_id'] ?? 'NOT_SET'
        ]);
        
        // Get pipeline step configuration
        $pipeline_steps = $step_config['pipeline_step_config'] ?? [];
        $current_execution_order = $step_config['current_execution_order'] ?? -1;
        
        do_action('dm_log', 'debug', 'AI Step: Pipeline analysis', [
            'job_id' => $job_id,
            'current_execution_order' => $current_execution_order,
            'total_pipeline_steps' => count($pipeline_steps),
            'pipeline_step_execution_orders' => array_map(fn($s) => $s['execution_order'] ?? 'no_order', $pipeline_steps),
            'pipeline_step_types' => array_map(fn($s) => $s['step_type'] ?? 'no_type', $pipeline_steps)
        ]);
        
        // Find next step in pipeline - use sequential discovery, not execution-order-based
        $next_step = null;
        $current_pipeline_step_id = $step_config['pipeline_step_id'] ?? '';
        
        // Find current step index in pipeline sequence
        $current_step_index = null;
        foreach ($pipeline_steps as $index => $step) {
            if ($step['pipeline_step_id'] === $current_pipeline_step_id) {
                $current_step_index = $index;
                break;
            }
        }
        
        do_action('dm_log', 'debug', 'AI Step: Current step identification', [
            'job_id' => $job_id,
            'current_pipeline_step_id' => $current_pipeline_step_id,
            'current_step_index' => $current_step_index,
            'total_pipeline_steps' => count($pipeline_steps)
        ]);
        
        // Get next step in sequence (if exists)
        if ($current_step_index !== null && isset($pipeline_steps[$current_step_index + 1])) {
            $next_step = $pipeline_steps[$current_step_index + 1];
            do_action('dm_log', 'debug', 'AI Step: Next step found via sequential discovery', [
                'job_id' => $job_id,
                'next_step_index' => $current_step_index + 1,
                'next_pipeline_step_id' => $next_step['pipeline_step_id'] ?? 'NO_ID',
                'next_step_type' => $next_step['step_type'] ?? 'NO_TYPE'
            ]);
        } else {
            do_action('dm_log', 'debug', 'AI Step: No next step in sequence', [
                'job_id' => $job_id,
                'current_step_index' => $current_step_index,
                'is_last_step' => $current_step_index === (count($pipeline_steps) - 1)
            ]);
        }
        
        if (!$next_step) {
            do_action('dm_log', 'debug', 'AI Step: No next step found for directive - end of pipeline', [
                'job_id' => $job_id,
                'current_step_index' => $current_step_index,
                'total_steps' => count($pipeline_steps)
            ]);
            return '';
        }
        
        // Check if any handlers exist for the next step type that have directives
        $all_handlers = apply_filters('dm_get_handlers', []);
        $all_directives = apply_filters('dm_get_handler_directives', []);

        // Find handlers for the next step type
        $step_handlers = array_filter($all_handlers, function($handler) use ($next_step) {
            return ($handler['type'] ?? '') === $next_step['step_type'];
        });

        do_action('dm_log', 'debug', 'AI Step: Handler directive discovery', [
            'job_id' => $job_id,
            'next_step_type' => $next_step['step_type'],
            'available_handlers_for_step_type' => array_keys($step_handlers),
            'total_available_directives' => count($all_directives),
            'available_directive_handlers' => array_keys($all_directives)
        ]);

        // Check if any of those handlers have directives
        $available_directive = '';
        $selected_handler = '';
        foreach ($step_handlers as $handler_slug => $handler_config) {
            if (isset($all_directives[$handler_slug])) {
                $available_directive = $all_directives[$handler_slug];
                $selected_handler = $handler_slug;
                
                do_action('dm_log', 'debug', 'AI Step: Found handler directive for next step', [
                    'job_id' => $job_id,
                    'next_step_type' => $next_step['step_type'],
                    'handler_slug' => $handler_slug,
                    'directive_length' => strlen($available_directive),
                    'directive_preview' => substr($available_directive, 0, 100) . '...'
                ]);
                break; // Use first available directive
            }
        }

        if (!empty($available_directive)) {
            do_action('dm_log', 'debug', 'AI Step: Returning handler directive', [
                'job_id' => $job_id,
                'handler' => $selected_handler,
                'next_step_type' => $next_step['step_type'],
                'directive_full' => $available_directive
            ]);
            return $available_directive;
        }

        do_action('dm_log', 'debug', 'AI Step: No handler directives available for next step type', [
            'job_id' => $job_id,
            'next_step_type' => $next_step['step_type'],
            'handlers_for_step_type' => array_keys($step_handlers),
            'handlers_with_directives' => array_keys($all_directives)
        ]);
        
        do_action('dm_log', 'debug', 'AI Step: No directive found for next step', [
            'job_id' => $job_id,
            'next_step_type' => $next_step['step_type'] ?? 'unknown'
        ]);
        return '';
    }


}


