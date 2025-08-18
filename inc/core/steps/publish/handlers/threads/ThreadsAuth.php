<?php
/**
 * Handles Threads OAuth 2.0 authentication for the Threads publish handler.
 *
 * Admin-global authentication system providing OAuth functionality with site-level
 * credential storage, long-lived token management with automatic refresh, and 
 * Facebook Graph API integration. Uses filter-based HTTP requests.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/threads
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Threads;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ThreadsAuth {

    // Constants for Threads OAuth
    const AUTH_URL = 'https://graph.facebook.com/oauth/authorize'; // Use FB authorize endpoint
    const TOKEN_URL = 'https://graph.threads.net/oauth/access_token';
    const REFRESH_URL = 'https://graph.threads.net/refresh_access_token';
    const API_BASE_URL = 'https://graph.threads.net/v1.0'; // Base for API calls
    const GRAPH_API_URL = 'https://graph.facebook.com/v19.0'; // Facebook Graph API URL for token revocation
    const SCOPES = 'threads_basic,threads_content_publish';

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get configuration fields required for Threads authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'app_id' => [
                'label' => __('App ID', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Threads application App ID from developers.facebook.com', 'data-machine')
            ],
            'app_secret' => [
                'label' => __('App Secret', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Threads application App Secret from developers.facebook.com', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Threads authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured, false otherwise
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_oauth', [], 'get_config', 'threads');
        return !empty($config['app_id']) && !empty($config['app_secret']);
    }

    /**
     * Registers the necessary WordPress action hooks for OAuth callback flow.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_threads_oauth_callback', array($this, 'handle_oauth_callback'));
    }



    /**
     * Checks if admin has valid Threads authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'threads');
        if (empty($account) || !is_array($account)) {
            return false;
        }

        // Check if we have access token
        if (empty($account['access_token'])) {
            return false;
        }

        // Check token expiry if exists
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the stored access token.
     * Uses global site options for admin-only authentication.
     * Handles token refresh if needed.
     *
     * @return string|null Access token or null if not found/valid.
     */
    public function get_access_token(): ?string {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'threads');
        if (empty($account) || !is_array($account) || empty($account['access_token'])) {
            return null;
        }

        // Check if token needs refresh (e.g., expires within the next 7 days)
        $needs_refresh = false;
        if (isset($account['token_expires_at'])) {
            $expiry_timestamp = intval($account['token_expires_at']);
            $seven_days_in_seconds = 7 * 24 * 60 * 60;
            if (time() > $expiry_timestamp) {
                // Attempt refresh even if expired, might still work shortly after
                $needs_refresh = true;
            } elseif (($expiry_timestamp - time()) < $seven_days_in_seconds) {
                $needs_refresh = true;
            }
        }

        // Get the current token directly
        $current_token = $account['access_token'];

        if ($needs_refresh) {
            $refreshed_data = $this->refresh_access_token($current_token);
            if (!is_wp_error($refreshed_data)) {
                // Update stored account details with refreshed token and expiry
                $account['access_token'] = $refreshed_data['access_token'];
                $account['token_expires_at'] = $refreshed_data['expires_at'];
                // Update the site option immediately
                apply_filters('dm_oauth', null, 'store', 'threads', $account);
                return $refreshed_data['access_token']; // Return the new plaintext token
            } else {
                // If refresh fails and token is already expired, return null
                if (isset($account['token_expires_at']) && time() > intval($account['token_expires_at'])) {
                    return null;
                }
                // Otherwise, return the old (but potentially soon-to-expire) token
                return $current_token;
            }
        }

        // If no refresh needed or attempt failed but token still valid, return current token
        return $current_token;
    }

    /**
     * Retrieves the stored Page ID.
     * Uses global site options for admin-only authentication.
     *
     * @return string|null Page ID or null if not found.
     */
    public function get_page_id(): ?string {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'threads');
        if (empty($account) || !is_array($account) || empty($account['page_id'])) {
            return null;
        }
        return $account['page_id'];
    }


    /**
     * Generates the authorization URL to redirect the user to.
     * Uses admin-global state management for consistent OAuth flow.
     *
     * @return string The authorization URL.
     */
    public function get_authorization_url(): string {
        $state = wp_create_nonce('dm_threads_oauth_state');
        // Store state in admin-global transient for verification
        set_transient('dm_threads_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

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
     * Handles the OAuth callback from Threads.
     * Verifies state, exchanges code for token, and stores credentials.
     *
     * @param string $code    Authorization code from Threads.
     * @param string $state   State parameter from Threads for verification.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(string $code, string $state): bool|\WP_Error {
        do_action('dm_log', 'debug', 'Handling Threads OAuth callback.');

        // 1. Verify state - use admin-global transient verification
        $stored_state = get_transient('dm_threads_oauth_state');
        delete_transient('dm_threads_oauth_state');
        
        if (empty($stored_state) || !wp_verify_nonce($state, 'dm_threads_oauth_state')) {
            do_action('dm_log', 'error', 'Threads OAuth Error: State mismatch or expired.');
            return new \WP_Error('threads_oauth_state_mismatch', __('Invalid or expired state parameter during Threads authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->get_redirect_uri(),
            'code'          => $code,
        ];

        // Use dm_request filter for Threads token exchange
        $result = apply_filters('dm_request', null, 'POST', self::TOKEN_URL, [
            'body' => $token_params,
        ], 'Threads OAuth');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Threads OAuth Error: Token request failed.', ['error' => $result['error']]);
            return new \WP_Error('threads_oauth_token_request_failed', __('HTTP error during token exchange with Threads.', 'data-machine'), $result['error']);
        }
        
        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error_description'] ?? $data['error'] ?? 'Failed to retrieve access token from Threads.';
            do_action('dm_log', 'error', 'Threads OAuth Error: Token exchange failed.', ['http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('threads_oauth_token_exchange_failed', $error_message, $data);
        }

        $initial_access_token = $data['access_token'];

        // 3. Exchange short-lived token for a long-lived one
        do_action('dm_log', 'debug', 'Threads OAuth: Exchanging short-lived token for long-lived token.');
        $exchange_params = [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => $this->get_client_secret(),
            'access_token'  => $initial_access_token, // Use the short-lived token here
        ];
        // The example used GET for this exchange
        $exchange_url = 'https://graph.threads.net/access_token?' . http_build_query($exchange_params);

        $exchange_result = apply_filters('dm_request', null, 'GET', $exchange_url, [], 'Threads OAuth');

        if (!$exchange_result['success']) {
            do_action('dm_log', 'error', 'Threads OAuth Error: Long-lived token exchange request failed (HTTP).', ['error' => $exchange_result['error']]);
            return new \WP_Error('threads_oauth_exchange_request_failed', __('HTTP error during long-lived token exchange with Threads.', 'data-machine'), $exchange_result['error']);
        }

        $exchange_body = $exchange_result['data'];
        $exchange_data = json_decode($exchange_body, true);
        $exchange_http_code = $exchange_result['status_code'];

        if ($exchange_http_code !== 200 || empty($exchange_data['access_token'])) {
            // Fail hard if the exchange doesn't succeed, as we need the long-lived token.
            $exchange_error_message = $exchange_data['error']['message'] ?? $exchange_data['error_description'] ?? 'Failed to retrieve long-lived access token from Threads.';
            do_action('dm_log', 'error', 'Threads OAuth Error: Long-lived token exchange failed (API).', ['http_code' => $exchange_http_code, 'response' => $exchange_body]);
            return new \WP_Error('threads_oauth_exchange_failed', $exchange_error_message, $exchange_data);
        }

        // Successfully exchanged for long-lived token
        do_action('dm_log', 'debug', 'Threads OAuth: Successfully exchanged for long-lived token.');
        $long_lived_access_token = $exchange_data['access_token'];
        $long_lived_expires_in = $exchange_data['expires_in'] ?? null; // Should be ~60 days in seconds
        $long_lived_token_type = $exchange_data['token_type'] ?? 'bearer';

        // 4. Prepare account details using the long-lived token
        $account_details = [
            'access_token'     => $long_lived_access_token,
            'expires_in'       => $long_lived_expires_in, // Store the long-lived expiry duration (for info)
            'token_type'       => $long_lived_token_type,
            'fb_user_id'       => null, // Store the authenticating FB User ID if needed
            'fb_user_name'     => 'Unknown', // Store the authenticating FB User name
            'page_id'          => null, // Store the ID of the target FB Page for posting
            'page_name'        => null, // Store the name of the target FB Page
            'authenticated_at' => time(),
            'token_expires_at' => isset($long_lived_expires_in) ? time() + intval($long_lived_expires_in) : null, // Calculate expiry timestamp
        ];

        // Use the long-lived token for fetching profile info
        $current_access_token = $long_lived_access_token; // Use the long-lived token

        // Fetch the /me node info using the long-lived token.
        // Based on testing, this should return the Page info (ID, name) when token has Threads scopes.
        $posting_entity_info = $this->get_facebook_user_profile($current_access_token);
        if (!is_wp_error($posting_entity_info) && isset($posting_entity_info['id'])) {
            $account_details['page_id'] = $posting_entity_info['id']; // Store the ID returned by /me as page_id
            $account_details['page_name'] = $posting_entity_info['name'] ?? 'Unknown Page/User';
            do_action('dm_log', 'debug', 'Fetched posting entity info from /me.', ['posting_entity_id' => $posting_entity_info['id'], 'posting_entity_name' => $account_details['page_name']]);
        } else {
            // Critical error if /me doesn't return the necessary ID
            do_action('dm_log', 'error', 'Threads OAuth Error: Failed to fetch posting entity info from /me endpoint.', [
                'error' => is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : '/me did not return an ID',
            ]);
            $error_message = is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : __('Could not retrieve the necessary profile ID using the access token.', 'data-machine');
            return new \WP_Error('threads_oauth_me_id_missing', $error_message);
        }

        // Store token directly

        // Update site option with all collected details for admin-only architecture
        apply_filters('dm_oauth', null, 'store', 'threads', $account_details);
        do_action('dm_log', 'debug', 'Threads account authenticated and token stored.', ['page_id' => $account_details['page_id']]);

        return true;
    }

    /**
     * Get Threads client ID from options
     *
     * @return string
     */
    private function get_client_id() {
        $config = apply_filters('dm_oauth', [], 'get_config', 'threads');
        return $config['app_id'] ?? '';
    }

    /**
     * Get Threads client secret from options
     *
     * @return string
     */
    private function get_client_secret() {
        $config = apply_filters('dm_oauth', [], 'get_config', 'threads');
        return $config['app_secret'] ?? '';
    }

    /**
     * Get redirect URI
     *
     * @return string
     */
    private function get_redirect_uri() {
        return apply_filters('dm_get_oauth_url', '', 'threads');
    }

    /**
     * Retrieves user profile information from Facebook Graph API.
     *
     * @param string $access_token Valid access token.
     * @return array|\WP_Error Profile data or WP_Error on failure.
     */
    private function get_facebook_user_profile(string $access_token): array|\WP_Error {
        // Use Facebook Graph API endpoint for /me
        $url = 'https://graph.facebook.com/v19.0/me?fields=id,name'; // Adjust version as needed
        do_action('dm_log', 'debug', 'Facebook Graph API: Fetching authenticating user profile.', ['url' => $url]);
        $result = apply_filters('dm_request', null, 'GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ], 'Threads Authentication');

        if (!$result['success']) {
             do_action('dm_log', 'error', 'Facebook Graph API Error: Profile fetch request failed.', ['error' => $result['error']]);
            return new \WP_Error('fb_profile_fetch_failed', $result['error']);
        }

        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook user profile.';
             do_action('dm_log', 'error', 'Facebook Graph API Error: Profile fetch failed.', ['http_code' => $http_code, 'response' => $body]);
             return new \WP_Error('fb_profile_fetch_failed', $error_message, $data);
        }

         if (empty($data['id'])) {
             do_action('dm_log', 'error', 'Facebook Graph API Error: Profile fetch response missing ID.', ['http_code' => $http_code, 'response' => $body]);
             return new \WP_Error('fb_profile_id_missing', __('Profile ID missing in response from Facebook Graph API.', 'data-machine'), $data);
         }

        do_action('dm_log', 'debug', 'Facebook Graph API: Profile fetched successfully.', ['profile_id' => $data['id']]);
        return $data; // Contains 'id' and 'name'
    }

    /**
     * Refreshes a long-lived Threads access token.
     *
     * @param string $access_token The current, valid (or recently expired) long-lived token.
     * @return array|\WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private function refresh_access_token(string $access_token): array|\WP_Error {
         $params = [
             'grant_type' => 'th_refresh_token', // Correct grant type for Threads
             'access_token' => $access_token,
         ];
         $url = self::REFRESH_URL . '?' . http_build_query($params);

         $result = apply_filters('dm_request', null, 'GET', $url, [], 'Threads OAuth');

         if (!$result['success']) {
             return new \WP_Error('threads_refresh_http_error', $result['error']);
         }

         $body = $result['data'];
         $data = json_decode($body, true);
         $http_code = $result['status_code'];

         if ($http_code !== 200 || empty($data['access_token'])) {
             $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to refresh Threads access token.';
             return new \WP_Error('threads_refresh_api_error', $error_message, $data);
         }

         // Calculate new expiry timestamp
         $expires_in = $data['expires_in'] ?? 3600 * 24 * 60; // Default to 60 days
         $expires_at = time() + intval($expires_in);

         return [
             'access_token' => $data['access_token'],
             'expires_at'   => $expires_at,
         ];
    }

    /**
     * Removes the authenticated Threads account.
     * Uses global site options for admin-only authentication.
     * Attempts to revoke the token via the Graph API first.
     *
     * @return bool True on success, false otherwise.
     */
    public function remove_account(): bool {
        // Try to get the stored token to attempt revocation
        $account = apply_filters('dm_oauth', [], 'retrieve', 'threads');
        $token = null;

        if (!empty($account) && is_array($account) && !empty($account['access_token'])) {
            $token = $account['access_token'];
        }

        if ($token) {
            // Attempt token revocation with Facebook Graph API (Threads uses Facebook infrastructure)
            $url = self::GRAPH_API_URL . '/me/permissions';
            $result = apply_filters('dm_request', null, 'DELETE', $url, [
                'body' => ['access_token' => $token],
            ], 'Threads Authentication');

            // Log success or failure of revocation, but don't stop deletion
            if (!$result['success'] || $result['status_code'] !== 200) {
                // Token revocation failed, but continue with local cleanup
                $error_details = !$result['success'] ? $result['error'] : 'HTTP ' . $result['status_code'];
                do_action('dm_log', 'error', 'Threads token revocation failed during account deletion.', [
                    'error' => $error_details
                ]);
            }
        }

        // Always attempt to delete the site option regardless of revocation success
        return apply_filters('dm_oauth', false, 'clear', 'threads');
    }

    /**
     * Retrieves the stored Threads account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'threads');
        if (empty($account) || !is_array($account)) {
            return null;
        }
        return $account;
    }

    /**
     * Handle OAuth callback from Threads.
     * Hooked to 'admin_post_dm_threads_oauth_callback'.
     */
    public function handle_oauth_callback() {
        // 1. Verify admin capability
        if (!current_user_can('manage_options')) {
             wp_die('Permission denied.');
        }

        // Check for error response first (user might deny access)
        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            $error_description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : 'No description provided.';
            do_action('dm_log', 'error', 'Threads OAuth Error (Callback Init): User denied access or error occurred.', ['error' => $error, 'description' => $error_description]);
            // Add an admin notice by storing in transient and display on API keys page
            set_transient('dm_oauth_error_threads', 'Threads authentication failed: ' . esc_html($error_description), 60);
            // Redirect back to the API keys page cleanly, removing error params
            wp_redirect(admin_url('admin.php?page=dm-pipelines&dm_oauth_status=error'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            do_action('dm_log', 'error', 'Threads OAuth Error: Missing code or state in callback.', ['query_params' => $_GET]);
            wp_redirect(add_query_arg('auth_error', 'missing_params', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Check user permissions (should be logged in to WP admin)
        if (!current_user_can('manage_options')) {
             do_action('dm_log', 'error', 'Threads OAuth Error: User does not have permission.');
             wp_redirect(add_query_arg('auth_error', 'permission_denied', admin_url('admin.php?page=dm-pipelines')));
             exit;
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));

        // Retrieve stored app credentials from global options
        $config = apply_filters('dm_oauth', [], 'get_config', 'threads');
        $app_id = $config['app_id'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        if (empty($app_id) || empty($app_secret)) {
            do_action('dm_log', 'error', 'Threads OAuth Error: App credentials not configured.');
            wp_redirect(add_query_arg('auth_error', 'config_missing', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Handle the callback
        $result = $this->handle_callback($code, $state);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            do_action('dm_log', 'error', 'Threads OAuth Callback Failed.', ['error_code' => $error_code, 'error_message' => $error_message]);
            set_transient('dm_oauth_error_threads', 'Threads authentication failed: ' . esc_html($error_message), 60);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&dm_oauth_status=error_token'));
        } else {
            do_action('dm_log', 'debug', 'Threads OAuth Callback Successful.');
            set_transient('dm_oauth_success_threads', 'Threads account connected successfully!', 60);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&dm_oauth_status=success'));
        }
        exit;
    }
}

