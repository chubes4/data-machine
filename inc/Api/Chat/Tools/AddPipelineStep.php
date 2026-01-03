<?php
/**
 * Add Pipeline Step Tool
 *
 * Focused tool for adding steps to existing pipelines.
 * Automatically syncs the new step to all flows on the pipeline.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\PipelineStepManager;
use DataMachine\Services\StepTypeService;

class AddPipelineStep {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'add_pipeline_step', [$this, 'getToolDefinition']);
	}

	private static function getValidStepTypes(): array {
		$step_type_service = new StepTypeService();
		return array_keys($step_type_service->getAll());
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$valid_types = self::getValidStepTypes();
		$types_list = !empty($valid_types) ? implode(', ', $valid_types) : 'fetch, ai, publish, update';
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Add a step to an existing pipeline. The step is automatically synced to all flows on that pipeline. Use this instead of make_api_request for adding steps.',
			'parameters' => [
				'pipeline_id' => [
					'type' => 'integer',
					'required' => true,
					'description' => 'Pipeline ID to add the step to'
				],
				'step_type' => [
					'type' => 'string',
					'required' => true,
					'description' => "Type of step: {$types_list}"
				]
			]
		];
	}

	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$pipeline_id = $parameters['pipeline_id'] ?? null;
		$step_type = $parameters['step_type'] ?? null;

		if (!is_numeric($pipeline_id) || (int) $pipeline_id <= 0) {
			return [
				'success' => false,
				'error' => 'pipeline_id is required and must be a positive integer',
				'tool_name' => 'add_pipeline_step'
			];
		}

		if (empty($step_type) || !is_string($step_type)) {
			return [
				'success' => false,
				'error' => 'step_type is required and must be a string',
				'tool_name' => 'add_pipeline_step'
			];
		}

		$valid_types = self::getValidStepTypes();
		if (!in_array($step_type, $valid_types, true)) {
			return [
				'success' => false,
				'error' => "Invalid step_type '{$step_type}'. Must be one of: " . implode(', ', $valid_types),
				'tool_name' => 'add_pipeline_step'
			];
		}

		$pipeline_id = (int) $pipeline_id;
		$step_manager = new PipelineStepManager();

		$pipeline_step_id = $step_manager->add($pipeline_id, $step_type);

		if (!$pipeline_step_id) {
			return [
				'success' => false,
				'error' => 'Failed to add step. Verify the pipeline_id exists and you have sufficient permissions.',
				'tool_name' => 'add_pipeline_step'
			];
		}

		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$flows = $db_flows->get_flows_for_pipeline($pipeline_id);

		$flow_step_ids = [];
		foreach ($flows as $flow) {
			$flow_config = $flow['flow_config'] ?? [];
			foreach ($flow_config as $flow_step_id => $step_data) {
				if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
					$flow_step_ids[] = [
						'flow_id' => $flow['flow_id'],
						'flow_step_id' => $flow_step_id
					];
				}
			}
		}

		return [
			'success' => true,
			'data' => [
				'pipeline_id' => $pipeline_id,
				'pipeline_step_id' => $pipeline_step_id,
				'step_type' => $step_type,
				'flows_updated' => count($flows),
				'flow_step_ids' => $flow_step_ids,
				'message' => "Step '{$step_type}' added to pipeline. Use configure_flow_step with the flow_step_ids to set handler configuration."
			],
			'tool_name' => 'add_pipeline_step'
		];
	}
}
