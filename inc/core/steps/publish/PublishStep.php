<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal Publish Step - Executes any publish handler
 * 
 * Publishes data to configured destinations using filter-based handler discovery.
 */
class PublishStep {

    /**
     * Execute publish publishing
     * 
     * @param string $job_id The job ID for context tracking
     * @param string $flow_step_id The flow step ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $flow_step_config Flow step configuration including handler settings
     * @return array Updated data packet array with publish result added
     */
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        try {
            do_action('dm_log', 'debug', 'Publish Step: Starting data publishing', ['flow_step_id' => $flow_step_id]);

            // Use step configuration directly - no job config introspection needed
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'Publish Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            $handler_data = $flow_step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                do_action('dm_log', 'error', 'Publish Step: Publish step requires handler configuration', [
                    'flow_step_id' => $flow_step_id,
                    'available_flow_step_config' => array_keys($flow_step_config),
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_settings = $handler_data['settings'] ?? [];

            // Check if AI already executed the tool for this handler
            $tool_result_entry = $this->find_tool_result_for_handler($data, $handler);
            if ($tool_result_entry) {
                do_action('dm_log', 'debug', 'Publish Step: Tool already executed by AI step', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler,
                    'tool_name' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);
                
                // Create success entry from tool result and return
                return $this->create_publish_entry_from_tool_result($tool_result_entry, $data, $handler, $flow_step_id);
            }

            // Publish steps use latest data entry (first in array)
            $latest_data = $data[0] ?? null;
            if (!$latest_data) {
                do_action('dm_log', 'error', 'Publish Step: No data available from previous step', ['flow_step_id' => $flow_step_id]);
                return $data; // Return unchanged array
            }

            // Execute single publish handler - one step, one handler, per flow
            $handler_result = $this->execute_publish_handler_direct($handler, $latest_data, $flow_step_config, $handler_settings);

            if (!$handler_result || !is_array($handler_result)) {
                do_action('dm_log', 'error', 'Publish Step: Handler execution failed - null or invalid result', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler,
                    'result_type' => gettype($handler_result)
                ]);
                return []; // Return empty array to signal step failure
            }

