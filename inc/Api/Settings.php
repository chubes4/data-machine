<?php
/**
 * REST API Settings Endpoint
 *
 * Provides REST API access to settings operations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use WP_REST_Server;

if (!defined('WPINC')) {
	die;
}

class Settings {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register /datamachine/v1/settings and /datamachine/v1/cache endpoints
	 */
	public static function register_routes() {
		// Tool configuration endpoint
		register_rest_route('datamachine/v1', '/settings/tools/(?P<tool_id>[a-zA-Z0-9_-]+)', [
			'methods' => 'POST',
			'callback' => [self::class, 'handle_save_tool_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'tool_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Tool identifier', 'datamachine'),
				],
				'config_data' => [
					'required' => true,
					'type' => 'object',
					'description' => __('Tool configuration data', 'datamachine'),
				],
			],
		]);

		// Cache clearing endpoint
		register_rest_route('datamachine/v1', '/cache', [
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => [self::class, 'handle_clear_cache'],
			'permission_callback' => [self::class, 'check_permission'],
		]);
	}

	/**
	 * Check if user has permission to manage settings
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage settings.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle tool configuration save request
	 */
	public static function handle_save_tool_config($request) {
		$tool_id = $request->get_param('tool_id');
		$config_data = $request->get_param('config_data');

		if (empty($tool_id)) {
			return new \WP_Error(
				'missing_tool_id',
				__('Tool ID is required.', 'datamachine'),
				['status' => 400]
			);
		}

		if (empty($config_data) || !is_array($config_data)) {
			return new \WP_Error(
				'invalid_config_data',
				__('Valid configuration data is required.', 'datamachine'),
				['status' => 400]
			);
		}

		// Sanitize config data
		$sanitized_config = [];
		foreach ($config_data as $key => $value) {
			$sanitized_key = sanitize_text_field($key);
			$sanitized_config[$sanitized_key] = is_array($value)
				? array_map('sanitize_text_field', $value)
				: sanitize_text_field($value);
		}

		// Delegate to existing action hook for tool-specific handlers
		do_action('datamachine_save_tool_config', $tool_id, $sanitized_config);

		// Check if any tool handler responded
		// Tool handlers should use wp_send_json_success/error which exits
		// If we get here, no handler claimed responsibility
		do_action('datamachine_log', 'warning', 'No handler for tool configuration', [
			'tool_id' => $tool_id,
			'user_id' => get_current_user_id()
		]);

		return new \WP_Error(
			'no_tool_handler',
			sprintf(
				__('No configuration handler found for tool: %s', 'datamachine'),
				$tool_id
			),
			['status' => 500]
		);
	}

	/**
	 * Handle cache clear request
	 */
	public static function handle_clear_cache($request) {
		// Trigger cache clearing action
		do_action('datamachine_clear_all_cache');

		do_action('datamachine_log', 'info', 'Cache cleared via REST API', [
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'message' => __('All cache has been cleared successfully.', 'datamachine')
		];
	}
}
