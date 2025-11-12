<?php

/**
 * OAuth system with public callbacks and filter-based data operations.
 */

if (!defined('WPINC')) {
    die;
}

function datamachine_register_oauth_system() {

    add_action('init', function() {
        add_rewrite_rule(
            '^datamachine-auth/([^/]+)/?$',
            'index.php?datamachine_oauth_provider=$matches[1]',
            'top'
        );
    });

    add_filter('query_vars', function($vars) {
        $vars[] = 'datamachine_oauth_provider';
        return $vars;
    });

    add_action('template_redirect', function() {
        $provider = get_query_var('datamachine_oauth_provider');
        
        if (!$provider) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions for OAuth operations.', 'datamachine'));
        }

        $all_auth = apply_filters('datamachine_auth_providers', []);
        $auth_instance = $all_auth[$provider] ?? null;
        
        if ($auth_instance && method_exists($auth_instance, 'handle_oauth_callback')) {
            $auth_instance->handle_oauth_callback();
        } else {
            wp_die(esc_html__('Unknown OAuth provider.', 'datamachine'));
        }

        exit;
    }, 5);

    add_filter('datamachine_store_oauth_account', function($data, $provider) {
        $all_auth_data = get_option('datamachine_auth_data', []);
        if (!isset($all_auth_data[$provider])) {
            $all_auth_data[$provider] = [];
        }
        $all_auth_data[$provider]['account'] = $data;
        return update_option('datamachine_auth_data', $all_auth_data);
    }, 10, 2);

    add_filter('datamachine_retrieve_oauth_account', function($result, $provider) {
        $all_auth_data = get_option('datamachine_auth_data', []);
        return $all_auth_data[$provider]['account'] ?? [];
    }, 10, 2);

    add_filter('datamachine_clear_oauth_account', function($result, $provider) {
        $all_auth_data = get_option('datamachine_auth_data', []);
        if (isset($all_auth_data[$provider]['account'])) {
            unset($all_auth_data[$provider]['account']);
            return update_option('datamachine_auth_data', $all_auth_data);
        }
        return true;
    }, 10, 2);

    add_filter('datamachine_store_oauth_keys', function($data, $provider) {
        $all_auth_data = get_option('datamachine_auth_data', []);
        if (!isset($all_auth_data[$provider])) {
            $all_auth_data[$provider] = [];
        }
        $all_auth_data[$provider]['config'] = $data;
        return update_option('datamachine_auth_data', $all_auth_data);
    }, 10, 2);

    add_filter('datamachine_retrieve_oauth_keys', function($result, $provider) {
        $all_auth_data = get_option('datamachine_auth_data', []);
        return $all_auth_data[$provider]['config'] ?? [];
    }, 10, 2);

    add_filter('datamachine_oauth_callback', function($url, $provider) {
        if (empty($url)) {
            $url = site_url("/datamachine-auth/{$provider}/");
        }
        return $url;
    }, 10, 2);

    add_filter('datamachine_oauth_url', function($auth_url, $provider) {
        if (!empty($auth_url)) {
            return $auth_url;
        }

        $all_auth = apply_filters('datamachine_auth_providers', []);
        $auth_instance = $all_auth[$provider] ?? null;
        
        if ($auth_instance && method_exists($auth_instance, 'get_authorization_url')) {
            return $auth_instance->get_authorization_url();
        }
        
        do_action('datamachine_log', 'error', 'OAuth Error: Unknown provider requested.', ['provider' => $provider]);
        return new WP_Error('unknown_provider', __('Unknown OAuth provider.', 'datamachine'));
    }, 10, 2);
}