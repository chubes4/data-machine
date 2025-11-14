<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data publishing step for Data Machine pipelines.
 *
 * @package DataMachine
 */
class PublishStep {

    /**
     * Execute data publishing for the current step.
     *
     * @param array $payload Unified step payload
     * @return array Updated data packet array
     */
    public function execute(array $payload): array {
        $flow_step_id = $payload['flow_step_id'] ?? '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        
        try {
            do_action('datamachine_log', 'debug', 'Publish Step: Starting data publishing', ['flow_step_id' => $flow_step_id]);

            if (empty($flow_step_config)) {
                do_action('datamachine_log', 'error', 'Publish Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            $handler = $flow_step_config['handler_slug'] ?? '';

            if (empty($handler)) {
                do_action('datamachine_log', 'error', 'Publish Step: Publish step requires handler configuration', [
                    'flow_step_id' => $flow_step_id,
                    'available_flow_step_config' => array_keys($flow_step_config)
                ]);
                return [];
            }

            $tool_result_entry = $this->find_tool_result_for_handler($data, $handler);
            if ($tool_result_entry) {
                do_action('datamachine_log', 'info', 'PublishStep: AI successfully used handler tool', [
                    'handler' => $handler,
                    'tool_result' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);
                
                return $this->create_publish_entry_from_tool_result($tool_result_entry, $data, $handler, $flow_step_id);
            }

            do_action('datamachine_log', 'error', 'PublishStep: AI did not execute handler tool - step failed', [
                'flow_step_id' => $flow_step_id,
                'expected_handler' => $handler,
                'data_entries' => count($data),
                'available_entry_types' => array_unique(array_column($data, 'type'))
            ]);
            
            return [];

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Publish Step: Exception during publishing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $data;
        }
    }

    /**
     * Searches for tool_result or ai_handler_complete entries matching the handler.
     */
    private function find_tool_result_for_handler(array $data, string $handler): ?array {
        do_action('datamachine_log', 'debug', 'PublishStep: Searching for tool result or ai_handler_complete entry', [
            'handler' => $handler,
            'data_entries_count' => count($data),
            'entry_types' => array_column($data, 'type')
        ]);
        
        foreach ($data as $index => $entry) {
            $entry_type = $entry['type'] ?? '';

            if ($entry_type === 'tool_result') {
                $tool_name = $entry['metadata']['tool_name'] ?? '';
                
                do_action('datamachine_log', 'debug', 'PublishStep: Found tool_result entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'tool_name' => $tool_name,
                    'matches_handler' => ($tool_name === $handler)
                ]);

                if ($tool_name === $handler) {
                    do_action('datamachine_log', 'debug', 'PublishStep: Matched traditional tool_result entry', [
                        'handler' => $handler,
                        'tool_name' => $tool_name,
                        'entry_type' => 'tool_result'
                    ]);
                    return $entry;
                }
            }

            if ($entry_type === 'ai_handler_complete') {
                $handler_tool = $entry['metadata']['handler_tool'] ?? '';

                do_action('datamachine_log', 'debug', 'PublishStep: Found ai_handler_complete entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'handler_tool' => $handler_tool,
                    'matches_handler' => ($handler_tool === $handler),
                    'entry_metadata' => $entry['metadata'] ?? []
                ]);

                if ($handler_tool === $handler) {
                    do_action('datamachine_log', 'debug', 'PublishStep: Using ai_handler_complete entry matching handler', [
                        'handler' => $handler,
                        'entry_type' => 'ai_handler_complete',
                        'conversation_turn_data' => isset($entry['metadata']['conversation_turn']) ? 'present' : 'missing'
                    ]);
                    return $entry;
                }
            }

            if (!in_array($entry_type, ['tool_result', 'ai_handler_complete'])) {
                do_action('datamachine_log', 'debug', 'PublishStep: Skipping non-tool entry', [
                    'handler' => $handler,
                    'entry_index' => $index,
                    'entry_type' => $entry_type
                ]);
            }
        }

        do_action('datamachine_log', 'debug', 'PublishStep: No matching tool result or ai_handler_complete entry found', [
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

    private function create_publish_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        $entry_type = $tool_result_entry['type'] ?? '';

        $executed_via = ($entry_type === 'ai_handler_complete') ? 'ai_conversation_tool' : 'ai_tool_call';
        $title_suffix = ($entry_type === 'ai_handler_complete') ? '(via AI Conversation)' : '(via AI Tool)';

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

        $data = apply_filters('datamachine_data_packet', $data, $publish_entry, $flow_step_id, 'publish');

        return $data;
    }

}
