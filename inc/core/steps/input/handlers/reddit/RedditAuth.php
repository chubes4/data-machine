<?php
/**
 * Handles Reddit OAuth 2.0 Authorization Code Grant flow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/input/reddit
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\Reddit;

use DataMachine\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RedditAuth {

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
     * Get encryption helper service via filter
     *
     * @return object|null EncryptionHelper instance or null if not available
     */
    private function get_encryption_helper() {
        return apply_filters('dm_get_encryption_helper', null);
    }

    /**
     * Registers the necessary WordPress action hooks.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_reddit_oauth_init', array($this, 'handle_oauth_init'));
        add_action('admin_post_dm_reddit_oauth_callback', array($this, 'handle_oauth_callback'));
    }

    /**
     * Handles the initiation of the Reddit OAuth flow.
     * Hooked to 'admin_post_dm_reddit_oauth_init'.
     */
    public function handle_oauth_init() {
        // 1. Verify Nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'dm_reddit_oauth_init_nonce')) {
            wp_die('Security check failed (Nonce mismatch). Please try initiating the connection again from the API Keys page.');
        }

        // Ensure user has capability
        if (!current_user_can('manage_options')) {
             wp_die('Permission denied.');
        }

        // 2. Get Client ID
        $client_id = get_option('reddit_oauth_client_id');
        if (empty($client_id)) {
            // Redirect back with error
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_missing_client_id'));
            exit;
        }

        // 3. Define Redirect URI (MUST match the one registered on Reddit Dev App settings)
        $redirect_uri = admin_url('admin-post.php?action=dm_reddit_oauth_callback');

        // 4. Generate State parameter
        $state = wp_create_nonce('reddit_oauth_state_' . get_current_user_id());
        // Store state temporarily to verify on callback (e.g., in user meta or transient)
        // Transients are good for short-lived verification data.
        set_transient('dm_reddit_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS);

        // 5. Define Scopes
        $scope = 'identity read'; // Request read access and user identity

        // 6. Construct Authorization URL
        $authorize_url = 'https://www.reddit.com/api/v1/authorize?' . http_build_query([
            'client_id'     => $client_id,
            'response_type' => 'code',
            'state'         => $state,
            'redirect_uri'  => $redirect_uri,
            'duration'      => 'permanent',
            'scope'         => $scope
        ]);

        // 7. Redirect User
        wp_redirect($authorize_url);
        exit;
    }

    /**
     * Handles the callback from Reddit after user authorization.
     * Hooked to 'admin_post_dm_reddit_oauth_callback'.
     */
    public function handle_oauth_callback() {
        // --- 1. Verify State and Check for Errors --- 
        $state_received = sanitize_key($_GET['state'] ?? '');
        
        if ( !is_user_logged_in() ) {
             wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_not_logged_in'));
             exit;
        }
        $user_id = get_current_user_id();
        $stored_state = get_transient('dm_reddit_oauth_state_' . $user_id);
        delete_transient('dm_reddit_oauth_state_' . $user_id); // Clean up transient immediately

        // Verify State
        if ( empty($state_received) || empty($stored_state) || !hash_equals($stored_state, $state_received) ) {
            $this->get_logger()->error('Reddit OAuth Error: State mismatch or missing.', ['received' => $state_received, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_state_mismatch'));
            exit;
        }

        // Check for errors returned by Reddit
        if (isset($_GET['error'])) {
            $error_code = sanitize_key($_GET['error']);
            $this->get_logger()->error('Reddit OAuth Error: Received error from Reddit.', ['error' => $error_code, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_' . $error_code));
            exit;
        }

        // Check for authorization code
        if (!isset($_GET['code'])) {
            $this->get_logger()->error('Reddit OAuth Error: Authorization code missing in callback.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_missing_code'));
            exit;
        }
        // Security: Use sanitize_key() for OAuth authorization codes (tokens should be treated as keys)
        $code = sanitize_key($_GET['code']);

        // --- 2. Exchange Code for Tokens --- 
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             $this->get_logger()->error('Reddit OAuth Error: Client ID or Secret not configured for token exchange.', ['user_id' => $user_id]);
             wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_missing_credentials'));
             exit;
        }

        $token_url = 'https://www.reddit.com/api/v1/access_token';
        $redirect_uri = admin_url('admin-post.php?action=dm_reddit_oauth_callback'); // Must match exactly
        $developer_username = get_option('reddit_developer_username', 'DataMachinePlugin'); // Fallback needed

        // Prepare request arguments
        $args = [
            'method'    => 'POST',
            'headers'   => [
                // HTTP Basic Auth: base64(client_id:client_secret)
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                // Unique User-Agent is important!
                'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (by /u/' . $developer_username . ')'
            ],
            'body'      => [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $redirect_uri,
            ],
            'timeout' => 15, // seconds
        ];

        // Make the POST request
        $response = wp_remote_post($token_url, $args);

        // --- 3. Process Token Response --- 
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->get_logger()->error('Reddit OAuth Error: Failed to connect to token endpoint.', ['error' => $error_message, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_token_request_failed'));
            exit;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
            $this->get_logger()->error('Reddit OAuth Error: Failed to retrieve access token.', [
                'response_code' => $response_code,
                'error_detail'  => $error_detail,
                'response_body' => $body, // Log full body for debugging
                'user_id'       => $user_id
            ]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_token_retrieval_error'));
            exit;
        }

        // Extract token data
        $access_token  = $data['access_token'];
        $refresh_token = $data['refresh_token'] ?? null; // May not be present if duration wasn't permanent
        $expires_in    = $data['expires_in'] ?? 3600; // Default to 1 hour if missing
        $scope_granted = $data['scope'] ?? '';
        $token_expires_at = time() + intval($expires_in);

         // --- 4. Get User Identity --- 
        $identity_url = 'https://oauth.reddit.com/api/v1/me';
        $identity_args = [
            'method'  => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                 'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (by /u/' . $developer_username . ')'
            ],
            'timeout' => 10,
        ];
        $identity_response = wp_remote_get($identity_url, $identity_args);
        $identity_username = null;
        if (!is_wp_error($identity_response) && wp_remote_retrieve_response_code($identity_response) === 200) {
            $identity_body = wp_remote_retrieve_body($identity_response);
            $identity_data = json_decode($identity_body, true);
            if (!empty($identity_data['name'])) {
                $identity_username = $identity_data['name'];
            } else {
                 $this->get_logger()->warning('Reddit OAuth Warning: Could not get username from /api/v1/me, but token obtained.', ['user_id' => $user_id, 'identity_response' => $identity_body]);
            }
        } else {
             $identity_error = is_wp_error($identity_response) ? $identity_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($identity_response);
             $this->get_logger()->warning('Reddit OAuth Warning: Failed to get user identity after getting token.', ['error' => $identity_error, 'user_id' => $user_id]);
        }

        // --- 5. Store Tokens and User Info (Encrypted) --- 
        // Encrypt the tokens before storing
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $this->get_logger() && $this->get_logger()->error('Reddit OAuth Error: Encryption helper service unavailable.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_service_unavailable'));
            exit;
        }
        $encrypted_access_token = $encryption_helper->encrypt($access_token);
        $encrypted_refresh_token = $refresh_token ? $encryption_helper->encrypt($refresh_token) : null;
        
        if ($encrypted_access_token === false || ($refresh_token && $encrypted_refresh_token === false)) {
            $this->get_logger()?->error('Reddit OAuth Error: Failed to encrypt tokens.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=reddit_encryption_failed'));
            exit;
        }
        
        $account_data = [
            'username'           => $identity_username, // Might be null
            'access_token'       => $encrypted_access_token,      // Store encrypted!
            'refresh_token'      => $encrypted_refresh_token,     // Store encrypted!
            'token_expires_at'   => $token_expires_at,
            'scope'              => $scope_granted,
            'last_refreshed_at'  => time()
        ];

        // Store against the current WP user. Assumes one Reddit auth per WP user.
        update_user_meta($user_id, 'data_machine_reddit_account', $account_data);

        // --- 6. Redirect on Success --- 
        wp_redirect(admin_url('admin.php?page=dm-project-management&auth_success=reddit'));
        exit;
    }

    /**
     * Attempts to refresh the Reddit access token using the stored refresh token.
     *
     * @param int $user_id The WordPress user ID whose token needs refreshing.
     * @return bool True on successful refresh and update, false otherwise.
     */
    public function refresh_token(int $user_id): bool {
        $this->get_logger()->debug('Attempting Reddit token refresh.', ['user_id' => $user_id]);

        $reddit_account = get_user_meta($user_id, 'data_machine_reddit_account', true);
        if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['refresh_token'])) {
            $this->get_logger()->error('Reddit Token Refresh Error: Refresh token not found in user meta.', ['user_id' => $user_id]);
            return false;
        }
        // Decrypt the refresh token
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $this->get_logger() && $this->get_logger()->error('Reddit Token Refresh Error: Encryption helper service unavailable.', ['user_id' => $user_id]);
            return false;
        }
        $refresh_token = $encryption_helper->decrypt($reddit_account['refresh_token']);
        if ($refresh_token === false) {
            $this->get_logger()->error('Reddit Token Refresh Error: Failed to decrypt refresh token.', ['user_id' => $user_id]);
            return false;
        }

        // Get credentials needed for Basic Auth
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             $this->get_logger()->error('Reddit Token Refresh Error: Client ID or Secret not configured.', ['user_id' => $user_id]);
             return false; // Cannot proceed
        }
        $developer_username = get_option('reddit_developer_username', 'DataMachinePlugin');

        // --- Make Refresh Request ---
        $token_url = 'https://www.reddit.com/api/v1/access_token';
        $args = [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'User-Agent'    => 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (by /u/' . $developer_username . ')'
            ],
            'body'      => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_post($token_url, $args);

        // --- Process Refresh Response ---
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->get_logger()->error('Reddit Token Refresh Error: Request failed.', ['error' => $error_message, 'user_id' => $user_id]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for errors or missing new access token
        // Reddit might return 400 Bad Request if refresh token is invalid/revoked
        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
             $this->get_logger()->error('Reddit Token Refresh Error: Failed to retrieve new access token.', [
                'response_code' => $response_code,
                'error_detail'  => $error_detail,
                'response_body' => $body,
                'user_id'       => $user_id
            ]);
             // If refresh fails (e.g., token revoked), clear stored data to force re-auth
             delete_user_meta($user_id, 'data_machine_reddit_account');
            return false;
        }

        // --- Update Stored Data on Success ---
        $new_access_token  = $data['access_token'];
        // Reddit might return a new refresh token, but often returns the same one. Use original if not provided.
        $new_refresh_token = $data['refresh_token'] ?? $refresh_token;
        $new_expires_in    = $data['expires_in'] ?? 3600;
        $new_scope_granted = $data['scope'] ?? $reddit_account['scope'] ?? ''; // Keep old scope if not returned
        $new_token_expires_at = time() + intval($new_expires_in);

        // Encrypt the tokens before storing
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $this->get_logger() && $this->get_logger()->error('Reddit Token Refresh Error: Encryption helper service unavailable during token storage.', ['user_id' => $user_id]);
            return false;
        }
        $encrypted_new_access_token = $encryption_helper->encrypt($new_access_token);
        $encrypted_new_refresh_token = $encryption_helper->encrypt($new_refresh_token);
        
        if ($encrypted_new_access_token === false || $encrypted_new_refresh_token === false) {
            $this->get_logger()->error('Reddit Token Refresh Error: Failed to encrypt new tokens.', ['user_id' => $user_id]);
            return false;
        }

        $updated_account_data = [
            'username'           => $reddit_account['username'] ?? null, // Keep existing username
            'access_token'       => $encrypted_new_access_token,
            'refresh_token'      => $encrypted_new_refresh_token,
            'token_expires_at'   => $new_token_expires_at,
            'scope'              => $new_scope_granted,
            'last_refreshed_at'  => time() // Update refresh time
        ];

        update_user_meta($user_id, 'data_machine_reddit_account', $updated_account_data);
        $this->get_logger()->debug('Reddit token refreshed successfully.', ['user_id' => $user_id, 'new_expiry' => gmdate('Y-m-d H:i:s', $new_token_expires_at)]);
        return true;
    }

    /**
     * Checks if a user has valid Reddit authentication
     *
     * @param int $user_id WordPress User ID
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(int $user_id): bool {
        $account = get_user_meta($user_id, 'data_machine_reddit_account', true);
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['refresh_token']);
    }

    /**
     * Gets Reddit account details for a user
     *
     * @param int $user_id WordPress User ID
     * @return array|null Account details array or null if not authenticated
     */
    public static function get_account_details(int $user_id): ?array {
        $account = get_user_meta($user_id, 'data_machine_reddit_account', true);
        if (empty($account) || !is_array($account) || empty($account['access_token'])) {
            return null;
        }
        
        // Return formatted account details for display
        $details = [];
        if (!empty($account['username'])) {
            $details['username'] = $account['username'];
        }
        if (!empty($account['scope'])) {
            $details['scope'] = $account['scope'];
        }
        if (!empty($account['last_refreshed_at'])) {
            $details['last_refreshed'] = gmdate('Y-m-d H:i:s', $account['last_refreshed_at']);
        }
        
        // Log when account exists but details are missing
        if (empty($details)) {
            $logger = apply_filters('dm_get_logger', null);
            $logger?->warning('Reddit account exists but all details are missing', [
                'user_id' => $user_id,
                'has_access_token' => !empty($account['access_token']),
                'available_keys' => array_keys($account)
            ]);
        }
        
        return $details;
    }

} // End class

