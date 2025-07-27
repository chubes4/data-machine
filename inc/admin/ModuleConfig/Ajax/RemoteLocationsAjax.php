<?php
/**
 * Handles AJAX requests related to remote locations for module config.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config/ajax
 * @since      NEXT_VERSION
 */
namespace DataMachine\Admin\ModuleConfig\Ajax;

use DataMachine\Admin\RemoteLocations\RemoteLocationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class RemoteLocationsAjax {

    /**
     * Constructor.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {
        add_action('wp_ajax_dm_get_user_locations', [$this, 'get_user_locations_ajax_handler']);
        add_action('wp_ajax_dm_get_location_synced_info', [$this, 'get_location_synced_info_ajax_handler']);
    }

    /**
     * AJAX handler to fetch remote locations for the current user (for module config page).
     */
    public function get_user_locations_ajax_handler() {
        check_ajax_referer('dm_module_config_actions_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
            return;
        }
        
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(['message' => __('User not logged in.', 'data-machine')]);
            return;
        }
        // Get service via filter-based access
        $db_locations = apply_filters('dm_get_db_remote_locations', null);
        
        // RemoteLocationService class auto-loaded via PSR-4
        $remote_location_service = new RemoteLocationService($db_locations);
        $locations = $remote_location_service->get_user_locations_for_js($user_id);
        wp_send_json_success($locations);
    }

    /**
     * AJAX handler to fetch synced site info for a specific remote location (for module config page).
     */
    public function get_location_synced_info_ajax_handler() {
        $location_id = isset($_POST['location_id']) ? absint($_POST['location_id']) : 0;
        check_ajax_referer('dm_module_config_actions_nonce', 'nonce');
        $user_id = get_current_user_id();

        if (!$location_id) {
            wp_send_json_error(['message' => __('Invalid Location ID.', 'data-machine')]);
            return; // Exit early
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'data-machine')]);
            return; // Exit early
        }

        // Get service via filter-based access
        $db_locations = apply_filters('dm_get_db_remote_locations', null);
        
        // Fetch the location data (get_location selects all columns)
        $location = $db_locations->get_location($location_id, $user_id, false);

        if (!$location) {
            wp_send_json_error(['message' => __('Location not found or permission denied.', 'data-machine')]);
            return; // Exit early
        }
        if (empty($location->synced_site_info)) {
            wp_send_json_error(['message' => __('Location has not been synced yet. Please sync it on the Manage Locations page.', 'data-machine')]);
            return; // Exit early
        }

        // Decode the JSON data
        $synced_info = json_decode($location->synced_site_info, true);
        $enabled_post_types = !empty($location->enabled_post_types) ? json_decode($location->enabled_post_types, true) : [];
        $enabled_taxonomies = !empty($location->enabled_taxonomies) ? json_decode($location->enabled_taxonomies, true) : [];

        if (json_last_error() !== JSON_ERROR_NONE) {
             wp_send_json_error(['message' => __('Error decoding location data.', 'data-machine')]);
             return; // Exit early
        }

        // Filter post types
        $filtered_post_types = [];
        if (!empty($synced_info['post_types']) && is_array($synced_info['post_types'])) {
            foreach ($synced_info['post_types'] as $slug => $details) {
                if (in_array($slug, $enabled_post_types)) {
                    $filtered_post_types[$slug] = $details;
                }
            }
        }

        // Filter taxonomies
        $filtered_taxonomies = [];
        if (!empty($synced_info['taxonomies']) && is_array($synced_info['taxonomies'])) {
            foreach ($synced_info['taxonomies'] as $slug => $details) {
                if (in_array($slug, $enabled_taxonomies)) {
                    // Also filter terms within the taxonomy if they exist
                    if (isset($details['terms']) && is_array($details['terms'])) {
                        // Currently, we enable the whole taxonomy, not individual terms.
                        // If term-level filtering was needed, it would go here.
                        // For now, just include the whole taxonomy details if the slug is enabled.
                        $filtered_taxonomies[$slug] = $details;
                    } else {
                         // If no terms key, include details as is
                         $filtered_taxonomies[$slug] = $details;
                    }
                }
            }
        }

        // Prepare the response data with only enabled items
        $response_data = [
            'post_types' => $filtered_post_types,
            'taxonomies' => $filtered_taxonomies,
        ];

        // Send the filtered data, encoding it back to JSON for the JS side
        wp_send_json_success(['enabled_site_info' => json_encode($response_data)]);
    }
} 