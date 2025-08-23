<?php
/**
 * Handles Twitter OAuth 1.0a authentication for the Twitter publish handler.
 *
 * Self-contained authentication system that provides all OAuth functionality
 * needed by the Twitter publish handler including credential management,
 * OAuth flow handling, and authenticated connection creation.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Twitter
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class TwitterAuth {

    const OAUTH_CALLBACK_ACTION = 'dm_twitter_oauth_callback';
    const TEMP_TOKEN_SECRET_TRANSIENT_PREFIX = 'dm_twitter_req_secret_'; // Prefix + request_token

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }



    /**
     * Checks if admin has valid Twitter authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['access_token_secret']);
    }

    /**
     * Get configuration fields required for Twitter authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'api_key' => [
                'label' => __('API Key', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Twitter application API key from developer.twitter.com', 'data-machine')
            ],
            'api_secret' => [
                'label' => __('API Secret', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Twitter application API secret from developer.twitter.com', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Twitter authentication is properly configured
     *
     * @return bool True if API credentials are configured, false otherwise
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
        return !empty($config['api_key']) && !empty($config['api_secret']);
    }

    /**
     * Gets an authenticated TwitterOAuth connection object.
     *
     * @return TwitterOAuth|\WP_Error Authenticated connection object or WP_Error on failure.
     */
    public function get_connection() {
        do_action('dm_log', 'debug', 'Attempting to get authenticated Twitter connection.');

        $credentials = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['access_token_secret'])) {
            do_action('dm_log', 'error', 'Missing Twitter credentials in options.');
            return new \WP_Error('twitter_missing_credentials', __('Twitter credentials not found. Please authenticate on the API Keys page.', 'data-machine'));
        }

        // Get the stored tokens directly
        $access_token = $credentials['access_token'];
        $access_token_secret = $credentials['access_token_secret'];

        $config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
        $consumer_key = $config['api_key'] ?? '';
        $consumer_secret = $config['api_secret'] ?? '';
        if (empty($consumer_key) || empty($consumer_secret)) {
            do_action('dm_log', 'error', 'Missing Twitter API key/secret in site options.');
            return new \WP_Error('twitter_missing_app_keys', __('Twitter application keys are not configured in plugin settings.', 'data-machine'));
        }

        try {
            $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
            do_action('dm_log', 'debug', 'Successfully created authenticated Twitter connection.');
            return $connection;
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Exception creating TwitterOAuth connection: ' . $e->getMessage());
            return new \WP_Error('twitter_connection_exception', __('Could not establish connection to Twitter.', 'data-machine'));
        }
    }

    /**
     * Registers the necessary WordPress action hooks for OAuth flow.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_' . self::OAUTH_CALLBACK_ACTION, array($this, 'handle_oauth_callback'));
    }

    /**
     * Get the authorization URL for direct connection to Twitter OAuth
     *
     * @return string|WP_Error Authorization URL or error
     */
    public function get_authorization_url() {
        // 1. Get API Key/Secret from configuration
        $config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';
        if (empty($api_key) || empty($api_secret)) {
            return new WP_Error('twitter_missing_app_keys', __('Twitter API Key/Secret not configured.', 'data-machine'));
        }

        // 2. Define Callback URL  
        $callback_url = apply_filters('dm_get_oauth_url', '', 'twitter');

        try {
            // 3. Instantiate TwitterOAuth
            $connection = new TwitterOAuth($api_key, $api_secret);

            // 4. Get Request Token from Twitter API
            $request_token = $connection->oauth('oauth/request_token', ['oauth_callback' => $callback_url]);

            // 5. Check for errors from Twitter
            if ($connection->getLastHttpCode() != 200 || !isset($request_token['oauth_token']) || !isset($request_token['oauth_token_secret'])) {
                $error_message = 'Failed to get request token from Twitter.';
                do_action('dm_log', 'error', 'Twitter OAuth Error: ' . $error_message, [
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody()
                ]);
                return new WP_Error('twitter_request_token_failed', __('Failed to get request token from Twitter.', 'data-machine'));
            }

            // 6. Store Request Token Secret temporarily
            set_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $request_token['oauth_token'], $request_token['oauth_token_secret'], 15 * MINUTE_IN_SECONDS);

            // 7. Return Authorization URL
            return $connection->url('oauth/authenticate', ['oauth_token' => $request_token['oauth_token']]);

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter OAuth Exception: ' . $e->getMessage());
            return new WP_Error('twitter_init_exception', __('Twitter OAuth initialization failed.', 'data-machine'));
        }
    }

    /**
     * Handles the callback from Twitter after user authorization.
     * Hooked to 'admin_post_dm_twitter_oauth_callback'.
     */
    public function handle_oauth_callback() {
        // --- 1. Initial Checks --- 
        if (!current_user_can('manage_options')) {
             wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_permission_denied'));
             exit;
        }

        // Check if user denied access
        if (isset($_GET['denied'])) {
            $denied_token = sanitize_text_field(wp_unslash($_GET['denied']));
            // Clean up transient if we can identify it (optional)
            delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $denied_token);
            do_action('dm_log', 'warning', 'Twitter OAuth Warning: User denied access.', ['denied_token' => $denied_token]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_access_denied'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['oauth_token']) || !isset($_GET['oauth_verifier'])) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: Missing oauth_token or oauth_verifier in callback.', ['query_params' => $_GET]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_missing_callback_params'));
            exit;
        }

        $oauth_token = sanitize_text_field(wp_unslash($_GET['oauth_token']));
        $oauth_verifier = sanitize_text_field(wp_unslash($_GET['oauth_verifier']));

        // --- 2. Retrieve Temp Secret & Credentials --- 
        $oauth_token_secret = get_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);
        // Delete transient immediately after retrieving it
        delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);

        if (empty($oauth_token_secret)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: Request token secret missing or expired in transient.', ['oauth_token' => $oauth_token]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_token_secret_expired'));
            exit;
        }

        $config = apply_filters('dm_oauth', [], 'get_config', 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';
        if (empty($api_key) || empty($api_secret)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: API Key/Secret missing during callback.');
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_missing_app_keys'));
            exit;
        }

        // --- 3. Exchange for Access Token --- 
        try {
            // Instantiate with App Key/Secret and *Request* Token/Secret
            $connection = new TwitterOAuth($api_key, $api_secret, $oauth_token, $oauth_token_secret);

            // Exchange Request Token for Access Token
            $access_token_data = $connection->oauth("oauth/access_token", ["oauth_verifier" => $oauth_verifier]);

            // Check for errors during token exchange
            if ($connection->getLastHttpCode() != 200 || !isset($access_token_data['oauth_token']) || !isset($access_token_data['oauth_token_secret'])) {
                do_action('dm_log', 'error', 'Twitter OAuth Error: Failed to get access token.', [
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody()
                ]);
                wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_access_token_failed'));
                exit;
            }

            // --- 4. Store Permanent Credentials --- 
            // Store the access tokens directly
            $account_data = [
                'access_token'        => $access_token_data['oauth_token'],
                'access_token_secret' => $access_token_data['oauth_token_secret'],
                'user_id'             => $access_token_data['user_id'] ?? null, // Twitter User ID
                'screen_name'         => $access_token_data['screen_name'] ?? null, // Twitter Screen Name (@handle)
                'last_verified_at'    => time() // Timestamp of this successful auth
            ];

            // Store in site options for admin-only authentication
            apply_filters('dm_oauth', null, 'store', 'twitter', $account_data);

            // --- 5. Redirect on Success --- 
            // Check if this is a modal context request
            $modal_context = isset($_GET['modal_context']) && $_GET['modal_context'] === '1';
            
            if ($modal_context) {
                // Modal context - show simple success page that closes window
                $this->show_modal_oauth_result('success', 'twitter');
                exit;
            } else {
                // Traditional context - redirect to pipelines page
                wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_success=twitter'));
                exit;
            }

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter OAuth Exception during callback: ' . $e->getMessage());
            
            // Check if this is a modal context request
            $modal_context = isset($_GET['modal_context']) && $_GET['modal_context'] === '1';
            
            if ($modal_context) {
                // Modal context - show simple error page
                $this->show_modal_oauth_result('error', 'twitter', 'callback_exception', $e->getMessage());
                exit;
            } else {
                // Traditional context - redirect to pipelines page
                wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=twitter_callback_exception'));
                exit;
            }
        }
    }

    /**
     * Retrieves the stored Twitter account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'twitter');
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['access_token_secret'])) {
            return null;
        }
        return $account;
    }

    /**
     * Removes the stored Twitter account details.
     * Uses global site options for admin-global authentication.
     *
     * @return bool True on success, false on failure.
     */
    public function remove_account(): bool {
        return apply_filters('dm_oauth', false, 'clear', 'twitter');
    }

}

