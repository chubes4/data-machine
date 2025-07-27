<?php

namespace DataMachine\Core\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Core\Constants;
use DataMachine\Core\DataPacket;

/**
 * Universal Input Step - Executes any input handler
 * 
 * This step can gather data from any configured input source.
 * No interface requirements - detected via method existence only.
 * External plugins can create completely independent input step classes.
 * All functionality is capability-based, not inheritance-based.
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in pipeline composition.
 */
class InputStep extends BasePipelineStep {

    /**
     * Execute input data collection with configurable handler (Closed-Door Philosophy)
     * 
     * Collects data from external sources only, returns DataPacket format.
     * No backward looking - only forward data passing.
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_logger', null);
        $db_jobs = apply_filters('dm_get_db_jobs', null);

        try {
            $logger->info('Input Step: Starting data collection (closed-door)', ['job_id' => $job_id]);

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

            // Execute input handler and ensure DataPacket return
            $data_packet = $this->execute_input_handler_direct($handler_name, $job, $handler_config);

            if (!$data_packet instanceof DataPacket) {
                return $this->fail_job($job_id, 'Input handler must return DataPacket: ' . $handler_name);
            }

            if (!$data_packet->hasContent()) {
                return $this->fail_job($job_id, 'Input handler returned empty DataPacket: ' . $handler_name);
            }

            // Store DataPacket for next step (closed-door: only forward data flow)
            $success = $this->store_step_data_packet($job_id, $data_packet);

            if ($success) {
                $logger->info('Input Step: Data collection completed', [
                    'job_id' => $job_id,
                    'handler' => $handler_name,
                    'content_length' => $data_packet->getContentLength(),
                    'source_type' => $data_packet->metadata['source_type'] ?? 'unknown'
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('Input Step: Exception during data collection', [
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
        $db_jobs = apply_filters('dm_get_db_jobs', null);
        
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
        $db_projects = apply_filters('dm_get_db_projects', null);
        if ($db_projects) {
            $project_id = $this->get_project_id_from_job($job);
            if ($project_id) {
                $config = $db_projects->get_project_pipeline_configuration($project_id);
                $pipeline_config = isset($config['steps']) ? $config['steps'] : [];
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
     * @return DataPacket|null DataPacket or null on failure
     */
    private function execute_input_handler_direct(string $handler_name, object $job, array $handler_config): ?DataPacket {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler info from Constants registry
        $handler_info = Constants::get_input_handler($handler_name);
        if (!$handler_info || !class_exists($handler_info['class'])) {
            $logger->error('Input Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'job_id' => $job->job_id
            ]);
            return null;
        }

        try {
            // Instantiate handler with parameter-less constructor
            $handler = new $handler_info['class']();

            // Get module object for handler
            $db_modules = apply_filters('dm_get_db_modules', null);
            $module = $db_modules->get_module($job->module_id);
            if (!$module) {
                $logger->error('Input Step: Module not found', [
                    'module_id' => $job->module_id,
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Execute handler - must return DataPacket
            $user_id = json_decode($job->module_config ?? '{}', true)['user_id'] ?? 0;
            $result = $handler->get_input_data($module, $handler_config, $user_id);

            // Ensure we have a DataPacket
            if ($result instanceof DataPacket) {
                return $result;
            }

            // If handler returns legacy format, convert to DataPacket
            if (is_array($result)) {
                try {
                    return DataPacket::fromLegacyInputData($result);
                } catch (\Exception $e) {
                    $logger->error('Input Step: Failed to convert legacy data to DataPacket', [
                        'handler' => $handler_name,
                        'job_id' => $job->job_id,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            }

            $logger->error('Input Step: Handler returned invalid data type', [
                'handler' => $handler_name,
                'job_id' => $job->job_id,
                'type' => gettype($result)
            ]);
            return null;

        } catch (\Exception $e) {
            $logger->error('Input Step: Handler execution failed', [
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
        $db_modules = apply_filters('dm_get_db_modules', null);
        if (!$db_modules || !isset($job->module_id)) {
            return null;
        }

        $module = $db_modules->get_module($job->module_id);
        return $module->project_id ?? null;
    }

    /**
     * Define configuration fields for input step
     * 
     * PURE CAPABILITY-BASED: External input step classes only need:
     * - execute(int $job_id): bool method
     * - get_prompt_fields(): array static method (optional)
     * - Parameter-less constructor
     * - No interface implementation required
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