<?php
/**
 * Delete Flow Tool
 *
 * Focused tool for deleting flows.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class DeleteFlow {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'delete_flow', [$this, 'getToolDefinition']);
	}

	/**
	 * Get tool definition.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Delete a flow.',
			'parameters' => [
				'flow_id' => [
					'type' => 'integer',
					'required' => true,
					'description' => 'ID of the flow to delete'
				]
			]
		];
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$flow_id = $parameters['flow_id'] ?? null;

		if (!is_numeric($flow_id) || (int) $flow_id <= 0) {
			return [
				'success' => false,
				'error' => 'flow_id is required and must be a positive integer',
				'tool_name' => 'delete_flow'
			];
		}

		$flow_id = (int) $flow_id;

		$request = new \WP_REST_Request('DELETE', '/datamachine/v1/flows/' . $flow_id);
		$response = rest_do_request($request);
		$data = $response->get_data();
		$status = $response->get_status();

		if ($status >= 400) {
			return [
				'success' => false,
				'error' => $data['message'] ?? 'Failed to delete flow',
				'tool_name' => 'delete_flow'
			];
		}

		return [
			'success' => true,
			'data' => [
				'flow_id' => $flow_id,
				'message' => 'Flow deleted.'
			],
			'tool_name' => 'delete_flow'
		];
	}
}
