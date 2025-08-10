<?php
/**
 * Handles Facebook OAuth 2.0 authentication for the Facebook publish handler.
 *
 * Self-contained authentication system that provides all OAuth functionality
 * needed by the Facebook publish handler including credential management,
 * OAuth flow handling, and page token management.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Facebook;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FacebookAuth {

    // Constants for Facebook OAuth
    const AUTH_URL = 'https://www.facebook.com/v19.0/dialog/oauth';
    const TOKEN_URL = 'https://graph.facebook.com/v19.0/oauth/access_token';
    const SCOPES = 'email,public_profile,pages_show_list,pages_read_engagement,pages_manage_posts';
    const GRAPH_API_URL = 'https://graph.facebook.com/v19.0';
    const USER_META_KEY = 'data_machine_facebook_auth_account';

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Registers the necessary WordPress action hooks for OAuth callback flow.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_init', [$this, 'handle_oauth_callback_check']);
    }



    /**
     * Checks if admin has valid Facebook authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = get_option('facebook_auth_data', []);
        if (empty($account) || !is_array($account)) {
            return false;
        }

        // Check if we have both user and page tokens
        if (empty($account['user_access_token']) || empty($account['page_access_token'])) {
            return false;
        }

        // Check token expiry if exists
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the stored Page access token.
     * Uses global site options for admin-only authentication.
     *
     * @return string|null Page Access token or null if not found/valid.
     */
    public function get_page_access_token(): ?string {
        $account = get_option('facebook_auth_data', []);
        if (empty($account) || !is_array($account) || empty($account['page_access_token'])) {
            return null;
        }

        // Check user token expiry as a proxy for page token validity
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return null;
        }

        // Get page token directly
        return $account['page_access_token'];
    }

    /**
     * Retrieves the stored Page ID.
     * Uses global site options for admin-only authentication.
     *
     * @return string|null Page ID or null if not found.
     */
    public function get_page_id(): ?string {
        $account = get_option('facebook_auth_data', []);
        if (empty($account) || !is_array($account) || empty($account['page_id'])) {
            return null;
        }
        return $account['page_id'];
    }

    /**
     * Handles the OAuth callback from Facebook.
     *
     * @param int    $user_id WordPress User ID.
     * @param string $code    Authorization code from Facebook.
     * @param string $state   State parameter from Facebook for verification.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(int $user_id, string $code, string $state): bool|\WP_Error {
        do_action('dm_log', 'debug', 'Handling Facebook OAuth callback.', ['user_id' => $user_id]);

        // 1. Verify state - use transient for admin-only architecture
        $stored_state = get_transient('dm_facebook_oauth_state_' . $user_id);
        delete_transient('dm_facebook_oauth_state_' . $user_id); // Clean up state
        if (empty($stored_state) || !hash_equals($stored_state, $state)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: State mismatch.', ['user_id' => $user_id]);
            return new \WP_Error('facebook_oauth_state_mismatch', __('Invalid state parameter during Facebook authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri'  => $this->get_redirect_uri(),
            'code'          => $code,
        ];
        $token_url = self::TOKEN_URL . '?' . http_build_query($token_params);

        // Facebook requires GET for token exchange - use dm_send_request action hook
        $result = null;
        do_action('dm_send_request', 'GET', $token_url, [
        ], 'Facebook Token Exchange', $result);
        
        // Convert result to WP_Error format for compatibility
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Token request failed.', ['user_id' => $user_id, 'error' => $result['error']]);
            return new \WP_Error('facebook_oauth_token_request_failed', __('HTTP error during token exchange with Facebook.', 'data-machine'), $result['error']);
        }
        
        $response = $result['data'];

        $body = $response['body'];
        $data = json_decode($body, true);
        $http_code = $response['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve access token from Facebook.';
            do_action('dm_log', 'error', 'Facebook OAuth Error: Token exchange failed.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('facebook_oauth_token_exchange_failed', $error_message, $data);
        }

        // 3. Store token and user info securely
        $short_lived_token = $data['access_token'];

        // Exchange for long-lived token
        $long_lived_token_data = $this->exchange_for_long_lived_token($short_lived_token);

        if (is_wp_error($long_lived_token_data)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Failed to exchange for long-lived token.', [
                'user_id' => $user_id,
                'error' => $long_lived_token_data->get_error_message(),
                'error_data' => $long_lived_token_data->get_error_data()
            ]);
            return $long_lived_token_data;
        }

        $access_token = $long_lived_token_data['access_token'];
        $token_expires_at = $long_lived_token_data['expires_at'];

        // Fetch Page credentials using the long-lived user token
        $page_credentials = $this->get_page_credentials($access_token, $user_id);

        if (is_wp_error($page_credentials)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Failed to fetch page credentials.', [
                'user_id' => $user_id,
                'error' => $page_credentials->get_error_message(),
                'error_data' => $page_credentials->get_error_data()
            ]);
            return $page_credentials;
        }

        // Select the first page found
        $page_id = $page_credentials['id'];
        $page_access_token = $page_credentials['access_token'];
        $page_name = $page_credentials['name'];

        // Store the page access token directly
        $page_token = $page_access_token;

        // Fetch user profile info
        $profile_info = $this->get_user_profile($access_token);
        $user_profile_id = 'Unknown';
        $user_profile_name = 'Unknown';

        if (!is_wp_error($profile_info)) {
            $user_profile_id = $profile_info['id'] ?? 'ErrorFetchingId';
            $user_profile_name = $profile_info['name'] ?? 'ErrorFetchingName';
        } else {
             do_action('dm_log', 'warning', 'Facebook OAuth Warning: Failed to fetch user profile info.', [
                 'user_id' => $user_id,
                 'error' => $profile_info->get_error_message(),
                 'error_data' => $profile_info->get_error_data()
             ]);
        }

        // Store the user access token directly
        $user_token = $access_token;

        $account_details = [
            'user_access_token'  => $user_token,
            'page_access_token'  => $page_token,
            'token_type'         => $data['token_type'] ?? 'bearer',
            'user_id'            => $user_profile_id,
            'user_name'          => $user_profile_name,
            'page_id'            => $page_id,
            'page_name'          => $page_name,
            'authenticated_at'   => time(),
            'token_expires_at'   => $token_expires_at,
        ];

        // Store details in site options for admin-only architecture
        update_option('facebook_auth_data', $account_details);
        do_action('dm_log', 'debug',
            'Facebook account authenticated. User and Page credentials stored.',
            [
                'user_id' => $user_id,
                'facebook_user_id' => $account_details['user_id'],
                'facebook_page_id' => $account_details['page_id']
            ]
        );

        return true;
    }

    /**
     * Generates the authorization URL to redirect the user to.
     *
     * @param int $user_id WordPress User ID for state verification.
     * @return string The authorization URL.
     */
    public function get_authorization_url(int $user_id): string {
        $state = wp_create_nonce('dm_facebook_oauth_state_' . $user_id);
        // Store state temporarily for verification on callback using transient for admin-only architecture
        set_transient('dm_facebook_oauth_state_' . $user_id, $state, 15 * MINUTE_IN_SECONDS);

        $params = [
            'client_id'     => $this->get_client_id(),
            'redirect_uri'  => $this->get_redirect_uri(),
            'scope'         => self::SCOPES,
            'response_type' => 'code',
            'state'         => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Get Facebook client ID from options
     *
     * @return string
     */
    private function get_client_id() {
        return get_option('facebook_app_id', '');
    }

    /**
     * Get Facebook client secret from options
     *
     * @return string
     */
    private function get_client_secret() {
        return get_option('facebook_app_secret', '');
    }

    /**
     * Get redirect URI
     *
     * @return string
     */
    private function get_redirect_uri() {
        return admin_url('admin.php?page=dm-pipelines&dm_oauth_callback=facebook');
    }

    /**
     * Retrieves user profile information from Facebook Graph API.
     *
     * @param string $access_token Valid access token.
     * @return array|\WP_Error Profile data (id, name) or WP_Error on failure.
     */
    private function get_user_profile(string $access_token): array|\WP_Error {
        $url = self::GRAPH_API_URL . '/me?fields=id,name';
        
        // Use dm_send_request action hook for Facebook profile fetch
        $result = null;
        do_action('dm_send_request', 'GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ], 'Facebook Profile API', $result);
        
        if (!$result['success']) {
            return new \WP_Error('facebook_profile_fetch_failed', $result['error']);
        }
        
        $response = $result['data'];


        $body = $response['body'];
        $data = json_decode($body, true);
        $http_code = $response['status_code'];

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook profile.';
             return new \WP_Error('facebook_profile_fetch_failed', $error_message, $data);
        }

        return $data;
    }

    /**
     * Exchanges a short-lived access token for a long-lived one.
     *
     * @param string $short_lived_token
     * @return array|\WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private function exchange_for_long_lived_token(string $short_lived_token): array|\WP_Error {
        do_action('dm_log', 'debug', 'Exchanging Facebook short-lived token for long-lived token.');
        $params = [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->get_client_id(),
            'client_secret'     => $this->get_client_secret(),
            'fb_exchange_token' => $short_lived_token,
        ];
        $url = self::TOKEN_URL . '?' . http_build_query($params);

        // Use dm_send_request action hook for long-lived token exchange
        $result = null;
        do_action('dm_send_request', 'GET', $url, [], 'Facebook Long-lived Token Exchange', $result);
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Long-lived token request failed.', ['error' => $result['error']]);
            return new \WP_Error('facebook_oauth_long_token_request_failed', __('HTTP error during long-lived token exchange with Facebook.', 'data-machine'), $result['error']);
        }
        
        $response = $result['data'];


        $body = $response['body'];
        $data = json_decode($body, true);
        $http_code = $response['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve long-lived access token from Facebook.';
             do_action('dm_log', 'error', 'Facebook OAuth Error: Long-lived token exchange failed.', ['http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('facebook_oauth_long_token_exchange_failed', $error_message, $data);
        }

        // Calculate expiry timestamp
        $expires_in = $data['expires_in'] ?? 3600 * 24 * 60; // Default to ~60 days if not provided
        $expires_at = time() + intval($expires_in);

        do_action('dm_log', 'debug', 'Successfully obtained Facebook long-lived token.');

        return [
            'access_token' => $data['access_token'],
            'expires_at'   => $expires_at,
        ];
    }

    /**
     * Fetches the Pages the user manages using the User Access Token.
     *
     * @param string $user_access_token The valid User Access Token.
     * @param int    $user_id WordPress User ID for logging context.
     * @return array|\WP_Error An array containing the first page's 'id', 'name', and 'access_token', or WP_Error on failure.
     */
    private function get_page_credentials(string $user_access_token, int $user_id): array|\WP_Error {
        do_action('dm_log', 'debug', 'Fetching Facebook page credentials.', ['user_id' => $user_id]);
        $url = self::GRAPH_API_URL . '/me/accounts?fields=id,name,access_token';

        // Use dm_send_request action hook for Facebook pages fetch
        $result = null;
        do_action('dm_send_request', 'GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $user_access_token,
            ],
        ], 'Facebook Pages API', $result);
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: Request failed.', ['user_id' => $user_id, 'error' => $result['error']]);
            return new \WP_Error('facebook_page_request_failed', __('HTTP error while fetching Facebook pages.', 'data-machine'), $result['error']);
        }
        
        $response = $result['data'];


        $body = $response['body'];
        $data = json_decode($body, true);
        $http_code = $response['status_code'];

        if ($http_code !== 200 || !isset($data['data'])) {
            $error_message = $data['error']['message'] ?? 'Failed to retrieve pages from Facebook.';
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: API error.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('facebook_page_api_error', $error_message, $data);
        }

        if (empty($data['data'])) {
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: No pages found for this user.', ['user_id' => $user_id, 'response' => $body]);
            return new \WP_Error('facebook_no_pages_found', __('No Facebook pages associated with this account were found. Please ensure the account manages at least one page and granted necessary permissions.', 'data-machine'));
        }

        // Return the first page found
        $first_page = $data['data'][0];

        if (empty($first_page['id']) || empty($first_page['access_token']) || empty($first_page['name'])) {
             do_action('dm_log', 'error', 'Facebook Page Fetch Error: Incomplete data for the first page.', ['user_id' => $user_id, 'page_data' => $first_page]);
            return new \WP_Error('facebook_incomplete_page_data', __('Required information (ID, Access Token, Name) was missing for the Facebook page.', 'data-machine'));
        }

        do_action('dm_log', 'debug', 'Successfully fetched credentials for Facebook page.', ['user_id' => $user_id, 'page_id' => $first_page['id']]);

        return [
            'id'           => $first_page['id'],
            'name'         => $first_page['name'],
            'access_token' => $first_page['access_token'],
        ];
    }

    /**
     * Removes the authenticated Facebook account.
     * Uses global site options for admin-only authentication.
     * Attempts to deauthorize the app via the Graph API first.
     *
     * @return bool True on success (local data deleted), false otherwise.
     */
    public function remove_account(): bool {
        // Try to get the stored token to attempt deauthorization
        $account = get_option('facebook_auth_data', []);
        $token = null;

        if (!empty($account) && is_array($account) && !empty($account['user_access_token'])) {
            $token = $account['user_access_token'];
        }

        if ($token) {
            // Attempt deauthorization with Facebook - use dm_send_request action hook
            $url = self::GRAPH_API_URL . '/me/permissions';
            $result = null;
            do_action('dm_send_request', 'DELETE', $url, [
                'body' => ['access_token' => $token],
                ], 'Facebook Deauthorization', $result);
            
            // Log success or failure of deauthorization, but don't stop deletion
            if (!$result['success']) {
                do_action('dm_log', 'warning', 'Facebook deauthorization failed (non-critical)', ['error' => $result['error']]);
            } else {
                do_action('dm_log', 'debug', 'Facebook deauthorization successful');
            }
        }

        // Always attempt to delete the site option regardless of deauth success
        return delete_option('facebook_auth_data');
    }

    /**
     * Retrieves the stored Facebook account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = get_option('facebook_auth_data', []);
        if (empty($account) || !is_array($account)) {
            return null;
        }
        return $account;
    }

    /**
     * Checks for the OAuth callback parameters on admin_init.
     */
    public function handle_oauth_callback_check() {
        // Check if this is our callback
        if (!isset($_GET['page']) || $_GET['page'] !== 'dm-pipelines' || !isset($_GET['dm_oauth_callback']) || $_GET['dm_oauth_callback'] !== 'facebook') {
            return;
        }

        // Check for error parameter first (user might deny access)
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : 'User denied access or an error occurred.';
            do_action('dm_log', 'warning', 'Facebook OAuth Error in callback:', ['error' => $error, 'description' => $error_description]);
            wp_redirect(add_query_arg('auth_error', $error, admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Missing code or state in callback.', ['query_params' => $_GET]);
            wp_redirect(add_query_arg('auth_error', 'missing_params', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Check user permissions (should be logged in to WP admin)
        if (!current_user_can('manage_options')) {
             do_action('dm_log', 'error', 'Facebook OAuth Error: User does not have permission.', ['user_id' => get_current_user_id()]);
             wp_redirect(add_query_arg('auth_error', 'permission_denied', admin_url('admin.php?page=dm-pipelines')));
             exit;
        }

        $user_id = get_current_user_id();
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);

        // Retrieve stored app credentials from global options
        $app_id = get_option('facebook_app_id');
        $app_secret = get_option('facebook_app_secret');
        if (empty($app_id) || empty($app_secret)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: App credentials not configured.', ['user_id' => $user_id]);
            wp_redirect(add_query_arg('auth_error', 'config_missing', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Handle the callback
        $result = $this->handle_callback($user_id, $code, $state);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            do_action('dm_log', 'error', 'Facebook OAuth Callback Failed.', ['user_id' => $user_id, 'error_code' => $error_code, 'error_message' => $error_message]);
            // Redirect with a generic or specific error code
            wp_redirect(add_query_arg('auth_error', $error_code, admin_url('admin.php?page=dm-pipelines')));
        } else {
            do_action('dm_log', 'debug', 'Facebook OAuth Callback Successful.', ['user_id' => $user_id]);
            wp_redirect(add_query_arg('auth_success', 'facebook', admin_url('admin.php?page=dm-pipelines')));
        }
        exit;
    }
}

