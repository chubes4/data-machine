<?php
/**
 * Handles Reddit OAuth 2.0 Authorization Code Grant flow.
 *
 * Admin-global authentication system providing OAuth functionality with site-level
 * credential storage, token refresh management, and Reddit API access.
 * Uses centralized logging and filter-based HTTP requests.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/fetch/reddit
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Fetch\Reddit;

use DataMachine\Engine\Logger;

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
        // 1. Verify admin capability (admin_post_* hook already requires admin authentication)
        if (!current_user_can('manage_options')) {
             wp_die('Permission denied.');
        }

        // 2. Get Client ID
        $client_id = get_option('reddit_oauth_client_id');
        if (empty($client_id)) {
            // Redirect back with error
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_missing_client_id'));
            exit;
        }

        // 3. Define Redirect URI (MUST match the one registered on Reddit Dev App settings)
        $redirect_uri = admin_url('admin-post.php?action=dm_reddit_oauth_callback');

        // 4. Generate State parameter
        $state = wp_create_nonce('dm_reddit_oauth_state');
        // Store state temporarily to verify on callback using admin-global transient
        set_transient('dm_reddit_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

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
        
        if (!current_user_can('manage_options')) {
             wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_permission_denied'));
             exit;
        }
        $stored_state = get_transient('dm_reddit_oauth_state');
        delete_transient('dm_reddit_oauth_state'); // Clean up transient immediately

        // Verify State
        if ( empty($state_received) || empty($stored_state) || !hash_equals($stored_state, $state_received) ) {
            do_action('dm_log', 'error', 'Reddit OAuth Error: State mismatch or missing.', ['received' => $state_received]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_state_mismatch'));
            exit;
        }

        // Check for errors returned by Reddit
        if (isset($_GET['error'])) {
            $error_code = sanitize_key($_GET['error']);
            do_action('dm_log', 'error', 'Reddit OAuth Error: Received error from Reddit.', ['error' => $error_code]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_' . $error_code));
            exit;
        }

        // Check for authorization code
        if (!isset($_GET['code'])) {
            do_action('dm_log', 'error', 'Reddit OAuth Error: Authorization code missing in callback.');
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_missing_code'));
            exit;
        }
        // Security: Use sanitize_key() for OAuth authorization codes (tokens should be treated as keys)
        $code = sanitize_key($_GET['code']);

        // --- 2. Exchange Code for Tokens --- 
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             do_action('dm_log', 'error', 'Reddit OAuth Error: Client ID or Secret not configured for token exchange.');
             wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_missing_credentials'));
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
        ];

        // Make the POST request
        $result = apply_filters('dm_request', null, 'POST', $token_url, $args, 'Reddit OAuth');

        // --- 3. Process Token Response --- 
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Reddit OAuth Error: Failed to connect to token endpoint.', ['error' => $result['error']]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_token_request_failed'));
            exit;
        }

        $response_code = $result['status_code'];
        $body = $result['data'];
        $data = json_decode($body, true);

        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
            do_action('dm_log', 'error', 'Reddit OAuth Error: Failed to retrieve access token.', [
                'response_code' => $response_code,
                'error_detail'  => $error_detail,
                'response_body' => $body // Log full body for debugging
            ]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=reddit_token_retrieval_error'));
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
        ];
        $identity_result = apply_filters('dm_request', null, 'GET', $identity_url, $identity_args, 'Reddit Authentication');
        $identity_username = null;
        if ($identity_result['success'] && $identity_result['status_code'] === 200) {
            $identity_body = $identity_result['data'];
            $identity_data = json_decode($identity_body, true);
            if (!empty($identity_data['name'])) {
                $identity_username = $identity_data['name'];
            } else {
                 do_action('dm_log', 'warning', 'Reddit OAuth Warning: Could not get username from /api/v1/me, but token obtained.', ['identity_response' => $identity_body]);
            }
        } else {
             $identity_error = $identity_result['success'] ? 'HTTP ' . $identity_result['status_code'] : $identity_result['error'];
             do_action('dm_log', 'warning', 'Reddit OAuth Warning: Failed to get user identity after getting token.', ['error' => $identity_error]);
        }

        // --- 5. Store Tokens and User Info --- 
        // Store the tokens directly
        $account_data = [
            'username'           => $identity_username, // Might be null
            'access_token'       => $access_token,
            'refresh_token'      => $refresh_token,
            'token_expires_at'   => $token_expires_at,
            'scope'              => $scope_granted,
            'last_refreshed_at'  => time()
        ];

        // Store as admin-only option for global Reddit authentication
        update_option('reddit_auth_data', $account_data);

        // --- 6. Redirect on Success --- 
        wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_success=reddit'));
        exit;
    }

    /**
     * Attempts to refresh the Reddit access token using the stored refresh token.
     *
     * @return bool True on successful refresh and update, false otherwise.
     */
    public function refresh_token(): bool {
        do_action('dm_log', 'debug', 'Attempting Reddit token refresh for admin authentication.');

        $reddit_account = get_option('reddit_auth_data', []);
        if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['refresh_token'])) {
            do_action('dm_log', 'error', 'Reddit Token Refresh Error: Refresh token not found in admin options.');
            return false;
        }
        // Get the refresh token directly
        $refresh_token = $reddit_account['refresh_token'];
        if (empty($refresh_token)) {
            do_action('dm_log', 'error', 'Reddit Token Refresh Error: Refresh token not found.');
            return false;
        }

        // Get credentials needed for Basic Auth
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             do_action('dm_log', 'error', 'Reddit Token Refresh Error: Client ID or Secret not configured.');
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
        ];

        $result = apply_filters('dm_request', null, 'POST', $token_url, $args, 'Reddit OAuth');

        // --- Process Refresh Response ---
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Reddit Token Refresh Error: Request failed.', ['error' => $result['error']]);
            return false;
        }

        $response_code = $result['status_code'];
        $body = $result['data'];
        $data = json_decode($body, true);

        // Check for errors or missing new access token
        // Reddit might return 400 Bad Request if refresh token is invalid/revoked
        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
             do_action('dm_log', 'error', 'Reddit Token Refresh Error: Failed to retrieve new access token.', [
                'response_code' => $response_code,
                'error_detail'  => $error_detail,
                'response_body' => $body
            ]);
             // If refresh fails (e.g., token revoked), clear stored data to force re-auth
             delete_option('reddit_auth_data');
            return false;
        }

        // --- Update Stored Data on Success ---
        $new_access_token  = $data['access_token'];
        // Reddit might return a new refresh token, but often returns the same one. Use original if not provided.
        $new_refresh_token = $data['refresh_token'] ?? $refresh_token;
        $new_expires_in    = $data['expires_in'] ?? 3600;
        $new_scope_granted = $data['scope'] ?? $reddit_account['scope'] ?? ''; // Keep old scope if not returned
        $new_token_expires_at = time() + intval($new_expires_in);

        // Store the tokens directly
        $updated_account_data = [
            'username'           => $reddit_account['username'] ?? null, // Keep existing username
            'access_token'       => $new_access_token,
            'refresh_token'      => $new_refresh_token,
            'token_expires_at'   => $new_token_expires_at,
            'scope'              => $new_scope_granted,
            'last_refreshed_at'  => time() // Update refresh time
        ];

        update_option('reddit_auth_data', $updated_account_data);
        do_action('dm_log', 'debug', 'Reddit token refreshed successfully.', ['new_expiry' => gmdate('Y-m-d H:i:s', $new_token_expires_at)]);
        return true;
    }

    /**
     * Checks if admin has valid Reddit authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = get_option('reddit_auth_data', []);
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['refresh_token']);
    }

    /**
     * Gets Reddit account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not authenticated
     */
    public function get_account_details(): ?array {
        $account = get_option('reddit_auth_data', []);
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
            do_action('dm_log', 'warning', 'Reddit account exists but all details are missing', [
                'has_access_token' => !empty($account['access_token']),
                'available_keys' => array_keys($account)
            ]);
        }
        
        return $details;
    }

} // End class

