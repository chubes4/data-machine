<?php

namespace DataMachine\Core\Steps\Output;

use DataMachine\Engine\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

// DataPacket is engine-only - steps work with simple arrays provided by engine

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
     * Execute output publishing with uniform array approach
     * 
     * UNIFORM ARRAY APPROACH:
     * - Engine provides array of DataPackets (most recent first)
     * - Output steps use latest packet by default (data_packets[0])
     * - Self-selects most recent packet for publishing
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets (uses first/latest by default)
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, array $data_packets = []): bool {
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
            if (!$step_config || empty($step_config['handlers']) || !is_array($step_config['handlers'])) {
                return $this->fail_job($job_id, 'Output step requires handlers array configuration');
            }

            $handlers = $step_config['handlers'];
            $handler_config = $step_config['config'] ?? [];

            // Output steps use latest DataPacket (first in array)
            $data_packet = $data_packets[0] ?? null;
            if (!$data_packet) {
                return $this->fail_job($job_id, 'No DataPacket available from previous step');
            }

            // Execute multiple output handlers in batch
            $batch_results = $this->execute_multiple_output_handlers($handlers, $data_packet, $job, $handler_config);

            if (!$batch_results['overall_success']) {
                $error_message = 'Multi-output publishing failed: ' . $batch_results['summary'];
                return $this->fail_job($job_id, $error_message);
            }

            // Create result DataPacket using filter system
            $output_data = [
                'content' => json_encode($batch_results, JSON_PRETTY_PRINT),
                'metadata' => [
                    'handlers_used' => $handlers,
                    'output_success' => $batch_results['overall_success'],
                    'successful_handlers' => $batch_results['successful_handlers'],
                    'failed_handlers' => $batch_results['failed_handlers'],
                    'processing_time' => time()
                ]
            ];
            $context = [
                'job_id' => $job_id,
                'handlers' => $handlers
            ];
            
            $result_packet = apply_filters('dm_create_datapacket', null, $output_data, 'output_result', $context);

            if (!$result_packet instanceof DataPacket) {
                $logger->error('Output Step: Failed to create result DataPacket', [
                    'job_id' => $job_id,
                    'handler' => $handler_name,
                    'conversion_failed' => true
                ]);
                return $this->fail_job($job_id, 'Failed to create output result DataPacket');
            }
            
            // Store result DataPacket
            $success = $this->store_step_data_packet($job_id, $result_packet);

            if ($success) {
                $logger->info('Output Step: Multi-handler publishing completed', [
                    'job_id' => $job_id,
                    'handlers' => $handlers,
                    'handler_count' => count($handlers),
                    'successful_handlers' => $batch_results['successful_handlers'],
                    'failed_handlers' => $batch_results['failed_handlers'],
                    'overall_success' => $batch_results['overall_success']
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
     * @param object $data_packet DataPacket object to publish
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array|null Output result or null on failure
     */
    private function execute_output_handler_direct(string $handler_name, object $data_packet, object $job, array $handler_config): ?array {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name, 'output');
        if (!$handler) {
            $logger->error('Output Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'job_id' => $job->job_id
            ]);
            return null;
        }

        try {
            // Handler is already instantiated from the registry

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
     * Execute multiple output handlers in batch and coordinate results
     * 
     * @param array $handlers Array of handler names to execute
     * @param DataPacket $data_packet DataPacket to publish
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return array Batch execution results
     */
    private function execute_multiple_output_handlers(array $handlers, DataPacket $data_packet, object $job, array $handler_config): array {
        $logger = apply_filters('dm_get_logger', null);
        $successful_handlers = [];
        $failed_handlers = [];
        $handler_results = [];
        
        $logger->info('Output Step: Starting multi-handler publishing', [
            'job_id' => $job->job_id,
            'handlers' => $handlers,
            'handler_count' => count($handlers)
        ]);
        
        // Execute each handler individually
        foreach ($handlers as $handler_name) {
            try {
                $result = $this->execute_output_handler_direct($handler_name, $data_packet, $job, $handler_config);
                
                if ($result && (isset($result['success']) ? $result['success'] : true)) {
                    $successful_handlers[] = $handler_name;
                    $handler_results[$handler_name] = $result;
                    
                    $logger->info('Output Step: Handler published successfully', [
                        'job_id' => $job->job_id,
                        'handler' => $handler_name,
                        'result' => $result
                    ]);
                } else {
                    $failed_handlers[] = $handler_name;
                    $handler_results[$handler_name] = $result;
                    
                    $logger->warning('Output Step: Handler publishing failed', [
                        'job_id' => $job->job_id,
                        'handler' => $handler_name,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                }
                
            } catch (\Exception $e) {
                $failed_handlers[] = $handler_name;
                $handler_results[$handler_name] = ['success' => false, 'error' => $e->getMessage()];
                
                $logger->error('Output Step: Handler execution exception', [
                    'job_id' => $job->job_id,
                    'handler' => $handler_name,
                    'exception' => $e->getMessage()
                ]);
            }
        }
        
        // Determine overall success (at least one handler succeeded)
        $overall_success = !empty($successful_handlers);
        
        // Create summary
        $total_handlers = count($handlers);
        $successful_count = count($successful_handlers);
        $failed_count = count($failed_handlers);
        
        $summary = "{$successful_count}/{$total_handlers} handlers succeeded";
        if ($failed_count > 0) {
            $summary .= ", {$failed_count} failed: " . implode(', ', $failed_handlers);
        }
        
        $batch_results = [
            'overall_success' => $overall_success,
            'successful_handlers' => $successful_handlers,
            'failed_handlers' => $failed_handlers,
            'handler_results' => $handler_results,
            'summary' => $summary,
            'counts' => [
                'total' => $total_handlers,
                'successful' => $successful_count,
                'failed' => $failed_count
            ]
        ];
        
        $logger->info('Output Step: Multi-handler publishing completed', [
            'job_id' => $job->job_id,
            'overall_success' => $overall_success,
            'summary' => $summary,
            'successful_handlers' => $successful_handlers,
            'failed_handlers' => $failed_handlers
        ]);
        
        return $batch_results;
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
    /**
     * Get handler object directly from the handler system.
     * 
     * Uses the revolutionary object-based handler registration to get
     * instantiated handler objects directly, eliminating class discovery.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (input/output)
     * @return object|null Handler object or null if not found
     */
    private function get_handler_object(string $handler_name, string $handler_type): ?object {
        $handlers = apply_filters('dm_get_handlers', null, $handler_type);
        return $handlers[$handler_name] ?? null;
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
            'handlers' => [
                'type' => 'multiselect',
                'label' => 'Output Destinations',
                'description' => 'Choose one or more output handlers to publish data (executed in batch)',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration applied to all selected output handlers',
                'placeholder' => '{"post_title": "Auto-generated Post"}'
            ]
        ];
    }

    // Legacy method removed - OutputStep receives DataPackets from engine via $data_packets parameter

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


