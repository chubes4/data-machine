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

use DataMachine\Handlers\{HandlerFactory, HandlerRegistry};
use DataMachine\Admin\RemoteLocations\RemoteLocationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SettingsFields {

    /**
     * Handler Factory instance.
     * @var HandlerFactory
     * @since 0.15.0
     */
    private $handler_factory;

    /**
     * Handler Registry instance.
     * @var HandlerRegistry
     * @since 0.15.0
     */
    private $handler_registry;

    /**
     * Service for retrieving remote location options.
     * @var RemoteLocationService
     * @since 0.16.0
     */
    private $remote_location_service;


    /**
     * Constructor.
     *
     * @param HandlerFactory   $handler_factory         Handler Factory instance.
     * @param HandlerRegistry          $handler_registry        Handler Registry instance.
     * @param RemoteLocationService $remote_location_service Service for remote locations.
     * @since 0.15.0
     * @since 0.16.0 Added $remote_location_service dependency.
     */
    public function __construct(
        HandlerFactory $handler_factory,
        HandlerRegistry $handler_registry,
        RemoteLocationService $remote_location_service
    ) {
        $this->handler_factory = $handler_factory;
        $this->handler_registry = $handler_registry;
        $this->remote_location_service = $remote_location_service;

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
        // This allows both core and external handlers to register their settings the same way
        $fields = apply_filters('dm_handler_settings_fields', [], $handler_type, $handler_slug, $current_config);
        
        // If no fields returned from filter, try legacy method as fallback
        if (empty($fields)) {
            $fields = $this->get_legacy_handler_fields($handler_type, $handler_slug, $current_config);
        }

        // --- Populate Remote Locations dynamically for publish_remote using the service ---
        if ($handler_type === 'output' && $handler_slug === 'publish_remote') {
            // Check if the location_id field exists and the service is available
            if (isset($fields['location_id']) && $this->remote_location_service) {
                $user_id = get_current_user_id();
                // Use the injected service to get formatted options for PHP select fields
                $fields['location_id']['options'] = $this->remote_location_service->get_user_locations_for_options($user_id);
                // For JS/AJAX, use get_user_locations_for_js (see AJAX handlers)
            }
        }
        // --- End Remote Location Population ---

        // --- Populate Remote Locations for airdrop_rest_api using the service ---
        if ($handler_type === 'input' && $handler_slug === 'airdrop_rest_api') {
             // Check if the location_id field exists and the service is available
            if (isset($fields['location_id']) && $this->remote_location_service) {
                $user_id = get_current_user_id();
                 // Use the injected service to get formatted options
                $fields['location_id']['options'] = $this->remote_location_service->get_user_locations_for_options($user_id);
            }
        }
        // --- End airdrop_rest_api field population ---

        // TODO: Potentially add plugin-wide filters or modifications here

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
            return $this->handler_registry->get_input_handler_class($handler_slug);
        } elseif ($handler_type === 'output') {
            return $this->handler_registry->get_output_handler_class($handler_slug);
        }

        return null;
    }

    /**
     * Legacy method to get handler fields via handler instantiation.
     * Used as fallback during transition to hook-based system.
     *
     * @param string $handler_type 'input' or 'output'
     * @param string $handler_slug Handler slug
     * @param array $current_config Current configuration
     * @return array Settings fields array
     */
    private function get_legacy_handler_fields(string $handler_type, string $handler_slug, array $current_config = []): array {
        $fields = [];
        
        try {
            // Use the factory's create_handler method directly
            $handler_instance = $this->handler_factory->create_handler($handler_type, $handler_slug);

            // Check if the instance has the get_settings_fields method
            if (method_exists($handler_instance, 'get_settings_fields')) {
                $ref = new \ReflectionMethod($handler_instance, 'get_settings_fields');
                $params = $ref->getParameters();
                $args = [];
                if (isset($params[0])) { // Check if it accepts config
                    $args[] = $current_config;
                }
                // Call with appropriate arguments
                $fields = $handler_instance->get_settings_fields(...$args);
            }
        } catch (\Exception $e) {
            // Default to empty on error
            $fields = [];
        }
        
        return $fields;
    }


} // End class \\DataMachine\\Admin\\ModuleConfig\\SettingsFields