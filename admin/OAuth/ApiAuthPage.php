<?php
/**
 * Handles API Auth and user meta saving for the API Keys page.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

use DataMachine\Helpers\{EncryptionHelper, Logger};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ApiAuthPage {
    /**
     * Logger instance.
     * @var ?Logger
     */
    private $logger;

    /**
     * Constructor.
     * @param object $admin_notices Admin notices object.
     */
    public function __construct( ?Logger $logger = null ) {
        $this->logger = $logger;
        add_action( 'admin_init', array( $this, 'handle_api_keys_page_user_meta_save' ) );
        // Legacy OpenAI form processing removed - now managed by AI HTTP Client library

        add_action('admin_post_dm_save_bluesky_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) {
                wp_redirect(add_query_arg('error', 'permission', admin_url('admin.php?page=dm-api-keys')));
                exit;
            }
            check_admin_referer('dm_save_bluesky_user_meta_action');
            update_option('bluesky_username', isset($_POST['bluesky_username']) ? sanitize_text_field(wp_unslash($_POST['bluesky_username'])) : '');
            if (isset($_POST['bluesky_app_password']) && $_POST['bluesky_app_password'] !== '') {
                $encrypted_password = EncryptionHelper::encrypt(wp_unslash($_POST['bluesky_app_password']));
                update_option('bluesky_app_password', $encrypted_password);
            }
            wp_redirect(add_query_arg('bluesky_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });



        add_action('admin_post_dm_save_twitter_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) {
                wp_redirect(add_query_arg('error', 'permission', admin_url('admin.php?page=dm-api-keys')));
                exit;
            }
            check_admin_referer('dm_save_twitter_user_meta_action');
            update_option('twitter_api_key', isset($_POST['twitter_api_key']) ? sanitize_text_field(wp_unslash($_POST['twitter_api_key'])) : '');
            update_option('twitter_api_secret', isset($_POST['twitter_api_secret']) ? sanitize_text_field(wp_unslash($_POST['twitter_api_secret'])) : '');
            wp_redirect(add_query_arg('twitter_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_reddit_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) {
                wp_redirect(add_query_arg('error', 'permission', admin_url('admin.php?page=dm-api-keys')));
                exit;
            }
            check_admin_referer('dm_save_reddit_user_meta_action');
            update_option('reddit_oauth_client_id', isset($_POST['reddit_oauth_client_id']) ? sanitize_text_field(wp_unslash($_POST['reddit_oauth_client_id'])) : '');
            update_option('reddit_oauth_client_secret', isset($_POST['reddit_oauth_client_secret']) ? sanitize_text_field(wp_unslash($_POST['reddit_oauth_client_secret'])) : '');
            update_option('reddit_developer_username', isset($_POST['reddit_developer_username']) ? sanitize_text_field(wp_unslash($_POST['reddit_developer_username'])) : '');
            wp_redirect(add_query_arg('reddit_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_threads_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) {
                wp_redirect(add_query_arg('error', 'permission', admin_url('admin.php?page=dm-api-keys')));
                exit;
            }
            check_admin_referer('dm_save_threads_user_meta_action');
            update_option('threads_app_id', isset($_POST['threads_app_id']) ? sanitize_text_field(wp_unslash($_POST['threads_app_id'])) : '');
            update_option('threads_app_secret', isset($_POST['threads_app_secret']) ? sanitize_text_field(wp_unslash($_POST['threads_app_secret'])) : '');
            wp_redirect(add_query_arg('threads_saved', 1, admin_url('admin.php?page=dm-api-keys')));
            exit;
        });

        add_action('admin_post_dm_save_facebook_user_meta', function() {
            // Debug logging removed for production
            if (!current_user_can('manage_options')) {
                wp_redirect(add_query_arg('error', 'permission', admin_url('admin.php?page=dm-api-keys')));
                exit;
            }
            check_admin_referer('dm_save_facebook_user_meta_action');
            update_option('facebook_app_id', isset($_POST['facebook_app_id']) ? sanitize_text_field(wp_unslash($_POST['facebook_app_id'])) : '');
            update_option('facebook_app_secret', isset($_POST['facebook_app_secret']) ? sanitize_text_field(wp_unslash($_POST['facebook_app_secret'])) : '');
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
        if ( 'POST' !== (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') ||
             !isset($_POST['option_page']) || 'dm_api_keys_group' !== $_POST['option_page'] ) {
            return; // Not our form submission
        }

        // Verify the nonce specific to user meta fields on this page
        if ( !isset($_POST['_wpnonce_dm_api_keys_user_meta']) || 
             !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_dm_api_keys_user_meta'])), 'dm_save_api_keys_user_meta') ) {
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
            $bluesky_username = sanitize_text_field(wp_unslash($_POST['bluesky_username']));
            update_option('bluesky_username', $bluesky_username);
            $updated = true;
        }

        // Handle Bluesky Password (always update on save)
        if ( isset($_POST['bluesky_app_password']) ) {
            try {
                $encrypted_password = EncryptionHelper::encrypt(wp_unslash($_POST['bluesky_app_password']));
                if ($encrypted_password === false) {
                    // Error logging removed for production
                    $this->logger?->error('Failed to encrypt Bluesky app password. It was not saved.');
                } else {
                    update_option('bluesky_app_password', $encrypted_password);
                    $updated = true;
                }
            } catch (\Exception $e) {
                // Error logging removed for production
                $this->logger?->error('An error occurred while encrypting the Bluesky app password. It was not saved.');
            }
        }

        // Legacy OpenAI API Key processing removed - now managed by AI HTTP Client library

        // Add a success notice if something was updated
        // Note: This might appear alongside the standard "Settings saved." notice from options.php
        if ($updated) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'API settings updated.', 'data-machine' ); ?></p>
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