<?php
/**
 * Handles module creation and selection operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Module_Handler {

    /**
     * Static flag to prevent duplicate form processing
     * @var bool
     */
    private static $did_handle_save = false;

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Database Modules class instance.
     * @var Data_Machine_Database_Modules
     */
    private $db_modules;

    /**
     * Initialize the class and set its properties.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        $this->db_modules = $locator->get('database_modules');
    }

    /**
     * Initialize hooks for module handlers.
     */
    public function init_hooks() {
        // Add hooks for module form submissions
        add_action('admin_post_dm_module_selection_update', array($this, 'handle_module_selection_update'));
        add_action('admin_post_dm_create_module', array($this, 'handle_new_module_creation'));
        
        // Add hook for handling module settings on the main settings page - REMOVED (Handled by AJAX now)
        // add_action('admin_init', array($this, 'handle_module_selection_save'), 20);
    }

    /**
     * Handle module selection update.
     *
     * @since    0.2.0
     */
    public function handle_module_selection_update() {
        if (!isset($_POST['dm_module_selection_nonce']) || !wp_verify_nonce($_POST['dm_module_selection_nonce'], 'dm_module_selection_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        $module_id = isset($_POST['current_module']) ? absint($_POST['current_module']) : 0;

        // Verify the user owns this module
        $module = $this->db_modules->get_module($module_id, $user_id);
        if (!$module) {
            wp_die('Invalid module selected');
        }

        update_user_meta($user_id, 'Data_Machine_current_module', $module_id);

        wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_updated=1'));
        exit;
    }

    /**
     * Handle new module creation.
     *
     * @since    0.2.0
     */
    public function handle_new_module_creation() {
        if (!isset($_POST['dm_create_module_nonce']) || !wp_verify_nonce($_POST['dm_create_module_nonce'], 'dm_create_module_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        $module_name = isset($_POST['new_module_name']) ? sanitize_text_field($_POST['new_module_name']) : '';
        $api_key = isset($_POST['new_module_api_key']) ? sanitize_text_field($_POST['new_module_api_key']) : '';

        if (empty($module_name)) {
            wp_die('Module name is required');
        }

        $module_data = array(
            'module_name' => $module_name,
            'openai_api_key' => $api_key,
            'process_data_prompt' => 'Review the provided file and provide an output.',
            'fact_check_prompt' => 'Please fact-check the following response and provide corrections and opportunities for enhancement.',
            'finalize_response_prompt' => 'Please finalize the response based on the fact check and the initial request.'
        );

        $module_id = $this->db_modules->create_module($user_id, $module_data);

        if ($module_id) {
            // Set the new module as current
            update_user_meta($user_id, 'Data_Machine_current_module', $module_id);
            wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_created=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_error=1'));
        }
        exit;
    }
    
    // Removed handle_module_selection_save method as saving is now handled via AJAX
    // in Data_Machine_Admin_Ajax::save_module_settings
} // End class Data_Machine_Module_Handler