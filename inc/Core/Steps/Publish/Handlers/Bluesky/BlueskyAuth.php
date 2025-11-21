<?php
/**
 * Handles Bluesky authentication for the Bluesky publish handler.
 *
 * Admin-global authentication system using app password credentials with site-level
 * storage, session management via Bluesky API, and filter-based HTTP requests.
 * Provides authenticated API access through session tokens.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Publish\Handlers\Bluesky
 * @since      0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class BlueskyAuth {

    public function is_authenticated(): bool {
        $auth_data = datamachine_get_oauth_account('bluesky');
        return !empty($auth_data) &&
               !empty($auth_data['username']) &&
               !empty($auth_data['app_password']);
    }

    public function is_configured(): bool {
        return $this->is_authenticated();
    }

    public function get_config_fields(): array {
        return [
            'username' => [
                'label' => __('Bluesky Handle', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Bluesky handle (e.g., user.bsky.social)', 'datamachine')
            ],
            'app_password' => [
                'label' => __('App Password', 'datamachine'),
                'type' => 'password',
                'required' => true,
                'description' => __('Generate an app password at bsky.app/settings/app-passwords', 'datamachine')
            ]
        ];
    }

    /**
     * Gets authenticated Bluesky session with access token and DID.
     */
    public function get_session() {
        $auth_data = datamachine_get_oauth_account('bluesky');
        $handle = $auth_data['username'] ?? '';
        $password = $auth_data['app_password'] ?? '';

        if (empty($handle) || empty($password)) {
            do_action('datamachine_log', 'error', 'Bluesky handle or app password missing in site options.');
            return new \WP_Error('bluesky_config_missing', __('Bluesky handle and app password must be configured.', 'datamachine'));
        }

        $session_data = $this->create_bluesky_session($handle, $password);

        unset($password);

        if (is_wp_error($session_data)) {
            do_action('datamachine_log', 'error', 'Bluesky authentication failed.', [
                'error_code' => $session_data->get_error_code(),
                'error_message' => $session_data->get_error_message()
            ]);
            return $session_data;
        }

        $access_token = $session_data['accessJwt'] ?? null;
        $did = $session_data['did'] ?? null;
        $pds_url = $session_data['pds_url'] ?? null;

        if (empty($access_token) || empty($did) || empty($pds_url)) {
            do_action('datamachine_log', 'error', 'Bluesky session data incomplete after authentication.', [
                'has_token' => !empty($access_token),
                'has_did' => !empty($did),
                'has_pds_url' => !empty($pds_url)
            ]);
            return new \WP_Error('bluesky_session_incomplete', __('Bluesky authentication succeeded but returned incomplete session data (missing accessJwt, did, or pds_url).', 'datamachine'));
        }

        $session_data['handle'] = $handle;

        return $session_data;
    }

    /**
     * Creates Bluesky session via AT Protocol authentication.
     */
    private function create_bluesky_session(string $handle, string $password) {
        $url = 'https://bsky.social/xrpc/com.atproto.server.createSession';
        
        $body = wp_json_encode([
            'identifier' => $handle,
            'password'   => $password,
        ]);

        if (false === $body) {
            do_action('datamachine_log', 'error', 'Failed to JSON encode Bluesky session request body.', ['handle' => $handle]);
            return new \WP_Error('bluesky_json_encode_error', __('Could not encode authentication request.', 'datamachine'));
        }


        $result = apply_filters('datamachine_request', null, 'POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $body,
        ], 'Bluesky Authentication');

        if (!$result['success']) {
            do_action('datamachine_log', 'error', 'Bluesky session request failed.', [
                'handle' => $handle,
                'error' => $result['error']
            ]);
            return new \WP_Error('bluesky_session_request_failed', 
                __('Could not connect to Bluesky server for authentication.', 'datamachine') . ' ' . $result['error']);
        }

        $response_code = $result['status_code'];
        $response_body = $result['data'];
        
        do_action('datamachine_log', 'debug', 'Bluesky session response received.', [
            'handle' => $handle,
            'code' => $response_code,
            'body_snippet' => substr($response_body, 0, 200)
        ]);

        $session_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            do_action('datamachine_log', 'error', 'Failed to decode Bluesky session response JSON.', [
                'handle' => $handle,
                'json_error' => json_last_error_msg()
            ]);
            return new \WP_Error('bluesky_json_decode_error', __('Invalid response from Bluesky server.', 'datamachine'));
        }

        if ($response_code !== 200) {
            if (empty($session_data['message'])) {
                do_action('datamachine_log', 'error', 'Bluesky authentication failed with no error message provided.', [
                    'handle' => $handle,
                    'code' => $response_code,
                    'response_data' => $session_data
                ]);
                return new \WP_Error('bluesky_auth_failed_no_message',
                    /* translators: %1$d: HTTP response code */
                    sprintf(__('Bluesky authentication failed with no error message provided (Code: %1$d)', 'datamachine'), $response_code));
            }
            
            $error_message = $session_data['message'];
            do_action('datamachine_log', 'error', 'Bluesky authentication failed (non-200 response).', [
                'handle' => $handle,
                'code' => $response_code,
                'response_message' => $error_message
            ]);
            return new \WP_Error('bluesky_auth_failed',
                /* translators: %1$s: Error message, %2$d: HTTP response code */
                sprintf(__('Bluesky authentication failed: %1$s (Code: %2$d)', 'datamachine'), $error_message, $response_code));
        }

        // Use pdsUrl from response if available, otherwise default to bsky.social
        if (!empty($session_data['pdsUrl'])) {
            if (!str_starts_with($session_data['pdsUrl'], 'http')) {
                $session_data['pds_url'] = 'https://' . ltrim($session_data['pdsUrl'], '/');
            } else {
                $session_data['pds_url'] = $session_data['pdsUrl'];
            }

            do_action('datamachine_log', 'debug', 'Using PDS URL from session response.', [
                'handle' => $handle,
                'pds_url' => $session_data['pds_url']
            ]);
        } else {
            // Fallback to default Bluesky PDS when API doesn't return pdsUrl
            $session_data['pds_url'] = 'https://bsky.social';

            do_action('datamachine_log', 'debug', 'Using default Bluesky PDS URL (pdsUrl not in response).', [
                'handle' => $handle,
                'pds_url' => $session_data['pds_url']
            ]);
        }

        return $session_data;
    }

    public function get_account_details(): ?array {
        $auth_data = datamachine_get_oauth_account('bluesky');
        $handle = $auth_data['username'] ?? '';
        $password = $auth_data['app_password'] ?? '';

        if (empty($handle) || empty($password)) {
            return null;
        }

        return [
            'handle' => $handle,
            'configured' => true,
            'last_verified_at' => $auth_data['last_verified'] ?? 0
        ];
    }

    public function remove_account(): bool {
        return apply_filters('datamachine_clear_oauth_account', false, 'bluesky');
    }
}