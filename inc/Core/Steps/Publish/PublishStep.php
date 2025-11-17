<?php

namespace DataMachine\Core\Steps\Publish;

use DataMachine\Engine\AI\ToolResultFinder;

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

            $tool_result_entry = ToolResultFinder::findHandlerResult($data, $handler);
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
