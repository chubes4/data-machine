<?php

namespace DataMachine\Core\Steps\AI;

use DataMachine\Engine\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

// DataPacket is engine-only - steps work with simple arrays provided by engine

/**
 * Universal AI Step - Fluid-by-default AI processing with full pipeline context
 * 
 * NATURAL DATA FLOW SUPPORT:
 * - Uses execute(int $job_id, ?DataPacket $data_packet = null) signature
 * - Natural flow eliminates manual DataPacket retrieval from database
 * 
 * CONTEXT ACCESS:
 * - Use dm_get_context filter for full pipeline context
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
 * - execute(int $job_id, ?DataPacket $data_packet = null): bool method
 * - get_prompt_fields(): array static method for UI configuration (optional)
 * 
 * Supports any AI operation: summarization, fact-checking, enhancement, translation,
 * content analysis, research, writing assistance, and complex multi-step workflows.
 */
class AIStep {

    /**
     * Execute AI processing with uniform array of data packets
     * 
     * UNIFORM ARRAY APPROACH:
     * - Engine always provides array of DataPackets (most recent first)
     * - AI steps consume all packets due to consume_all_packets: true flag
     * - Self-selects all packets from array for complete pipeline context
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets from pipeline (newest first)
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, array $data_packets = []): bool {
        $logger = apply_filters('dm_get_logger', null);
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);

        try {
            // Validate required services
            if (!$logger) {
                // Logger service unavailable - fail gracefully
                return false;
            }
            
            if (!$ai_http_client) {
                $logger->error('AI Step: AI HTTP client service unavailable', ['job_id' => $job_id]);
                return false;
            }

            $logger->debug('AI Step: Starting AI processing with fluid context', ['job_id' => $job_id]);

            // Get step configuration from pipeline/flow context  
            $step_config = $this->get_pipeline_step_config($job_id);
            if (!$step_config) {
                $logger->error('AI Step: AI step configuration not found', ['job_id' => $job_id]);
                return false;
            }

            // Validate required configuration
            $prompt = $step_config['prompt'] ?? '';
            $title = $step_config['title'] ?? 'AI Processing';
            
            if (empty($prompt)) {
                $logger->error('AI Step: AI step requires prompt configuration', ['job_id' => $job_id]);
                return false;
            }

            // AI steps consume all packets from the provided array
            $all_packets = $data_packets;
            
            if (!empty($all_packets)) {
                $logger->debug('AI Step: Processing with all data packets', [
                    'job_id' => $job_id,
                    'packets_count' => count($all_packets)
                ]);
            } else {
                // First step in pipeline - no previous DataPackets
                $logger->debug('AI Step: First step - no previous data packets available', [
                    'job_id' => $job_id
                ]);
            }


            // Use FluidContextBridge for enhanced AI request
            $context_bridge = apply_filters('dm_get_fluid_context_bridge', null);
            if (!$context_bridge) {
                $logger->error('AI Step: Fluid context bridge service unavailable', ['job_id' => $job_id]);
                return false;
            }
            
            $aggregated_context = $context_bridge->aggregate_pipeline_context($all_packets);
            
            // Get job and pipeline ID for including pipeline prompts in AI request
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if (!$db_jobs) {
                $logger->error('AI Step: Database jobs service unavailable', ['job_id' => $job_id]);
                return false;
            }
            
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                $logger->error('AI Step: Job not found in database', ['job_id' => $job_id]);
                return false;
            }
            
            $pipeline_id = $this->get_pipeline_id_from_job($job);
            $enhanced_request = $context_bridge->build_ai_request($aggregated_context, $step_config, $pipeline_id);
            
            if (empty($enhanced_request['messages'])) {
                $logger->error('AI Step: Failed to build enhanced AI request from fluid context', ['job_id' => $job_id]);
                return false;
            }
            
            $messages = $enhanced_request['messages'];
            
            // Use the most recent packet as the primary input for output processing
            $input_packet = end($all_packets);

            // Generate proper step key for AI HTTP Client step-aware configuration
            $step_key = $this->generate_step_key($job_id);
            
            // Prepare AI request with messages for step-aware processing
            $ai_request = ['messages' => $messages];
            
            // Execute AI request using AI HTTP Client's step-aware method
            // This automatically uses step-specific configuration (provider, model, temperature, etc.)
            $ai_response = $ai_http_client->send_step_request($step_key, $ai_request);

            if (!$ai_response['success']) {
                $error_message = 'AI processing failed: ' . ($ai_response['error'] ?? 'Unknown error');
                $logger->error('AI Step: Processing failed', [
                    'job_id' => $job_id,
                    'error' => $ai_response['error'] ?? 'Unknown error',
                    'provider' => $ai_response['provider'] ?? 'Unknown'
                ]);
                return false;
            }

            // Create output DataPacket from AI response using filter system
            $ai_content = $ai_response['data']['content'] ?? '';
            $ai_data = [
                'content' => $ai_content,
                'metadata' => [
                    'model' => $ai_response['data']['model'] ?? 'unknown',
                    'provider' => $ai_response['provider'] ?? 'unknown',
                    'usage' => $ai_response['data']['usage'] ?? [],
                    'prompt_used' => $prompt,
                    'step_title' => $title,
                    'processing_time' => time()
                ]
            ];
            
            $context = [
                'original_packet' => $input_packet,
                'job_id' => $job_id
            ];
            
            // Create DataPacket using universal constructor
            try {
                // Simple heuristic: first line as title if it's short
                $content_lines = explode("\n", trim($ai_content), 2);
                $title = (strlen($content_lines[0]) <= 100) ? $content_lines[0] : 'AI Generated Content';
                $body = $ai_content;
                
                $ai_output_packet = new DataPacket($title, $body, 'ai');
                
                // Add AI-specific metadata
                $ai_output_packet->metadata = array_merge($ai_output_packet->metadata, [
                    'model' => $ai_data['metadata']['model'],
                    'provider' => $ai_data['metadata']['provider'],
                    'usage' => $ai_data['metadata']['usage'],
                    'prompt_used' => $ai_data['metadata']['prompt_used'],
                    'step_title' => $ai_data['metadata']['step_title'],
                    'processing_time' => $ai_data['metadata']['processing_time']
                ]);
                
                // Copy context from original packet if available
                if (isset($context['original_packet']) && $context['original_packet'] instanceof DataPacket) {
                    $original = $context['original_packet'];
                    $ai_output_packet->metadata['source_type'] = $original->metadata['source_type'] ?? 'unknown';
                    if (!empty($original->attachments)) {
                        $ai_output_packet->attachments = $original->attachments;
                    }
                }
                
                $ai_output_packet->processing['steps_completed'][] = 'ai';
                
            } catch (\Exception $e) {
                $logger->error('AI Step: Failed to create DataPacket from AI output', [
                    'job_id' => $job_id,
                    'ai_content_length' => strlen($ai_content),
                    'error' => $e->getMessage()
                ]);
                return false;
            }

            // Store transformed DataPacket for next step (maintains fluid context chain)
            $success = $this->store_step_data_packet($job_id, $ai_output_packet);

            if ($success) {
                $logger->debug('AI Step: Processing completed successfully', [
                    'job_id' => $job_id,
                    'content_length' => strlen($ai_content),
                    'model' => $ai_response['data']['model'] ?? 'unknown',
                    'provider' => $ai_response['provider'] ?? 'unknown'
                ]);
                return true;
            } else {
                $logger->error('AI Step: Failed to store output DataPacket', ['job_id' => $job_id]);
                return false;
            }

        } catch (\Exception $e) {
            if ($logger) {
                $logger->error('AI Step: Exception during processing', [
                    'job_id' => $job_id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            } else {
                // Logger unavailable - fail gracefully with no logging
            }
            return false;
        }
    }

    /**
     * Get pipeline step configuration from pipeline/flow context
     * 
     * @param int $job_id Job ID
     * @return array|null Step configuration or null if not found
     */
    private function get_pipeline_step_config(int $job_id): ?array {
        // Get step configuration from pipeline context service
        $context = apply_filters('dm_get_context', null, $job_id);
        if (!$context) {
            return null;
        }
        
        // Get step data from pipeline context - this contains prompt, title, etc.
        $step_data = $context->get_current_step_data();
        
        return $step_data;
    }

