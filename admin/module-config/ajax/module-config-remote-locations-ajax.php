<?php
/**
 * Handles AJAX requests related to remote locations for module config.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config/ajax
 * @since      NEXT_VERSION
 */
class Data_Machine_Module_Config_Remote_Locations_Ajax {
    /** @var Data_Machine_Database_Remote_Locations */
    private $db_locations;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations Remote Locations DB service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Remote_Locations $db_locations,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_locations = $db_locations;
        $this->logger = $logger;

        add_action('wp_ajax_dm_get_user_locations', [$this, 'get_user_locations_ajax_handler']);
        add_action('wp_ajax_dm_get_location_synced_info', [$this, 'get_location_synced_info_ajax_handler']);
    }

    /**
     * AJAX handler to fetch remote locations for the current user (for module config page).
     */
    public function get_user_locations_ajax_handler() {
        check_ajax_referer('dm_module_config_actions_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            wp_send_json_error(['message' => __('User not logged in.', 'data-machine')]);
            return;
        }
        require_once(DATA_MACHINE_PATH . 'admin/module-config/remote-locations/RemoteLocationService.php');
        $remote_location_service = new Data_Machine_Remote_Location_Service($this->db_locations);
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

        // Fetch the location data (get_location selects all columns)
        $location = $this->db_locations->get_location($location_id, $user_id, false);

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
                if (in_array((int)$slug, $enabled_post_types)) {
                    $filtered_post_types[$slug] = $details;
                }
            }
        }

        // Filter taxonomies
        $filtered_taxonomies = [];
        if (!empty($synced_info['taxonomies']) && is_array($synced_info['taxonomies'])) {
            foreach ($synced_info['taxonomies'] as $slug => $details) {
                if (in_array((int)$slug, $enabled_taxonomies)) {
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