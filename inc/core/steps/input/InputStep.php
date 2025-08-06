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
     * Execute input data collection with uniform array approach
     * 
     * UNIFORM ARRAY APPROACH:
     * - Engine provides array of DataPackets (most recent first)
     * - Input steps typically ignore array (closed-door philosophy)
     * - Collects data from external sources only, returns DataPacket format
     * - Self-selects no packets (generates new data)
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packets Array of DataPackets (ignored by input steps)
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, array $data_packets = []): bool {
        $logger = apply_filters('dm_get_logger', null);
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            // Input steps ignore provided DataPacket (closed-door philosophy)
            $logger->debug('Input Step: Starting data collection (closed-door)', [
                'job_id' => $job_id,
                'data_packet_ignored' => $data_packets !== null ? 'yes' : 'n/a'
            ]);
            
            // Context awareness: Get pipeline position if available
            $context = apply_filters('dm_get_context', null, $job_id);
            if ($context) {
                $logger->debug('Input Step: Pipeline context available', [
                    'job_id' => $job_id,
                    'step_position' => $context['current_step_position'] ?? 'unknown',
                    'is_first_step' => $context['pipeline_summary']['is_first_step'] ?? false
                ]);
            }

            // Get job and pipeline configuration
            $job = $db_jobs->get_job($job_id);
            if (!$job) {
                return $this->fail_job($job_id, 'Job not found for input step');
            }

            // Get step configuration
            $step_config = $this->get_step_configuration($job_id, 'input');
            if (!$step_config || empty($step_config['handlers']) || !is_array($step_config['handlers'])) {
                return $this->fail_job($job_id, 'Input step requires handlers array configuration');
            }

            $handlers = $step_config['handlers'];
            $handler_config = $step_config['config'] ?? [];

            // Execute multiple input handlers and merge results
            $merged_data_packet = $this->execute_multiple_input_handlers($handlers, $job, $handler_config);

            if (!$merged_data_packet instanceof DataPacket) {
                return $this->fail_job($job_id, 'Failed to create merged DataPacket from input handlers');
            }

            if (!$merged_data_packet->hasContent()) {
                return $this->fail_job($job_id, 'All input handlers returned empty content');
            }


            // Store DataPacket for next step (closed-door: only forward data flow)
            $success = $this->store_step_data_packet($job_id, $merged_data_packet);

            if ($success) {
                $logger->debug('Input Step: Multi-handler data collection completed', [
                    'job_id' => $job_id,
                    'handlers' => $handlers,
                    'handler_count' => count($handlers),
                    'content_length' => $merged_data_packet->getContentLength(),
                    'source_types' => $merged_data_packet->metadata['source_types'] ?? []
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
     * Get step configuration from pipeline or job context
     * 
     * @param int $job_id Job ID
     * @param string $step_name Step name
     * @return array|null Step configuration or null if not found
     */
    private function get_step_configuration(int $job_id, string $step_name): ?array {
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
        
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
        $db_pipelines = $all_databases['pipelines'] ?? null;
        if ($db_pipelines) {
            $config = $db_pipelines->get_pipeline_flow_configuration($pipeline_id);
            $flow_config = isset($config['steps']) ? $config['steps'] : [];
            // Find input step configuration in flow
            foreach ($flow_config as $step) {
                if ($step['slug'] === 'input_step' || $step['slug'] === 'input') {
                    return $step;
                }
            }
        }

        return null;
    }

    /**
     * Execute input handler directly using pure auto-discovery
     * 
     * @param string $handler_name Input handler name
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return DataPacket|null DataPacket or null on failure
     */
    private function execute_input_handler_direct(string $handler_name, object $job, array $handler_config): ?DataPacket {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name, 'input');
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
                'pipeline_id' => $pipeline_id,
                'user_id' => $user_id
            ];
            
            $data_packet = apply_filters('dm_create_datapacket', null, $result, $handler_name, $context);

            if (!$data_packet instanceof DataPacket) {
                $logger->error('Input Step: Failed to create DataPacket from handler output', [
                    'handler' => $handler_name,
                    'job_id' => $job->job_id,
                    'result_type' => gettype($result),
                    'conversion_failed' => true
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
    private function get_handler_object(string $handler_name, string $handler_type): ?object {
        $all_handlers = apply_filters('dm_get_handlers', []);
        $handlers = array_filter($all_handlers, function($handler) use ($handler_type) {
            return ($handler['type'] ?? '') === $handler_type;
        });
        return $handlers[$handler_name] ?? null;
    }

    /**
     * Execute multiple input handlers and merge their results
     * 
     * @param array $handlers Array of handler names to execute
     * @param object $job Job object
     * @param array $handler_config Handler configuration
     * @return DataPacket|null Merged DataPacket or null on failure
     */
    private function execute_multiple_input_handlers(array $handlers, object $job, array $handler_config): ?DataPacket {
        $logger = apply_filters('dm_get_logger', null);
        $successful_results = [];
        $successful_handlers = [];
        
        $logger->debug('Input Step: Starting multi-handler execution', [
            'job_id' => $job->job_id,
            'handlers' => $handlers,
            'handler_count' => count($handlers)
        ]);
        
        // Execute each handler individually
        foreach ($handlers as $handler_name) {
            try {
                $result = $this->execute_input_handler_direct($handler_name, $job, $handler_config);
                
                if ($result instanceof DataPacket && $result->hasContent()) {
                    $successful_results[] = $result;
                    $successful_handlers[] = $handler_name;
                    
                    $logger->debug('Input Step: Handler executed successfully', [
                        'job_id' => $job->job_id,
                        'handler' => $handler_name,
                        'content_length' => $result->getContentLength()
                    ]);
                } else {
                    $logger->warning('Input Step: Handler returned empty or invalid result', [
                        'job_id' => $job->job_id,
                        'handler' => $handler_name,
                        'result_type' => is_object($result) ? get_class($result) : gettype($result)
                    ]);
                }
                
            } catch (\Exception $e) {
                $logger->error('Input Step: Handler execution failed', [
                    'job_id' => $job->job_id,
                    'handler' => $handler_name,
                    'exception' => $e->getMessage()
                ]);
            }
        }
        
        // Check if we have any successful results
        if (empty($successful_results)) {
            $logger->error('Input Step: All handlers failed or returned empty content', [
                'job_id' => $job->job_id,
                'attempted_handlers' => $handlers
            ]);
            return null;
        }
        
        // Merge results into single DataPacket
        return $this->merge_input_datapackets($successful_results, $successful_handlers, $job->job_id);
    }

    /**
     * Merge multiple input DataPackets into a single comprehensive DataPacket
     * 
     * @param array $datapackets Array of DataPacket objects to merge
     * @param array $handler_names Array of handler names that created the packets
     * @param int $job_id Job ID for logging
     * @return DataPacket Merged DataPacket
     */
    private function merge_input_datapackets(array $datapackets, array $handler_names, int $job_id): DataPacket {
        $logger = apply_filters('dm_get_logger', null);
        
        if (empty($datapackets)) {
            throw new \InvalidArgumentException('Cannot merge empty datapackets array');
        }
        
        // Use first packet as base
        $base_packet = $datapackets[0];
        $merged_packet = clone $base_packet;
        
        // Merge content from all packets
        $all_content = [];
        $all_titles = [];
        $all_source_types = [];
        
        foreach ($datapackets as $index => $packet) {
            $handler_name = $handler_names[$index] ?? "handler_$index";
            $source_type = $packet->metadata['source_type'] ?? $handler_name;
            
            // Collect titles
            if (!empty($packet->content['title'])) {
                $all_titles[] = "[$source_type] " . $packet->content['title'];
            }
            
            // Collect content with source attribution
            if (!empty($packet->content['body'])) {
                $all_content[] = "=== $source_type Content ===\n" . $packet->content['body'];
            }
            
            // Collect source types
            $all_source_types[] = $source_type;
        }
        
        // Set merged content
        $merged_packet->content['title'] = !empty($all_titles) ? implode(' | ', $all_titles) : 'Multi-Source Content';
        $merged_packet->content['body'] = implode("\n\n", $all_content);
        
        // Update metadata to reflect multi-source nature
        $merged_packet->metadata['source_type'] = 'multi_input';
        $merged_packet->metadata['source_types'] = $all_source_types;
        $merged_packet->metadata['handler_count'] = count($datapackets);
        $merged_packet->metadata['merge_timestamp'] = current_time('c');
        
        // Update processing information
        $merged_packet->processing['steps_completed'] = array_merge(
            $merged_packet->processing['steps_completed'] ?? [],
            ['multi_input']
        );
        
        $logger->debug('Input Step: DataPackets merged successfully', [
            'job_id' => $job_id,
            'source_packets' => count($datapackets),
            'merged_content_length' => strlen($merged_packet->content['body']),
            'source_types' => $all_source_types
        ]);
        
        return $merged_packet;
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
        
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;
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


