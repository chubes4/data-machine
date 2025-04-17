<?php
/**
 * Handles AJAX requests related to authentication flows (OAuth, etc.).
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Data_Machine_Ajax_Auth {

    /**
     * Service Locator instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Data_Machine_Service_Locator    $locator    Service Locator instance.
     */
    private $locator;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
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
        
        // TODO: Add actions for removing Instagram/Reddit accounts here for consistency?
    }

    /**
     * Handles AJAX request to generate a nonce.
     * Used by JS to get fresh nonces for admin-post actions.
     */
    public function handle_generate_nonce() {
        $id = isset($_POST['id']) ? sanitize_key($_POST['id']) : '';
        if (empty($id)) {
            wp_send_json_error(['message' => 'Nonce ID missing.']);
        }
        // Check if user has permission to perform actions associated with these nonces
        if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => 'Permission denied.']);
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

        // Ensure the Twitter OAuth class exists
        if (!class_exists('Data_Machine_OAuth_Twitter')) {
            require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-twitter.php';
        }

        // Call the remove method
        $removed = Data_Machine_OAuth_Twitter::remove_account($user_id);

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
         if (!class_exists('Data_Machine_OAuth_Twitter')) {
            require_once DATA_MACHINE_PATH . 'admin/oauth/class-data-machine-oauth-twitter.php';
        }
        $account = Data_Machine_OAuth_Twitter::get_account_details($user_id);
        return $account['user_id'] ?? null;
    }

    // TODO: Add similar remove handlers for Instagram/Reddit if centralizing.

} 