<?php
/**
 * Update Flow Tool
 *
 * Tool for updating flow-level properties including title and scheduling configuration.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class UpdateFlow {
    use ToolRegistrationTrait;

    private const VALID_INTERVALS = ['manual', 'hourly', 'daily', 'weekly', 'monthly', 'one_time'];

    public function __construct() {
        $this->registerTool('chat', 'update_flow', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Update flow-level properties including title and scheduling configuration.',
            'parameters' => [
                'flow_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Flow ID to update'
                ],
                'flow_name' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'New flow title'
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
        $flow_id = $parameters['flow_id'] ?? null;

        if (!is_numeric($flow_id) || (int) $flow_id <= 0) {
            return [
                'success' => false,
                'error' => 'flow_id is required and must be a positive integer',
                'tool_name' => 'update_flow'
            ];
        }

        $flow_id = (int) $flow_id;
        $flow_name = $parameters['flow_name'] ?? null;
        $scheduling_config = $parameters['scheduling_config'] ?? null;

        if (empty($flow_name) && empty($scheduling_config)) {
            return [
                'success' => false,
                'error' => 'At least one of flow_name or scheduling_config is required',
                'tool_name' => 'update_flow'
            ];
        }

        if (!empty($scheduling_config)) {
            $validation = $this->validateSchedulingConfig($scheduling_config);
            if ($validation !== true) {
                return [
                    'success' => false,
                    'error' => $validation,
                    'tool_name' => 'update_flow'
                ];
            }
        }

        $body_params = [];
        if (!empty($flow_name)) {
            $body_params['flow_name'] = $flow_name;
        }
        if (!empty($scheduling_config)) {
            $body_params['scheduling_config'] = $scheduling_config;
        }

        $request = new \WP_REST_Request('PATCH', '/datamachine/v1/flows/' . $flow_id);
        $request->set_body_params($body_params);

        $response = rest_do_request($request);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'tool_name' => 'update_flow'
            ];
        }

        $data = $response->get_data();
        $status = $response->get_status();

        if ($status >= 400) {
            $error_message = $data['message'] ?? 'Failed to update flow';
            return [
                'success' => false,
                'error' => $error_message,
                'tool_name' => 'update_flow'
            ];
        }

        $response_data = [
            'flow_id' => $flow_id,
            'message' => 'Flow updated successfully.'
        ];

        if (!empty($flow_name)) {
            $response_data['flow_name'] = $flow_name;
        }
        if (!empty($scheduling_config)) {
            $response_data['scheduling'] = $scheduling_config['interval'];
        }

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'update_flow'
        ];
    }

    private function validateSchedulingConfig(array $config): bool|string {
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

new UpdateFlow();
