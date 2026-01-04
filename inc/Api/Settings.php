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
use DataMachine\Services\HandlerService;
use DataMachine\Services\StepTypeService;
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
					'description' => __('AI-specific settings', 'data-machine'),
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
					'description' => __('Tool identifier', 'data-machine'),
				],
				'config_data' => [
					'required' => true,
					'type' => 'object',
					'description' => __('Tool configuration data', 'data-machine'),
				],
			],
		]);

		// Handler defaults endpoints
		register_rest_route('datamachine/v1', '/settings/handler-defaults', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_handler_defaults'],
			'permission_callback' => [self::class, 'check_permission'],
		]);

		register_rest_route('datamachine/v1', '/settings/handler-defaults/(?P<handler_slug>[a-zA-Z0-9_-]+)', [
			'methods' => 'PUT',
			'callback' => [self::class, 'handle_update_handler_defaults'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'handler_slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'description' => __('Handler slug', 'data-machine'),
				],
				'defaults' => [
					'required' => true,
					'type' => 'object',
					'description' => __('Default configuration values', 'data-machine'),
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
				__('You do not have permission to manage settings.', 'data-machine'),
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
				__('Tool ID is required.', 'data-machine'),
				['status' => 400]
			);
		}

		if (empty($config_data) || !is_array($config_data)) {
			return new \WP_Error(
				'invalid_config_data',
				__('Valid configuration data is required.', 'data-machine'),
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
				/* translators: %s: tool ID */
				__('No configuration handler found for tool: %s', 'data-machine'),
				$tool_id
			),
			['status' => 500]
		);
	}

	/**
	 * Handle get settings request
	 *
	 * Returns all settings needed for the React settings page.
	 *
	 * @return WP_REST_Response Settings data
	 */
	public static function handle_get_settings($request) {
		$settings = PluginSettings::all();

		// Get global tools for agent tab (keyed by tool name for frontend)
		$tool_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
		$global_tools = $tool_manager->get_global_tools();
		$tools_keyed = [];
		foreach ($global_tools as $tool_name => $tool_config) {
			$tools_keyed[$tool_name] = [
				'label' => $tool_config['label'] ?? ucfirst(str_replace('_', ' ', $tool_name)),
				'description' => $tool_config['description'] ?? '',
				'is_configured' => $tool_manager->is_tool_configured($tool_name),
				'requires_configuration' => $tool_manager->requires_configuration($tool_name),
				'is_enabled' => isset($settings['enabled_tools'][$tool_name]),
			];
		}

		// Get AI provider keys and mask them for the frontend
		$raw_keys = apply_filters('chubes_ai_provider_api_keys', null) ?: [];
		$masked_keys = [];
		foreach ($raw_keys as $provider => $key) {
			if (!empty($key)) {
				// Show first 4 and last 4 characters, mask the middle
				if (strlen($key) > 12) {
					$masked_keys[$provider] = substr($key, 0, 4) . '****************' . substr($key, -4);
				} else {
					$masked_keys[$provider] = '****************';
				}
			} else {
				$masked_keys[$provider] = '';
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'settings' => [
					'cleanup_job_data_on_failure' => $settings['cleanup_job_data_on_failure'] ?? true,
					'file_retention_days' => $settings['file_retention_days'] ?? 7,
					'global_system_prompt' => $settings['global_system_prompt'] ?? '',
					'site_context_enabled' => $settings['site_context_enabled'] ?? false,
					'default_provider' => $settings['default_provider'] ?? '',
					'default_model' => $settings['default_model'] ?? '',
					'max_turns' => $settings['max_turns'] ?? 12,
					'enabled_tools' => $settings['enabled_tools'] ?? [],
					'ai_provider_keys' => $masked_keys,
				],
				'global_tools' => $tools_keyed,
			]
		]);
	}

	/**
	 * Handle update settings request (partial update)
	 *
	 * Accepts any settings fields and merges them with existing settings.
	 *
	 * @param \WP_REST_Request $request
	 * @return WP_REST_Response|\WP_Error Updated settings or error
	 */
	public static function handle_update_settings($request) {
		$all_settings = get_option('datamachine_settings', []);
		$params = $request->get_json_params();

		// Handle each setting type
		if (isset($params['cleanup_job_data_on_failure'])) {
			$all_settings['cleanup_job_data_on_failure'] = (bool) $params['cleanup_job_data_on_failure'];
		}

		if (isset($params['file_retention_days'])) {
			$days = absint($params['file_retention_days']);
			$all_settings['file_retention_days'] = max(1, min(90, $days));
		}

		if (isset($params['global_system_prompt'])) {
			$all_settings['global_system_prompt'] = wp_kses_post($params['global_system_prompt']);
		}

		if (isset($params['site_context_enabled'])) {
			$all_settings['site_context_enabled'] = (bool) $params['site_context_enabled'];
		}

		if (isset($params['default_provider'])) {
			$all_settings['default_provider'] = sanitize_text_field($params['default_provider']);
		}

		if (isset($params['default_model'])) {
			$all_settings['default_model'] = sanitize_text_field($params['default_model']);
		}

		if (isset($params['max_turns'])) {
			$turns = absint($params['max_turns']);
			$all_settings['max_turns'] = max(1, min(50, $turns));
		}

		if (isset($params['enabled_tools'])) {
			$all_settings['enabled_tools'] = [];
			foreach ($params['enabled_tools'] as $tool_id => $enabled) {
				if ($enabled) {
					$all_settings['enabled_tools'][sanitize_key($tool_id)] = true;
				}
			}
		}

		// Handle AI provider API keys (stored separately via filter)
		if (isset($params['ai_provider_keys']) && is_array($params['ai_provider_keys'])) {
			$current_keys = apply_filters('chubes_ai_provider_api_keys', null);
			if (!is_array($current_keys)) {
				$current_keys = [];
			}
			foreach ($params['ai_provider_keys'] as $provider => $key) {
				$provider_key = sanitize_key($provider);
				$new_key = sanitize_text_field($key);

				// Only update if the key is not the masked version and not empty
				// If it contains asterisks, it's the masked version from the frontend
				if (strpos($new_key, '****') === false) {
					$current_keys[$provider_key] = $new_key;
				}
			}
			// Trigger the filter to save keys
			apply_filters('chubes_ai_provider_api_keys', $current_keys);
		}

		// Update the option
		$updated = update_option('datamachine_settings', $all_settings);
		PluginSettings::clearCache();

		return rest_ensure_response([
			'success' => true,
			'message' => __('Settings saved successfully.', 'data-machine'),
		]);
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
			'label' => __('Manual only', 'data-machine')
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

	/**
	 * Option name for handler defaults storage.
	 */
	const HANDLER_DEFAULTS_OPTION = 'datamachine_handler_defaults';

	/**
	 * Get all handler defaults grouped by step type.
	 *
	 * Auto-populates from schema defaults on first access.
	 *
	 * @return \WP_REST_Response Handler defaults response
	 */
	public static function handle_get_handler_defaults() {
		$defaults = get_option(self::HANDLER_DEFAULTS_OPTION, null);

		// Auto-populate from schema defaults on first access
		if ($defaults === null) {
			$defaults = self::build_initial_handler_defaults();
			update_option(self::HANDLER_DEFAULTS_OPTION, $defaults);
		}

		// Group defaults by step type for frontend convenience
		$handler_service = new HandlerService();
		$step_type_service = new StepTypeService();
		$step_types = $step_type_service->getAll();

		$grouped = [];
		foreach ($step_types as $step_type_slug => $step_type_config) {
			$uses_handler = $step_type_config['uses_handler'] ?? true;
			if (!$uses_handler) {
				$grouped[$step_type_slug] = [
					'label' => $step_type_config['label'] ?? $step_type_slug,
					'uses_handler' => false,
					'handlers' => [],
				];
				continue;
			}

			$handlers = $handler_service->getAll($step_type_slug);
			$handler_defaults = [];

			foreach ($handlers as $handler_slug => $handler_info) {
				$handler_defaults[$handler_slug] = [
					'label' => $handler_info['label'] ?? $handler_slug,
					'description' => $handler_info['description'] ?? '',
					'defaults' => $defaults[$handler_slug] ?? [],
					'fields' => $handler_service->getConfigFields($handler_slug),
				];
			}

			$grouped[$step_type_slug] = [
				'label' => $step_type_config['label'] ?? $step_type_slug,
				'uses_handler' => true,
				'handlers' => $handler_defaults,
			];
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $grouped
		]);
	}

	/**
	 * Update defaults for a specific handler.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Update response
	 */
	public static function handle_update_handler_defaults($request) {
		$handler_slug = $request->get_param('handler_slug');
		$new_defaults = $request->get_param('defaults');

		// Validate handler exists
		$handler_service = new HandlerService();
		$handler_info = $handler_service->get($handler_slug);

		if (!$handler_info) {
			return new \WP_Error(
				'handler_not_found',
				sprintf(__('Handler "%s" not found.', 'data-machine'), $handler_slug),
				['status' => 404]
			);
		}

		// Get existing defaults
		$all_defaults = get_option(self::HANDLER_DEFAULTS_OPTION, []);

		// Sanitize and merge new defaults
		$sanitized_defaults = self::sanitize_settings_array($new_defaults);
		$all_defaults[$handler_slug] = $sanitized_defaults;

		// Save
		$updated = update_option(self::HANDLER_DEFAULTS_OPTION, $all_defaults);

		if (!$updated && get_option(self::HANDLER_DEFAULTS_OPTION) !== $all_defaults) {
			return new \WP_Error(
				'update_failed',
				__('Failed to update handler defaults.', 'data-machine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'handler_slug' => $handler_slug,
				'defaults' => $sanitized_defaults,
				'message' => sprintf(__('Defaults updated for handler "%s".', 'data-machine'), $handler_slug),
			]
		]);
	}

	/**
	 * Build initial handler defaults from schema defaults.
	 *
	 * Iterates all registered handlers and extracts default values
	 * from their field definitions.
	 *
	 * @return array Handler defaults keyed by handler slug
	 */
	private static function build_initial_handler_defaults(): array {
		$handler_service = new HandlerService();
		$step_type_service = new StepTypeService();

		$defaults = [];
		$step_types = $step_type_service->getAll();

		foreach ($step_types as $step_type_slug => $step_type_config) {
			$uses_handler = $step_type_config['uses_handler'] ?? true;
			if (!$uses_handler) {
				continue;
			}

			$handlers = $handler_service->getAll($step_type_slug);

			foreach ($handlers as $handler_slug => $handler_info) {
				$fields = $handler_service->getConfigFields($handler_slug);
				$handler_defaults = [];

				foreach ($fields as $field_key => $field_config) {
					if (isset($field_config['default'])) {
						$handler_defaults[$field_key] = $field_config['default'];
					}
				}

				if (!empty($handler_defaults)) {
					$defaults[$handler_slug] = $handler_defaults;
				}
			}
		}

		return $defaults;
	}
}
