<?php
/**
 * Make API Request Tool
 *
 * Internal REST API request tool for chat agent.
 * Allows agent to discover handlers, execute workflows, and manage pipelines.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Make API Request Tool
 */
class MakeAPIRequest {

	/**
	 * Register tool with chubes_ai_tools filter
	 *
	 * @param array $tools Existing tools array
	 * @return array Modified tools array
	 */
	public static function register_tool($tools) {
		$tools['make_api_request'] = [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Make internal REST API calls to Data Machine endpoints. Use this to discover handlers, tools, providers, create pipelines, or execute workflows.',
			'parameters' => [
				'endpoint' => [
					'type' => 'string',
					'required' => true,
					'description' => 'REST API endpoint path (e.g., /datamachine/v1/handlers or /datamachine/v1/execute)'
				],
				'method' => [
					'type' => 'string',
					'required' => true,
					'description' => 'HTTP method: GET, POST, PUT, or DELETE'
				],
				'data' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Request body data for POST/PUT requests (optional)'
				]
			],
		];
		return $tools;
	}

	/**
	 * Execute API request
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def   Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$endpoint = $parameters['endpoint'] ?? '';
		$method = strtoupper($parameters['method'] ?? 'GET');
		$data = $parameters['data'] ?? [];

		if (empty($endpoint)) {
			return [
				'success' => false,
				'error' => 'Endpoint parameter is required',
				'tool_name' => 'make_api_request'
			];
		}

		$allowed_methods = ['GET', 'POST', 'PUT', 'DELETE'];
		if (!in_array($method, $allowed_methods, true)) {
			return [
				'success' => false,
				'error' => 'Invalid HTTP method. Allowed: GET, POST, PUT, DELETE',
				'tool_name' => 'make_api_request'
			];
		}

		do_action('datamachine_log', 'debug', 'Chat agent making API request', [
			'endpoint' => $endpoint,
			'method' => $method,
			'request_data' => $data
		]);

		$request = new \WP_REST_Request($method, $endpoint);

		if (!empty($data) && in_array($method, ['POST', 'PUT'], true)) {
			$request->set_body_params($data);
		}

		$response = rest_do_request($request);

		if (is_wp_error($response)) {
			do_action('datamachine_log', 'error', 'Chat agent API request failed', [
				'endpoint' => $endpoint,
				'method' => $method,
				'error' => $response->get_error_message()
			]);

			return [
				'success' => false,
				'error' => $response->get_error_message(),
				'tool_name' => 'make_api_request'
			];
		}

		$response_data = $response->get_data();
		$status_code = $response->get_status();

		do_action('datamachine_log', 'debug', 'Chat agent API request completed', [
			'endpoint' => $endpoint,
			'method' => $method,
			'status' => $status_code,
			'response_data' => $response_data
		]);

		return [
			'success' => true,
			'data' => $response_data,
			'status' => $status_code,
			'tool_name' => 'make_api_request'
		];
	}
}

// Self-register for chat tools
add_filter('datamachine_chat_tools', [MakeAPIRequest::class, 'register_tool']);
