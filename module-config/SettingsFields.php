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

class Data_Machine_Settings_Fields {

    /**
     * Handler Factory instance.
     * @var Data_Machine_Handler_Factory
     * @since 0.15.0
     */
    private $handler_factory;

    /**
     * Service for retrieving remote location options.
     * @var Data_Machine_Remote_Location_Service
     * @since 0.16.0
     */
    private $remote_location_service;

    /**
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations|null
     * @deprecated 0.16.0 Use $remote_location_service instead.
     */
    // private $db_locations = null; // Keep commented out or remove later

    /**
     * Constructor.
     *
     * @param Data_Machine_Handler_Factory           $handler_factory         Handler Factory instance.
     * @param Data_Machine_Remote_Location_Service $remote_location_service Service for remote locations.
     * @since 0.15.0
     * @since 0.16.0 Added $remote_location_service dependency.
     */
    public function __construct(
        Data_Machine_Handler_Factory $handler_factory,
        Data_Machine_Remote_Location_Service $remote_location_service
    ) {
        $this->handler_factory = $handler_factory;
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
        $fields = [];
        // Construct the service key (e.g., 'input_files', 'output_publish_local')
        $handler_key = $handler_type . '_' . str_replace('-', '_', $handler_slug);

        try {
            // Use the factory's create_handler method directly.
            // It will throw an exception if the handler doesn't exist.
            $handler_instance = $this->handler_factory->create_handler($handler_type, $handler_slug);

            // Check if the instance has the get_settings_fields method
            if (method_exists($handler_instance, 'get_settings_fields')) {
                // Pass $current_config and the locator instance
                // Note: We might need to adjust how the locator is passed if the factory doesn't expose it.
                // For now, assume the handler's get_settings_fields signature might have changed
                // or doesn't strictly need the locator anymore.
                // Let's refine the argument passing based on the ReflectionMethod result.
                $ref = new \ReflectionMethod($handler_instance, 'get_settings_fields');
                $params = $ref->getParameters();
                $args = [];
                if (isset($params[0])) { // Check if it accepts config
                    $args[] = $current_config;
                }
                // Call with appropriate arguments
                $fields = $handler_instance->get_settings_fields(...$args);

            } else {
                // Log if method doesn't exist on the retrieved instance
                			// Debug logging removed for production
            }
        } catch (\Exception $e) {
            // Catch errors from create_handler (handler not found) or get_settings_fields
            			// Debug logging removed for production
            $fields = []; // Default to empty on error
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
     * Retrieves all settings fields for all registered handlers.
     * Useful for potentially displaying all possible settings somewhere.
     *
     * @return array An associative array where keys are handler types ('input', 'output')
     *               and values are arrays of handler slugs mapped to their fields.
     *               e.g., ['input' => ['rss' => [...fields...], 'files' => []], 'output' => [...]]
     * @since 0.15.0
     */
    public function get_all_fields(): array {
        $all_fields = [
            'input' => [],
            'output' => [],
        ];

        // Get handlers from the registry service
        $handler_registry = $this->handler_factory->get('handler_registry');
        $input_handlers = $handler_registry->get_input_handlers();
        $output_handlers = $handler_registry->get_output_handlers();

        foreach ($input_handlers as $slug => $handler_info) {
            $class_name = $handler_info['class'];
            if (method_exists($class_name, 'get_settings_fields')) {
                $all_fields['input'][$slug] = call_user_func([$class_name, 'get_settings_fields']);
            } else {
                 $all_fields['input'][$slug] = [];
            }
        }

        foreach ($output_handlers as $slug => $handler_info) {
             $class_name = $handler_info['class'];
             if (method_exists($class_name, 'get_settings_fields')) {
                $all_fields['output'][$slug] = call_user_func([$class_name, 'get_settings_fields']);
            } else {
                 $all_fields['output'][$slug] = [];
            }
        }

        return $all_fields;
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
        $handler_registry = $this->handler_factory->get('handler_registry');

        if ($handler_type === 'input') {
            return $handler_registry->get_input_handler_class($handler_slug);
        } elseif ($handler_type === 'output') {
            return $handler_registry->get_output_handler_class($handler_slug);
        }

        return null;
    }


} // End class Data_Machine_Settings_Fields