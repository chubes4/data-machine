<?php
/**
 * Handles Reddit OAuth 2.0 Authorization Code Grant flow.
 *
 * Refactored to use centralized OAuth2Handler for standardized OAuth flow.
 * Maintains Reddit-specific logic (token refresh, user identity, API requirements).
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Reddit
 * @since      0.2.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

use DataMachine\Core\HttpClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RedditAuth extends \DataMachine\Core\OAuth\BaseOAuth2Provider {

	public function __construct() {
		parent::__construct( 'reddit' );
	}

	/**
	 * Get configuration fields required for Reddit authentication
	 *
	 * @return array Configuration field definitions
	 */
	public function get_config_fields(): array {
		return array(
			'client_id'          => array(
				'label'       => __( 'Client ID', 'data-machine' ),
				'type'        => 'text',
				'required'    => true,
				'description' => __( 'Your Reddit application Client ID from reddit.com/prefs/apps', 'data-machine' ),
			),
			'client_secret'      => array(
				'label'       => __( 'Client Secret', 'data-machine' ),
				'type'        => 'text',
				'required'    => true,
				'description' => __( 'Your Reddit application Client Secret from reddit.com/prefs/apps', 'data-machine' ),
			),
			'developer_username' => array(
				'label'       => __( 'Developer Username', 'data-machine' ),
				'type'        => 'text',
				'required'    => true,
				'description' => __( 'Your Reddit username that is registered in the Reddit app configuration', 'data-machine' ),
			),
		);
	}

	/**
	 * Get the authorization URL for Reddit OAuth
	 *
	 * @return string Authorization URL
	 */
	public function get_authorization_url(): string {
		$config    = $this->get_config();
		$client_id = $config['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			do_action(
				'datamachine_log',
				'error',
				'Reddit OAuth Error: Client ID not configured.',
				array(
					'handler'   => 'reddit',
					'operation' => 'get_authorization_url',
				)
			);
			return '';
		}

		// Create state via OAuth2Handler
		$state = $this->oauth2->create_state( 'reddit' );

		// Build authorization URL with Reddit-specific parameters
		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'state'         => $state,
			'redirect_uri'  => $this->get_callback_url(),
			'duration'      => 'permanent', // Reddit-specific: request refresh token
			'scope'         => 'identity read', // Reddit-specific scopes
		);

		return $this->oauth2->get_authorization_url( 'https://www.reddit.com/api/v1/authorize', $params );
	}

	/**
	 * Handle OAuth callback from Reddit
	 */
	public function handle_oauth_callback() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state parameter provides CSRF protection via OAuth2Handler
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		// Get configuration
		$config             = $this->get_config();
		$client_id          = $config['client_id'] ?? '';
		$client_secret      = $config['client_secret'] ?? '';
		$developer_username = $config['developer_username'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $developer_username ) ) {
			do_action( 'datamachine_log', 'error', 'Reddit OAuth Error: Missing configuration' );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'datamachine-settings',
						'auth_error' => 'missing_config',
						'provider'   => 'reddit',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Prepare token exchange parameters (Reddit-specific)
		$token_params = array(
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $this->get_callback_url(),
		);

		// Reddit requires Basic Auth for token exchange
		$token_params['headers'] = array(
			'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
			'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')',
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);

		// Use OAuth2Handler for token exchange and callback handling
		$this->oauth2->handle_callback(
			'reddit',
			'https://www.reddit.com/api/v1/access_token',
			$token_params,
			function ( $token_data ) use ( $developer_username ) {
				// Reddit-specific: Get user identity
				return $this->get_reddit_user_identity( $token_data, $developer_username );
			},
			null,
			array( $this, 'save_account' )
		);
	}

	/**
	 * Get Reddit user identity (Reddit-specific logic)
	 *
	 * @param array  $token_data Token data from Reddit
	 * @param string $developer_username Developer username for User-Agent
	 * @return array Account data
	 */
	private function get_reddit_user_identity( array $token_data, string $developer_username ): array {
		$access_token     = $token_data['access_token'];
		$refresh_token    = $token_data['refresh_token'] ?? null;
		$expires_in       = $token_data['expires_in'] ?? 3600;
		$scope_granted    = $token_data['scope'] ?? '';
		$token_expires_at = time() + intval( $expires_in );

		// Get user identity from Reddit API
		$identity_url    = 'https://oauth.reddit.com/api/v1/me';
		$identity_result = HttpClient::get(
			$identity_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')',
				),
				'context' => 'Reddit Authentication',
			)
		);

		$identity_username = null;
		if ( $identity_result['success'] && $identity_result['status_code'] === 200 ) {
			$identity_data     = json_decode( $identity_result['data'], true );
			$identity_username = $identity_data['name'] ?? null;

			if ( empty( $identity_username ) ) {
				do_action( 'datamachine_log', 'warning', 'Reddit OAuth Warning: Could not get username from /api/v1/me' );
			}
		} else {
			do_action( 'datamachine_log', 'warning', 'Reddit OAuth Warning: Failed to get user identity after token exchange' );
		}

		// Return account data for storage
		return array(
			'username'          => $identity_username,
			'access_token'      => $access_token,
			'refresh_token'     => $refresh_token,
			'token_expires_at'  => $token_expires_at,
			'scope'             => $scope_granted,
			'last_refreshed_at' => time(),
		);
	}

	/**
	 * Refresh Reddit access token (Reddit-specific logic)
	 *
	 * @return bool True on success
	 */
	public function refresh_token(): bool {
		do_action( 'datamachine_log', 'debug', 'Attempting Reddit token refresh' );

		$reddit_account = $this->get_account();
		if ( empty( $reddit_account['refresh_token'] ) ) {
			do_action( 'datamachine_log', 'error', 'Reddit Token Refresh Error: Refresh token not found' );
			return false;
		}

		$config             = $this->get_config();
		$client_id          = $config['client_id'] ?? '';
		$client_secret      = $config['client_secret'] ?? '';
		$developer_username = $config['developer_username'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $developer_username ) ) {
			do_action( 'datamachine_log', 'error', 'Reddit Token Refresh Error: Missing configuration' );
			return false;
		}

		// Reddit-specific token refresh request
		$token_url = 'https://www.reddit.com/api/v1/access_token';
		$result    = HttpClient::post(
			$token_url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')',
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $reddit_account['refresh_token'],
				),
				'context' => 'Reddit OAuth',
			)
		);

		if ( ! $result['success'] || $result['status_code'] !== 200 ) {
			do_action(
				'datamachine_log',
				'error',
				'Reddit Token Refresh Error: Request failed',
				array(
					'status_code' => $result['status_code'] ?? 'unknown',
					'error'       => $result['error'] ?? 'unknown',
				)
			);

			// Clear stored data if refresh token is invalid
			$this->clear_account();
			return false;
		}

		$data = json_decode( $result['data'], true );
		if ( empty( $data['access_token'] ) ) {
			do_action( 'datamachine_log', 'error', 'Reddit Token Refresh Error: No access token in response' );
			$this->clear_account();
			return false;
		}

		// Update account data with new tokens
		$updated_account_data = array(
			'username'          => $reddit_account['username'] ?? null,
			'access_token'      => $data['access_token'],
			'refresh_token'     => $data['refresh_token'] ?? $reddit_account['refresh_token'],
			'token_expires_at'  => time() + intval( $data['expires_in'] ?? 3600 ),
			'scope'             => $data['scope'] ?? $reddit_account['scope'] ?? '',
			'last_refreshed_at' => time(),
		);

		$this->save_account( $updated_account_data );
		do_action( 'datamachine_log', 'debug', 'Reddit token refreshed successfully' );
		return true;
	}

	/**
	 * Check if admin has valid Reddit authentication
	 *
	 * @return bool True if authenticated
	 */
	public function is_authenticated(): bool {
		$account = $this->get_account();
		return ! empty( $account ) &&
				is_array( $account ) &&
				! empty( $account['access_token'] ) &&
				! empty( $account['refresh_token'] );
	}

	/**
	 * Get Reddit account details
	 *
	 * @return array|null Account details or null if not authenticated
	 */
	public function get_account_details(): ?array {
		$account = $this->get_account();
		if ( empty( $account ) || ! is_array( $account ) || empty( $account['access_token'] ) ) {
			return null;
		}

		return $account;
	}
}
