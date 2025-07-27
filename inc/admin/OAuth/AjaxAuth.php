<?php
/**
 * Handles AJAX requests related to authentication flows (OAuth, etc.).
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\OAuth;

use DataMachine\Admin\OAuth\{Twitter, Facebook, Threads};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class AjaxAuth {

    /**
     * Constructor.
     */
    public function __construct() {
        // No locator needed
        $this->register_hooks();
    }

    /**
     * Register AJAX hooks.
     */
    private function register_hooks() {
        // Action for generating nonces dynamically (used by api-keys-page.php JS)
        add_action('wp_ajax_dm_generate_nonce', array($this, 'handle_generate_nonce'));

        // Action for removing Twitter account
        add_action('wp_ajax_dm_remove_twitter_account', array($this, 'handle_remove_twitter_account'));

        // Actions for initiating OAuth flows
        add_action('wp_ajax_dm_initiate_threads_oauth', array($this, 'handle_initiate_threads_oauth'));
        add_action('wp_ajax_dm_initiate_facebook_oauth', array($this, 'handle_initiate_facebook_oauth'));

        // Actions for removing accounts
        add_action('wp_ajax_dm_remove_threads_account', array($this, 'handle_remove_threads_account'));
        add_action('wp_ajax_dm_remove_facebook_account', array($this, 'handle_remove_facebook_account'));
    }

    /**
     * Handles AJAX request to generate a nonce.
     * Used by JS to get fresh nonces for admin-post actions.
     */
    public function handle_generate_nonce() {
        // Verify nonce for generating new nonces (meta-nonce security)
        check_ajax_referer( 'dm_generate_nonce_action', 'security' );
        
        // Check user capability
        if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        $id = isset($_POST['id']) ? sanitize_key($_POST['id']) : '';
        if (empty($id)) {
            wp_send_json_error(['message' => __('Nonce ID missing.', 'data-machine')]);
        }

        $nonce = wp_create_nonce($id);
        wp_send_json_success(['nonce' => $nonce]);
    }

    /**
     * Handles AJAX request to remove the authenticated Twitter account.
     */
    public function handle_remove_twitter_account() {
        $user_id = get_current_user_id();
        $nonce_action = 'dm_remove_twitter_account_' . ($this->get_stored_twitter_user_id($user_id) ?? 'unknown');

        // Check nonce - Use check_ajax_referer for AJAX actions
        // The nonce is expected in the '_ajax_nonce' field by default
        check_ajax_referer($nonce_action, '_ajax_nonce');

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        // Twitter class auto-loaded via PSR-4
        // Call the remove method
        $removed = Twitter::remove_account($user_id);

        if ($removed) {
            wp_send_json_success(['message' => __('Twitter account connection removed successfully.', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove Twitter account connection. It might have already been removed.', 'data-machine')]);
        }
    }

    /**
     * Helper to get the stored Twitter user ID for nonce generation.
     *
     * @param int $user_id WP User ID.
     * @return string|null Twitter User ID or null.
     */
    private function get_stored_twitter_user_id(int $user_id): ?string {
        // Twitter class auto-loaded via PSR-4
        $account = Twitter::get_account_details($user_id);
        return $account['user_id'] ?? null;
    }

    /**
     * Handles AJAX request to initiate Threads OAuth flow.
     */
    public function handle_initiate_threads_oauth() {
        // Check nonce & capability
        check_ajax_referer('dm_initiate_threads_oauth_action', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        $user_id = get_current_user_id();
        $app_id = get_option('threads_app_id');
        $app_secret = get_option('threads_app_secret');

        if (empty($app_id) || empty($app_secret)) {
            wp_send_json_error(['message' => __('Threads App ID and Secret must be configured first.', 'data-machine')]);
        }

        // Threads class auto-loaded via PSR-4
        $oauth_handler = new Threads($app_id, $app_secret);
        $auth_url = $oauth_handler->get_authorization_url($user_id);

        wp_send_json_success(['authorization_url' => $auth_url]);
    }

    /**
     * Handles AJAX request to initiate Facebook OAuth flow.
     */
    public function handle_initiate_facebook_oauth() {
        // Check nonce & capability
        check_ajax_referer('dm_initiate_facebook_oauth_action', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        $user_id = get_current_user_id();
        $app_id = get_option('facebook_app_id');
        $app_secret = get_option('facebook_app_secret');

        if (empty($app_id) || empty($app_secret)) {
            wp_send_json_error(['message' => __('Facebook App ID and Secret must be configured first.', 'data-machine')]);
        }

        // Facebook class auto-loaded via PSR-4
        $oauth_handler = new Facebook($app_id, $app_secret);
        $auth_url = $oauth_handler->get_authorization_url($user_id);

        wp_send_json_success(['authorization_url' => $auth_url]);
    }

    /**
     * Handles AJAX request to remove the authenticated Threads account.
     */
    public function handle_remove_threads_account() {
        $user_id = get_current_user_id();
        // Nonce action needs identifier - using user_id for simplicity, adjust if needed
        $account_id = isset($_POST['account_id']) ? sanitize_text_field($_POST['account_id']) : $user_id;
        $nonce_action = 'dm_remove_threads_account_' . $account_id;

        check_ajax_referer($nonce_action, '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        // Threads class auto-loaded via PSR-4
        $removed = Threads::remove_account($user_id);

        if ($removed) {
            wp_send_json_success(['message' => __('Threads account connection removed successfully.', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove Threads account connection.', 'data-machine')]);
        }
    }

    /**
     * Handles AJAX request to remove the authenticated Facebook account.
     */
    public function handle_remove_facebook_account() {
        $user_id = get_current_user_id();

        // First, check capability before doing anything else
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }

        // Fetch the stored account details to get the correct ID for the nonce
        $account = get_user_meta($user_id, 'data_machine_facebook_auth_account', true);
        // Use the Facebook User ID stored in the meta for the nonce check
        $facebook_user_id = $account['user_id'] ?? 'unknown'; 

        // Nonce action uses the stored Facebook User ID
        $nonce_action = 'dm_remove_facebook_account_' . $facebook_user_id;
        check_ajax_referer($nonce_action, '_ajax_nonce');

        // Facebook class auto-loaded via PSR-4
        // Proceed with removal 
        $removed = Facebook::remove_account($user_id);

        if ($removed) {
            wp_send_json_success(['message' => __('Facebook account connection removed successfully.', 'data-machine')]);
        } else {
            // Check if the account was already gone
            if (empty($account)) {
                 wp_send_json_success(['message' => __('Facebook account connection was already removed.', 'data-machine')]);
            } else {
                 wp_send_json_error(['message' => __('Failed to remove Facebook account connection. Deauthorization with Facebook may have failed, but local data should be gone.', 'data-machine')]);
            }
        }
    }

}