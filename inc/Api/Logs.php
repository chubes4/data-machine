<?php
/**
 * Logs REST API Endpoints
 *
 * Provides REST API access to log file operations and configuration.
 * Requires WordPress manage_options capability for all operations.
 *
 * Endpoints:
 * - DELETE /datamachine/v1/logs - Clear log file
 * - GET /datamachine/v1/logs/content - Get log file content
 * - GET /datamachine/v1/logs - Get log metadata and configuration
 * - PUT /datamachine/v1/logs/level - Update log level
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use DataMachine\Services\LogsManager;

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

		// DELETE /datamachine/v1/logs - Clear logs
		register_rest_route('datamachine/v1', '/logs', [
			'methods' => 'DELETE',
			'callback' => [self::class, 'handle_clear_logs'],
			'permission_callback' => [self::class, 'check_permission']
		]);

		// GET /datamachine/v1/logs/content - Get log content
		register_rest_route('datamachine/v1', '/logs/content', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_content'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'mode' => [
					'required' => false,
					'type' => 'string',
					'default' => 'full',
					'description' => __('Content mode: full or recent', 'datamachine'),
					'enum' => ['full', 'recent']
				],
				'limit' => [
					'required' => false,
					'type' => 'integer',
					'default' => 200,
					'description' => __('Number of recent entries (when mode=recent)', 'datamachine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0 && $param <= 10000;
					}
				],
				'job_id' => [
					'required' => false,
					'type' => 'integer',
					'description' => __('Filter logs by job ID', 'datamachine'),
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
			'permission_callback' => [self::class, 'check_permission']
		]);

		// PUT /datamachine/v1/logs/level - Update log level
		register_rest_route('datamachine/v1', '/logs/level', [
			'methods' => ['PUT', 'POST'],
			'callback' => [self::class, 'handle_update_level'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'level' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Log level to set', 'datamachine'),
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
				__('You do not have permission to manage logs.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle clear logs request
	 *
	 * DELETE /datamachine/v1/logs
	 */
 	public static function handle_clear_logs($request) {
 		LogsManager::clear();

		return rest_ensure_response([
			'success' => true,
			'data' => null,
			'message' => __('Logs cleared successfully.', 'datamachine')
		]);
 	}

	/**
	 * Handle get log content request
	 *
	 * GET /datamachine/v1/logs/content
	 */
	public static function handle_get_content($request) {
		$mode = $request->get_param('mode');
		$limit = $request->get_param('limit');
		$job_id = $request->get_param('job_id');

		$result = LogsManager::getContent($mode, $limit, $job_id);

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
	 */
	public static function handle_get_metadata($request) {
		return rest_ensure_response(LogsManager::getMetadata());
	}

	/**
	 * Handle update log level request
	 *
	 * PUT /datamachine/v1/logs/level
	 */
	public static function handle_update_level($request) {
		$new_level = $request->get_param('level');

		LogsManager::setLevel($new_level);

		$available_levels = datamachine_get_available_log_levels();
		$level_display = $available_levels[$new_level] ?? ucfirst($new_level);

		return rest_ensure_response([
			'success' => true,
			'level' => $new_level,
			'message' => sprintf(
				/* translators: %s: log level label */
				esc_html__('Log level updated to %s.', 'datamachine'),
				$level_display
			)
		]);
	}
}
