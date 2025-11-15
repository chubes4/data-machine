<?php
/**
 * Providers REST API Endpoint
 *
 * Exposes AI provider metadata for frontend discovery.
 * Enables dynamic provider/model selection in AI configuration.
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
 * Providers API Handler
 *
 * Provides REST endpoint for AI provider discovery and metadata.
 */
class Providers {

	/**
	 * Register REST API routes
	 *
	 * @since 0.1.2
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
	 * @since 0.1.2
	 * @return \WP_REST_Response Providers response
	 */
 	public static function handle_get_providers() {
		try {
			// Use AI HTTP Client library's filters directly
			$library_providers = apply_filters('ai_providers', []);

			$providers = [];
			foreach ($library_providers as $key => $provider_info) {
				// Get models for this provider via filter
				$models = apply_filters('ai_models', $key);

				$providers[$key] = [
					'label' => $provider_info['name'] ?? ucfirst($key),
					'models' => $models
				];
			}

			// Get default settings
			$settings = get_option('datamachine_settings', []);
			$defaults = [
				'provider' => $settings['default_provider'] ?? '',
				'model' => $settings['default_model'] ?? ''
			];

			return rest_ensure_response([
				'success' => true,
				'providers' => $providers,
				'defaults' => $defaults
			]);

		} catch (\Exception $e) {
			do_action('datamachine_log', 'error', 'Failed to fetch AI providers from library', [
				'error' => $e->getMessage(),
				'exception' => $e
			]);

			return new \WP_Error(
				'providers_api_error',
				__('Failed to communicate with AI HTTP Client library.', 'datamachine'),
				['status' => 500]
			);
		}
	}
}

// Register routes on WordPress REST API initialization
add_action('rest_api_init', [Providers::class, 'register_routes']);
