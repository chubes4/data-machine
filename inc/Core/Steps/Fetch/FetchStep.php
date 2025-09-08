<?php

namespace DataMachine\Core\Steps\Fetch;

if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Fetch Step - Data Collection from External Sources
 *
 * Executes configured fetch handlers to collect data from external sources.
 * Generates initial data packets for pipeline processing.
 */
class FetchStep {

    /**
     * Execute fetch processing
     * 
     * Fetches data from external sources using configured handlers.
     * Generates new data entries from sources like RSS feeds, files, APIs, etc.
     * 
     * @param array $parameters Flat parameter structure from dm_engine_parameters filter:
     *   - job_id: Job execution identifier
     *   - flow_step_id: Flow step identifier
     *   - flow_step_config: Step configuration data
     *   - data: Data packet array (typically empty for fetch steps)
     * @return array Updated data packet array with fetched content
     */
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        try {
            // Fetch steps generate data from external sources 
            do_action('dm_log', 'debug', 'Fetch Step: Starting data collection', [
                'flow_step_id' => $flow_step_id,
                'existing_items' => count($data)
            ]);
            
            // Use step configuration directly - no job config introspection needed
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'Fetch Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            $handler_data = $flow_step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                do_action('dm_log', 'error', 'Fetch Step: Fetch step requires handler configuration', [
                    'flow_step_id' => $flow_step_id,
                    'available_flow_step_config' => array_keys($flow_step_config),
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_settings = $handler_data['settings'] ?? [];
            
            // Add flow_step_id to handler settings for proper file isolation
            $handler_settings['flow_step_id'] = $flow_step_config['flow_step_id'] ?? null;

            // Execute single handler - one step, one handler, per flow
            $fetch_entry = $this->execute_handler($handler, $flow_step_config, $handler_settings, $job_id);

            if (!$fetch_entry || empty($fetch_entry['content']['title']) && empty($fetch_entry['content']['body'])) {
                do_action('dm_log', 'error', 'Fetch handler returned no content', ['flow_step_id' => $flow_step_id]);
                return $data; // Return unchanged array
            }

            // Add fetch entry to front of data packet array (newest first)
            $data = apply_filters('dm_data_packet', $data, $fetch_entry, $flow_step_id, 'fetch');

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Fetch Step: Exception during data collection', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return unchanged data packet on failure
            return $data;
        }
    }


    /**
     * Execute fetch handler
     * 
     * @param string $handler_name Handler slug (rss, files, reddit, etc.)
     * @param array $flow_step_config Complete step configuration
     * @param array $handler_settings Handler-specific settings
     * @param string $job_id Job identifier for deduplication tracking
     * @return array|null Fetch entry data packet or null on failure
     */
    private function execute_handler(string $handler_name, array $flow_step_config, array $handler_settings, string $job_id): ?array {
        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            do_action('dm_log', 'error', 'Fetch Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'flow_step_config' => array_keys($flow_step_config)
            ]);
            return null;
        }

        try {
            // Handler is already instantiated from the registry

            // Get pipeline and flow IDs from flow_step_config (provided by orchestrator)
            $pipeline_id = $flow_step_config['pipeline_id'] ?? null;
            $flow_id = $flow_step_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                do_action('dm_log', 'error', 'Fetch Step: Pipeline ID not found in step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }

            // Execute handler - handlers return arrays, use universal conversion
            // Pass job_id for processed items tracking
            $result = $handler->get_fetch_data($pipeline_id, $handler_settings, $job_id);

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
                
                // Handle different handler data structures
                if (isset($result['processed_items']) && is_array($result['processed_items']) && !empty($result['processed_items'])) {
                    $item_data = $result['processed_items'][0];
                    
                    // Check if this is Files handler structure (has file_name/file_path)
                    if (isset($item_data['file_name']) || isset($item_data['file_path'])) {
                        // Files handler format
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
                        // Standard handler format (Reddit/RSS/WordPress) - extract from data['content_string']
                        $content_string = $item_data['data']['content_string'] ?? '';
                        $file_info = $item_data['data']['file_info'] ?? null;
                        
                        // Extract title and body from content_string
                        $title = $item_data['metadata']['original_title'] ?? '';
                        $body = $content_string;
                        
                        // Preserve image handling from file_info
                        if ($file_info && !empty($file_info['url'])) {
                            $result['metadata'] = array_merge($result['metadata'] ?? [], [
                                'image_source_url' => $file_info['url'],
                                'image_mime_type' => $file_info['mime_type'] ?? 'image/jpeg'
                            ]);
                        }
                        
                        // Merge existing metadata from item
                        $result['metadata'] = array_merge($result['metadata'] ?? [], $item_data['metadata'] ?? []);
                    }
                } else {
                    // Direct title/body structure - simplified processing
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
     * Get handler object from filter-based registry
     * 
     * @param string $handler_name Handler slug from dm_handlers filter
     * @return object|null Instantiated handler object or null if not found
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


