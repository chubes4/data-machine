<?php
/**
 * Handles Reddit OAuth 2.0 Authorization Code Grant flow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/oauth
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reddit {

    /**
     * Logger instance
     * @var Logger
     */
    private $logger;

    /**
     * Initialize hooks and dependencies.
     */
    public function __construct($logger) {
        $this->logger = $logger;
        // Add hooks for the OAuth flow actions
        // Note: These hooks need to be added where this class is instantiated.
        // Example: add_action('admin_post_dm_reddit_oauth_init', array($this, 'handle_oauth_init'));
        // Example: add_action('admin_post_nopriv_dm_reddit_oauth_callback', array($this, 'handle_oauth_callback')); // Allow callback for logged-out users temporarily?
        // Example: add_action('admin_post_dm_reddit_oauth_callback', array($this, 'handle_oauth_callback'));
    }

    /**
     * Registers the necessary WordPress action hooks.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_reddit_oauth_init', array($this, 'handle_oauth_init'));
        add_action('admin_post_dm_reddit_oauth_callback', array($this, 'handle_oauth_callback'));
        // If you need the callback to work even if the user somehow got logged out during the flow,
        // you might need add_action('admin_post_nopriv_dm_reddit_oauth_callback', ...); but ensure security.
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
        if (!current_user_can('manage_options')) { // Or a more specific capability
             wp_die('Permission denied.');
        }

        // 2. Get Client ID
        $client_id = get_option('reddit_oauth_client_id');
        if (empty($client_id)) {
            // Redirect back with error
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_missing_client_id'));
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
        // Need user context if verifying state nonce tied to user ID
        // If the callback could potentially happen when logged out, 
        // we might need a different way to link state, but let's assume user is logged in.
        if ( !is_user_logged_in() ) {
             wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_not_logged_in'));
             exit;
        }
        $user_id = get_current_user_id();
        $stored_state = get_transient('dm_reddit_oauth_state_' . $user_id);
        delete_transient('dm_reddit_oauth_state_' . $user_id); // Clean up transient immediately

        // Verify State
        if ( empty($state_received) || empty($stored_state) || !hash_equals($stored_state, $state_received) ) {
            $this->logger->error('Reddit OAuth Error: State mismatch or missing.', ['received' => $state_received, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_state_mismatch'));
            exit;
        }

        // Check for errors returned by Reddit
        if (isset($_GET['error'])) {
            $error_code = sanitize_key($_GET['error']);
            $this->logger->error('Reddit OAuth Error: Received error from Reddit.', ['error' => $error_code, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_' . $error_code));
            exit;
        }

        // Check for authorization code
        if (!isset($_GET['code'])) {
            $this->logger->error('Reddit OAuth Error: Authorization code missing in callback.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_missing_code'));
            exit;
        }
        $code = sanitize_text_field($_GET['code']);

        // --- 2. Exchange Code for Tokens --- 
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             $this->logger->error('Reddit OAuth Error: Client ID or Secret not configured for token exchange.', ['user_id' => $user_id]);
             wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_missing_credentials'));
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
            $this->logger->error('Reddit OAuth Error: Failed to connect to token endpoint.', ['error' => $error_message, 'user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_token_request_failed'));
            exit;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
            $this->logger->error('Reddit OAuth Error: Failed to retrieve access token.', [
                'response_code' => $response_code,
                'error_detail'  => $error_detail,
                'response_body' => $body, // Log full body for debugging
                'user_id'       => $user_id
            ]);
            wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=reddit_token_retrieval_error'));
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
                 $this->logger->warning('Reddit OAuth Warning: Could not get username from /api/v1/me, but token obtained.', ['user_id' => $user_id, 'identity_response' => $identity_body]);
            }
        } else {
             $identity_error = is_wp_error($identity_response) ? $identity_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($identity_response);
             $this->logger->warning('Reddit OAuth Warning: Failed to get user identity after getting token.', ['error' => $identity_error, 'user_id' => $user_id]);
        }

        // --- 5. Store Tokens and User Info --- 
        $account_data = [
            'username'           => $identity_username, // Might be null
            'access_token'       => $access_token,      // Store securely!
            'refresh_token'      => $refresh_token,     // Store securely!
            'token_expires_at'   => $token_expires_at,
            'scope'              => $scope_granted,
            'last_refreshed_at'  => time()
        ];

        // Store against the current WP user. Assumes one Reddit auth per WP user.
        update_user_meta($user_id, 'data_machine_reddit_account', $account_data);

        // --- 6. Redirect on Success --- 
        wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_success=reddit'));
        exit;
    }

    /**
     * Attempts to refresh the Reddit access token using the stored refresh token.
     *
     * @param int $user_id The WordPress user ID whose token needs refreshing.
     * @return bool True on successful refresh and update, false otherwise.
     */
    public function refresh_token(int $user_id): bool {
        $this->logger->info('Attempting Reddit token refresh.', ['user_id' => $user_id]);

        $reddit_account = get_user_meta($user_id, 'data_machine_reddit_account', true);
        if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['refresh_token'])) {
            $this->logger->error('Reddit Token Refresh Error: Refresh token not found in user meta.', ['user_id' => $user_id]);
            // Cannot refresh without a refresh token. User needs full re-auth.
            // Optionally delete the corrupted/incomplete meta here?
            // delete_user_meta($user_id, 'data_machine_reddit_account');
            return false;
        }
        $refresh_token = $reddit_account['refresh_token'];

        // Get credentials needed for Basic Auth
        $client_id = get_option('reddit_oauth_client_id');
        $client_secret = get_option('reddit_oauth_client_secret');
        if (empty($client_id) || empty($client_secret)) {
             $this->logger->error('Reddit Token Refresh Error: Client ID or Secret not configured.', ['user_id' => $user_id]);
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
            $this->logger->error('Reddit Token Refresh Error: Request failed.', ['error' => $error_message, 'user_id' => $user_id]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for errors or missing new access token
        // Reddit might return 400 Bad Request if refresh token is invalid/revoked
        if ($response_code !== 200 || empty($data['access_token'])) {
            $error_detail = isset($data['error']) ? $data['error'] : 'Unknown reason';
             $this->logger->error('Reddit Token Refresh Error: Failed to retrieve new access token.', [
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

        $updated_account_data = [
            'username'           => $reddit_account['username'] ?? null, // Keep existing username
            'access_token'       => $new_access_token,
            'refresh_token'      => $new_refresh_token,
            'token_expires_at'   => $new_token_expires_at,
            'scope'              => $new_scope_granted,
            'last_refreshed_at'  => time() // Update refresh time
        ];

        update_user_meta($user_id, 'data_machine_reddit_account', $updated_account_data);
        $this->logger->info('Reddit token refreshed successfully.', ['user_id' => $user_id, 'new_expiry' => date('Y-m-d H:i:s', $new_token_expires_at)]);
        return true;
    }

} // End class 