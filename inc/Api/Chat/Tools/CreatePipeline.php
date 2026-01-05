<?php
/**
 * Create Pipeline Tool
 *
 * Focused tool for creating pipelines with optional predefined steps.
 * Automatically creates an associated flow for immediate configuration.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\PipelineManager;
use DataMachine\Services\StepTypeService;

class CreatePipeline {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'create_pipeline', [$this, 'getToolDefinition']);
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
		$types_list = !empty($valid_types) ? implode('|', $valid_types) : 'fetch|ai|publish|update';
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Create a pipeline with optional steps. Automatically creates a flow - do NOT call create_flow afterward.',
			'parameters' => [
				'pipeline_name' => [
					'type' => 'string',
					'required' => true,
					'description' => 'Pipeline name'
				],
				'steps' => [
					'type' => 'array',
					'required' => false,
					'description' => "Steps in execution order: {step_type: \"{$types_list}\", handler_slug, handler_config}. AI steps: add provider, model, system_prompt."
				],
				'flow_name' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Flow name (defaults to pipeline_name)'
				],
				'scheduling_config' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Schedule: {interval: value}. Valid intervals:' . "\n" . SchedulingDocumentation::getIntervalsJson()
				]
			]
		];
	}

	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$pipeline_name = $parameters['pipeline_name'] ?? null;

		if (empty($pipeline_name) || !is_string($pipeline_name)) {
			return [
				'success' => false,
				'error' => 'pipeline_name is required and must be a non-empty string',
				'tool_name' => 'create_pipeline'
			];
		}

		$steps = $parameters['steps'] ?? [];
		$flow_name = $parameters['flow_name'] ?? $pipeline_name;
		$scheduling_config = $parameters['scheduling_config'] ?? ['interval' => 'manual'];

		$scheduling_validation = $this->validateSchedulingConfig($scheduling_config);
		if ($scheduling_validation !== true) {
			return [
				'success' => false,
				'error' => $scheduling_validation,
				'tool_name' => 'create_pipeline'
			];
		}

		if (!empty($steps)) {
			$steps_validation = $this->validateSteps($steps);
			if ($steps_validation !== true) {
				return [
					'success' => false,
					'error' => $steps_validation,
					'tool_name' => 'create_pipeline'
				];
			}

			$steps = $this->normalizeSteps($steps);
		}

		$pipeline_manager = new PipelineManager();

		$options = [
			'flow_config' => [
				'flow_name' => $flow_name,
				'scheduling_config' => $scheduling_config
			]
		];

		if (!empty($steps)) {
			$result = $pipeline_manager->createWithSteps($pipeline_name, $steps, $options);
		} else {
			$result = $pipeline_manager->create($pipeline_name, $options);
		}

		if (!$result) {
			return [
				'success' => false,
				'error' => 'Failed to create pipeline. Check logs for details.',
				'tool_name' => 'create_pipeline'
			];
		}

		$flow_step_ids = [];
		if (!empty($result['flows'])) {
			$flow = $result['flows'][0] ?? null;
			if ($flow && !empty($flow['flow_config'])) {
				$flow_step_ids = array_keys($flow['flow_config']);
			}
		}

		$flow_id = $result['flows'][0]['flow_id'] ?? null;

		return [
			'success' => true,
			'data' => [
				'pipeline_id' => $result['pipeline_id'],
				'pipeline_name' => $result['pipeline_name'],
				'flow_id' => $flow_id,
				'flow_name' => $flow_name,
				'steps_created' => count($steps),
				'flow_step_ids' => $flow_step_ids,
				'scheduling' => $scheduling_config['interval'],
				'message' => empty($steps)
					? "Pipeline and flow (ID: {$flow_id}) created. Do NOT call create_flow - a flow already exists. Use add_pipeline_step to add steps, then configure_flow_step to configure handlers."
					: "Pipeline and flow (ID: {$flow_id}) created with " . count($steps) . " steps. Do NOT call create_flow - a flow already exists. Use configure_flow_step with the flow_step_ids to set handler configurations."
			],
			'tool_name' => 'create_pipeline'
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

		$intervals = array_keys(apply_filters('datamachine_scheduler_intervals', []));
		$valid_intervals = array_merge(['manual', 'one_time'], $intervals);
		if (!in_array($interval, $valid_intervals, true)) {
			return 'Invalid interval. Must be one of: ' . implode(', ', $valid_intervals);
		}

		if ($interval === 'one_time') {
			$timestamp = $config['timestamp'] ?? null;
			if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
				return 'one_time interval requires a valid unix timestamp';
			}
		}

		return true;
	}

	private function validateSteps(array $steps): bool|string {
		foreach ($steps as $index => $step) {
			if (!is_array($step)) {
				return "Step at index {$index} must be an object";
			}

			$step_type = $step['step_type'] ?? null;
			if (empty($step_type)) {
				return "Step at index {$index} is missing required step_type";
			}

			$valid_types = self::getValidStepTypes();
			if (!in_array($step_type, $valid_types, true)) {
				return "Step at index {$index} has invalid step_type '{$step_type}'. Must be one of: " . implode(', ', $valid_types);
			}
		}

		return true;
	}

	private function normalizeSteps(array $steps): array {
		$normalized = [];
		foreach ($steps as $index => $step) {
			$normalized_step = [
				'step_type' => $step['step_type'],
				'execution_order' => $step['execution_order'] ?? $index,
				'handler_slug' => $step['handler_slug'] ?? null,
				'handler_config' => $step['handler_config'] ?? []
			];

			if (isset($step['provider'])) {
				$normalized_step['provider'] = $step['provider'];
			}
			if (isset($step['model'])) {
				$normalized_step['model'] = $step['model'];
			}
			if (isset($step['system_prompt'])) {
				$normalized_step['system_prompt'] = $step['system_prompt'];
			}

			$normalized[] = $normalized_step;
		}
		return $normalized;
	}
}
