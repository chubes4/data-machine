<?php
/**
 * Handles admin form submissions related to remote locations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      0.16.0 // Or current version
 */

namespace DataMachine\Admin\RemoteLocations;

use DataMachine\Database\RemoteLocations as DatabaseRemoteLocations;
use DataMachine\Helpers\Logger;
use DataMachine\Admin\RemoteLocations\ListTable;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class FormHandler {

    /** @var DatabaseRemoteLocations */
    private $db_locations;

    /** @var ?Logger */
    private $logger;

    /** @var ?SyncRemoteLocations */
    private $sync_service;

    /**
     * Initialize hooks and dependencies.
     *
     * @param DatabaseRemoteLocations $db_locations DB Handler for remote locations.
     * @param Logger|null $logger Logger service (optional).
     * @param SyncRemoteLocations|null $sync_service Sync service for remote locations.
     */
    public function __construct(
        DatabaseRemoteLocations $db_locations,
        ?Logger $logger = null,
        ?SyncRemoteLocations $sync_service = null
    ) {
        $this->db_locations = $db_locations;
        $this->logger = $logger;
        $this->sync_service = $sync_service;

        // Add hooks for form handlers
        add_action('admin_post_dm_add_location', array($this, 'handle_add_location'));
        add_action('admin_post_dm_update_location', array($this, 'handle_update_location'));
        add_action('admin_post_dm_delete_location', array($this, 'handle_delete_location'));
        add_action('admin_post_dm_sync_location', array($this, 'handle_sync_location'));
    }

    /**
     * Displays the Remote Locations admin page.
     * 
     * @since NEXT_VERSION
     */
    public function display_page() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $template_to_load = '';
        $template_data = [];
        $page_title = get_admin_page_title(); // Use the registered page title

        if ($action === 'add' || $action === 'edit') {
            $location_id = isset($_GET['location_id']) ? absint($_GET['location_id']) : 0;
            $is_editing = $location_id > 0;
            $location = null;

            if ($is_editing) {
                // Verify nonce for editing
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'edit_location_' . $location_id)) {
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
                            /* translators: %d: Location ID number */
                            sprintf(__('Location %d not found or permission denied.', 'data-machine'), $location_id)
                        );
                        $action = 'list'; // Revert to list view
                    }
                }
            }

            // Only set form template if we are still adding or successfully verified editing
            if ($action === 'add' || ($action === 'edit' && $location)) { 
                $template_to_load = DATA_MACHINE_PATH . 'admin/page-templates/remote-locations.php';
                $template_data = [
                    'action' => $action,
                    'is_editing' => $is_editing,
                    'location_id' => $location_id,
                    'location' => $location,
                ];
                $page_title = $is_editing ? __('Edit Location', 'data-machine') : __('Add New Location', 'data-machine');
            }
        }

        // Default to list table if not adding/editing or if an error occurred during edit setup
        if (empty($template_to_load) || $action === 'list') { 
            // List table class auto-loaded via PSR-4
            // Instantiate list table, passing the required DB dependency
            $list_table = new ListTable($this->db_locations);
            $list_table->prepare_items();

            $template_to_load = DATA_MACHINE_PATH . 'admin/page-templates/remote-locations.php';
            $template_data = [
                'action' => 'list',
                'list_table' => $list_table
            ];
            // Page title is already set to the default page title
        }

        // Load the consolidated template
        if (file_exists($template_to_load)) {
            // Extract template data into local variables
            extract($template_data);
            include $template_to_load; 
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Remote locations template missing.', 'data-machine') . '</p></div>';
        }
    }

    /**
     * Handles the admin-post action for adding a new location.
     */
    public function handle_add_location() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'dm_add_location')) {
            wp_die(esc_html__('Nonce verification failed!', 'data-machine'));
        }
    
        if (!current_user_can('manage_options')) { // Check capability
            wp_die(esc_html__('Permission denied!', 'data-machine'));
        }
    
        $data = array(
            'location_name'   => isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '',
            'target_site_url' => isset($_POST['target_site_url']) ? esc_url_raw(wp_unslash($_POST['target_site_url'])) : '',
            'target_username' => isset($_POST['target_username']) ? sanitize_text_field(wp_unslash($_POST['target_username'])) : '',
            'password'        => isset($_POST['password']) ? wp_unslash($_POST['password']) : '' // Keep raw for encryption
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
            $user_id = get_current_user_id();
            
            // Attempt to sync the location immediately if sync service is available
            if ($this->sync_service) {
                $sync_result = $this->sync_service->sync_location_data($result, $user_id);
                if ($sync_result['success']) {
                    $this->logger?->add_admin_success(__('Remote location added and synced successfully! Configuration options are now available.', 'data-machine'));
                } else {
                    $this->logger?->add_admin_success(__('Remote location added successfully.', 'data-machine'));
                    $this->logger?->add_admin_error(sprintf(
                        /* translators: %s: error message */
                        __('Sync failed: %s', 'data-machine'), 
                        $sync_result['message']
                    ));
                }
            } else {
                $this->logger?->add_admin_success(__('Remote location added successfully.', 'data-machine'));
            }
            
            // Redirect to edit page for configuration
            $redirect_url = add_query_arg(
                array(
                    'page' => 'dm-remote-locations',
                    'action' => 'edit',
                    'location_id' => $result,
                    '_wpnonce' => wp_create_nonce('edit_location_' . $result),
                    'message' => 'added'
                ),
                admin_url('admin.php')
            );
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
    
        if (!$location_id || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'dm_update_location_' . $location_id)) {
            wp_die(esc_html__('Nonce verification failed or invalid location ID!', 'data-machine'));
        }
    
        if (!current_user_can('manage_options')) { // Check capability
            wp_die(esc_html__('Permission denied!', 'data-machine'));
        }
    
        $data = array();
        if (!empty($_POST['location_name'])) $data['location_name'] = sanitize_text_field(wp_unslash($_POST['location_name']));
        if (!empty($_POST['target_site_url'])) $data['target_site_url'] = esc_url_raw(wp_unslash($_POST['target_site_url']));
        if (!empty($_POST['target_username'])) $data['target_username'] = sanitize_text_field(wp_unslash($_POST['target_username']));
        if (isset($_POST['password']) && $_POST['password'] !== '') {
            $data['password'] = wp_unslash($_POST['password']); // Keep raw for encryption in DB class
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
                // Clean synced_site_info to only include enabled post types/taxonomies, but keep all keys with a disabled placeholder for unselected
                $location = $this->db_locations->get_location($location_id, get_current_user_id(), false);
                if ($location && !empty($location->synced_site_info)) {
                    $site_info = json_decode($location->synced_site_info, true);
                    $enabled_post_types = json_decode($location->enabled_post_types ?? '[]', true);
                    $enabled_taxonomies = json_decode($location->enabled_taxonomies ?? '[]', true);

                    if (is_array($site_info)) {
                        $filtered = [
                            'post_types' => [],
                            'taxonomies' => [],
                        ];
                        // Post types: keep all keys, only keep details for enabled
                        if (!empty($site_info['post_types']) && is_array($site_info['post_types'])) {
                            foreach ($site_info['post_types'] as $index => $info) {
                                // Handle both indexed array (with 'name' field) and associative array formats
                                $post_type_slug = '';
                                if (is_array($info) && isset($info['name'])) {
                                    // Indexed array format: info contains 'name' field
                                    $post_type_slug = $info['name'];
                                    $key = $post_type_slug; // Use the actual slug as key
                                } elseif (is_string($index) && !is_numeric($index)) {
                                    // Associative array format: index is the slug
                                    $post_type_slug = $index;
                                    $key = $index;
                                } else {
                                    // Skip malformed entries
                                    continue;
                                }
                                
                                if (in_array($post_type_slug, $enabled_post_types, true)) {
                                    $filtered['post_types'][$key] = $info;
                                } else {
                                    $filtered['post_types'][$key] = ['disabled' => true];
                                }
                            }
                        }
                        // Taxonomies: keep all keys, only keep details for enabled
                        if (!empty($site_info['taxonomies']) && is_array($site_info['taxonomies'])) {
                            foreach ($site_info['taxonomies'] as $slug => $info) {
                                if (in_array($slug, $enabled_taxonomies, true)) {
                                    $filtered['taxonomies'][$slug] = $info;
                                } else {
                                    // Only keep the label (if present) and the disabled flag
                                    $label = is_array($info) && isset($info['label']) ? $info['label'] : '';
                                    $filtered['taxonomies'][$slug] = [
                                        'label' => $label,
                                        'disabled' => true
                                    ];
                                }
                            }
                        }
                        // Save the filtered site info
                        $this->db_locations->update_synced_info($location_id, get_current_user_id(), wp_json_encode($filtered));
                    }
                }
                // Redirect back to the edit page
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'dm-remote-locations',
                        'action' => 'edit',
                        'location_id' => $location_id,
                        '_wpnonce' => wp_create_nonce('edit_location_' . $location_id),
                        'message' => 'updated'
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
     * Handle delete location form submission.
     */
    public function handle_delete_location() {
        // Verify nonce
        $location_id = absint($_GET['location_id'] ?? 0);
        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'dm_delete_location_' . $location_id)) {
            wp_die(esc_html__('Nonce verification failed.', 'data-machine'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'data-machine'));
        }

        // Delete the location
        $result = $this->db_locations->delete_location($location_id, get_current_user_id());

        if ($result) {
            // Redirect with success message
            wp_redirect(admin_url('admin.php?page=dm-remote-locations&message=deleted'));
        } else {
            // Redirect with error message
            wp_redirect(admin_url('admin.php?page=dm-remote-locations&message=delete_failed'));
        }
        exit;
    }

    /**
     * Handle sync location form submission.
     */
    public function handle_sync_location() {
        // Verify nonce
        $location_id = absint($_GET['location_id'] ?? 0);
        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'dm_sync_location_' . $location_id)) {
            wp_die(esc_html__('Nonce verification failed.', 'data-machine'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'data-machine'));
        }

        // Sync the location using the sync service
        if ($this->sync_service) {
            $sync_result = $this->sync_service->sync_location_data($location_id, get_current_user_id());
            
            if ($sync_result['success']) {
                // Redirect back to edit page with success message and nonce
                $redirect_url = add_query_arg(array(
                    'page' => 'dm-remote-locations',
                    'action' => 'edit',
                    'location_id' => $location_id,
                    '_wpnonce' => wp_create_nonce('edit_location_' . $location_id),
                    'message' => 'synced'
                ), admin_url('admin.php'));
                wp_redirect($redirect_url);
            } else {
                // Redirect back with error message and nonce
                $redirect_url = add_query_arg(array(
                    'page' => 'dm-remote-locations',
                    'action' => 'edit',
                    'location_id' => $location_id,
                    '_wpnonce' => wp_create_nonce('edit_location_' . $location_id),
                    'message' => 'sync_failed'
                ), admin_url('admin.php'));
                wp_redirect($redirect_url);
            }
        } else {
            // Redirect with error if sync service not available
            $redirect_url = add_query_arg(array(
                'page' => 'dm-remote-locations',
                'action' => 'edit',
                'location_id' => $location_id,
                '_wpnonce' => wp_create_nonce('edit_location_' . $location_id),
                'message' => 'sync_unavailable'
            ), admin_url('admin.php'));
            wp_redirect($redirect_url);
        }
        exit;
    }

} 