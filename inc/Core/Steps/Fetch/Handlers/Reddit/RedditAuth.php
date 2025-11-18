<?php
/**
 * Handles Reddit OAuth 2.0 Authorization Code Grant flow.
 *
 * Refactored to use centralized OAuth2Handler for standardized OAuth flow.
 * Maintains Reddit-specific logic (token refresh, user identity, API requirements).
 *
 * @package    DataMachine
 * @subpackage Core\Steps\Fetch\Handlers\Reddit
 * @since      0.2.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

if (!defined('ABSPATH')) {
    exit;
}

class RedditAuth {

    /**
     * @var \DataMachine\Core\OAuth\OAuth2Handler OAuth2 handler instance
     */
    private $oauth2;

    public function __construct() {
        $this->oauth2 = apply_filters('datamachine_get_oauth2_handler', null);
    }

    /**
     * Get configuration fields required for Reddit authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'client_id' => [
                'label' => __('Client ID', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Reddit application Client ID from reddit.com/prefs/apps', 'datamachine')
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Reddit application Client Secret from reddit.com/prefs/apps', 'datamachine')
            ],
            'developer_username' => [
                'label' => __('Developer Username', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Reddit username that is registered in the Reddit app configuration', 'datamachine')
            ]
        ];
    }

    /**
     * Check if Reddit authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured
     */
    public function is_configured(): bool {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Get the authorization URL for Reddit OAuth
     *
     * @return string Authorization URL
     */
    public function get_authorization_url(): string {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');
        $client_id = $config['client_id'] ?? '';

        if (empty($client_id)) {
            do_action('datamachine_log', 'error', 'Reddit OAuth Error: Client ID not configured.', [
                'handler' => 'reddit',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }

        // Create state via OAuth2Handler
        $state = $this->oauth2->create_state('reddit');

        // Build authorization URL with Reddit-specific parameters
        $params = [
            'client_id' => $client_id,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => apply_filters('datamachine_oauth_callback', '', 'reddit'),
            'duration' => 'permanent', // Reddit-specific: request refresh token
            'scope' => 'identity read' // Reddit-specific scopes
        ];

        return $this->oauth2->get_authorization_url('https://www.reddit.com/api/v1/authorize', $params);
    }

    /**
     * Handle OAuth callback from Reddit
     */
    public function handle_oauth_callback() {
        // Sanitize input
        $state = isset($_GET['state']) ? sanitize_key(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

        // Verify state via OAuth2Handler
        if (!$this->oauth2->verify_state('reddit', $state)) {
            do_action('datamachine_log', 'error', 'Reddit OAuth Error: State verification failed');
            wp_redirect(add_query_arg([
                'page' => 'datamachine-settings',
                'auth_error' => 'state_mismatch',
                'provider' => 'reddit'
            ], admin_url('admin.php')));
            exit;
        }

        // Get configuration
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        $developer_username = $config['developer_username'] ?? '';

        if (empty($client_id) || empty($client_secret) || empty($developer_username)) {
            do_action('datamachine_log', 'error', 'Reddit OAuth Error: Missing configuration');
            wp_redirect(add_query_arg([
                'page' => 'datamachine-settings',
                'auth_error' => 'missing_config',
                'provider' => 'reddit'
            ], admin_url('admin.php')));
            exit;
        }

        // Prepare token exchange parameters (Reddit-specific)
        $token_params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => apply_filters('datamachine_oauth_callback', '', 'reddit'),
        ];

        // Reddit requires Basic Auth for token exchange
        $token_params['headers'] = [
            'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            'User-Agent' => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        // Use OAuth2Handler for token exchange and callback handling
        $this->oauth2->handle_callback(
            'reddit',
            'https://www.reddit.com/api/v1/access_token',
            $token_params,
            function($token_data) use ($developer_username) {
                // Reddit-specific: Get user identity
                return $this->get_reddit_user_identity($token_data, $developer_username);
            }
        );
    }

    /**
     * Get Reddit user identity (Reddit-specific logic)
     *
     * @param array $token_data Token data from Reddit
     * @param string $developer_username Developer username for User-Agent
     * @return array Account data
     */
    private function get_reddit_user_identity(array $token_data, string $developer_username): array {
        $access_token = $token_data['access_token'];
        $refresh_token = $token_data['refresh_token'] ?? null;
        $expires_in = $token_data['expires_in'] ?? 3600;
        $scope_granted = $token_data['scope'] ?? '';
        $token_expires_at = time() + intval($expires_in);

        // Get user identity from Reddit API
        $identity_url = 'https://oauth.reddit.com/api/v1/me';
        $identity_result = apply_filters('datamachine_request', null, 'GET', $identity_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'User-Agent' => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')'
            ]
        ], 'Reddit Authentication');

        $identity_username = null;
        if ($identity_result['success'] && $identity_result['status_code'] === 200) {
            $identity_data = json_decode($identity_result['data'], true);
            $identity_username = $identity_data['name'] ?? null;

            if (empty($identity_username)) {
                do_action('datamachine_log', 'warning', 'Reddit OAuth Warning: Could not get username from /api/v1/me');
            }
        } else {
            do_action('datamachine_log', 'warning', 'Reddit OAuth Warning: Failed to get user identity after token exchange');
        }

        // Return account data for storage
        return [
            'username' => $identity_username,
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_expires_at' => $token_expires_at,
            'scope' => $scope_granted,
            'last_refreshed_at' => time()
        ];
    }

    /**
     * Refresh Reddit access token (Reddit-specific logic)
     *
     * @return bool True on success
     */
    public function refresh_token(): bool {
        do_action('datamachine_log', 'debug', 'Attempting Reddit token refresh');

        $reddit_account = apply_filters('datamachine_retrieve_oauth_account', [], 'reddit');
        if (empty($reddit_account['refresh_token'])) {
            do_action('datamachine_log', 'error', 'Reddit Token Refresh Error: Refresh token not found');
            return false;
        }

        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'reddit');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        $developer_username = $config['developer_username'] ?? '';

        if (empty($client_id) || empty($client_secret) || empty($developer_username)) {
            do_action('datamachine_log', 'error', 'Reddit Token Refresh Error: Missing configuration');
            return false;
        }

        // Reddit-specific token refresh request
        $token_url = 'https://www.reddit.com/api/v1/access_token';
        $result = apply_filters('datamachine_request', null, 'POST', $token_url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'User-Agent' => 'php:DataMachineWPPlugin:v' . DATAMACHINE_VERSION . ' (by /u/' . $developer_username . ')'
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $reddit_account['refresh_token']
            ]
        ], 'Reddit OAuth');

        if (!$result['success'] || $result['status_code'] !== 200) {
            do_action('datamachine_log', 'error', 'Reddit Token Refresh Error: Request failed', [
                'status_code' => $result['status_code'] ?? 'unknown',
                'error' => $result['error'] ?? 'unknown'
            ]);

            // Clear stored data if refresh token is invalid
            apply_filters('datamachine_clear_oauth_account', false, 'reddit');
            return false;
        }

        $data = json_decode($result['data'], true);
        if (empty($data['access_token'])) {
            do_action('datamachine_log', 'error', 'Reddit Token Refresh Error: No access token in response');
            apply_filters('datamachine_clear_oauth_account', false, 'reddit');
            return false;
        }

        // Update account data with new tokens
        $updated_account_data = [
            'username' => $reddit_account['username'] ?? null,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $reddit_account['refresh_token'],
            'token_expires_at' => time() + intval($data['expires_in'] ?? 3600),
            'scope' => $data['scope'] ?? $reddit_account['scope'] ?? '',
            'last_refreshed_at' => time()
        ];

        apply_filters('datamachine_store_oauth_account', $updated_account_data, 'reddit');
        do_action('datamachine_log', 'debug', 'Reddit token refreshed successfully');
        return true;
    }

    /**
     * Check if admin has valid Reddit authentication
     *
     * @return bool True if authenticated
     */
    public function is_authenticated(): bool {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'reddit');
        return !empty($account) &&
               is_array($account) &&
               !empty($account['access_token']) &&
               !empty($account['refresh_token']);
    }

    /**
     * Get Reddit account details
     *
     * @return array|null Account details or null if not authenticated
     */
    public function get_account_details(): ?array {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'reddit');
        if (empty($account) || !is_array($account) || empty($account['access_token'])) {
            return null;
        }

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

        if (empty($details)) {
            do_action('datamachine_log', 'warning', 'Reddit account exists but details are missing', [
                'has_access_token' => !empty($account['access_token']),
                'available_keys' => array_keys($account)
            ]);
        }

        return $details;
    }
}
