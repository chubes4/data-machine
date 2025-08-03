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
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIStep: Logger service unavailable');
                }
                return false;
            }
            
            if (!$ai_http_client) {
                $logger->error('AI Step: AI HTTP client service unavailable', ['job_id' => $job_id]);
                return $this->fail_job($job_id, 'AI HTTP client service unavailable');
            }

            $logger->info('AI Step: Starting AI processing with fluid context', ['job_id' => $job_id]);

            // Get step configuration from project or job
            $step_config = $this->get_step_configuration($job_id, 'ai');
            if (!$step_config) {
                return $this->fail_job($job_id, 'AI step configuration not found');
            }

            // Support handlers array for architectural consistency (typically contains single 'ai' handler)
            $handlers = $step_config['handlers'] ?? ['ai']; // Default to single AI handler
            if (!is_array($handlers) || empty($handlers)) {
                $handlers = ['ai']; // Fallback to default
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
                $logger->info('AI Step: Processing with all data packets', [
                    'job_id' => $job_id,
                    'packets_count' => count($all_packets),
                    'handlers' => $handlers
                ]);
            } else {
                // First step in pipeline - no previous DataPackets
                $logger->info('AI Step: First step - no previous data packets available', [
                    'job_id' => $job_id,
                    'handlers' => $handlers
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

            // AI configuration handled by AI HTTP Client library's plugin-scoped system
            // Library manages provider/model/parameters automatically via 'data-machine' plugin_context

            // Prepare AI request with messages - library handles all provider/model configuration
            $ai_request = ['messages' => $messages];
            
            // Execute AI request using AI HTTP Client library
            // Library automatically uses plugin-scoped configuration for provider/model/parameters
            $ai_response = $ai_http_client->send_step_request('ai', $ai_request);

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
                $logger->info('AI Step: Processing completed successfully', [
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
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIStep Exception: ' . $e->getMessage());
                }
            }
            return $this->fail_job($job_id, 'AI step failed: ' . $e->getMessage());
        }
    }

    /**
     * Get step configuration from pipeline or job context
     * 
     * @param int $job_id Job ID
     * @param string $step_name Step name
     * @return array|null Step configuration or null if not found
     */
    public function get_step_configuration(int $job_id, string $step_name): ?array {
        // Get configuration from direct database access
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');

        // Validate database services
        if (!$db_jobs) {
            return null;
        }

        // Get job to find pipeline context
        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Get step-level AI configuration using AI HTTP Client's OptionsManager
        if (class_exists('AI_HTTP_Options_Manager')) {
            
            // Generate step-specific key for configuration scoping
            $step_key = $job_id . '_ai_' . $step_name;
            
            // Retrieve step-level AI configuration
            $step_config = AI_HTTP_Options_Manager::get_step_config([
                'plugin_context' => 'data-machine',
                'ai_type' => 'llm',
                'step_key' => $step_key,
                'config_keys' => ['system_prompt', 'temperature', 'selected_provider', 'selected_model']
            ]);
            
            // Return configuration if found, otherwise return empty config for defaults
            return $step_config ?: [
                'system_prompt' => '',
                'temperature' => 0.7,
                'selected_provider' => '',
                'selected_model' => ''
            ];
        }
        
        // Fallback if AI HTTP Client not available - return empty config
        return [
            'system_prompt' => '',
            'temperature' => 0.7,
            'selected_provider' => '',
            'selected_model' => ''
        ];
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


