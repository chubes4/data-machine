<?php
/**
 * Service to retrieve remote location data formatted for settings UI elements.
 *
 * This class abstracts the fetching of remote locations specifically for use
 * in dropdowns or similar UI components within the plugin settings.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      0.16.0
 */

// It's assumed the main plugin file or autoloader handles including the dependency.
// require_once DATA_MACHINE_PATH . 'includes/database/class-database-remote-locations.php';

namespace DataMachine\Admin\RemoteLocations;

use DataMachine\Database\RemoteLocations as DatabaseRemoteLocations;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class RemoteLocationService {

    /**
     * Database handler for remote locations.
     * @var DatabaseRemoteLocations
     * @since 0.16.0
     */
    private $db_locations;

    /**
     * Constructor - parameter-less for pure filter-based architecture.
     * All services accessed via WordPress filters.
     *
     * @since 0.16.0
     */
    public function __construct() {
        // Parameter-less constructor - all services accessed via filters
    }

    /**
     * Retrieves remote locations for a specific user, formatted as options for a select dropdown.
     *
     * Includes a default '-- Select Location --' option.
     * Returns an error message option if the user ID is invalid or no locations are found.
     *
     * @param int $user_id The ID of the user for whom to retrieve locations.
     * @return array An associative array suitable for dropdown options [value => label].
     *               Keys are location IDs, values are location names. Both are escaped.
     * @since 0.16.0
     */
    public function get_user_locations_for_options(int $user_id): array {
        // Start with the default option, escaped and translatable.
        $options = ['' => esc_html__('-- Select Location --', 'data-machine')];

        // Retrieve locations using filter-based service access
        $db_locations = apply_filters('dm_get_db_remote_locations', null);
        $locations = $db_locations ? $db_locations->get_all_locations() : [];

        // Check if locations were retrieved successfully and is an array
        if (!empty($locations) && is_array($locations)) {
            foreach ($locations as $location) {
                // Ensure the expected object properties exist before using them
                if (isset($location->location_id) && isset($location->location_name)) {
                    // Sanitize/escape output for security. Use esc_attr for values, esc_html for display text.
                    $options[esc_attr($location->location_id)] = esc_html($location->location_name);
                }
            }
        }
        // If $locations is empty or not an array, $options will just contain the default item.
        // We could add a specific message like '-- No locations found --' if needed,
        // but the current logic in Settings_Fields doesn't do this, so we'll keep it simple.

        return $options;
    }

    /**
     * Retrieves remote locations for a specific user, formatted for JavaScript (AJAX/JS usage).
     *
     * @param int $user_id The ID of the user for whom to retrieve locations.
     * @return array Array of objects: [ [ 'location_id' => ..., 'location_name' => ... ], ... ]
     */
    public function get_user_locations_for_js(int $user_id): array {
        $db_locations = apply_filters('dm_get_db_remote_locations', null);
        $locations = $db_locations ? $db_locations->get_all_locations() : [];
        $result = [];
        if (!empty($locations) && is_array($locations)) {
            foreach ($locations as $location) {
                if (isset($location->location_id) && isset($location->location_name)) {
                    $result[] = [
                        'location_id' => (string)$location->location_id,
                        'location_name' => (string)$location->location_name,
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Retrieves all remote locations, formatted for JavaScript (AJAX/JS usage).
     *
     * @return array Array of objects: [ [ 'location_id' => ..., 'location_name' => ... ], ... ]
     */
    public function get_all_locations_for_js(): array {
        return $this->get_user_locations_for_js(0); // Reuse existing logic
    }

    /**
     * Retrieves all remote locations, formatted as options for a select dropdown.
     *
     * @return array An associative array suitable for dropdown options [value => label].
     */
    public function get_all_locations_for_options(): array {
        return $this->get_user_locations_for_options(0); // Reuse existing logic
    }

} // End class RemoteLocationService 