<?php

namespace DataMachine\Core\Steps\AI;

use DataMachine\Engine\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

// DataPacket is engine-only - steps work with simple arrays provided by engine

/**
 * Universal AI Step - AI processing with full pipeline context
 * 
 * ENGINE DATA FLOW:
 * - Engine passes all DataPackets to every step via execute() method
 * - AI steps consume all packets for complete pipeline awareness
 * - No manual DataPacket retrieval or storage needed
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
 * - execute(int $job_id, array $data_packets = []): bool method
 * - get_prompt_fields(): array static method for UI configuration (optional)
 * 
 * Supports any AI operation: summarization, fact-checking, enhancement, translation,
 * content analysis, research, writing assistance, and complex multi-step workflows.
 */
class AIStep {

    /**
     * Execute AI processing with engine DataPacket flow
     * 
     * PURE ENGINE FLOW:
     * - Receives all DataPackets from previous steps via engine
     * - AI steps consume all packets for complete pipeline context
     * - Returns processed DataPackets to engine for next step
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets from previous steps
     * @return array Array of output DataPackets for next step
     */
    public function execute(int $job_id, array $data_packets = []): array {
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

            // Return DataPackets to engine (pure engine flow)
            $logger->debug('AI Step: Processing completed successfully', [
                'job_id' => $job_id,
                'content_length' => strlen($ai_content),
                'model' => $ai_response['data']['model'] ?? 'unknown',
                'provider' => $ai_response['provider'] ?? 'unknown'
            ]);
            
            // Return output DataPackets to engine for next step
            return [$ai_output_packet];

        } catch (\Exception $e) {
            if ($logger) {
                $logger->error('AI Step: Exception during processing', [
                    'job_id' => $job_id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Return empty array on failure (engine interprets as step failure)
            return [];
        }
    }

    /**
     * Get pipeline step configuration from job and flow context
     * 
     * @param int $job_id Job ID
     * @return array|null Step configuration or null if not found
     */
    private function get_pipeline_step_config(int $job_id): ?array {
        // Get step configuration from job and flow configuration
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        if (!$db_jobs) {
            return null;
        }
        
        $job = $db_jobs->get_job($job_id);
        if (!$job || !$job->flow_config) {
            return null;
        }
        
        $flow_config = json_decode($job->flow_config, true);
        if (!$flow_config) {
            return null;
        }
        
        // Get current step configuration from pipeline context service
        $pipeline_context = apply_filters('dm_get_pipeline_context', null);
        if (!$pipeline_context) {
            return null;
        }
        
        $current_step_name = $pipeline_context->get_current_step_name($job_id);
        if (!$current_step_name || !isset($flow_config['steps'][$current_step_name])) {
            return null;
        }
        
        return $flow_config['steps'][$current_step_name];
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


