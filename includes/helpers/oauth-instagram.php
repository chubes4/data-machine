<?php
// Dedicated Instagram OAuth handler for Data Machine plugin

/**
 * Bootstrap WordPress (minimal)
 * This file is: /wp-content/plugins/data-machine/includes/helpers/oauth-instagram.php
 * WP root is six levels up: /wp-load.php
 */
require_once dirname(__FILE__, 6) . '/wp-load.php';

if (!is_user_logged_in()) {
    wp_die('You must be logged in to authenticate with Instagram.');
}

$user_id = get_current_user_id();
$client_id = get_option('instagram_oauth_client_id');
$client_secret = get_option('instagram_oauth_client_secret');
$redirect_uri = site_url('/oauth-instagram/');

if (isset($_GET['start'])) {
    // Start OAuth flow
    $scope = 'user_profile,user_media';
    $auth_url = 'https://api.instagram.com/oauth/authorize'
        . '?client_id=' . urlencode($client_id)
        . '&redirect_uri=' . urlencode($redirect_uri)
        . '&scope=' . urlencode($scope)
        . '&response_type=code';
    header('Location: ' . $auth_url);
    exit;
}

if (isset($_GET['code'])) {
    // Handle callback: exchange code for token
    $code = sanitize_text_field($_GET['code']);
    $response = wp_remote_post('https://api.instagram.com/oauth/access_token', [
        'body' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        ]
    ]);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['access_token']) && !empty($data['user_id'])) {
        $access_token = $data['access_token'];
        $user_id_ig = $data['user_id'];
        // Fetch user info
        $user_info_response = wp_remote_get('https://graph.instagram.com/' . $user_id_ig . '?fields=id,username,account_type,media_count,profile_picture_url&access_token=' . urlencode($access_token));
        $user_info = json_decode(wp_remote_retrieve_body($user_info_response), true);

        if (!empty($user_info['id'])) {
            $accounts = get_user_meta($user_id, 'data_machine_instagram_accounts', true);
            if (!is_array($accounts)) $accounts = [];
            $accounts[] = [
                'id' => $user_info['id'],
                'username' => $user_info['username'],
                'profile_pic' => $user_info['profile_picture_url'] ?? '',
                'access_token' => $access_token,
                'account_type' => $user_info['account_type'] ?? '',
                'media_count' => $user_info['media_count'] ?? 0,
                'expires_at' => isset($data['expires_in']) ? date('Y-m-d H:i:s', time() + intval($data['expires_in'])) : '',
            ];
            update_user_meta($user_id, 'data_machine_instagram_accounts', $accounts);
            // Close popup and notify parent
            echo '<script>window.close();</script>';
            exit;
        }
    }
    // On error, close popup
    echo '<script>window.close();</script>';
    exit;
}

// If accessed directly without params, show a message
echo 'Instagram OAuth handler ready.';
exit;