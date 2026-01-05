<?php
/**
 * Get Problem Flows Tool
 *
 * Identifies flows with issues that may need attention:
 * - Consecutive failures (something is broken)
 * - Consecutive no-items (source is slow/exhausted, consider lowering interval)
 *
 * Uses the problem_flow_threshold setting by default.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Core\PluginSettings;

class GetProblemFlows {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'get_problem_flows', [$this, 'getToolDefinition']);
	}

	/**
	 * Get tool definition.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$default_threshold = PluginSettings::get('problem_flow_threshold', 3);

		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => "Identify flows with issues: consecutive failures (broken) or consecutive no-items runs (source exhausted). Default threshold: {$default_threshold}.",
			'parameters' => [
				'threshold' => [
					'type' => 'integer',
					'required' => false,
					'description' => "Minimum consecutive count to report (default: {$default_threshold} from settings)"
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
		$threshold = $parameters['threshold'] ?? null;

		// Use setting if threshold not provided
		if ($threshold === null || !is_numeric($threshold) || (int) $threshold <= 0) {
			$threshold = PluginSettings::get('problem_flow_threshold', 3);
		}

		$threshold = (int) $threshold;

		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$problem_flows = $db_flows->get_problem_flows($threshold);

		if (empty($problem_flows)) {
			return [
				'success' => true,
				'data' => [
					'problem_flows' => [],
					'total' => 0,
					'threshold' => $threshold,
					'message' => "No problem flows detected. All flows are below the threshold of {$threshold}."
				],
				'tool_name' => 'get_problem_flows'
			];
		}

		// Categorize and build summary
		$failing_flows = [];
		$idle_flows = [];

		foreach ($problem_flows as $flow) {
			$failures = $flow['consecutive_failures'] ?? 0;
			$no_items = $flow['consecutive_no_items'] ?? 0;

			if ($failures >= $threshold) {
				$failing_flows[] = sprintf(
					'%s (Flow #%d) - %d consecutive failures - investigate errors',
					$flow['flow_name'],
					$flow['flow_id'],
					$failures
				);
			}

			if ($no_items >= $threshold) {
				$idle_flows[] = sprintf(
					'%s (Flow #%d) - %d runs with no new items - consider lowering interval',
					$flow['flow_name'],
					$flow['flow_id'],
					$no_items
				);
			}
		}

		// Build message
		$message_parts = [];

		if (!empty($failing_flows)) {
			$message_parts[] = "FAILING FLOWS ({$threshold}+ consecutive failures):\n- " . implode("\n- ", $failing_flows);
		}

		if (!empty($idle_flows)) {
			$message_parts[] = "IDLE FLOWS ({$threshold}+ runs with no new items):\n- " . implode("\n- ", $idle_flows);
		}

		$message = implode("\n\n", $message_parts);

		return [
			'success' => true,
			'data' => [
				'problem_flows' => $problem_flows,
				'total' => count($problem_flows),
				'failing_count' => count($failing_flows),
				'idle_count' => count($idle_flows),
				'threshold' => $threshold,
				'message' => $message
			],
			'tool_name' => 'get_problem_flows'
		];
	}
}
