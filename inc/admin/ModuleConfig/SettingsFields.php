<?php
/**
 * Manages the definition and retrieval of settings fields for various handlers.
 *
 * Centralizes the settings field definitions for input and output handlers
 * within the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      0.15.0 // Or current version
 */

namespace DataMachine\Admin\ModuleConfig;

use DataMachine\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SettingsFields {

    /**
     * Constructor - parameter-less for pure filter-based architecture.
     * All services accessed via WordPress filters.
     *
     * @since 0.15.0
     * @since 0.16.0 Converted to filter-based service access.
     */
    public function __construct() {
        // Parameter-less constructor - all services accessed via filters
    }

    /**
     * Retrieves the settings fields for a specific handler type and slug.
     *
     * @param string $handler_type 'input' or 'output'.
     * @param string $handler_slug The slug of the handler (e.g., 'rss', 'publish_local').
     * @return array An array defining the settings fields for the handler, or an empty array if none.
     * @since 0.15.0
     */
    public function get_fields_for_handler(string $handler_type, string $handler_slug, array $current_config = []): array {
        // Use WordPress filter system to get handler settings fields
        // This is the ONLY way to register settings fields - core and external handlers use identical system
        $fields = apply_filters('dm_handler_settings_fields', [], $handler_type, $handler_slug, $current_config);

        // --- Populate Remote Locations dynamically for publish_remote using filter-based service access ---
        if ($handler_type === 'output' && $handler_slug === 'publish_remote') {
            // Check if the location_id field exists and get service via filter
            if (isset($fields['location_id'])) {
                $remote_location_service = apply_filters('dm_get_remote_location_service', null);
                if ($remote_location_service) {
                    $user_id = get_current_user_id();
                    // Use filter-based service to get formatted options for PHP select fields
                    $fields['location_id']['options'] = $remote_location_service->get_user_locations_for_options($user_id);
                    // For JS/AJAX, use get_user_locations_for_js (see AJAX handlers)
                }
            }
        }
        // --- End Remote Location Population ---

        // --- Populate Remote Locations for airdrop_rest_api using filter-based service access ---
        if ($handler_type === 'input' && $handler_slug === 'airdrop_rest_api') {
             // Check if the location_id field exists and get service via filter
            if (isset($fields['location_id'])) {
                $remote_location_service = apply_filters('dm_get_remote_location_service', null);
                if ($remote_location_service) {
                    $user_id = get_current_user_id();
                     // Use filter-based service to get formatted options
                    $fields['location_id']['options'] = $remote_location_service->get_user_locations_for_options($user_id);
                }
            }
        }
        // --- End airdrop_rest_api field population ---

        // Apply plugin-wide filters for field modifications
        $fields = apply_filters('dm_settings_fields_after_population', $fields, $handler_type, $handler_slug, $current_config);

        return is_array($fields) ? $fields : [];
    }



    /**
     * Gets the class name for a specific handler.
     *
     * @param string $handler_type 'input' or 'output'.
     * @param string $handler_slug The slug of the handler.
     * @return string|null The class name or null if not found.
     * @since 0.15.0
     */
    private function get_handler_class_name(string $handler_type, string $handler_slug): ?string {
        // Get handlers from the registry service

        if ($handler_type === 'input') {
            return Constants::get_input_handler_class($handler_slug);
        } elseif ($handler_type === 'output') {
            return Constants::get_output_handler_class($handler_slug);
        }

        return null;
    }



} // End class \\DataMachine\\Admin\\ModuleConfig\\SettingsFields