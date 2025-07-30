<?php

namespace DataMachine\Core\Steps\Output;

if (!defined('ABSPATH')) {
    exit;
}

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
class OutputStep {

    /**
     * Execute output publishing with configurable handler (Closed-Door Philosophy)
     * 
     * Publishes DataPacket from previous step only.
     * No backward looking - only forward data consumption.
     * 
     * @param int $job_id The job ID to process
     * @param DataPacket|null $data_packet Latest DataPacket from previous step
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, ?DataPacket $data_packet = null): bool {
        $logger = apply_filters('dm_get_logger', null);
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');

        try {
            $logger->info('Output Step: Starting data publishing (closed-door)', ['job_id' => $job_id]);

            // Get job and pipeline configuration
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

            // Use provided DataPacket from natural flow or get from previous step
            if (!$data_packet) {
                $data_packet = $this->get_previous_step_data_packet($job_id);
            }
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
     * Get step configuration from pipeline or job context
     * 
     * @param int $job_id Job ID
     * @param string $step_name Step name
     * @return array|null Step configuration or null if not found
     */
    private function get_step_configuration(int $job_id, string $step_name): ?array {
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        
        // Get job to find pipeline configuration
        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Get pipeline-based configuration only
        $pipeline_id = $this->get_pipeline_id_from_job($job);
        if (!$pipeline_id) {
            return null;
        }

        // Get pipeline-level flow configuration
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if ($db_pipelines) {
            $config = $db_pipelines->get_pipeline_flow_configuration($pipeline_id);
            $flow_config = isset($config['steps']) ? $config['steps'] : [];
            // Find output step configuration in flow
            foreach ($flow_config as $step) {
                if ($step['slug'] === 'output_step' || $step['slug'] === 'output') {
                    return $step;
                }
            }
        }

        return null;
    }


    /**
     * Execute output handler directly using pure auto-discovery
     * 
     * @param string $handler_name Output handler name
     * @param DataPacket $data_packet DataPacket to publish
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array|null Output result or null on failure
     */
    private function execute_output_handler_direct(string $handler_name, DataPacket $data_packet, object $job, array $handler_config): ?array {
        $logger = apply_filters('dm_get_logger', null);

        // Auto-discover handler class using pure filter-based registration
        $handler_class = $this->auto_discover_handler_class($handler_name, 'output');
        if (!$handler_class) {
            $logger->error('Output Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'job_id' => $job->job_id
            ]);
            return null;
        }

        try {
            // Instantiate handler with parameter-less constructor
            $handler = new $handler_class();

            // Get pipeline ID for handler
            $pipeline_id = $this->get_pipeline_id_from_job($job);
            if (!$pipeline_id) {
                $logger->error('Output Step: Pipeline ID not found', [
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Universal JSON DataPacket interface - simple and direct
            $user_id = $job->user_id ?? 0;
            
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
     * Get pipeline ID from job context
     * 
     * @param object $job Job object
     * @return int|null Pipeline ID or null if not found
     */
    private function get_pipeline_id_from_job(object $job): ?int {
        return $job->pipeline_id ?? null;
    }

    /**
     * Auto-discover handler class from registration context
     * 
     * This method examines the handler registration information to auto-discover
     * which class registered itself, eliminating the need for explicit class parameters.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (input/output)
     * @return string|null Handler class name or null if not found
     */
    /**
     * Auto-discover handler class using centralized utility filter.
     * 
     * Uses the enhanced auto-discovery system from DataMachineFilters.php
     * for consistent handler class resolution across all components.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (input/output)
     * @return string|null Handler class name or null if not found
     */
    private function auto_discover_handler_class(string $handler_name, string $handler_type): ?string {
        return apply_filters('dm_auto_discover_handler_class', null, $handler_name, $handler_type);
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
        // Get available output handlers via pure filter system
        $output_handlers = apply_filters('dm_get_handlers', null, 'output');
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

    /**
     * Get data packet from previous step.
     *
     * @param int $job_id The job ID.
     * @return \DataMachine\Core\DataPacket|null The previous step's data packet or null if not found.
     */
    private function get_previous_step_data_packet(int $job_id): ?\DataMachine\Core\DataPacket {
        $pipeline_context = apply_filters('dm_get_pipeline_context', null);
        if (!$pipeline_context) {
            return null;
        }
        
        $previous_step_name = $pipeline_context->get_previous_step_name($job_id);
        if (!$previous_step_name) {
            return null;
        }
        
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $json_data = $db_jobs->get_step_data_by_name($job_id, $previous_step_name);
        
        if (!$json_data) {
            return null;
        }
        
        try {
            return \DataMachine\Core\DataPacket::fromJson($json_data);
        } catch (\Exception $e) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Exception caught in get_previous_step_data_packet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
                'job_id' => $job_id,
                'previous_step_name' => $previous_step_name
            ]);
            return null;
        }
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
    if ($step_type === 'output') {
        return [
            'label' => __('Output', 'data-machine'),
            'has_handlers' => true,
            'description' => __('Publish to external platforms', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\Output\\OutputStep'
        ];
    }
    return $step_config;
}, 10, 2);