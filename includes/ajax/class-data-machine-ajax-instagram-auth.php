<?php
/**
 * Handles AJAX requests for Instagram authentication and account management.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Data_Machine_Ajax_Instagram_Auth {

	/**
	 * Registers AJAX actions.
	 */
	public static function register_hooks() {
		add_action( 'wp_ajax_dm_list_instagram_accounts', [ __CLASS__, 'list_accounts' ] );
		add_action( 'wp_ajax_dm_authenticate_instagram_account', [ __CLASS__, 'authenticate_account' ] );
		add_action( 'wp_ajax_dm_remove_instagram_account', [ __CLASS__, 'remove_account' ] );
		add_action( 'wp_ajax_dm_refresh_instagram_token', [ __CLASS__, 'refresh_token' ] );
		add_action( 'wp_ajax_dm_instagram_oauth_start', [ __CLASS__, 'oauth_start' ] );
		add_action( 'wp_ajax_nopriv_dm_instagram_oauth_start', [ __CLASS__, 'oauth_start' ] );
		add_action( 'wp_ajax_dm_instagram_oauth_callback', [ __CLASS__, 'oauth_callback' ] );
		add_action( 'wp_ajax_nopriv_dm_instagram_oauth_callback', [ __CLASS__, 'oauth_callback' ] );
	}

	/**
	 * Initiates the Instagram OAuth flow by redirecting to the Instagram authorization URL.
	 */
	public static function oauth_start() {
		// These should be stored in a secure settings location
		$client_id = get_option('instagram_oauth_client_id');
		$redirect_uri = admin_url('admin-ajax.php?action=dm_instagram_oauth_callback');
		$scope = 'user_profile,user_media';

		$auth_url = 'https://api.instagram.com/oauth/authorize'
			. '?client_id=' . urlencode($client_id)
			. '&redirect_uri=' . urlencode($redirect_uri)
			. '&scope=' . urlencode($scope)
			. '&response_type=code';

		wp_redirect($auth_url);
		exit;
		add_action( 'wp_ajax_dm_instagram_oauth_callback', [ __CLASS__, 'oauth_callback' ] );
	}

	/**
	 * Handles the Instagram OAuth callback, exchanges code for token, and stores account info.
	 */
	public static function oauth_callback() {
		$user_id = get_current_user_id();
		$client_id = get_option('instagram_oauth_client_id');
		$client_secret = get_option('instagram_oauth_client_secret');
		$redirect_uri = admin_url('admin-ajax.php?action=dm_instagram_oauth_callback');

		if (empty($_GET['code'])) {
			echo '<script>window.close();</script>';
			exit;
		}

		$code = sanitize_text_field($_GET['code']);

		// Exchange code for access token
		$response = wp_remote_post('https://api.instagram.com/oauth/access_token', [
			'body' => [
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $redirect_uri,
				'code' => $code,
			]
		]);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (empty($data['access_token']) || empty($data['user_id'])) {
			echo '<script>window.close();</script>';
			exit;
		}

		$access_token = $data['access_token'];
		$user_id_ig = $data['user_id'];

		// Fetch user info
		$user_info_response = wp_remote_get('https://graph.instagram.com/' . $user_id_ig . '?fields=id,username,account_type,media_count,profile_picture_url&access_token=' . urlencode($access_token));
		$user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);

		if (empty($user_info['id'])) {
			echo '<script>window.close();</script>';
			exit;
		}

		// Store in user meta
		$accounts = get_user_meta($user_id, 'data_machine_instagram_accounts', true);
		if (!is_array($accounts)) $accounts = [];
		$accounts[] = [
			'id' => $user_info['id'],
			'username' => $user_info['username'],
			'profile_pic' => esc_url_raw($user_info['profile_picture_url'] ?? ''),
			'access_token' => $access_token,
			'account_type' => $user_info['account_type'] ?? '',
			'media_count' => $user_info['media_count'] ?? 0,
			'expires_at' => isset($data['expires_in']) ? date('Y-m-d H:i:s', time() + intval($data['expires_in'])) : '',
		];
		update_user_meta($user_id, 'data_machine_instagram_accounts', $accounts);

		// Close popup and notify parent
		echo '<script>window.close();</script>';
		exit;
	}

	/**
	 * Lists authenticated Instagram accounts for the current user.
	 */
	public static function list_accounts() {
		check_ajax_referer( 'dm_instagram_auth_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$accounts = get_user_meta( $user_id, 'data_machine_instagram_accounts', true );
		if ( ! is_array( $accounts ) ) {
			$accounts = [];
		}
		// Prepare a safe list for the frontend (exclude token, sanitize fields)
		$safe_accounts_list = [];
		foreach ($accounts as $account) {
			$safe_accounts_list[] = [
				'id' => $account['id'] ?? null, // Assuming ID is safe (numeric from IG)
				'username' => isset($account['username']) ? sanitize_text_field($account['username']) : 'N/A',
				'profile_pic' => isset($account['profile_pic']) ? esc_url($account['profile_pic']) : '',
				'expires_at' => isset($account['expires_at']) ? sanitize_text_field($account['expires_at']) : '', // Date string, basic sanitization
			];
		}
		wp_send_json_success( [ 'accounts' => $safe_accounts_list ] );
	}

	/**
	 * Handles authentication of a new Instagram account (OAuth flow).
	 * (Stub: actual OAuth logic to be implemented in JS and backend)
	 */
	public static function authenticate_account() {
		check_ajax_referer( 'dm_instagram_auth_nonce', 'nonce' );
		// OAuth logic goes here (handled via JS popup and backend callback)
		wp_send_json_error( [ 'message' => 'OAuth flow not yet implemented.' ] );
	}

	/**
	 * Removes an authenticated Instagram account.
	 */
	public static function remove_account() {
		check_ajax_referer( 'dm_instagram_auth_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$account_id = sanitize_text_field( $_POST['account_id'] ?? '' );
		$accounts = get_user_meta( $user_id, 'data_machine_instagram_accounts', true );
		if ( ! is_array( $accounts ) ) {
			$accounts = [];
		}
		$accounts = array_filter( $accounts, function( $acct ) use ( $account_id ) {
			return ($acct['id'] ?? null) !== $account_id; // Check key exists before comparison
		} );
		update_user_meta( $user_id, 'data_machine_instagram_accounts', $accounts );

		// Send back the updated *safe* list after removal
		$safe_accounts_list = [];
		foreach (array_values($accounts) as $account) { // Re-index after filter might be needed depending on usage
			$safe_accounts_list[] = [
				'id' => $account['id'] ?? null,
				'username' => isset($account['username']) ? sanitize_text_field($account['username']) : 'N/A',
				'profile_pic' => isset($account['profile_pic']) ? esc_url($account['profile_pic']) : '',
				'expires_at' => isset($account['expires_at']) ? sanitize_text_field($account['expires_at']) : '',
			];
		}
		wp_send_json_success( [ 'accounts' => $safe_accounts_list ] );
	}

	/**
	 * Refreshes the access token for an Instagram account.
	 * (Stub: actual token refresh logic to be implemented)
	 */
	public static function refresh_token() {
		check_ajax_referer( 'dm_instagram_auth_nonce', 'nonce' );
		// Token refresh logic goes here
		wp_send_json_error( [ 'message' => 'Token refresh not yet implemented.' ] );
	}
}

// Register AJAX hooks
Data_Machine_Ajax_Instagram_Auth::register_hooks();