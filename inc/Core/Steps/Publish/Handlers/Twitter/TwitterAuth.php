<?php
/**
 * Twitter OAuth 1.0a authentication handler.
 *
 * Refactored to use centralized OAuth1Handler for standardized OAuth 1.0a flow.
 * Maintains Twitter-specific connection management via TwitterOAuth library.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\Twitter
 * @since 0.2.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\Twitter;

use Abraham\TwitterOAuth\TwitterOAuth;

if (!defined('ABSPATH')) {
    exit;
}

class TwitterAuth {

    /**
     * @var \DataMachine\Core\OAuth\OAuth1Handler OAuth1 handler instance
     */
    private $oauth1;

    public function __construct() {
        $this->oauth1 = apply_filters('datamachine_get_oauth1_handler', null);
    }

    /**
     * Check if Twitter authentication is valid.
     *
     * @return bool True if authenticated
     */
    public function is_authenticated(): bool {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'twitter');
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
                'label' => __('API Key', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Twitter application API key from developer.twitter.com', 'datamachine')
            ],
            'api_secret' => [
                'label' => __('API Secret', 'datamachine'),
                'type' => 'text',
                'required' => true,
                'description' => __('Your Twitter application API secret from developer.twitter.com', 'datamachine')
            ]
        ];
    }

    /**
     * Check if Twitter API credentials are configured.
     *
     * @return bool True if configured
     */
    public function is_configured(): bool {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');
        return !empty($config['api_key']) && !empty($config['api_secret']);
    }

    /**
     * Get authenticated TwitterOAuth connection.
     *
     * @return TwitterOAuth|\WP_Error Connection or error
     */
    public function get_connection() {
        $credentials = apply_filters('datamachine_retrieve_oauth_account', [], 'twitter');
        if (empty($credentials) || empty($credentials['access_token']) || empty($credentials['access_token_secret'])) {
            do_action('datamachine_log', 'error', 'Missing Twitter credentials in options.');
            return new \WP_Error('twitter_missing_credentials', __('Twitter credentials not found. Please authenticate.', 'datamachine'));
        }

        $access_token = $credentials['access_token'];
        $access_token_secret = $credentials['access_token_secret'];

        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');
        $consumer_key = $config['api_key'] ?? '';
        $consumer_secret = $config['api_secret'] ?? '';

        if (empty($consumer_key) || empty($consumer_secret)) {
            do_action('datamachine_log', 'error', 'Missing Twitter API key/secret in site options.');
            return new \WP_Error('twitter_missing_app_keys', __('Twitter application keys are not configured in plugin settings.', 'datamachine'));
        }

        try {
            $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
            return $connection;
        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Exception creating TwitterOAuth connection: ' . $e->getMessage());
            return new \WP_Error('twitter_connection_exception', __('Could not establish connection to Twitter.', 'datamachine'));
        }
    }

    /**
     * Get Twitter OAuth authorization URL.
     *
     * @return string Authorization URL
     */
    public function get_authorization_url(): string {
        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';

        if (empty($api_key) || empty($api_secret)) {
            do_action('datamachine_log', 'error', 'Twitter OAuth Error: API Key/Secret not configured.', [
                'handler' => 'twitter',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }

        $callback_url = apply_filters('datamachine_oauth_callback', '', 'twitter');

        // Get request token via OAuth1Handler
        $request_token = $this->oauth1->get_request_token(
            'https://api.twitter.com/oauth/request_token',
            $api_key,
            $api_secret,
            $callback_url,
            'twitter'
        );

        if (is_wp_error($request_token)) {
            do_action('datamachine_log', 'error', 'Twitter OAuth Error: ' . $request_token->get_error_message(), [
                'handler' => 'twitter',
                'operation' => 'get_authorization_url'
            ]);
            return '';
        }

        // Build authorization URL via OAuth1Handler
        return $this->oauth1->get_authorization_url(
            'https://api.twitter.com/oauth/authenticate',
            $request_token['oauth_token'],
            'twitter'
        );
    }

    /**
     * Handle Twitter OAuth callback after user authorization.
     */
    public function handle_oauth_callback() {
        if (!current_user_can('manage_options')) {
            wp_redirect(add_query_arg('auth_error', 'twitter_permission_denied', admin_url('admin.php?page=datamachine-settings')));
            exit;
        }

        $config = apply_filters('datamachine_retrieve_oauth_keys', [], 'twitter');
        $api_key = $config['api_key'] ?? '';
        $api_secret = $config['api_secret'] ?? '';

        if (empty($api_key) || empty($api_secret)) {
            do_action('datamachine_log', 'error', 'Twitter OAuth Error: API Key/Secret missing during callback.');
            wp_redirect(add_query_arg('auth_error', 'twitter_missing_app_keys', admin_url('admin.php?page=datamachine-settings')));
            exit;
        }

        // Use OAuth1Handler for complete callback flow
        $this->oauth1->handle_callback(
            'twitter',
            'https://api.twitter.com/oauth/access_token',
            $api_key,
            $api_secret,
            function($access_token_data) {
                // Build account data from Twitter response
                return [
                    'access_token' => $access_token_data['oauth_token'],
                    'access_token_secret' => $access_token_data['oauth_token_secret'],
                    'user_id' => $access_token_data['user_id'] ?? null,
                    'screen_name' => $access_token_data['screen_name'] ?? null,
                    'last_verified_at' => time()
                ];
            }
        );
    }

    /**
     * Get stored Twitter account details.
     *
     * @return array|null Account details or null
     */
    public function get_account_details(): ?array {
        $account = apply_filters('datamachine_retrieve_oauth_account', [], 'twitter');
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
        return apply_filters('datamachine_clear_oauth_account', false, 'twitter');
    }
}
