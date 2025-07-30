<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Core\DataPacket;

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
 * - Optional: get_prompt_fields(): array static method for UI configuration
 * 
 * Supports any AI operation: summarization, fact-checking, enhancement, translation,
 * content analysis, research, writing assistance, and complex multi-step workflows.
 */
class AIStep {

    /**
     * Execute AI processing with natural data flow and fluid context system
     * 
     * NATURAL FLOW SIGNATURE:
     * - Receives latest DataPacket directly (no database queries needed)
     * - Full pipeline context available via dm_get_context filter
     * 
     * @param int $job_id The job ID to process
     * @param DataPacket|null $data_packet Latest DataPacket from previous step
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, ?DataPacket $data_packet = null): bool {
        $logger = apply_filters('dm_get_logger', null);
        $ai_http_client = apply_filters('dm_get_ai_http_client', null);

        try {
            // Validate required services
            if (!$logger) {
                error_log('AIStep: Logger service unavailable');
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

            // Validate required configuration
            $prompt = $step_config['prompt'] ?? '';
            $title = $step_config['title'] ?? 'AI Processing';
            
            if (empty($prompt)) {
                return $this->fail_job($job_id, 'AI step requires prompt configuration');
            }

            // Use provided DataPacket and context filter
            if ($data_packet !== null) {
                $logger->info('AI Step: Using natural data flow with provided DataPacket', [
                    'job_id' => $job_id,
                    'data_packet_source' => $data_packet->metadata['source_type'] ?? 'unknown'
                ]);
                
                // Get all previous packets from context filter
                $context = apply_filters('dm_get_context', null);
                $all_packets = $context['all_previous_packets'] ?? [];
                
                // Add current DataPacket as the latest
                $all_packets[] = $data_packet;
                
            } else {
                // First step in pipeline - no previous DataPacket
                $all_packets = [];
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

            // Get step-specific AI configuration using filter-based access
            $step_ai_config = null;
            if ($pipeline_id) {
                // Get step position using pipeline context service
                $pipeline_context = apply_filters('dm_get_pipeline_context', null);
                $step_position = $pipeline_context ? $pipeline_context->get_current_step_position($job_id) : null;
                if ($step_position !== null) {
                    // Filter-based AI step configuration access
                    $step_ai_config = apply_filters('dm_get_ai_step_config', null, $pipeline_id, $step_position);
                    
                    // Check if AI processing is disabled for this step
                    if (isset($step_ai_config['enabled']) && !$step_ai_config['enabled']) {
                        $logger->info('AI Step: Processing disabled for this step, passing data through', [
                            'job_id' => $job_id,
                            'pipeline_id' => $pipeline_id,
                            'step_position' => $step_position
                        ]);
                        
                        // Pass through the most recent DataPacket unchanged
                        $success = $this->store_step_data_packet($job_id, $input_packet);
                        if (!$success) {
                            $logger->error('AI Step: Failed to store passthrough DataPacket', ['job_id' => $job_id]);
                            return $this->fail_job($job_id, 'Failed to store passthrough data');
                        }
                        return true;
                    }
                    
                    $logger->info('AI Step: Using step-specific AI configuration', [
                        'job_id' => $job_id,
                        'pipeline_id' => $pipeline_id,
                        'step_position' => $step_position,
                        'provider' => $step_ai_config['provider'] ?? 'default',
                        'model' => $step_ai_config['model'] ?? 'default'
                    ]);
                }
            }

            // Prepare AI request parameters with step-specific configuration
            $ai_request = ['messages' => $messages];
            
            // Add step-specific AI parameters if available
            if ($step_ai_config && !empty($step_ai_config['provider'])) {
                $ai_request['provider'] = $step_ai_config['provider'];
            }
            if ($step_ai_config && !empty($step_ai_config['model'])) {
                $ai_request['model'] = $step_ai_config['model'];
            }
            if ($step_ai_config && isset($step_ai_config['temperature'])) {
                $ai_request['temperature'] = $step_ai_config['temperature'];
            }
            if ($step_ai_config && isset($step_ai_config['max_tokens'])) {
                $ai_request['max_tokens'] = $step_ai_config['max_tokens'];
            }

            // Execute AI request using ai-http-client with step-specific configuration
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

            // Create output DataPacket from AI response (pure transformation)
            $ai_content = $ai_response['data']['content'] ?? '';
            $ai_output_packet = DataPacket::fromAIOutput([
                'content' => $ai_content,
                'metadata' => [
                    'model' => $ai_response['data']['model'] ?? 'unknown',
                    'provider' => $ai_response['provider'] ?? 'unknown',
                    'usage' => $ai_response['data']['usage'] ?? [],
                    'prompt_used' => $prompt,
                    'step_title' => $title,
                    'processing_time' => time()
                ]
            ], $input_packet);


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
                error_log('AIStep Exception: ' . $e->getMessage());
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
    private function get_step_configuration(int $job_id, string $step_name): ?array {
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

        // Try to get step configuration from pipeline
        $pipeline_id = $this->get_pipeline_id_from_job($job);
        if ($pipeline_id) {
            $step_prompts = apply_filters('dm_get_pipeline_prompt', null, $pipeline_id);
            if (!empty($step_prompts) && isset($step_prompts[$step_name])) {
                return $step_prompts[$step_name];
            }
        }

        // No fallback - pipeline configuration is required
        return null;
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
     * @param \DataMachine\Core\DataPacket $data_packet The data packet to store.
     * @return bool True on success, false on failure.
     */
    private function store_step_data_packet(int $job_id, \DataMachine\Core\DataPacket $data_packet): bool {
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

// Auto-register this step type using parameter-based filter system
add_filter('dm_get_steps', function($step_config, $step_type) {
    if ($step_type === 'ai') {
        return [
            'label' => __('AI Processing', 'data-machine'),
            'has_handlers' => false,
            'description' => __('Process content using AI models', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep'
        ];
    }
    return $step_config;
}, 10, 2);