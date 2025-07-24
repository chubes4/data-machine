<?php
/**
 * Handles Threads OAuth 2.0 authentication flow.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/oauth
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

use DataMachine\Helpers\{EncryptionHelper, Logger};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Threads {

    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $logger;
    private $encryption;

    // Corrected URLs and Scopes based on Meta documentation
    const AUTH_URL = 'https://graph.facebook.com/oauth/authorize'; // Use FB authorize endpoint
    const TOKEN_URL = 'https://graph.threads.net/oauth/access_token';
    const REFRESH_URL = 'https://graph.threads.net/refresh_access_token';
    const API_BASE_URL = 'https://graph.threads.net/v1.0'; // Base for API calls
    // Required scopes for basic info and publishing
    const SCOPES = 'threads_basic,threads_content_publish';

    /**
     * Constructor.
     *
     * @param string $client_id     Threads App Client ID.
     * @param string $client_secret Threads App Client Secret.
     * @param Logger|null $logger Optional Logger instance.
     */
    public function __construct(string $client_id, string $client_secret, ?Logger $logger = null) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        // Revert to the original admin callback URL
        $this->redirect_uri = admin_url('admin.php?page=dm-api-keys&dm_oauth_callback=threads');
        $this->logger = $logger;
        $this->encryption = new EncryptionHelper(); // Ensure encryption helper is initialized

        // Register hooks - moved back here from separate method if needed, or called elsewhere
        $this->register_hooks();
    }

    /**
     * Registers WordPress hooks.
     */
    public function register_hooks() {
        // Revert to checking on admin_init for the specific admin page
        add_action('admin_init', [$this, 'handle_admin_page_oauth_callback']);

        // Remove template_redirect hook if it exists from previous edits
        // Note: Directly removing actions added by other instances might be tricky,
        // but we ensure we are not adding it here.
        // has_action('template_redirect', [$this, 'handle_oauth_callback_check']) remove_action?
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
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
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
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function handle_callback(int $user_id, string $code, string $state): bool|WP_Error {
        $this->logger?->info('Handling Threads OAuth callback.', ['user_id' => $user_id]);

        // 1. Verify state
        $stored_state = get_user_meta($user_id, 'dm_threads_oauth_state', true);
        delete_user_meta($user_id, 'dm_threads_oauth_state'); // Clean up state
        if (empty($stored_state) || !hash_equals($stored_state, $state)) {
            $this->logger?->error('Threads OAuth Error: State mismatch.', ['user_id' => $user_id]);
            return new WP_Error('threads_oauth_state_mismatch', __('Invalid state parameter during Threads authentication.', 'data-machine'));
        }

        // 2. Exchange code for access token
        $token_params = [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirect_uri,
            'code'          => $code,
        ];

        $response = wp_remote_post(self::TOKEN_URL, [
            'method' => 'POST',
            'body'   => $token_params,
            'timeout'=> 15,
        ]);

        if (is_wp_error($response)) {
            $this->logger?->error('Threads OAuth Error: Token request failed.', ['user_id' => $user_id, 'error' => $response->get_error_message()]);
            return new WP_Error('threads_oauth_token_request_failed', __('HTTP error during token exchange with Threads.', 'data-machine'), $response);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_message = $data['error_description'] ?? $data['error'] ?? 'Failed to retrieve access token from Threads.';
            $this->logger?->error('Threads OAuth Error: Token exchange failed.', ['user_id' => $user_id, 'http_code' => $http_code, 'response' => $body]);
            return new WP_Error('threads_oauth_token_exchange_failed', $error_message, $data);
        }

        $initial_access_token = $data['access_token'];

        // 3. Exchange short-lived token for a long-lived one
        $this->logger?->info('Threads OAuth: Exchanging short-lived token for long-lived token.', ['user_id' => $user_id]);
        $exchange_params = [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => $this->client_secret,
            'access_token'  => $initial_access_token, // Use the short-lived token here
        ];
        // The example used GET for this exchange
        $exchange_url = 'https://graph.threads.net/access_token?' . http_build_query($exchange_params);

        $exchange_response = wp_remote_get($exchange_url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($exchange_response)) {
            $this->logger?->error('Threads OAuth Error: Long-lived token exchange request failed (HTTP).', ['user_id' => $user_id, 'error' => $exchange_response->get_error_message()]);
            return new WP_Error('threads_oauth_exchange_request_failed', __('HTTP error during long-lived token exchange with Threads.', 'data-machine'), $exchange_response);
        }

        $exchange_body = wp_remote_retrieve_body($exchange_response);
        $exchange_data = json_decode($exchange_body, true);
        $exchange_http_code = wp_remote_retrieve_response_code($exchange_response);

        if ($exchange_http_code !== 200 || empty($exchange_data['access_token'])) {
            // Fail hard if the exchange doesn't succeed, as we need the long-lived token.
            $exchange_error_message = $exchange_data['error']['message'] ?? $exchange_data['error_description'] ?? 'Failed to retrieve long-lived access token from Threads.';
            $this->logger?->error('Threads OAuth Error: Long-lived token exchange failed (API).', ['user_id' => $user_id, 'http_code' => $exchange_http_code, 'response' => $exchange_body]);
            return new WP_Error('threads_oauth_exchange_failed', $exchange_error_message, $exchange_data);
        }

        // Successfully exchanged for long-lived token
        $this->logger?->info('Threads OAuth: Successfully exchanged for long-lived token.', ['user_id' => $user_id]);
        $long_lived_access_token = $exchange_data['access_token'];
        $long_lived_expires_in = $exchange_data['expires_in'] ?? null; // Should be ~60 days in seconds
        $long_lived_token_type = $exchange_data['token_type'] ?? 'bearer';


        // 4. Prepare account details using the long-lived token
        $account_details = [
            'access_token'  => $long_lived_access_token, // Use the long-lived token
            'expires_in'    => $long_lived_expires_in, // Store the long-lived expiry duration (for info)
            'token_type'    => $long_lived_token_type, // Use type from exchange response
            'fb_user_id'    => null, // Store the authenticating FB User ID if needed
            'fb_user_name'  => 'Unknown', // Store the authenticating FB User name
            'page_id'       => null, // Store the ID of the target FB Page for posting
            'page_name'     => null, // Store the name of the target FB Page
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
            $this->logger?->info('Fetched posting entity info from /me.', ['user_id' => $user_id, 'posting_entity_id' => $posting_entity_info['id'], 'posting_entity_name' => $account_details['page_name']]);
        } else {
            // Critical error if /me doesn't return the necessary ID
            $this->logger?->error('Threads OAuth Error: Failed to fetch posting entity info from /me endpoint.', [
                'user_id' => $user_id,
                'error' => is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : '/me did not return an ID',
            ]);
            $error_message = is_wp_error($posting_entity_info) ? $posting_entity_info->get_error_message() : __('Could not retrieve the necessary profile ID using the access token.', 'data-machine');
            return new WP_Error('threads_oauth_me_id_missing', $error_message);
        }

        // Encrypt tokens before storing
        $account_details['access_token'] = EncryptionHelper::encrypt($account_details['access_token']);
        if ($account_details['access_token'] === false) {
             $this->logger?->error('Threads OAuth Error: Failed to encrypt access token.', ['user_id' => $user_id]);
             return new WP_Error('threads_oauth_encryption_failed', __('Failed to securely store the Threads access token.', 'data-machine'));
        }
        // Note: Threads API currently doesn't seem to issue refresh tokens via standard OAuth code exchange.
        // Long-lived tokens are refreshed using the token itself. So, no refresh token to store/encrypt.
        // unset($account_details['refresh_token']); // Ensure no refresh token logic remains if not used
        // Update user meta with all collected details (token, FB user, FB page)
        update_user_meta($user_id, 'data_machine_threads_auth_account', $account_details);
        $this->logger?->info('Threads account authenticated and token stored.', ['user_id' => $user_id, 'page_id' => $account_details['page_id']]);

        return true;
    }

    /**
     * Retrieves the stored access token for a user.
     * Handles decryption and token refresh if needed.
     *
     * @param int $user_id WordPress User ID.
     * @return string|null Access token or null if not found/valid.
     */
    public static function get_access_token(int $user_id): ?string {
        $account = get_user_meta($user_id, 'data_machine_threads_auth_account', true);
        if (empty($account) || !is_array($account) || empty($account['access_token'])) {
            			// Debug logging removed for production
            return null;
        }

        // Check if token needs refresh (e.g., expires within the next 7 days)
        $needs_refresh = false;
        if (isset($account['token_expires_at'])) {
            $expiry_timestamp = intval($account['token_expires_at']);
            $seven_days_in_seconds = 7 * 24 * 60 * 60;
            if (time() > $expiry_timestamp) {
                 			// Debug logging removed for production
                 // Attempt refresh even if expired, might still work shortly after.
                 $needs_refresh = true;
            } elseif (($expiry_timestamp - time()) < $seven_days_in_seconds) {
                 			// Debug logging removed for production
                 $needs_refresh = true;
            }
        }

        // Decrypt the current token first
        $current_token = EncryptionHelper::decrypt($account['access_token']);
        if ($current_token === false) {
            			// Debug logging removed for production
            return null;
        }

        if ($needs_refresh) {
            $refreshed_data = self::refresh_access_token($current_token, $user_id);
            if (!is_wp_error($refreshed_data)) {
                // Update stored account details with refreshed token and expiry
                $account['access_token'] = EncryptionHelper::encrypt($refreshed_data['access_token']);
                $account['token_expires_at'] = $refreshed_data['expires_at'];
                // Update the user meta immediately
                update_user_meta($user_id, 'data_machine_threads_auth_account', $account);
                			// Debug logging removed for production
                return $refreshed_data['access_token']; // Return the new plaintext token
            } else {
                 			// Debug logging removed for production
                 // If refresh fails and token is already expired, return null
                 if (isset($account['token_expires_at']) && time() > intval($account['token_expires_at'])) {
                     			// Debug logging removed for production
                     return null;
                 }
                 // Otherwise, return the old (but potentially soon-to-expire) token
                 			// Debug logging removed for production
                 return $current_token;
            }
        }

        // If no refresh needed or attempt failed but token still valid, return current decrypted token
        return $current_token;
    }

     /**
     * Removes the authenticated Threads account for the user.
     *
     * @param int $user_id WordPress User ID.
     * @return bool True on success, false otherwise.
     */
    public static function remove_account(int $user_id): bool {
        // TODO: Optionally call a token revocation endpoint on the Threads API if available
        return delete_user_meta($user_id, 'data_machine_threads_auth_account');
    }

    /**
     * Retrieves user profile information from Threads API. (Placeholder)
     *
     * @param string $access_token Valid access token.
     * @return array|WP_Error Profile data or WP_Error on failure.
     */
    private function get_facebook_user_profile(string $access_token): array|WP_Error {
        // Use Facebook Graph API endpoint for /me
        $url = 'https://graph.facebook.com/v19.0/me?fields=id,name'; // Adjust version as needed
        $this->logger?->debug('Facebook Graph API: Fetching authenticating user profile.', ['url' => $url]);
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
             $this->logger?->error('Facebook Graph API Error: Profile fetch wp_remote_get failed.', ['error' => $response->get_error_message()]);
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200 || isset($data['error'])) {
             $error_message = $data['error']['message'] ?? 'Failed to fetch Facebook user profile.';
             $this->logger?->error('Facebook Graph API Error: Profile fetch failed.', ['http_code' => $http_code, 'response' => $body]);
             return new WP_Error('fb_profile_fetch_failed', $error_message, $data);
        }

         if (empty($data['id'])) {
             $this->logger?->error('Facebook Graph API Error: Profile fetch response missing ID.', ['http_code' => $http_code, 'response' => $body]);
             return new WP_Error('fb_profile_id_missing', __('Profile ID missing in response from Facebook Graph API.', 'data-machine'), $data);
         }

        $this->logger?->debug('Facebook Graph API: Profile fetched successfully.', ['profile_id' => $data['id']]);
        return $data; // Contains 'id' and 'name'
    }

    /**
     * Refreshes a long-lived Threads access token.
     *
     * @param string $access_token The current, valid (or recently expired) long-lived token.
     * @param int $user_id WP User ID for logging context.
     * @return array|WP_Error ['access_token' => ..., 'expires_at' => timestamp] or WP_Error
     */
    private static function refresh_access_token(string $access_token, int $user_id): array|WP_Error {
         		// Debug logging removed for production
         $params = [
             'grant_type' => 'th_refresh_token', // Correct grant type for Threads
             'access_token' => $access_token,
         ];
         $url = self::REFRESH_URL . '?' . http_build_query($params);

         $response = wp_remote_get($url, ['timeout' => 15]);

         if (is_wp_error($response)) {
             			// Debug logging removed for production
             return new WP_Error('threads_refresh_http_error', $response->get_error_message(), $response);
         }

         $body = wp_remote_retrieve_body($response);
         $data = json_decode($body, true);
         $http_code = wp_remote_retrieve_response_code($response);

         if ($http_code !== 200 || empty($data['access_token'])) {
             $error_message = $data['error']['message'] ?? $data['error_description'] ?? 'Failed to refresh Threads access token.';
             			// Debug logging removed for production
             return new WP_Error('threads_refresh_api_error', $error_message, $data);
         }

         // Calculate new expiry timestamp
         $expires_in = $data['expires_in'] ?? 3600 * 24 * 60; // Default to 60 days
         $expires_at = time() + intval($expires_in);

         		// Debug logging removed for production

         return [
             'access_token' => $data['access_token'],
             'expires_at'   => $expires_at,
         ];
    }

    /**
     * Checks for the OAuth callback parameters on the admin page load.
     * Hooked to admin_init.
     */
    public function handle_admin_page_oauth_callback() {
        // Check if we are on the correct admin page and the callback parameter is set
        if ( ! isset($_GET['page']) || $_GET['page'] !== 'dm-api-keys' || ! isset($_GET['dm_oauth_callback']) || $_GET['dm_oauth_callback'] !== 'threads') {
            return; // Not our callback
        }

        // Check for error response
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : 'No description provided.';
            $this->logger?->error('Threads OAuth Error (Callback Init): User denied access or error occurred.', ['error' => $error, 'description' => $error_description]);
            // Add an admin notice? Redirect? Decide how to handle user-facing error.
            // For now, maybe store in transient and display on API keys page.
            set_transient('dm_oauth_error_threads', "Threads authentication failed: " . $error_description, 60);
            // Redirect back to the API keys page cleanly, removing error params
            wp_redirect(admin_url('admin.php?page=dm-api-keys&dm_oauth_status=error'));
            exit;
        }

        // Check for code and state
        if (isset($_GET['code']) && isset($_GET['state'])) {
            $code = sanitize_text_field($_GET['code']);
            $received_state = sanitize_text_field($_GET['state']);

            // Get current user ID for state verification
            $user_id = get_current_user_id();
            
            // Verify state - use user_meta consistently
            $stored_state = get_user_meta($user_id, 'dm_threads_oauth_state', true);
            if (!$stored_state || !hash_equals($stored_state, $received_state)) {
                $this->logger?->error('Threads OAuth Error (Callback): State mismatch.', ['user_id' => $user_id, 'received' => $received_state, 'stored' => $stored_state]);
                set_transient('dm_oauth_error_threads', 'Threads authentication failed: Invalid state parameter. Please try again.', 60);
                wp_redirect(admin_url('admin.php?page=dm-api-keys&dm_oauth_status=error_state'));
                exit;
            }
            // State verified, it will be cleaned up in handle_callback()

            // Exchange code for token
            // Call the main handle_callback method which now includes page ID fetching
            $result = $this->handle_callback(get_current_user_id(), $code, $received_state);

            // Redirect back to the API keys page with status
            if (is_wp_error($result)) {
                 set_transient('dm_oauth_error_threads', 'Threads authentication failed: ' . $result->get_error_message(), 60);
                 wp_redirect(admin_url('admin.php?page=dm-api-keys&dm_oauth_status=error_token'));
                 exit;
            } else {
                 set_transient('dm_oauth_success_threads', 'Threads account connected successfully!', 60);
                 wp_redirect(admin_url('admin.php?page=dm-api-keys&dm_oauth_status=success'));
                 exit;
            }
        }
        // If neither error nor code/state are present, it's not a callback we need to handle here.
    }

} // End Class