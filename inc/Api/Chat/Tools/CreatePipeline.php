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

class CreatePipeline {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'create_pipeline', $this->getToolDefinition());
	}

	private static function getValidStepTypes(): array {
		$step_types = apply_filters('datamachine_step_types', []);
		return array_keys($step_types);
	}

	private static function getValidIntervals(): array {
		$intervals = apply_filters('datamachine_scheduler_intervals', []);
		return array_merge(['manual', 'one_time'], array_keys($intervals));
	}

	private function getToolDefinition(): array {
		$valid_types = self::getValidStepTypes();
		$types_list = !empty($valid_types) ? implode('|', $valid_types) : 'fetch|ai|publish|update';
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Create a new pipeline with optional predefined steps. IMPORTANT: This tool automatically creates a flow - do NOT call create_flow afterward. For AI steps, include provider, model, and system_prompt in the step definition.',
			'parameters' => [
				'pipeline_name' => [
					'type' => 'string',
					'required' => true,
					'description' => 'Name for the new pipeline'
				],
				'steps' => [
					'type' => 'array',
					'required' => false,
					'description' => "Array of step definitions in execution order. Each step: {step_type: \"{$types_list}\", handler_slug: \"handler_slug\", handler_config: {...}}. For AI steps, also include: provider, model, system_prompt. Handler details can be configured later with configure_flow_step."
				],
				'flow_name' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Name for the automatically created flow (defaults to pipeline_name)'
				],
				'scheduling_config' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Scheduling: {interval: "manual|hourly|daily|weekly|monthly"} or {interval: "one_time", timestamp: unix_timestamp}. Defaults to manual.'
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
				$flow_config = $flow['flow_config'];
				if (is_array($flow_config)) {
					$flow_step_ids = array_keys($flow_config);
				}
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

		$valid_intervals = self::getValidIntervals();
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
		if (!is_array($steps)) {
			return 'steps must be an array';
		}

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

new CreatePipeline();
