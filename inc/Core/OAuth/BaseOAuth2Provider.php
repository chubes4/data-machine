<?php
/**
 * Base OAuth 2.0 Provider Class
 *
 * Abstract base class for OAuth 2.0 providers to reduce code duplication.
 * Standardizes configuration, authentication checks, and callback handling.
 *
 * @package DataMachine
 * @subpackage Core\OAuth
 * @since 0.2.0
 */

namespace DataMachine\Core\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseOAuth2Provider extends BaseAuthProvider {

	/**
	 * @var OAuth2Handler OAuth2 handler instance
	 */
	protected $oauth2;

	/**
	 * Constructor
	 *
	 * @param string $provider_slug Provider identifier
	 */
	public function __construct( string $provider_slug ) {
		parent::__construct( $provider_slug );
		$this->oauth2 = new OAuth2Handler();
	}

	/**
	 * Check if provider is properly configured
	 *
	 * @return bool True if configured
	 */
	public function is_configured(): bool {
		$config = $this->get_config();
		// Default check: client_id and client_secret exist
		// Can be overridden by child classes if keys differ (e.g. app_id vs client_id)
		return ! empty( $config['client_id'] ) && ! empty( $config['client_secret'] );
	}

	/**
	 * Check if authenticated
	 *
	 * @return bool True if authenticated
	 */
	public function is_authenticated(): bool {
		$account = $this->get_account();
		return ! empty( $account ) &&
				is_array( $account ) &&
				! empty( $account['access_token'] );
	}

	/**
	 * Get account details for display
	 *
	 * @return array|null Account details or null
	 */
	public function get_account_details(): ?array {
		$account = $this->get_account();
		if ( empty( $account ) || empty( $account['access_token'] ) ) {
			return null;
		}

		$details = array();
		if ( ! empty( $account['username'] ) ) {
			$details['username'] = $account['username'];
		}
		if ( ! empty( $account['name'] ) ) {
			$details['name'] = $account['name'];
		}
		if ( ! empty( $account['scope'] ) ) {
			$details['scope'] = $account['scope'];
		}
		if ( ! empty( $account['last_refreshed_at'] ) ) {
			$details['last_refreshed'] = wp_date( 'Y-m-d H:i:s', $account['last_refreshed_at'] );
		}

		return $details;
	}

	/**
	 * Get configuration fields (Abstract)
	 *
	 * @return array Configuration field definitions
	 */
	abstract public function get_config_fields(): array;

	/**
	 * Get authorization URL (Abstract or default implementation)
	 *
	 * @return string Authorization URL
	 */
	abstract public function get_authorization_url(): string;

	/**
	 * Handle OAuth callback (Abstract or default implementation)
	 */
	abstract public function handle_oauth_callback();

	/**
	 * Refresh token (Optional)
	 *
	 * @return bool True on success
	 */
	public function refresh_token(): bool {
		return false;
	}
}
