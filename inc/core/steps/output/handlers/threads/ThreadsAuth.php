<?php
/**
 * Handles Threads OAuth 2.0 authentication for the Threads output handler.
 *
 * Self-contained authentication system that provides all OAuth functionality
 * needed by the Threads output handler including credential management,
 * OAuth flow handling, and access token management.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/threads
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Threads;


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
    const USER_META_KEY = 'data_machine_threads_auth_account';

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
     * Get logger service via filter
     *
     * @return object|null Logger instance or null if not available
     */
    private function get_logger() {
        return apply_filters('dm_get_logger', null);
    }

    /**
     * Get encryption helper service via filter
     *
     * @return object|null EncryptionHelper instance or null if not available
     */
    private function get_encryption_helper() {
        return apply_filters('dm_get_encryption_helper', null);
    }

    /**
     * Checks if a user has valid Threads authentication
     *
     * @param int $user_id WordPress User ID
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(int $user_id): bool {
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
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
     * Retrieves the stored access token for a user.
     * Handles decryption and token refresh if needed.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Access token or null if not found/valid.
     */
    public function get_access_token(int $user_id): ?string {
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
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

        // Decrypt the current token first
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            return null;
        }
        $current_token = $encryption_helper->decrypt($account['access_token']);
        if ($current_token === false) {
            return null;
        }

        if ($needs_refresh) {
            $refreshed_data = $this->refresh_access_token($current_token, $user_id);
            if (!is_wp_error($refreshed_data)) {
                // Update stored account details with refreshed token and expiry
                $encryption_helper = $this->get_encryption_helper();
                if (!$encryption_helper) {
                    return null;
                }
                $account['access_token'] = $encryption_helper->encrypt($refreshed_data['access_token']);
                $account['token_expires_at'] = $refreshed_data['expires_at'];
                // Update the user meta immediately
                update_user_meta($user_id, self::USER_META_KEY, $account);
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

        // If no refresh needed or attempt failed but token still valid, return current decrypted token
        return $current_token;
    }

    /**
     * Retrieves the stored Page ID for a user.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Page ID or null if not found.
     */
    public function get_page_id(int $user_id): ?string {
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
        if (empty($account) || !is_array($account) || empty($account['page_id'])) {
            return null;
        }
        return $account['page_id'];
    }

    /**
     * Generates the authorization URL to redirect the user to.
     *
     * @param int $user_id WordPress User ID for state verification.
     * @return string The authorization URL.
     */
    public function get_authorization_url(int $user_id): string {
        $state = wp_create_nonce('dm_threads_oauth_state_' . $user_id);
        // Store state temporarily for verification on callback
        update_user_meta($user_id, 'dm_threads_oauth_state', $state);

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
     * @param int    $user_id WordPress User ID.
     * @param string $code    Authorization code from Threads.
     * @param string $state   State parameter from Threads for verification.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(int $user_id, string $code, string $state): bool|\WP_Error {
        $this->get_logger() && $this->get_logger()->info('Handling Threads OAuth callback.', ['user_id' => $user_id]);

        // 1. Verify state
        $stored_state = get_user_meta($user_id, 'dm_threads_oauth_state', true);
        delete_user_meta($user_id, 'dm_threads_oauth_state'); // Clean up state
        if (empty($stored_state) || !hash_equals($stored_state, $state)) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: State mismatch.', ['user_id' => $user_id]);
            return new \WP_Error('threads_oauth_state_mismatch', __('Invalid state parameter during Threads authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->get_redirect_uri(),
            'code'          => $code,
        ];

        // Use HttpService for external override capability
        $http_service = apply_filters('dm_get_http_service', null);
        if (!$http_service) {
            return new \WP_Error('threads_service_unavailable', __('HTTP service unavailable for Threads token exchange.', 'data-machine'));
        }

        $response = $http_service->post(self::TOKEN_URL, $token_params, [
            'timeout' => 15,
        ], 'Threads Token Exchange');

        if (is_wp_error($response)) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Token request failed.', ['user_id' => $user_id, 'error' => $response->get_error_message()]);
            return new \WP_Error('threads_oauth_token_request_failed', __('HTTP error during token exchange with Threads.', 'data-machine'), $response);
        }

        $body = $response['body'];
        $data = json_decode($body, true);
        $http_code = $response['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error_description'] ?? $data['error'] ?? 'Failed to retrieve access token from Threads.';
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Token exchange failed.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('threads_oauth_token_exchange_failed', $error_message, $data);
        }

        $initial_access_token = $data['access_token'];

        // 3. Exchange short-lived token for a long-lived one
        $this->get_logger() && $this->get_logger()->info('Threads OAuth: Exchanging short-lived token for long-lived token.', ['user_id' => $user_id]);
        $exchange_params = [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => $this->get_client_secret(),
            'access_token'  => $initial_access_token, // Use the short-lived token here
        ];
        // The example used GET for this exchange
        $exchange_url = 'https://graph.threads.net/access_token?' . http_build_query($exchange_params);

        $exchange_response = wp_remote_get($exchange_url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($exchange_response)) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Long-lived token exchange request failed (HTTP).', ['user_id' => $user_id, 'error' => $exchange_response->get_error_message()]);
            return new \WP_Error('threads_oauth_exchange_request_failed', __('HTTP error during long-lived token exchange with Threads.', 'data-machine'), $exchange_response);
        }

        $exchange_body = wp_remote_retrieve_body($exchange_response);
        $exchange_data = json_decode($exchange_body, true);
        $exchange_http_code = wp_remote_retrieve_response_code($exchange_response);

        if ($exchange_http_code !== 200 || empty($exchange_data['access_token'])) {
            // Fail hard if the exchange doesn't succeed, as we need the long-lived token.
            $exchange_error_message = $exchange_data['error']['message'] ?? $exchange_data['error_description'] ?? 'Failed to retrieve long-lived access token from Threads.';
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Long-lived token exchange failed (API).', ['user_id' => $user_id, 'http_code' => $exchange_http_code, 'response' => $exchange_body]);
            return new \WP_Error('threads_oauth_exchange_failed', $exchange_error_message, $exchange_data);
        }

        // Successfully exchanged for long-lived token
        $this->get_logger() && $this->get_logger()->info('Threads OAuth: Successfully exchanged for long-lived token.', ['user_id' => $user_id]);
        $long_lived_access_token = $exchange_data['access_token'];
        $long_lived_expires_in = $exchange_data['expires_in'] ?? null; // Should be ~60 days in seconds
        $long_lived_token_type = $exchange_data['token_type'] ?? 'bearer';

        // 4. Prepare account details using the long-lived token
        $account_details = [
            'access_token'     => $long_lived_access_token, // Will be encrypted below
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
        $current_access_token = $long_lived_access_token; // Use the raw long-lived token before encrypting it

        // Fetch the /me node info using the long-lived token.
        // Based on testing, this should return the Page info (ID, name) when token has Threads scopes.
        $posting_entity_info = $this->get_facebook_user_profile($current_access_token);
        if (!is_wp_error($posting_entity_info) && isset($posting_entity_info['id'])) {
            $account_details['page_id'] = $posting_entity_info['id']; // Store the ID returned by /me as page_id
            $account_details['page_name'] = $posting_entity_info['name'] ?? 'Unknown Page/User';
            $this->get_logger() && $this->get_logger()->info('Fetched posting entity info from /me.', ['user_id' => $user_id, 'posting_entity_id' => $posting_entity_info['id'], 'posting_entity_name' => $account_details['page_name']]);
        } else {
            // Critical error if /me doesn't return the necessary ID
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Failed to fetch posting entity info from /me endpoint.', [
                'user_id' => $user_id,
                'error' => is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : '/me did not return an ID',
            ]);
            $error_message = is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : __('Could not retrieve the necessary profile ID using the access token.', 'data-machine');
            return new \WP_Error('threads_oauth_me_id_missing', $error_message);
        }

        // Encrypt tokens before storing
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Encryption helper service unavailable.', ['user_id' => $user_id]);
            return new \WP_Error('threads_oauth_service_unavailable', __('Encryption service unavailable for storing Threads access token.', 'data-machine'));
        }
        $account_details['access_token'] = $encryption_helper->encrypt($account_details['access_token']);
        if ($account_details['access_token'] === false) {
             $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Failed to encrypt access token.', ['user_id' => $user_id]);
             return new \WP_Error('threads_oauth_encryption_failed', __('Failed to securely store the Threads access token.', 'data-machine'));
        }

        // Update user meta with all collected details
        update_user_meta($user_id, self::USER_META_KEY, $account_details);
        $this->get_logger() && $this->get_logger()->info('Threads account authenticated and token stored.', ['user_id' => $user_id, 'page_id' => $account_details['page_id']]);

        return true;
    }

    /**
     * Get Threads client ID from options
     *
     * @return string
     */
    private function get_client_id() {
        return get_option('threads_app_id', '');
    }

    /**
     * Get Threads client secret from options
     *
     * @return string
     */
    private function get_client_secret() {
        return get_option('threads_app_secret', '');
    }

    /**
     * Get redirect URI
     *
     * @return string
     */
    private function get_redirect_uri() {
        return admin_url('admin.php?page=dm-project-management&dm_oauth_callback=threads');
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
        $this->get_logger() && $this->get_logger()->debug('Facebook Graph API: Fetching authenticating user profile.', ['url' => $url]);
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
             $this->get_logger() && $this->get_logger()->error('Facebook Graph API Error: Profile fetch wp_remote_get failed.', ['error' => $response->get_error_message()]);
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook user profile.';
             $this->get_logger() && $this->get_logger()->error('Facebook Graph API Error: Profile fetch failed.', ['http_code' => $http_code, 'response' => $body]);
             return new \WP_Error('fb_profile_fetch_failed', $error_message, $data);
        }

         if (empty($data['id'])) {
             $this->get_logger() && $this->get_logger()->error('Facebook Graph API Error: Profile fetch response missing ID.', ['http_code' => $http_code, 'response' => $body]);
             return new \WP_Error('fb_profile_id_missing', __('Profile ID missing in response from Facebook Graph API.', 'data-machine'), $data);
         }

        $this->get_logger() && $this->get_logger()->debug('Facebook Graph API: Profile fetched successfully.', ['profile_id' => $data['id']]);
        return $data; // Contains 'id' and 'name'
    }

    /**
     * Refreshes a long-lived Threads access token.
     *
     * @param string $access_token The current, valid (or recently expired) long-lived token.
     * @param int $user_id WP User ID for logging context.
     * @return array|\WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private function refresh_access_token(string $access_token, int $user_id): array|\WP_Error {
         $params = [
             'grant_type' => 'th_refresh_token', // Correct grant type for Threads
             'access_token' => $access_token,
         ];
         $url = self::REFRESH_URL . '?' . http_build_query($params);

         $response = wp_remote_get($url, ['timeout' => 15]);

         if (is_wp_error($response)) {
             return new \WP_Error('threads_refresh_http_error', $response->get_error_message(), $response);
         }

         $body = wp_remote_retrieve_body($response);
         $data = json_decode($body, true);
         $http_code = wp_remote_retrieve_response_code($response);

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
     * Removes the authenticated Threads account for the user.
     * Attempts to revoke the token via the Graph API first.
     *
     * @param int $user_id WordPress User ID.
     * @return bool True on success, false otherwise.
     */
    public static function remove_account(int $user_id): bool {
        // Try to get the stored token to attempt revocation
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
        $token = null;

        if (!empty($account) && is_array($account) && !empty($account['access_token'])) {
            $encryption_helper = apply_filters('dm_get_encryption_helper', null);
            if ($encryption_helper) {
                $token = $encryption_helper->decrypt($account['access_token']);
            }
        }

        if ($token) {
            // Attempt token revocation with Facebook Graph API (Threads uses Facebook infrastructure)
            $url = self::GRAPH_API_URL . '/me/permissions';
            $response = wp_remote_request($url, [
                'method' => 'DELETE',
                'body' => ['access_token' => $token],
                'timeout' => 10,
            ]);

            // Log success or failure of revocation, but don't stop deletion
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                // Token revocation failed, but continue with local cleanup
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Threads token revocation failed for user ' . $user_id);
                }
            }
        }

        // Always attempt to delete the local user meta regardless of revocation success
        return delete_user_meta($user_id, self::USER_META_KEY);
    }

    /**
     * Retrieves the stored Threads account details for a user.
     *
     * @param int $user_id WordPress User ID.
     * @return array|null Account details array or null if not found/invalid.
     */
    public static function get_account_details(int $user_id): ?array {
        $account = get_user_meta($user_id, self::USER_META_KEY, true);
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
        if (!isset($_GET['page']) || $_GET['page'] !== 'dm-project-management' || !isset($_GET['dm_oauth_callback']) || $_GET['dm_oauth_callback'] !== 'threads') {
            return;
        }

        // Check for error response first (user might deny access)
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : 'No description provided.';
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error (Callback Init): User denied access or error occurred.', ['error' => $error, 'description' => $error_description]);
            // Add an admin notice by storing in transient and display on API keys page
            set_transient('dm_oauth_error_threads', 'Threads authentication failed: ' . esc_html($error_description), 60);
            // Redirect back to the API keys page cleanly, removing error params
            wp_redirect(admin_url('admin.php?page=dm-project-management&dm_oauth_status=error'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: Missing code or state in callback.', ['query_params' => $_GET]);
            wp_redirect(add_query_arg('auth_error', 'missing_params', admin_url('admin.php?page=dm-project-management')));
            exit;
        }

        // Check user permissions (should be logged in to WP admin)
        if (!current_user_can('manage_options')) {
             $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: User does not have permission.', ['user_id' => get_current_user_id()]);
             wp_redirect(add_query_arg('auth_error', 'permission_denied', admin_url('admin.php?page=dm-project-management')));
             exit;
        }

        $user_id = get_current_user_id();
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);

        // Retrieve stored app credentials from global options
        $app_id = get_option('threads_app_id');
        $app_secret = get_option('threads_app_secret');
        if (empty($app_id) || empty($app_secret)) {
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Error: App credentials not configured.', ['user_id' => $user_id]);
            wp_redirect(add_query_arg('auth_error', 'config_missing', admin_url('admin.php?page=dm-project-management')));
            exit;
        }

        // Handle the callback
        $result = $this->handle_callback($user_id, $code, $state);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $this->get_logger() && $this->get_logger()->error('Threads OAuth Callback Failed.', ['user_id' => $user_id, 'error_code' => $error_code, 'error_message' => $error_message]);
            set_transient('dm_oauth_error_threads', 'Threads authentication failed: ' . esc_html($error_message), 60);
            wp_redirect(admin_url('admin.php?page=dm-project-management&dm_oauth_status=error_token'));
        } else {
            $this->get_logger() && $this->get_logger()->info('Threads OAuth Callback Successful.', ['user_id' => $user_id]);
            set_transient('dm_oauth_success_threads', 'Threads account connected successfully!', 60);
            wp_redirect(admin_url('admin.php?page=dm-project-management&dm_oauth_status=success'));
        }
        exit;
    }
}

