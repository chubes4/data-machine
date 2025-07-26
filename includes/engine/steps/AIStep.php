<?php

namespace DataMachine\Engine\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\Interfaces\PipelineStepInterface;
use DataMachine\DataPacket;

/**
 * Universal AI Step - Fluid-by-default AI processing with full pipeline context
 * 
 * This step automatically receives ALL previous DataPackets in the pipeline,
 * enabling powerful agentic workflows:
 * - AI Step 1: Process with input context
 * - AI Step 2: Fact-check with input + AI Step 1 context
 * - AI Step 3: Refine with input + AI Step 1 + AI Step 2 context
 * 
 * Each AI step gets progressively more intelligent context for enhanced processing.
 * Supports any AI operation: summarization, fact-checking, enhancement, translation,
 * content analysis, research, writing assistance, and complex multi-step workflows.
 */
class AIStep extends BasePipelineStep implements PipelineStepInterface {

    /**
     * Execute AI processing with fluid context system
     * 
     * Automatically aggregates ALL previous DataPackets for comprehensive context.
     * Enables powerful agentic workflows where each AI step builds on full pipeline history.
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_service', null, 'logger');
        $ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');

        try {
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

            // Fluid context system: Always aggregate context from all previous steps
            $logger->info('AI Step: Using fluid context system for enhanced AI interaction', ['job_id' => $job_id]);
            
            $all_packets = $this->get_all_previous_data_packets($job_id);
            if (empty($all_packets)) {
                return $this->fail_job($job_id, 'No DataPackets available from previous steps for fluid context');
            }

            // Use FluidContextBridge for enhanced AI request
            $context_bridge = apply_filters('dm_get_service', null, 'fluid_context_bridge');
            $aggregated_context = $context_bridge->aggregate_pipeline_context($all_packets);
            $enhanced_request = $context_bridge->build_ai_request($aggregated_context, $step_config);
            
            if (empty($enhanced_request['messages'])) {
                return $this->fail_job($job_id, 'Failed to build enhanced AI request from fluid context');
            }
            
            $messages = $enhanced_request['messages'];
            
            // Use the most recent packet as the primary input for output processing
            $input_packet = end($all_packets);

            // Get step-specific AI configuration
            $ai_config_service = apply_filters('dm_get_service', null, 'ai_step_config_service');
            $step_ai_config = null;
            if ($ai_config_service) {
                $project_id = $this->get_project_id_from_job($job);
                if ($project_id) {
                    // Get step position from job or step configuration
                    $step_position = $this->get_step_position_from_job($job_id);
                    if ($step_position !== null) {
                        $step_ai_config = $ai_config_service->get_step_ai_config($project_id, $step_position);
                        
                        // Check if AI processing is disabled for this step
                        if (isset($step_ai_config['enabled']) && !$step_ai_config['enabled']) {
                            $logger->info('AI Step: Processing disabled for this step, passing data through', [
                                'job_id' => $job_id,
                                'project_id' => $project_id,
                                'step_position' => $step_position
                            ]);
                            
                            // Pass through the most recent DataPacket unchanged
                            $success = $this->store_step_data_packet($job_id, $input_packet);
                            return $success;
                        }
                        
                        $logger->info('AI Step: Using step-specific AI configuration', [
                            'job_id' => $job_id,
                            'project_id' => $project_id,
                            'step_position' => $step_position,
                            'provider' => $step_ai_config['provider'] ?? 'default',
                            'model' => $step_ai_config['model'] ?? 'default'
                        ]);
                    }
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
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('AI Step: Exception during processing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail_job($job_id, 'AI step failed: ' . $e->getMessage());
        }
    }

    /**
     * Get step configuration from project or job context
     * 
     * @param int $job_id Job ID
     * @param string $step_name Step name
     * @return array|null Step configuration or null if not found
     */
    private function get_step_configuration(int $job_id, string $step_name): ?array {
        // Get configuration from project pipeline config service
        $project_pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        $project_prompts_service = apply_filters('dm_get_service', null, 'project_prompts_service');
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');

        // Get job to find project context
        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Try to get step configuration from project
        if ($project_pipeline_service && $project_prompts_service) {
            $project_id = $this->get_project_id_from_job($job);
            if ($project_id) {
                $step_prompts = $project_prompts_service->get_project_step_prompts($project_id, $step_name);
                if (!empty($step_prompts)) {
                    return $step_prompts;
                }
            }
        }

        // Fallback to job-level configuration if available
        $job_config = json_decode($job->module_config ?? '{}', true);
        return $job_config['ai_step_config'] ?? null;
    }

    /**
     * Get project ID from job context
     * 
     * @param object $job Job object
     * @return int|null Project ID or null if not found
     */
    private function get_project_id_from_job(object $job): ?int {
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        if (!$db_modules || !isset($job->module_id)) {
            return null;
        }

        $module = $db_modules->get_module($job->module_id);
        return $module->project_id ?? null;
    }

    /**
     * Get step position from job context
     * 
     * @param int $job_id Job ID
     * @return int|null Step position (0-based) or null if not found
     */
    private function get_step_position_from_job(int $job_id): ?int {
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        if (!$db_jobs) {
            return null;
        }

        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Try to get step position from job step_name or step_order
        if (isset($job->step_name) && preg_match('/ai_(\d+)/', $job->step_name, $matches)) {
            return intval($matches[1]);
        }

        // Try to get from step_order field if it exists
        if (isset($job->step_order) && is_numeric($job->step_order)) {
            return intval($job->step_order);
        }

        // Fallback: Try to determine from pipeline configuration
        $project_id = $this->get_project_id_from_job($job);
        if ($project_id) {
            $pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
            if ($pipeline_service) {
                $pipeline_steps = $pipeline_service->get_project_pipeline_steps($project_id, get_current_user_id());
                if (!empty($pipeline_steps['steps'])) {
                    // Find the AI step position
                    $ai_step_count = 0;
                    foreach ($pipeline_steps['steps'] as $index => $step) {
                        if ($step['type'] === 'ai') {
                            if ($ai_step_count === 0) {
                                // This is likely our step - return its position
                                return $index;
                            }
                            $ai_step_count++;
                        }
                    }
                }
            }
        }

        // Ultimate fallback - assume position 0
        return 0;
    }

    /**
     * Define prompt fields for AI step configuration
     * 
     * Fluid context is enabled by default - AI steps automatically receive
     * full pipeline context for powerful agentic workflows.
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
}