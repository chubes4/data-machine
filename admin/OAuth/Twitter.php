<?php
/**
 * Handles Twitter OAuth 1.0a flow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/oauth
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Abraham\TwitterOAuth\TwitterOAuth;
use DataMachine\Helpers\Logger;

class Twitter {

    /**
	 * Logger instance.
	 * @var ?Logger
	 */
	private $logger;

    const OAUTH_CALLBACK_ACTION = 'dm_twitter_oauth_callback';
    const TEMP_TOKEN_SECRET_TRANSIENT_PREFIX = 'dm_twitter_req_secret_'; // Prefix + request_token
    const USER_META_KEY = 'data_machine_twitter_account';

    /**
	 * Initialize hooks and dependencies.
	 *
	 * @param Logger|null $logger Optional Logger instance.
	 */
	public function __construct(?Logger $logger = null) {
		$this->logger = $logger;
		// Hooks should be registered where this class is instantiated.
	}

    /**
     * Registers the necessary WordPress action hooks.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_twitter_oauth_init', array($this, 'handle_oauth_init'));
        add_action('admin_post_' . self::OAUTH_CALLBACK_ACTION, array($this, 'handle_oauth_callback'));
        // Note: Unlike Reddit, Twitter OAuth 1.0a callback doesn't typically work if logged out
        // as the initial state isn't easily preserved without custom handling.
    }

    /**
     * Handles the initiation of the Twitter OAuth flow.
     * Hooked to 'admin_post_dm_twitter_oauth_init'.
     */
    public function handle_oauth_init() {
        // 1. Verify Nonce & Capability
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'dm_twitter_oauth_init_nonce')) {
            wp_die('Security check failed (Nonce mismatch). Please try initiating the connection again from the API Keys page.', 'data-machine');
        }
        if (!current_user_can('manage_options')) { // Use appropriate capability
             wp_die('Permission denied.', 'data-machine');
        }

        // 2. Get API Key/Secret
        $apiKey = get_option('twitter_api_key');
        $apiSecret = get_option('twitter_api_secret');
        if (empty($apiKey) || empty($apiSecret)) {
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_missing_app_keys'));
            exit;
        }

        // 4. Define Callback URL
        $callback_url = admin_url('admin-post.php?action=' . self::OAUTH_CALLBACK_ACTION);

        try {
            // 3. Instantiate TwitterOAuth
            $connection = new TwitterOAuth($apiKey, $apiSecret);

            // 5. Get Request Token from Twitter API
            $request_token = $connection->oauth('oauth/request_token', ['oauth_callback' => $callback_url]);

            // 6. Check for errors from Twitter
            if ($connection->getLastHttpCode() != 200 || !isset($request_token['oauth_token']) || !isset($request_token['oauth_token_secret'])) {
                $error_message = 'Failed to get request token from Twitter.';
                $response_info = $connection->getLastXHeaders(); // Or other debug info
                $this->logger?->error('Twitter OAuth Error: ' . $error_message, [
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody(), // Log response body if available
                    'headers' => $response_info
                ]);
                wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_request_token_failed'));
                exit;
            }

            // 7. Store Request Token Secret temporarily
            // Use the oauth_token as part of the transient key
            set_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $request_token['oauth_token'], $request_token['oauth_token_secret'], 15 * MINUTE_IN_SECONDS); // 15 min expiry

            // 8. Build Authorization URL
            // Use oauth/authenticate - allows users to skip authorization if already granted
            $url = $connection->url('oauth/authenticate', ['oauth_token' => $request_token['oauth_token']]);

            // 9. Redirect user to Twitter
            wp_redirect($url);
            exit;

        } catch (\Exception $e) {
            $this->logger?->error('Twitter OAuth Exception during init: ' . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_init_exception'));
            exit;
        }
    }

    /**
     * Handles the callback from Twitter after user authorization.
     * Hooked to 'admin_post_dm_twitter_oauth_callback'.
     */
    public function handle_oauth_callback() {
        // --- 1. Initial Checks --- 
        if ( !is_user_logged_in() ) {
             wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_not_logged_in'));
             exit;
        }
        $user_id = get_current_user_id();

        // Check if user denied access
        if (isset($_GET['denied'])) {
            $denied_token = sanitize_text_field($_GET['denied']);
            // Clean up transient if we can identify it (optional)
            delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $denied_token);
            $this->logger?->warning('Twitter OAuth Warning: User denied access.', ['user_id' => $user_id, 'denied_token' => $denied_token]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_access_denied'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['oauth_token']) || !isset($_GET['oauth_verifier'])) {
            $this->logger?->error('Twitter OAuth Error: Missing oauth_token or oauth_verifier in callback.', ['user_id' => $user_id, 'query_params' => $_GET]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_missing_callback_params'));
            exit;
        }

        $oauth_token = sanitize_text_field($_GET['oauth_token']);
        $oauth_verifier = sanitize_text_field($_GET['oauth_verifier']);

        // --- 2. Retrieve Temp Secret & Credentials --- 
        $oauth_token_secret = get_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);
        // Delete transient immediately after retrieving it
        delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);

        if (empty($oauth_token_secret)) {
            $this->logger?->error('Twitter OAuth Error: Request token secret missing or expired in transient.', ['user_id' => $user_id, 'oauth_token' => $oauth_token]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_token_secret_expired'));
            exit;
        }

        $apiKey = get_option('twitter_api_key');
        $apiSecret = get_option('twitter_api_secret');
        if (empty($apiKey) || empty($apiSecret)) {
            $this->logger?->error('Twitter OAuth Error: API Key/Secret missing during callback.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_missing_app_keys'));
            exit;
        }

        // --- 3. Exchange for Access Token --- 
        try {
            // Instantiate with App Key/Secret and *Request* Token/Secret
            $connection = new TwitterOAuth($apiKey, $apiSecret, $oauth_token, $oauth_token_secret);

            // Exchange Request Token for Access Token
            $access_token_data = $connection->oauth("oauth/access_token", ["oauth_verifier" => $oauth_verifier]);

            // Check for errors during token exchange
            if ($connection->getLastHttpCode() != 200 || !isset($access_token_data['oauth_token']) || !isset($access_token_data['oauth_token_secret'])) {
                $this->logger?->error('Twitter OAuth Error: Failed to get access token.', [
                    'user_id' => $user_id,
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody()
                ]);
                wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_access_token_failed'));
                exit;
            }

            // --- 4. Store Permanent Credentials --- 
            $account_data = [
                'access_token'        => $access_token_data['oauth_token'],
                'access_token_secret' => $access_token_data['oauth_token_secret'],
                'user_id'             => $access_token_data['user_id'] ?? null, // Twitter User ID
                'screen_name'         => $access_token_data['screen_name'] ?? null, // Twitter Screen Name (@handle)
                'last_verified_at'    => time() // Timestamp of this successful auth
            ];

            // Store against the current WP user. Assumes one Twitter auth per WP user.
            update_user_meta($user_id, self::USER_META_KEY, $account_data);

            // --- 5. Redirect on Success --- 
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_success=twitter'));
            exit;

        } catch (\Exception $e) {
            $this->logger?->error('Twitter OAuth Exception during callback: ' . $e->getMessage(), ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=twitter_callback_exception'));
            exit;
        }
    }

    /**
     * Retrieves the stored Twitter account details for a user.
     *
     * @param int $user_id WordPress User ID.
     * @return array|null Account details array or null if not found/invalid.
     */
    public static function get_account_details(int $user_id): ?array {
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['access_token_secret'])) {
            return null;
        }
        // Optionally add checks for screen_name, user_id etc. if needed downstream
        return $account;
    }

    /**
     * Removes the stored Twitter account details for a user.
     *
     * @param int $user_id WordPress User ID.
     * @return bool True on success, false on failure.
     */
    public static function remove_account(int $user_id): bool {
        return delete_user_meta($user_id, self::USER_META_KEY);
    }

    /**
     * Gets an authenticated TwitterOAuth connection object for a specific user.
     *
     * @param int $user_id The user ID.
     * @return TwitterOAuth|WP_Error Authenticated connection object or WP_Error on failure.
     */
    public function get_connection_for_user(int $user_id): TwitterOAuth|WP_Error {
        $this->logger?->info('Attempting to get authenticated Twitter connection for user.', ['user_id' => $user_id]);

        $credentials = get_user_meta($user_id, 'data_machine_twitter_account', true);
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['access_token_secret'])) {
            $this->logger?->error('Missing Twitter credentials in user meta.', ['user_id' => $user_id]);
            return new WP_Error('twitter_missing_credentials', __('Twitter credentials not found for this user. Please authenticate on the API Keys page.', 'data-machine'));
        }

        $consumer_key = get_option('twitter_api_key');
        $consumer_secret = get_option('twitter_api_secret');
        if (empty($consumer_key) || empty($consumer_secret)) {
            $this->logger?->error('Missing Twitter API key/secret in site options.', ['user_id' => $user_id]);
            return new WP_Error('twitter_missing_app_keys', __('Twitter application keys are not configured in plugin settings.', 'data-machine'));
        }

        try {
            $connection = new TwitterOAuth($consumer_key, $consumer_secret, $credentials['access_token'], $credentials['access_token_secret']);
            // Optionally verify credentials work
            // $user = $connection->get("account/verify_credentials");
            // if ($connection->getLastHttpCode() != 200) { ... handle error ... }
            
            $this->logger?->info('Successfully created authenticated Twitter connection for user.', ['user_id' => $user_id]);
            return $connection;
        } catch (\Exception $e) {
            $this->logger?->error('Exception creating TwitterOAuth connection: ' . $e->getMessage(), ['user_id' => $user_id]);
            return new WP_Error('twitter_connection_exception', __('Could not establish connection to Twitter.', 'data-machine'));
        }
    }


} 