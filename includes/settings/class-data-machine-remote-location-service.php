<?php
/**
 * Service to retrieve remote location data formatted for settings UI elements.
 *
 * This class abstracts the fetching of remote locations specifically for use
 * in dropdowns or similar UI components within the plugin settings.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/settings
 * @since      0.16.0
 */

// It's assumed the main plugin file or autoloader handles including the dependency.
// require_once DATA_MACHINE_PATH . 'includes/database/class-database-remote-locations.php';

class Data_Machine_Remote_Location_Service {

    /**
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations
     * @since 0.16.0
     */
    private $db_locations;

    /**
     * Constructor.
     *
     * Injects the database handler dependency.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations Instance of the remote locations database handler.
     * @since 0.16.0
     */
    public function __construct(Data_Machine_Database_Remote_Locations $db_locations) {
        $this->db_locations = $db_locations;
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
        if ($user_id <= 0) {
            // Return an error indication suitable for a dropdown
            // Using esc_html__ for translation readiness and escaping.
            return ['' => esc_html__('-- Error: Invalid User ID --', 'data-machine')];
        }

        // Start with the default option, escaped and translatable.
        $options = ['' => esc_html__('-- Select Location --', 'data-machine')];

        // Retrieve locations using the injected database service
        // It's assumed get_locations_for_user returns an array of objects or empty array/null on failure.
        $locations = $this->db_locations->get_locations_for_user($user_id);

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

    // Potential future method:
    // public function get_all_locations_for_options(): array { ... }

} // End class Data_Machine_Remote_Location_Service 