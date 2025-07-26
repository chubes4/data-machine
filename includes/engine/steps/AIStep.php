<?php

namespace DataMachine\Engine\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\Interfaces\PipelineStepInterface;
use DataMachine\DataPacket;

/**
 * Universal AI Step - Configurable AI processing with user-defined prompts
 * 
 * This step can perform any AI operation based on user configuration:
 * - Summarization, fact-checking, enhancement, translation
 * - Content analysis, research, writing assistance
 * - Any AI task defined by user prompts
 * 
 * Standardized input/output ensures seamless data flow between pipeline steps.
 */
class AIStep extends BasePipelineStep implements PipelineStepInterface {

    /**
     * Execute AI processing with configurable prompt (Closed-Door Philosophy)
     * 
     * Transforms DataPacket from previous step only.
     * No backward looking - pure sequential transformation.
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_service', null, 'logger');
        $ai_http_client = apply_filters('dm_get_service', null, 'ai_http_client');

        try {
            $logger->info('AI Step: Starting AI processing (closed-door)', ['job_id' => $job_id]);

            // Get step configuration from project or job
            $step_config = $this->get_step_configuration($job_id, 'ai');
            if (!$step_config) {
                return $this->fail_job($job_id, 'AI step configuration not found');
            }

            // Validate required prompt configuration
            $prompt = $step_config['prompt'] ?? '';
            if (empty($prompt)) {
                return $this->fail_job($job_id, 'AI step requires prompt configuration');
            }

            // Get DataPacket from previous step only (closed-door: no backward looking)
            $input_packet = $this->get_previous_step_data_packet($job_id);
            if (!$input_packet) {
                return $this->fail_job($job_id, 'No DataPacket available from previous step');
            }

            // Prepare AI request with user-defined prompt
            $messages = $this->build_ai_messages($prompt, $input_packet, $step_config);

            // Execute AI request using existing library
            $ai_response = $ai_http_client->send_step_request('ai', ['messages' => $messages]);

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
                    'processing_time' => time()
                ]
            ], $input_packet);

            // Store transformed DataPacket for next step (closed-door: only forward data flow)
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
     * Build AI messages array from prompt and DataPacket
     * 
     * @param string $prompt User-defined prompt
     * @param DataPacket $input_packet Input DataPacket to process
     * @param array $step_config Step configuration
     * @return array Messages array for AI request
     */
    private function build_ai_messages(string $prompt, DataPacket $input_packet, array $step_config): array {
        // Get formatted content from DataPacket for AI processing
        $content_text = $input_packet->getContentForAI();
        
        // Build system message with context
        $system_message = "You are an AI assistant helping with content processing. ";
        $system_message .= "Process the provided content according to the user's instructions. ";
        $system_message .= "Return your response in a clear, structured format.";

        // Build user message with prompt and content
        $user_message = $prompt . "\n\n";
        $user_message .= "Content to process:\n" . $content_text;

        return [
            ['role' => 'system', 'content' => $system_message],
            ['role' => 'user', 'content' => $user_message]
        ];
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
     * Define prompt fields for AI step configuration
     * 
     * @return array Prompt field definitions for UI
     */
    public static function get_prompt_fields(): array {
        return [
            'prompt' => [
                'type' => 'textarea',
                'label' => 'AI Prompt',
                'description' => 'Define what you want the AI to do with the input data',
                'required' => true,
                'placeholder' => 'Example: Summarize this content in 3 bullet points...'
            ],
            'model' => [
                'type' => 'select',
                'label' => 'AI Model',
                'description' => 'Choose the AI model for processing',
                'options' => [
                    'gpt-4' => 'GPT-4 (Balanced)',
                    'gpt-4o' => 'GPT-4o (Fast)',
                    'claude-3-sonnet' => 'Claude 3 Sonnet',
                    'claude-3-haiku' => 'Claude 3 Haiku (Fast)',
                ],
                'default' => 'gpt-4'
            ],
            'temperature' => [
                'type' => 'number',
                'label' => 'Creativity (Temperature)',
                'description' => 'Lower = more focused, Higher = more creative',
                'min' => 0,
                'max' => 2,
                'step' => 0.1,
                'default' => 0.7
            ]
        ];
    }
}