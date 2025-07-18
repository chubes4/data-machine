<?php
/**
 * Provides the HTML structure for the API / Auth settings page.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

// NOTE: OAuth processing logic should ideally be moved to dedicated handlers
// triggered by admin_post_ or admin_init actions, not run directly in the template.

// Check for Auth success/error messages
if (isset($_GET['auth_success'])) {
    $service = sanitize_text_field($_GET['auth_success']);
    if (isset($admin_notices)) $admin_notices->success(ucfirst($service) . __(' account authenticated successfully!', 'data-machine'));
}
if (isset($_GET['auth_error'])) {
     $error_code = sanitize_text_field($_GET['auth_error']);
     if (isset($admin_notices)) $admin_notices->error(__('Failed to authenticate account. Error: ', 'data-machine') . esc_html($error_code));
}

// Check for per-form success notices
if (isset($_GET['openai_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>OpenAI API key saved.</p></div>';
}
if (isset($_GET['bluesky_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Bluesky credentials saved.</p></div>';
}
if (isset($_GET['instagram_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Instagram credentials saved.</p></div>';
}
if (isset($_GET['twitter_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Twitter credentials saved.</p></div>';
}
if (isset($_GET['reddit_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Reddit credentials saved.</p></div>';
}

$twitter_account = get_user_meta(get_current_user_id(), 'data_machine_twitter_account', true);
$reddit_account = get_user_meta(get_current_user_id(), 'data_machine_reddit_account', true);
$instagram_accounts = get_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', true);
if (!is_array($instagram_accounts)) $instagram_accounts = [];
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    // Display admin notices from logger
    if (isset($logger) && method_exists($logger, 'get_pending_notices')) {
        $notices = $logger->get_pending_notices();
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                if (empty($notice['message']) || empty($notice['type'])) continue;
                $type = esc_attr($notice['type']);
                $is_dismissible = isset($notice['is_dismissible']) && $notice['is_dismissible'] ? ' is-dismissible' : '';
                $message = $notice['message'];
                $details = $notice['details'] ?? [];
                $time = $notice['time'] ?? null;
                $css_class = 'notice-' . $type;
                ?>
                <div class="notice <?php echo esc_attr($css_class); ?><?php echo esc_attr($is_dismissible); ?>">
                    <p><?php echo wp_kses_post($message); ?></p>
                    <?php if ($type === 'error' && !empty($details)) : ?>
                        <p><strong><?php esc_html_e('Details:', 'data-machine'); ?></strong></p>
                        <ul class="error-details" style="margin-left: 20px; margin-bottom: 10px;">
                            <?php foreach ($details as $key => $value) : ?>
                                <li><strong><?php echo esc_html(ucfirst($key)); ?>:</strong> <?php
                                    if (is_array($value) || is_object($value)) {
                                        echo '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html('Debug output removed for production') . '</pre>';
                                    } else {
                                        echo esc_html($value);
                                    }
                                ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($time): ?>
                        <p><small>Timestamp: <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $time)); ?></small></p>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
    }
    ?>

    <hr>
    <h2>API Credentials</h2>
    <p>Enter the API credentials for the services you want to use as data sources. Each section saves independently.</p>

    <!-- OpenAI API Key -->
    <h3 style="margin-top: 20px;">OpenAI API Key</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_openai_user_meta" />
        <?php wp_nonce_field('dm_save_openai_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="openai_api_key_user">OpenAI API Key</label></th>
                <td>
                    <input type="text" id="openai_api_key_user" name="openai_api_key_user" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'dm_openai_api_key', true)); ?>" class="regular-text" />
                    <p class="description">Enter your API key from OpenAI for features like content generation or analysis.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save OpenAI API Key</button>
    </form>

    <!-- Bluesky Credentials -->
    <h3 style="margin-top: 20px;">Bluesky Credentials</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_bluesky_user_meta" />
        <?php wp_nonce_field('dm_save_bluesky_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="bluesky_username">Bluesky Handle</label></th>
                <td>
                    <input type="text" id="bluesky_username" name="bluesky_username" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'dm_bluesky_username', true)); ?>" class="regular-text" />
                    <p class="description">Enter your Bluesky handle (e.g., yourname.bsky.social).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="bluesky_app_password">Bluesky App Password</label></th>
                <td>
                    <input type="password" id="bluesky_app_password" name="bluesky_app_password" value="" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to keep current password', 'data-machine'); ?>" autocomplete="new-password" />
                    <p class="description">Enter a <a href="https://bsky.app/settings/app-passwords" target="_blank">Bluesky App Password</a>. Using an app password is recommended for security.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Bluesky Credentials</button>
    </form>

    <!-- Instagram Credentials -->
    <h3 style="margin-top: 20px;">Instagram Credentials</h3>
    <?php $instagram_account = get_user_meta(get_current_user_id(), 'data_machine_instagram_account', true); if (!is_array($instagram_account)) $instagram_account = []; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_instagram_user_meta" />
        <?php wp_nonce_field('dm_save_instagram_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="instagram_oauth_client_id">Instagram App ID (Client ID)</label></th>
                <td>
                    <input type="text" id="instagram_oauth_client_id" name="instagram_oauth_client_id" value="<?php echo esc_attr($instagram_account['client_id'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Instagram App ID (Client ID) from the Facebook Developer Console.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="instagram_oauth_client_secret">Instagram App Secret (Client Secret)</label></th>
                <td>
                    <input type="text" id="instagram_oauth_client_secret" name="instagram_oauth_client_secret" value="<?php echo esc_attr($instagram_account['client_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Instagram App Secret (Client Secret) from the Facebook Developer Console.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Instagram Credentials</button>
    </form>

    <!-- Twitter Credentials (Manual Entry Only) -->
    <h3 style="margin-top: 20px;">Twitter Account</h3>
    <?php $twitter_account = get_user_meta(get_current_user_id(), 'data_machine_twitter_account', true); if (!is_array($twitter_account)) $twitter_account = []; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_twitter_user_meta" />
        <?php wp_nonce_field('dm_save_twitter_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="twitter_api_key">Twitter API Key (Consumer Key)</label></th>
                <td>
                    <input type="text" id="twitter_api_key" name="twitter_api_key" value="<?php echo esc_attr($twitter_account['api_key'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Key (sometimes called Consumer Key).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="twitter_api_secret">Twitter API Secret (Consumer Secret)</label></th>
                <td>
                    <input type="text" id="twitter_api_secret" name="twitter_api_secret" value="<?php echo esc_attr($twitter_account['api_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Secret (sometimes called Consumer Secret).</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Twitter Credentials</button>
    </form>

    <!-- Reddit Credentials (Manual Entry Only) -->
    <h3 style="margin-top: 20px;">Reddit Account</h3>
    <?php $reddit_account = get_user_meta(get_current_user_id(), 'data_machine_reddit_account', true); if (!is_array($reddit_account)) $reddit_account = []; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_reddit_user_meta" />
        <?php wp_nonce_field('dm_save_reddit_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="reddit_oauth_client_id">Reddit Client ID</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_id" name="reddit_oauth_client_id" value="<?php echo esc_attr($reddit_account['client_id'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client ID (found under your app details on Reddit's app preferences page - it's the string under the app name/type).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reddit_oauth_client_secret">Reddit Client Secret</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_secret" name="reddit_oauth_client_secret" value="<?php echo esc_attr($reddit_account['client_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client Secret.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reddit_developer_username">Reddit Developer Username</label></th>
                <td>
                    <input type="text" id="reddit_developer_username" name="reddit_developer_username" value="<?php echo esc_attr($reddit_account['developer_username'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter the Reddit username (without u/) associated with the account that registered the app above. Required for the User-Agent string in API calls.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Reddit Credentials</button>
    </form>

    <!-- Threads Credentials -->
    <h3 style="margin-top: 20px;">Threads Credentials (Required for Posting)</h3>
    <?php $threads_account = get_user_meta(get_current_user_id(), 'data_machine_threads_account', true); if (!is_array($threads_account)) $threads_account = []; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_threads_user_meta" />
        <?php wp_nonce_field('dm_save_threads_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="threads_app_id">Threads App ID</label></th>
                <td>
                    <input type="text" id="threads_app_id" name="threads_app_id" value="<?php echo esc_attr($threads_account['app_id'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Threads App ID (likely obtained via Facebook Developer Console).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="threads_app_secret">Threads App Secret</label></th>
                <td>
                    <input type="text" id="threads_app_secret" name="threads_app_secret" value="<?php echo esc_attr($threads_account['app_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Threads App Secret.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Threads Credentials</button>
    </form>

    <!-- Facebook Credentials -->
    <h3 style="margin-top: 20px;">Facebook Credentials (Required for Posting)</h3>
    <?php $facebook_account = get_user_meta(get_current_user_id(), 'data_machine_facebook_account', true); if (!is_array($facebook_account)) $facebook_account = []; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_facebook_user_meta" />
        <?php wp_nonce_field('dm_save_facebook_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="facebook_app_id">Facebook App ID</label></th>
                <td>
                    <input type="text" id="facebook_app_id" name="facebook_app_id" value="<?php echo esc_attr($facebook_account['app_id'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Facebook App ID from the Facebook Developer Console.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="facebook_app_secret">Facebook App Secret</label></th>
                <td>
                    <input type="text" id="facebook_app_secret" name="facebook_app_secret" value="<?php echo esc_attr($facebook_account['app_secret'] ?? ''); ?>" class="regular-text" />
                    <p class="description">Enter your Facebook App Secret.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Facebook Credentials</button>
    </form>

</div>

<!-- Authenticated Accounts Section -->
<hr>
<h2>Authenticated Accounts</h2>

<!-- Instagram Accounts Section -->
<div class="accounts-section" id="instagram-accounts-section" style="margin-top: 20px;">
    <h3>Instagram Accounts</h3>
    <div id="instagram-accounts-list">
        <?php
        $ig_accounts = get_user_meta(get_current_user_id(), 'data_machine_instagram_accounts', true);
        if (!is_array($ig_accounts)) $ig_accounts = [];
        if (empty($ig_accounts)) {
            echo '<p>No Instagram accounts authenticated yet.</p>';
        } else {
            echo '<ul class="dm-account-list">';
            foreach ($ig_accounts as $index => $acct) { // Use index if needed for removal
                 echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
                 if (!empty($acct['profile_pic'])) {
                     echo '<img src="' . esc_url($acct['profile_pic']) . '" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;">';
                 }
                 echo '<strong>' . esc_html($acct['username']) . '</strong> (' . esc_html($acct['account_type'] ?? 'N/A') . ') ';
                 if (!empty($acct['expires_at'])) {
                     // Note: Instagram short-lived tokens expire quickly. Need long-lived token exchange & refresh logic.
                     $ig_expiry_ts = strtotime($acct['expires_at']);
                     $ig_time_left = $ig_expiry_ts - time();
                     $ig_expires_display = ($ig_time_left > 0) ? human_time_diff(time(), $ig_expiry_ts) . ' left' : 'Expired';
                     echo '<span style="color:' . ($ig_time_left > 0 ? '#888' : 'red') . ';">(Token expires: ' . esc_html(date('Y-m-d H:i', $ig_expiry_ts)) . ' - ' . $ig_expires_display . ')</span> ';
                 }
                 // Add nonce for security
                 $remove_nonce_ig = wp_create_nonce('dm_remove_ig_account_' . $acct['id']);
                 echo '<button class="button button-small button-danger instagram-remove-account-btn" data-account-id="' . esc_attr($acct['id']) . '" data-nonce="' . esc_attr($remove_nonce_ig) . '" style="float: right;">Remove</button>';
                 echo '</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
     <button type="button" id="instagram-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty(get_option('instagram_oauth_client_id')) || empty(get_option('instagram_oauth_client_secret'))); ?>>
        Authenticate New Instagram Account
    </button>
    <span id="instagram-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Authenticate a new Instagram business/creator account. Requires saved Instagram App ID and Secret above.</p>
</div>

<!-- Twitter Accounts Section -->
<div class="accounts-section" id="twitter-accounts-section" style="margin-top: 40px;">
    <h3>Twitter Account</h3>
    <div id="twitter-accounts-list">
        <?php
        $twitter_account = Data_Machine_OAuth_Twitter::get_account_details(get_current_user_id());
        if (empty($twitter_account)):
        ?>
            <p>No Twitter account authenticated yet.</p>
        <?php else:
            $remove_nonce_twitter = wp_create_nonce('dm_remove_twitter_account_' . ($twitter_account['user_id'] ?? 'unknown'));
            // Display authenticated account info
        ?>
            <ul class="dm-account-list">
                 <li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">
                    <?php
                    // Ideally, fetch profile pic URL via API if needed, but screen name is good for now
                    echo '<strong>@' . esc_html($twitter_account['screen_name'] ?? 'UnknownUser') . '</strong> ';
                    echo '(ID: ' . esc_html($twitter_account['user_id'] ?? 'N/A') . ') ';
                    echo '<button class="button button-small button-danger twitter-remove-account-btn" data-nonce="' . esc_attr($remove_nonce_twitter) . '" style="float: right;">Remove</button>';
                    ?>
                 </li>
             </ul>
        <?php endif; ?>
    </div>

    <?php if (empty($twitter_account)): // Only show auth button if not authenticated ?>
    <button type="button" id="twitter-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty(get_option('twitter_api_key')) || empty(get_option('twitter_api_secret'))); ?>>
        Authenticate Twitter Account
    </button>
    <span id="twitter-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Connect the Twitter account you want to post tweets to. Requires saved Twitter API Key and Secret above.</p>
    <?php else:
        // Optionally show a message if already authenticated
    ?>
     <p class="description">Twitter account is authenticated. Remove the existing account to authenticate a different one.</p>
    <?php endif; ?>
</div>

<!-- Reddit Accounts Section -->
<div class="accounts-section" id="reddit-accounts-section" style="margin-top: 40px;">
    <h3>Reddit Account</h3>
    <div id="reddit-accounts-list">
        <?php
        // Retrieve stored Reddit account info (assuming single account per WP user for now)
        $reddit_account = get_user_meta(get_current_user_id(), 'data_machine_reddit_account', true);
        if (empty($reddit_account) || !is_array($reddit_account) || empty($reddit_account['username'])) {
            echo '<p>No Reddit account authenticated yet.</p>';
        } else {
             echo '<ul class="dm-account-list">';
             echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
             // Display Reddit username
             echo '<strong>u/' . esc_html($reddit_account['username']) . '</strong> ';
              if (!empty($reddit_account['token_expires_at'])) {
                    // Calculate remaining time for display
                    $expires_timestamp = $reddit_account['token_expires_at'];
                    $now = time();
                    $time_left = $expires_timestamp - $now;
                    if ($time_left <= 0) {
                        echo '<span style="color:red;">(Token Expired - Refresh Needed)</span> ';
                    } else {
                        $expires_display = human_time_diff($now, $expires_timestamp) . ' left';
                         echo '<span style="color:#888;">(Token expires: ' . esc_html(date('Y-m-d H:i', $expires_timestamp)) . ' - ' . $expires_display . ')</span> ';
                    }
              } else {
                   echo '<span style="color:orange;">(Token expiry unknown)</span> ';
              }
             // Add nonce for security
             $remove_nonce_reddit = wp_create_nonce('dm_remove_reddit_account_' . $reddit_account['username']); // Use username as identifier
             echo '<button class="button button-small button-danger reddit-remove-account-btn" data-account-username="' . esc_attr($reddit_account['username']) . '" data-nonce="' . esc_attr($remove_nonce_reddit) . '" style="float: right;">Remove</button>';
             echo '</li>';
             echo '</ul>';
        }
        ?>
    </div>
    <?php if (empty($reddit_account['username'])): // Only show auth button if not already authenticated ?>
    <button type="button" id="reddit-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty(get_option('reddit_oauth_client_id')) || empty(get_option('reddit_oauth_client_secret'))); ?>>
        Authenticate Reddit Account
    </button>
     <span id="reddit-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Authenticate the Reddit account you want to use for fetching data. Requires saved Reddit Client ID and Secret above.</p>
    <?php else: ?>
     <p class="description">Reddit account is authenticated. Remove the existing account to authenticate a different one.</p>
    <?php endif; ?>
</div>

<!-- Threads Accounts Section -->
<div class="accounts-section" id="threads-accounts-section" style="margin-top: 40px;">
    <h3>Threads Account</h3>
    <div id="threads-accounts-list">
        <?php
        // TODO: Implement logic to retrieve and display authenticated Threads account(s)
        $threads_auth_account = get_user_meta(get_current_user_id(), 'data_machine_threads_auth_account', true); // Example meta key
        if (empty($threads_auth_account) || !is_array($threads_auth_account) || empty($threads_auth_account['username'])) {
            echo '<p>No Threads account authenticated yet.</p>';
        } else {
             echo '<ul class="dm-account-list">';
             echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
             // Display Threads username/ID
             echo '<strong>' . esc_html($threads_auth_account['username'] ?? 'Unknown') . '</strong> '; // Placeholder
             // TODO: Display token expiry if applicable
             // Add nonce for security
             $remove_nonce_threads = wp_create_nonce('dm_remove_threads_account_' . ($threads_auth_account['user_id'] ?? 'unknown')); // Use user_id if available
             echo '<button class="button button-small button-danger threads-remove-account-btn" data-account-id="' . esc_attr($threads_auth_account['user_id'] ?? '') . '" data-nonce="' . esc_attr($remove_nonce_threads) . '" style="float: right;">Remove</button>';
             echo '</li>';
             echo '</ul>';
        }
        ?>
    </div>
    <?php if (empty($threads_auth_account['username'])): // Only show auth button if not already authenticated ?>
    <button type="button" id="threads-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty(get_user_meta(get_current_user_id(), 'data_machine_threads_account', true)['app_id']) || empty(get_user_meta(get_current_user_id(), 'data_machine_threads_account', true)['app_secret'])); ?>>
        Authenticate Threads Account
    </button>
     <span id="threads-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Authenticate the Threads account you want to post to. Requires saved Threads App ID and Secret above.</p>
    <?php else: ?>
     <p class="description">Threads account is authenticated. Remove the existing account to authenticate a different one.</p>
    <?php endif; ?>
</div>

<!-- Facebook Accounts Section -->
<div class="accounts-section" id="facebook-accounts-section" style="margin-top: 40px;">
    <h3>Facebook Account</h3>
    <div id="facebook-accounts-list">
        <?php
        // TODO: Implement logic to retrieve and display authenticated Facebook account(s)/page(s)
        $facebook_auth_account = get_user_meta(get_current_user_id(), 'data_machine_facebook_auth_account', true); // Example meta key
        // Check using user_id as the primary identifier for an established connection
        if (empty($facebook_auth_account) || !is_array($facebook_auth_account) || empty($facebook_auth_account['user_id'])):
            echo '<p>No Facebook account/page authenticated yet.</p>';
        else:
             echo '<ul class="dm-account-list">';
             echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
             // Display Facebook User Name and Page Name (Reverting to ?? operator)
             echo '<strong>User:</strong> ' . esc_html($facebook_auth_account['user_name'] ?? 'Unknown User') . ' (ID: ' . esc_html($facebook_auth_account['user_id'] ?? 'N/A') . ')<br>';
             echo '<strong>Page:</strong> ' . esc_html($facebook_auth_account['page_name'] ?? 'Unknown Page') . ' (ID: ' . esc_html($facebook_auth_account['page_id'] ?? 'N/A') . ') ';
             
             // TODO: Display token expiry if applicable - Needs page token expiry check

             // Add nonce for security - Use the Facebook User ID for consistency with the AJAX handler
             $remove_nonce_facebook = wp_create_nonce('dm_remove_facebook_account_' . ($facebook_auth_account['user_id'] ?? 'unknown'));
             // Pass the actual Facebook User ID in data-account-id for clarity, though nonce relies on it too
             echo '<button class="button button-small button-danger facebook-remove-account-btn" data-account-id="' . esc_attr($facebook_auth_account['user_id'] ?? '') . '" data-nonce="' . esc_attr($remove_nonce_facebook) . '" style="float: right;">Remove</button>';
             echo '</li>';
             echo '</ul>';
        endif;
        ?>
    </div>
    <?php // Check using user_id
    if (empty($facebook_auth_account['user_id'])):
    ?>
    <button type="button" id="facebook-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty(get_user_meta(get_current_user_id(), 'data_machine_facebook_account', true)['app_id']) || empty(get_user_meta(get_current_user_id(), 'data_machine_facebook_account', true)['app_secret'])); ?>>
        Authenticate Facebook Account/Page
    </button>
     <span id="facebook-auth-feedback" style="margin-left: 10px;"></span>
    <p class="description">Authenticate the Facebook account or Page you want to post to. Requires saved Facebook App ID and Secret above.</p>
    <?php else: ?>
     <p class="description">Facebook account/page is authenticated. Remove the existing account to authenticate a different one.</p>
    <?php endif; ?>
</div>

<style>
/* Simple styling for account lists */
.dm-account-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-width: 600px; /* Limit width */
}
.dm-account-list li {
    border: 1px solid #ddd;
    background-color: #f9f9f9;
    padding: 10px 15px;
    margin-bottom: 8px;
    overflow: hidden; /* Contain floated button */
    border-radius: 3px;
}
.dm-account-list li strong {
    font-size: 1.1em;
}
.dm-account-list li span {
    font-size: 0.9em;
    margin-left: 5px;
}
.button-danger { /* Basic red button style */
    background: #d63638;
    border-color: #b02a2c #9e2628 #9e2628;
    box-shadow: 0 1px 0 #9e2628;
    color: #fff;
    text-decoration: none;
    text-shadow: 0 -1px 1px #9e2628, 1px 0 1px #9e2628, 0 1px 1px #9e2628, -1px 0 1px #9e2628;
}
.button-danger:hover {
     background: #e14d4f;
    border-color: #9e2628;
    color: #fff;
}
.accounts-section {
    padding: 15px;
    background: #fff;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
}
.accounts-section h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

</style>