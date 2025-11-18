<?php
/**
 * Update step with AI tool-calling architecture.
 *
 * @package DataMachine\Core\Steps\Update
 */

namespace DataMachine\Core\Steps\Update;

use DataMachine\Core\Steps\Step;
use DataMachine\Engine\AI\ToolResultFinder;

if (!defined('ABSPATH')) {
    exit;
}

class UpdateStep extends Step {

    /**
     * Initialize update step.
     */
    public function __construct() {
        parent::__construct('update');
    }

    /**
     * Execute update step logic.
     *
     * @return array
     */
    protected function executeStep(): array {
        $handler = $this->getHandlerSlug();

        $tool_result_entry = ToolResultFinder::findHandlerResult($this->dataPackets, $handler, $this->flow_step_id);
        if ($tool_result_entry) {
            $this->log('info', 'AI successfully used handler tool', [
                'handler' => $handler,
                'tool_result' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
            ]);

            return $this->create_update_entry_from_tool_result($tool_result_entry, $this->dataPackets, $handler, $this->flow_step_id);
        }

        return [];
    }

    /**
     * Handle exceptions with job failure action.
     *
     * @param \Exception $e Exception instance
     * @param string $context Context where exception occurred
     * @return array Data packet array (unchanged on exception)
     */
    protected function handleException(\Exception $e, string $context = 'execution'): array {
        do_action('datamachine_fail_job', $this->job_id, 'update_step_exception', [
            'flow_step_id' => $this->flow_step_id,
            'exception_message' => $e->getMessage(),
            'exception_trace' => $e->getTraceAsString()
        ]);

        return $this->dataPackets;
    }

     /**
      * Create update entry from AI tool result.
      *
      * @param array $tool_result_entry Tool result from AI step
      * @param array $dataPackets Current data packet array
      * @param string $handler Handler slug
      * @param string $flow_step_id Flow step ID
      * @return array Updated data packet array
      */
    private function create_update_entry_from_tool_result(array $tool_result_entry, array $dataPackets, string $handler, string $flow_step_id): array {
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
        
        $dataPackets = apply_filters('datamachine_data_packet', $dataPackets, $update_entry, $flow_step_id, 'update');

        return $dataPackets;
    }
}