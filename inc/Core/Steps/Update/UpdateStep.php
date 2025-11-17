<?php
/**
 * Update step with AI tool-calling architecture.
 *
 * @package DataMachine\Core\Steps\Update
 */

namespace DataMachine\Core\Steps\Update;

use DataMachine\Engine\AI\ToolResultFinder;

if (!defined('ABSPATH')) {
    exit;
}
class UpdateStep {

    /**
     * Execute update step via AI tool-calling.
     *
     * @param array $payload Unified step payload
     * @return array Updated data packet array
     */
    public function execute(array $payload): array {
        $job_id = $payload['job_id'] ?? 0;
        $flow_step_id = $payload['flow_step_id'] ?? '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $flow_step_config = $payload['flow_step_config'] ?? [];
        
        try {
            if (empty($flow_step_config)) {
                do_action('datamachine_log', 'error', 'Update Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            $handler_slug = $flow_step_config['handler_slug'] ?? '';

            if (empty($handler_slug)) {
                do_action('datamachine_log', 'error', 'Update Step: No handler configured', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            $handler_config = $flow_step_config['handler_config'] ?? [];

            if (empty($data)) {
                do_action('datamachine_log', 'error', 'Update Step: No data to process', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            do_action('datamachine_log', 'debug', 'Update Step: Starting update processing', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug,
                'data_entries' => count($data)
            ]);

            $tool_result_entry = ToolResultFinder::findHandlerResult($data, $handler_slug);
            if ($tool_result_entry) {
                do_action('datamachine_log', 'info', 'UpdateStep: AI successfully used handler tool', [
                    'handler' => $handler_slug,
                    'tool_result' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);

                return $this->create_update_entry_from_tool_result($tool_result_entry, $data, $handler_slug, $flow_step_id);
            }

            // AI did not execute handler tool - fail cleanly
            do_action('datamachine_log', 'error', 'UpdateStep: AI did not execute handler tool - step failed', [
                'flow_step_id' => $flow_step_id,
                'expected_handler' => $handler_slug,
                'data_entries' => count($data),
                'available_entry_types' => array_unique(array_column($data, 'type'))
            ]);

            return [];

        } catch (\Exception $e) {
            do_action('datamachine_fail_job', $job_id, 'update_step_exception', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            
            return $data;
        }
    }

    /**
     * Create update entry from AI tool result.
     *
     * @param array $tool_result_entry Tool result from AI step
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet
     */
    private function create_update_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        
        $update_entry = [
            'content' => [
                'update_result' => $tool_result_data,
                'updated_at' => current_time('mysql')
            ],
            'metadata' => [
                'step_type' => 'update',
                'handler' => $handler,
                'flow_step_id' => $flow_step_id,
                'success' => $tool_result_data['success'] ?? false,
                'executed_via' => 'ai_tool_call',
                'tool_execution_data' => $tool_result_data
            ],
            'attachments' => []
        ];
        
        $data = apply_filters('datamachine_data_packet', $data, $update_entry, $flow_step_id, 'update');

        return $data;
    }
}