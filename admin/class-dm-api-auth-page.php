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

        // Handle Bluesky Password (only update if a new password was submitted)
        if ( isset($_POST['bluesky_app_password']) && !empty($_POST['bluesky_app_password']) ) {
            try {
                $encrypted_password = Data_Machine_Encryption_Helper::encrypt($_POST['bluesky_app_password']);
                if ($encrypted_password === false) {
                    error_log('[Data Machine] Failed to encrypt Bluesky app password for user ' . $user_id . ' on API Keys page save.');
                    if ($this->admin_notices) $this->admin_notices->error(__('Failed to encrypt Bluesky app password. It was not saved.', 'data-machine'));
                } else {
                    update_user_meta($user_id, 'dm_bluesky_app_password', $encrypted_password);
                    $updated = true;
                }
            } catch (\Exception $e) {
                error_log('[Data Machine] Exception encrypting Bluesky app password for user ' . $user_id . ': ' . $e->getMessage());
                if ($this->admin_notices) $this->admin_notices->error(__('An error occurred while encrypting the Bluesky app password. It was not saved.', 'data-machine'));
            }
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
     * Sanitize the OpenAI API Key input.
     *
     * @since    NEXT_VERSION
     * @param    string    $input    The unsanitized input.
     * @return   string              The sanitized input.
     */
    public function sanitize_openai_api_key( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Print the API Settings section information.
     *
     * @since    NEXT_VERSION
     */
    public function print_api_settings_section_info() {
        echo '<p>Enter your OpenAI API key below. This key is required for features utilizing OpenAI models.</p>';
    }

    /**
     * OpenAI API Key field callback.
     *
     * @since    NEXT_VERSION
     */
    public function openai_api_key_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="openai_api_key" value="%s" class="regular-text" />',
            esc_attr( get_option( 'openai_api_key' ) )
        );
    }
} 