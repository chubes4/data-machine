<?php
/**
 * REST API Flow Steps Endpoint
 *
 * Provides REST API access to flow step configuration operations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api\Flows
 */

namespace DataMachine\Api\Flows;

use DataMachine\Services\FlowStepManager;

if (!defined('WPINC')) {
	die;
}

class FlowSteps {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register flow step configuration endpoints
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/flows/(?P<flow_id>\d+)/config', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_flow_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Flow ID to retrieve configuration for', 'datamachine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/config', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_get_flow_step_config'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'datamachine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/handler', [
			'methods' => 'PUT',
			'callback' => [self::class, 'handle_update_flow_step_handler'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'datamachine'),
				],
				'handler_slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Handler identifier', 'datamachine'),
				],
				'pipeline_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'description' => __('Pipeline ID for context', 'datamachine'),
				],
				'step_type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $param ) {
						$types = apply_filters('datamachine_step_types', []);
						$valid_step_types = is_array($types) ? array_keys($types) : [];
						return in_array($param, $valid_step_types, true);
					},
					'description' => __('Step type', 'datamachine'),
				],
			]
		]);

		register_rest_route('datamachine/v1', '/flows/steps/(?P<flow_step_id>[A-Za-z0-9_\-]+)/user-message', [
			'methods' => 'PATCH',
			'callback' => [self::class, 'handle_update_user_message'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_step_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Flow step ID (composite key: pipeline_step_id_flow_id)', 'datamachine'),
				],
				'user_message' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'description' => __('User message for AI step', 'datamachine'),
				],
			]
		]);
	}

	/**
	 * Check if user has permission to manage flow steps
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage flow steps.', 'datamachine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle flow configuration retrieval request
	 */
	public static function handle_get_flow_config($request) {
		$flow_id = (int) $request->get_param('flow_id');

		// Retrieve flow data via filter
		$db_flows = new \DataMachine\Core\Database\Flows\Flows();
		$flow = $db_flows->get_flow($flow_id);

		if (!$flow) {
			return new \WP_Error(
				'flow_not_found',
				__('Flow not found.', 'datamachine'),
				['status' => 404]
			);
		}

		$flow_config = $flow['flow_config'] ?? [];

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'flow_id' => $flow_id,
				'flow_config' => $flow_config
			]
		]);
	}

	/**
	 * Handle flow step configuration retrieval request
	 */
	public static function handle_get_flow_step_config($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));

		if (empty($flow_step_id)) {
			return new \WP_Error(
				'invalid_flow_step_id',
				__('Flow step ID is required.', 'datamachine'),
				['status' => 400]
			);
		}

		$manager = new FlowStepManager();
		$step_config = $manager->get($flow_step_id);

		if (empty($step_config)) {
			return new \WP_Error(
				'flow_step_not_found',
				__('Flow step configuration not found.', 'datamachine'),
				['status' => 404]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'flow_step_id' => $flow_step_id,
				'step_config' => $step_config
			]
		]);
	}

	/**
	 * Handle flow step handler settings update
	 *
	 * PUT /datamachine/v1/flows/steps/{flow_step_id}/handler
	 */
	public static function handle_update_flow_step_handler($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));
		$handler_slug = sanitize_text_field($request->get_param('handler_slug'));
		$step_type = sanitize_text_field($request->get_param('step_type'));

		if (empty($handler_slug) || empty($flow_step_id)) {
			return new \WP_Error(
				'missing_required_fields',
				__('Handler slug and flow step ID are required.', 'datamachine'),
				['status' => 400]
			);
		}

		$all_handlers = apply_filters('datamachine_handlers', [], $step_type);
		$handler_info = null;

		foreach ($all_handlers as $slug => $config) {
			if ($slug === $handler_slug) {
				$handler_info = $config;
				break;
			}
		}

		if (!$handler_info) {
			return new \WP_Error(
				'handler_not_found',
				__('Handler not found.', 'datamachine'),
				['status' => 404]
			);
		}

		$handler_settings = self::process_handler_settings($handler_slug, $request->get_params());

		$parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
		if (!isset($parts['flow_id']) || !isset($parts['pipeline_step_id'])) {
			return new \WP_Error(
				'invalid_flow_step_id',
				__('Invalid flow step ID format.', 'datamachine'),
				['status' => 400]
			);
		}
		$flow_id = $parts['flow_id'];
		$pipeline_step_id = $parts['pipeline_step_id'];

		try {
			$manager = new FlowStepManager();
			$success = $manager->updateHandler($flow_step_id, $handler_slug, $handler_settings);

			if (!$success) {
				return new \WP_Error(
					'update_failed',
					__('Failed to update handler for flow step', 'datamachine'),
					['status' => 500]
				);
			}

			$step_config = [
				'step_type' => $step_type,
				'handler_slug' => $handler_slug,
				'handler_config' => $handler_settings,
				'enabled' => true,
				'flow_id' => $flow_id,
				'pipeline_step_id' => $pipeline_step_id,
				'flow_step_id' => $flow_step_id
			];

			$db_flows = new \DataMachine\Core\Database\Flows\Flows();
			$flow = $db_flows->get_flow($flow_id);
			$flow_config = $flow['flow_config'] ?? [];
			$existing_step = $flow_config[$flow_step_id] ?? [];
			if (isset($existing_step['execution_order'])) {
				$step_config['execution_order'] = $existing_step['execution_order'];
			}

			$service = new \DataMachine\Core\Steps\Settings\SettingsDisplayService();
			$handler_settings_display = $service->getFieldState($handler_slug, $handler_settings);

            $message = sprintf(
                /* translators: %s: handler label */
                esc_html__('Handler "%s" settings saved successfully.', 'datamachine'),
                $handler_info['label'] ?? $handler_slug
            );


			return rest_ensure_response([
				'success' => true,
				'data' => [
					'handler_slug' => $handler_slug,
					'step_type' => $step_type,
					'flow_step_id' => $flow_step_id,
					'flow_id' => $flow_id,
					'pipeline_step_id' => $pipeline_step_id,
					'step_config' => $step_config,
					'handler_settings_display' => $handler_settings_display
				],
				'message' => $message
			]);

		} catch (\Exception $e) {
			return new \WP_Error(
				'handler_update_failed',
				__('Failed to save handler settings due to server error.', 'datamachine'),
				['status' => 500]
			);
		}
	}

	/**
	 * Handle user message update for AI flow steps
	 *
	 * PATCH /datamachine/v1/flows/steps/{flow_step_id}/user-message
	 */
	public static function handle_update_user_message($request) {
		$flow_step_id = sanitize_text_field($request->get_param('flow_step_id'));
		$user_message = sanitize_textarea_field($request->get_param('user_message'));

		$manager = new FlowStepManager();
		$success = $manager->updateUserMessage($flow_step_id, $user_message);

		if (!$success) {
			return new \WP_Error(
				'update_failed',
				__('Failed to update user message.', 'datamachine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [],
			'message' => __('User message saved successfully', 'datamachine')
		]);
	}

	/**
	 * Process handler settings from request parameters
	 *
	 * @param string $handler_slug Handler identifier
	 * @param array $params Request parameters
	 * @return array Sanitized handler settings
	 */
	private static function process_handler_settings($handler_slug, $params) {
		$all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
		$handler_settings = $all_settings[$handler_slug] ?? null;

		if (!$handler_settings || !method_exists($handler_settings, 'sanitize')) {
			return [];
		}

		$raw_settings = $params['settings'] ?? [];

		if (!is_array($raw_settings)) {
			$raw_settings = [];
		}

		try {
			return $handler_settings->sanitize($raw_settings);
		} catch (\Exception $e) {
			return [];
		}
	}
}
