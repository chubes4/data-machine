<?php
/**
 * Step Types REST API Endpoint
 *
 * Exposes registered step types via REST API for frontend discovery.
 * Enables dynamic UI rendering based on step type metadata.
 *
 * @package DataMachine\Api
 * @since 0.1.2
 */

namespace DataMachine\Api;

use WP_REST_Server;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Step Types API Handler
 *
 * Provides REST endpoint for step type discovery and metadata.
 */
class StepTypes {

	/**
	 * Register REST API routes
	 *
	 * @since 0.1.2
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/step-types', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_step_types'],
			'permission_callback' => '__return_true', // Public endpoint - step type info is not sensitive
			'args' => []
		]);

		register_rest_route('datamachine/v1', '/step-types/(?P<step_type>[a-zA-Z0-9_-]+)', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_step_type_detail'],
			'permission_callback' => '__return_true',
			'args' => [
				'step_type' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Step type slug', 'datamachine'),
					'sanitize_callback' => 'sanitize_key'
				]
			]
		]);
	}

	/**
	 * Get all registered step types
	 *
	 * Returns step type metadata including labels, descriptions, positions, and handler requirements.
	 *
	 * @since 0.1.2
	 * @return \WP_REST_Response Step types response
	 */
	public static function handle_get_step_types() {
		// Get all registered step types via filter-based discovery
		$step_types = apply_filters('datamachine_step_types', []);

		return rest_ensure_response([
			'success' => true,
			'data' => $step_types
		]);
	}

	/**
	 * Get full metadata for a specific step type
	 *
	 * Exposes the registered step definition along with pipeline-level
	 * configuration metadata (if provided by the step).
	 *
	 * @since 0.1.2
	 * @param \WP_REST_Request $request Request instance
	 * @return \WP_REST_Response|\WP_Error Step type detail response
	 */
	public static function handle_get_step_type_detail($request) {
		$step_type = $request->get_param('step_type');

		$step_types = apply_filters('datamachine_step_types', []);

		if (!isset($step_types[$step_type])) {
			return new \WP_Error(
				'step_type_not_found',
				__('Step type not found', 'datamachine'),
				['status' => 404]
			);
		}

		$step_settings = apply_filters('datamachine_step_settings', []);
		$config = $step_settings[$step_type] ?? null;

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'step_type' => $step_type,
				'definition' => $step_types[$step_type],
				'config' => $config
			]
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [StepTypes::class, 'register_routes']);
