<?php

namespace DataMachine\Core\Steps\Input;

use DataMachine\Engine\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

// DataPacket is engine-only - steps work with simple arrays provided by engine

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
class InputStep {

    /**
     * Execute input data collection with engine DataPacket flow
     * 
     * PURE ENGINE FLOW:
     * - Input steps generate new data from external sources
     * - Ignores provided DataPackets (closed-door philosophy)
     * - Returns output DataPackets to engine for next step
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets (ignored by input steps)
     * @param array $job_config Complete job configuration from JobCreator
     * @return array Array of output DataPackets for next step
     */
    public function execute(int $job_id, array $data_packets = [], array $job_config = []): array {
        $logger = apply_filters('dm_get_logger', null);
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            // Input steps ignore provided DataPacket (closed-door philosophy)
            $logger->debug('Input Step: Starting data collection (closed-door)', [
                'job_id' => $job_id,
                'data_packet_ignored' => $data_packets !== null ? 'yes' : 'n/a'
            ]);
            
            // Input steps ignore pipeline context - pure data generation

            // Get job and pipeline configuration
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Job not found for input step', ['job_id' => $job_id]);
                }
                return false;
            }

            // Get step configuration
            $step_config = $this->get_step_configuration($job_id, 'input');
            if (!$step_config || empty($step_config['handler'])) {
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Input step requires handler configuration', ['job_id' => $job_id]);
                }
                return false;
            }

            $handler = $step_config['handler'];
            $handler_config = $step_config['config'] ?? [];

            // Execute single handler - one step, one handler, per flow
            $data_packet = $this->execute_handler($handler, $job, $handler_config);

            if (!$data_packet instanceof DataPacket) {
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Failed to create DataPacket from input handler', ['job_id' => $job_id]);
                }
                return [];
            }

            if (!$data_packet->hasContent()) {
                $logger = apply_filters('dm_get_logger', null);
                if ($logger) {
                    $logger->error('Input handler returned empty content', ['job_id' => $job_id]);
                }
                return [];
            }

            // Return DataPackets to engine (pure engine flow)
            $logger->debug('Input Step: Data collection completed', [
                'job_id' => $job_id,
                'handler' => $handler,
                'content_length' => $data_packet->getContentLength(),
                'source_type' => $data_packet->metadata['source_type'] ?? ''
            ]);

            return [$data_packet];

        } catch (\Exception $e) {
            $logger->error('Input Step: Exception during data collection', [
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
     * Execute input handler directly using pure auto-discovery
     * 
     * @param string $handler_name Input handler name
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return DataPacket|null DataPacket or null on failure
     */
    private function execute_handler(string $handler_name, object $job, array $handler_config): ?DataPacket {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            $logger->error('Input Step: Handler not found or invalid', [
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
                $logger->error('Input Step: Pipeline ID not found', [
                    'job_id' => $job->job_id
                ]);
                return null;
            }

            // Execute handler - handlers return arrays, use universal conversion
            $result = $handler->get_input_data($pipeline_id, $handler_config);

            // Convert handler output to DataPacket using filter system
            $context = [
                'job_id' => $job->job_id,
                'pipeline_id' => $pipeline_id
            ];
            
            // Create DataPacket using universal constructor - handlers must return proper structure
            try {
                if (!is_array($result)) {
                    throw new \InvalidArgumentException('Handler output must be an array');
                }
                
                $title = $result['title'] ?? '';
                $body = $result['body'] ?? '';
                
                $data_packet = new DataPacket($title, $body, $handler_name);
                
                // Add any additional metadata from handler
                if (isset($result['metadata']) && is_array($result['metadata'])) {
                    $data_packet->metadata = array_merge($data_packet->metadata, $result['metadata']);
                }
                
                // Add any attachments from handler
                if (isset($result['attachments']) && is_array($result['attachments'])) {
                    $data_packet->attachments = array_merge($data_packet->attachments, $result['attachments']);
                }
                
                // Add context information
                $data_packet->metadata['job_id'] = $context['job_id'];
                $data_packet->metadata['pipeline_id'] = $context['pipeline_id'];
                $data_packet->processing['steps_completed'][] = 'input';
                
            } catch (\Exception $e) {
                $logger->error('Input Step: Failed to create DataPacket from handler output', [
                    'handler' => $handler_name,
                    'job_id' => $job->job_id,
                    'result_type' => gettype($result),
                    'error' => $e->getMessage()
                ]);
                return null;
            }

            return $data_packet;

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
    private function get_handler_object(string $handler_name): ?object {
        // Direct handler discovery - no redundant filtering needed
        $all_handlers = apply_filters('dm_get_handlers', []);
        $handler_config = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_config || !isset($handler_config['class'])) {
            return null;
        }
        
        $class_name = $handler_config['class'];
        return class_exists($class_name) ? new $class_name() : null;
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
        // Get available input handlers via pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $input_handlers = array_filter($all_handlers, function($handler) {
            return ($handler['type'] ?? '') === 'input';
        });
        $handler_options = [];
        
        foreach ($input_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handlers' => [
                'type' => 'multiselect',
                'label' => 'Input Sources',
                'description' => 'Choose one or more input handlers to collect data (executed in batch)',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration applied to all selected input handlers',
                'placeholder' => '{"feed_url": "https://example.com/feed.xml"}'
            ]
        ];
    }


    /**
     * Fail a job with an error message.
     *
     * @param int $job_id The job ID.
     * @param string $message The error message.
     * @return bool Always returns false for easy return usage.
     */
}