    /**
     * Generate step key for AI HTTP Client step-aware configuration
     * 
     * @param int $job_id Job ID
     * @return string Step key for configuration scoping
     */
    private function generate_step_key(int $job_id): string {
        // Get pipeline context for proper step identification
        $pipeline_context = apply_filters('dm_get_pipeline_context', null);
        if (!$pipeline_context) {
            // Fallback to job-based key if pipeline context unavailable
            return "job_{$job_id}_ai_step";
        }
        
        $current_step = $pipeline_context->get_current_step_name($job_id);
        $pipeline_id = $this->get_pipeline_id_from_job_id($job_id);
        
        // Generate step key: pipeline_123_step_ai_processing_position_2
        return "pipeline_{$pipeline_id}_step_{$current_step}";
    }

    /**
     * Get pipeline ID from job ID
     * 
     * @param int $job_id Job ID
     * @return int|null Pipeline ID or null if not found
     */
    private function get_pipeline_id_from_job_id(int $job_id): ?int {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        if (!$db_jobs) {
            return null;
        }
        
        $job = $db_jobs->get_job($job_id);
        return $job ? ($job->pipeline_id ?? null) : null;
    }

    /**
     * Get pipeline ID from job context
     * 
     * @param object|null $job Job object
     * @return int|null Pipeline ID or null if not found
     */
    private function get_pipeline_id_from_job(?object $job): ?int {
        if (!$job) {
            return null;
        }
        
        return $job->pipeline_id ?? null;
    }


