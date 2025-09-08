<?php

/**
 * OAuth system with URL rewrites and data management
 *
 * Provides public OAuth callbacks and unified data operations.
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register OAuth system
 */
function dm_register_oauth_system() {
    
    // Add rewrite rule for public OAuth callbacks
    add_action('init', function() {
        add_rewrite_rule(
            '^dm-oauth/([^/]+)/?$',
            'index.php?dm_oauth_provider=$matches[1]',
            'top'
        );
    });
    
    // Register query variable
    add_filter('query_vars', function($vars) {
        $vars[] = 'dm_oauth_provider';
        return $vars;
    });
    
    // Handle OAuth requests via template redirect
    add_action('template_redirect', function() {
        $provider = get_query_var('dm_oauth_provider');
        
        if (!$provider) {
            return;
        }
        
        // Security check - ensure user has admin capabilities for OAuth operations
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions for OAuth operations.', 'data-machine'));
        }
        
        // Route to OAuth callback handlers via filter-based discovery
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$provider] ?? null;
        
        if ($auth_instance && method_exists($auth_instance, 'handle_oauth_callback')) {
            $auth_instance->handle_oauth_callback();
        } else {
            wp_die(__('Unknown OAuth provider.', 'data-machine'));
        }
        
        exit;
    }, 5);
    
    // Direct OAuth operations filters - eliminates action-type switching for better maintainability
    // Account data: apply_filters('dm_retrieve_oauth_account', [], 'twitter'); apply_filters('dm_store_oauth_account', $data, 'twitter');
    // Config data: apply_filters('dm_retrieve_oauth_keys', [], 'twitter'); apply_filters('dm_store_oauth_keys', $config, 'twitter');
    
    // Store OAuth account data (access tokens, user info)
    add_filter('dm_store_oauth_account', function($data, $provider) {
        $all_auth_data = get_option('dm_auth_data', []);
        if (!isset($all_auth_data[$provider])) {
            $all_auth_data[$provider] = [];
        }
        $all_auth_data[$provider]['account'] = $data;
        return update_option('dm_auth_data', $all_auth_data);
    }, 10, 2);
    
    // Retrieve OAuth account data
    add_filter('dm_retrieve_oauth_account', function($result, $provider) {
        $all_auth_data = get_option('dm_auth_data', []);
        return $all_auth_data[$provider]['account'] ?? [];
    }, 10, 2);
    
    // Clear OAuth account data only
    add_filter('dm_clear_oauth_account', function($result, $provider) {
        $all_auth_data = get_option('dm_auth_data', []);
        if (isset($all_auth_data[$provider]['account'])) {
            unset($all_auth_data[$provider]['account']);
            return update_option('dm_auth_data', $all_auth_data);
        }
        return true;
    }, 10, 2);
    
    // Store OAuth configuration data (API keys, client secrets)
    add_filter('dm_store_oauth_keys', function($data, $provider) {
        $all_auth_data = get_option('dm_auth_data', []);
        if (!isset($all_auth_data[$provider])) {
            $all_auth_data[$provider] = [];
        }
        $all_auth_data[$provider]['config'] = $data;
        return update_option('dm_auth_data', $all_auth_data);
    }, 10, 2);
    
    // Retrieve OAuth configuration data
    add_filter('dm_retrieve_oauth_keys', function($result, $provider) {
        $all_auth_data = get_option('dm_auth_data', []);
        return $all_auth_data[$provider]['config'] ?? [];
    }, 10, 2);
    
    // OAuth callback URL filter - provides public callback URLs for external APIs
    add_filter('dm_get_oauth_url', function($url, $provider) {
        if (empty($url)) {
            $url = site_url("/dm-oauth/{$provider}/");
        }
        return $url;
    }, 10, 2);
    
    // OAuth authorization URL filter - provides direct provider auth URLs for Connect buttons
    add_filter('dm_get_oauth_auth_url', function($auth_url, $provider) {
        if (!empty($auth_url)) {
            return $auth_url;
        }
        
        // Get authorization URL from provider's auth handler via filter-based discovery
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_instance = $all_auth[$provider] ?? null;
        
        if ($auth_instance && method_exists($auth_instance, 'get_authorization_url')) {
            return $auth_instance->get_authorization_url();
        }
        
        do_action('dm_log', 'error', 'OAuth Error: Unknown provider requested.', ['provider' => $provider]);
        return new WP_Error('unknown_provider', __('Unknown OAuth provider.', 'data-machine'));
    }, 10, 2);
}