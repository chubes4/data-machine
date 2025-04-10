<?php
/**
 * Handles admin form submissions related to remote locations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      0.16.0 // Or current version
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Remote_Locations {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Initialize hooks and dependencies.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        
        // Add hooks for form handlers
        add_action('admin_post_dm_add_location', array($this, 'handle_add_location'));
        add_action('admin_post_dm_update_location', array($this, 'handle_update_location'));
    }
    
    /**
     * Displays the Remote Locations admin page.
     * 
     * @since NEXT_VERSION
     */
    public function display_page() {
        $action = $_GET['action'] ?? 'list';
        $template_to_load = '';
        $template_data = [];
        $page_title = get_admin_page_title(); // Use the registered page title

        if ($action === 'add' || $action === 'edit') {
            $location_id = isset($_GET['location_id']) ? absint($_GET['location_id']) : 0;
            $is_editing = $location_id > 0;
            $location = null;

            if ($is_editing) {
                // Verify nonce for editing
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'edit_location_' . $location_id)) {
                    // Add an admin notice instead of wp_die
                    $this->locator->get('logger')->add_admin_error(
                        __('Nonce verification failed! Cannot edit location.', 'data-machine')
                    );
                    // Set to load the list view instead of the broken form
                    $action = 'list'; 
                } else {
                    // Fetch existing location data
                    $db_locations = $this->locator->get('database_remote_locations');
                    $location = $db_locations->get_location($location_id, get_current_user_id(), false); // Don't decrypt password

                    if (!$location) {
                        // Add an admin notice
                        $this->locator->get('logger')->add_admin_error(
                            sprintf(__('Location %d not found or permission denied.', 'data-machine'), $location_id)
                        );
                        $action = 'list'; // Revert to list view
                    }
                }
            }

            // Only set form template if we are still adding or successfully verified editing
            if ($action === 'add' || ($action === 'edit' && $location)) { 
                $template_to_load = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/remote-locations-form.php';
                $template_data = [
                    'is_editing' => $is_editing,
                    'location_id' => $location_id,
                    'location' => $location,
                ];
                // Update page title for Add/Edit screens
                $page_title = $is_editing ? __('Edit Location', 'data-machine') : __('Add New Location', 'data-machine');
            }
        }

        // Default to list table if not adding/editing or if an error occurred during edit setup
        if (empty($template_to_load) || $action === 'list') { 
            // Ensure the list table class is loaded
            if (!class_exists('Remote_Locations_List_Table')) {
                require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-remote-locations-list-table.php';
            }
            // Instantiate, prepare the list table
            $list_table = new Remote_Locations_List_Table($this->locator);
            $list_table->prepare_items();

            $template_to_load = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/remote-locations-list-table.php';
            $template_data = ['list_table' => $list_table];
            // Page title is already set to the default page title
        }

        // Now load the main wrapper template, passing it the specific template and data
        $main_template = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/remote-locations-page.php';
        if (file_exists($main_template)) {
            // Pass variables to the main template
            include $main_template; 
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Main locations template missing.', 'data-machine') . '</p></div>';
        }
    }

    /**
     * Handles the admin-post action for adding a new location.
     */
    public function handle_add_location() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dm_add_location')) {
            wp_die(__('Nonce verification failed!', 'data-machine'));
        }
    
        if (!current_user_can('manage_options')) { // Check capability
            wp_die(__('Permission denied!', 'data-machine'));
        }
    
        $data = array(
            'location_name'   => sanitize_text_field($_POST['location_name'] ?? ''),
            'target_site_url' => esc_url_raw($_POST['target_site_url'] ?? ''),
            'target_username' => sanitize_text_field($_POST['target_username'] ?? ''),
            'password'        => $_POST['password'] ?? '' // Keep raw for encryption
        );
    
        if (empty($data['location_name']) || empty($data['target_site_url']) || empty($data['target_username']) || !isset($data['password']) || $data['password'] === '') {
            // Use logger for admin notice
            $this->locator->get('logger')->add_admin_error(__('Error: All fields are required.', 'data-machine'));
        } else {
            $db_locations = $this->locator->get('database_remote_locations');
            $result = $db_locations->add_location(get_current_user_id(), $data);
    
            if ($result) {
                // Use logger for admin notice
                $this->locator->get('logger')->add_admin_success(__('Remote location added successfully.', 'data-machine'));
            } else {
                // Use logger for admin notice
                $this->locator->get('logger')->add_admin_error(__('Error: Could not add remote location.', 'data-machine'));
            }
        }
    
        wp_redirect(admin_url('admin.php?page=adc-remote-locations'));
        exit;
    }
   
    /**
     * Handles the admin-post action for updating an existing location.
     */
    public function handle_update_location() {
        $location_id = isset($_POST['location_id']) ? absint($_POST['location_id']) : 0;
    
        if (!$location_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dm_update_location_' . $location_id)) {
            wp_die(__('Nonce verification failed or invalid location ID!', 'data-machine'));
        }
    
        if (!current_user_can('manage_options')) { // Check capability
            wp_die(__('Permission denied!', 'data-machine'));
        }
    
        $data = array();
        if (!empty($_POST['location_name'])) $data['location_name'] = sanitize_text_field($_POST['location_name']);
        if (!empty($_POST['target_site_url'])) $data['target_site_url'] = esc_url_raw($_POST['target_site_url']);
        if (!empty($_POST['target_username'])) $data['target_username'] = sanitize_text_field($_POST['target_username']);
        if (isset($_POST['password']) && $_POST['password'] !== '') {
            $data['password'] = $_POST['password'];
        }
    
        if (empty($data['location_name']) || empty($data['target_site_url']) || empty($data['target_username'])) {
             // Use logger for admin notice
             $this->locator->get('logger')->add_admin_error(__('Error: Name, URL, and Username are required.', 'data-machine'));
        } else {
            $db_locations = $this->locator->get('database_remote_locations');
            $result = $db_locations->update_location($location_id, get_current_user_id(), $data);
    
            if ($result) {
                // Use logger for admin notice
                $this->locator->get('logger')->add_admin_success(__('Remote location updated successfully.', 'data-machine'));
            } else {
                // Use logger for admin notice
                // Provide slightly more context in the log message
                $log_message = 'Failed to update remote location.';
                $admin_message = __('Error: Could not update remote location (or no changes made).', 'data-machine');
                if (!$result && $db_locations->get_last_error()) { // Assuming DB class might store specific error
                    $log_message .= ' DB Error: ' . $db_locations->get_last_error();
                }
                $this->locator->get('logger')->add_admin_error($admin_message, ['location_id' => $location_id, 'raw_db_error' => $log_message]);
            }
        }
    
        wp_redirect(admin_url('admin.php?page=adc-remote-locations'));
        exit;
    }
} 