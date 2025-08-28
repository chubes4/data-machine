<?php
/**
 * Handles Google Search Console OAuth 2.0 authentication for the GSC tool.
 *
 * Self-contained authentication system that provides all OAuth functionality
 * needed by the Google Search Console tool including credential management,
 * OAuth flow handling, and authenticated connection creation.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\AI\Tools
 * @since      1.0.0
 */

namespace DataMachine\Core\Steps\AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class GoogleSearchConsoleAuth {

    const OAUTH_CALLBACK_ACTION = 'dm_gsc_oauth_callback';
    const STATE_TRANSIENT_PREFIX = 'dm_gsc_state_'; // Prefix + state value

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Checks if admin has valid Google Search Console authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['refresh_token']);
    }

    /**
     * Get configuration fields required for Google Search Console authentication
     *
     * @return array Configuration field definitions
     */
    public function get_config_fields(): array {
        return [
            'client_id' => [
                'label' => __('Client ID', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Google Cloud Console OAuth 2.0 Client ID', 'data-machine')
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'data-machine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Your Google Cloud Console OAuth 2.0 Client Secret', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Google Search Console authentication is properly configured
     *
     * @return bool True if OAuth credentials are configured, false otherwise
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
        return !empty($config['client_id']) && !empty($config['client_secret']);
    }

    /**
     * Gets an authenticated Google Client object for Search Console API.
     *
     * @return \Google\Client|\WP_Error Authenticated client object or WP_Error on failure.
     */
    public function get_connection() {

        // Check if Google Client library is available
        if (!class_exists('Google\Client')) {
            do_action('dm_log', 'error', 'Google Client library not found. Please install google/apiclient.');
            return new \WP_Error('gsc_client_missing', __('Google Client library not available. Please install google/apiclient via Composer.', 'data-machine'));
        }

        $credentials = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
        if (empty($credentials) || empty($credentials['access_token'])) {
            do_action('dm_log', 'error', 'Missing Google Search Console credentials.');
            return new \WP_Error('gsc_missing_credentials', __('Google Search Console credentials not found. Please authenticate on the Settings page.', 'data-machine'));
        }

        $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        if (empty($client_id) || empty($client_secret)) {
            do_action('dm_log', 'error', 'Missing Google Search Console OAuth configuration.');
            return new \WP_Error('gsc_missing_config', __('Google Search Console OAuth configuration is incomplete.', 'data-machine'));
        }

        try {
            $client = new \Google\Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri(apply_filters('dm_get_oauth_url', '', 'google_search_console'));
            $client->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            // Set access token
            $client->setAccessToken([
                'access_token' => $credentials['access_token'],
                'refresh_token' => $credentials['refresh_token'] ?? null,
                'expires_in' => $credentials['expires_in'] ?? 3600,
                'created' => $credentials['created'] ?? time()
            ]);

            // Check if token needs refresh
            if ($client->isAccessTokenExpired()) {
                if (!empty($credentials['refresh_token'])) {
                    $client->refreshToken($credentials['refresh_token']);
                    $new_token = $client->getAccessToken();
                    
                    // Update stored credentials
                    $updated_credentials = array_merge($credentials, $new_token);
                    apply_filters('dm_store_oauth_account', $updated_credentials, 'google_search_console');
                } else {
                    do_action('dm_log', 'error', 'Google Search Console token expired and no refresh token available.');
                    return new \WP_Error('gsc_token_expired', __('Google Search Console authentication expired. Please re-authenticate.', 'data-machine'));
                }
            }

            return $client;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Exception creating Google Search Console client: ' . $e->getMessage());
            return new \WP_Error('gsc_connection_exception', __('Could not establish connection to Google Search Console.', 'data-machine'));
        }
    }

    /**
     * Get the authorization URL for direct connection to Google Search Console OAuth
     *
     * @return string|WP_Error Authorization URL or error
     */
    public function get_authorization_url() {
        // 1. Get Client ID/Secret from configuration
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        if (empty($client_id) || empty($client_secret)) {
            do_action('dm_log', 'error', 'Google Search Console OAuth configuration incomplete for URL generation.');
            return new \WP_Error('gsc_missing_config', __('Google Search Console Client ID/Secret not configured.', 'data-machine'));
        }

        // Check if Google Client library is available
        if (!class_exists('Google\Client')) {
            do_action('dm_log', 'error', 'Google Client library not found during OAuth URL generation. Install google/apiclient via Composer.');
            return new \WP_Error('gsc_client_missing', __('Google Client library not available.', 'data-machine'));
        }

        // 2. Define Callback URL  
        $callback_url = apply_filters('dm_get_oauth_url', '', 'google_search_console');

        try {
            // 3. Instantiate Google Client
            $client = new \Google\Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri($callback_url);
            $client->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            // 4. Generate and store state parameter for CSRF protection
            $state = wp_generate_password(32, false);
            set_transient(self::STATE_TRANSIENT_PREFIX . $state, $state, 15 * MINUTE_IN_SECONDS);
            $client->setState($state);

            // 5. Get Authorization URL
            $auth_url = $client->createAuthUrl();

            return $auth_url;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Google Search Console OAuth Exception: ' . $e->getMessage());
            return new \WP_Error('gsc_auth_exception', __('Google Search Console OAuth initialization failed.', 'data-machine'));
        }
    }

    /**
     * Handles the callback from Google after user authorization.
     * Called via OAuth system template redirect.
     */
    public function handle_oauth_callback() {
        // --- 1. Initial Checks --- 
        if (!current_user_can('manage_options')) {
             wp_redirect(add_query_arg('auth_error', 'gsc_permission_denied', admin_url('admin.php?page=data-machine')));
             exit;
        }

        // Check for error parameter
        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            do_action('dm_log', 'warning', 'Google Search Console OAuth Error: ' . $error);
            wp_redirect(add_query_arg('auth_error', 'gsc_' . $error, admin_url('admin.php?page=data-machine')));
            exit;
        }

        // Check for required parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            do_action('dm_log', 'error', 'Google Search Console OAuth Error: Missing code or state parameter.');
            wp_redirect(add_query_arg('auth_error', 'gsc_missing_params', admin_url('admin.php?page=data-machine')));
            exit;
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));

        // --- 2. Verify State Parameter --- 
        $stored_state = get_transient(self::STATE_TRANSIENT_PREFIX . $state);
        delete_transient(self::STATE_TRANSIENT_PREFIX . $state);

        if (empty($stored_state) || $stored_state !== $state) {
            do_action('dm_log', 'error', 'Google Search Console OAuth Error: Invalid state parameter.');
            wp_redirect(add_query_arg('auth_error', 'gsc_invalid_state', admin_url('admin.php?page=data-machine')));
            exit;
        }

        // --- 3. Get Configuration --- 
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        if (empty($client_id) || empty($client_secret)) {
            do_action('dm_log', 'error', 'Google Search Console OAuth Error: Missing configuration during callback.');
            wp_redirect(add_query_arg('auth_error', 'gsc_missing_config', admin_url('admin.php?page=data-machine')));
            exit;
        }

        // --- 4. Exchange Code for Access Token --- 
        try {
            $client = new \Google\Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri(apply_filters('dm_get_oauth_url', '', 'google_search_console'));
            $client->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);
            $client->setAccessType('offline');

            // Exchange authorization code for access token
            $access_token = $client->fetchAccessTokenWithAuthCode($code);

            // Check for errors during token exchange
            if (isset($access_token['error'])) {
                do_action('dm_log', 'error', 'Google Search Console OAuth Error: ' . $access_token['error']);
                wp_redirect(add_query_arg('auth_error', 'gsc_token_exchange_failed', admin_url('admin.php?page=data-machine')));
                exit;
            }

            // --- 5. Store Permanent Credentials --- 
            $account_data = [
                'access_token' => $access_token['access_token'],
                'refresh_token' => $access_token['refresh_token'] ?? null,
                'expires_in' => $access_token['expires_in'] ?? 3600,
                'created' => time(),
                'scope' => $access_token['scope'] ?? 'https://www.googleapis.com/auth/webmasters.readonly',
                'last_verified_at' => time()
            ];

            // Store in centralized OAuth system
            apply_filters('dm_store_oauth_account', $account_data, 'google_search_console');

            // --- 6. Redirect on Success --- 
            do_action('dm_log', 'info', 'Google Search Console OAuth authentication successful.');
            wp_redirect(add_query_arg('auth_success', 'google_search_console', admin_url('admin.php?page=data-machine')));
            exit;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Google Search Console OAuth Exception during callback: ' . $e->getMessage());
            wp_redirect(add_query_arg('auth_error', 'gsc_callback_exception', admin_url('admin.php?page=data-machine')));
            exit;
        }
    }

    /**
     * Retrieves the stored Google Search Console account details.
     * Uses centralized OAuth system for credential management.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
        if (empty($account) || !is_array($account) || empty($account['access_token'])) {
            return null;
        }
        return $account;
    }

    /**
     * Removes the stored Google Search Console account details.
     * Uses centralized OAuth system for credential management.
     *
     * @return bool True on success, false on failure.
     */
    public function remove_account(): bool {
        return apply_filters('dm_clear_oauth_account', false, 'google_search_console');
    }

    /**
     * Get verified Search Console properties for the authenticated user.
     *
     * @return array|\WP_Error Array of verified sites or WP_Error on failure.
     */
    public function get_verified_sites() {
        $client = $this->get_connection();
        if (is_wp_error($client)) {
            return $client;
        }

        try {
            $service = new \Google\Service\SearchConsole($client);
            $sites_list = $service->sites->listSites();
            
            $verified_sites = [];
            foreach ($sites_list->getSiteEntry() as $site) {
                // Only include verified sites with sufficient permissions
                if ($site->getPermissionLevel() !== 'siteUnverifiedUser') {
                    $verified_sites[] = [
                        'site_url' => $site->getSiteUrl(),
                        'permission_level' => $site->getPermissionLevel()
                    ];
                }
            }
            
            return $verified_sites;
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Error fetching Google Search Console sites: ' . $e->getMessage());
            return new \WP_Error('gsc_sites_error', __('Could not retrieve Search Console sites.', 'data-machine'));
        }
    }
}