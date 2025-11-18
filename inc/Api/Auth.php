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
						'description' => __('Handler identifier (e.g., twitter, facebook)', 'datamachine'),
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
						'description' => __('Handler identifier', 'datamachine'),
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
					'description' => __('Handler identifier', 'datamachine'),
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
				__('You do not have permission to manage authentication.', 'datamachine'),
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
				__('Handler slug is required', 'datamachine'),
				['status' => 400]
			);
		}

		// Validate handler exists and supports authentication
		$all_auth = apply_filters('datamachine_auth_providers', []);
		$auth_instance = $all_auth[$handler_slug] ?? null;

		if (!$auth_instance) {
			return new \WP_Error(
				'auth_provider_not_found',
				__('Authentication provider not found', 'datamachine'),
				['status' => 404]
			);
		}

		// Clear OAuth credentials using centralized filter
		$cleared = apply_filters('datamachine_clear_oauth_account', false, $handler_slug);

		if ($cleared) {
			do_action('datamachine_log', 'debug', 'Account disconnected successfully', [
				'handler_slug' => $handler_slug
			]);

			return [
				'success' => true,
				/* translators: %s: Service name (e.g., Twitter, Facebook) */
				'message' => sprintf(__('%s account disconnected successfully', 'datamachine'), ucfirst($handler_slug))
			];
		} else {
			do_action('datamachine_log', 'error', 'Failed to disconnect account', [
				'handler_slug' => $handler_slug
			]);

			return new \WP_Error(
				'disconnect_failed',
				__('Failed to disconnect account', 'datamachine'),
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
				__('Handler slug is required', 'datamachine'),
				['status' => 400]
			);
		}

		// Get auth provider instance
		$all_auth = apply_filters('datamachine_auth_providers', []);
		$auth_instance = $all_auth[$handler_slug] ?? null;

		if (!$auth_instance) {
			return new \WP_Error(
				'auth_provider_not_found',
				__('Authentication provider not found', 'datamachine'),
				['status' => 404]
			);
		}

		// Check authentication status
		$is_authenticated = $auth_instance->is_authenticated();

		if ($is_authenticated) {
			// Get account details for success response
			$account_details = null;
			if (method_exists($auth_instance, 'get_account_details')) {
				$account_details = $auth_instance->get_account_details();
			}

			return [
				'success' => true,
				'authenticated' => true,
				'account_details' => $account_details,
				'handler_slug' => $handler_slug
			];
		} else {
			// Check for recent OAuth errors stored in transients
			$error_transient = get_transient('datamachine_oauth_error_' . $handler_slug);
			$success_transient = get_transient('datamachine_oauth_success_' . $handler_slug);

			if ($error_transient) {
				// Clear the error transient since we're handling it
				delete_transient('datamachine_oauth_error_' . $handler_slug);

				return [
					'success' => true,
					'authenticated' => false,
					'error' => true,
					'error_code' => 'oauth_failed',
					'error_message' => $error_transient,
					'handler_slug' => $handler_slug
				];
			} elseif ($success_transient) {
				// Clear the success transient and re-check auth status
				delete_transient('datamachine_oauth_success_' . $handler_slug);

				// Force re-check authentication status as success transient might indicate completion
				$is_authenticated = $auth_instance->is_authenticated();

				if ($is_authenticated) {
					$account_details = null;
					if (method_exists($auth_instance, 'get_account_details')) {
						$account_details = $auth_instance->get_account_details();
					}

					return [
						'success' => true,
						'authenticated' => true,
						'account_details' => $account_details,
						'handler_slug' => $handler_slug
					];
				}
			}

			// Still not authenticated, continue polling
			return [
				'success' => true,
				'authenticated' => false,
				'error' => false,
				'handler_slug' => $handler_slug
			];
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
				__('Handler slug is required', 'datamachine'),
				['status' => 400]
			);
		}

		// Get auth provider instance to validate fields
		$all_auth = apply_filters('datamachine_auth_providers', []);
		$auth_instance = $all_auth[$handler_slug] ?? null;

		if (!$auth_instance || !method_exists($auth_instance, 'get_config_fields')) {
			return new \WP_Error(
				'invalid_auth_provider',
				__('Auth provider not found or invalid', 'datamachine'),
				['status' => 404]
			);
		}

		// Get field definitions for validation
		$config_fields = $auth_instance->get_config_fields();
		$config_data = [];

		// OAuth providers: store to oauth_keys; simple auth: store to oauth_account
		$uses_oauth = method_exists($auth_instance, 'get_authorization_url') || method_exists($auth_instance, 'handle_oauth_callback');

		$existing_config = $uses_oauth
			? datamachine_get_oauth_keys($handler_slug)
			: datamachine_get_oauth_account($handler_slug);

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
					sprintf(__('%s is required', 'datamachine'), $field_config['label']),
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
				return [
					'success' => true,
					'message' => __('Configuration is already up to date - no changes detected', 'datamachine')
				];
			}
		}

		// OAuth: save API keys; Simple auth: save credentials
		if ($uses_oauth) {
			$saved = apply_filters('datamachine_store_oauth_keys', $config_data, $handler_slug);
		} else {
			$saved = apply_filters('datamachine_store_oauth_account', $config_data, $handler_slug);
		}

		if ($saved) {
			return [
				'success' => true,
				'message' => __('Configuration saved successfully', 'datamachine')
			];
		} else {
			return new \WP_Error(
				'save_failed',
				__('Failed to save configuration', 'datamachine'),
				['status' => 500]
			);
		}
	}
}
