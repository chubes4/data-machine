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
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('OpenAI API key saved.', 'data-machine') . '</p></div>';
}
if (isset($_GET['bluesky_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Bluesky credentials saved.', 'data-machine') . '</p></div>';
}
if (isset($_GET['twitter_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Twitter credentials saved.', 'data-machine') . '</p></div>';
}
if (isset($_GET['reddit_saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Reddit credentials saved.', 'data-machine') . '</p></div>';
}

$twitter_account = get_user_meta(get_current_user_id(), 'data_machine_twitter_account', true);
$reddit_account = get_user_meta(get_current_user_id(), 'data_machine_reddit_account', true);
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
    <h2>AI Provider Configuration</h2>
    <p>Configure AI providers for automated content processing. Data Machine supports multiple AI providers with shared API key management.</p>

    <!-- AI HTTP Client Provider Manager -->
    <div style="margin: 20px 0;">
        <?php
        // Render the AI HTTP Client Provider Manager Component with step-specific configuration
        if (class_exists('AI_HTTP_ProviderManager_Component')) {
            ?>
            
            <!-- Global AI Provider Configuration -->
            <h3>Global AI Provider Settings</h3>
            <p>Configure default AI provider settings. These can be overridden per processing step below.</p>
            <?php
            echo wp_kses_post(AI_HTTP_ProviderManager_Component::render([
                'plugin_context' => 'data-machine',
                'ai_type' => 'llm',
                'components' => [
                    'core' => ['provider_selector', 'api_key_input', 'model_selector'],
                    'extended' => ['temperature_slider', 'system_prompt_field']
                ],
                'component_configs' => [
                    'temperature_slider' => [
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                        'default_value' => 0.7
                    ]
                ]
            ]));
            ?>
            
            <!-- Step-Specific AI Configuration -->
            <h3 style="margin-top: 30px;">Step-Specific AI Configuration</h3>
            <p>Override AI model selection for specific processing steps. Leave blank to use global settings.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 15px;">
                
                <!-- Process Step Configuration -->
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h4>Step 2: Process Data</h4>
                    <p style="font-size: 12px; color: #666;">Analyzes and processes raw input data</p>
                    <?php
                    echo wp_kses_post(AI_HTTP_ProviderManager_Component::render([
                        'plugin_context' => 'data-machine',
                        'ai_type' => 'llm',
                        'step_key' => 'process',
                        'components' => [
                            'core' => ['model_selector']
                        ],
                        'show_title' => false,
                        'show_description' => false
                    ]));
                    ?>
                </div>
                
                <!-- Fact Check Step Configuration -->
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h4>Step 3: Fact Check</h4>
                    <p style="font-size: 12px; color: #666;">Verifies information accuracy using web search</p>
                    <?php
                    echo wp_kses_post(AI_HTTP_ProviderManager_Component::render([
                        'plugin_context' => 'data-machine',
                        'ai_type' => 'llm',
                        'step_key' => 'factcheck',
                        'components' => [
                            'core' => ['model_selector']
                        ],
                        'show_title' => false,
                        'show_description' => false
                    ]));
                    ?>
                </div>
                
                <!-- Finalize Step Configuration -->
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h4>Step 4: Finalize</h4>
                    <p style="font-size: 12px; color: #666;">Formats content for final output destination</p>
                    <?php
                    echo wp_kses_post(AI_HTTP_ProviderManager_Component::render([
                        'plugin_context' => 'data-machine',
                        'ai_type' => 'llm',
                        'step_key' => 'finalize',
                        'components' => [
                            'core' => ['model_selector']
                        ],
                        'show_title' => false,
                        'show_description' => false
                    ]));
                    ?>
                </div>
                
            </div>
            
            <?php
        } else {
            echo '<div class="notice notice-warning"><p>' . esc_html__('AI HTTP Client library not loaded. Using legacy OpenAI integration.', 'data-machine') . '</p></div>';
        }
        ?>
    </div>

    <hr style="margin: 30px 0;">
    <h2>Social Media & Platform Credentials</h2>
    <p>Configure credentials for social media platforms and external services used for content publishing.</p>

    <!-- Bluesky Credentials -->
    <h3 style="margin-top: 20px;">Bluesky Credentials</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_bluesky_user_meta" />
        <?php wp_nonce_field('dm_save_bluesky_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="bluesky_username">Bluesky Handle</label></th>
                <td>
                    <input type="text" id="bluesky_username" name="bluesky_username" value="<?php echo esc_attr(get_option('bluesky_username', '')); ?>" class="regular-text" />
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


    <!-- Twitter Credentials (Manual Entry Only) -->
    <h3 style="margin-top: 20px;">Twitter Account</h3>
    <?php $twitter_api_key = get_option('twitter_api_key', ''); $twitter_api_secret = get_option('twitter_api_secret', ''); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_twitter_user_meta" />
        <?php wp_nonce_field('dm_save_twitter_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="twitter_api_key">Twitter API Key (Consumer Key)</label></th>
                <td>
                    <input type="text" id="twitter_api_key" name="twitter_api_key" value="<?php echo esc_attr($twitter_api_key); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Key (sometimes called Consumer Key).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="twitter_api_secret">Twitter API Secret (Consumer Secret)</label></th>
                <td>
                    <input type="text" id="twitter_api_secret" name="twitter_api_secret" value="<?php echo esc_attr($twitter_api_secret); ?>" class="regular-text" />
                    <p class="description">Enter your Twitter App's API Secret (sometimes called Consumer Secret).</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Twitter Credentials</button>
    </form>

    <!-- Reddit Credentials (Manual Entry Only) -->
    <h3 style="margin-top: 20px;">Reddit Account</h3>
    <?php $reddit_client_id = get_option('reddit_oauth_client_id', ''); $reddit_client_secret = get_option('reddit_oauth_client_secret', ''); $reddit_developer_username = get_option('reddit_developer_username', ''); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_reddit_user_meta" />
        <?php wp_nonce_field('dm_save_reddit_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="reddit_oauth_client_id">Reddit Client ID</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_id" name="reddit_oauth_client_id" value="<?php echo esc_attr($reddit_client_id); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client ID (found under your app details on Reddit's app preferences page - it's the string under the app name/type).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reddit_oauth_client_secret">Reddit Client Secret</label></th>
                <td>
                    <input type="text" id="reddit_oauth_client_secret" name="reddit_oauth_client_secret" value="<?php echo esc_attr($reddit_client_secret); ?>" class="regular-text" />
                    <p class="description">Enter your Reddit App Client Secret.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reddit_developer_username">Reddit Developer Username</label></th>
                <td>
                    <input type="text" id="reddit_developer_username" name="reddit_developer_username" value="<?php echo esc_attr($reddit_developer_username); ?>" class="regular-text" />
                    <p class="description">Enter the Reddit username (without u/) associated with the account that registered the app above. Required for the User-Agent string in API calls.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Reddit Credentials</button>
    </form>

    <!-- Threads Credentials -->
    <h3 style="margin-top: 20px;">Threads Credentials (Required for Posting)</h3>
    <?php $threads_app_id = get_option('threads_app_id', ''); $threads_app_secret = get_option('threads_app_secret', ''); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_threads_user_meta" />
        <?php wp_nonce_field('dm_save_threads_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="threads_app_id">Threads App ID</label></th>
                <td>
                    <input type="text" id="threads_app_id" name="threads_app_id" value="<?php echo esc_attr($threads_app_id); ?>" class="regular-text" />
                    <p class="description">Enter your Threads App ID (likely obtained via Facebook Developer Console).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="threads_app_secret">Threads App Secret</label></th>
                <td>
                    <input type="text" id="threads_app_secret" name="threads_app_secret" value="<?php echo esc_attr($threads_app_secret); ?>" class="regular-text" />
                    <p class="description">Enter your Threads App Secret.</p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary">Save Threads Credentials</button>
    </form>

    <!-- Facebook Credentials -->
    <h3 style="margin-top: 20px;">Facebook Credentials (Required for Posting)</h3>
    <?php $facebook_app_id = get_option('facebook_app_id', ''); $facebook_app_secret = get_option('facebook_app_secret', ''); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="dm_save_facebook_user_meta" />
        <?php wp_nonce_field('dm_save_facebook_user_meta_action'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="facebook_app_id">Facebook App ID</label></th>
                <td>
                    <input type="text" id="facebook_app_id" name="facebook_app_id" value="<?php echo esc_attr($facebook_app_id); ?>" class="regular-text" />
                    <p class="description">Enter your Facebook App ID from the Facebook Developer Console.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="facebook_app_secret">Facebook App Secret</label></th>
                <td>
                    <input type="text" id="facebook_app_secret" name="facebook_app_secret" value="<?php echo esc_attr($facebook_app_secret); ?>" class="regular-text" />
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


<!-- Twitter Accounts Section -->
<div class="accounts-section" id="twitter-accounts-section" style="margin-top: 40px;">
    <h3>Twitter Account</h3>
    <div id="twitter-accounts-list">
        <?php
        $twitter_account = \DataMachine\Admin\OAuth\Twitter::get_account_details(get_current_user_id());
        if (empty($twitter_account)):
        ?>
            <p><?php echo esc_html__('No Twitter account authenticated yet.', 'data-machine'); ?></p>
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
                    echo '<button class="button button-small button-danger twitter-remove-account-btn" data-nonce="' . esc_attr($remove_nonce_twitter) . '" style="float: right;">' . esc_html__('Remove', 'data-machine') . '</button>';
                    ?>
                 </li>
             </ul>
        <?php endif; ?>
    </div>

    <?php if (empty($twitter_account)): // Only show auth button if not authenticated ?>
    <button type="button" id="twitter-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty($twitter_api_key) || empty($twitter_api_secret)); ?>>
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
            echo '<p>' . esc_html__('No Reddit account authenticated yet.', 'data-machine') . '</p>';
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
                        echo '<span style="color:red;">(' . esc_html__('Token Expired - Refresh Needed', 'data-machine') . ')</span> ';
                    } else {
                        $expires_display = human_time_diff($now, $expires_timestamp) . ' left';
                         echo '<span style="color:#888;">(' . esc_html(sprintf(__('Token expires: %s - %s', 'data-machine'), date('Y-m-d H:i', $expires_timestamp), $expires_display)) . ')</span> ';
                    }
              } else {
                   echo '<span style="color:orange;">(' . esc_html__('Token expiry unknown', 'data-machine') . ')</span> ';
              }
             // Add nonce for security
             $remove_nonce_reddit = wp_create_nonce('dm_remove_reddit_account_' . $reddit_account['username']); // Use username as identifier
             echo '<button class="button button-small button-danger reddit-remove-account-btn" data-account-username="' . esc_attr($reddit_account['username']) . '" data-nonce="' . esc_attr($remove_nonce_reddit) . '" style="float: right;">' . esc_html__('Remove', 'data-machine') . '</button>';
             echo '</li>';
             echo '</ul>';
        }
        ?>
    </div>
    <?php if (empty($reddit_account['username'])): // Only show auth button if not already authenticated ?>
    <button type="button" id="reddit-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty($reddit_client_id) || empty($reddit_client_secret)); ?>>
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
        $threads_auth_account = get_user_meta(get_current_user_id(), 'data_machine_threads_auth_account', true);
        if (empty($threads_auth_account) || !is_array($threads_auth_account) || empty($threads_auth_account['page_name'])) {
            echo '<p>' . esc_html__('No Threads account authenticated yet.', 'data-machine') . '</p>';
        } else {
             echo '<ul class="dm-account-list">';
             echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
             
             // Display Threads page name and ID
             echo '<strong>' . esc_html($threads_auth_account['page_name'] ?? 'Unknown') . '</strong>';
             if (!empty($threads_auth_account['page_id'])) {
                 echo ' <span style="color: #666;">(ID: ' . esc_html($threads_auth_account['page_id']) . ')</span>';
             }
             
             // Display token expiry if available
             if (!empty($threads_auth_account['token_expires_at'])) {
                 $expires_at = intval($threads_auth_account['token_expires_at']);
                 $time_until_expiry = $expires_at - time();
                 if ($time_until_expiry > 0) {
                     echo '<br><small style="color: #666;">Token expires: ' . esc_html(human_time_diff($expires_at)) . '</small>';
                 } else {
                     echo '<br><small style="color: #d63638;">Token expired</small>';
                 }
             }
             
             // Add remove button with proper nonce
             $remove_nonce_threads = wp_create_nonce('dm_remove_threads_account_' . get_current_user_id());
             echo '<button class="button button-small button-danger threads-remove-account-btn" data-account-id="' . esc_attr($threads_auth_account['page_id'] ?? '') . '" data-nonce="' . esc_attr($remove_nonce_threads) . '" style="float: right;">' . esc_html__('Remove', 'data-machine') . '</button>';
             echo '</li>';
             echo '</ul>';
        }
        ?>
    </div>
    <?php if (empty($threads_auth_account['username'])): // Only show auth button if not already authenticated ?>
    <button type="button" id="threads-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty($threads_app_id) || empty($threads_app_secret)); ?>>
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
        $facebook_auth_account = get_user_meta(get_current_user_id(), 'data_machine_facebook_auth_account', true);
        if (empty($facebook_auth_account) || !is_array($facebook_auth_account) || empty($facebook_auth_account['user_id'])):
            echo '<p>' . esc_html__('No Facebook account/page authenticated yet.', 'data-machine') . '</p>';
        else:
             echo '<ul class="dm-account-list">';
             echo '<li style="margin-bottom: 10px; padding: 5px; border: 1px solid #eee;">';
             
             // Display Facebook User Name and Page Name
             echo '<strong>User:</strong> ' . esc_html($facebook_auth_account['user_name'] ?? 'Unknown User') . ' <span style="color: #666;">(ID: ' . esc_html($facebook_auth_account['user_id'] ?? 'N/A') . ')</span><br>';
             echo '<strong>Page:</strong> ' . esc_html($facebook_auth_account['page_name'] ?? 'Unknown Page') . ' <span style="color: #666;">(ID: ' . esc_html($facebook_auth_account['page_id'] ?? 'N/A') . ')</span>';
             
             // Display token expiry if available
             if (!empty($facebook_auth_account['token_expires_at'])) {
                 $expires_at = intval($facebook_auth_account['token_expires_at']);
                 $time_until_expiry = $expires_at - time();
                 if ($time_until_expiry > 0) {
                     echo '<br><small style="color: #666;">Token expires: ' . esc_html(human_time_diff($expires_at)) . '</small>';
                 } else {
                     echo '<br><small style="color: #d63638;">Token expired</small>';
                 }
             }

             // Add remove button with proper nonce
             $remove_nonce_facebook = wp_create_nonce('dm_remove_facebook_account_' . get_current_user_id());
             echo '<button class="button button-small button-danger facebook-remove-account-btn" data-account-id="' . esc_attr($facebook_auth_account['user_id'] ?? '') . '" data-nonce="' . esc_attr($remove_nonce_facebook) . '" style="float: right;">' . esc_html__('Remove', 'data-machine') . '</button>';
             echo '</li>';
             echo '</ul>';
        endif;
        ?>
    </div>
    <?php // Check using user_id
    if (empty($facebook_auth_account['user_id'])):
    ?>
    <button type="button" id="facebook-authenticate-btn" class="button button-primary" style="margin-top: 10px;" <?php disabled(empty($facebook_app_id) || empty($facebook_app_secret)); ?>>
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