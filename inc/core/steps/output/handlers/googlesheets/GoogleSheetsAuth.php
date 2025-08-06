<?php
/**
 * Handles Google Sheets OAuth 2.0 authentication for the Google Sheets output handler.
 *
 * Self-contained authentication system that provides all OAuth functionality
 * needed by the Google Sheets output handler including credential management,
 * OAuth flow handling, and authenticated API access.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\GoogleSheets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GoogleSheetsAuth {

    const OAUTH_CALLBACK_ACTION = 'dm_googlesheets_oauth_callback';
    const STATE_TRANSIENT_PREFIX = 'dm_googlesheets_state_'; // Prefix + state value
    const USER_META_KEY = 'data_machine_googlesheets_account';
    const SCOPES = 'https://www.googleapis.com/auth/spreadsheets'; // Google Sheets read/write scope

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
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
     * Checks if admin has valid Google Sheets authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = get_option('googlesheets_auth_data', []);
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['refresh_token']);
    }

    /**
     * Gets an authenticated Google Sheets API access token.
     *
     * @return string|\WP_Error Access token string or WP_Error on failure.
     */
    public function get_service() {
        $logger = $this->get_logger();
        $logger && $logger->debug('Attempting to get authenticated Google Sheets access token.');

        $credentials = get_option('googlesheets_auth_data', []);
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['refresh_token'])) {
            $logger && $logger->error('Missing Google Sheets credentials in options.');
            return new \WP_Error('googlesheets_missing_credentials', __('Google Sheets credentials not found. Please authenticate on the API Keys page.', 'data-machine'));
        }

        // Decrypt the stored tokens
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $logger && $logger->error('Encryption helper service unavailable for Google Sheets connection.');
            return new \WP_Error('googlesheets_service_unavailable', __('Encryption service unavailable for Google Sheets authentication.', 'data-machine'));
        }

        $decrypted_access_token = $encryption_helper->decrypt($credentials['access_token']);
        $decrypted_refresh_token = $encryption_helper->decrypt($credentials['refresh_token']);
        
        if ($decrypted_access_token === false || $decrypted_refresh_token === false) {
            $logger && $logger->error('Failed to decrypt Google Sheets credentials.');
            return new \WP_Error('googlesheets_decryption_failed', __('Failed to decrypt Google Sheets credentials. Please re-authenticate.', 'data-machine'));
        }

        // Check if access token needs refreshing
        $expires_at = $credentials['expires_at'] ?? 0;
        if (time() >= $expires_at - 300) { // Refresh 5 minutes before expiry
            $logger && $logger->debug('Google Sheets access token expired, attempting refresh.');
            
            $refreshed_token = $this->refresh_access_token($decrypted_refresh_token);
            if (is_wp_error($refreshed_token)) {
                return $refreshed_token;
            }
            
            return $refreshed_token; // Return the new access token
        }

        $logger && $logger->debug('Successfully retrieved valid Google Sheets access token.');
        return $decrypted_access_token;
    }

    /**
     * Refresh an expired access token using the refresh token.
     *
     * @param string $refresh_token The refresh token.
     * @return string|\WP_Error New access token or WP_Error on failure.
     */
    private function refresh_access_token(string $refresh_token) {
        $logger = $this->get_logger();
        
        $client_id = get_option('googlesheets_client_id');
        $client_secret = get_option('googlesheets_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            $logger && $logger->error('Missing Google OAuth client credentials.');
            return new \WP_Error('googlesheets_missing_oauth_config', __('Google OAuth configuration is incomplete.', 'data-machine'));
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $logger && $logger->error('Google token refresh request failed.', [
                'error' => $response->get_error_message()
            ]);
            return new \WP_Error('googlesheets_refresh_failed', __('Failed to refresh Google Sheets access token.', 'data-machine'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $logger && $logger->error('Google token refresh failed.', [
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
            return new \WP_Error('googlesheets_refresh_error', __('Google token refresh failed. Please re-authenticate.', 'data-machine'));
        }

        $token_data = json_decode($response_body, true);
        if (empty($token_data['access_token'])) {
            $logger && $logger->error('Invalid token refresh response from Google.');
            return new \WP_Error('googlesheets_invalid_refresh_response', __('Invalid response from Google during token refresh.', 'data-machine'));
        }

        // Update stored credentials with new access token
        $this->update_credentials($token_data['access_token'], $refresh_token, $token_data['expires_in'] ?? 3600);
        
        $logger && $logger->debug('Successfully refreshed Google Sheets access token.');
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
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            return; // Can't update without encryption
        }

        $encrypted_access_token = $encryption_helper->encrypt($access_token);
        $encrypted_refresh_token = $encryption_helper->encrypt($refresh_token);
        
        if ($encrypted_access_token === false || $encrypted_refresh_token === false) {
            return; // Can't update with failed encryption
        }

        $account_data = [
            'access_token' => $encrypted_access_token,
            'refresh_token' => $encrypted_refresh_token,
            'expires_at' => time() + $expires_in,
            'last_refreshed_at' => time()
        ];

        update_option('googlesheets_auth_data', $account_data);
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
        $logger = $this->get_logger();
        
        // 1. Verify Nonce & Capability
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'dm_googlesheets_oauth_init_nonce')) {
            wp_die('Security check failed (Nonce mismatch). Please try initiating the connection again from the API Keys page.', 'data-machine');
        }
        if (!current_user_can('manage_options')) {
             wp_die('Permission denied.', 'data-machine');
        }

        // 2. Get OAuth configuration
        $client_id = get_option('googlesheets_client_id');
        $client_secret = get_option('googlesheets_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_missing_oauth_config'));
            exit;
        }

        // 3. Generate state parameter for security
        $state = wp_generate_password(32, false);
        set_transient(self::STATE_TRANSIENT_PREFIX . $state, get_current_user_id(), 15 * MINUTE_IN_SECONDS);

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

        $logger && $logger->debug('Redirecting user to Google OAuth authorization.', [
            'user_id' => get_current_user_id(),
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
        $logger = $this->get_logger();
        
        // 1. Initial checks
        if (!is_user_logged_in()) {
             wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_not_logged_in'));
             exit;
        }

        // Check for error parameter
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $logger && $logger->warning('Google OAuth error returned.', ['error' => $error]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_oauth_error'));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            $logger && $logger->error('Missing code or state parameter in Google OAuth callback.');
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_missing_callback_params'));
            exit;
        }

        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);

        // 2. Verify state parameter
        $stored_user_id = get_transient(self::STATE_TRANSIENT_PREFIX . $state);
        delete_transient(self::STATE_TRANSIENT_PREFIX . $state);

        if (empty($stored_user_id) || $stored_user_id != get_current_user_id()) {
            $logger && $logger->error('Invalid or expired state parameter in Google OAuth callback.');
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_invalid_state'));
            exit;
        }

        // 3. Exchange authorization code for tokens
        $user_id = get_current_user_id();
        $client_id = get_option('googlesheets_client_id');
        $client_secret = get_option('googlesheets_client_secret');
        $callback_url = admin_url('admin-post.php?action=' . self::OAUTH_CALLBACK_ACTION);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $callback_url
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $logger && $logger->error('Google token exchange request failed.', [
                'user_id' => $user_id,
                'error' => $response->get_error_message()
            ]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_token_exchange_failed'));
            exit;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $logger && $logger->error('Google token exchange failed.', [
                'user_id' => $user_id,
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_token_exchange_error'));
            exit;
        }

        $token_data = json_decode($response_body, true);
        if (empty($token_data['access_token']) || empty($token_data['refresh_token'])) {
            $logger && $logger->error('Invalid token response from Google.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_invalid_token_response'));
            exit;
        }

        // 4. Store encrypted credentials
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $logger && $logger->error('Encryption helper service unavailable during OAuth callback.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_service_unavailable'));
            exit;
        }

        $encrypted_access_token = $encryption_helper->encrypt($token_data['access_token']);
        $encrypted_refresh_token = $encryption_helper->encrypt($token_data['refresh_token']);
        
        if ($encrypted_access_token === false || $encrypted_refresh_token === false) {
            $logger && $logger->error('Failed to encrypt Google Sheets tokens.', ['user_id' => $user_id]);
            wp_redirect(admin_url('admin.php?page=dm-project-management&auth_error=googlesheets_encryption_failed'));
            exit;
        }
        
        $account_data = [
            'access_token' => $encrypted_access_token,
            'refresh_token' => $encrypted_refresh_token,
            'expires_at' => time() + ($token_data['expires_in'] ?? 3600),
            'scope' => $token_data['scope'] ?? self::SCOPES,
            'last_verified_at' => time()
        ];

        update_user_meta($user_id, self::USER_META_KEY, $account_data);

        $logger && $logger->debug('Successfully completed Google Sheets OAuth flow.', ['user_id' => $user_id]);

        // 5. Redirect on success
        wp_redirect(admin_url('admin.php?page=dm-project-management&auth_success=googlesheets'));
        exit;
    }

    /**
     * Retrieves the stored Google Sheets account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = get_option('googlesheets_auth_data', []);
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['refresh_token'])) {
            return null;
        }
        return $account;
    }

    /**
     * Removes the stored Google Sheets account details.
     * Uses global site options for admin-global authentication.
     *
     * @return bool True on success, false on failure.
     */
    public function remove_account(): bool {
        return delete_option('googlesheets_auth_data');
    }
}