<?php

namespace DataMachine\Core\Steps\Fetch;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Executes fetch handlers to collect data from external sources.
 */
class FetchStep {

    /**
     * @param array $parameters Flat parameter structure from dm_engine_parameters filter
     * @return array Updated data packet array
     */
    public function execute(array $parameters): array {
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        try {
            do_action('dm_log', 'debug', 'Fetch Step: Starting data collection', [
                'flow_step_id' => $flow_step_id,
                'existing_items' => count($data)
            ]);

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
            
            $handler_settings['flow_step_id'] = $flow_step_config['flow_step_id'] ?? null;

            $fetch_entry = $this->execute_handler($handler, $flow_step_config, $handler_settings, $job_id);

            if (!$fetch_entry || empty($fetch_entry['content']['title']) && empty($fetch_entry['content']['body'])) {
                do_action('dm_log', 'error', 'Fetch handler returned no content', ['flow_step_id' => $flow_step_id]);
                return $data; // Return unchanged array
            }

            $data = apply_filters('dm_data_packet', $data, $fetch_entry, $flow_step_id, 'fetch');

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Fetch Step: Exception during data collection', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $data;
        }
    }


    /**
     * @param string $handler_name Handler slug
     * @param array $flow_step_config Step configuration
     * @param array $handler_settings Handler settings
     * @param string $job_id Job identifier
     * @return array|null Fetch entry or null
     */
    private function execute_handler(string $handler_name, array $flow_step_config, array $handler_settings, string $job_id): ?array {
        $handler = $this->get_handler_object($handler_name);
        if (!$handler) {
            do_action('dm_log', 'error', 'Fetch Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'flow_step_config' => array_keys($flow_step_config)
            ]);
            return null;
        }

        try {
            $pipeline_id = $flow_step_config['pipeline_id'] ?? null;
            $flow_id = $flow_step_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                do_action('dm_log', 'error', 'Fetch Step: Pipeline ID not found in step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }

            $result = $handler->get_fetch_data($pipeline_id, $handler_settings, $job_id);

            $context = [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id
            ];
            
            try {
                if (!is_array($result)) {
                    throw new \InvalidArgumentException('Handler output must be an array');
                }
                
                if (isset($result['processed_items']) && is_array($result['processed_items']) && !empty($result['processed_items'])) {
                    $item_data = $result['processed_items'][0];

                    // Universal file parameter extraction for all handlers
                    if (isset($item_data['file_path'])) {
                        // File-based data (Files handler, Reddit images, etc.)
                        $title = $item_data['metadata']['original_title'] ?? $item_data['file_name'] ?? 'File';
                        $body = $item_data['data']['content_string'] ?? '';

                        // If no content string, create basic file info
                        if (empty($body)) {
                            $body = "File: " . ($item_data['file_name'] ?? '') . "\nType: " . ($item_data['mime_type'] ?? '') . "\nSize: " . ($item_data['file_size'] ?? 0) . " bytes";
                        }

                        $result['metadata'] = array_merge($result['metadata'] ?? [], [
                            'file_path' => $item_data['file_path'],
                            'file_name' => $item_data['file_name'] ?? '',
                            'mime_type' => $item_data['mime_type'] ?? '',
                            'file_size' => $item_data['file_size'] ?? 0
                        ], $item_data['metadata'] ?? []);
                    } else {
                        // Text-only data (RSS, WordPress, etc.)
                        $content_string = $item_data['data']['content_string'] ?? '';
                        $title = $item_data['metadata']['original_title'] ?? '';
                        $body = $content_string;

                        $result['metadata'] = array_merge($result['metadata'] ?? [], $item_data['metadata'] ?? []);
                    }
                } else {
                    $title = $result['title'] ?? '';
                    $body = $result['body'] ?? '';
                }

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
     * @param string $handler_name Handler slug from dm_handlers filter
     * @return object|null Instantiated handler object or null if not found
     */
    private function get_handler_object(string $handler_name): ?object {
        $all_handlers = apply_filters('dm_handlers', []);
        $handler_info = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_info || !isset($handler_info['class'])) {
            return null;
        }
        
        $class_name = $handler_info['class'];
        return class_exists($class_name) ? new $class_name() : null;
    }
}


