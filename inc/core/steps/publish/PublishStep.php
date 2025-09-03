<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Publishes data using configured handlers
 */
class PublishStep {

    /**
     * Execute publish processing
     * 
     * @param string $job_id Job identifier
     * @param string $flow_step_id Flow step identifier
     * @param array $data Data packet array
     * @param array $flow_step_config Step configuration
     * @param mixed ...$additional_parameters Engine-provided parameters via dm_step_additional_parameters filter
     * @return array Updated data packet array
     */
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = [], ...$additional_parameters): array {
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

            // PublishStep trusts AI workflow completely - AI must call handler tool during conversation
            $tool_result_entry = $this->find_tool_result_for_handler($data, $handler);
            if ($tool_result_entry) {
                do_action('dm_log', 'info', 'PublishStep: AI successfully used handler tool', [
                    'handler' => $handler,
                    'tool_result' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);
                
                // Create success entry from AI tool result and return
                return $this->create_publish_entry_from_tool_result($tool_result_entry, $data, $handler, $flow_step_id);
            }

            // AI did not execute handler tool - this indicates a workflow problem
            do_action('dm_log', 'error', 'PublishStep: AI did not execute handler tool - step failed', [
                'flow_step_id' => $flow_step_id,
                'expected_handler' => $handler,
                'data_entries' => count($data),
                'available_entry_types' => array_unique(array_column($data, 'type'))
            ]);
            
            return []; // Return empty array to signal step failure

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
     * Execute publish handler
     * 
     * @param string $handler_name Handler name
     * @param array $data_entry Data entry
     * @param array $flow_step_config Step configuration
     * @param array $handler_settings Handler settings
     * @return array|null Handler result or null on failure
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
            $all_tools = apply_filters('ai_tools', [], $handler_name, $handler_settings);
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
     * Extract parameters from data entry
     * 
     * @param array $data_entry Data entry
     * @param array $handler_settings Handler settings
     * @return array Extracted parameters
     */
    private function extract_tool_parameters_from_data(array $data_entry, array $handler_settings): array {
        $metadata = $data_entry['metadata'] ?? [];
        $entry_type = $data_entry['type'] ?? '';
        
        // NEW: For ai_handler_complete entries, use clean separated parameters from metadata
        if ($entry_type === 'ai_handler_complete' && isset($metadata['tool_parameters'])) {
            // Use clean separated parameters stored by AIStep
            $clean_parameters = $metadata['tool_parameters'];
            
            // Use handler_config from metadata if available, otherwise fall back to handler_settings
            $handler_config = $metadata['handler_config'] ?? $handler_settings;
            
            // Merge handler config into parameters for tool execution
            if (!empty($handler_config)) {
                $parameters = array_merge($clean_parameters, $handler_config);
            } else {
                $parameters = $clean_parameters;
            }
            
            return $parameters;
        }
        
        return [];
    }

    /**
     * Get handler object from registration
     * 
     * @param string $handler_name Handler name
     * @param string $handler_type Handler type
     * @return object|null Handler object or null
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
     * Find tool result for handler
     * 
     * @param array $data Data packet array
     * @param string $handler Handler slug
     * @return array|null Tool result entry or null
     */
    private function find_tool_result_for_handler(array $data, string $handler): ?array {
        do_action('dm_log', 'debug', 'PublishStep: Searching for tool result or ai_handler_complete entry', [
            'handler' => $handler,
            'data_entries_count' => count($data),
            'entry_types' => array_column($data, 'type')
        ]);
        
        foreach ($data as $index => $entry) {
            $entry_type = $entry['type'] ?? '';
            
            // Check for traditional tool_result entries
            if ($entry_type === 'tool_result') {
                $tool_name = $entry['metadata']['tool_name'] ?? '';
                
                do_action('dm_log', 'debug', 'PublishStep: Found tool_result entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'tool_name' => $tool_name,
                    'matches_handler' => ($tool_name === $handler)
                ]);
                
                // Match tool name to handler (e.g., 'wordpress_publish' matches 'wordpress_publish' handler)
                if ($tool_name === $handler) {
                    do_action('dm_log', 'debug', 'PublishStep: Matched traditional tool_result entry', [
                        'handler' => $handler,
                        'tool_name' => $tool_name,
                        'entry_type' => 'tool_result'
                    ]);
                    return $entry;
                }
            }
            
            // Check for new ai_handler_complete entries (from unified conversation system)
            if ($entry_type === 'ai_handler_complete') {
                $handler_tool = $entry['metadata']['handler_tool'] ?? '';
                
                do_action('dm_log', 'debug', 'PublishStep: Found ai_handler_complete entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'handler_tool' => $handler_tool,
                    'matches_handler' => ($handler_tool === $handler),
                    'entry_metadata' => $entry['metadata'] ?? []
                ]);
                
                // Match handler tool to handler (e.g., 'wordpress_publish' matches 'wordpress_publish' handler)
                if ($handler_tool === $handler) {
                    do_action('dm_log', 'debug', 'PublishStep: Matched ai_handler_complete entry from unified conversation system', [
                        'handler' => $handler,
                        'handler_tool' => $handler_tool,
                        'entry_type' => 'ai_handler_complete',
                        'conversation_turn_data' => isset($entry['metadata']['conversation_turn']) ? 'present' : 'missing'
                    ]);
                    return $entry;
                }
            }
            
            // Log other entry types for visibility
            if (!in_array($entry_type, ['tool_result', 'ai_handler_complete'])) {
                do_action('dm_log', 'debug', 'PublishStep: Skipping non-tool entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'entry_type' => $entry_type
                ]);
            }
        }
        
        do_action('dm_log', 'debug', 'PublishStep: No matching tool result or ai_handler_complete entry found', [
            'handler' => $handler,
            'searched_entries' => count($data),
            'available_tool_results' => array_filter($data, function($entry) {
                return ($entry['type'] ?? '') === 'tool_result';
            }),
            'available_ai_handler_complete' => array_filter($data, function($entry) {
                return ($entry['type'] ?? '') === 'ai_handler_complete';
            })
        ]);
        
        return null;
    }

    /**
     * Create publish entry from tool result
     * 
     * @param array $tool_result_entry Tool result entry
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet
     */
    private function create_publish_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        $entry_type = $tool_result_entry['type'] ?? '';
        
        // Determine execution method based on entry type
        $executed_via = ($entry_type === 'ai_handler_complete') ? 'ai_conversation_tool' : 'ai_tool_call';
        $title_suffix = ($entry_type === 'ai_handler_complete') ? '(via AI Conversation)' : '(via AI Tool)';
        
        // Create publish data entry for the data packet array
        $publish_entry = [
            'type' => 'publish',
            'handler' => $handler,
            'content' => [
                'title' => 'Publish Complete ' . $title_suffix,
                'body' => json_encode($tool_result_data, JSON_PRETTY_PRINT)
            ],
            'metadata' => [
                'handler_used' => $handler,
                'publish_success' => true,
                'executed_via' => $executed_via,
                'flow_step_id' => $flow_step_id,
                'source_type' => $tool_result_entry['metadata']['source_type'] ?? 'unknown',
                'tool_execution_data' => $tool_result_data,
                'original_entry_type' => $entry_type
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


