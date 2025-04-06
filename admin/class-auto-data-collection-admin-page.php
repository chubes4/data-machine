<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/admin
 */

/**
 * The admin-specific functionality of the plugin.
 */
class Auto_Data_Collection_Admin_Page {

    /**
     * The plugin version.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current plugin version.
     */
    private $version;

    /**
     * Database Modules class instance.
     *
     * @since    0.2.0
     * @access   private
     * @var      Auto_Data_Collection_Database_Modules    $db_modules    Database Modules class instance.
     */
    private $db_modules;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string    $version         The plugin version.
     */
    public function __construct( $version ) {
        $this->version = $version;
        $this->db_modules = new Auto_Data_Collection_Database_Modules();
    }

    /**
     * Display the main admin page content.
     */
    public function display_admin_page() {
        ?>
        <?php
        include_once plugin_dir_path( __FILE__ ) . 'auto-data-collection-admin-page.php';
    }

    /**
     * Display the settings page content by including the template file.
     */
    public function display_settings_page() {
        include_once plugin_dir_path( __FILE__ ) . 'auto-data-collection-settings-page.php';
    }

    /**
     * Add admin menu for the plugin.
     *
     * @since    0.1.0
     */
    public function add_admin_menu() {
        add_menu_page(
            'Data Machine', // Page title
            'Data Machine', // Menu title
            'manage_options', // Capability
            'auto-data-collection-admin-page', // Menu slug
            array( $this, 'display_admin_page' ), // Callback function for main page
            'dashicons-database-import', // Icon slug
            6 // Position
        );
        add_submenu_page(
            'auto-data-collection-admin-page', // Parent slug
            'Settings', // Page title
            'Settings', // Menu title
            'manage_options', // Capability
            'auto-data-collection-settings-page', // Menu slug
            array( $this, 'display_settings_page' ) // Callback function for settings page
        );
    }

    /**
     * Enqueue admin assets (CSS and JS).
     *
     * @since    0.1.0
     * @param    string    $hook_suffix    The current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( 'toplevel_page_auto-data-collection-admin-page' === $hook_suffix || 'data-machine_page_auto-data-collection-settings-page' === $hook_suffix ) {
            wp_enqueue_style( 'auto-data-collection-admin', plugin_dir_url( __FILE__ ) . '../assets/css/auto-data-collection-admin.css', array(), $this->version, 'all' );
            
            if ('toplevel_page_auto-data-collection-admin-page' === $hook_suffix) {
                wp_enqueue_script( 'auto-data-collection-admin', plugin_dir_url( __FILE__ ) . '../assets/js/auto-data-collection-admin.js', array( 'jquery' ), $this->version, false );
                wp_localize_script( 'auto-data-collection-admin', 'adc_ajax_params', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'file_processing_nonce' => wp_create_nonce( 'file_processing_nonce' ),
                    'fact_check_nonce' => wp_create_nonce( 'fact_check_nonce' ),
                    'finalize_json_nonce' => wp_create_nonce( 'finalize_json_nonce' ),
                ) );
            }
        }
    }

    /**
     * Display admin notices for errors with details.
     *
     * @since    0.1.0
     */
    public function display_admin_notices() {
        $errors = get_transient( 'auto_data_collection_errors' );
        if ( is_array( $errors ) && ! empty( $errors ) ) {
            ?>
            <div class="notice notice-error">
                <p><strong>Data Machine Errors:</strong></p>
                <ul class="error-list">
                    <?php foreach ( $errors as $error ) : ?>
                        <?php if ( is_array( $error ) && isset( $error['time'] ) ) : ?>
                        <li>
                            <?php echo esc_html( $error['message'] ); ?>
                            <?php if ( ! empty( $error['details'] ) ) : ?>
                                <ul class="error-details">
                                    <?php foreach ( $error['details'] as $key => $value ) : ?>
                                        <li><strong><?php echo esc_html( ucfirst( $key ) ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <p><small>Timestamp: <?php
                            if ( !is_string( $error['time'] ) ) {
                                echo date( 'Y-m-d H:i:s', $error['time'] );
                            } else {
                                echo 'Invalid Timestamp';
                            }
                            ?></small></p>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            delete_transient( 'auto_data_collection_errors' ); // Clear errors after displaying
        }
    }

    /**
     * Handle module selection update.
     *
     * @since    0.2.0
     */
    public function handle_module_selection_update() {
        if (!isset($_POST['adc_module_selection_nonce']) || !wp_verify_nonce($_POST['adc_module_selection_nonce'], 'adc_module_selection_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        $module_id = isset($_POST['current_module']) ? absint($_POST['current_module']) : 0;

        // Verify the user owns this module
        $module = $this->db_modules->get_module($module_id, $user_id);
        if (!$module) {
            wp_die('Invalid module selected');
        }

        update_user_meta($user_id, 'auto_data_collection_current_module', $module_id);

        wp_redirect(admin_url('admin.php?page=auto-data-collection-settings-page&module_updated=1'));
        exit;
    }

    /**
     * Handle new module creation.
     *
     * @since    0.2.0
     */
    public function handle_new_module_creation() {
        if (!isset($_POST['adc_create_module_nonce']) || !wp_verify_nonce($_POST['adc_create_module_nonce'], 'adc_create_module_nonce')) {
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
            'process_data_prompt' => 'The Frankenstein Prompt',
            'fact_check_prompt' => 'Please fact-check the following data:',
            'finalize_json_prompt' => 'Please finalize the JSON output:'
        );

        $module_id = $this->db_modules->create_module($user_id, $module_data);

        if ($module_id) {
            // Set the new module as current
            update_user_meta($user_id, 'auto_data_collection_current_module', $module_id);
            wp_redirect(admin_url('admin.php?page=auto-data-collection-settings-page&module_created=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=auto-data-collection-settings-page&module_error=1'));
        }
        exit;
    }
}
