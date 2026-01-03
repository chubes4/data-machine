<?php
/**
 * Logs REST API Endpoints
 *
 * Provides REST API access to log file operations and configuration.
 * Supports per-agent-type log files and levels.
 * Requires WordPress manage_options capability for all operations.
 *
 * Endpoints:
 * - GET /datamachine/v1/logs/agent-types - Get available agent types
 * - GET /datamachine/v1/logs - Get log metadata (requires agent_type param)
 * - GET /datamachine/v1/logs/content - Get log file content (requires agent_type param)
 * - DELETE /datamachine/v1/logs - Clear log file (requires agent_type param, or agent_type=all)
 * - PUT /datamachine/v1/logs/level - Update log level (requires agent_type param)
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use DataMachine\Services\LogsManager;
use DataMachine\Engine\AI\AgentType;

if (!defined('WPINC')) {
	die;
}

class Logs {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register all log-related REST endpoints
	 */
	public static function register_routes() {

		// GET /datamachine/v1/logs/agent-types - Get available agent types
		register_rest_route('datamachine/v1', '/logs/agent-types', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_agent_types'],
			'permission_callback' => [self::class, 'check_permission']
		]);

		// DELETE /datamachine/v1/logs - Clear logs
		register_rest_route('datamachine/v1', '/logs', [
			'methods' => 'DELETE',
			'callback' => [self::class, 'handle_clear_logs'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'agent_type' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Agent type to clear logs for, or "all" to clear all logs', 'data-machine'),
					'validate_callback' => function($param) {
						return $param === 'all' || AgentType::isValid($param);
					}
				]
			]
		]);

		// GET /datamachine/v1/logs/content - Get log content
		register_rest_route('datamachine/v1', '/logs/content', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_content'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'agent_type' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Agent type to get logs for', 'data-machine'),
					'validate_callback' => function($param) {
						return AgentType::isValid($param);
					}
				],
				'mode' => [
					'required' => false,
					'type' => 'string',
					'default' => 'full',
					'description' => __('Content mode: full or recent', 'data-machine'),
					'enum' => ['full', 'recent']
				],
				'limit' => [
					'required' => false,
					'type' => 'integer',
					'default' => 200,
					'description' => __('Number of recent entries (when mode=recent)', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0 && $param <= 10000;
					}
				],
				'job_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter logs by job ID', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					}
				],
				'pipeline_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter logs by pipeline ID', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					}
				],
				'flow_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter logs by flow ID', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					}
				]
			]
		]);

		// GET /datamachine/v1/logs - Get log metadata
		register_rest_route('datamachine/v1', '/logs', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_metadata'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'agent_type' => [
					'required' => false,
					'type' => 'string',
					'description' => __('Agent type to get metadata for. If omitted, returns metadata for all agent types.', 'data-machine'),
					'validate_callback' => function($param) {
						return empty($param) || AgentType::isValid($param);
					}
				]
			]
		]);

		// PUT /datamachine/v1/logs/level - Update log level
		register_rest_route('datamachine/v1', '/logs/level', [
			'methods' => ['PUT', 'POST'],
			'callback' => [self::class, 'handle_update_level'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'agent_type' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Agent type to set log level for', 'data-machine'),
					'validate_callback' => function($param) {
						return AgentType::isValid($param);
					}
				],
				'level' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Log level to set', 'data-machine'),
					'validate_callback' => function($param) {
						$available_levels = datamachine_get_available_log_levels();
						return array_key_exists($param, $available_levels);
					}
				]
			]
		]);
	}

	/**
	 * Check if user has permission to manage logs
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage logs.', 'data-machine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle get agent types request
	 *
	 * GET /datamachine/v1/logs/agent-types
	 */
	public static function handle_get_agent_types($request) {
		$agent_types = AgentType::getAll();

		return rest_ensure_response([
			'success' => true,
			'data' => $agent_types
		]);
	}

	/**
	 * Handle clear logs request
	 *
	 * DELETE /datamachine/v1/logs?agent_type=pipeline
	 * DELETE /datamachine/v1/logs?agent_type=all
	 */
	public static function handle_clear_logs($request) {
		$agent_type = $request->get_param('agent_type');

		if ($agent_type === 'all') {
			LogsManager::clearAll();
			return rest_ensure_response([
				'success' => true,
				'data' => null,
				'message' => __('All logs cleared successfully.', 'data-machine')
			]);
		}

		LogsManager::clear($agent_type);

		$agent_types = AgentType::getAll();
		$agent_label = $agent_types[$agent_type]['label'] ?? ucfirst($agent_type);

		return rest_ensure_response([
			'success' => true,
			'data' => null,
			'message' => sprintf(
				__('%s logs cleared successfully.', 'data-machine'),
				$agent_label
			)
		]);
	}

	/**
	 * Handle get log content request
	 *
	 * GET /datamachine/v1/logs/content?agent_type=pipeline
	 */
	public static function handle_get_content($request) {
		$agent_type = $request->get_param('agent_type');
		$mode = $request->get_param('mode');
		$limit = $request->get_param('limit');
		$job_id = $request->get_param('job_id');
		$pipeline_id = $request->get_param('pipeline_id');
		$flow_id = $request->get_param('flow_id');

		$result = LogsManager::getContent($agent_type, $mode, $limit, $job_id, $pipeline_id, $flow_id);

		if (!$result['success']) {
			return new \WP_Error(
				$result['error'],
				$result['message'],
				['status' => $result['error'] === 'log_file_not_found' ? 404 : 500]
			);
		}

		return rest_ensure_response($result);
	}

	/**
	 * Handle get log metadata request
	 *
	 * GET /datamachine/v1/logs
	 * GET /datamachine/v1/logs?agent_type=pipeline
	 */
	public static function handle_get_metadata($request) {
		$agent_type = $request->get_param('agent_type');

		if (empty($agent_type)) {
			return rest_ensure_response(LogsManager::getAllMetadata());
		}

		return rest_ensure_response(LogsManager::getMetadata($agent_type));
	}

	/**
	 * Handle update log level request
	 *
	 * PUT /datamachine/v1/logs/level
	 */
	public static function handle_update_level($request) {
		$agent_type = $request->get_param('agent_type');
		$new_level = $request->get_param('level');

		LogsManager::setLevel($agent_type, $new_level);

		$available_levels = datamachine_get_available_log_levels();
		$level_display = $available_levels[$new_level] ?? ucfirst($new_level);

		$agent_types = AgentType::getAll();
		$agent_label = $agent_types[$agent_type]['label'] ?? ucfirst($agent_type);

		return rest_ensure_response([
			'success' => true,
			'agent_type' => $agent_type,
			'level' => $new_level,
			'message' => sprintf(
				__('%s log level updated to %s.', 'data-machine'),
				$agent_label,
				$level_display
			)
		]);
	}
}
