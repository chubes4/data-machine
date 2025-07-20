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

    /** @var Data_Machine_Database_Remote_Locations */
    private $db_locations;

    /**
     * Initialize hooks and dependencies.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations Injected DB Locations service.
     */
    public function __construct(Data_Machine_Database_Remote_Locations $db_locations) {
        $this->db_locations = $db_locations;

        // Add hooks for location-related AJAX actions
        add_action('wp_ajax_dm_sync_location_info', [$this, 'handle_sync_location_ajax']);
        add_action('wp_ajax_dm_delete_location', [$this, 'handle_delete_location_ajax']);
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
   
        $db_locations = $this->db_locations; // Use injected property
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
                /* translators: %d: HTTP response code */
                'message' => sprintf(__('Remote site returned an error (Code: %d).', 'data-machine'), $response_code),
                'error_detail' => $error_message
            ]);
        }
   
        $decoded_data = json_decode($body, true);
        if (empty($decoded_data) || !isset($decoded_data['post_types']) || !isset($decoded_data['taxonomies'])) {
             wp_send_json_error(['message' => __('Received invalid data format from the remote site.', 'data-machine')]);
             return;
        }

        // Get enabled taxonomies for this location (as slugs)
        $enabled_taxonomies = [];
        if (!empty($location->enabled_taxonomies)) {
            $enabled_taxonomies = json_decode($location->enabled_taxonomies, true);
            if (!is_array($enabled_taxonomies)) {
                $enabled_taxonomies = [];
            }
        }

        // Always keep all post types and taxonomies (names/labels)
        // For taxonomies, only keep terms for enabled taxonomies (or all if none enabled)
        $filtered_taxonomies = [];
        foreach ($decoded_data['taxonomies'] as $slug => $tax_data) {
            $filtered = [
                'label' => $tax_data['label'] ?? $slug,
                'post_types' => $tax_data['post_types'] ?? [],
            ];
            // Only include terms if this taxonomy is enabled, or if no taxonomies are enabled (first sync)
            if (empty($enabled_taxonomies) || in_array($slug, $enabled_taxonomies, true)) {
                if (isset($tax_data['terms'])) {
                    $filtered['terms'] = $tax_data['terms'];
                }
            }
            $filtered_taxonomies[$slug] = $filtered;
        }
        $filtered_data = [
            'post_types' => $decoded_data['post_types'],
            'taxonomies' => $filtered_taxonomies,
        ];

        $site_info_json = wp_json_encode($filtered_data);

        if ($site_info_json === false) {
            wp_send_json_error(['message' => __('Failed to process data received from the remote site (JSON encoding failed).', 'data-machine')]);
            return;
        }

        $updated = $this->db_locations->update_synced_info($location_id, $user_id, $site_info_json);
   
        if ($updated) {
            $updated_location = $this->db_locations->get_location($location_id, $user_id, false);
            $last_sync_time_formatted = 'Error fetching time';
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
   
        $db_locations = $this->db_locations; // Use injected property
        $result = $db_locations->delete_location($location_id, get_current_user_id());
   
        if ($result) {
            wp_send_json_success(['message' => __('Location deleted successfully.', 'data-machine')]);
        } else {
            wp_send_json_error(['message' => __('Could not delete location.', 'data-machine')]);
        }
    }
} 