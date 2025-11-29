<?php
/**
 * Create Flow Tool
 *
 * Focused tool for creating flow instances from existing pipelines.
 * Automatically syncs pipeline steps to the new flow.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\FlowManager;

class CreateFlow {
    use ToolRegistrationTrait;

    private const VALID_INTERVALS = ['manual', 'hourly', 'daily', 'weekly', 'monthly', 'one_time'];

    public function __construct() {
        $this->registerTool('chat', 'create_flow', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Create a new flow instance for an existing pipeline. The flow automatically syncs steps from the pipeline. After creation, use configure_flow_step to set handler configurations for each step.',
            'parameters' => [
                'pipeline_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'ID of the pipeline to create a flow for'
                ],
                'flow_name' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Name for the flow (defaults to "Flow")'
                ],
                'scheduling_config' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Scheduling configuration: {interval: "manual|hourly|daily|weekly|monthly"} or {interval: "one_time", timestamp: unix_timestamp}'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $pipeline_id = $parameters['pipeline_id'] ?? null;

        if (!is_numeric($pipeline_id) || (int) $pipeline_id <= 0) {
            return [
                'success' => false,
                'error' => 'pipeline_id is required and must be a positive integer',
                'tool_name' => 'create_flow'
            ];
        }

        $pipeline_id = (int) $pipeline_id;
        $flow_name = $parameters['flow_name'] ?? 'Flow';
        $scheduling_config = $parameters['scheduling_config'] ?? ['interval' => 'manual'];

        $validation = $this->validateSchedulingConfig($scheduling_config);
        if ($validation !== true) {
            return [
                'success' => false,
                'error' => $validation,
                'tool_name' => 'create_flow'
            ];
        }

        $flow_manager = new FlowManager();
        $result = $flow_manager->create($pipeline_id, $flow_name, [
            'scheduling_config' => $scheduling_config
        ]);

        if (!$result) {
            return [
                'success' => false,
                'error' => 'Failed to create flow. Verify the pipeline_id exists and you have sufficient permissions.',
                'tool_name' => 'create_flow'
            ];
        }

        $flow_step_ids = [];
        $flow_config = $result['flow_data']['flow_config'] ?? [];
        if (is_array($flow_config)) {
            $flow_step_ids = array_keys($flow_config);
        }

        return [
            'success' => true,
            'data' => [
                'flow_id' => $result['flow_id'],
                'flow_name' => $result['flow_name'],
                'pipeline_id' => $result['pipeline_id'],
                'synced_steps' => $result['synced_steps'],
                'flow_step_ids' => $flow_step_ids,
                'scheduling' => $scheduling_config['interval'],
                'message' => 'Flow created successfully. Use configure_flow_step with the flow_step_ids to set handler configurations.'
            ],
            'tool_name' => 'create_flow'
        ];
    }

    private function validateSchedulingConfig(array $config): bool|string {
        if (empty($config)) {
            return true;
        }

        $interval = $config['interval'] ?? null;

        if ($interval === null) {
            return 'scheduling_config requires an interval property';
        }

        if (!in_array($interval, self::VALID_INTERVALS, true)) {
            return 'Invalid interval. Must be one of: ' . implode(', ', self::VALID_INTERVALS);
        }

        if ($interval === 'one_time') {
            $timestamp = $config['timestamp'] ?? null;
            if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
                return 'one_time interval requires a valid unix timestamp';
            }
        }

        return true;
    }
}

new CreateFlow();
