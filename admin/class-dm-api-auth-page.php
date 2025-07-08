<?php
/**
 * Handles API Auth and user meta saving for the API Keys page.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Api_Auth_Page {
    /**
     * Admin notices object.
     * @var object
     */
    private $admin_notices;

    /**
     * Constructor.
     * @param object $admin_notices Admin notices object.
     */
    public function __construct( $admin_notices = null ) {
        $this->admin_notices = $admin_notices;
        add_action( 'admin_init', array( $this, 'handle_api_keys_page_user_meta_save' ) );
        add_action('admin_post_dm_save_openai_user_meta', function() {
            // Debug: Log POST and SERVER data
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_openai_user_meta_action');
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'dm_openai_api_key', sanitize_text_field($_POST['openai_api_key_user'] ?? ''));
            wp_redirect(add_query_arg('openai_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_bluesky_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_bluesky_user_meta_action');
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'dm_bluesky_username', sanitize_text_field($_POST['bluesky_username'] ?? ''));
            if (isset($_POST['bluesky_app_password']) && $_POST['bluesky_app_password'] !== '') {
                $encrypted_password = Data_Machine_Encryption_Helper::encrypt($_POST['bluesky_app_password']);
                update_user_meta($user_id, 'dm_bluesky_app_password', $encrypted_password);
            }
            wp_redirect(add_query_arg('bluesky_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_instagram_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_instagram_user_meta_action');
            $user_id = get_current_user_id();
            $instagram_account = get_user_meta($user_id, 'data_machine_instagram_account', true);
            if (!is_array($instagram_account)) $instagram_account = [];
            $instagram_account = array_merge($instagram_account, [
                'client_id' => sanitize_text_field($_POST['instagram_oauth_client_id'] ?? ''),
                'client_secret' => sanitize_text_field($_POST['instagram_oauth_client_secret'] ?? ''),
            ]);
            update_user_meta($user_id, 'data_machine_instagram_account', $instagram_account);
            wp_redirect(add_query_arg('instagram_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_twitter_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_twitter_user_meta_action');
            $user_id = get_current_user_id();
            $twitter_account = get_user_meta($user_id, 'data_machine_twitter_account', true);
            if (!is_array($twitter_account)) $twitter_account = [];
            $twitter_account = array_merge($twitter_account, [
                'api_key' => sanitize_text_field($_POST['twitter_api_key'] ?? ''),
                'api_secret' => sanitize_text_field($_POST['twitter_api_secret'] ?? ''),
            ]);
            update_user_meta($user_id, 'data_machine_twitter_account', $twitter_account);
            wp_redirect(add_query_arg('twitter_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_reddit_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_reddit_user_meta_action');
            $user_id = get_current_user_id();
            $reddit_account = get_user_meta($user_id, 'data_machine_reddit_account', true);
            if (!is_array($reddit_account)) $reddit_account = [];
            $reddit_account = array_merge($reddit_account, [
                'client_id' => sanitize_text_field($_POST['reddit_oauth_client_id'] ?? ''),
                'client_secret' => sanitize_text_field($_POST['reddit_oauth_client_secret'] ?? ''),
                'developer_username' => sanitize_text_field($_POST['reddit_developer_username'] ?? ''),
            ]);
            update_user_meta($user_id, 'data_machine_reddit_account', $reddit_account);
            wp_redirect(add_query_arg('reddit_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_threads_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_threads_user_meta_action');
            $user_id = get_current_user_id();
            $threads_account = get_user_meta($user_id, 'data_machine_threads_account', true);
            if (!is_array($threads_account)) $threads_account = [];
            $threads_account = array_merge($threads_account, [
                'app_id' => sanitize_text_field($_POST['threads_app_id'] ?? ''),
                'app_secret' => sanitize_text_field($_POST['threads_app_secret'] ?? ''), // Consider encryption if needed later
            ]);
            update_user_meta($user_id, 'data_machine_threads_account', $threads_account);
            wp_redirect(add_query_arg('threads_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_facebook_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) wp_die('Unauthorized');
            check_admin_referer('dm_save_facebook_user_meta_action');
            $user_id = get_current_user_id();
            $facebook_account = get_user_meta($user_id, 'data_machine_facebook_account', true);
            if (!is_array($facebook_account)) $facebook_account = [];
            $facebook_account = array_merge($facebook_account, [
                'app_id' => sanitize_text_field($_POST['facebook_app_id'] ?? ''),
                'app_secret' => sanitize_text_field($_POST['facebook_app_secret'] ?? ''), // Consider encryption if needed later
            ]);
            update_user_meta($user_id, 'data_machine_facebook_account', $facebook_account);
            wp_redirect(add_query_arg('facebook_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });
    }

    /**
     * Handle saving user-specific meta fields from the API Keys page.
     * Hooks into admin_init to catch the POST request before options.php processes it.
     *
     * @since NEXT_VERSION
     */
    public function handle_api_keys_page_user_meta_save() {
        // Check if this is a POST request for the API keys page
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ||
             !isset($_POST['option_page']) || 'dm_api_keys_group' !== $_POST['option_page'] ) {
            return; // Not our form submission
        }

        // Verify the nonce specific to user meta fields on this page
        if ( !isset($_POST['_wpnonce_dm_api_keys_user_meta']) || 
             !wp_verify_nonce($_POST['_wpnonce_dm_api_keys_user_meta'], 'dm_save_api_keys_user_meta') ) {
            // Nonce failed, log or add an error notice? Silently return for now.
            return;
        }

        // Check user capabilities
        if ( !current_user_can('manage_options') ) { // Assuming manage_options is the capability for this page
            return; 
        }

        $user_id = get_current_user_id();
        $updated = false;

        // Handle Bluesky Username
        if ( isset($_POST['bluesky_username']) ) {
            $bluesky_username = sanitize_text_field($_POST['bluesky_username']);
            update_user_meta($user_id, 'dm_bluesky_username', $bluesky_username);
            $updated = true;
        }

        // Handle Bluesky Password (always update on save)
        if ( isset($_POST['bluesky_app_password']) ) {
            try {
                $encrypted_password = Data_Machine_Encryption_Helper::encrypt($_POST['bluesky_app_password']);
                if ($encrypted_password === false) {
                    // Error logging removed for production
                    if ($this->admin_notices) $this->admin_notices->error(__('Failed to encrypt Bluesky app password. It was not saved.', 'data-machine'));
                } else {
                    update_user_meta($user_id, 'dm_bluesky_app_password', $encrypted_password);
                    $updated = true;
                }
            } catch (\Exception $e) {
                // Error logging removed for production
                if ($this->admin_notices) $this->admin_notices->error(__('An error occurred while encrypting the Bluesky app password. It was not saved.', 'data-machine'));
            }
        }

        // Handle OpenAI API Key (per-user)
        if (isset($_POST['openai_api_key_user'])) {
            $openai_api_key = sanitize_text_field($_POST['openai_api_key_user']);
            update_user_meta($user_id, 'dm_openai_api_key', $openai_api_key);
            $updated = true;
        }

        // Add a success notice if something was updated
        // Note: This might appear alongside the standard "Settings saved." notice from options.php
        if ($updated) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'User-specific API settings updated.', 'data-machine' ); ?></p>
                </div>
                <?php
            } );
        }
    }

    /**
     * Print the API Settings section information.
     *
     * @since    NEXT_VERSION
     */
    public function print_api_settings_section_info() {
        echo '<p>Enter your OpenAI API key below. This key is required for features utilizing OpenAI models.</p>';
    }
} 