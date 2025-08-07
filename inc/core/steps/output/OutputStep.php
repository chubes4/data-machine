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
     * Execute output publishing with engine DataPacket flow
     * 
     * PURE ENGINE FLOW:
     * - Receives DataPackets from previous steps via engine
     * - Uses latest packet for publishing (data_packets[0])
     * - Returns result DataPackets to engine for next step
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets from previous steps
     * @param array $job_config Complete job configuration from JobCreator
     * @return array Array of output DataPackets for next step
     */
    public function execute(int $job_id, array $data_packets = [], array $job_config = []): array {
        $logger = apply_filters('dm_get_logger', null);
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            $logger->debug('Output Step: Starting data publishing (closed-door)', ['job_id' => $job_id]);

            // Get job and pipeline configuration
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                $logger->error('Output Step: Job not found for output step', ['job_id' => $job_id]);
                return false;
            }

            // Get step configuration
            $step_config = $this->get_step_configuration($job_id, 'output');
            if (!$step_config || empty($step_config['handler'])) {
                $logger->error('Output Step: Output step requires handler configuration', ['job_id' => $job_id]);
                return false;
            }

            $handler = $step_config['handler'];
            $handler_config = $step_config['config'] ?? [];

            // Output steps use latest DataPacket (first in array)
            $data_packet = $data_packets[0] ?? null;
            if (!$data_packet) {
                $logger->error('Output Step: No DataPacket available from previous step', ['job_id' => $job_id]);
                return [];
            }

            // Execute single output handler - one step, one handler, per flow
            $handler_result = $this->execute_output_handler_direct($handler, $data_packet, $job, $handler_config);

            if (!$handler_result) {
                $logger->error('Output Step: Handler execution failed', [
                    'job_id' => $job_id,
                    'handler' => $handler
                ]);
                return [];
            }

            // Create result DataPacket using filter system
            $output_data = [
                'content' => json_encode($handler_result, JSON_PRETTY_PRINT),
                'metadata' => [
                    'handler_used' => $handler,
                    'output_success' => true,
                    'processing_time' => time()
                ]
            ];
            $context = [
                'job_id' => $job_id,
                'handler' => $handler
            ];
            
            // Create DataPacket using universal constructor
            try {
                $result_packet = new DataPacket(
                    'Output Complete',
                    $output_data['content'] ?? '',
                    'output_result'
                );
                
                // Add output-specific metadata
                if (isset($output_data['metadata'])) {
                    $metadata = $output_data['metadata'];
                    $result_packet->metadata['handler_used'] = $metadata['handler_used'] ?? $handler;
                    $result_packet->metadata['output_success'] = $metadata['output_success'] ?? true;
                    $result_packet->metadata['processing_time'] = $metadata['processing_time'] ?? time();
                }
                
                // Add context information
                if (isset($context['job_id'])) {
                    $result_packet->metadata['job_id'] = $context['job_id'];
                }
                if (isset($context['handler'])) {
                    $result_packet->metadata['handler'] = $context['handler'];
                }
                
                $result_packet->processing['steps_completed'][] = 'output';
                
            } catch (\Exception $e) {
                $logger->error('Output Step: Failed to create result DataPacket', [
                    'job_id' => $job_id,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
            
            // Return result DataPackets to engine (pure engine flow)
            $logger->debug('Output Step: Publishing completed successfully', [
                'job_id' => $job_id,
                'handler' => $handler,
                'handler_result' => $handler_result ? 'success' : 'failed'
            ]);

            return [$result_packet];

        } catch (\Exception $e) {
            $logger->error('Output Step: Exception during publishing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty array on failure (engine interprets as step failure)
            return [];
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
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_jobs || !$db_flows) {
            return null;
        }
        
        // Get job to find flow_id
        $job = $db_jobs->get_job($job_id);
        if (!$job) {
            return null;
        }

        // Get flow_id from job
        $flow_id = is_object($job) ? $job->flow_id : ($job['flow_id'] ?? null);
        if (!$flow_id) {
            return null;
        }

        // Get flow record from flows database
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            return null;
        }

        // Parse flow's flow_config to find step configuration
        $flow_config_json = is_object($flow) ? $flow->flow_config : ($flow['flow_config'] ?? '{}');
        $flow_config = json_decode($flow_config_json, true);
        
        if (!is_array($flow_config)) {
            return null;
        }

        // Return step configuration for the specified step
        return $flow_config[$step_name] ?? null;
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
            
            // Convert DataPacket to pure JSON object
            $json_data_packet = json_decode(json_encode($data_packet));
            $json_data_packet->output_config = $handler_config;
            
            // Execute handler with pure JSON object - beautiful simplicity
            $output_result = $handler->handle_output($json_data_packet);

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
     * Get handler object directly from the handler system.
     * 
     * Uses the object-based handler registration to get
     * instantiated handler objects directly, eliminating class discovery.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (input/output)
     * @return object|null Handler object or null if not found
     */
    private function get_handler_object(string $handler_name, string $handler_type): ?object {
        // Direct handler discovery - no redundant filtering needed
        $all_handlers = apply_filters('dm_get_handlers', []);
        $handler_config = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_config || !isset($handler_config['class'])) {
            return null;
        }
        
        // Verify handler type matches
        if (($handler_config['type'] ?? '') !== $handler_type) {
            return null;
        }
        
        $class_name = $handler_config['class'];
        return class_exists($class_name) ? new $class_name() : null;
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
        // Get available output handlers via pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $output_handlers = array_filter($all_handlers, function($handler) {
            return ($handler['type'] ?? '') === 'output';
        });
        $handler_options = [];
        
        foreach ($output_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handler' => [
                'type' => 'select',
                'label' => 'Output Destination',
                'description' => 'Choose one output handler to publish data (single handler per step)',
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

    // OutputStep receives DataPackets from engine via $data_packets parameter


}


