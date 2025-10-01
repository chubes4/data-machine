<?php
/**
 * REST API Trigger Endpoint
 *
 * Provides REST API access to flow execution via dm_run_flow_now action.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Engine\Rest
 */

namespace DataMachine\Engine\Rest;

if (!defined('WPINC')) {
	die;
}

class Trigger {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register /dm/v1/trigger endpoint
	 */
	public static function register_routes() {
		register_rest_route('dm/v1', '/trigger', [
			'methods' => 'POST',
			'callback' => [self::class, 'handle_trigger'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'flow_id' => [
					'required' => true,
					'type' => 'integer',
					'description' => __('Flow ID to trigger', 'data-machine'),
					'validate_callback' => function($param) {
						return is_numeric($param) && $param > 0;
					}
				]
			]
		]);
	}

	/**
	 * Check if user has permission to trigger flows
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to trigger flows.', 'data-machine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle trigger request
	 */
	public static function handle_trigger($request) {
		$flow_id = $request->get_param('flow_id');

		// Validate flow exists
		$flow = apply_filters('dm_get_flow', null, $flow_id);
		if (!$flow) {
			return new \WP_Error(
				'invalid_flow',
				__('Flow not found.', 'data-machine'),
				['status' => 404]
			);
		}

		// Trigger flow execution (works for any flow - scheduled or manual)
		do_action('dm_run_flow_now', $flow_id, 'rest_api_trigger');

		do_action('dm_log', 'info', 'Flow triggered via REST API', [
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login
		]);

		return [
			'success' => true,
			'flow_id' => $flow_id,
			'flow_name' => $flow['flow_name'] ?? '',
			'message' => __('Flow triggered successfully.', 'data-machine')
		];
	}
}