<?php
/**
 * API Query Tool
 *
 * Internal REST API query tool for chat agent.
 * Used for discovery, monitoring, and troubleshooting operations.
 * Supports single and batch requests.
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
		$this->registerTool('chat', 'api_query', [$this, 'getToolDefinition']);
	}

	/**
	 * Get API Query tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		return [
			'class' => self::class,
			'method' => 'handle_tool_call',
			'description' => $this->buildApiDocumentation(),
			'parameters' => [
				'endpoint' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Single mode: REST API endpoint path (e.g., /datamachine/v1/handlers)'
				],
				'method' => [
					'type' => 'string',
					'required' => false,
					'description' => 'Single mode: HTTP method (GET, POST, PUT, PATCH, DELETE)'
				],
				'data' => [
					'type' => 'object',
					'required' => false,
					'description' => 'Single mode: Request body data for POST/PUT/PATCH requests'
				],
				'requests' => [
					'type' => 'array',
					'required' => false,
					'description' => 'Batch mode: Array of requests. Each request: {endpoint: string, method: string, data?: object, key?: string}. Results keyed by endpoint name or custom key.'
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
		return 'Query Data Machine REST API for discovery, monitoring, and troubleshooting.

MODES:
- Single: Use endpoint + method params for one request
- Batch: Use requests array for multiple requests in one call (faster)

BATCH EXAMPLE:
{
  "requests": [
    {"endpoint": "/datamachine/v1/handlers", "method": "GET"},
    {"endpoint": "/datamachine/v1/pipelines", "method": "GET"},
    {"endpoint": "/datamachine/v1/providers", "method": "GET"}
  ]
}

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
GET /datamachine/v1/settings - Get plugin settings
POST /datamachine/v1/settings - Update settings

## Files
GET /datamachine/v1/files - List uploaded files
POST /datamachine/v1/files - Upload file
DELETE /datamachine/v1/files/{filename} - Delete file
';
	}

	/**
	 * Execute API query - single or batch mode.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def   Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call(array $parameters, array $tool_def = []): array {
		// Batch mode: requests array provided
		if (!empty($parameters['requests']) && is_array($parameters['requests'])) {
			return $this->handleBatchRequest($parameters['requests']);
		}

		// Single mode: existing behavior
		return $this->handleSingleRequest($parameters);
	}

	/**
	 * Handle single API request.
	 *
	 * @param array $parameters Request parameters
	 * @return array Result
	 */
	private function handleSingleRequest(array $parameters): array {
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

		$result = $this->executeSingleRequest($endpoint, $method, $data);

		return array_merge($result, ['tool_name' => 'api_query']);
	}

	/**
	 * Handle batch API request.
	 *
	 * @param array $requests Array of request definitions
	 * @return array Batch result with keyed responses
	 */
	private function handleBatchRequest(array $requests): array {
		if (empty($requests)) {
			return [
				'success' => false,
				'error' => 'Requests array cannot be empty',
				'tool_name' => 'api_query'
			];
		}

		$results = [];
		$errors = [];
		$used_keys = [];

		foreach ($requests as $index => $req) {
			$endpoint = $req['endpoint'] ?? '';
			$method = strtoupper($req['method'] ?? 'GET');
			$data = $req['data'] ?? [];

			// Determine result key
			$key = $req['key'] ?? $this->extractKeyFromEndpoint($endpoint);

			// Handle duplicate keys by appending index
			if (isset($used_keys[$key])) {
				$key = $key . '_' . $index;
			}
			$used_keys[$key] = true;

			if (empty($endpoint)) {
				$errors[$key] = 'Missing endpoint';
				continue;
			}

			$result = $this->executeSingleRequest($endpoint, $method, $data);

			if ($result['success']) {
				$results[$key] = $result['data'];
			} else {
				$errors[$key] = $result['error'];
			}
		}

		$response = [
			'success' => empty($errors),
			'data' => $results,
			'tool_name' => 'api_query',
			'batch' => true,
			'request_count' => count($requests),
			'success_count' => count($results),
			'error_count' => count($errors)
		];

		if (!empty($errors)) {
			$response['errors'] = $errors;
			// Partial success if some requests succeeded
			if (!empty($results)) {
				$response['success'] = true;
				$response['partial'] = true;
			}
		}

		return $response;
	}

	/**
	 * Execute a single REST API request.
	 *
	 * @param string $endpoint API endpoint path
	 * @param string $method   HTTP method
	 * @param array  $data     Request body data
	 * @return array Result with success, data/error, status
	 */
	private function executeSingleRequest(string $endpoint, string $method, array $data = []): array {
		$allowed_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
		if (!in_array($method, $allowed_methods, true)) {
			return [
				'success' => false,
				'error' => 'Invalid HTTP method. Allowed: GET, POST, PUT, PATCH, DELETE'
			];
		}

		$parsed = wp_parse_url($endpoint);
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
				'error' => $response->get_error_message()
			];
		}

		return [
			'success' => true,
			'data' => $response->get_data(),
			'status' => $response->get_status()
		];
	}

	/**
	 * Extract a key name from an endpoint path.
	 *
	 * @param string $endpoint Endpoint path
	 * @return string Generated key
	 */
	private function extractKeyFromEndpoint(string $endpoint): string {
		// Parse and get path without query string
		$parsed = wp_parse_url($endpoint);
		$path = $parsed['path'] ?? $endpoint;

		// Remove /datamachine/v1/ prefix
		$path = preg_replace('#^/datamachine/v\d+/#', '', $path);

		// Split into segments
		$segments = array_filter(explode('/', $path));

		if (empty($segments)) {
			return 'result';
		}

		// Get main resource (first segment)
		$resource = array_shift($segments);

		// If there's an ID (numeric second segment), append it
		if (!empty($segments)) {
			$next = array_shift($segments);
			if (is_numeric($next)) {
				$resource .= '_' . $next;
			} elseif (!empty($next)) {
				// Sub-resource like /pipelines/5/steps
				$resource .= '_' . $next;
			}
		}

		return $resource;
	}
}
