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
                return $this->fail_job($job_id, 'AI HTTP client service unavailable');
            }

            $logger->debug('AI Step: Starting AI processing with fluid context', ['job_id' => $job_id]);

            // Get step configuration from pipeline/flow context  
            $step_config = $this->get_pipeline_step_config($job_id);
            if (!$step_config) {
                return $this->fail_job($job_id, 'AI step configuration not found');
            }

            // Validate required configuration
            $prompt = $step_config['prompt'] ?? '';
            $title = $step_config['title'] ?? 'AI Processing';
            
            if (empty($prompt)) {
                return $this->fail_job($job_id, 'AI step requires prompt configuration');
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
                return $this->fail_job($job_id, 'Fluid context bridge service unavailable');
            }
            
            $aggregated_context = $context_bridge->aggregate_pipeline_context($all_packets);
            
            // Get job and pipeline ID for including pipeline prompts in AI request
            $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
            if (!$db_jobs) {
                return $this->fail_job($job_id, 'Database jobs service unavailable');
            }
            
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found in database');
            }
            
            $pipeline_id = $this->get_pipeline_id_from_job($job);
            $enhanced_request = $context_bridge->build_ai_request($aggregated_context, $step_config, $pipeline_id);
            
            if (empty($enhanced_request['messages'])) {
                return $this->fail_job($job_id, 'Failed to build enhanced AI request from fluid context');
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
                return $this->fail_job($job_id, $error_message);
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
            
            $ai_output_packet = apply_filters('dm_create_datapacket', null, $ai_data, 'ai', $context);

            if (!$ai_output_packet instanceof DataPacket) {
                $logger->error('AI Step: Failed to create DataPacket from AI output', [
                    'job_id' => $job_id,
                    'ai_content_length' => strlen($ai_content),
                    'conversion_failed' => true
                ]);
                return $this->fail_job($job_id, 'Failed to create DataPacket from AI processing results');
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
                return $this->fail_job($job_id, 'Failed to store AI processing results');
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
            return $this->fail_job($job_id, 'AI step failed: ' . $e->getMessage());
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
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
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
        
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $json_data = $data_packet->toJson();
        
        return $db_jobs->update_step_data_by_name($job_id, $current_step, $json_data);
    }

    /**
     * Fail a job with an error message.
     *
     * @param int $job_id The job ID.
     * @param string $message The error message.
     * @return bool Always returns false for easy return usage.
     */
    private function fail_job(int $job_id, string $message): bool {
        $job_status_manager = apply_filters('dm_get_job_status_manager', null);
        $logger = apply_filters('dm_get_logger', null);
        if ($job_status_manager) {
            $job_status_manager->fail($job_id, $message);
        }
        if ($logger) {
            $logger->error($message, ['job_id' => $job_id]);
        }
        return false;
    }
}


