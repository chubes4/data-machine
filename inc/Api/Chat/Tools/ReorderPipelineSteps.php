<?php
/**
 * Reorder Pipeline Steps Tool
 *
 * Focused tool for reordering steps within a pipeline.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class ReorderPipelineSteps {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'reorder_pipeline_steps', [$this, 'getToolDefinition']);
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
			'description' => 'Reorder steps within a pipeline.',
			'parameters' => [
				'pipeline_id' => [
					'type' => 'integer',
					'required' => true,
					'description' => 'ID of the pipeline'
				],
				'step_order' => [
					'type' => 'array',
					'required' => true,
					'description' => 'Array of step order objects: [{pipeline_step_id: "...", execution_order: 0}, ...]'
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
		$pipeline_id = $parameters['pipeline_id'] ?? null;
		$step_order = $parameters['step_order'] ?? null;

		if (!is_numeric($pipeline_id) || (int) $pipeline_id <= 0) {
			return [
				'success' => false,
				'error' => 'pipeline_id is required and must be a positive integer',
				'tool_name' => 'reorder_pipeline_steps'
			];
		}

		if (empty($step_order) || !is_array($step_order)) {
			return [
				'success' => false,
				'error' => 'step_order is required and must be an array',
				'tool_name' => 'reorder_pipeline_steps'
			];
		}

		$pipeline_id = (int) $pipeline_id;

		$request = new \WP_REST_Request('PUT', '/datamachine/v1/pipelines/' . $pipeline_id . '/steps/reorder');
		$request->set_body_params(['step_order' => $step_order]);

		$response = rest_do_request($request);
		$data = $response->get_data();
		$status = $response->get_status();

		if ($status >= 400) {
			return [
				'success' => false,
				'error' => $data['message'] ?? 'Failed to reorder pipeline steps',
				'tool_name' => 'reorder_pipeline_steps'
			];
		}

		return [
			'success' => true,
			'data' => [
				'pipeline_id' => $pipeline_id,
				'message' => 'Pipeline steps reordered.'
			],
			'tool_name' => 'reorder_pipeline_steps'
		];
	}
}
