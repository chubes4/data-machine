<?php
/**
 * Copy Flow Tool
 *
 * Copy an existing flow to the same or different pipeline with optional
 * configuration overrides. Supports cross-pipeline copying with compatibility
 * validation.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.6.25
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowManager;

class CopyFlow {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'copy_flow', [$this, 'getToolDefinition']);
    }

    /**
     * Get tool definition.
     * Called lazily when tool is first accessed to ensure translations are loaded.
     *
     * @return array Tool definition array
     */
    public function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Copy a flow to the same or different pipeline. Cross-pipeline requires compatible step structures. Copies handlers, messages, and schedule.',
            'parameters' => [
                'source_flow_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Flow ID to copy'
                ],
                'target_pipeline_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Destination pipeline ID'
                ],
                'flow_name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'New flow name'
                ],
                'scheduling_config' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Override schedule (defaults to source). Format: {interval: value}. Valid intervals:' . "\n" . SchedulingDocumentation::getIntervalsJson()
                ],
                'step_config_overrides' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Override steps by step_type or execution_order: {handler_slug?, handler_config?, user_message?}'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $source_flow_id = $parameters['source_flow_id'] ?? null;
        $target_pipeline_id = $parameters['target_pipeline_id'] ?? null;
        $flow_name = $parameters['flow_name'] ?? null;

        // Validate required parameters
        if (!is_numeric($source_flow_id) || (int) $source_flow_id <= 0) {
            return [
                'success' => false,
                'error' => 'source_flow_id is required and must be a positive integer',
                'tool_name' => 'copy_flow'
            ];
        }

        if (!is_numeric($target_pipeline_id) || (int) $target_pipeline_id <= 0) {
            return [
                'success' => false,
                'error' => 'target_pipeline_id is required and must be a positive integer',
                'tool_name' => 'copy_flow'
            ];
        }

        if (empty($flow_name)) {
            return [
                'success' => false,
                'error' => 'flow_name is required',
                'tool_name' => 'copy_flow'
            ];
        }

        $source_flow_id = (int) $source_flow_id;
        $target_pipeline_id = (int) $target_pipeline_id;
        $flow_name = sanitize_text_field($flow_name);

        // Build options
        $options = [];

        if (!empty($parameters['scheduling_config'])) {
            $options['scheduling_config'] = $parameters['scheduling_config'];
        }

        if (!empty($parameters['step_config_overrides'])) {
            $options['step_config_overrides'] = $parameters['step_config_overrides'];
        }

        // Execute copy
        $flow_manager = new FlowManager();
        $result = $flow_manager->copyToPipeline(
            $source_flow_id,
            $target_pipeline_id,
            $flow_name,
            $options
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to copy flow',
                'tool_name' => 'copy_flow'
            ];
        }

        $data = $result['data'];

        // Build response message
        $is_cross_pipeline = $data['source_pipeline_id'] !== $data['target_pipeline_id'];
        $has_overrides = !empty($parameters['step_config_overrides']);

        if ($is_cross_pipeline && $has_overrides) {
            $message = 'Flow copied to different pipeline and configured with overrides.';
        } elseif ($is_cross_pipeline) {
            $message = 'Flow copied to different pipeline.';
        } elseif ($has_overrides) {
            $message = 'Flow duplicated and configured with overrides.';
        } else {
            $message = 'Flow duplicated successfully.';
        }

        return [
            'success' => true,
            'data' => [
                'flow_id' => $data['new_flow_id'],
                'flow_name' => $data['flow_name'],
                'source_flow_id' => $data['source_flow_id'],
                'source_pipeline_id' => $data['source_pipeline_id'],
                'target_pipeline_id' => $data['target_pipeline_id'],
                'flow_step_ids' => $data['flow_step_ids'],
                'scheduling' => $data['scheduling'],
                'message' => $message
            ],
            'tool_name' => 'copy_flow'
        ];
    }
}
