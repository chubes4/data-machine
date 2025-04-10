<?php
/**
 * Handles AJAX requests related to remote locations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.16.0 // Or the current version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Data_Machine_Ajax_Locations {

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
        
        // Add hooks for location-related AJAX actions
        add_action('wp_ajax_dm_get_location_synced_info', [$this, 'handle_get_location_synced_info_ajax']);
        add_action('wp_ajax_dm_get_user_locations', [$this, 'get_user_locations_ajax_handler']);
        add_action('wp_ajax_dm_sync_location_info', [$this, 'handle_sync_location_ajax']);
        add_action('wp_ajax_dm_delete_location', [$this, 'handle_delete_location_ajax']);
    }

    /**
     * AJAX handler to fetch remote locations for the current user.
     * Used by the settings page dropdowns.
     *
     * Moved from Data_Machine_Ajax_Projects
     * @since 0.16.0 // Or current version
     */
    public function get_user_locations_ajax_handler() {
        // Verify nonce - Use a nonce appropriate for settings or a general one
        // Assuming the settings script sends 'dm_settings_nonce'
        check_ajax_referer('dm_settings_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(['message' => __('User not logged in.', 'data-machine')]);
            return;
        }

        // Get database service via the locator
        try {
            $db_locations = $this->locator->get('database_remote_locations');
        } catch (\Exception $e) {
            error_log('ADC Error: Failed to get Database_Remote_Locations service in AJAX handler. ' . $e->getMessage());
            wp_send_json_error(['message' => __('Internal server error: Could not access location data.', 'data-machine')]);
            return;
        }

        // Fetch locations using the correct method
        $locations = $db_locations->get_locations_for_user($user_id);

        if (is_wp_error($locations)) {
            error_log('ADC Error: WP_Error fetching user locations. ' . $locations->get_error_message());
            wp_send_json_error(['message' => __('Error fetching locations.', 'data-machine') . ' ' . $locations->get_error_message()]);
        } elseif ($locations === false) {
            // Handle potential false return if the method can return false on DB error
            error_log('ADC Error: Database error fetching user locations (returned false).');
            wp_send_json_error(['message' => __('Database error fetching locations.', 'data-machine')]);
        } else {
            // Success - send the location data (array of objects)
            wp_send_json_success($locations);
        }
    }

    /**
     * Handles the AJAX request to get synced site info for a specific location.
     *
     * Moved from Data_Machine_Admin_Page
     * @since 0.16.0 // Or current version
     */
    public function handle_get_location_synced_info_ajax() {
        $location_id = isset($_POST['location_id']) ? absint($_POST['location_id']) : 0;
        // Use specific nonce for this action, differentiate from list table nonce
        $nonce_action = 'dm_get_location_synced_info_nonce';

        // Use check_ajax_referer for consistency, expecting the nonce under the key 'nonce'
        check_ajax_referer($nonce_action, 'nonce');

        if (!$location_id) {
            wp_send_json_error(['message' => __('Invalid Location ID.', 'data-machine')]);
        }

        $user_id = get_current_user_id();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }
  
        $db_locations = $this->locator->get('database_remote_locations');
        $location = $db_locations->get_location($location_id, $user_id, false);
  
        if (!$location) {
            wp_send_json_error(['message' => __('Location not found or permission denied.', 'data-machine')]);
        }
  
        if (empty($location->synced_site_info)) {
            wp_send_json_error(['message' => __('Location has not been synced yet. Please sync it on the Manage Locations page.', 'data-machine')]);
        }
  
        wp_send_json_success(['synced_site_info' => $location->synced_site_info]);
    }

    /**
     * Handles the AJAX request to sync site info for a remote location.
     * 
     * Moved from Data_Machine_Admin_Page
     * @since 0.16.0 // Or current version
     */
    public function handle_sync_location_ajax() {
        $location_id = isset($_POST['location_id']) ? absint($_POST['location_id']) : 0;
        $nonce = $_POST['_wpnonce'] ?? '';
        $user_id = get_current_user_id();
   
        if (!$location_id || !wp_verify_nonce($nonce, 'dm_sync_location_' . $location_id)) {
            wp_send_json_error(['message' => __('Invalid request or nonce verification failed.', 'data-machine')]);
        }
   
        if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }
   
        $db_locations = $this->locator->get('database_remote_locations');
        $location = $db_locations->get_location($location_id, $user_id, true);
   
        if (!$location || empty($location->target_site_url) || empty($location->target_username) || !isset($location->password)) {
             wp_send_json_error(['message' => __('Location not found or missing credentials.', 'data-machine')]);
        }
        if ($location->password === false) {
             wp_send_json_error(['message' => __('Failed to decrypt password for location.', 'data-machine')]);
        }
   
        $api_url = $location->target_site_url . '/wp-json/dma/v1/site-info';
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($location->target_username . ':' . $location->password)
            ),
            'timeout' => 30,
        );
        $response = wp_remote_get($api_url, $args);
   
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => __('Failed to connect to the remote site.', 'data-machine'),
                'error_detail' => $response->get_error_message()
            ]);
        }
   
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
   
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : __('Unknown error occurred on the remote site.', 'data-machine');
            wp_send_json_error([
                'message' => sprintf(__('Remote site returned an error (Code: %d).', 'data-machine'), $response_code),
                'error_detail' => $error_message
            ]);
        }
   
        $decoded_data = json_decode($body, true);
        if (empty($decoded_data) || !isset($decoded_data['post_types']) || !isset($decoded_data['taxonomies'])) {
             wp_send_json_error(['message' => __('Received invalid data format from the remote site.', 'data-machine')]);
             return; // Exit if data format is invalid
        }
   
        $site_info_json = wp_json_encode($decoded_data);

        // Add check for encoding failure
        if ($site_info_json === false) {
            // Log the error if possible
            error_log('ADC Sync Error: Failed to wp_json_encode decoded data for location ID: ' . $location_id);
            wp_send_json_error(['message' => __('Failed to process data received from the remote site (JSON encoding failed).', 'data-machine')]);
            return; // Exit
        }
        // End check

        $updated = $db_locations->update_synced_info($location_id, $user_id, $site_info_json);
   
        if ($updated) {
            // Re-fetch the location to get the actual stored timestamp
            $updated_location = $db_locations->get_location($location_id, $user_id, false); // Don't need password here
            $last_sync_time_formatted = 'Error fetching time'; // Default
            if ($updated_location && !empty($updated_location->last_sync_time)) {
                $timestamp = strtotime($updated_location->last_sync_time);
                $last_sync_time_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
            }

             wp_send_json_success([
                 'message' => __('Remote site data synced successfully!', 'data-machine'),
                 'last_sync_time' => $last_sync_time_formatted
             ]);
        } else {
             wp_send_json_error(['message' => __('Failed to save synced data to the database.', 'data-machine')]);
        }
    }

    /**
     * Handles the AJAX request to delete a remote location.
     * 
     * Moved from Data_Machine_Admin_Page
     * @since 0.16.0 // Or current version
     */
    public function handle_delete_location_ajax() {
        $location_id = isset($_POST['location_id']) ? absint($_POST['location_id']) : 0;
        $nonce = $_POST['_wpnonce'] ?? '';
   
        if (!$location_id || !wp_verify_nonce($nonce, 'dm_delete_location_' . $location_id)) {
            wp_send_json_error(['message' => __('Invalid request or nonce verification failed.', 'data-machine')]);
        }
   
        if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
        }
   
        $db_locations = $this->locator->get('database_remote_locations');
        $result = $db_locations->delete_location($location_id, get_current_user_id());
   
        if ($result) {
            wp_send_json_success(['message' => __('Location deleted successfully.', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Could not delete location.', 'data-machine')]);
        }
    }
} 