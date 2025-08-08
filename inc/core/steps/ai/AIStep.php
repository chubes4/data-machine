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
     * @param array $job_config Complete job configuration from JobCreator
     * @return array Updated data packet array with AI output added
     */
    public function execute(int $job_id, array $data_packet = [], array $job_config = []): array {
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

            $logger->debug('AI Step: Starting AI processing with fluid context', ['job_id' => $job_id]);

            // Get step configuration from job configuration
            // ARCHITECTURE: AI steps have pipeline-level configuration (prompts, models)
            // Get current step by finding the AI step in pipeline steps array
            $pipeline_steps = $job_config['pipeline_step_config'] ?? [];
            $step_config = null;
            foreach ($pipeline_steps as $step) {
                if (($step['step_type'] ?? '') === 'ai') {
                    $step_config = $step;
                    break;
                }
            }
            
            if (!$step_config) {
                $logger->error('AI Step: AI step configuration not found in job config', ['job_id' => $job_id]);
                return [];
            }

            // AI configuration managed by AI HTTP Client - no validation needed here
            $title = $step_config['title'] ?? 'AI Processing';

            // Get the latest input from data packet array (newest first)
            $latest_input = $data_packet[0] ?? null;
            
            if (!$latest_input) {
                $logger->error('AI Step: No data found in data packet array', ['job_id' => $job_id]);
                return $data_packet; // Return unchanged array
            }
            
            $logger->debug('AI Step: Processing latest input', [
                'job_id' => $job_id,
                'total_items' => count($data_packet),
                'latest_type' => $latest_input['type'] ?? 'unknown'
            ]);


            // Extract content from the latest input for AI processing
            $content = '';
            if (isset($latest_input['content'])) {
                if (!empty($latest_input['content']['title'])) {
                    $content .= "Title: " . $latest_input['content']['title'] . "\n\n";
                }
                if (!empty($latest_input['content']['body'])) {
                    $content .= $latest_input['content']['body'];
                }
            }

            if (empty($content)) {
                $logger->error('AI Step: No content found in latest input', [
                    'job_id' => $job_id,
                    'latest_input_keys' => array_keys($latest_input)
                ]);
                return $data_packet; // Return unchanged array
            }

            // Get publish handlers from flow config for directive application
            $flow_config = $job_config['flow_config'] ?? [];
            $publish_handlers = $this->extract_publish_handlers($flow_config);
            
            // Apply platform-specific directives if publish handlers are configured
            $enhanced_content = $this->apply_output_directives(trim($content), $publish_handlers, $flow_config);
            
            // Build AI messages array
            $messages = [
                [
                    'role' => 'user',
                    'content' => $enhanced_content
                ]
            ];
            
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
            
            // Prepare AI request with messages for step-aware processing
            $ai_request = ['messages' => $messages];
            
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
     * Extract publish handlers from flow configuration
     * 
     * @param array $flow_config Flow configuration array
     * @return array Array of publish handler types
     */
    private function extract_publish_handlers(array $flow_config): array {
        $publish_handlers = [];
        
        if (empty($flow_config)) {
            return $publish_handlers;
        }
        
        // Validate flow_config structure - must be position-based array
        $logger = apply_filters('dm_get_logger', null);
        $first_key = key($flow_config);
        if (!is_int($first_key)) {
            if ($logger) {
                $logger->error('AI Step: Invalid flow_config structure - expected position-based array with integer keys', [
                    'first_key_type' => gettype($first_key),
                    'first_key_value' => $first_key,
                    'flow_config_keys' => array_keys($flow_config)
                ]);
            }
            return $publish_handlers;
        }
        
        // Flow config contains step configurations with handlers
        foreach ($flow_config as $position => $step_config) {
            if (!is_array($step_config)) {
                continue;
            }
            
            // Check if handler exists and is configured
            $handler = $step_config['handler'] ?? null;
            if (empty($handler) || !is_array($handler)) {
                continue;
            }
            
            $handler_slug = $handler['slug'] ?? '';
            if (empty($handler_slug)) {
                continue;
            }
            
            // Get handler information to determine if it's a publish handler
            $all_handlers = apply_filters('dm_get_handlers', []);
            $handler_info = $all_handlers[$handler_slug] ?? null;
            
            if ($handler_info && ($handler_info['type'] ?? '') === 'publish') {
                $publish_handlers[] = [
                    'handler_slug' => $handler_slug,
                    'handler_config' => $handler['settings'] ?? []
                ];
            }
        }
        
        return $publish_handlers;
    }
    
    /**
     * Apply output directives based on publish handlers
     * 
     * @param string $content Original content
     * @param array $publish_handlers Array of publish handler configurations
     * @param array $flow_config Complete flow configuration
     * @return string Enhanced content with directives
     */
    private function apply_output_directives(string $content, array $publish_handlers, array $flow_config): string {
        if (empty($publish_handlers)) {
            return $content;
        }
        
        $logger = apply_filters('dm_get_logger', null);
        $enhanced_content = $content;
        
        foreach ($publish_handlers as $handler_info) {
            $handler_slug = $handler_info['handler_slug'];
            $handler_config = $handler_info['handler_config'];
            
            // Map handler slugs to directive output types
            $output_type = $this->map_handler_to_output_type($handler_slug);
            
            if (empty($output_type)) {
                continue;
            }
            
            // Build output config for directive system
            $output_config = [
                $handler_slug => $handler_config
            ];
            
            // Apply directives using the existing filter system
            $directive_block = apply_filters('dm_get_output_directive', '', $output_type, $output_config);
            
            if (!empty($directive_block)) {
                $enhanced_content .= $directive_block;
                
                if ($logger) {
                    $logger->debug('AI Step: Applied directives for handler', [
                        'handler' => $handler_slug,
                        'output_type' => $output_type,
                        'directive_length' => strlen($directive_block)
                    ]);
                }
            }
        }
        
        return $enhanced_content;
    }
    
    /**
     * Map handler slug to directive output type
     * 
     * @param string $handler_slug Handler slug
     * @return string Output type for directive system
     */
    private function map_handler_to_output_type(string $handler_slug): string {
        // Map publish handler slugs to directive output types
        $handler_to_type_map = [
            'twitter' => 'twitter',
            'bluesky' => 'bluesky', 
            'facebook' => 'facebook',
            'threads' => 'threads',
            'wordpress' => 'wordpress',
            'googlesheets' => 'googlesheets'
        ];
        
        return $handler_to_type_map[$handler_slug] ?? '';
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


