<?php
/**
 * Google Search Console Tool - Filter Registration
 * 
 * Register Google Search Console authentication provider and configuration filters
 * following Data Machine's established OAuth architecture patterns.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

use DataMachine\Core\Steps\AI\Tools\GoogleSearchConsoleAuth;

/**
 * Register Google Search Console authentication provider
 * 
 * Integrates GSC into the centralized OAuth system even though it's a general tool
 * rather than a handler-specific tool. This maintains consistency with existing
 * OAuth architecture patterns.
 */
add_filter('dm_auth_providers', function($providers) {
    $providers['google_search_console'] = new GoogleSearchConsoleAuth();
    return $providers;
});

/**
 * Google Search Console tool configuration detection
 * 
 * Checks if GSC tool is properly configured with OAuth credentials
 */
add_filter('dm_tool_configured', function($configured, $tool_id) {
    if ($tool_id === 'google_search_console') {
        // Check if OAuth configuration exists
        $config = apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
        $has_config = !empty($config['client_id']) && !empty($config['client_secret']);
        
        // Check if authenticated (has tokens)
        $account = apply_filters('dm_retrieve_oauth_account', [], 'google_search_console');
        $has_tokens = !empty($account['access_token']);
        
        return $has_config && $has_tokens;
    }
    
    return $configured;
}, 10, 2);

/**
 * Google Search Console tool configuration retrieval
 * 
 * Retrieves stored OAuth configuration for GSC tool
 */
add_filter('dm_get_tool_config', function($config, $tool_id) {
    if ($tool_id === 'google_search_console') {
        return apply_filters('dm_retrieve_oauth_keys', [], 'google_search_console');
    }
    
    return $config;
}, 10, 2);

/**
 * Save Google Search Console tool configuration
 * 
 * Handles saving OAuth configuration data through the centralized OAuth system
 */
add_action('dm_save_tool_config', function($tool_id, $config_data) {
    if ($tool_id === 'google_search_console') {
        // Validate and sanitize Google Search Console OAuth configuration
        $client_id = sanitize_text_field($config_data['client_id'] ?? '');
        $client_secret = sanitize_text_field($config_data['client_secret'] ?? '');
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(['message' => __('Client ID and Client Secret are required', 'data-machine')]);
            return;
        }
        
        // Store OAuth configuration using centralized system
        $oauth_config = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'configured_at' => time()
        ];
        
        // Save configuration via OAuth system
        if (apply_filters('dm_store_oauth_keys', $oauth_config, 'google_search_console')) {
            wp_send_json_success([
                'message' => __('Google Search Console configuration saved successfully. You can now connect your account.', 'data-machine'),
                'configured' => true,
                'requires_auth' => true
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration', 'data-machine')]);
        }
    }
}, 10, 2);

/**
 * AJAX handler to get OAuth authorization URL
 * 
 * Provides authorization URL for OAuth popup workflow
 */
add_action('wp_ajax_dm_get_oauth_auth_url', function() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dm_ajax_actions')) {
        wp_send_json_error(['message' => __('Security check failed', 'data-machine')]);
    }
    
    $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
    
    if ($provider === 'google_search_console') {
        $auth_url = apply_filters('dm_get_oauth_auth_url', '', 'google_search_console');
        
        if (is_wp_error($auth_url)) {
            wp_send_json_error(['message' => $auth_url->get_error_message()]);
        } else {
            wp_send_json_success(['auth_url' => $auth_url]);
        }
    } else {
        wp_send_json_error(['message' => __('Unknown OAuth provider', 'data-machine')]);
    }
});