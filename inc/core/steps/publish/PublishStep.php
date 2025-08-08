<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal Publish Step - Executes any publish handler
 * 
 * This step can publish data to any configured publish destination.
 * No interface requirements - detected via method existence only.
 * External plugins can create completely independent publish step classes.
 * All functionality is capability-based, not inheritance-based.
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in publishing workflows.
 */
class PublishStep {

    /**
     * Execute publish publishing with pure array data packet system
     * 
     * PURE ARRAY SYSTEM:
     * - Receives the cumulative data packet array (newest items first)
     * - Uses latest entry for publishing (data_packet[0])
     * - Adds publish result to the array and returns updated array
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packet The cumulative data packet array for this job
     * @param array $job_config Complete job configuration from JobCreator
     * @return array Updated data packet array with publish result added
     */
    public function execute(int $job_id, array $data_packet = [], array $job_config = []): array {
        $logger = apply_filters('dm_get_logger', null);
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            $logger->debug('Publish Step: Starting data publishing (closed-door)', ['job_id' => $job_id]);

            // Get step configuration from job_config using current step position
            $flow_config = $job_config['flow_config'] ?? [];
            $step_position = $job_config['current_step_position'] ?? null;
            
            if ($step_position === null) {
                $logger->error('Publish Step: Publish step requires current step position', [
                    'job_id' => $job_id,
                    'available_job_config' => array_keys($job_config)
                ]);
                return [];
            }
            
            $step_config = $flow_config[$step_position] ?? null;
            
            if (!$step_config || empty($step_config['handler'])) {
                $logger->error('Publish Step: Publish step requires handler configuration', [
                    'job_id' => $job_id,
                    'step_position' => $step_position,
                    'available_flow_positions' => array_keys($flow_config)
                ]);
                return [];
            }

            $handler_data = $step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                $logger->error('Publish Step: Publish step handler configuration invalid', [
                    'job_id' => $job_id,
                    'step_position' => $step_position,
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_config = $handler_data['settings'] ?? [];

            // Publish steps use latest data entry (first in array)
            $latest_data = $data_packet[0] ?? null;
            if (!$latest_data) {
                $logger->error('Publish Step: No data available from previous step', ['job_id' => $job_id]);
                return $data_packet; // Return unchanged array
            }

            // Execute single publish handler - one step, one handler, per flow
            $handler_result = $this->execute_publish_handler_direct($handler, $latest_data, $job_config, $handler_config);

            if (!$handler_result) {
                $logger->error('Publish Step: Handler execution failed', [
                    'job_id' => $job_id,
                    'handler' => $handler
                ]);
                return $data_packet; // Return unchanged array
            }

            // Create publish data entry for the data packet array
            $publish_entry = [
                'type' => 'publish',
                'handler' => $handler,
                'content' => [
                    'title' => 'Publish Complete',
                    'body' => json_encode($handler_result, JSON_PRETTY_PRINT)
                ],
                'metadata' => [
                    'handler_used' => $handler,
                    'publish_success' => true,
                    'job_id' => $job_id,
                    'source_type' => $latest_data['metadata']['source_type'] ?? 'unknown'
                ],
                'result' => $handler_result,
                'timestamp' => time()
            ];
            
            // Add publish entry to front of data packet array (newest first)
            array_unshift($data_packet, $publish_entry);
            
            $logger->debug('Publish Step: Publishing completed successfully', [
                'job_id' => $job_id,
                'handler' => $handler,
                'total_items' => count($data_packet)
            ]);

            return $data_packet;

        } catch (\Exception $e) {
            $logger->error('Publish Step: Exception during publishing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty array on failure (engine interprets as step failure)
            return $data_packet;
        }
    }



    /**
     * Execute publish handler directly using pure auto-discovery
     * 
     * @param string $handler_name Publish handler name
     * @param array $data_entry Latest data entry from data packet array
     * @param array $job_config Job configuration from JobCreator
     * @param array $handler_config Handler configuration
     * @return array|null Publish result or null on failure
     */
    private function execute_publish_handler_direct(string $handler_name, array $data_entry, array $job_config, array $handler_config): ?array {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name, 'publish');
        if (!$handler) {
            $logger->error('Publish Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'job_config' => array_keys($job_config)
            ]);
            return null;
        }

        try {
            // Handler is already instantiated from the registry

            // Get pipeline and flow IDs from job_config (provided by JobCreator)
            $pipeline_id = $job_config['pipeline_id'] ?? null;
            $flow_id = $job_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                $logger->error('Publish Step: Pipeline ID not found in job config', [
                    'job_config_keys' => array_keys($job_config)
                ]);
                return null;
            }

            // Universal JSON data entry interface - simple and direct
            
            // Convert data entry to pure JSON object  
            $json_data_entry = json_decode(json_encode($data_entry));
            $json_data_entry->publish_config = $handler_config;
            
            // Execute handler with pure JSON object - beautiful simplicity
            $publish_result = $handler->handle_publish($json_data_entry);

            return $publish_result;

        } catch (\Exception $e) {
            $logger->error('Publish Step: Handler execution failed', [
                'handler' => $handler_name,
                'pipeline_id' => $pipeline_id ?? 'unknown',
                'flow_id' => $flow_id ?? 'unknown',
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }



    /**
     * Get handler object directly from the handler system.
     * 
     * Uses the object-based handler registration to get
     * instantiated handler objects directly, eliminating class discovery.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (fetch/publish)
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
     * Define configuration fields for publish step
     * 
     * PURE CAPABILITY-BASED: External publish step classes only need:
     * - execute(int $job_id): bool method
     * - get_prompt_fields(): array static method (optional)
     * - Parameter-less constructor
     * - No interface implementation required
     * 
     * @return array Configuration field definitions for UI
     */
    public static function get_prompt_fields(): array {
        // Get available publish handlers via pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $publish_handlers = array_filter($all_handlers, function($handler) {
            return ($handler['type'] ?? '') === 'publish';
        });
        $handler_options = [];
        
        foreach ($publish_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handler' => [
                'type' => 'select',
                'label' => 'Publish Destination',
                'description' => 'Choose one publish handler to publish data',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration for the selected publish handler',
                'placeholder' => '{"post_title": "Auto-generated Post"}'
            ]
        ];
    }

    // PublishStep receives cumulative data packet array from engine via $data_packet parameter


}


