<?php

/**
 * OAuth URL rewrite system and centralized OAuth operations
 * 
 * Provides public OAuth callback URLs and unified OAuth data management
 * External APIs can access: /dm-oauth/{provider} instead of wp-admin URLs
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register OAuth URL rewrite system and filters
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
        
        // Route to OAuth callback handlers (only callbacks now - no init)
        switch ($provider) {
            case 'reddit':
                if (class_exists('DataMachine\\Core\\Handlers\\Fetch\\Reddit\\RedditAuth')) {
                    $auth = new DataMachine\Core\Handlers\Fetch\Reddit\RedditAuth();
                    $auth->handle_oauth_callback();
                }
                break;
                
            case 'twitter':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Twitter\\TwitterAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Twitter\TwitterAuth();
                    $auth->handle_oauth_callback();
                }
                break;
                
            case 'googlesheets':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\GoogleSheets\\GoogleSheetsAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\GoogleSheets\GoogleSheetsAuth();
                    $auth->handle_oauth_callback();
                }
                break;
                
            case 'threads':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Threads\\ThreadsAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Threads\ThreadsAuth();
                    $auth->handle_oauth_callback();
                }
                break;
                
            case 'facebook':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Facebook\\FacebookAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Facebook\FacebookAuth();
                    $auth->handle_oauth_callback();
                }
                break;
                
            default:
                wp_die(__('Unknown OAuth provider.', 'data-machine'));
        }
        
        exit;
    }, 5);
    
    // Central OAuth operations filter - eliminates handler-specific OAuth code duplication
    // Account data: apply_filters('dm_oauth', [], 'retrieve', 'twitter'); apply_filters('dm_oauth', null, 'store', 'twitter', $data);
    // Config data: apply_filters('dm_oauth', [], 'get_config', 'twitter'); apply_filters('dm_oauth', null, 'store_config', 'twitter', $config);
    add_filter('dm_oauth', function($result, $operation, $handler, $data = null) {
        // Use centralized option for all auth data
        $all_auth_data = get_option('dm_auth_data', []);
        
        switch ($operation) {
            case 'store':
                // Store account data (access tokens, etc.)
                if (!isset($all_auth_data[$handler])) {
                    $all_auth_data[$handler] = [];
                }
                $all_auth_data[$handler]['account'] = $data;
                return update_option('dm_auth_data', $all_auth_data);
                
            case 'retrieve':
                // Retrieve account data
                return $all_auth_data[$handler]['account'] ?? [];
                
            case 'clear':
                // Clear account data only
                if (isset($all_auth_data[$handler]['account'])) {
                    unset($all_auth_data[$handler]['account']);
                    return update_option('dm_auth_data', $all_auth_data);
                }
                return true;
                
            case 'store_config':
                // Store configuration data (API keys, client secrets, etc.)
                if (!isset($all_auth_data[$handler])) {
                    $all_auth_data[$handler] = [];
                }
                $all_auth_data[$handler]['config'] = $data;
                return update_option('dm_auth_data', $all_auth_data);
                
            case 'get_config':
                // Retrieve configuration data
                return $all_auth_data[$handler]['config'] ?? [];
                
            case 'clear_config':
                // Clear configuration data only
                if (isset($all_auth_data[$handler]['config'])) {
                    unset($all_auth_data[$handler]['config']);
                    return update_option('dm_auth_data', $all_auth_data);
                }
                return true;
                
            case 'clear_all':
                // Clear both config and account data for handler
                if (isset($all_auth_data[$handler])) {
                    unset($all_auth_data[$handler]);
                    return update_option('dm_auth_data', $all_auth_data);
                }
                return true;
                
            default:
                do_action('dm_log', 'error', 'Invalid OAuth operation', [
                    'operation' => $operation,
                    'handler' => $handler
                ]);
                return false;
        }
    }, 10, 4);
    
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
        
        // Get authorization URL from provider's auth handler
        switch ($provider) {
            case 'reddit':
                if (class_exists('DataMachine\\Core\\Handlers\\Fetch\\Reddit\\RedditAuth')) {
                    $auth = new DataMachine\Core\Handlers\Fetch\Reddit\RedditAuth();
                    return $auth->get_authorization_url();
                }
                break;
                
            case 'twitter':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Twitter\\TwitterAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Twitter\TwitterAuth();
                    return $auth->get_authorization_url();
                }
                break;
                
            case 'googlesheets':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\GoogleSheets\\GoogleSheetsAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\GoogleSheets\GoogleSheetsAuth();
                    return $auth->get_authorization_url();
                }
                break;
                
            case 'threads':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Threads\\ThreadsAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Threads\ThreadsAuth();
                    return $auth->get_authorization_url();
                }
                break;
                
            case 'facebook':
                if (class_exists('DataMachine\\Core\\Handlers\\Publish\\Facebook\\FacebookAuth')) {
                    $auth = new DataMachine\Core\Handlers\Publish\Facebook\FacebookAuth();
                    return $auth->get_authorization_url();
                }
                break;
        }
        
        return new WP_Error('unknown_provider', __('Unknown OAuth provider.', 'data-machine'));
    }, 10, 2);
}