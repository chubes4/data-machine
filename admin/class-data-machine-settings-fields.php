<?php
/**
 * Manages the definition and retrieval of settings fields for various handlers.
 *
 * Centralizes the settings field definitions for input and output handlers
 * within the Data Machine plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin
 * @since      0.15.0 // Or current version
 */
class Data_Machine_Settings_Fields {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     * @since 0.15.0
     */
    private $locator;

    /**
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations|null
     */
    private $db_locations = null;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     * @since 0.15.0
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        // Get the DB locations service, but allow it to fail gracefully if not registered yet
        try {
             $this->db_locations = $this->locator->get('database_remote_locations');
        } catch (\Exception $e) {
             error_log('ADC Settings Fields Error: Failed to get database_remote_locations service: ' . $e->getMessage());
             $this->db_locations = null;
        }
    }

    /**
     * Retrieves the settings fields for a specific handler type and slug.
     *
     * @param string $handler_type 'input' or 'output'.
     * @param string $handler_slug The slug of the handler (e.g., 'rss', 'publish_local').
     * @return array An array defining the settings fields for the handler, or an empty array if none.
     * @since 0.15.0
     */
    public function get_fields_for_handler(string $handler_type, string $handler_slug): array {
        $fields = [];
        $handler_class = $this->get_handler_class_name($handler_type, $handler_slug);

        if ($handler_class && method_exists($handler_class, 'get_settings_fields')) {
            // Call the static method on the handler class
            $fields = call_user_func([$handler_class, 'get_settings_fields']);
        }

        // --- Populate Remote Locations dynamically for publish-remote ---
        if ($handler_type === 'output' && $handler_slug === 'publish-remote' && isset($fields['remote_location_id']) && $this->db_locations) {
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                 $locations = $this->db_locations->get_locations_for_user($user_id);
                 $options = ['' => '-- Select Location --'];
                 if (!empty($locations)) {
                     foreach ($locations as $location) {
                         $options[$location->location_id] = $location->location_name;
                     }
                 }
                 $fields['remote_location_id']['options'] = $options;
             } else {
                 // No user logged in? Shouldn't happen in admin, but handle defensively
                 $fields['remote_location_id']['options'] = ['' => '-- Error: User not logged in --'];
             }
         }
         // --- End Remote Location Population ---

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
        $handler_registry = $this->locator->get('handler_registry');
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
        $handler_registry = $this->locator->get('handler_registry');

        if ($handler_type === 'input') {
            return $handler_registry->get_input_handler_class($handler_slug);
        } elseif ($handler_type === 'output') {
            return $handler_registry->get_output_handler_class($handler_slug);
        }

        return null;
    }


} // End class Data_Machine_Settings_Fields