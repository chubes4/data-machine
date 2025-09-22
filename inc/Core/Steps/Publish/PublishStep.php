<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Publish Step - Content Distribution
 *
 * Publishes processed content to external platforms via handler tools.
 * Relies on AI agents to execute handler tools during conversation workflow.
 */
class PublishStep {

    /**
     * Execute publish processing
     * 
     * Publishes processed data to external platforms via handler tools.
     * Expects AI agents to execute handler tools during conversation workflow.
     * 
     * @param array $parameters Core parameter structure from step execution:
     *   - job_id: Job execution identifier
     *   - flow_step_id: Flow step identifier
     *   - flow_step_config: Step configuration data
     *   - data: Data packet array containing processed content
     *   - Additional parameters: source_url, image_url, file_path, mime_type (as available)
     * @return array Updated data packet array with publish results
     */
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        
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

                // Accept ai_handler_complete when it matches this handler, even without storing full tool_result
                if ($handler_tool === $handler) {
                    do_action('dm_log', 'debug', 'PublishStep: Using ai_handler_complete entry matching handler', [
                        'handler' => $handler,
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
        $data = apply_filters('dm_data_packet', $data, $publish_entry, $flow_step_id, 'publish');

        return $data;
    }

}
