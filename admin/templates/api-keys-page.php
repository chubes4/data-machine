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
    add_settings_error('Data_Machine_api_keys_messages', 'auth_success', ucfirst($service) . __(' account authenticated successfully!', 'data-machine'), 'success');
}
if (isset($_GET['auth_error'])) {
     $error_code = sanitize_text_field($_GET['auth_error']);
     // Add more user-friendly messages based on error codes if needed
    add_settings_error('Data_Machine_api_keys_messages', 'auth_error', __('Failed to authenticate account. Error: ', 'data-machine') . esc_html($error_code), 'error');
}


settings_errors('Data_Machine_api_keys_messages'); // Display notices
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        // Use the correct option group name defined during setting registration
        settings_fields('dm_api_keys_group');
        // If using settings sections, uncomment the line below
        // do_settings_sections('dm-api-keys');
        wp_nonce_field('dm_save_api_keys_user_meta', '_wpnonce_dm_api_keys_user_meta'); // Add nonce for user meta fields
        ?>

        <hr>
        <h2>API Credentials</h2>
        <p>Enter the API credentials for the services you want to use as data sources.</p>

        <h3 style="margin-top: 20px;">OpenAI API Key</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
                <td>
                    <input type="text" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr(get_option('openai_api_key', '')); ?>" class="regular-text" />
                    <p class="description">Enter your API key from OpenAI for features like content generation or analysis.</p>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 20px;">Instagram API Credentials</h3>
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

        <h3 style="margin-top: 20px;">Twitter API Credentials (App Keys)</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="twitter_api_key">Twitter API Key (Consumer Key)</label></th>
                <td>
                    <input type="text" id="twitter_api_key" name="twitter_api_key" value="<?php echo esc_attr(get_option('twitter_api_key', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Key (sometimes called Consumer Key).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="twitter_api_secret">Twitter API Secret (Consumer Secret)</label></th>
                <td>
                    <input type="text" id="twitter_api_secret" name="twitter_api_secret" value="<?php echo esc_attr(get_option('twitter_api_secret', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Secret (sometimes called Consumer Secret).</p>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 20px;">Reddit API Credentials</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="reddit_oauth_client_id">Reddit Client ID</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_id" name="reddit_oauth_client_id" value="<?php echo esc_attr(get_option('reddit_oauth_client_id', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client ID (found under your app details on Reddit's app preferences page - it's the string under the app name/type).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reddit_oauth_client_secret">Reddit Client Secret</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_secret" name="reddit_oauth_client_secret" value="<?php echo esc_attr(get_option('reddit_oauth_client_secret', '')); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client Secret.</p>
                </td>
            </tr>
             <tr>
                <th scope="row"><label for="reddit_developer_username">Reddit Developer Username</label></th>
                <td>
                    <input type="text" id="reddit_developer_username" name="reddit_developer_username" value="<?php echo esc_attr(get_option('reddit_developer_username', '')); ?>" class="regular-text" />
                    <p class="description">Enter the Reddit username (without u/) associated with the account that registered the app above. Required for the User-Agent string in API calls.</p>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 20px;">Bluesky Credentials</h3>
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

        <?php
        submit_button('Save API Credentials');
        ?>
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

<?php
// Add JavaScript for handling the popup window (using admin-post.php)
?>
<script type="text/javascript">
    jQuery(document).ready(function($) {

        // Function to open OAuth popup and monitor closure
        function openOAuthPopup(url, windowName, width, height) {
            var left = (screen.width / 2) - (width / 2);
            var top = (screen.height / 2) - (height / 2);
            var popup = window.open(url, windowName, 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left);
            
            // Monitor popup closure (basic example)
            var timer = setInterval(function() {
                if (popup.closed) {
                    clearInterval(timer);
                    // Optionally, reload the page to see changes
                    // window.location.reload(); 
                    // Or show a message asking the user to refresh
                    $('#instagram-auth-feedback').text('Authentication window closed. Please refresh if needed.');
                    $('#reddit-auth-feedback').text('Authentication window closed. Please refresh if needed.');
                    $('#twitter-auth-feedback').text('Authentication window closed. Please refresh if needed.'); // Add for Twitter
                }
            }, 1000);

            return popup;
        }

        // Instagram Authentication
        $('#instagram-authenticate-btn').on('click', function() {
            var clientId = $('#instagram_oauth_client_id').val();
            var clientSecret = $('#instagram_oauth_client_secret').val();
            if (!clientId || !clientSecret) {
                alert('Please save your Instagram Client ID and Secret first.');
                return;
            }
             // Use admin-post.php for initiating the flow
            var instagramInitUrl = '<?php echo esc_url(admin_url("admin-post.php?action=dm_instagram_oauth_init")); ?>';
             // Add nonce for security
            instagramInitUrl += '&_wpnonce=<?php echo wp_create_nonce("dm_instagram_oauth_init_nonce"); ?>';

            openOAuthPopup(instagramInitUrl, 'instagramOAuth', 600, 600);
        });

        // Reddit Authentication
        $('#reddit-authenticate-btn').on('click', function() {
             var clientId = $('#reddit_oauth_client_id').val();
             var clientSecret = $('#reddit_oauth_client_secret').val();
             if (!clientId || !clientSecret) {
                 alert('Please save your Reddit Client ID and Secret first.');
                 return;
             }
             // Use admin-post.php for initiating the flow
             var redditInitUrl = '<?php echo esc_url(admin_url("admin-post.php?action=dm_reddit_oauth_init")); ?>';
             // Add nonce for security
             redditInitUrl += '&_wpnonce=<?php echo wp_create_nonce("dm_reddit_oauth_init_nonce"); ?>';

            openOAuthPopup(redditInitUrl, 'redditOAuth', 600, 700);
        });

        // Twitter Authentication
        $('#twitter-authenticate-btn').on('click', function() {
            var button = $(this);
            button.prop('disabled', true);
            $('#twitter-auth-feedback').text('Initiating authentication...');

            // Simple Nonce generation (use wp_localize_script for robust nonces ideally)
            // For now, generate a basic nonce for the init action
            $.post(ajaxurl, { action: 'dm_generate_nonce', id: 'dm_twitter_oauth_init_nonce' }, function(response) {
                if (response.success && response.data.nonce) {
                    var nonce = response.data.nonce;
                    var authUrl = '<?php echo esc_url(admin_url("admin-post.php")); ?>?action=dm_twitter_oauth_init&_wpnonce=' + nonce;
                    $('#twitter-auth-feedback').text('Redirecting to Twitter...');
                    openOAuthPopup(authUrl, 'twitterAuth', 600, 700);
                } else {
                     $('#twitter-auth-feedback').text('Error: Could not generate security token.').css('color', 'red');
                     button.prop('disabled', false);
                }
            }).fail(function() {
                 $('#twitter-auth-feedback').text('Error: AJAX request failed.').css('color', 'red');
                 button.prop('disabled', false);
            });
        });

        // --- Remove Account Buttons --- 

        // Instagram Remove
        $('#instagram-accounts-list').on('click', '.instagram-remove-account-btn', function() {
            e.preventDefault();
            if (!confirm('<?php esc_html_e("Are you sure you want to remove this Instagram account authorization?", "data-machine"); ?>')) return;

            var button = $(this);
            var accountId = button.data('account-id');
            var nonce = button.data('nonce');

            button.prop('disabled', true).text('Removing...');

            $.post(ajaxurl, {
                action: 'dm_remove_instagram_account', // Define this action hook in PHP
                account_id: accountId,
                _ajax_nonce: nonce
            }, function(response) {
                if (response.success) {
                     button.closest('li').fadeOut(300, function() { $(this).remove(); });
                     // Check if list is now empty
                     if ($('#instagram-accounts-list ul li').length === 1) { // Check if only the removed one was left (li itself is removed after fadeOut)
                        // Use timeout to ensure removal is complete before check
                        setTimeout(function() {
                             if ($('#instagram-accounts-list ul li').length === 0) {
                                $('#instagram-accounts-list').html('<p>No Instagram accounts authenticated yet.</p>');
                             }
                        }, 350);
                     }
                } else {
                    alert('Error removing account: ' + (response.data ? response.data.message : 'Unknown error'));
                    button.prop('disabled', false).text('Remove');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error: ", textStatus, errorThrown);
                 alert('Error removing account: Request failed. Check browser console.');
                 button.prop('disabled', false).text('Remove');
            });
        });

        // Reddit Remove
        $('#reddit-accounts-list').on('click', '.reddit-remove-account-btn', function() {
            e.preventDefault();
            if (!confirm('<?php esc_html_e("Are you sure you want to remove this Reddit account authorization? This will remove stored tokens.", "data-machine"); ?>')) return;

            var button = $(this);
            var accountUsername = button.data('account-username'); // Using username as identifier
            var nonce = button.data('nonce');

            button.prop('disabled', true).text('Removing...');

            $.post(ajaxurl, {
                action: 'dm_remove_reddit_account', // Define this action hook in PHP
                account_username: accountUsername,
                _ajax_nonce: nonce
            }, function(response) {
                if (response.success) {
                    // Fade out, remove, and then reload the page to reset state
                    button.closest('li').fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    alert('Error removing account: ' + (response.data ? response.data.message : 'Unknown error'));
                     button.prop('disabled', false).text('Remove');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error: ", textStatus, errorThrown);
                alert('Error removing account: Request failed. Check browser console.');
                 button.prop('disabled', false).text('Remove');
            });
        });

        // Twitter Remove
        $('#twitter-accounts-list').on('click', '.twitter-remove-account-btn', function() {
            var button = $(this);
            var nonce = button.data('nonce');

            if (!confirm('<?php esc_html_e("Are you sure you want to remove this Twitter account connection?", "data-machine"); ?>')) {
                return;
            }

            button.prop('disabled', true);
            $('#twitter-auth-feedback').text('Removing account...').css('color', ''); // Clear previous errors

            $.post(ajaxurl, {
                action: 'dm_remove_twitter_account',
                _ajax_nonce: nonce // WordPress checks this field by default
            }, function(response) {
                if (response.success) {
                    // Reload page to update UI
                    window.location.reload();
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred.';
                    $('#twitter-auth-feedback').text('Error: ' + errorMessage).css('color', 'red');
                    button.prop('disabled', false);
                    alert('<?php esc_html_e("Error removing Twitter account:", "data-machine"); ?> ' + errorMessage);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                 var error = 'AJAX request failed: ' + textStatus + ', ' + errorThrown;
                 $('#twitter-auth-feedback').text('Error: ' + error).css('color', 'red');
                 button.prop('disabled', false);
                 alert('<?php esc_html_e("An error occurred while trying to remove the account. Please try again.", "data-machine"); ?>');
            });
        });

    });
</script>
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