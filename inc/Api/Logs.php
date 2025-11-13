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
						$available_levels = apply_filters('datamachine_log_file', [], 'get_available_levels');
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
 		// Log operation before clearing (don't log to the file we're clearing)
 		error_log('Data Machine: Logs cleared via REST API by user ' . wp_get_current_user()->user_login);

 		// Delegate to centralized delete action
 		do_action('datamachine_delete_logs');

 		return [
 			'success' => true,
 			'message' => __('Logs cleared successfully.', 'datamachine')
 		];
 	}

	/**
	 * Handle get log content request
	 *
	 * GET /datamachine/v1/logs/content
	 */
	public static function handle_get_content($request) {
		$mode = $request->get_param('mode');
		$limit = $request->get_param('limit');

		$log_file = datamachine_get_log_file_path();

		// Check if log file exists
		if (!file_exists($log_file)) {
			return new \WP_Error(
				'log_file_not_found',
				__('Log file does not exist.', 'datamachine'),
				['status' => 404]
			);
		}

		// Read log file
		$file_content = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if ($file_content === false) {
			return new \WP_Error(
				'log_file_read_error',
				__('Unable to read log file.', 'datamachine'),
				['status' => 500]
			);
		}

		// Reverse to show newest first
		$file_content = array_reverse($file_content);
		$total_lines = count($file_content);

		// Apply limit if mode is recent
		if ($mode === 'recent') {
			$file_content = array_slice($file_content, 0, $limit);
		}

		// Join lines with newlines
		$content = implode("\n", $file_content);

		// Log operation
		do_action('datamachine_log', 'debug', 'Log content retrieved via REST API', [
			'mode' => $mode,
			'lines_returned' => count($file_content),
			'total_lines' => $total_lines,
			'user_id' => get_current_user_id()
		]);

		return [
			'success' => true,
			'content' => $content,
			'total_lines' => $total_lines,
			'mode' => $mode,
			'message' => sprintf(
				__('Loaded %d %s log entries.', 'datamachine'),
				count($file_content),
				$mode === 'recent' ? 'recent' : 'total'
			)
		];
	}

	/**
	 * Handle get log metadata request
	 *
	 * GET /datamachine/v1/logs
	 */
	public static function handle_get_metadata($request) {
		$log_file_path = datamachine_get_log_file_path();
		$log_file_exists = file_exists($log_file_path);
		$log_file_size = $log_file_exists ? filesize($log_file_path) : 0;

		// Format file size
		$size_formatted = $log_file_size > 0
			? size_format($log_file_size, 2)
			: '0 bytes';

		// Get current log level
		$current_level = apply_filters('datamachine_log_file', null, 'get_level');

		// Get available log levels
		$available_levels = apply_filters('datamachine_log_file', [], 'get_available_levels');

		return [
			'success' => true,
			'log_file' => [
				'path' => $log_file_path,
				'exists' => $log_file_exists,
				'size' => $log_file_size,
				'size_formatted' => $size_formatted
			],
			'configuration' => [
				'current_level' => $current_level,
				'available_levels' => $available_levels
			]
		];
	}

	/**
	 * Handle update log level request
	 *
	 * PUT /datamachine/v1/logs/level
	 */
	public static function handle_update_level($request) {
		$new_level = $request->get_param('level');

		// Update log level via action
		do_action('datamachine_log', 'set_level', $new_level);

		// Get available levels for display name
		$available_levels = apply_filters('datamachine_log_file', [], 'get_available_levels');
		$level_display = $available_levels[$new_level] ?? ucfirst($new_level);

		// Log operation
		do_action('datamachine_log', 'info', 'Log level updated via REST API', [
			'new_level' => $new_level,
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'level' => $new_level,
			'message' => sprintf(
				__('Log level updated to %s.', 'datamachine'),
				$level_display
			)
		];
	}
}
