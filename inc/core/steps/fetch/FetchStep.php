<?php

namespace DataMachine\Core\Steps\Fetch;

if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal Fetch Step - Executes any fetch handler
 * 
 * This step can gather data from any configured fetch source.
 * No interface requirements - detected via method existence only.
 * External plugins can create completely independent fetch step classes.
 * All functionality is capability-based, not inheritance-based.
 * 
 * Handler selection is determined by step configuration, enabling
 * complete flexibility in pipeline composition.
 */
class FetchStep {

    /**
     * Execute fetch data collection with pure array data packet system
     * 
     * PURE ARRAY SYSTEM:
     * - Fetch steps generate new data from external sources
     * - Receives data packet array (may be empty for first step)
     * - Adds fetch data to the array and returns updated array
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packet The cumulative data packet array for this job  
     * @param array $job_config Complete job configuration from JobCreator
     * @return array Updated data packet array with fetch data added
     */
    public function execute(int $job_id, array $data_packet = [], array $job_config = []): array {
        $logger = apply_filters('dm_get_logger', null);
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            // Fetch steps generate data from external sources 
            $logger->debug('Fetch Step: Starting data collection', [
                'job_id' => $job_id,
                'existing_items' => count($data_packet)
            ]);
            
            // Fetch steps ignore pipeline context - pure data generation

            // Get step configuration from job_config using current step position
            $flow_config = $job_config['flow_config'] ?? [];
            $step_position = $job_config['current_step_position'] ?? null;
            
            if ($step_position === null) {
                $logger->error('Fetch step requires current step position', [
                    'job_id' => $job_id,
                    'available_job_config' => array_keys($job_config)
                ]);
                return [];
            }
            
            $step_config = $flow_config[$step_position] ?? null;
            
            if (!$step_config || empty($step_config['handler'])) {
                $logger->error('Fetch step requires handler configuration', [
                    'job_id' => $job_id,
                    'step_position' => $step_position,
                    'available_flow_positions' => array_keys($flow_config)
                ]);
                return [];
            }

            $handler_data = $step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                $logger->error('Fetch step handler configuration invalid', [
                    'job_id' => $job_id,
                    'step_position' => $step_position,
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_config = $handler_data['settings'] ?? [];
            
            // Add flow_step_id to handler config for proper file isolation
            $handler_config['flow_step_id'] = $step_config['flow_step_id'] ?? null;

            // Execute single handler - one step, one handler, per flow
            $fetch_entry = $this->execute_handler($handler, $job_config, $handler_config);

            if (!$fetch_entry || empty($fetch_entry['content']['title']) && empty($fetch_entry['content']['body'])) {
                $logger->error('Fetch handler returned no content', ['job_id' => $job_id]);
                return $data_packet; // Return unchanged array
            }

            // Add fetch entry to front of data packet array (newest first)
            array_unshift($data_packet, $fetch_entry);

            $logger->debug('Fetch Step: Data collection completed', [
                'job_id' => $job_id,
                'handler' => $handler,
                'content_length' => strlen($fetch_entry['content']['body'] ?? '') + strlen($fetch_entry['content']['title'] ?? ''),
                'source_type' => $fetch_entry['metadata']['source_type'] ?? '',
                'total_items' => count($data_packet)
            ]);

            return $data_packet;

        } catch (\Exception $e) {
            $logger->error('Fetch Step: Exception during data collection', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure
            return $data_packet;
        }
    }


    /**
     * Execute fetch handler directly using pure auto-discovery
     * 
     * @param string $handler_name Fetch handler name
     * @param array $job_config Job configuration from JobCreator
     * @param array $handler_config Handler configuration
     * @return array|null Fetch entry array or null on failure
     */
    private function execute_handler(string $handler_name, array $job_config, array $handler_config): ?array {
        $logger = apply_filters('dm_get_logger', null);

        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            $logger->error('Fetch Step: Handler not found or invalid', [
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
                $logger->error('Fetch Step: Pipeline ID not found in job config', [
                    'job_config_keys' => array_keys($job_config)
                ]);
                return null;
            }

            // Execute handler - handlers return arrays, use universal conversion
            // Pass flow_id for processed items tracking
            $result = $handler->get_fetch_data($pipeline_id, $handler_config, $flow_id);

            // Convert handler output to data entry for the data packet array
            $context = [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id
            ];
            
            // Create data entry using universal structure - handlers must return proper format
            try {
                if (!is_array($result)) {
                    throw new \InvalidArgumentException('Handler output must be an array');
                }
                
                // Handle Files handler's processed_items format
                if (isset($result['processed_items']) && is_array($result['processed_items']) && !empty($result['processed_items'])) {
                    $item_data = $result['processed_items'][0]; // Process first file
                    $title = $item_data['original_title'] ?? $item_data['file_name'] ?? 'Uploaded File';
                    $body = "File: " . ($item_data['file_name'] ?? '') . "\nPath: " . ($item_data['file_path'] ?? '') . "\nType: " . ($item_data['mime_type'] ?? '') . "\nSize: " . ($item_data['file_size'] ?? 0) . " bytes";
                    
                    // Add file-specific metadata to result for downstream processing
                    $result['metadata'] = array_merge($result['metadata'] ?? [], [
                        'file_path' => $item_data['file_path'] ?? '',
                        'original_filename' => $item_data['file_name'] ?? '',
                        'mime_type' => $item_data['mime_type'] ?? '',
                        'file_size' => $item_data['file_size'] ?? 0
                    ]);
                } else {
                    // Fallback for other handlers that return title/body directly
                    $title = $result['title'] ?? '';
                    $body = $result['body'] ?? '';
                }
                
                // Create fetch data entry for the data packet array
                $fetch_entry = [
                    'type' => 'fetch',
                    'handler' => $handler_name,
                    'content' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'metadata' => array_merge([
                        'source_type' => $handler_name,
                        'pipeline_id' => $context['pipeline_id'],
                        'flow_id' => $context['flow_id']
                    ], $result['metadata'] ?? []),
                    'attachments' => $result['attachments'] ?? [],
                    'timestamp' => time()
                ];
                
            } catch (\Exception $e) {
                $logger->error('Fetch Step: Failed to create data entry from handler output', [
                    'handler' => $handler_name,
                    'pipeline_id' => $pipeline_id,
                    'flow_id' => $flow_id,
                    'result_type' => gettype($result),
                    'error' => $e->getMessage()
                ]);
                return null;
            }

            return $fetch_entry;

        } catch (\Exception $e) {
            $logger->error('Fetch Step: Handler execution failed', [
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
     * Define configuration fields for fetch step
     * 
     * PURE CAPABILITY-BASED: External fetch step classes only need:
     * - execute(int $job_id): bool method
     * - get_prompt_fields(): array static method (optional)
     * - Parameter-less constructor
     * - No interface implementation required
     * 
     * @return array Configuration field definitions for UI
     */
    public static function get_prompt_fields(): array {
        // Get available fetch handlers via pure discovery
        $all_handlers = apply_filters('dm_get_handlers', []);
        $fetch_handlers = array_filter($all_handlers, function($handler) {
            return ($handler['type'] ?? '') === 'fetch';
        });
        $handler_options = [];
        
        foreach ($fetch_handlers as $slug => $handler_info) {
            $handler_options[$slug] = $handler_info['label'] ?? ucfirst($slug);
        }

        return [
            'handlers' => [
                'type' => 'multiselect',
                'label' => 'Fetch Sources',
                'description' => 'Choose one or more fetch handlers to collect data (executed in batch)',
                'options' => $handler_options,
                'required' => true
            ],
            'config' => [
                'type' => 'json',
                'label' => 'Handler Configuration',
                'description' => 'JSON configuration applied to all selected fetch handlers',
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


