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

    /** @var Data_Machine_Database_Remote_Locations */
    private $db_locations;

    /** @var ?Data_Machine_Logger */
    private $logger;

    // Note: We don't store the list table instance, it's created on demand in display_page.

    /**
     * Initialize hooks and dependencies.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations DB Handler for remote locations.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Remote_Locations $db_locations,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_locations = $db_locations;
        $this->logger = $logger;

        // Add hooks for form handlers
        add_action('admin_post_dm_add_location', array($this, 'handle_add_location'));
        add_action('admin_post_dm_update_location', array($this, 'handle_update_location'));
        add_action('admin_post_dm_instagram_accounts', array($this, 'dm_handle_instagram_accounts'));
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
                    // Add an admin notice using injected logger
                    $this->logger?->add_admin_error(
                        __('Nonce verification failed! Cannot edit location.', 'data-machine')
                    );
                    $action = 'list'; 
                } else {
                    // Fetch existing location data using injected db_locations
                    $location = $this->db_locations->get_location($location_id, get_current_user_id(), false); // Don't decrypt password

                    if (!$location) {
                        // Add an admin notice using injected logger
                        $this->logger?->add_admin_error(
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
                $page_title = $is_editing ? __('Edit Location', 'data-machine') : __('Add New Location', 'data-machine');
            }
        }

        // Default to list table if not adding/editing or if an error occurred during edit setup
        if (empty($template_to_load) || $action === 'list') { 
            // Ensure the list table class is loaded
            if (!class_exists('Remote_Locations_List_Table')) {
                require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-remote-locations-list-table.php';
            }
            // Instantiate list table, passing the required DB dependency
            $list_table = new Remote_Locations_List_Table($this->db_locations);
            $list_table->prepare_items();

            $template_to_load = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/remote-locations-list-table.php';
            $template_data = ['list_table' => $list_table];
            // Page title is already set to the default page title
        }

        // Now load the main wrapper template, passing it the specific template and data
        $main_template = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/templates/remote-locations-page.php';
        if (file_exists($main_template)) {
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
            // Use injected logger for admin notice
            $this->logger?->add_admin_error(__('Error: All fields are required.', 'data-machine'));
            // Redirect back to the add form if initial validation failed
            wp_redirect(admin_url('admin.php?page=dm-remote-locations&action=add&message=validation_failed'));
            exit;
        }
        
        // Use injected db_locations
        $result = $this->db_locations->add_location(get_current_user_id(), $data);

        if ($result) { // $result contains the new location_id
            // Add success message to be displayed on the edit page
            $redirect_url = add_query_arg(
                array(
                    'page' => 'dm-remote-locations',
                    'action' => 'edit',
                    'location_id' => $result,
                    'message' => 'added' // Add a success indicator
                ),
                admin_url('admin.php')
            );
            $this->logger?->add_admin_success(__('Remote location added successfully.', 'data-machine'));
            wp_redirect($redirect_url);
            exit;
        } else {
            // Use logger for admin notice
            $this->logger?->add_admin_error(__('Error: Could not add remote location.', 'data-machine'));
            // Redirect back to the add form on failure
            wp_redirect(admin_url('admin.php?page=dm-remote-locations&action=add&message=add_failed'));
            exit;
        }
        // No fallback redirect needed here as all paths above include exit;
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
            $data['password'] = $_POST['password']; // Keep raw for encryption in DB class
        }

        // Handle enabled post types and taxonomies
        $enabled_post_types = isset($_POST['enabled_post_types']) && is_array($_POST['enabled_post_types'])
            ? array_map('sanitize_key', $_POST['enabled_post_types'])
            : [];
        $data['enabled_post_types'] = wp_json_encode($enabled_post_types);

        $enabled_taxonomies = isset($_POST['enabled_taxonomies']) && is_array($_POST['enabled_taxonomies'])
            ? array_map('sanitize_key', $_POST['enabled_taxonomies'])
            : [];
        $data['enabled_taxonomies'] = wp_json_encode($enabled_taxonomies);
    
        // Basic validation for core fields (password is optional on update)
        if (empty($data['location_name']) || empty($data['target_site_url']) || empty($data['target_username'])) {
             // Use logger for admin notice
             $this->logger?->add_admin_error(__('Error: Name, URL, and Username are required.', 'data-machine'));
        } else { // Validation passed
            // Use injected db_locations
            $result = $this->db_locations->update_location($location_id, get_current_user_id(), $data);
    
            if ($result) {
                // Change redirect to the list page
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'dm-remote-locations',
                        // Remove 'action' => 'edit' and 'location_id' to go to list view
                        'message' => 'updated' // Keep success indicator
                    ),
                    admin_url('admin.php')
                );
                $this->logger?->add_admin_success(__('Remote location updated successfully.', 'data-machine'));
                wp_redirect($redirect_url);
                exit;
            } else {
                // Use logger for admin notice
                $log_message = 'Failed to update remote location.';
                $admin_message = __('Error: Could not update remote location (or no changes made).', 'data-machine');
                // Check if the DB class has a method to get the last error (assuming it might)
                if (method_exists($this->db_locations, 'get_last_error') && $this->db_locations->get_last_error()) {
                    $log_message .= ' DB Error: ' . $this->db_locations->get_last_error();
                }
                $this->logger?->add_admin_error($admin_message, ['location_id' => $location_id, 'raw_db_error' => $log_message]);
                // Redirect back to the edit form on failure
                 wp_redirect(admin_url('admin.php?page=dm-remote-locations&action=edit&location_id=' . $location_id . '&message=update_failed'));
                 exit;
            }
        }
        // No fallback redirect needed here as all paths above include exit;
    }

    /**
     * Handles the admin-post action for updating Instagram accounts.
     */
    public function dm_handle_instagram_accounts() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dm_instagram_accounts')) {
            wp_die(__('Nonce verification failed!', 'data-machine'));
        }
    
        if (!current_user_can('manage_options')) { // Check capability
            wp_die(__('Permission denied!', 'data-machine'));
        }
    
        $user_id = get_current_user_id();
        $accounts = array_map('sanitize_text_field', $_POST['accounts'] ?? []);
        $account_id = isset($_POST['account_id']) ? absint($_POST['account_id']) : null;

        $accounts = array_filter($accounts, function($acct) use ($account_id) {
            return ($acct['id'] ?? null) !== $account_id; // Check key exists before comparison
        });
        update_user_meta($user_id, 'data_machine_instagram_accounts', $accounts);

        wp_send_json_success();
    }

} 