<?php

namespace DataMachine\Engine\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\Interfaces\PipelineStepInterface;
use DataMachine\Constants;

/**
 * Universal Input Step - Executes any input handler
 * 
 * This step can gather data from any configured input source:
 * - RSS feeds, social media, local files, APIs
 * - WordPress posts, databases, web scraping
 * - Any input handler registered via dm_register_handlers filter
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in pipeline composition.
 */
class UniversalInputStep extends BasePipelineStep implements PipelineStepInterface {

    /**
     * Execute input data collection with configurable handler
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_service', null, 'logger');
        $db_jobs = apply_filters('dm_get_service', null, 'db_jobs');

        try {
            $logger->info('Universal Input Step: Starting data collection', ['job_id' => $job_id]);

            // Get job and module configuration
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found for input step');
            }

            // Get step configuration
            $step_config = $this->get_step_configuration($job_id, 'input');
            if (!$step_config || empty($step_config['handler'])) {
                return $this->fail_job($job_id, 'Input step requires handler configuration');
            }

            $handler_name = $step_config['handler'];
            $handler_config = $step_config['config'] ?? [];

            // Execute input handler via filter
            $input_data = apply_filters('dm_execute_input_handler', null, $handler_name, $job, $handler_config);

            if ($input_data === null) {
                // Fallback to direct handler execution if no filter override
                $input_data = $this->execute_input_handler_direct($handler_name, $job, $handler_config);
            }

            if (empty($input_data)) {
                return $this->fail_job($job_id, 'Input handler returned no data: ' . $handler_name);
            }

            // Store input data in standardized format
            $success = $this->store_step_data($job_id, 'input_data', $input_data);

            if ($success) {
                $item_count = isset($input_data['processed_items']) ? count($input_data['processed_items']) : 1;
                $logger->info('Universal Input Step: Data collection completed', [
                    'job_id' => $job_id,
                    'handler' => $handler_name,
                    'items_collected' => $item_count
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('Universal Input Step: Exception during data collection', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail_job($job_id, 'Input step failed: ' . $e->getMessage());
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
        if (isset($module_config['input_step_config'])) {
            return $module_config['input_step_config'];
        }

        // Fallback to legacy module configuration format
        if (isset($module_config['input_source_type'])) {
            return [
                'handler' => $module_config['input_source_type'],
                'config' => $module_config['input_source_config'] ?? []
            ];
        }

        // Try project-level pipeline configuration
        $project_pipeline_service = apply_filters('dm_get_service', null, 'project_pipeline_config_service');
        if ($project_pipeline_service) {
            $project_id = $this->get_project_id_from_job($job);
            if ($project_id) {
                $pipeline_config = $project_pipeline_service->get_project_pipeline_config($project_id);
                // Find input step configuration in pipeline
                foreach ($pipeline_config as $step) {
                    if ($step['type'] === 'input') {
                        return $step;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Execute input handler directly using Constants registry
     * 
     * @param string $handler_name Input handler name
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array|null Input data or null on failure
     */
    private function execute_input_handler_direct(string $handler_name, object $job, array $handler_config): ?array {
        $logger = apply_filters('dm_get_service', null, 'logger');

        // Get handler info from Constants registry
        $handler_info = Constants::get_input_handler($handler_name);
        if (!$handler_info || !class_exists($handler_info['class'])) {
            $logger->error('Universal Input Step: Handler not found or invalid', [
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
                $logger->error('Universal Input Step: Module not found', [
                    'module_id' => $job->module_id,
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Execute handler
            $user_id = json_decode($job->module_config ?? '{}', true)['user_id'] ?? 0;
            $input_data = $handler->get_input_data($module, $handler_config, $user_id);

            return $input_data;

        } catch (\Exception $e) {
            $logger->error('Universal Input Step: Handler execution failed', [
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
     * Define configuration fields for input step
     * 
     * @return array Configuration field definitions for UI
     */
    public static function get_prompt_fields(): array {
        // Get available input handlers
        $input_handlers = Constants::get_input_handlers();
        $handler_options = [];
        
        foreach ($input_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handler' => [
                'type' => 'select',
                'label' => 'Input Source',
                'description' => 'Choose the input handler to collect data',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration for the selected input handler',
                'placeholder' => '{"feed_url": "https://example.com/feed.xml"}'
            ]
        ];
    }
}