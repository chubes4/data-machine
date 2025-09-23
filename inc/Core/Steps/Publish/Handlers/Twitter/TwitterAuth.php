<?php
/**
 * Twitter OAuth 1.0a authentication handler.
 *
 * Provides complete OAuth functionality for Twitter publish handler including
 * credential management, OAuth flow, and connection creation via filter-based architecture.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class TwitterAuth {

    const TEMP_TOKEN_SECRET_TRANSIENT_PREFIX = 'dm_twitter_req_secret_'; // Prefix + request_token

    /**
     * Constructor for filter-based architecture.
     */
    public function __construct() {
        // Services accessed via filters
    }

    /**
     * Check if Twitter authentication is valid.
     *
     * @return bool True if authenticated
     */
    public function is_authenticated(): bool {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'twitter');
        return !empty($account) && 
               is_array($account) && 
               !empty($account['access_token']) && 
               !empty($account['access_token_secret']);
    }

    /**
     * Get Twitter configuration field definitions.
     *
     * @return array Configuration fields
     */
    public function get_config_fields(): array {
        return [
            'api_key' => [
                'label' => __('API Key', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Twitter application API key from developer.twitter.com', 'data-machine')
            ],
            'api_secret' => [
                'label' => __('API Secret', 'data-machine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Twitter application API secret from developer.twitter.com', 'data-machine')
            ]
        ];
    }

    /**
     * Check if Twitter API credentials are configured.
     *
     * @return bool True if configured
     */
    public function is_configured(): bool {
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'twitter');
        return !empty($config['api_key']) && !empty($config['api_secret']);
    }

    /**
     * Get authenticated TwitterOAuth connection.
     *
     * @return TwitterOAuth|\WP_Error Connection or error
     */
    public function get_connection() {

        $credentials = apply_filters('dm_retrieve_oauth_account', [], 'twitter');
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['access_token_secret'])) {
            do_action('dm_log', 'error', 'Missing Twitter credentials in options.');
            return new \WP_Error('twitter_missing_credentials', __('Twitter credentials not found. Please authenticate.', 'data-machine'));
        }

        // Get stored access tokens
        $access_token = $credentials['access_token'];
        $access_token_secret = $credentials['access_token_secret'];

        $config = apply_filters('dm_retrieve_oauth_keys', [], 'twitter');
        $consumer_key = $config['api_key'] ?? '';
        $consumer_secret = $config['api_secret'] ?? '';
        if (empty($consumer_key) || empty($consumer_secret)) {
            do_action('dm_log', 'error', 'Missing Twitter API key/secret in site options.');
            return new \WP_Error('twitter_missing_app_keys', __('Twitter application keys are not configured in plugin settings.', 'data-machine'));
        }

        try {
            $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
            return $connection;
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Exception creating TwitterOAuth connection: ' . $e->getMessage());
            return new \WP_Error('twitter_connection_exception', __('Could not establish connection to Twitter.', 'data-machine'));
        }
    }


    /**
     * Get Twitter OAuth authorization URL.
     *
     * @return string Authorization URL
     */
    public function get_authorization_url(): string {
        // Get API credentials
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';
        if (empty($api_key) || empty($api_secret)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: API Key/Secret not configured.', [
                'handler' => 'twitter',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }

        // Define callback URL
        $callback_url = apply_filters('dm_oauth_callback', '', 'twitter');

        try {
            // Initialize TwitterOAuth
            $connection = new TwitterOAuth($api_key, $api_secret);

            // Get request token
            $request_token = $connection->oauth('oauth/request_token', ['oauth_callback' => $callback_url]);

            // Check for API errors
            if ($connection->getLastHttpCode() != 200 || !isset($request_token['oauth_token']) || !isset($request_token['oauth_token_secret'])) {
                $error_message = 'Failed to get request token from Twitter.';
                do_action('dm_log', 'error', 'Twitter OAuth Error: ' . $error_message, [
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody(),
                    'handler' => 'twitter',
                    'operation' => 'get_authorization_url'
                ]);
                return '';
            }

            // Store temporary token secret
            set_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $request_token['oauth_token'], $request_token['oauth_token_secret'], 15 * MINUTE_IN_SECONDS);

            // Return authorization URL
            return $connection->url('oauth/authenticate', [
                'oauth_token' => $request_token['oauth_token']
            ]);

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter OAuth Exception: ' . $e->getMessage(), [
                'handler' => 'twitter',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }
    }

    /**
     * Handle Twitter OAuth callback after user authorization.
     */
    public function handle_oauth_callback() {
        // Extract OAuth callback parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback cannot use nonces
        $denied = sanitize_text_field(wp_unslash($_GET['denied'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback cannot use nonces
        $oauth_token = sanitize_text_field(wp_unslash($_GET['oauth_token'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback cannot use nonces
        $oauth_verifier = sanitize_text_field(wp_unslash($_GET['oauth_verifier'] ?? ''));

        // Perform initial validation
        if (!current_user_can('manage_options')) {
             wp_redirect(add_query_arg('auth_error', 'twitter_permission_denied', admin_url('admin.php?page=dm-pipelines')));
             exit;
        }

        // Handle user denial
        if (!empty($denied)) {
            // Clean up temporary token
            delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $denied);
            do_action('dm_log', 'warning', 'Twitter OAuth Warning: User denied access.', ['denied_token' => $denied]);
            wp_redirect(add_query_arg('auth_error', 'twitter_access_denied', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Validate required parameters
        if (empty($oauth_token) || empty($oauth_verifier)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: Missing oauth_token or oauth_verifier in callback.');
            wp_redirect(add_query_arg('auth_error', 'twitter_missing_callback_params', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Retrieve temporary secret and credentials
        $oauth_token_secret = get_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);
        // Clean up temporary token immediately
        delete_transient(self::TEMP_TOKEN_SECRET_TRANSIENT_PREFIX . $oauth_token);

        if (empty($oauth_token_secret)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: Request token secret missing or expired in transient.', ['oauth_token' => $oauth_token]);
            wp_redirect(add_query_arg('auth_error', 'twitter_token_secret_expired', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        $config = apply_filters('dm_retrieve_oauth_keys', [], 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';
        if (empty($api_key) || empty($api_secret)) {
            do_action('dm_log', 'error', 'Twitter OAuth Error: API Key/Secret missing during callback.');
            wp_redirect(add_query_arg('auth_error', 'twitter_missing_app_keys', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }

        // Exchange for access token
        try {
            // Initialize with request token
            $connection = new TwitterOAuth($api_key, $api_secret, $oauth_token, $oauth_token_secret);

            // Exchange tokens
            $access_token_data = $connection->oauth("oauth/access_token", ["oauth_verifier" => $oauth_verifier]);

            // Validate token exchange
            if ($connection->getLastHttpCode() != 200 || !isset($access_token_data['oauth_token']) || !isset($access_token_data['oauth_token_secret'])) {
                do_action('dm_log', 'error', 'Twitter OAuth Error: Failed to get access token.', [
                    'http_code' => $connection->getLastHttpCode(),
                    'response' => $connection->getLastBody()
                ]);
                wp_redirect(add_query_arg('auth_error', 'twitter_access_token_failed', admin_url('admin.php?page=dm-pipelines')));
                exit;
            }

            // Store permanent credentials
            // Store access tokens
            $account_data = [
                'access_token'        => $access_token_data['oauth_token'],
                'access_token_secret' => $access_token_data['oauth_token_secret'],
                'user_id'             => $access_token_data['user_id'] ?? null, // Twitter User ID
                'screen_name'         => $access_token_data['screen_name'] ?? null, // Twitter Screen Name (@handle)
                'last_verified_at'    => time() // Timestamp of this successful auth
            ];

            // Store in site options
            apply_filters('dm_store_oauth_account', $account_data, 'twitter');

            // Redirect on success
            wp_redirect(add_query_arg('auth_success', 'twitter', admin_url('admin.php?page=dm-pipelines')));
            exit;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Twitter OAuth Exception during callback: ' . $e->getMessage());
            wp_redirect(add_query_arg('auth_error', 'twitter_callback_exception', admin_url('admin.php?page=dm-pipelines')));
            exit;
        }
    }


    /**
     * Get stored Twitter account details.
     *
     * @return array|null Account details or null
     */
    public function get_account_details(): ?array {
        $account = apply_filters('dm_retrieve_oauth_account', [], 'twitter');
        if (empty($account) || !is_array($account) || empty($account['access_token']) || empty($account['access_token_secret'])) {
            return null;
        }
        return $account;
    }

    /**
     * Remove stored Twitter account details.
     *
     * @return bool Success status
     */
    public function remove_account(): bool {
        return apply_filters('dm_clear_oauth_account', false, 'twitter');
    }

}
