<?php
/**
 * List Flows Tool
 *
 * Chat tool for listing flows with optional filtering by pipeline ID or handler slug.
 * Wraps FlowAbilities API primitive.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

defined('ABSPATH') || exit;

class ListFlows {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'list_flows', [$this, 'getToolDefinition']);
	}

	public function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => 'List flows with optional filtering by pipeline ID or handler slug. Supports pagination.',
			'parameters' => [
				'pipeline_id' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Filter flows by pipeline ID'
				],
				'handler_slug' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Filter flows using this handler slug (any step that uses this handler)'
				],
				'per_page' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Number of flows per page (default: 20, max: 100)'
				],
				'offset' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Offset for pagination (default: 0)'
				]
			]
		];
	}

	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$ability = wp_get_ability('datamachine/list-flows');

		if (!$ability) {
			return [
				'success' => false,
				'error' => 'datamachine/list-flows ability not found',
				'tool_name' => 'list_flows'
			];
		}

		$result = $ability->execute($parameters);

		if (!$result['success']) {
			return [
				'success' => false,
				'error' => $result['error'],
				'tool_name' => 'list_flows'
			];
		}

		return [
			'success' => true,
			'data' => $result,
			'tool_name' => 'list_flows'
		];
	}
}
