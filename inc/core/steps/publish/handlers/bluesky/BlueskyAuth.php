<?php
/**
 * Handles Bluesky authentication for the Bluesky publish handler.
 *
 * Self-contained authentication system that provides all authentication functionality
 * needed by the Bluesky publish handler including credential management,
 * session handling, and authenticated API access.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/publish/bluesky
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Publish\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class BlueskyAuth {

    const USER_META_KEY = 'data_machine_bluesky_account';

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
     * Checks if admin has valid Bluesky authentication
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated(): bool {
        $handle = get_option('bluesky_username', '');
        $encrypted_password = get_option('bluesky_app_password', '');
        return !empty($handle) && !empty($encrypted_password);
    }

    /**
     * Gets an authenticated Bluesky session.
     *
     * @return array|\WP_Error Session data array or WP_Error on failure.
     */
    public function get_session() {
        $logger = $this->get_logger();
        $logger && $logger->debug('Attempting to get authenticated Bluesky session.');

        // Get credentials from site options (global configuration)
        $handle = get_option('bluesky_username', '');
        $encrypted_password = get_option('bluesky_app_password', '');

        if (empty($handle) || empty($encrypted_password)) {
            $logger && $logger->error('Bluesky handle or app password missing in site options.');
            return new \WP_Error('bluesky_config_missing', __('Bluesky handle and app password must be configured on the API Keys page.', 'data-machine'));
        }

        // Decrypt the stored password
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            $logger && $logger->error('Encryption helper service unavailable for Bluesky authentication.');
            return new \WP_Error('bluesky_service_unavailable', __('Encryption service unavailable for Bluesky authentication.', 'data-machine'));
        }

        $password = $encryption_helper->decrypt($encrypted_password);
        if ($password === false) {
            $logger && $logger->error('Failed to decrypt Bluesky app password.');
            return new \WP_Error('bluesky_decryption_failed', __('Failed to decrypt Bluesky app password. Please re-save the settings.', 'data-machine'));
        }

        if (empty($password)) {
            $logger && $logger->error('Bluesky app password is empty after decryption.');
            return new \WP_Error('bluesky_empty_password', __('Bluesky app password is empty. Please check your configuration.', 'data-machine'));
        }

        // Authenticate with Bluesky and get session
        $session_data = $this->create_bluesky_session($handle, $password);
        
        // Clear password from memory
        unset($password);

        if (is_wp_error($session_data)) {
            $logger && $logger->error('Bluesky authentication failed.', [
                'error_code' => $session_data->get_error_code(),
                'error_message' => $session_data->get_error_message()
            ]);
            return $session_data;
        }

        $access_token = $session_data['accessJwt'] ?? null;
        $did = $session_data['did'] ?? null;
        $pds_url = $session_data['pds_url'] ?? null;

        if (empty($access_token) || empty($did) || empty($pds_url)) {
            $logger && $logger->error('Bluesky session data incomplete after authentication.', [
                'has_token' => !empty($access_token),
                'has_did' => !empty($did),
                'has_pds_url' => !empty($pds_url)
            ]);
            return new \WP_Error('bluesky_session_incomplete', __('Bluesky authentication succeeded but returned incomplete session data (missing accessJwt, did, or pds_url).', 'data-machine'));
        }

        // Add handle to session data for URL building
        $session_data['handle'] = $handle;

        $logger && $logger->debug('Bluesky authentication successful.', [
            'did' => $did,
            'pds' => $pds_url,
            'handle' => $handle
        ]);

        return $session_data;
    }

    /**
     * Authenticates with Bluesky and creates a session.
     *
     * @param string $handle User handle (e.g., user.bsky.social).
     * @param string $password App password.
     * @return array|\WP_Error Session data array on success, WP_Error on failure.
     */
    private function create_bluesky_session(string $handle, string $password) {
        $logger = $this->get_logger();
        $url = 'https://bsky.social/xrpc/com.atproto.server.createSession';
        
        $body = wp_json_encode([
            'identifier' => $handle,
            'password'   => $password,
        ]);

        if (false === $body) {
            $logger && $logger->error('Failed to JSON encode Bluesky session request body.', ['handle' => $handle]);
            return new \WP_Error('bluesky_json_encode_error', __('Could not encode authentication request.', 'data-machine'));
        }

        $logger && $logger->debug('Attempting Bluesky authentication (createSession).', ['handle' => $handle, 'url' => $url]);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $body,
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $logger && $logger->error('Bluesky session request failed.', [
                'handle' => $handle,
                'error' => $response->get_error_message()
            ]);
            return new \WP_Error('bluesky_session_request_failed', 
                __('Could not connect to Bluesky server for authentication.', 'data-machine') . ' ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $logger && $logger->debug('Bluesky session response received.', [
            'handle' => $handle,
            'code' => $response_code,
            'body_snippet' => substr($response_body, 0, 200)
        ]);

        $session_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger && $logger->error('Failed to decode Bluesky session response JSON.', [
                'handle' => $handle,
                'json_error' => json_last_error_msg()
            ]);
            return new \WP_Error('bluesky_json_decode_error', __('Invalid response from Bluesky server.', 'data-machine'));
        }

        if ($response_code !== 200) {
            if (empty($session_data['message'])) {
                $logger && $logger->error('Bluesky authentication failed with no error message provided.', [
                    'handle' => $handle,
                    'code' => $response_code,
                    'response_data' => $session_data
                ]);
                return new \WP_Error('bluesky_auth_failed_no_message', 
                    sprintf(__('Bluesky authentication failed with no error message provided (Code: %1$d)', 'data-machine'), $response_code));
            }
            
            $error_message = $session_data['message'];
            $logger && $logger->error('Bluesky authentication failed (non-200 response).', [
                'handle' => $handle,
                'code' => $response_code,
                'response_message' => $error_message
            ]);
            return new \WP_Error('bluesky_auth_failed', 
                sprintf(__('Bluesky authentication failed: %1$s (Code: %2$d)', 'data-machine'), $error_message, $response_code));
        }

        // Require PDS URL in session data - no defaults or inference
        if (empty($session_data['pdsUrl'])) {
            $logger && $logger->error('Bluesky session response missing required pdsUrl field.', [
                'handle' => $handle,
                'response_keys' => array_keys($session_data)
            ]);
            return new \WP_Error('bluesky_missing_pds_url', __('Bluesky authentication response missing required PDS URL. Server configuration issue.', 'data-machine'));
        }
        
        // Ensure PDS URL has https:// prefix
        if (!str_starts_with($session_data['pdsUrl'], 'http')) {
            $session_data['pds_url'] = 'https://' . ltrim($session_data['pdsUrl'], '/');
        } else {
            $session_data['pds_url'] = $session_data['pdsUrl'];
        }
        
        $logger && $logger->debug('Using PDS URL from session response.', [
            'handle' => $handle,
            'pds_url' => $session_data['pds_url']
        ]);

        return $session_data;
    }

    /**
     * Retrieves the stored Bluesky account details.
     * Uses global site options for admin-global authentication.
     *
     * @return array|null Account details array or null if not found/invalid.
     */
    public function get_account_details(): ?array {
        $handle = get_option('bluesky_username', '');
        $encrypted_password = get_option('bluesky_app_password', '');
        
        if (empty($handle) || empty($encrypted_password)) {
            return null;
        }
        
        return [
            'handle' => $handle,
            'configured' => true,
            'last_verified_at' => get_option('bluesky_last_verified', 0)
        ];
    }

    /**
     * Removes the stored Bluesky account details.
     * Uses global site options for admin-global authentication.
     *
     * @return bool True on success, false on failure.
     */
    public function remove_account(): bool {
        $result1 = delete_option('bluesky_username');
        $result2 = delete_option('bluesky_app_password');
        delete_option('bluesky_last_verified');
        
        return $result1 || $result2; // Return true if at least one option was deleted
    }
}