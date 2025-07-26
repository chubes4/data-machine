<?php

namespace DataMachine\Engine\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\Interfaces\PipelineStepInterface;
use DataMachine\Constants;

/**
 * Universal Output Step - Executes any output handler
 * 
 * This step can publish data to any configured output destination:
 * - WordPress posts, social media platforms, email newsletters
 * - File systems, databases, APIs, webhooks
 * - Any output handler registered via dm_register_handlers filter
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in publishing workflows.
 */
class UniversalOutputStep extends BasePipelineStep implements PipelineStepInterface {

    /**
     * Execute output publishing with configurable handler
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_service', null, 'logger');
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');

        try {
            $logger->info('Universal Output Step: Starting data publishing', ['job_id' => $job_id]);

            // Get job and module configuration
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found for output step');
            }

            // Get step configuration
            $step_config = $this->get_step_configuration($job_id, 'output');
            if (!$step_config || empty($step_config['handler'])) {
                return $this->fail_job($job_id, 'Output step requires handler configuration');
            }

            $handler_name = $step_config['handler'];
            $handler_config = $step_config['config'] ?? [];

            // Get processed data from previous step(s)
            $processed_data = $this->get_previous_step_data($job_id);
            if (empty($processed_data)) {
                return $this->fail_job($job_id, 'No processed data available for output');
            }

            // Execute output handler via filter
            $output_result = apply_filters('dm_execute_output_handler', null, $handler_name, $processed_data, $job, $handler_config);

            if ($output_result === null) {
                // Fallback to direct handler execution if no filter override
                $output_result = $this->execute_output_handler_direct($handler_name, $processed_data, $job, $handler_config);
            }

            if (!$output_result || (isset($output_result['success']) && !$output_result['success'])) {
                $error_message = 'Output handler failed: ' . $handler_name;
                if (isset($output_result['error'])) {
                    $error_message .= ' - ' . $output_result['error'];
                }
                return $this->fail_job($job_id, $error_message);
            }

            // Store output result
            $success = $this->store_step_data($job_id, 'output_result', $output_result);

            if ($success) {
                $logger->info('Universal Output Step: Data publishing completed', [
                    'job_id' => $job_id,
                    'handler' => $handler_name,
                    'output_success' => $output_result['success'] ?? true
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('Universal Output Step: Exception during publishing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail_job($job_id, 'Output step failed: ' . $e->getMessage());
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
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');
        
        // Get job to find module configuration
        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Get configuration from job/module
        $module_config = json_decode($job->module_config ?? '{}', true);
        
        // Check for step-specific configuration
        if (isset($module_config['output_step_config'])) {
            return $module_config['output_step_config'];
        }

        // Fallback to legacy module configuration format
        if (isset($module_config['output_type'])) {
            return [
                'handler' => $module_config['output_type'],
                'config' => $module_config['output_config'] ?? []
            ];
        }

        // Try project-level pipeline configuration
        $project_pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ($project_pipeline_service) {
            $project_id = $this->get_project_id_from_job($job);
            if ($project_id) {
                $pipeline_config = $project_pipeline_service->get_project_pipeline_config($project_id);
                // Find output step configuration in pipeline
                foreach ($pipeline_config as $step) {
                    if ($step['type'] === 'output') {
                        return $step;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get processed data from previous pipeline step(s)
     * 
     * @param int $job_id Job ID
     * @return array Processed data for output publishing
     */
    private function get_previous_step_data(int $job_id): array {
        // Try to get data from the most recent AI processing step
        $all_step_data = $this->get_all_step_data($job_id);
        
        // Look for AI-processed data first
        if (isset($all_step_data['ai_processed_data'])) {
            return $all_step_data['ai_processed_data'];
        }

        // Fallback to other step data
        foreach (['finalized_data', 'fact_checked_data', 'processed_data', 'input_data'] as $data_field) {
            if (isset($all_step_data[$data_field]) && !empty($all_step_data[$data_field])) {
                return $all_step_data[$data_field];
            }
        }

        // Legacy numbered access fallback
        for ($step = 5; $step >= 1; $step--) {
            $step_data = $this->get_step_data($job_id, $step);
            if (!empty($step_data)) {
                return $step_data;
            }
        }

        return [];
    }

    /**
     * Execute output handler directly using Constants registry
     * 
     * @param string $handler_name Output handler name
     * @param array $processed_data Processed data to publish
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array|null Output result or null on failure
     */
    private function execute_output_handler_direct(string $handler_name, array $processed_data, object $job, array $handler_config): ?array {
        $logger = apply_filters('dm_get_service', null, 'logger');

        // Get handler info from Constants registry
        $handler_info = Constants::get_output_handler($handler_name);
        if (!$handler_info || !class_exists($handler_info['class'])) {
            $logger->error('Universal Output Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'job_id' => $job->job_id
            ]);
            return null;
        }

        try {
            // Instantiate handler with parameter-less constructor
            $handler = new $handler_info['class']();

            // Get module object for handler
            $db_modules = apply_filters('dm_get_service', null, 'db_modules');
            $module = $db_modules->get_module($job->module_id);
            if (!$module) {
                $logger->error('Universal Output Step: Module not found', [
                    'module_id' => $job->module_id,
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Prepare module with output configuration
            $module->output_config = $handler_config;

            // Execute handler
            $user_id = json_decode($job->module_config ?? '{}', true)['user_id'] ?? 0;
            $output_result = $handler->handle_output($processed_data, $module, $user_id);

            return $output_result;

        } catch (\Exception $e) {
            $logger->error('Universal Output Step: Handler execution failed', [
                'handler' => $handler_name,
                'job_id' => $job->job_id,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
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
     * Define configuration fields for output step
     * 
     * @return array Configuration field definitions for UI
     */
    public static function get_prompt_fields(): array {
        // Get available output handlers
        $output_handlers = Constants::get_output_handlers();
        $handler_options = [];
        
        foreach ($output_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handler' => [
                'type' => 'select',
                'label' => 'Output Destination',
                'description' => 'Choose the output handler to publish data',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration for the selected output handler',
                'placeholder' => '{"post_title": "Auto-generated Post"}'
            ]
        ];
    }
}