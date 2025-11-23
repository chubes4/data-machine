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
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $param ) {
						// Allow empty param for no filtering
						if (empty($param)) {
							return true;
						}
						$types = apply_filters('datamachine_step_types', []);
						$valid_step_types = is_array($types) ? array_keys($types) : [];
						return in_array($param, $valid_step_types, true);
					},
					'description' => __('Filter handlers by step type (supports custom step types)', 'datamachine')
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

		// Get auth providers to detect auth types
		$auth_providers = apply_filters('datamachine_auth_providers', []);

		// Enrich handler data with auth_type and auth_fields
		foreach ($handlers as $slug => &$handler) {
			if ($handler['requires_auth'] && isset($auth_providers[$slug])) {
				$auth_instance = $auth_providers[$slug];
				$handler['auth_type'] = self::detect_auth_type($auth_instance);

				// Add auth fields if available (regardless of auth type)
				if (method_exists($auth_instance, 'get_config_fields')) {
					$handler['auth_fields'] = $auth_instance->get_config_fields();
				}
			}
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $handlers
		]);
	}

	/**
	 * Detect authentication type from auth class instance.
	 *
	 * @param object $auth_instance Auth provider instance.
	 * @return string Auth type: 'oauth2', 'oauth1', or 'simple'.
	 */
	private static function detect_auth_type($auth_instance): string {
		if ($auth_instance instanceof \DataMachine\Core\OAuth\BaseOAuth2Provider) {
			return 'oauth2';
		}
		if ($auth_instance instanceof \DataMachine\Core\OAuth\BaseOAuth1Provider) {
			return 'oauth1';
		}
		
		// Default to simple auth for any other provider type (API Key, Basic Auth, etc.)
		return 'simple';
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

		// Get field state from backend (single source of truth)
		$settings_display_service = new \DataMachine\Core\Steps\Settings\SettingsDisplayService();
		$field_state = $settings_display_service->getFieldState($handler_slug);

		// Get AI tool definition
		$ai_tool = null;
		$tools = apply_filters('chubes_ai_tools', [], $handler_slug, []);

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
			'data' => [
				'slug' => $handler_slug,
				'info' => $handler_info,
				'settings' => $field_state,
				'ai_tool' => $ai_tool
			]
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [Handlers::class, 'register_routes']);
