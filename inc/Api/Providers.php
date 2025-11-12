<?php
/**
 * Providers REST API Endpoint
 *
 * Exposes AI provider metadata for frontend discovery.
 * Enables dynamic provider/model selection in AI configuration.
 *
 * @package DataMachine\Api
 * @since 0.1.3
 */

namespace DataMachine\Api;

use WP_REST_Server;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Providers API Handler
 *
 * Provides REST endpoint for AI provider discovery and metadata.
 */
class Providers {

	/**
	 * Register REST API routes
	 *
	 * @since 0.1.3
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/providers', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'handle_get_providers'],
			'permission_callback' => '__return_true', // Public endpoint
			'args' => []
		]);
	}

	/**
	 * Get all registered AI providers
	 *
	 * Returns provider metadata including labels and available models.
	 *
	 * @since 0.1.3
	 * @return \WP_REST_Response Providers response
	 */
	public static function handle_get_providers() {
		// Get providers from AI HTTP Client library
		$http_providers = apply_filters('ai_http_providers', []);

		$providers = [];
		foreach ($http_providers as $key => $provider_data) {
			$providers[$key] = [
				'label' => $provider_data['label'] ?? ucfirst($key),
				'models' => $provider_data['models'] ?? []
			];
		}

		return rest_ensure_response([
			'success' => true,
			'providers' => $providers
		]);
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [Providers::class, 'register_routes']);
