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
			'step_types' => $step_types
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [StepTypes::class, 'register_routes']);
