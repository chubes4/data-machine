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
		// Get all settings
		register_rest_route('datamachine/v1', '/settings', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_settings'],
			'permission_callback' => [self::class, 'check_permission'],
		]);

		// Update settings (partial update)
		register_rest_route('datamachine/v1', '/settings', [
			'methods' => 'PATCH',
			'callback' => [self::class, 'handle_update_settings'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'wordpress_settings' => [
					'type' => 'object',
					'description' => __('WordPress-specific settings', 'datamachine'),
				],
				'ai_settings' => [
					'type' => 'object',
					'description' => __('AI-specific settings', 'datamachine'),
				],
			],
		]);

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

	/**
	 * Handle get settings request
	 *
	 * @return array Settings data
	 */
	public static function handle_get_settings($request) {
		$all_settings = get_option('datamachine_settings', []);
		$wp_settings = $all_settings['wordpress_settings'] ?? [];
		$ai_settings = $all_settings['ai_settings'] ?? [];

		// Enrich author ID with author name
		if (!empty($wp_settings['default_author_id'])) {
			$author = get_userdata($wp_settings['default_author_id']);
			$wp_settings['default_author_name'] = $author ? $author->display_name : '';
		}

		do_action('datamachine_log', 'debug', 'Settings fetched via REST API', [
			'user_id' => get_current_user_id()
		]);

		return [
			'success' => true,
			'settings' => [
				'wordpress_settings' => $wp_settings,
				'ai_settings' => $ai_settings
			]
		];
	}

	/**
	 * Handle update settings request (partial update)
	 *
	 * @param \WP_REST_Request $request
	 * @return array|\WP_Error Updated settings or error
	 */
	public static function handle_update_settings($request) {
		$all_settings = get_option('datamachine_settings', []);

		// Get incoming updates
		$wp_settings_update = $request->get_param('wordpress_settings');
		$ai_settings_update = $request->get_param('ai_settings');

		// Merge updates with existing settings (partial update)
		if (is_array($wp_settings_update)) {
			$all_settings['wordpress_settings'] = array_merge(
				$all_settings['wordpress_settings'] ?? [],
				self::sanitize_settings_array($wp_settings_update)
			);
		}

		if (is_array($ai_settings_update)) {
			$all_settings['ai_settings'] = array_merge(
				$all_settings['ai_settings'] ?? [],
				self::sanitize_settings_array($ai_settings_update)
			);
		}

		// Update the option
		$updated = update_option('datamachine_settings', $all_settings);

		if (!$updated && get_option('datamachine_settings') !== $all_settings) {
			return new \WP_Error(
				'settings_update_failed',
				__('Failed to update settings.', 'datamachine'),
				['status' => 500]
			);
		}

		// Clear relevant caches
		do_action('datamachine_clear_flow_cache');

		do_action('datamachine_log', 'info', 'Settings updated via REST API', [
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login,
			'updated_keys' => array_keys($wp_settings_update ?? []) + array_keys($ai_settings_update ?? [])
		]);

		// Return updated settings
		return self::handle_get_settings($request);
	}

	/**
	 * Sanitize settings array recursively
	 *
	 * @param array $settings Settings to sanitize
	 * @return array Sanitized settings
	 */
	private static function sanitize_settings_array($settings) {
		$sanitized = [];

		foreach ($settings as $key => $value) {
			$sanitized_key = sanitize_text_field($key);

			if (is_array($value)) {
				$sanitized[$sanitized_key] = self::sanitize_settings_array($value);
			} elseif (is_bool($value)) {
				$sanitized[$sanitized_key] = (bool) $value;
			} elseif (is_numeric($value)) {
				$sanitized[$sanitized_key] = is_float($value) ? (float) $value : (int) $value;
			} else {
				$sanitized[$sanitized_key] = sanitize_text_field($value);
			}
		}

		return $sanitized;
	}
}
