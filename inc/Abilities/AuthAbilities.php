<?php
/**
 * Auth Abilities
 *
 * WordPress 6.9 Abilities API primitives for authentication operations.
 * Centralizes OAuth status, disconnect, and configuration saving.
 *
 * @package DataMachine\Abilities
 */

namespace DataMachine\Abilities;

use DataMachine\Services\AuthProviderService;
use DataMachine\Services\HandlerService;

defined( 'ABSPATH' ) || exit;

class AuthAbilities {

	private AuthProviderService $auth_service;
	private HandlerService $handler_service;

	public function __construct() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		$this->auth_service    = new AuthProviderService();
		$this->handler_service = new HandlerService();
		$this->registerAbilities();
	}

	private function registerAbilities(): void {
		add_action(
			'wp_abilities_api_init',
			function () {
				$this->registerGetAuthStatus();
				$this->registerDisconnectAuth();
				$this->registerSaveAuthConfig();
			}
		);
	}

	private function registerGetAuthStatus(): void {
		wp_register_ability(
			'datamachine/get-auth-status',
			array(
				'label'               => __( 'Get Auth Status', 'data-machine' ),
				'description'         => __( 'Get OAuth/authentication status for a handler including authorization URL if applicable.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'handler_slug' ),
					'properties' => array(
						'handler_slug' => array(
							'type'        => 'string',
							'description' => __( 'Handler identifier (e.g., twitter, facebook)', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'authenticated' => array( 'type' => 'boolean' ),
						'requires_auth' => array( 'type' => 'boolean' ),
						'handler_slug'  => array( 'type' => 'string' ),
						'oauth_url'     => array( 'type' => 'string' ),
						'message'       => array( 'type' => 'string' ),
						'instructions'  => array( 'type' => 'string' ),
						'error'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetAuthStatus' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerDisconnectAuth(): void {
		wp_register_ability(
			'datamachine/disconnect-auth',
			array(
				'label'               => __( 'Disconnect Auth', 'data-machine' ),
				'description'         => __( 'Disconnect/revoke authentication for a handler.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'handler_slug' ),
					'properties' => array(
						'handler_slug' => array(
							'type'        => 'string',
							'description' => __( 'Handler identifier (e.g., twitter, facebook)', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeDisconnectAuth' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	private function registerSaveAuthConfig(): void {
		wp_register_ability(
			'datamachine/save-auth-config',
			array(
				'label'               => __( 'Save Auth Config', 'data-machine' ),
				'description'         => __( 'Save authentication configuration for a handler.', 'data-machine' ),
				'category'            => 'datamachine',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'handler_slug' ),
					'properties' => array(
						'handler_slug' => array(
							'type'        => 'string',
							'description' => __( 'Handler identifier', 'data-machine' ),
						),
						'config'       => array(
							'type'        => 'object',
							'description' => __( 'Configuration key-value pairs to save', 'data-machine' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'executeSaveAuthConfig' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	public function checkPermission(): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}

	public function executeGetAuthStatus( array $input ): array {
		$handler_slug = sanitize_text_field( $input['handler_slug'] ?? '' );

		if ( empty( $handler_slug ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Handler slug is required', 'data-machine' ),
			);
		}

		$handler_info = $this->handler_service->get( $handler_slug );
		if ( $handler_info && ( $handler_info['requires_auth'] ?? false ) === false ) {
			return array(
				'success'       => true,
				'authenticated' => true,
				'requires_auth' => false,
				'handler_slug'  => $handler_slug,
				'message'       => __( 'Authentication not required for this handler', 'data-machine' ),
			);
		}

		$auth_instance = $this->auth_service->getForHandler( $handler_slug );

		if ( ! $auth_instance ) {
			return array(
				'success' => false,
				'error'   => __( 'Authentication provider not found', 'data-machine' ),
			);
		}

		if ( ! method_exists( $auth_instance, 'get_authorization_url' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This handler does not support OAuth authorization', 'data-machine' ),
			);
		}

		if ( method_exists( $auth_instance, 'is_configured' ) && ! $auth_instance->is_configured() ) {
			return array(
				'success' => false,
				'error'   => __( 'OAuth credentials not configured. Please provide client ID and secret first.', 'data-machine' ),
			);
		}

		try {
			$oauth_url = $auth_instance->get_authorization_url();

			return array(
				'success'       => true,
				'oauth_url'     => $oauth_url,
				'handler_slug'  => $handler_slug,
				'requires_auth' => true,
				'instructions'  => __( 'Visit this URL to authorize your account. You will be redirected back to Data Machine upon completion.', 'data-machine' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	public function executeDisconnectAuth( array $input ): array {
		$handler_slug = sanitize_text_field( $input['handler_slug'] ?? '' );

		if ( empty( $handler_slug ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Handler slug is required', 'data-machine' ),
			);
		}

		$handler_info = $this->handler_service->get( $handler_slug );
		if ( $handler_info && ( $handler_info['requires_auth'] ?? false ) === false ) {
			return array(
				'success' => false,
				'error'   => __( 'Authentication is not required for this handler', 'data-machine' ),
			);
		}

		$auth_instance = $this->auth_service->getForHandler( $handler_slug );

		if ( ! $auth_instance ) {
			return array(
				'success' => false,
				'error'   => __( 'Authentication provider not found', 'data-machine' ),
			);
		}

		if ( ! method_exists( $auth_instance, 'clear_account' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This handler does not support account disconnection', 'data-machine' ),
			);
		}

		$cleared = $auth_instance->clear_account();

		if ( $cleared ) {
			return array(
				'success' => true,
				/* translators: %s: Service name (e.g., Twitter, Facebook) */
				'message' => sprintf( __( '%s account disconnected successfully', 'data-machine' ), ucfirst( $handler_slug ) ),
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'Failed to disconnect account', 'data-machine' ),
		);
	}

	public function executeSaveAuthConfig( array $input ): array {
		$handler_slug = sanitize_text_field( $input['handler_slug'] ?? '' );
		$config_input = $input['config'] ?? array();

		if ( empty( $handler_slug ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Handler slug is required', 'data-machine' ),
			);
		}

		$handler_info = $this->handler_service->get( $handler_slug );
		if ( $handler_info && ( $handler_info['requires_auth'] ?? false ) === false ) {
			return array(
				'success' => false,
				'error'   => __( 'Authentication is not required for this handler', 'data-machine' ),
			);
		}

		$auth_instance = $this->auth_service->getForHandler( $handler_slug );

		if ( ! $auth_instance || ! method_exists( $auth_instance, 'get_config_fields' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Auth provider not found or invalid', 'data-machine' ),
			);
		}

		$config_fields = $auth_instance->get_config_fields();
		$config_data   = array();

		$uses_oauth = method_exists( $auth_instance, 'get_authorization_url' ) || method_exists( $auth_instance, 'handle_oauth_callback' );

		$existing_config = array();
		if ( method_exists( $auth_instance, 'get_config' ) ) {
			$existing_config = $auth_instance->get_config();
		} elseif ( method_exists( $auth_instance, 'get_account' ) ) {
			$existing_config = $auth_instance->get_account();
		} else {
			return array(
				'success' => false,
				'error'   => __( 'Could not retrieve existing configuration', 'data-machine' ),
			);
		}

		foreach ( $config_fields as $field_name => $field_config ) {
			$value = sanitize_text_field( $config_input[ $field_name ] ?? '' );

			if ( ( $field_config['required'] ?? false ) && empty( $value ) && empty( $existing_config[ $field_name ] ?? '' ) ) {
				return array(
					'success' => false,
					/* translators: %s: Field label (e.g., API Key, Client ID) */
					'error'   => sprintf( __( '%s is required', 'data-machine' ), $field_config['label'] ),
				);
			}

			if ( empty( $value ) && ! empty( $existing_config[ $field_name ] ?? '' ) ) {
				$value = $existing_config[ $field_name ];
			}

			$config_data[ $field_name ] = $value;
		}

		if ( ! empty( $existing_config ) ) {
			$data_changed = false;

			foreach ( $config_data as $field_name => $new_value ) {
				$existing_value = $existing_config[ $field_name ] ?? '';
				if ( $new_value !== $existing_value ) {
					$data_changed = true;
					break;
				}
			}

			if ( ! $data_changed ) {
				return array(
					'success' => true,
					'message' => __( 'Configuration is already up to date - no changes detected', 'data-machine' ),
				);
			}
		}

		if ( $uses_oauth ) {
			if ( method_exists( $auth_instance, 'save_config' ) ) {
				$saved = $auth_instance->save_config( $config_data );
			} else {
				return array(
					'success' => false,
					'error'   => __( 'Handler does not support saving config', 'data-machine' ),
				);
			}
		} elseif ( method_exists( $auth_instance, 'save_account' ) ) {
			$saved = $auth_instance->save_account( $config_data );
		} elseif ( method_exists( $auth_instance, 'save_config' ) ) {
			$saved = $auth_instance->save_config( $config_data );
		} else {
			return array(
				'success' => false,
				'error'   => __( 'Handler does not support saving account', 'data-machine' ),
			);
		}

		if ( $saved ) {
			return array(
				'success' => true,
				'message' => __( 'Configuration saved successfully', 'data-machine' ),
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'Failed to save configuration', 'data-machine' ),
		);
	}
}
