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
        $this->registerTool('chat', 'run_flow', [$this, 'getToolDefinition']);
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
            'description' => 'Execute an existing flow immediately or schedule it for later. For IMMEDIATE execution: provide only flow_id (do NOT include timestamp). For SCHEDULED execution: provide flow_id AND a future Unix timestamp. Flows run asynchronously in the background. Use api_query with GET /datamachine/v1/jobs/{job_id} to check execution status.',
            'parameters' => [
                'flow_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Flow ID to execute'
                ],
                'timestamp' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'ONLY for scheduled execution: a future Unix timestamp. OMIT this parameter entirely for immediate execution.'
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

        if (!empty($timestamp) && is_numeric($timestamp) && (int) $timestamp > time()) {
            $timestamp = (int) $timestamp;
            $execution_type = 'delayed';
        } else {
            $timestamp = null;
        }

        $body_params = ['flow_id' => $flow_id];
        if ($timestamp !== null) {
            $body_params['timestamp'] = $timestamp;
        }

        $request = new \WP_REST_Request('POST', '/datamachine/v1/execute');
        $request->set_body_params($body_params);

        $response = rest_do_request($request);
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
                ? 'Flow queued for immediate background execution. It will start within seconds. Use job_id to check status.'
                : 'Flow scheduled for delayed background execution at the specified time.'
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
