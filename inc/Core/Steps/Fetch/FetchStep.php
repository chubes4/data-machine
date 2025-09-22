<?php

namespace DataMachine\Core\Steps\Fetch;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Fetch step with timeframe and keyword filtering capabilities.
 */
class FetchStep {

    /**
     * Execute fetch handler to collect data from external sources.
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

            if (!$fetch_entry) {
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
                
                $has_data_title_key = null;
                $has_data_content_key = null;
                $attachments_count = is_array($result['attachments'] ?? null) ? count($result['attachments']) : 0;
                $metadata_keys = array_keys($result['metadata'] ?? []);

                // Normalize handler output: accept wrapped or flat item lists
                $items = [];
                $has_processed_items_key = isset($result['processed_items']);
                if ($has_processed_items_key && is_array($result['processed_items'])) {
                    $items = $result['processed_items'];
                } elseif (is_array($result) && isset($result[0]) && is_array($result[0]) && (isset($result[0]['data']) || isset($result[0]['metadata']))) {
                    $items = $result; // flat list form
                }

                do_action('dm_log', 'debug', 'FetchStep: Handler output shape', [
                    'flow_step_id' => $flow_step_config['flow_step_id'] ?? null,
                    'handler' => $handler_name,
                    'has_processed_items_key' => $has_processed_items_key,
                    'processed_items_count' => is_array($items) ? count($items) : 0,
                ]);

                // Handle accidental double-wrapping: [ 'processed_items' => [ [ 'processed_items' => [ ... ] ] ] ]
                if (!empty($items) && isset($items[0]['processed_items']) && is_array($items[0]['processed_items'])) {
                    $items = $items[0]['processed_items'];
                }

                if (!empty($items)) {
                    $item_data = $items[0];

                    // Universal data extraction using standardized structure
                    $title = $item_data['data']['title'] ?? $item_data['metadata']['original_title'] ?? '';
                    $body = $item_data['data']['content'] ?? '';

                    $has_data_title_key = array_key_exists('title', $item_data['data'] ?? []);
                    $has_data_content_key = array_key_exists('content', $item_data['data'] ?? []);

                    // Handle file info if present
                    $file_info = $item_data['data']['file_info'] ?? null;
                    if ($file_info) {
                        $file_path_meta = $item_data['file_path'] ?? null;
                        $file_name_meta = $item_data['file_name'] ?? null;
                        $result['metadata'] = array_merge($result['metadata'] ?? [], [
                            'file_path' => $file_path_meta,
                            'file_name' => $file_name_meta,
                            'mime_type' => $file_info['mime_type'] ?? '',
                            'file_size' => $file_info['file_size'] ?? 0
                        ], $item_data['metadata'] ?? []);
                    } else {
                        $result['metadata'] = array_merge($result['metadata'] ?? [], $item_data['metadata'] ?? []);
                    }
                } else {
                    $title = $result['title'] ?? '';
                    $body = $result['body'] ?? '';

                    $has_data_title_key = array_key_exists('title', $result);
                    $has_data_content_key = array_key_exists('body', $result);
                }

                // Observability: confirm presence of keys and non-emptiness without logging content
                do_action('dm_log', 'debug', 'FetchStep: Content presence check', [
                    'flow_step_id' => $flow_step_config['flow_step_id'] ?? null,
                    'handler' => $handler_name,
                    'has_title' => !empty($title),
                    'has_body' => !empty($body),
                    'has_data_title_key' => $has_data_title_key,
                    'has_data_content_key' => $has_data_content_key,
                    'metadata_keys' => array_keys($result['metadata'] ?? []),
                    'attachments_count' => is_array($result['attachments'] ?? null) ? count($result['attachments']) : 0
                ]);

                // Validate that we have meaningful content after extraction
                if (empty($title) && empty($body)) {
                    do_action('dm_log', 'error', 'Fetch handler returned no content after extraction', [
                        'handler' => $handler_name,
                        'pipeline_id' => $context['pipeline_id'],
                        'flow_id' => $context['flow_id']
                    ]);
                    return null;
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
