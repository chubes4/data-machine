<?php
/**
 * API Query Tool
 *
 * Internal REST API query tool for chat agent.
 * Used for discovery, monitoring, and troubleshooting operations.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
	exit;
}

use \DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

/**
 * API Query Tool
 */
class ApiQuery {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool('chat', 'api_query', $this->getToolDefinition());
	}

	/**
	 * Get API Query tool definition.
	 *
	 * @return array Tool definition array
	 */
	private function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => $this->buildApiDocumentation(),
			'parameters' => [
				'endpoint' => [
					'type' => 'string',
					'required' => true,
					'description' => 'REST API endpoint path (e.g., /datamachine/v1/handlers)'
				],
				'method' => [
					'type' => 'string',
					'required' => true,
					'description' => 'HTTP method: GET, POST, PUT, PATCH, or DELETE'
				],
				'data' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Request body data for POST/PUT/PATCH requests'
				]
			]
		];
	}

	/**
	 * Build comprehensive API documentation for the tool description.
	 *
	 * @return string API documentation
	 */
	private function buildApiDocumentation(): string {
		return <<<'DOC'
Query Data Machine REST API for discovery, monitoring, and troubleshooting.

PREFER SPECIALIZED TOOLS FOR ACTIONS:
- For creating pipelines: use create_pipeline tool
- For adding pipeline steps: use add_pipeline_step tool
- For creating flows: use create_flow tool
- For configuring flow steps: use configure_flow_step tool
- For workflow execution: use execute_workflow tool

ENDPOINTS:

## Discovery
GET /datamachine/v1/handlers - List all handlers
GET /datamachine/v1/handlers?step_type={fetch|publish|update} - Filter by type
GET /datamachine/v1/handlers/{slug} - Handler details and config schema
GET /datamachine/v1/auth/{handler}/status - Check OAuth connection status
GET /datamachine/v1/providers - List AI providers and models
GET /datamachine/v1/tools - List available AI tools

## Pipelines (read-only - use create_pipeline tool for creation)
GET /datamachine/v1/pipelines - List all pipelines
GET /datamachine/v1/pipelines/{id} - Get pipeline details with steps and flows
DELETE /datamachine/v1/pipelines/{id} - Delete pipeline

## Pipeline Steps (use add_pipeline_step tool for adding)
DELETE /datamachine/v1/pipelines/{id}/steps/{step_id} - Remove step
PUT /datamachine/v1/pipelines/{id}/steps/reorder - Reorder steps
  data: {step_order: [{pipeline_step_id: "...", execution_order: 0}, ...]}

## Flows (use create_flow and configure_flow_step tools)
GET /datamachine/v1/flows - List all flows
GET /datamachine/v1/flows/{id} - Get flow details
DELETE /datamachine/v1/flows/{id} - Delete flow
POST /datamachine/v1/flows/{id}/duplicate - Duplicate flow

## Scheduling
PATCH /datamachine/v1/flows/{id} - Update flow scheduling
  data: {scheduling_config: {interval: "manual|hourly|daily|weekly|monthly"}}
  data: {scheduling_config: {interval: "one_time", timestamp: unix_timestamp}}

## Jobs & Monitoring
GET /datamachine/v1/jobs - List all jobs
GET /datamachine/v1/jobs?flow_id={id} - Jobs for specific flow
GET /datamachine/v1/jobs?status={pending|running|completed|failed} - Filter by status
GET /datamachine/v1/jobs/{id} - Job details

## Logs
GET /datamachine/v1/logs/content - Get log content
GET /datamachine/v1/logs/content?job_id={id} - Logs for specific job
GET /datamachine/v1/logs/content?mode=recent&limit=100 - Recent logs
DELETE /datamachine/v1/logs - Clear logs
PUT /datamachine/v1/logs/level - Set log level
  data: {level: "debug|info|warning|error"}

## System
DELETE /datamachine/v1/cache - Clear all caches
GET /datamachine/v1/settings - Get plugin settings
POST /datamachine/v1/settings - Update settings

## Files
GET /datamachine/v1/files - List uploaded files
POST /datamachine/v1/files - Upload file
DELETE /datamachine/v1/files/{filename} - Delete file
DOC;
	}

	/**
	 * Execute API query
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
				'tool_name' => 'api_query'
			];
		}

		$allowed_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
		if (!in_array($method, $allowed_methods, true)) {
			return [
				'success' => false,
				'error' => 'Invalid HTTP method. Allowed: GET, POST, PUT, PATCH, DELETE',
				'tool_name' => 'api_query'
			];
		}

		$parsed = parse_url($endpoint);
		$path = $parsed['path'] ?? $endpoint;
		$query_string = $parsed['query'] ?? '';

		$request = new \WP_REST_Request($method, $path);

		if (!empty($query_string)) {
			parse_str($query_string, $query_params);
			$request->set_query_params($query_params);
		}

		if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
			$request->set_body_params($data);
		}

		$response = rest_do_request($request);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'error' => $response->get_error_message(),
				'tool_name' => 'api_query'
			];
		}

		$response_data = $response->get_data();
		$status_code = $response->get_status();

		return [
			'success' => true,
			'data' => $response_data,
			'status' => $status_code,
			'tool_name' => 'api_query'
		];
	}
}

new ApiQuery();
