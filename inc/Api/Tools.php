<?php
/**
 * Tools REST API Endpoint
 *
 * Exposes general AI tools for frontend discovery.
 * Enables dynamic tool selection with configuration status.
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
 * Tools API Handler
 *
 * Provides REST endpoint for general AI tool discovery and configuration status.
 */
class Tools {

	/**
	 * Register REST API routes
	 *
	 * @since 0.1.2
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/tools', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_tools'],
			'permission_callback' => '__return_true', // Public endpoint
			'args' => []
		]);
	}

	/**
	 * Get all registered general AI tools
	 *
	 * Returns tool metadata including labels, descriptions, and configuration status.
	 * Filters to only global tools (excludes handler-specific tools).
	 *
	 * @since 0.1.2
	 * @return \WP_REST_Response Tools response
	 */
	public static function handle_get_tools() {
		$tool_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
		$tools = $tool_manager->get_tools_for_api();

		return rest_ensure_response([
			'success' => true,
			'data' => $tools
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [Tools::class, 'register_routes']);
