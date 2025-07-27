<?php
/**
 * Handles Facebook OAuth 2.0 authentication flow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/oauth
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

use DataMachine\Helpers\{EncryptionHelper, Logger};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Facebook {

    // Constants for Facebook OAuth
    const AUTH_URL = 'https://www.facebook.com/v19.0/dialog/oauth'; // Use a recent version
    const TOKEN_URL = 'https://graph.facebook.com/v19.0/oauth/access_token';
    // Define required scopes - adjust as needed for posting permissions
    const SCOPES = 'email,public_profile,pages_show_list,pages_read_engagement,pages_manage_posts'; // Example scopes
    // Added pages_show_list, pages_read_engagement, pages_manage_posts
    // Added constant for Graph API URL base
    const GRAPH_API_URL = 'https://graph.facebook.com/v19.0';

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get logger service via filter
     *
     * @return Logger|null
     */
    private function get_logger() {
        return apply_filters('dm_get_logger', null);
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
        return admin_url('admin.php?page=dm-api-keys&dm_oauth_callback=facebook');
    }

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        add_action('admin_init', [$this, 'handle_oauth_callback_check']);
    }

    /**
     * Generates the authorization URL to redirect the user to.
     *
     * @param int $user_id WordPress User ID for state verification.
     * @return string The authorization URL.
     */
    public function get_authorization_url(int $user_id): string {
        $state = wp_create_nonce('dm_facebook_oauth_state_' . $user_id);
        // Store state temporarily for verification on callback
        update_user_meta($user_id, 'dm_facebook_oauth_state', $state);

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
     * Handles the OAuth callback from Facebook.
     * Verifies state, exchanges code for token, and stores credentials.
     *
     * @param int    $user_id WordPress User ID.
     * @param string $code    Authorization code from Facebook.
     * @param string $state   State parameter from Facebook for verification.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(int $user_id, string $code, string $state): bool|WP_Error {
        $this->get_logger()?->info('Handling Facebook OAuth callback.', ['user_id' => $user_id]);

        // 1. Verify state
        $stored_state = get_user_meta($user_id, 'dm_facebook_oauth_state', true);
        delete_user_meta($user_id, 'dm_facebook_oauth_state'); // Clean up state
        if (empty($stored_state) || !hash_equals($stored_state, $state)) {
            $this->get_logger()?->error('Facebook OAuth Error: State mismatch.', ['user_id' => $user_id]);
            return new WP_Error('facebook_oauth_state_mismatch', __('Invalid state parameter during Facebook authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri'  => $this->get_redirect_uri(),
            'code'          => $code,
        ];
        $token_url = self::TOKEN_URL . '?' . http_build_query($token_params);

        // Facebook requires GET for token exchange
        $response = wp_remote_get($token_url, [
            'timeout'=> 15,
        ]);

        if (is_wp_error($response)) {
            $this->get_logger()?->error('Facebook OAuth Error: Token request failed.', ['user_id' => $user_id, 'error' => $response->get_error_message()]);
            return new WP_Error('facebook_oauth_token_request_failed', __('HTTP error during token exchange with Facebook.', 'data-machine'), $response);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve access token from Facebook.';
            $this->get_logger()?->error('Facebook OAuth Error: Token exchange failed.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new WP_Error('facebook_oauth_token_exchange_failed', $error_message, $data);
        }

        // 3. Store token and user info securely
        // Note: Facebook short-lived tokens need to be exchanged for long-lived tokens
        $short_lived_token = $data['access_token'];
        $expires_in_short = $data['expires_in'] ?? null; // Seconds (for short-lived token)

        // Exchange for long-lived token
        $long_lived_token_data = $this->exchange_for_long_lived_token($short_lived_token);

        if (is_wp_error($long_lived_token_data)) {
            $this->get_logger()?->error('Facebook OAuth Error: Failed to exchange for long-lived token.', [
                'user_id' => $user_id,
                'error' => $long_lived_token_data->get_error_message(),
                'error_data' => $long_lived_token_data->get_error_data()
            ]);
            // Return the error, as a long-lived token is crucial
            return $long_lived_token_data;
        }

        $access_token = $long_lived_token_data['access_token'];
        $token_expires_at = $long_lived_token_data['expires_at']; // Timestamp

        // ---> NEW: Fetch Page credentials using the long-lived user token
        $page_credentials = $this->get_page_credentials($access_token, $user_id);

        if (is_wp_error($page_credentials)) {
            $this->get_logger()?->error('Facebook OAuth Error: Failed to fetch page credentials.', [
                'user_id' => $user_id,
                'error' => $page_credentials->get_error_message(),
                'error_data' => $page_credentials->get_error_data()
            ]);
            // Return the error, as page credentials are required for posting
            return $page_credentials;
        }

        // Select the first page found. For multiple pages, users can manage selection 
        // through the WordPress admin interface by re-authenticating and selecting different pages.
        $page_id = $page_credentials['id'];
        $page_access_token = $page_credentials['access_token'];
        $page_name = $page_credentials['name']; // Use the page name for clarity

        // Encrypt the page access token before storing
        $encrypted_page_token = EncryptionHelper::encrypt($page_access_token);
        if ($encrypted_page_token === false) {
             $this->get_logger()?->error('Facebook OAuth Error: Failed to encrypt page access token.', ['user_id' => $user_id]);
             return new WP_Error('facebook_oauth_page_encryption_failed', __('Failed to securely store the Facebook page access token.', 'data-machine'));
        }
        // ---< END NEW

        // Fetch user/page info using the new long-lived access token
        $profile_info = $this->get_user_profile($access_token);
        $user_profile_id = 'Unknown';
        $user_profile_name = 'Unknown';

        if (!is_wp_error($profile_info)) {
            $user_profile_id = $profile_info['id'] ?? 'ErrorFetchingId';
            $user_profile_name = $profile_info['name'] ?? 'ErrorFetchingName';
        } else {
             $this->get_logger()?->warning('Facebook OAuth Warning: Failed to fetch user profile info.', [
                 'user_id' => $user_id,
                 'error' => $profile_info->get_error_message(),
                 'error_data' => $profile_info->get_error_data()
             ]);
        }

        // Encrypt the user access token before storing
        $encrypted_user_token = EncryptionHelper::encrypt($access_token);
        if ($encrypted_user_token === false) {
             $this->get_logger()?->error('Facebook OAuth Error: Failed to encrypt user access token.', ['user_id' => $user_id]);
             return new WP_Error('facebook_oauth_user_encryption_failed', __('Failed to securely store the Facebook user access token.', 'data-machine'));
        }

        $account_details = [
            'user_access_token'  => $encrypted_user_token, // Store encrypted user token
            'page_access_token'  => $encrypted_page_token, // Store encrypted page token
            'token_type'         => $data['token_type'] ?? 'bearer',
            'user_id'            => $user_profile_id, // Facebook User ID
            'user_name'          => $user_profile_name, // Facebook User Name
            'page_id'            => $page_id, // Facebook Page ID
            'page_name'          => $page_name, // Facebook Page Name
            'authenticated_at'   => time(),
            'token_expires_at'   => $token_expires_at, // Expiry for the USER token
            // Note: Page tokens obtained this way usually don't expire unless the user token does,
            // but specific page management actions might invalidate them sooner.
        ];

        // Store details
        update_user_meta($user_id, 'data_machine_facebook_auth_account', $account_details);
        $this->get_logger()?->info(
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
     * Retrieves the stored access token for a user.
     * Handles decryption. Long-lived token refresh is not implemented here.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Access token or null if not found/valid/decryption fails.
     */
    public static function get_access_token(int $user_id): ?string {
        $account = get_user_meta($user_id, 'data_machine_facebook_auth_account', true);
        if (empty($account) || !is_array($account) || empty($account['user_access_token'])) {
            return null;
        }

        // Check expiry if exists (long-lived tokens expire ~60 days)
        // We won't automatically refresh here, but this check prevents using an expired token.
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
             // Token expired - user needs to re-authenticate through the admin interface
             // WordPress admin will display authentication prompts when tokens are invalid
             return null; // Return null if expired
        }

        // Decrypt token
        $decrypted_token = EncryptionHelper::decrypt($account['user_access_token']);
        if($decrypted_token === false) {
            return null; // Return null if decryption fails
        }

        return $decrypted_token;
    }

    /**
     * Retrieves the stored Page access token for a user.
     * Handles decryption. Assumes page token validity is tied to user token expiry for now.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Page Access token or null if not found/valid/decryption fails.
     */
    public static function get_page_access_token(int $user_id): ?string {
        $account = get_user_meta($user_id, 'data_machine_facebook_auth_account', true);
        if (empty($account) || !is_array($account) || empty($account['page_access_token'])) {
            return null;
        }

        // Check user token expiry as a proxy for page token validity
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return null; // Return null if expired
        }

        // Decrypt page token
        $decrypted_token = EncryptionHelper::decrypt($account['page_access_token']);
        if($decrypted_token === false) {
            return null; // Return null if decryption fails
        }

        return $decrypted_token;
    }

    /**
     * Retrieves the stored Page ID for a user.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Page ID or null if not found.
     */
    public static function get_page_id(int $user_id): ?string {
        $account = get_user_meta($user_id, 'data_machine_facebook_auth_account', true);
        if (empty($account) || !is_array($account) || empty($account['page_id'])) {
            return null;
        }
        // No expiry check needed for Page ID itself
        return $account['page_id'];
    }

    /**
     * Retrieves user profile information from Facebook Graph API.
     *
     * @param string $access_token Valid access token.
     * @return array|WP_Error Profile data (id, name) or WP_Error on failure.
     */
    private function get_user_profile(string $access_token): array|WP_Error {
        $url = self::GRAPH_API_URL . '/me?fields=id,name'; // Basic profile fields
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook profile.';
             return new WP_Error('facebook_profile_fetch_failed', $error_message, $data);
        }

        return $data; // Should contain 'id' and 'name'
    }

    /**
     * Exchanges a short-lived access token for a long-lived one.
     *
     * @param string $short_lived_token
     * @return array|WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private function exchange_for_long_lived_token(string $short_lived_token): array|WP_Error {
        $this->get_logger()?->info('Exchanging Facebook short-lived token for long-lived token.');
        $params = [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->get_client_id(),
            'client_secret'     => $this->get_client_secret(),
            'fb_exchange_token' => $short_lived_token,
        ];
        $url = self::TOKEN_URL . '?' . http_build_query($params);

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            $this->get_logger()?->error('Facebook OAuth Error: Long-lived token request failed.', ['error' => $response->get_error_message()]);
            return new WP_Error('facebook_oauth_long_token_request_failed', __('HTTP error during long-lived token exchange with Facebook.', 'data-machine'), $response);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve long-lived access token from Facebook.';
             $this->get_logger()?->error('Facebook OAuth Error: Long-lived token exchange failed.', ['http_code' => $http_code, 'response' => $body]);
            return new WP_Error('facebook_oauth_long_token_exchange_failed', $error_message, $data);
        }

        // Calculate expiry timestamp (Facebook returns seconds_until_expiry)
        $expires_in = $data['expires_in'] ?? 3600 * 24 * 60; // Default to ~60 days if not provided
        $expires_at = time() + intval($expires_in);

        $this->get_logger()?->info('Successfully obtained Facebook long-lived token.');

        return [
            'access_token' => $data['access_token'],
            'expires_at'   => $expires_at, // Return timestamp
        ];
    }

    /**
     * Fetches the Pages the user manages using the User Access Token.
     *
     * @param string $user_access_token The valid User Access Token.
     * @param int    $user_id WordPress User ID for logging context.
     * @return array|WP_Error An array containing the first page's 'id', 'name', and 'access_token', or WP_Error on failure.
     */
    private function get_page_credentials(string $user_access_token, int $user_id): array|WP_Error {
        $this->get_logger()?->info('Fetching Facebook page credentials.', ['user_id' => $user_id]);
        $url = self::GRAPH_API_URL . '/me/accounts?fields=id,name,access_token'; // Request Page ID, Name, and Page Access Token

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $user_access_token,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->get_logger()?->error('Facebook Page Fetch Error: Request failed.', ['user_id' => $user_id, 'error' => $response->get_error_message()]);
            return new WP_Error('facebook_page_request_failed', __('HTTP error while fetching Facebook pages.', 'data-machine'), $response);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || !isset($data['data'])) {
            $error_message = $data['error']['message'] ?? 'Failed to retrieve pages from Facebook.';
            $this->get_logger()?->error('Facebook Page Fetch Error: API error.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new WP_Error('facebook_page_api_error', $error_message, $data);
        }

        if (empty($data['data'])) {
            $this->get_logger()?->error('Facebook Page Fetch Error: No pages found for this user.', ['user_id' => $user_id, 'response' => $body]);
            return new WP_Error('facebook_no_pages_found', __('No Facebook pages associated with this account were found. Please ensure the account manages at least one page and granted necessary permissions.', 'data-machine'));
        }

        // Return the first page found.
        // Multiple page selection can be handled through user re-authentication
        // when users need to switch to different pages.
        $first_page = $data['data'][0];

        if (empty($first_page['id']) || empty($first_page['access_token']) || empty($first_page['name'])) {
             $this->get_logger()?->error('Facebook Page Fetch Error: Incomplete data for the first page.', ['user_id' => $user_id, 'page_data' => $first_page]);
            return new WP_Error('facebook_incomplete_page_data', __('Required information (ID, Access Token, Name) was missing for the Facebook page.', 'data-machine'));
        }

        $this->get_logger()?->info('Successfully fetched credentials for Facebook page.', ['user_id' => $user_id, 'page_id' => $first_page['id']]);

        return [
            'id'           => $first_page['id'],
            'name'         => $first_page['name'],
            'access_token' => $first_page['access_token'],
        ];
    }

    /**
     * Checks for the OAuth callback parameters on admin_init.
     */
    public function handle_oauth_callback_check() {
        // Check if this is our callback
        if (!isset($_GET['page']) || $_GET['page'] !== 'dm-api-keys' || !isset($_GET['dm_oauth_callback']) || $_GET['dm_oauth_callback'] !== 'facebook') {
            return;
        }

        // Check for error parameter first (user might deny access)
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : 'User denied access or an error occurred.';
            $this->get_logger()?->warning('Facebook OAuth Error in callback:', ['error' => $error, 'description' => $error_description]);
            wp_redirect(add_query_arg('auth_error', $error, admin_url('admin.php?page=dm-api-keys')));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            $this->get_logger()?->error('Facebook OAuth Error: Missing code or state in callback.', ['query_params' => $_GET]);
            wp_redirect(add_query_arg('auth_error', 'missing_params', admin_url('admin.php?page=dm-api-keys')));
            exit;
        }

        // Check user permissions (should be logged in to WP admin)
        if (!current_user_can('manage_options')) {
             $this->get_logger()?->error('Facebook OAuth Error: User does not have permission.', ['user_id' => get_current_user_id()]);
             wp_redirect(add_query_arg('auth_error', 'permission_denied', admin_url('admin.php?page=dm-api-keys')));
             exit;
        }

        $user_id = get_current_user_id();
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);

        // Retrieve stored app credentials from global options
        $app_id = get_option('facebook_app_id');
        $app_secret = get_option('facebook_app_secret');
        if (empty($app_id) || empty($app_secret)) {
            $this->get_logger()?->error('Facebook OAuth Error: App credentials not configured.', ['user_id' => $user_id]);
            wp_redirect(add_query_arg('auth_error', 'config_missing', admin_url('admin.php?page=dm-api-keys')));
            exit;
        }

        // Re-instantiate self to handle the callback
        $oauth_handler = new self();
        $result = $oauth_handler->handle_callback($user_id, $code, $state);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $this->get_logger()?->error('Facebook OAuth Callback Failed.', ['user_id' => $user_id, 'error_code' => $error_code, 'error_message' => $error_message]);
            // Redirect with a generic or specific error code
            wp_redirect(add_query_arg('auth_error', $error_code, admin_url('admin.php?page=dm-api-keys')));
        } else {
            $this->get_logger()?->info('Facebook OAuth Callback Successful.', ['user_id' => $user_id]);
            wp_redirect(add_query_arg('auth_success', 'facebook', admin_url('admin.php?page=dm-api-keys')));
        }
        exit;
    }

    /**
     * Removes the authenticated Facebook account for the user.
     * Attempts to deauthorize the app via the Graph API first.
     *
     * @param int $user_id WordPress User ID.
     * @return bool True on success (local data deleted), false otherwise.
     */
    public static function remove_account(int $user_id): bool {
        // Try to get the stored token to attempt deauthorization
        $token = self::get_access_token($user_id);

        if ($token) {
            // Attempt deauthorization with Facebook
            $url = self::GRAPH_API_URL . '/me/permissions'; // Use defined constant
            $response = wp_remote_request($url, [
                'method' => 'DELETE',
                'body' => ['access_token' => $token], // Send token in body for DELETE
                'timeout' => 10,
            ]);

            // Log success or failure of deauthorization, but don't stop deletion
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                // Consider logging this via a proper logger instance if available/needed
            } else {
            }
        }

        // Always attempt to delete the local user meta regardless of deauth success
        return delete_user_meta($user_id, 'data_machine_facebook_auth_account');
    }
}