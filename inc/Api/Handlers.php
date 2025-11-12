<?php
/**
 * Handlers REST API Endpoint
 *
 * Exposes registered handlers via REST API for frontend discovery.
 * Enables dynamic UI rendering based on available handlers per step type.
 *
 * @package DataMachine\Api
 * @since 0.1.2
 */

namespace DataMachine\Api;

use WP_REST_Server;
use WP_REST_Request;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handlers API Handler
 *
 * Provides REST endpoint for handler discovery and metadata.
 */
class Handlers {

	/**
	 * Register REST API routes
	 *
	 * @since 0.1.2
	 */
	public static function register_routes() {
		// List all handlers (basic info)
		register_rest_route('datamachine/v1', '/handlers', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_handlers'],
			'permission_callback' => '__return_true', // Public endpoint - handler info is not sensitive
			'args' => [
				'step_type' => [
					'required' => false,
					'type' => 'string',
					'enum' => ['fetch', 'publish', 'update'],
					'description' => __('Filter handlers by step type (fetch, publish, update)', 'datamachine')
				]
			]
		]);

		// Get complete handler details including settings schema and AI tool definition
		register_rest_route('datamachine/v1', '/handlers/(?P<handler_slug>[a-zA-Z0-9_-]+)', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_handler_detail'],
			'permission_callback' => '__return_true', // Public endpoint
			'args' => [
				'handler_slug' => [
					'required' => true,
					'type' => 'string',
					'description' => __('Handler slug (e.g., twitter, rss, wordpress_publish)', 'datamachine')
				]
			]
		]);
	}

	/**
	 * Get all registered handlers
	 *
	 * Returns handler metadata including types, labels, descriptions, and auth requirements.
	 * Optionally filter by step type using the step_type query parameter.
	 *
	 * @since 0.1.2
	 * @param WP_REST_Request $request Request object
	 * @return \WP_REST_Response Handlers response
	 */
	public static function handle_get_handlers($request) {
		// Get optional step_type filter
		$step_type = $request->get_param('step_type');

		// Get handlers via filter-based discovery
		// If step_type provided, filter returns only handlers for that type
		// If null, returns all handlers across all types
		$handlers = apply_filters('datamachine_handlers', [], $step_type);

		return rest_ensure_response([
			'success' => true,
			'handlers' => $handlers
		]);
	}

	/**
	 * Get complete handler details
	 *
	 * Returns comprehensive handler information including:
	 * - Basic info (type, label, description, requires_auth)
	 * - Settings schema (field definitions for configuration forms)
	 * - AI tool definition (parameters for AI integration)
	 *
	 * @since 0.1.2
	 * @param WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Handler details or error
	 */
	public static function handle_get_handler_detail($request) {
		$handler_slug = $request->get_param('handler_slug');

		// Get basic handler info from all handlers
		$all_handlers = apply_filters('datamachine_handlers', []);
		$handler_info = null;

		// Search across all step types to find the handler
		foreach ($all_handlers as $slug => $config) {
			if ($slug === $handler_slug) {
				$handler_info = $config;
				break;
			}
		}

		if (!$handler_info) {
			return new \WP_Error(
				'handler_not_found',
				__('Handler not found', 'datamachine'),
				['status' => 404]
			);
		}

		// Get settings schema
		$settings_schema = [];
		$all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);

		if (isset($all_settings[$handler_slug])) {
			$settings_class = $all_settings[$handler_slug];

			// Call get_fields() if method exists
			if (method_exists($settings_class, 'get_fields')) {
				$settings_schema = $settings_class::get_fields();
			}
		}

		// Get AI tool definition
		$ai_tool = null;
		$tools = apply_filters('ai_tools', [], $handler_slug, []);

		// Find the tool associated with this handler
		foreach ($tools as $tool_name => $tool_def) {
			if (($tool_def['handler'] ?? '') === $handler_slug) {
				$ai_tool = [
					'tool_name' => $tool_name,
					'description' => $tool_def['description'] ?? '',
					'parameters' => $tool_def['parameters'] ?? []
				];
				break;
			}
		}

		return rest_ensure_response([
			'success' => true,
			'handler' => [
				'slug' => $handler_slug,
				'info' => $handler_info,
				'settings' => $settings_schema,
				'ai_tool' => $ai_tool
			]
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [Handlers::class, 'register_routes']);
