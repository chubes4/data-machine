<?php
/**
 * Handles Facebook OAuth 2.0 authentication for the Facebook publish handler.
 *
 * Admin-global authentication system providing OAuth functionality with site-level
 * credential storage, long-lived token management, and automatic page token acquisition.
 * Uses filter-based HTTP requests and centralized logging.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Facebook
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Facebook;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FacebookAuth {

    // Constants for Facebook OAuth
    const AUTH_URL = 'https://www.facebook.com/v23.0/dialog/oauth';
    const TOKEN_URL = 'https://graph.facebook.com/v23.0/oauth/access_token';
    const SCOPES = 'email,public_profile,pages_show_list,pages_read_engagement,pages_manage_posts,pages_manage_comments,business_management';
    const GRAPH_API_URL = 'https://graph.facebook.com/v23.0';

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get configuration fields required for Facebook authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'app_id' => [
                'label' => __('App ID', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Facebook application App ID from developers.facebook.com', 'data-machine')
            ],
            'app_secret' => [
                'label' => __('App Secret', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Facebook application App Secret from developers.facebook.com', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Facebook authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured, false otherwise
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'facebook');
        return !empty($config['app_id']) && !empty($config['app_secret']);
    }

    /**
     * Registers the necessary WordPress action hooks for OAuth callback flow.
     * This should be called from the main plugin setup.
     */
    public function register_hooks() {
        add_action('admin_post_dm_facebook_oauth_callback', array($this, 'handle_oauth_callback'));
    }



    /**
     * Checks if admin has valid Facebook authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'facebook');
        if (empty($account) || !is_array($account)) {
            return false;
        }

        // Check if we have both user and page tokens
        if (empty($account['user_access_token']) || empty($account['page_access_token'])) {
            return false;
        }

        // Check token expiry if exists
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the stored Page access token.
     * Uses global site options for admin-only authentication.
     *
     * @return string|null Page Access token or null if not found/valid.
     */
    public function get_page_access_token(): ?string {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'facebook');
        if (empty($account) || !is_array($account) || empty($account['page_access_token'])) {
            return null;
        }

        // Check user token expiry as a proxy for page token validity
        if (isset($account['token_expires_at']) && time() > $account['token_expires_at']) {
            return null;
        }

        // Get page token directly
        return $account['page_access_token'];
    }

    /**
     * Retrieves the stored Page ID.
     * Uses global site options for admin-only authentication.
     *
     * @return string|null Page ID or null if not found.
     */
    public function get_page_id(): ?string {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'facebook');
        if (empty($account) || !is_array($account) || empty($account['page_id'])) {
            return null;
        }
        return $account['page_id'];
    }

    /**
     * Handles the OAuth callback from Facebook.
     * 
     * Exchanges authorization code for access token, upgrades to long-lived token,
     * retrieves page credentials, and stores complete authentication data.
     *
     * @param string $code    Authorization code from Facebook.
     * @param string $state   State parameter from Facebook for verification.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(string $code, string $state): bool|\WP_Error {
        do_action('dm_log', 'debug', 'Handling Facebook OAuth callback.');

        // 1. Verify state - use admin-global transient verification
        $stored_state = get_transient('dm_facebook_oauth_state');
        delete_transient('dm_facebook_oauth_state');
        
        if (empty($stored_state) || !wp_verify_nonce($state, 'dm_facebook_oauth_state')) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: State mismatch or expired.');
            return new \WP_Error('facebook_oauth_state_mismatch', __('Invalid or expired state parameter during Facebook authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri'  => $this->get_redirect_uri(),
            'code'          => $code,
        ];
        $token_url = self::TOKEN_URL . '?' . http_build_query($token_params);

        // Facebook requires GET for token exchange - use dm_request filter
        $result = apply_filters('dm_request', null, 'GET', $token_url, [], 'Facebook OAuth');
        
        // Convert result to WP_Error format for compatibility
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Token request failed.', ['error' => $result['error']]);
            return new \WP_Error('facebook_oauth_token_request_failed', __('HTTP error during token exchange with Facebook.', 'data-machine'), $result['error']);
        }
        
        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve access token from Facebook.';
            do_action('dm_log', 'error', 'Facebook OAuth Error: Token exchange failed.', ['http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('facebook_oauth_token_exchange_failed', $error_message, $data);
        }

        // 3. Store token and user info securely
        $short_lived_token = $data['access_token'];

        // Exchange for long-lived token
        $long_lived_token_data = $this->exchange_for_long_lived_token($short_lived_token);

        if (is_wp_error($long_lived_token_data)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Failed to exchange for long-lived token.', [
                'error' => $long_lived_token_data->get_error_message(),
                'error_data' => $long_lived_token_data->get_error_data()
            ]);
            return $long_lived_token_data;
        }

        $access_token = $long_lived_token_data['access_token'];
        $token_expires_at = $long_lived_token_data['expires_at'];

        // Fetch Page credentials using the long-lived user token
        $page_credentials = $this->get_page_credentials($access_token);

        if (is_wp_error($page_credentials)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Failed to fetch page credentials.', [
                'error' => $page_credentials->get_error_message(),
                'error_data' => $page_credentials->get_error_data()
            ]);
            return $page_credentials;
        }

        // Select the first page found
        $page_id = $page_credentials['id'];
        $page_access_token = $page_credentials['access_token'];
        $page_name = $page_credentials['name'];

        // Store the page access token directly
        $page_token = $page_access_token;

        // Fetch user profile info
        $profile_info = $this->get_user_profile($access_token);
        $user_profile_id = 'Unknown';
        $user_profile_name = 'Unknown';

        if (!is_wp_error($profile_info)) {
            $user_profile_id = $profile_info['id'] ?? 'ErrorFetchingId';
            $user_profile_name = $profile_info['name'] ?? 'ErrorFetchingName';
        } else {
             do_action('dm_log', 'warning', 'Facebook OAuth Warning: Failed to fetch user profile info.', [
                 'error' => $profile_info->get_error_message(),
                 'error_data' => $profile_info->get_error_data()
             ]);
        }

        // Store the user access token directly
        $user_token = $access_token;

        $account_details = [
            'user_access_token'  => $user_token,
            'page_access_token'  => $page_token,
            'token_type'         => $data['token_type'] ?? 'bearer',
            'user_id'            => $user_profile_id,
            'user_name'          => $user_profile_name,
            'page_id'            => $page_id,
            'page_name'          => $page_name,
            'authenticated_at'   => time(),
            'token_expires_at'   => $token_expires_at,
        ];

        // Store details in site options for admin-only architecture
        apply_filters('dm_store_oauth_account', $account_details, 'facebook');
        do_action('dm_log', 'debug',
            'Facebook account authenticated. User and Page credentials stored.',
            [
                'facebook_user_id' => $account_details['user_id'],
                'facebook_page_id' => $account_details['page_id']
            ]
        );

        return true;
    }


    /**
     * Generates the authorization URL to redirect the user to.
     * Uses admin-global state management for consistent OAuth flow.
     *
     * @return string The authorization URL.
     */
    public function get_authorization_url(): string {
        $state = wp_create_nonce('dm_facebook_oauth_state');
        // Store state in admin-global transient for verification
        set_transient('dm_facebook_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

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
     * Get Facebook client ID from options
     *
     * @return string
     */
    private function get_client_id() {
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'facebook');
        return $config['app_id'] ?? '';
    }

    /**
     * Get Facebook client secret from options
     *
     * @return string
     */
    private function get_client_secret() {
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'facebook');
        return $config['app_secret'] ?? '';
    }

    /**
     * Get redirect URI
     *
     * @return string
     */
    private function get_redirect_uri() {
        return apply_filters('dm_get_oauth_url', '', 'facebook');
    }

    /**
     * Retrieves user profile information from Facebook Graph API.
     *
     * @param string $access_token Valid access token.
     * @return array|\WP_Error Profile data (id, name) or WP_Error on failure.
     */
    private function get_user_profile(string $access_token): array|\WP_Error {
        $url = self::GRAPH_API_URL . '/me?fields=id,name';
        
        // Use dm_request filter for Facebook profile fetch
        $result = apply_filters('dm_request', null, 'GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ], 'Facebook Authentication');
        
        if (!$result['success']) {
            return new \WP_Error('facebook_profile_fetch_failed', $result['error']);
        }
        
        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook profile.';
             return new \WP_Error('facebook_profile_fetch_failed', $error_message, $data);
        }

        return $data;
    }

    /**
     * Exchanges a short-lived access token for a long-lived one.
     *
     * @param string $short_lived_token
     * @return array|\WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private function exchange_for_long_lived_token(string $short_lived_token): array|\WP_Error {
        do_action('dm_log', 'debug', 'Exchanging Facebook short-lived token for long-lived token.');
        $params = [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->get_client_id(),
            'client_secret'     => $this->get_client_secret(),
            'fb_exchange_token' => $short_lived_token,
        ];
        $url = self::TOKEN_URL . '?' . http_build_query($params);

        // Use dm_request filter for long-lived token exchange
        $result = apply_filters('dm_request', null, 'GET', $url, [], 'Facebook OAuth');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Long-lived token request failed.', ['error' => $result['error']]);
            return new \WP_Error('facebook_oauth_long_token_request_failed', __('HTTP error during long-lived token exchange with Facebook.', 'data-machine'), $result['error']);
        }
        
        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to retrieve long-lived access token from Facebook.';
             do_action('dm_log', 'error', 'Facebook OAuth Error: Long-lived token exchange failed.', ['http_code' => $http_code, 'response' => $body]);
            return new \WP_Error('facebook_oauth_long_token_exchange_failed', $error_message, $data);
        }

        // Calculate expiry timestamp
        $expires_in = $data['expires_in'] ?? 3600 * 24 * 60; // Default to ~60 days if not provided
        $expires_at = time() + intval($expires_in);

        do_action('dm_log', 'debug', 'Successfully obtained Facebook long-lived token.');

        return [
            'access_token' => $data['access_token'],
            'expires_at'   => $expires_at,
        ];
    }

    /**
     * Fetches the Pages the user manages using the User Access Token.
     * Uses /me/pages endpoint which is appropriate for manage_pages permission.
     *
     * @param string $user_access_token The valid User Access Token.
     * @return array|\WP_Error An array containing the first page's 'id', 'name', and 'access_token', or WP_Error on failure.
     */
    private function get_page_credentials(string $user_access_token): array|\WP_Error {
        do_action('dm_log', 'debug', 'Fetching Facebook page credentials.', [
            'token_length' => strlen($user_access_token),
            'token_preview' => substr($user_access_token, 0, 15) . '...',
            'api_version' => str_replace('https://graph.facebook.com/', '', self::GRAPH_API_URL)
        ]);
        
        // First, check permissions to help debug "no pages found" issue
        $permissions_url = self::GRAPH_API_URL . '/me/permissions';
        $permissions_result = apply_filters('dm_request', null, 'GET', $permissions_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $user_access_token,
            ],
        ], 'Facebook Permissions Check');
        
        if ($permissions_result['success']) {
            $permissions_data = json_decode($permissions_result['data'], true);
            do_action('dm_log', 'debug', 'Facebook permissions check', [
                'permissions' => $permissions_data['data'] ?? [],
                'raw_response' => $permissions_result['data']
            ]);
        }
        
        // Use /me/accounts endpoint (recommended for pages_show_list permission)
        $url = self::GRAPH_API_URL . '/me/accounts?fields=id,name,access_token';

        do_action('dm_log', 'debug', 'Attempting to fetch Facebook pages from /me/accounts endpoint.', [
            'url' => $url
        ]);

        // Use dm_request filter for Facebook pages fetch
        $result = apply_filters('dm_request', null, 'GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $user_access_token,
            ],
        ], 'Facebook Authentication');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: Request failed.', [
                'error' => $result['error']
            ]);
            return new \WP_Error('facebook_page_request_failed', __('HTTP error while fetching Facebook pages.', 'data-machine'), $result['error']);
        }
        
        $body = $result['data'];
        $data = json_decode($body, true);
        $http_code = $result['status_code'];

        // Enhanced debugging for "no pages found" issue
        do_action('dm_log', 'debug', 'Facebook pages API response', [
            'url' => $url,
            'http_code' => $http_code,
            'response_body' => $body,
            'response_length' => strlen($body),
            'data_structure' => is_array($data) ? array_keys($data) : 'not_array',
            'data_content' => $data,
            'has_data_key' => isset($data['data']),
            'data_array_count' => isset($data['data']) && is_array($data['data']) ? count($data['data']) : 'not_array',
            'token_length' => strlen($user_access_token),
            'token_prefix' => substr($user_access_token, 0, 10) . '...',
        ]);

        if ($http_code !== 200 || !isset($data['data'])) {
            $error_message = $data['error']['message'] ?? 'Failed to retrieve pages from Facebook.';
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: API error.', [
                'http_code' => $http_code, 
                'response' => $body
            ]);
            return new \WP_Error('facebook_page_api_error', $error_message, $data);
        }

        if (empty($data['data'])) {
            do_action('dm_log', 'error', 'Facebook Page Fetch Error: No pages found for this user.', [
                'response' => $body,
                'data_isset' => isset($data['data']),
                'data_is_array' => isset($data['data']) ? is_array($data['data']) : false,
                'data_empty' => isset($data['data']) ? empty($data['data']) : true,
                'full_data_structure' => $data,
                'error_triggered_condition' => 'empty($data[\'data\']) returned true',
                'endpoint_used' => '/me/accounts',
                'permission_required' => 'pages_show_list'
            ]);
            
            // Check if we have permission data to provide more helpful error message
            $error_message = __('No Facebook pages were found for this account using the /me/accounts endpoint.', 'data-machine');
            
            if ($permissions_result['success']) {
                $permissions_data = json_decode($permissions_result['data'], true);
                $has_manage_pages = false;
                
                if (isset($permissions_data['data']) && is_array($permissions_data['data'])) {
                    foreach ($permissions_data['data'] as $permission) {
                        if (isset($permission['permission']) && $permission['permission'] === 'pages_show_list' && $permission['status'] === 'granted') {
                            $has_manage_pages = true;
                            break;
                        }
                    }
                }
                
                if ($has_manage_pages) {
                    $error_message = __('No Facebook pages found despite having "pages_show_list" permission. This may indicate: 1) The account has no pages, 2) The account is not an admin/editor of any pages, or 3) Pages are managed through Business Manager and require different API access patterns.', 'data-machine');
                } else {
                    $error_message = __('The "pages_show_list" permission was not granted or is not active. Please ensure you grant the "pages_show_list" permission during authentication and that your account has admin/editor access to at least one Facebook page.', 'data-machine');
                }
            }
            
            return new \WP_Error('facebook_no_pages_found', $error_message);
        }

        // Return the first page found
        $first_page = $data['data'][0];
        
        do_action('dm_log', 'debug', 'Facebook: Processing first page from API response', [
            'total_pages_found' => count($data['data']),
            'first_page_keys' => array_keys($first_page),
            'page_id' => $first_page['id'] ?? 'missing',
            'page_name' => $first_page['name'] ?? 'missing',
            'has_access_token' => !empty($first_page['access_token'])
        ]);

        if (empty($first_page['id']) || empty($first_page['access_token']) || empty($first_page['name'])) {
             do_action('dm_log', 'error', 'Facebook Page Fetch Error: Incomplete data for the first page.', [
                 'page_data' => $first_page
             ]);
            return new \WP_Error('facebook_incomplete_page_data', __('Required information (ID, Access Token, Name) was missing for the Facebook page.', 'data-machine'));
        }

        do_action('dm_log', 'debug', 'Successfully fetched credentials for Facebook page.', [
            'page_id' => $first_page['id']
        ]);

        return [
            'id'           => $first_page['id'],
            'name'         => $first_page['name'],
            'access_token' => $first_page['access_token'],
        ];
    }

    /**
     * Removes the authenticated Facebook account.
     * Uses global site options for admin-only authentication.
     * Attempts to deauthorize the app via the Graph API first.
     *
     * @return bool True on success (local data deleted), false otherwise.
     */
    public function remove_account(): bool {
        // Try to get the stored token to attempt deauthorization
        $account = apply_filters('dm_retrieve_oauth_account', [], 'facebook');
        $token = null;

        if (!empty($account) && is_array($account) && !empty($account['user_access_token'])) {
            $token = $account['user_access_token'];
        }

        if ($token) {
            // Attempt deauthorization with Facebook - use dm_request filter
            $url = self::GRAPH_API_URL . '/me/permissions';
            $result = apply_filters('dm_request', null, 'DELETE', $url, [
                'body' => ['access_token' => $token],
                ], 'Facebook Authentication');
            
            // Log success or failure of deauthorization, but don't stop deletion
            if (!$result['success']) {
                do_action('dm_log', 'warning', 'Facebook deauthorization failed (non-critical)', ['error' => $result['error']]);
            } else {
                do_action('dm_log', 'debug', 'Facebook deauthorization successful');
            }
        }

        // Always attempt to delete the site option regardless of deauth success
        return apply_filters('dm_clear_oauth_account', false, 'facebook');
    }

    /**
     * Check if the authenticated account has comment permissions
     *
     * @return bool True if comment permissions are available, false otherwise
     */
    public function has_comment_permission(): bool {
        $page_access_token = $this->get_page_access_token();
        if (!$page_access_token) {
            return false;
        }

        // Check permissions for the page access token
        $permissions_url = self::GRAPH_API_URL . '/me/permissions?access_token=' . $page_access_token;
        
        $result = apply_filters('dm_request', null, 'GET', $permissions_url, [], 'Facebook Comment Permission Check');
        
        if (!$result['success']) {
            do_action('dm_log', 'debug', 'Facebook: Failed to check comment permissions', [
                'error' => $result['error']
            ]);
            return false;
        }
        
        $data = json_decode($result['data'], true);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            return false;
        }
        
        // Look for pages_manage_comments permission
        foreach ($data['data'] as $permission) {
            if (isset($permission['permission']) && 
                $permission['permission'] === 'pages_manage_comments' && 
                $permission['status'] === 'granted') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Retrieves the stored Facebook account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'facebook');
        if (empty($account) || !is_array($account)) {
            return null;
        }
        return $account;
    }

    /**
     * Handle OAuth callback from Facebook.
     * Hooked to 'admin_post_dm_facebook_oauth_callback'.
     */
    public function handle_oauth_callback() {
        // 1. Verify admin capability
        if (!current_user_can('manage_options')) {
             wp_die('Permission denied.');
        }

        // Check for error parameter first (user might deny access)
        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            $error_description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : 'User denied access or an error occurred.';
            do_action('dm_log', 'warning', 'Facebook OAuth Error in callback:', ['error' => $error, 'description' => $error_description]);
            wp_redirect(add_query_arg('auth_error', $error, admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: Missing code or state in callback.', ['query_params' => $_GET]);
            wp_redirect(add_query_arg('auth_error', 'missing_params', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));

        // Retrieve stored app credentials from global options
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'facebook');
        $app_id = $config['app_id'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        if (empty($app_id) || empty($app_secret)) {
            do_action('dm_log', 'error', 'Facebook OAuth Error: App credentials not configured.');
            wp_redirect(add_query_arg('auth_error', 'config_missing', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Handle the callback
        $result = $this->handle_callback($code, $state);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            do_action('dm_log', 'error', 'Facebook OAuth Callback Failed.', ['error_code' => $error_code, 'error_message' => $error_message]);
            // Redirect with a generic or specific error code
            wp_redirect(add_query_arg('auth_error', $error_code, admin_url('admin.php?page=dm-pipelines')));
        } else {
            do_action('dm_log', 'debug', 'Facebook OAuth Callback Successful.');
            wp_redirect(add_query_arg('auth_success', 'facebook', admin_url('admin.php?page=dm-pipelines')));
        }
        exit;
    }
}