            // Check if handler reported failure
            if (isset($handler_result['success']) && $handler_result['success'] === false) {
                do_action('dm_log', 'error', 'Publish Step: Handler reported failure', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler,
                    'error' => $handler_result['error'] ?? 'Unknown error'
                ]);
                return []; // Return empty array to signal step failure
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
                    'flow_step_id' => $flow_step_id,
                    'source_type' => $latest_data['metadata']['source_type'] ?? 'unknown'
                ],
                'result' => $handler_result,
                'timestamp' => time()
            ];
            
            // Add publish entry to front of data packet array (newest first)
            array_unshift($data, $publish_entry);
            
            do_action('dm_log', 'debug', 'Publish Step: Publishing completed successfully', [
                'flow_step_id' => $flow_step_id,
                'handler' => $handler,
                'items_processed' => 1, // Publish step always processes exactly 1 item (latest)
                'total_items_in_packet' => count($data) // Total accumulated items in data packet
            ]);

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Publish Step: Exception during publishing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty array on failure (engine interprets as step failure)
            return $data;
        }
    }



    /**
     * Execute publish handler using tool-first architecture
     * 
     * @param string $handler_name Publish handler name
     * @param array $data_entry Latest data entry from data packet array
     * @param array $flow_step_config Flow step configuration
     * @param array $handler_settings Handler settings
     * @return array|null Publish result or null on failure
     */
    private function execute_publish_handler_direct(string $handler_name, array $data_entry, array $flow_step_config, array $handler_settings): ?array {
        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name, 'publish');
        if (!$handler) {
            do_action('dm_log', 'error', 'Publish Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'flow_step_config' => array_keys($flow_step_config)
            ]);
            return null;
        }

        try {
            // Get pipeline and flow IDs from flow_step_config
            $pipeline_id = $flow_step_config['pipeline_id'] ?? null;
            $flow_id = $flow_step_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                do_action('dm_log', 'error', 'Publish Step: Pipeline ID not found in flow step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }

            // Tool-first execution: Check if handler has tools available
            $all_tools = apply_filters('ai_tools', []);
            $handler_tools = array_filter($all_tools, function($tool) use ($handler_name) {
                return isset($tool['handler']) && $tool['handler'] === $handler_name;
            });

            // Execute via handle_tool_call if tools are available
            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                
                // Extract tool parameters from data entry
                $parameters = $this->extract_tool_parameters_from_data($data_entry, $handler_settings);
                
                do_action('dm_log', 'debug', 'Publish Step: Executing handler via tool calling', [
                    'handler' => $handler_name,
                    'tool_name' => $tool_name,
                    'parameters_count' => count($parameters)
                ]);
                
                return $handler->handle_tool_call($parameters, $tool_def);
            }

            // No execution method available
            do_action('dm_log', 'error', 'Publish Step: Handler has no execution method available', [
                'handler' => $handler_name,
                'has_tools' => !empty($handler_tools)
            ]);
            return null;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Publish Step: Handler execution failed', [
                'handler' => $handler_name,
                'pipeline_id' => $pipeline_id ?? 'unknown',
                'flow_id' => $flow_id ?? 'unknown',
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract tool parameters from data entry for tool calling
     * 
     * @param array $data_entry Latest data entry from data packet array
     * @param array $handler_settings Handler configuration settings
     * @return array Tool parameters extracted from data entry
     */
    private function extract_tool_parameters_from_data(array $data_entry, array $handler_settings): array {
        $parameters = [];
        
        // Extract content from data entry
        $content_data = $data_entry['content'] ?? [];
        
        if (isset($content_data['title'])) {
            $parameters['title'] = $content_data['title'];
        }
        
        if (isset($content_data['body'])) {
            $parameters['content'] = $content_data['body'];
        }
        
        // Extract source URL from metadata if available
        $metadata = $data_entry['metadata'] ?? [];
        if (isset($metadata['source_url'])) {
            $parameters['source_url'] = $metadata['source_url'];
        }
        
        // Extract attachments/media if available
        $attachments = $data_entry['attachments'] ?? [];
        if (!empty($attachments)) {
            // Look for image attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['type']) && $attachment['type'] === 'image') {
                    $parameters['image_url'] = $attachment['url'] ?? null;
                    break;
                }
            }
        }
        
        // Merge any additional parameters from handler settings
        // This allows handler-specific configuration to be passed through
        if (!empty($handler_settings)) {
            // Filter out internal settings, only pass through tool-relevant ones
            $tool_relevant_settings = array_filter($handler_settings, function($key) {
                return !in_array($key, ['handler_slug', 'auth_config', 'internal_config']);
            }, ARRAY_FILTER_USE_KEY);
            
            $parameters = array_merge($parameters, $tool_relevant_settings);
        }
        
        return $parameters;
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
        $all_handlers = apply_filters('dm_handlers', []);
        $handler_info = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_info || !isset($handler_info['class'])) {
            return null;
        }
        
        // Verify handler type matches
        if (($handler_info['type'] ?? '') !== $handler_type) {
            return null;
        }
        
        $class_name = $handler_info['class'];
        return class_exists($class_name) ? new $class_name() : null;
    }

    // PublishStep receives cumulative data packet array from engine via $data parameter

    /**
     * Find tool_result entry for the specified handler in the data packet.
     * 
     * @param array $data Data packet array
     * @param string $handler Handler slug to look for
     * @return array|null Tool result entry or null if not found
     */
    private function find_tool_result_for_handler(array $data, string $handler): ?array {
        foreach ($data as $entry) {
            if (($entry['type'] ?? '') === 'tool_result') {
                $tool_name = $entry['metadata']['tool_name'] ?? '';
                // Match tool name to handler (e.g., 'wordpress_publish' matches 'wordpress_publish' handler)
                if ($tool_name === $handler) {
                    return $entry;
                }
            }
        }
        return null;
    }

    /**
     * Create publish entry from successful tool result.
     * 
     * @param array $tool_result_entry The tool result entry from AI step
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet with publish entry
     */
    private function create_publish_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        
        // Create publish data entry for the data packet array
        $publish_entry = [
            'type' => 'publish',
            'handler' => $handler,
            'content' => [
                'title' => 'Publish Complete (via AI Tool)',
                'body' => json_encode($tool_result_data, JSON_PRETTY_PRINT)
            ],
            'metadata' => [
                'handler_used' => $handler,
                'publish_success' => true,
                'executed_via' => 'ai_tool_call',
                'flow_step_id' => $flow_step_id,
                'source_type' => $tool_result_entry['metadata']['source_type'] ?? 'unknown',
                'tool_execution_data' => $tool_result_data
            ],
            'result' => $tool_result_data,
            'timestamp' => time()
        ];
        
        // Add publish entry to front of data packet array (newest first)
        array_unshift($data, $publish_entry);
        
        do_action('dm_log', 'debug', 'Publish Step: Completed successfully via AI tool call', [
            'flow_step_id' => $flow_step_id,
            'handler' => $handler,
            'tool_result_keys' => array_keys($tool_result_data),
            'total_items_in_packet' => count($data)
        ]);

        return $data;
    }

}


