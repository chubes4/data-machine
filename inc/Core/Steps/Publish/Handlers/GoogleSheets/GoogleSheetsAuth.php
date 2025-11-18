<?php
/**
 * Handles Google Sheets OAuth 2.0 authentication.
 *
 * Refactored to use centralized OAuth2Handler for standardized OAuth flow.
 * Maintains Google Sheets-specific logic (token refresh, service access).
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Publish\Handlers\GoogleSheets
 * @since      0.2.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\GoogleSheets;

if (!defined('ABSPATH')) {
    exit;
}

class GoogleSheetsAuth {

    const SCOPES = 'https://www.googleapis.com/auth/spreadsheets';

    /**
     * @var \DataMachine\Core\OAuth\OAuth2Handler OAuth2 handler instance
     */
    private $oauth2;

    public function __construct() {
        $this->oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
    }

    /**
     * Check if admin has valid Google Sheets authentication
     *
     * @return bool True if authenticated
     */
    public function is_authenticated(): bool {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'googlesheets');
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
                'label' => __('Client ID', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Google application Client ID from console.cloud.google.com', 'datamachine')
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Google application Client Secret from console.cloud.google.com', 'datamachine')
            ]
        ];
    }

    /**
     * Check if Google Sheets authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured
     */
    public function is_configured(): bool {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'googlesheets');
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Get authenticated Google Sheets access token (Google Sheets-specific service).
     *
     * @return string|\WP_Error Access token or error
     */
    public function get_service() {
        do_action('datamachine_log', 'debug', 'Attempting to get authenticated Google Sheets access token.');

        $credentials = apply_filters('datamachine_retrieve_oauth_account', [], 'googlesheets');
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['refresh_token'])) {
            do_action('datamachine_log', 'error', 'Missing Google Sheets credentials in options.');
            return new \WP_Error('googlesheets_missing_credentials', __('Google Sheets credentials not found. Please authenticate.', 'datamachine'));
        }

        $access_token = $credentials['access_token'];
        $refresh_token = $credentials['refresh_token'];

        // Check if access token needs refreshing (Google-specific logic)
        $expires_at = $credentials['expires_at'] ?? 0;
        if (time() >= $expires_at - 300) { // Refresh 5 minutes before expiry
            do_action('datamachine_log', 'debug', 'Google Sheets access token expired, attempting refresh.');

            $refreshed_token = $this->refresh_access_token($refresh_token);
            if (is_wp_error($refreshed_token)) {
                return $refreshed_token;
            }

            return $refreshed_token;
        }

        do_action('datamachine_log', 'debug', 'Successfully retrieved valid Google Sheets access token.');
        return $access_token;
    }

    /**
     * Refresh expired access token (Google Sheets-specific logic).
     *
     * @param string $refresh_token Refresh token
     * @return string|\WP_Error New access token or error
     */
    private function refresh_access_token(string $refresh_token) {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'googlesheets');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';

        if (empty($client_id) || empty($client_secret)) {
            do_action('datamachine_log', 'error', 'Missing Google OAuth client credentials.');
            return new \WP_Error('googlesheets_missing_oauth_config', __('Google OAuth configuration is incomplete.', 'datamachine'));
        }

        $result = apply_filters('datamachine_request', null, 'POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ], 'Google Sheets OAuth');

        if (!$result['success']) {
            do_action('datamachine_log', 'error', 'Google token refresh request failed.', [
                'error' => $result['error']
            ]);
            return new \WP_Error('googlesheets_refresh_failed', __('Failed to refresh Google Sheets access token.', 'datamachine'));
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];

        if ($response_code !== 200) {
            do_action('datamachine_log', 'error', 'Google token refresh failed.', [
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
            return new \WP_Error('googlesheets_refresh_error', __('Google token refresh failed. Please re-authenticate.', 'datamachine'));
        }

        $token_data = json_decode($response_body, true);
        if (empty($token_data['access_token'])) {
            do_action('datamachine_log', 'error', 'Invalid token refresh response from Google.');
            return new \WP_Error('googlesheets_invalid_refresh_response', __('Invalid response from Google during token refresh.', 'datamachine'));
        }

        // Update stored credentials
        $this->update_credentials($token_data['access_token'], $refresh_token, $token_data['expires_in'] ?? 3600);

        do_action('datamachine_log', 'debug', 'Successfully refreshed Google Sheets access token.');
        return $token_data['access_token'];
    }

    /**
     * Update credentials with new tokens (Google Sheets-specific).
     *
     * @param string $access_token New access token
     * @param string $refresh_token Refresh token
     * @param int $expires_in Token expiry time in seconds
     */
    private function update_credentials(string $access_token, string $refresh_token, int $expires_in) {
        $account_data = [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_at' => time() + $expires_in,
            'last_refreshed_at' => time()
        ];

        apply_filters('datamachine_store_oauth_account', $account_data, 'googlesheets');
    }

    /**
     * Get authorization URL for Google OAuth
     *
     * @return string Authorization URL
     */
    public function get_authorization_url(): string {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'googlesheets');
        $client_id = $config['client_id'] ?? '';

        if (empty($client_id)) {
            do_action('datamachine_log', 'error', 'Google Sheets OAuth Error: Client ID not configured.', [
                'handler' => 'googlesheets',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }

        // Create state via OAuth2Handler
        $state = $this->oauth2->create_state('googlesheets');

        // Build authorization URL with Google-specific parameters
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => apply_filters('datamachine_oauth_callback', '', 'googlesheets'),
            'scope' => self::SCOPES,
            'response_type' => 'code',
            'access_type' => 'offline', // Google-specific: request refresh token
            'prompt' => 'consent', // Google-specific: force consent to ensure refresh token
            'state' => $state
        ];

        return $this->oauth2->get_authorization_url('https://accounts.google.com/o/oauth2/v2/auth', $params);
    }

    /**
     * Handle OAuth callback from Google
     */
    public function handle_oauth_callback() {
        // Sanitize input
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

        // Verify state via OAuth2Handler
        if (!$this->oauth2->verify_state('googlesheets', $state)) {
            do_action('datamachine_log', 'error', 'Google Sheets OAuth Error: State verification failed');
            wp_redirect(add_query_arg([
                'page' => 'datamachine-settings',
                'auth_error' => 'invalid_state',
                'provider' => 'googlesheets'
            ], admin_url('admin.php')));
            exit;
        }

        // Get configuration
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'googlesheets');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';

        if (empty($client_id) || empty($client_secret)) {
            do_action('datamachine_log', 'error', 'Google Sheets OAuth Error: Missing configuration');
            wp_redirect(add_query_arg([
                'page' => 'datamachine-settings',
                'auth_error' => 'missing_config',
                'provider' => 'googlesheets'
            ], admin_url('admin.php')));
            exit;
        }

        // Prepare token exchange parameters (Google-specific)
        $token_params = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => apply_filters('datamachine_oauth_callback', '', 'googlesheets')
        ];

        // Use OAuth2Handler for token exchange and callback handling
        $this->oauth2->handle_callback(
            'googlesheets',
            'https://oauth2.googleapis.com/token',
            $token_params,
            function($token_data) {
                // Google Sheets-specific: Build account data
                return [
                    'access_token' => $token_data['access_token'],
                    'refresh_token' => $token_data['refresh_token'] ?? null,
                    'expires_at' => time() + ($token_data['expires_in'] ?? 3600),
                    'scope' => $token_data['scope'] ?? self::SCOPES,
                    'last_verified_at' => time()
                ];
            }
        );
    }

    /**
     * Get stored Google Sheets account details
     *
     * @return array|null Account details or null
     */
    public function get_account_details(): ?array {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'googlesheets');
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['refresh_token'])) {
            return null;
        }
        return $account;
    }

    /**
     * Remove stored Google Sheets account details
     *
     * @return bool Success status
     */
    public function remove_account(): bool {
        return apply_filters('datamachine_clear_oauth_account', false, 'googlesheets');
    }
}
