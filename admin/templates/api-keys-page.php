<?php
/**
 * Provides the HTML structure for the API / Auth settings page.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

// Instagram OAuth flow logic
if (isset($_GET['instagram_oauth'])) {
    $client_id = get_option('instagram_oauth_client_id');
    $client_secret = get_option('instagram_oauth_client_secret');
    $redirect_uri = admin_url('admin.php?page=dm-api-keys&instagram_oauth=1');
    $scope = 'user_profile,user_media';

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
                $accounts = get_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', true);
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
                update_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', $accounts);
                // Redirect to remove query params
                wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_success=1'));
                exit;
            }
        }
        // On error, redirect with error param
        wp_redirect(admin_url('admin.php?page=dm-api-keys&auth_error=1'));
        exit;
    } else {
        // Start OAuth flow
        $auth_url = 'https://api.instagram.com/oauth/authorize'
            . '?client_id=' . urlencode($client_id)
            . '&redirect_uri=' . urlencode($redirect_uri)
            . '&scope=' . urlencode($scope)
            . '&response_type=code';
        wp_redirect($auth_url);
        exit;
    }
}

settings_errors('Data_Machine_api_keys_messages'); // Use a specific message key if needed
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        // Use the new option group name
        settings_fields('dm_api_keys_group');
        // Use the new page slug for displaying sections
        do_settings_sections('dm-api-keys');
        ?>
        <h2>Instagram API Credentials</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="instagram_oauth_client_id">Instagram App ID (Client ID)</label></th>
                <td>
                    <input type="text" id="instagram_oauth_client_id" name="instagram_oauth_client_id" value="<?php echo esc_attr(get_option('instagram_oauth_client_id', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Instagram App ID (Client ID) from the Facebook Developer Console.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="instagram_oauth_client_secret">Instagram App Secret (Client Secret)</label></th>
                <td>
                    <input type="text" id="instagram_oauth_client_secret" name="instagram_oauth_client_secret" value="<?php echo esc_attr(get_option('instagram_oauth_client_secret', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Instagram App Secret (Client Secret) from the Facebook Developer Console.</p>
                </td>
            </tr>
        </table>
        <?php
        submit_button('Save API Keys');
        ?>
    </form>
</div>

<!-- Instagram Accounts Section -->
<div class="wrap" id="instagram-accounts-section" style="margin-top: 40px;">
    <h2>Instagram Accounts</h2>
    <div id="instagram-accounts-list">
        <?php
        $accounts = get_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', true);
        if (!is_array($accounts)) $accounts = [];
        if (empty($accounts)) {
            echo '<p>No Instagram accounts authenticated yet.</p>';
        } else {
            echo '<ul>';
            foreach ($accounts as $acct) {
                echo '<li style="margin-bottom: 10px;">';
                if (!empty($acct['profile_pic'])) {
                    echo '<img src="' . esc_url($acct['profile_pic']) . '" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;">';
                }
                echo '<strong>' . esc_html($acct['username']) . '</strong> ';
                if (!empty($acct['expires_at'])) {
                    echo '<span style="color:#888;">(expires: ' . esc_html($acct['expires_at']) . ')</span> ';
                }
                echo '<button class="button button-small instagram-remove-account-btn" data-account-id="' . esc_attr($acct['id']) . '">Remove</button>';
                echo '</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
    <button type="button" id="instagram-authenticate-btn" class="button button-primary" style="margin-top: 10px;">
        Authenticate New Instagram Account
    </button>
    <span id="instagram-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Authenticate a new Instagram business account to use with Data Machine. You can manage multiple accounts here.</p>
</div>