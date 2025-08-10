<?php

namespace DataMachine\Core\Steps\Fetch;

if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal Fetch Step - Executes any fetch handler
 * 
 * This step can gather data from any configured fetch source using the filter-based
 * handler discovery system. Handler configuration is managed through the modal system
 * and flow-level settings, maintaining complete separation from step-level logic.
 * 
 * PURE CAPABILITY-BASED: External fetch step classes only need:
 * - execute(int $job_id, array $data, array $step_config): array method
 * - Parameter-less constructor
 * - No interface implementation required
 * 
 * Handler selection is determined by flow configuration, enabling
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
     * @param array $data The cumulative data packet array for this job  
     * @param array $step_config Step configuration including handler settings
     * @return array Updated data packet array with fetch data added
     */
    public function execute($flow_step_id, array $data = [], array $step_config = []): array {
        $job_id = $step_config['job_id'] ?? 0;
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            // Fetch steps generate data from external sources 
            do_action('dm_log', 'debug', 'Fetch Step: Starting data collection', [
                'job_id' => $job_id,
                'existing_items' => count($data)
            ]);
            
            // Use step configuration directly - no job config introspection needed
            if (empty($step_config)) {
                do_action('dm_log', 'error', 'Fetch Step: No step configuration provided', ['job_id' => $job_id]);
                return [];
            }

            $handler_data = $step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                do_action('dm_log', 'error', 'Fetch Step: Fetch step requires handler configuration', [
                    'job_id' => $job_id,
                    'available_step_config' => array_keys($step_config),
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_settings = $handler_data['settings'] ?? [];
            
            // Add flow_step_id to handler settings for proper file isolation
            $handler_settings['flow_step_id'] = $step_config['flow_step_id'] ?? null;

            // Execute single handler - one step, one handler, per flow
            $fetch_entry = $this->execute_handler($handler, $step_config, $handler_settings);

            if (!$fetch_entry || empty($fetch_entry['content']['title']) && empty($fetch_entry['content']['body'])) {
                do_action('dm_log', 'error', 'Fetch handler returned no content', ['job_id' => $job_id]);
                return $data; // Return unchanged array
            }

            // Add fetch entry to front of data packet array (newest first)
            array_unshift($data, $fetch_entry);

            do_action('dm_log', 'debug', 'Fetch Step: Data collection completed', [
                'job_id' => $job_id,
                'handler' => $handler,
                'content_length' => strlen($fetch_entry['content']['body'] ?? '') + strlen($fetch_entry['content']['title'] ?? ''),
                'source_type' => $fetch_entry['metadata']['source_type'] ?? '',
                'total_items' => count($data)
            ]);

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Fetch Step: Exception during data collection', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure
            return $data;
        }
    }


    /**
     * Execute fetch handler directly using pure auto-discovery
     * 
     * @param string $handler_name Fetch handler name
     * @param array $step_config Step configuration including pipeline/flow IDs
     * @param array $handler_settings Handler settings
     * @return array|null Fetch entry array or null on failure
     */
    private function execute_handler(string $handler_name, array $step_config, array $handler_settings): ?array {
        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            do_action('dm_log', 'error', 'Fetch Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'step_config' => array_keys($step_config)
            ]);
            return null;
        }

        try {
            // Handler is already instantiated from the registry

            // Get pipeline and flow IDs from step_config (provided by orchestrator)
            $pipeline_id = $step_config['pipeline_id'] ?? null;
            $flow_id = $step_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                do_action('dm_log', 'error', 'Fetch Step: Pipeline ID not found in step config', [
                    'step_config_keys' => array_keys($step_config)
                ]);
                return null;
            }

            // Execute handler - handlers return arrays, use universal conversion
            // Pass flow_id for processed items tracking
            $result = $handler->get_fetch_data($pipeline_id, $handler_settings, $flow_id);

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
                do_action('dm_log', 'error', 'Fetch Step: Failed to create data entry from handler output', [
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
            do_action('dm_log', 'error', 'Fetch Step: Handler execution failed', [
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
        $all_handlers = apply_filters('dm_handlers', []);
        $handler_info = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_info || !isset($handler_info['class'])) {
            return null;
        }
        
        $class_name = $handler_info['class'];
        return class_exists($class_name) ? new $class_name() : null;
    }


}