    /**
     * Define prompt fields for AI step configuration
     * 
     * Fluid context is enabled by default - AI steps automatically receive
     * full pipeline context for powerful agentic workflows.
     * 
     * PURE CAPABILITY-BASED: External AI step classes only need:
     * - execute(int $job_id): bool method
     * - get_prompt_fields(): array static method (optional)
     * - Parameter-less constructor
     * - No interface implementation required
     * 
     * @return array Prompt field definitions for UI
     */
    public static function get_prompt_fields(): array {
        return [
            'handlers' => [
                'type' => 'hidden',
                'default' => ['ai'],
                'description' => 'AI processing handlers (defaults to ai handler for consistency)'
            ],
            'title' => [
                'type' => 'text',
                'label' => 'Step Title',
                'description' => 'A descriptive name for this AI processing step (e.g., "Content Summarizer", "Fact Checker", "Content Refiner")',
                'required' => true,
                'placeholder' => 'e.g., "Content Summarizer", "SEO Optimizer", "Language Translator"'
            ],
            'prompt' => [
                'type' => 'textarea',
                'label' => 'AI Prompt',
                'description' => 'Define what you want the AI to do. Fluid context automatically provides ALL previous pipeline data. Use variables: {{packet_count}}, {{source_types}}, {{all_titles}}, {{content_previews}}, {{processing_steps}}',
                'required' => true,
                'placeholder' => 'Example: Analyze the {{packet_count}} content sources from {{source_types}}. Create a comprehensive summary highlighting key insights from all sources...',
                'rows' => 8
            ]
        ];
    }


    /**
     * Store data packet for current step.
     *
     * @param int $job_id The job ID.
     * @param object $data_packet The data packet object to store.
     * @return bool True on success, false on failure.
     */
    private function store_step_data_packet(int $job_id, object $data_packet): bool {
        $pipeline_context = apply_filters('dm_get_pipeline_context', null);
        if (!$pipeline_context) {
            return false;
        }
        
        $current_step = $pipeline_context->get_current_step_name($job_id);
        if (!$current_step) {
            return false;
        }
        
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        $json_data = $data_packet->toJson();
        
        return $db_jobs->update_step_data_by_name($job_id, $current_step, $json_data);
    }

}


