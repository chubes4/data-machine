<?php
/**
 * REST API Authentication Endpoint
 *
 * Provides REST API access to OAuth and authentication operations.
 * Enables programmatic authentication management for external integrations.
 * Requires WordPress manage_options capability.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use DataMachine\Services\AuthProviderService;
use DataMachine\Services\HandlerService;
use WP_REST_Server;

if (!defined('WPINC')) {
	die;
}

class Auth {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register /datamachine/v1/auth endpoints
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/auth/(?P<handler_slug>[a-zA-Z0-9_\-]+)', [
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [self::class, 'handle_disconnect_account'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'handler_slug' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('Handler identifier (e.g., twitter, facebook)', 'data-machine'),
					],
				]
			],
			[
				'methods' => 'PUT',
				'callback' => [self::class, 'handle_save_auth_config'],
				'permission_callback' => [self::class, 'check_permission'],
				'args' => [
					'handler_slug' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description' => __('Handler identifier', 'data-machine'),
					],
				]
			]
		]);

		register_rest_route('datamachine/v1', '/auth/(?P<handler_slug>[a-zA-Z0-9_\-]+)/status', [
			'methods' => 'GET',
			'callback' => [self::class, 'handle_check_oauth_status'],
			'permission_callback' => [self::class, 'check_permission'],
			'args' => [
				'handler_slug' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'description' => __('Handler identifier', 'data-machine'),
				],
			]
		]);


	}

	/**
	 * Check if user has permission to manage authentication
	 */
	public static function check_permission($request) {
		if (!current_user_can('manage_options')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to manage authentication.', 'data-machine'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Handle account disconnection request
	 *
	 * DELETE /datamachine/v1/auth/{handler_slug}
	 */
	public static function handle_disconnect_account($request) {
		$handler_slug = sanitize_text_field($request->get_param('handler_slug'));

		if (empty($handler_slug)) {
			return new \WP_Error(
				'missing_handler',
				__('Handler slug is required', 'data-machine'),
				['status' => 400]
			);
		}

		// Check if handler exists but doesn't require auth
		$handler_service = new HandlerService();
		$handler_info = $handler_service->get($handler_slug);
		if ($handler_info && ($handler_info['requires_auth'] ?? false) === false) {
			return new \WP_Error(
				'auth_not_required',
				__('Authentication is not required for this handler', 'data-machine'),
				['status' => 400]
			);
		}

		// Validate handler exists and supports authentication
		$auth_service = new AuthProviderService();
		$auth_instance = $auth_service->getForHandler($handler_slug);

		if (!$auth_instance) {
			return new \WP_Error(
				'auth_provider_not_found',
				__('Authentication provider not found', 'data-machine'),
				['status' => 404]
			);
		}

		// Clear OAuth credentials using centralized function
		if (method_exists($auth_instance, 'clear_account')) {
			$cleared = $auth_instance->clear_account();
		} else {
			return new \WP_Error(
				'disconnect_not_supported',
				__('This handler does not support account disconnection', 'data-machine'),
				['status' => 500]
			);
		}

		if ($cleared) {
			return rest_ensure_response([
				'success' => true,
				'data' => null,
				/* translators: %s: Service name (e.g., Twitter, Facebook) */
				'message' => sprintf(__('%s account disconnected successfully', 'data-machine'), ucfirst($handler_slug))
			]);
		} else {
			return new \WP_Error(
				'disconnect_failed',
				__('Failed to disconnect account', 'data-machine'),
				['status' => 500]
			);
		}
	}

	/**
	 * Handle OAuth status check request
	 *
	 * GET /datamachine/v1/auth/{handler_slug}/status
	 */
	public static function handle_check_oauth_status($request) {
		$handler_slug = sanitize_text_field($request->get_param('handler_slug'));

		if (empty($handler_slug)) {
			return new \WP_Error(
				'missing_handler',
				__('Handler slug is required', 'data-machine'),
				['status' => 400]
			);
		}

		// Check if handler exists and doesn't require auth
		$handler_service = new HandlerService();
		$handler_info = $handler_service->get($handler_slug);
		if ($handler_info && ($handler_info['requires_auth'] ?? false) === false) {
			return rest_ensure_response([
				'success' => true,
				'data' => [
					'authenticated' => true,
					'requires_auth' => false,
					'handler_slug' => $handler_slug,
					'message' => __('Authentication not required for this handler', 'data-machine')
				]
			]);
		}

		// Get auth provider instance via cached service
		$auth_service = new AuthProviderService();
		$auth_instance = $auth_service->getForHandler($handler_slug);


		if (!$auth_instance) {
			return new \WP_Error(
				'auth_provider_not_found',
				__('Authentication provider not found', 'data-machine'),
				['status' => 404]
			);
		}

		if (!method_exists($auth_instance, 'get_authorization_url')) {
			return new \WP_Error(
				'oauth_not_supported',
				__('This handler does not support OAuth authorization', 'data-machine'),
				['status' => 400]
			);
		}

		// Check configuration first
		if (method_exists($auth_instance, 'is_configured') && !$auth_instance->is_configured()) {
			return new \WP_Error(
				'oauth_not_configured',
				__('OAuth credentials not configured. Please provide client ID and secret first.', 'data-machine'),
				['status' => 400]
			);
		}

		try {
			$oauth_url = $auth_instance->get_authorization_url();
			
			return rest_ensure_response([
				'success' => true,
				'data' => [
					'oauth_url' => $oauth_url,
					'handler_slug' => $handler_slug,
					'instructions' => __('Visit this URL to authorize your account. You will be redirected back to Data Machine upon completion.', 'data-machine')
				]
			]);
		} catch (\Exception $e) {
			return new \WP_Error(
				'oauth_url_generation_failed',
				$e->getMessage(),
				['status' => 500]
			);
		}
	}

	/**
	 * Handle auth configuration save request
	 *
	 * PUT /datamachine/v1/auth/{handler_slug}
	 */
	public static function handle_save_auth_config($request) {
		$handler_slug = sanitize_text_field($request->get_param('handler_slug'));

		if (empty($handler_slug)) {
			return new \WP_Error(
				'missing_handler',
				__('Handler slug is required', 'data-machine'),
				['status' => 400]
			);
		}

		// Check if handler exists but doesn't require auth
		$handler_service = new HandlerService();
		$handler_info = $handler_service->get($handler_slug);
		if ($handler_info && ($handler_info['requires_auth'] ?? false) === false) {
			return new \WP_Error(
				'auth_not_required',
				__('Authentication is not required for this handler', 'data-machine'),
				['status' => 400]
			);
		}

		// Get auth provider instance via cached service
		$auth_service = new AuthProviderService();
		$auth_instance = $auth_service->getForHandler($handler_slug);

		if (!$auth_instance || !method_exists($auth_instance, 'get_config_fields')) {
			return new \WP_Error(
				'invalid_auth_provider',
				__('Auth provider not found or invalid', 'data-machine'),
				['status' => 404]
			);
		}

		// Get field definitions for validation
		$config_fields = $auth_instance->get_config_fields();
		$config_data = [];

		// OAuth providers: store to oauth_keys; simple auth: store to oauth_account
		$uses_oauth = method_exists($auth_instance, 'get_authorization_url') || method_exists($auth_instance, 'handle_oauth_callback');

		$existing_config = [];
		if (method_exists($auth_instance, 'get_config')) {
			$existing_config = $auth_instance->get_config();
		} elseif (method_exists($auth_instance, 'get_account')) {
			// Simple auth might store config in account data
			$existing_config = $auth_instance->get_account();
		} else {
			return new \WP_Error(
				'config_retrieval_failed',
				__('Could not retrieve existing configuration', 'data-machine'),
				['status' => 500]
			);
		}

		// Get all request parameters
		$request_params = $request->get_params();

		// Validate and sanitize each field
		foreach ($config_fields as $field_name => $field_config) {
			$value = sanitize_text_field($request_params[$field_name] ?? '');

			// Check required fields only if no existing config and value is empty
			if (($field_config['required'] ?? false) && empty($value) && empty($existing_config[$field_name] ?? '')) {
				return new \WP_Error(
					'required_field_missing',
					/* translators: %s: Field label (e.g., API Key, Client ID) */
					sprintf(__('%s is required', 'data-machine'), $field_config['label']),
					['status' => 400]
				);
			}

			// Use existing value if form value is empty (handles unchanged saves)
			if (empty($value) && !empty($existing_config[$field_name] ?? '')) {
				$value = $existing_config[$field_name];
			}

			$config_data[$field_name] = $value;
		}

		// Skip save if data unchanged
		if (!empty($existing_config)) {
			$data_changed = false;

			foreach ($config_data as $field_name => $new_value) {
				$existing_value = $existing_config[$field_name] ?? '';
				if ($new_value !== $existing_value) {
					$data_changed = true;
					break;
				}
			}

			if (!$data_changed) {
				return rest_ensure_response([
					'success' => true,
					'data' => null,
					'message' => __('Configuration is already up to date - no changes detected', 'data-machine')
				]);
			}
		}

		// OAuth: save API keys; Simple auth: save credentials
		if ($uses_oauth) {
			if (method_exists($auth_instance, 'save_config')) {
				$saved = $auth_instance->save_config($config_data);
			} else {
				return new \WP_Error('save_config_not_supported', __('Handler does not support saving config', 'data-machine'));
			}
		} else {
			if (method_exists($auth_instance, 'save_account')) {
				$saved = $auth_instance->save_account($config_data);
			} elseif (method_exists($auth_instance, 'save_config')) {
				// Some simple auth might use save_config (like Bluesky now)
				$saved = $auth_instance->save_config($config_data);
			} else {
				return new \WP_Error('save_account_not_supported', __('Handler does not support saving account', 'data-machine'));
			}
		}

		if ($saved) {
			return rest_ensure_response([
				'success' => true,
				'data' => null,
				'message' => __('Configuration saved successfully', 'data-machine')
			]);
		} else {
			return new \WP_Error(
				'save_failed',
				__('Failed to save configuration', 'data-machine'),
				['status' => 500]
			);
		}
	}
}
