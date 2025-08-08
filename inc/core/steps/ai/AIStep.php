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
 * - execute(int $job_id, array $data_packet = [], array $job_config = []): array method
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
     * @param array $step_config Step configuration for this AI step
     * @return array Updated data packet array with AI output added
     */
    public function execute(int $job_id, array $data_packet = [], array $step_config = []): array {
        $logger = apply_filters('dm_get_logger', null);
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);

        try {
            // Validate required services
            if (!$logger) {
                // Logger service unavailable - fail gracefully
                return [];
            }
            
            if (!$ai_http_client) {
                $logger->error('AI Step: AI HTTP client service unavailable', ['job_id' => $job_id]);
                return [];
            }

            $logger->debug('AI Step: Starting AI processing with step config', ['job_id' => $job_id]);

            // Use step configuration directly - no pipeline introspection needed
            if (empty($step_config)) {
                $logger->error('AI Step: No step configuration provided', ['job_id' => $job_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $step_config['title'] ?? 'AI Processing';

            // Process ALL data packet entries (oldest to newest for logical message flow)
            if (empty($data_packet)) {
                $logger->error('AI Step: No data found in data packet array', ['job_id' => $job_id]);
                return $data_packet;
            }
            
            $logger->debug('AI Step: Processing all data packet inputs', [
                'job_id' => $job_id,
                'total_inputs' => count($data_packet)
            ]);

            // Build messages from all data packet entries (reverse order for oldest-to-newest)
            $messages = [];
            $data_packet_reversed = array_reverse($data_packet);
            
            foreach ($data_packet_reversed as $index => $input) {
                $input_type = $input['type'] ?? 'unknown';
                $metadata = $input['metadata'] ?? [];
                
                $logger->debug('AI Step: Processing data packet input', [
                    'job_id' => $job_id,
                    'index' => $index,
                    'input_type' => $input_type,
                    'has_file_path' => !empty($metadata['file_path'])
                ]);
                
                // Check if this input has a file to process
                $file_path = $metadata['file_path'] ?? '';
                if ($file_path && file_exists($file_path)) {
                    $logger->debug('AI Step: Adding file message', [
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
                        
                        $logger->debug('AI Step: Added text message', [
                            'job_id' => $job_id,
                            'source_type' => $source_type,
                            'content_length' => strlen($enhanced_content)
                        ]);
                    } else {
                        $logger->debug('AI Step: Skipping empty content input', [
                            'job_id' => $job_id,
                            'input_type' => $input_type
                        ]);
                    }
                }
            }
            
            // Ensure we have at least one message
            if (empty($messages)) {
                $logger->error('AI Step: No processable content found in any data packet inputs', [
                    'job_id' => $job_id,
                    'total_inputs' => count($data_packet)
                ]);
                return $data_packet;
            }
            
            $logger->debug('AI Step: All inputs processed into messages', [
                'job_id' => $job_id,
                'total_messages' => count($messages)
            ]);
            
            // Get step_id for AI HTTP Client step-aware processing

            // Use step ID from pipeline configuration - required for AI HTTP Client step-aware configuration
            // Pipeline steps must have stable UUID4 step_ids for consistent AI settings
            if (empty($step_config['step_id'])) {
                $logger->error('AI Step: Missing required step_id from pipeline configuration', [
                    'job_id' => $job_id,
                    'step_config' => $step_config
                ]);
                throw new \RuntimeException("AI Step requires step_id from pipeline configuration for step-aware AI client operation");
            }
            $step_id = $step_config['step_id'];
            
            // Add handler directive for next step if available
            $handler_directive = $this->get_next_step_directive($step_config, $logger, $job_id);
            if (!empty($handler_directive)) {
                // Add directive to system message or create one if none exists
                $system_message_found = false;
                foreach ($messages as &$message) {
                    if ($message['role'] === 'system') {
                        $message['content'] .= "\n\n" . $handler_directive;
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
                }
                
                $logger->debug('AI Step: Handler directive added', [
                    'job_id' => $job_id,
                    'directive_length' => strlen($handler_directive)
                ]);
            }
            
            // Prepare AI request with messages for step-aware processing
            $ai_request = [
                'messages' => $messages
            ];
            
            // Debug: Log step configuration details before AI request
            $step_debug_config = $ai_http_client->get_step_configuration($step_id);
            $logger->debug('AI Step: Step configuration retrieved', [
                'job_id' => $job_id,
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
                $logger->error('AI Step: Processing failed', [
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

            $logger->debug('AI Step: Processing completed successfully', [
                'job_id' => $job_id,
                'ai_content_length' => strlen($ai_content),
                'model' => $ai_response['data']['model'] ?? 'unknown',
                'provider' => $ai_response['provider'] ?? 'unknown',
                'total_items_in_packet' => count($data_packet)
            ]);
            
            // Return updated data packet array
            return $data_packet;

        } catch (\Exception $e) {
            if ($logger) {
                $logger->error('AI Step: Exception during processing', [
                    'job_id' => $job_id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Return unchanged data packet on failure  
            return $data_packet;
        }
    }
    
    /**
     * Get handler directive for the next step in the pipeline
     * 
     * @param array $step_config Step configuration containing pipeline info
     * @param object $logger Logger instance
     * @param int $job_id Job ID for logging
     * @return string Handler directive or empty string
     */
    private function get_next_step_directive(array $step_config, $logger, int $job_id): string {
        // Get pipeline step configuration
        $pipeline_steps = $step_config['pipeline_step_config'] ?? [];
        $current_position = $step_config['current_step_position'] ?? -1;
        
        // Find next step in pipeline
        $next_step = null;
        foreach ($pipeline_steps as $step) {
            if (($step['position'] ?? 0) > $current_position) {
                if ($next_step === null || ($step['position'] ?? 0) < ($next_step['position'] ?? 0)) {
                    $next_step = $step;
                }
            }
        }
        
        if (!$next_step) {
            $logger->debug('AI Step: No next step found for directive', ['job_id' => $job_id]);
            return '';
        }
        
        // For publish/fetch steps, get handler from flow config
        if (in_array($next_step['step_type'], ['publish', 'fetch'])) {
            $flow_config = $step_config['flow_config'] ?? [];
            $next_step_config = $flow_config['steps'][$next_step['step_type']] ?? [];
            $handlers = $next_step_config['handlers'] ?? [];
            
            // Find enabled handler
            $enabled_handler = null;
            foreach ($handlers as $handler_slug => $handler_config) {
                if (!empty($handler_config['enabled'])) {
                    $enabled_handler = $handler_slug;
                    break;
                }
            }
            
            if ($enabled_handler) {
                // Get handler directive via filter discovery
                $all_directives = apply_filters('dm_get_handler_directives', []);
                $directive = $all_directives[$enabled_handler] ?? '';
                
                if (!empty($directive)) {
                    $logger->debug('AI Step: Found handler directive', [
                        'job_id' => $job_id,
                        'next_step_type' => $next_step['step_type'],
                        'handler' => $enabled_handler,
                        'directive_preview' => substr($directive, 0, 100) . '...'
                    ]);
                    return $directive;
                }
            }
        }
        
        $logger->debug('AI Step: No directive found for next step', [
            'job_id' => $job_id,
            'next_step_type' => $next_step['step_type'] ?? 'unknown'
        ]);
        return '';
    }

    /**
     * AI Step Configuration handled by AI HTTP Client Library
     * 
     * Configuration UI is provided by AI_HTTP_ProviderManager_Component via:
     * - Provider selection (OpenAI, Anthropic, etc.)
     * - API key management
     * - Model selection
     * - Temperature/creativity settings
     * - AI processing instructions (system prompt)
     * 
     * All configuration is step-aware via step_id parameter for unique
     * per-step AI settings within pipelines.
     */


}


