<?php
/**
 * Run Flow Tool
 *
 * Tool for executing existing flows immediately or scheduling delayed execution.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class RunFlow {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'run_flow', $this->getToolDefinition());
    }

    private function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Execute an existing flow immediately or schedule it for delayed execution. Use this to run flows that have already been created and configured.',
            'parameters' => [
                'flow_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Flow ID to execute'
                ],
                'timestamp' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Unix timestamp for delayed execution (if not provided, executes immediately)'
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
                'tool_name' => 'run_flow'
            ];
        }

        $flow_id = (int) $flow_id;
        $timestamp = $parameters['timestamp'] ?? null;
        $execution_type = 'immediate';

        if ($timestamp !== null) {
            if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
                return [
                    'success' => false,
                    'error' => 'timestamp must be a positive integer',
                    'tool_name' => 'run_flow'
                ];
            }

            $timestamp = (int) $timestamp;

            if ($timestamp <= time()) {
                return [
                    'success' => false,
                    'error' => 'timestamp must be in the future',
                    'tool_name' => 'run_flow'
                ];
            }

            $execution_type = 'delayed';
        }

        $body_params = ['flow_id' => $flow_id];
        if ($timestamp !== null) {
            $body_params['timestamp'] = $timestamp;
        }

        $request = new \WP_REST_Request('POST', '/datamachine/v1/execute');
        $request->set_body_params($body_params);

        $response = rest_do_request($request);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'tool_name' => 'run_flow'
            ];
        }

        $data = $response->get_data();
        $status = $response->get_status();

        if ($status >= 400) {
            $error_message = $data['message'] ?? 'Failed to execute flow';
            return [
                'success' => false,
                'error' => $error_message,
                'tool_name' => 'run_flow'
            ];
        }

        $response_data = [
            'flow_id' => $flow_id,
            'execution_type' => $execution_type,
            'message' => $execution_type === 'immediate'
                ? 'Flow executed successfully.'
                : 'Flow scheduled for delayed execution.'
        ];

        if (isset($data['data']['job_id'])) {
            $response_data['job_id'] = $data['data']['job_id'];
        }
        if (isset($data['data']['flow_name'])) {
            $response_data['flow_name'] = $data['data']['flow_name'];
        }

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'run_flow'
        ];
    }
}

new RunFlow();
