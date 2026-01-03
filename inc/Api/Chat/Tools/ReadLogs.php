<?php
/**
 * Read Logs Tool
 *
 * Dedicated tool for reading Data Machine logs with filtering capabilities.
 * Supports filtering by job_id, pipeline_id, and flow_id for troubleshooting.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.8.2
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class ReadLogs {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'read_logs', [$this, 'getToolDefinition']);
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
			'description' => $this->buildDescription(),
			'parameters' => [
				'agent_type' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Log source: "pipeline" (default) for job execution logs, "chat" for chat agent logs'
				],
				'mode' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Content mode: "recent" (default) or "full"'
				],
				'limit' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Max entries for recent mode (default: 200, max: 10000)'
				],
				'job_id' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Filter logs by job ID'
				],
				'pipeline_id' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Filter logs by pipeline ID'
				],
				'flow_id' => [
					'type' => 'integer',
					'required' => false,
					'description' => 'Filter logs by flow ID'
				]
			]
		];
	}

	/**
	 * Build tool description.
	 *
	 * @return string Tool description
	 */
	private function buildDescription(): string {
		return 'Read Data Machine logs for troubleshooting jobs, flows, and pipelines.

AGENT TYPES:
- pipeline (default): Logs from job/flow execution - use this for troubleshooting failed jobs
- chat: Logs from chat agent operations - use this to check your own activity

FILTERS (all optional, combined with AND logic):
- job_id: Filter to specific job execution
- pipeline_id: Filter to specific pipeline
- flow_id: Filter to specific flow

MODES:
- recent (default): Most recent entries first, limited by limit param
- full: All matching entries

TIPS:
- Start with job_id filter when troubleshooting a specific failed job
- Use flow_id to see all executions of a particular flow
- Check chat logs to review your own recent operations';
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		$query_params = [
			'agent_type' => $parameters['agent_type'] ?? 'pipeline',
			'mode' => $parameters['mode'] ?? 'recent',
			'limit' => $parameters['limit'] ?? 200
		];

		if (!empty($parameters['job_id'])) {
			$query_params['job_id'] = (int) $parameters['job_id'];
		}
		if (!empty($parameters['pipeline_id'])) {
			$query_params['pipeline_id'] = (int) $parameters['pipeline_id'];
		}
		if (!empty($parameters['flow_id'])) {
			$query_params['flow_id'] = (int) $parameters['flow_id'];
		}

		$request = new \WP_REST_Request('GET', '/datamachine/v1/logs/content');
		$request->set_query_params($query_params);

		$response = rest_do_request($request);
		$data = $response->get_data();
		$status = $response->get_status();

		if ($status >= 400 || !($data['success'] ?? false)) {
			return [
				'success' => false,
				'error' => $data['message'] ?? 'Failed to read logs',
				'tool_name' => 'read_logs'
			];
		}

		return [
			'success' => true,
			'data' => $data,
			'tool_name' => 'read_logs'
		];
	}
}
