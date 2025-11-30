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

use DataMachine\Core\PluginSettings;
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
	 * Register /datamachine/v1/settings endpoints
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
				'ai_settings' => [
					'type' => 'object',
					'description' => __('AI-specific settings', 'datamachine'),
				],
			],
		]);

		// Scheduling intervals endpoint
		register_rest_route('datamachine/v1', '/settings/scheduling-intervals', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_scheduling_intervals'],
			'permission_callback' => [self::class, 'check_permission'],
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
	 * Handle get settings request
	 *
	 * @return WP_REST_Response Settings data
	 */
	public static function handle_get_settings($request) {
		$ai_settings = PluginSettings::get('ai_settings', []);

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'settings' => [
					'ai_settings' => $ai_settings
				]
			]
		]);
	}

	/**
	 * Handle update settings request (partial update)
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error Updated settings or error
	 */
	public static function handle_update_settings($request) {
		$all_settings = get_option('datamachine_settings', []);

		// Get incoming updates
		$ai_settings_update = $request->get_param('ai_settings');

		// Merge updates with existing settings (partial update)
		if (is_array($ai_settings_update)) {
			$all_settings['ai_settings'] = array_merge(
				$all_settings['ai_settings'] ?? [],
				self::sanitize_settings_array($ai_settings_update)
			);
		}

		// Update the option
		$updated = update_option('datamachine_settings', $all_settings);
		PluginSettings::clearCache();

		if (!$updated && get_option('datamachine_settings') !== $all_settings) {
			return new \WP_Error(
				'settings_update_failed',
				__('Failed to update settings.', 'datamachine'),
				['status' => 500]
			);
		}

		// Return updated settings
		return self::handle_get_settings($request);
	}

	/**
	 * Handle get scheduling intervals request
	 */
	public static function handle_get_scheduling_intervals($request) {
		$intervals = apply_filters('datamachine_scheduler_intervals', []);

		// Transform from PHP format to frontend format
		$frontend_intervals = [];

		// Add manual option first
		$frontend_intervals[] = [
			'value' => 'manual',
			'label' => __('Manual only', 'datamachine')
		];

		// Add all PHP-defined intervals
		foreach ($intervals as $key => $interval_data) {
			$frontend_intervals[] = [
				'value' => $key,
				'label' => $interval_data['label']
			];
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $frontend_intervals
		]);
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
