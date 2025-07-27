<?php

namespace DataMachine\Core\Steps;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Core\Constants;
use DataMachine\Core\DataPacket;

/**
 * Universal Output Step - Executes any output handler
 * 
 * This step can publish data to any configured output destination.
 * No interface requirements - detected via method existence only.
 * External plugins can create completely independent output step classes.
 * All functionality is capability-based, not inheritance-based.
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in publishing workflows.
 */
class OutputStep extends BasePipelineStep {

    /**
     * Execute output publishing with configurable handler (Closed-Door Philosophy)
     * 
     * Publishes DataPacket from previous step only.
     * No backward looking - only forward data consumption.
     * 
     * @param int $job_id The job ID to process
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id): bool {
        $logger = apply_filters('dm_get_logger', null);
        $db_jobs = apply_filters('dm_get_db_jobs', null);

        try {
            $logger->info('Output Step: Starting data publishing (closed-door)', ['job_id' => $job_id]);

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

            // Get DataPacket from previous step only (closed-door: no backward looking)
            $data_packet = $this->get_previous_step_data_packet($job_id);
            if (!$data_packet) {
                return $this->fail_job($job_id, 'No DataPacket available from previous step');
            }

            // Execute output handler with DataPacket
            $output_result = $this->execute_output_handler_direct($handler_name, $data_packet, $job, $handler_config);

            if (!$output_result || (isset($output_result['success']) && !$output_result['success'])) {
                $error_message = 'Output handler failed: ' . $handler_name;
                if (isset($output_result['error'])) {
                    $error_message .= ' - ' . $output_result['error'];
                }
                return $this->fail_job($job_id, $error_message);
            }

            // Create result DataPacket for final storage
            $result_packet = new DataPacket(
                'Output Complete',
                json_encode($output_result, JSON_PRETTY_PRINT),
                'output_handler'
            );
            $result_packet->metadata['handler_used'] = $handler_name;
            $result_packet->metadata['output_success'] = $output_result['success'] ?? true;
            
            // Store result DataPacket
            $success = $this->store_step_data_packet($job_id, $result_packet);

            if ($success) {
                $logger->info('Output Step: Data publishing completed', [
                    'job_id' => $job_id,
                    'handler' => $handler_name,
                    'output_success' => $output_result['success'] ?? true
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('Output Step: Exception during publishing', [
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
        $db_jobs = apply_filters('dm_get_db_jobs', null);
        
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
        $db_projects = apply_filters('dm_get_db_projects', null);
        if ($db_projects) {
            $project_id = $this->get_project_id_from_job($job);
            if ($project_id) {
                $config = $db_projects->get_project_pipeline_configuration($project_id);
                $pipeline_config = isset($config['steps']) ? $config['steps'] : [];
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
     * Execute output handler directly using Constants registry
     * 
     * @param string $handler_name Output handler name
     * @param DataPacket $data_packet DataPacket to publish
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array|null Output result or null on failure
     */
    private function execute_output_handler_direct(string $handler_name, DataPacket $data_packet, object $job, array $handler_config): ?array {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler info from Constants registry
        $handler_info = Constants::get_output_handler($handler_name);
        if (!$handler_info || !class_exists($handler_info['class'])) {
            $logger->error('Output Step: Handler not found or invalid', [
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
                $logger->error('Output Step: Module not found', [
                    'module_id' => $job->module_id,
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Prepare module with output configuration
            $module->output_config = json_encode($handler_config);

            // Universal JSON DataPacket interface - simple and direct
            $user_id = json_decode($job->module_config ?? '{}', true)['user_id'] ?? 0;
            
            // Convert DataPacket to pure JSON object
            $json_data_packet = json_decode(json_encode($data_packet));
            $json_data_packet->output_config = $handler_config;
            
            // Execute handler with pure JSON object - beautiful simplicity
            $output_result = $handler->handle_output($json_data_packet, $user_id);

            return $output_result;

        } catch (\Exception $e) {
            $logger->error('Output Step: Handler execution failed', [
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
     * Define configuration fields for output step
     * 
     * PURE CAPABILITY-BASED: External output step classes only need:
     * - execute(int $job_id): bool method
     * - get_prompt_fields(): array static method (optional)
     * - Parameter-less constructor
     * - No interface implementation required
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