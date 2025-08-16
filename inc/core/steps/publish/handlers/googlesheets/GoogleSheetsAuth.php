<?php
/**
 * Handles Google Sheets OAuth 2.0 authentication for the Google Sheets publish handler.
 *
 * Admin-global authentication system providing OAuth functionality with site-level
 * credential storage, refresh token management, and Google Sheets API access.
 * Uses filter-based HTTP requests and centralized logging.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\GoogleSheets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GoogleSheetsAuth {

    const OAUTH_CALLBACK_ACTION = 'dm_googlesheets_oauth_callback';
    const STATE_TRANSIENT_PREFIX = 'dm_googlesheets_state_'; // Prefix + state value
    const SCOPES = 'https://www.googleapis.com/auth/spreadsheets'; // Google Sheets read/write scope

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }



    /**
     * Checks if admin has valid Google Sheets authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'googlesheets');
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['refresh_token']);
    }

    /**
     * Get configuration fields required for Google Sheets authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'client_id' => [
                'label' => __('Client ID', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Google application Client ID from console.cloud.google.com', 'data-machine')
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Google application Client Secret from console.cloud.google.com', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Google Sheets authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured, false otherwise
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_oauth', [], 'get_config', 'googlesheets');
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Gets an authenticated Google Sheets API access token.
     *
     * @return string|\WP_Error Access token string or WP_Error on failure.
     */
    public function get_service() {
        do_action('dm_log', 'debug', 'Attempting to get authenticated Google Sheets access token.');

        $credentials = apply_filters('dm_oauth', [], 'retrieve', 'googlesheets');
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['refresh_token'])) {
            do_action('dm_log', 'error', 'Missing Google Sheets credentials in options.');
            return new \WP_Error('googlesheets_missing_credentials', __('Google Sheets credentials not found. Please authenticate on the API Keys page.', 'data-machine'));
        }

        // Get the stored tokens directly
        $access_token = $credentials['access_token'];
        $refresh_token = $credentials['refresh_token'];

        // Check if access token needs refreshing
        $expires_at = $credentials['expires_at'] ?? 0;
        if (time() >= $expires_at - 300) { // Refresh 5 minutes before expiry
            do_action('dm_log', 'debug', 'Google Sheets access token expired, attempting refresh.');
            
            $refreshed_token = $this->refresh_access_token($refresh_token);
            if (is_wp_error($refreshed_token)) {
                return $refreshed_token;
            }
            
            return $refreshed_token; // Return the new access token
        }

        do_action('dm_log', 'debug', 'Successfully retrieved valid Google Sheets access token.');
        return $access_token;
    }

    /**
     * Refresh an expired access token using the refresh token.
     *
     * @param string $refresh_token The refresh token.
     * @return string|\WP_Error New access token or WP_Error on failure.
     */
    private function refresh_access_token(string $refresh_token) {
        
        $config = apply_filters('dm_oauth', [], 'get_config', 'googlesheets');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        
        if (empty($client_id) || empty($client_secret)) {
            do_action('dm_log', 'error', 'Missing Google OAuth client credentials.');
            return new \WP_Error('googlesheets_missing_oauth_config', __('Google OAuth configuration is incomplete.', 'data-machine'));
        }

        $result = apply_filters('dm_request', null, 'POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ],
        ], 'Google Sheets OAuth');

        if (!$result['success']) {
            do_action('dm_log', 'error', 'Google token refresh request failed.', [
                'error' => $result['error']
            ]);
            return new \WP_Error('googlesheets_refresh_failed', __('Failed to refresh Google Sheets access token.', 'data-machine'));
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];
        
        if ($response_code !== 200) {
            do_action('dm_log', 'error', 'Google token refresh failed.', [
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
            return new \WP_Error('googlesheets_refresh_error', __('Google token refresh failed. Please re-authenticate.', 'data-machine'));
        }

        $token_data = json_decode($response_body, true);
        if (empty($token_data['access_token'])) {
            do_action('dm_log', 'error', 'Invalid token refresh response from Google.');
            return new \WP_Error('googlesheets_invalid_refresh_response', __('Invalid response from Google during token refresh.', 'data-machine'));
        }

        // Update stored credentials with new access token
        $this->update_credentials($token_data['access_token'], $refresh_token, $token_data['expires_in'] ?? 3600);
        
        do_action('dm_log', 'debug', 'Successfully refreshed Google Sheets access token.');
        return $token_data['access_token'];
    }

    /**
     * Update credentials with new tokens.
     * Uses global site options for admin-global authentication.
     *
     * @param string $access_token New access token.
     * @param string $refresh_token Refresh token.
     * @param int $expires_in Token expiry time in seconds.
     */
    private function update_credentials(string $access_token, string $refresh_token, int $expires_in) {
        // Store the tokens directly
        $account_data = [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_at' => time() + $expires_in,
            'last_refreshed_at' => time()
        ];

        apply_filters('dm_oauth', null, 'store', 'googlesheets', $account_data);
    }

    /**
     * Registers the necessary WordPress action hooks for OAuth flow.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_googlesheets_oauth_init', array($this, 'handle_oauth_init'));
        add_action('admin_post_' . self::OAUTH_CALLBACK_ACTION, array($this, 'handle_oauth_callback'));
    }

    /**
     * Handles the initiation of the Google OAuth flow.
     * Hooked to 'admin_post_dm_googlesheets_oauth_init'.
     */
    public function handle_oauth_init() {
        
        // 1. Verify admin capability (admin_post_* hook already requires admin authentication)
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.', 'data-machine');
        }

        // 2. Get OAuth configuration
        $config = apply_filters('dm_oauth', [], 'get_config', 'googlesheets');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        
        if (empty($client_id) || empty($client_secret)) {
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_missing_oauth_config'));
            exit;
        }

        // 3. Generate state parameter for security
        $state = wp_create_nonce('dm_googlesheets_oauth_state');
        set_transient(self::STATE_TRANSIENT_PREFIX . $state, 'admin_authenticated', 15 * MINUTE_IN_SECONDS);

        // 4. Build authorization URL
        $callback_url = admin_url('admin-post.php?action=' . self::OAUTH_CALLBACK_ACTION);
        
        $auth_params = [
            'client_id' => $client_id,
            'redirect_uri' => $callback_url,
            'scope' => self::SCOPES,
            'response_type' => 'code',
            'access_type' => 'offline', // To get refresh token
            'prompt' => 'consent', // Force consent screen to ensure refresh token
            'state' => $state
        ];

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($auth_params);

        do_action('dm_log', 'debug', 'Redirecting user to Google OAuth authorization.', [
            'auth_url' => $auth_url
        ]);

        // 5. Redirect to Google
        wp_redirect($auth_url);
        exit;
    }

    /**
     * Handles the callback from Google after user authorization.
     * Hooked to 'admin_post_dm_googlesheets_oauth_callback'.
     */
    public function handle_oauth_callback() {
        
        // 1. Initial checks
        if (!current_user_can('manage_options')) {
             wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_permission_denied'));
             exit;
        }

        // Check for error parameter
        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            do_action('dm_log', 'warning', 'Google OAuth error returned.', ['error' => $error]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_oauth_error'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            do_action('dm_log', 'error', 'Missing code or state parameter in Google OAuth callback.');
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_missing_callback_params'));
            exit;
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));

        // 2. Verify state parameter
        $stored_state_marker = get_transient(self::STATE_TRANSIENT_PREFIX . $state);
        delete_transient(self::STATE_TRANSIENT_PREFIX . $state);

        if (empty($stored_state_marker) || !wp_verify_nonce($state, 'dm_googlesheets_oauth_state')) {
            do_action('dm_log', 'error', 'Invalid or expired state parameter in Google OAuth callback.');
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_invalid_state'));
            exit;
        }

        // 3. Exchange authorization code for tokens
        $config = apply_filters('dm_oauth', [], 'get_config', 'googlesheets');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        $callback_url = admin_url('admin-post.php?action=' . self::OAUTH_CALLBACK_ACTION);

        $result = apply_filters('dm_request', null, 'POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $callback_url
            ],
        ], 'Google Sheets OAuth');

        if (!$result['success']) {
            do_action('dm_log', 'error', 'Google token exchange request failed.', [
                'error' => $result['error']
            ]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_token_exchange_failed'));
            exit;
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];
        
        if ($response_code !== 200) {
            do_action('dm_log', 'error', 'Google token exchange failed.', [
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_token_exchange_error'));
            exit;
        }

        $token_data = json_decode($response_body, true);
        if (empty($token_data['access_token']) || empty($token_data['refresh_token'])) {
            do_action('dm_log', 'error', 'Invalid token response from Google.');
            wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_error=googlesheets_invalid_token_response'));
            exit;
        }

        // 4. Store credentials
        $account_data = [
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'],
            'expires_at' => time() + ($token_data['expires_in'] ?? 3600),
            'scope' => $token_data['scope'] ?? self::SCOPES,
            'last_verified_at' => time()
        ];

        apply_filters('dm_oauth', null, 'store', 'googlesheets', $account_data);

        do_action('dm_log', 'debug', 'Successfully completed Google Sheets OAuth flow.');

        // 5. Redirect on success
        wp_redirect(admin_url('admin.php?page=dm-pipelines&auth_success=googlesheets'));
        exit;
    }

    /**
     * Retrieves the stored Google Sheets account details.
     * Uses global site options for admin-only authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_oauth', [], 'retrieve', 'googlesheets');
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['refresh_token'])) {
            return null;
        }
        return $account;
    }

    /**
     * Removes the stored Google Sheets account details.
     * Uses global site options for admin-only authentication.
     *
     * @return bool True on success, false on failure.
     */
    public function remove_account(): bool {
        return apply_filters('dm_oauth', false, 'clear', 'googlesheets');
    }
}