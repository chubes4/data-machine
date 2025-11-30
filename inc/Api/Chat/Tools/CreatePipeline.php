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

	private const VALID_STEP_TYPES = ['fetch', 'ai', 'publish', 'update'];
	private const VALID_INTERVALS = ['manual', 'hourly', 'daily', 'weekly', 'monthly', 'one_time'];

	public function __construct() {
		$this->registerTool('chat', 'create_pipeline', $this->getToolDefinition());
	}

	private function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'Create a new pipeline with optional predefined steps. Automatically creates an associated flow. Use this instead of make_api_request for pipeline creation.',
			'parameters' => [
				'pipeline_name' => [
					'type' => 'string',
					'required' => true,
					'description' => 'Name for the new pipeline'
				],
				'steps' => [
					'type' => 'array',
					'required' => false,
					'description' => 'Array of step definitions in execution order. Each step: {step_type: "fetch|ai|publish|update", handler_slug: "rss|wordpress|bluesky|etc", handler_config: {...}}. Handler details can be configured later with configure_flow_step.'
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

		return [
			'success' => true,
			'data' => [
				'pipeline_id' => $result['pipeline_id'],
				'pipeline_name' => $result['pipeline_name'],
				'flow_id' => $result['flows'][0]['flow_id'] ?? null,
				'flow_name' => $flow_name,
				'steps_created' => count($steps),
				'flow_step_ids' => $flow_step_ids,
				'scheduling' => $scheduling_config['interval'],
				'message' => empty($steps)
					? 'Pipeline created successfully. Use add_pipeline_step to add steps, then configure_flow_step to configure handlers.'
					: 'Pipeline created with ' . count($steps) . ' steps. Use configure_flow_step with the flow_step_ids to set handler configurations.'
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

			if (!in_array($step_type, self::VALID_STEP_TYPES, true)) {
				return "Step at index {$index} has invalid step_type '{$step_type}'. Must be one of: " . implode(', ', self::VALID_STEP_TYPES);
			}
		}

		return true;
	}

	private function normalizeSteps(array $steps): array {
		$normalized = [];
		foreach ($steps as $index => $step) {
			$normalized[] = [
				'step_type' => $step['step_type'],
				'execution_order' => $step['execution_order'] ?? $index,
				'handler_slug' => $step['handler_slug'] ?? null,
				'handler_config' => $step['handler_config'] ?? []
			];
		}
		return $normalized;
	}
}

new CreatePipeline();
