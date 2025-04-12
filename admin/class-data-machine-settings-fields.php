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
             error_log('DM Settings Fields Error: Failed to get database_remote_locations service: ' . $e->getMessage());
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
    public function get_fields_for_handler(string $handler_type, string $handler_slug, array $current_config = []): array {
        $fields = [];
        // Construct the service key (e.g., 'input_files', 'output_publish_local')
        $handler_key = $handler_type . '_' . str_replace('-', '_', $handler_slug);

        try {
            // Check if the handler is registered in the locator
            if ($this->locator->has($handler_key)) {
                // Get the handler instance from the locator
                $handler_instance = $this->locator->get($handler_key);

                // Check if the instance has the get_settings_fields method
                if (method_exists($handler_instance, 'get_settings_fields')) {
                	// Pass $current_config and the locator instance
                	$ref = new \ReflectionMethod($handler_instance, 'get_settings_fields');
                	$params = $ref->getParameters();
                	$args = [];
                	if (isset($params[0])) { // Check if it accepts config
                		$args[] = $current_config;
                	}
                	if (isset($params[1]) && $params[1]->hasType() && $params[1]->getType()->getName() === 'Data_Machine_Service_Locator') { // Check if it accepts locator
                		$args[] = $this->locator;
                	}
                	// Call with appropriate arguments
                	$fields = $handler_instance->get_settings_fields(...$args);
            
                } else {
                    // Log if method doesn't exist on the retrieved instance
                    error_log("DM Settings Fields Info: Handler '{$handler_key}' retrieved but has no get_settings_fields method.");
                }
            } else {
                // Log if handler key is not found in the locator
                error_log("DM Settings Fields Warning: Handler service key '{$handler_key}' not found in locator for type '{$handler_type}' and slug '{$handler_slug}'.");
            }
        } catch (\Exception $e) {
            // Catch any errors during locator->get() or method call
            error_log("DM Settings Fields Error: Failed to get settings fields for handler '{$handler_key}': " . $e->getMessage());
            $fields = []; // Default to empty on error
        }

        // --- Populate Remote Locations dynamically for publish_remote ---
        if ($handler_type === 'output' && $handler_slug === 'publish_remote') { // Check handler first
            if (isset($fields['location_id']) && $this->db_locations) { // Now check field and DB service
        	$user_id = get_current_user_id();
        	$options = ['' => '-- Select Location --']; // Start with default
        	if ($user_id > 0) {
        		$locations = $this->db_locations->get_locations_for_user($user_id);
        		if (!empty($locations)) {
        			foreach ($locations as $location) {
        				$options[$location->location_id] = $location->location_name;
        			}
        		}
        	} else {
        		$options = ['' => '-- Error: User not logged in --']; // Overwrite if no user
        	}
        	// Directly modify the options in the $fields array
        	$fields['location_id']['options'] = $options;
        	   }
        }
        // --- End Remote Location Population ---

        // --- Populate Remote Locations and fields for airdrop_rest_api input handler ---
        if ($handler_type === 'input' && $handler_slug === 'airdrop_rest_api') {
            if (isset($fields['location_id']) && $this->db_locations) {
                $user_id = get_current_user_id();
                $options = ['' => '-- Select Location --']; // Start with default
                
                if ($user_id > 0) {
                    $locations = $this->db_locations->get_locations_for_user($user_id);
                    if (!empty($locations)) {
                        foreach ($locations as $location) {
                            $options[$location->location_id] = $location->location_name;
                        }
                    }
                } else {
                    $options = ['' => '-- Error: User not logged in --'];
                }
                
                // Set location dropdown options
                $fields['location_id']['options'] = $options;
                
                // REMOVED Pre-population logic for post types, categories, tags.
                // JavaScript will handle this dynamically based on selection.
                // Placeholder options will be defined in the handler's get_settings_fields method.
                
                /* // --- Start Removed Block --- 
                $location_id = isset($current_config['location_id']) ? absint($current_config['location_id']) : 0;
                if ($location_id && $user_id) {
                    $location = $this->db_locations->get_location($location_id, $user_id);
                    if ($location && !empty($location->synced_site_info)) {
                        $site_info = json_decode($location->synced_site_info, true);
                        
                        // Check if JSON decoding was successful
                        if (json_last_error() === JSON_ERROR_NONE && !empty($site_info)) {
                            // Populate post types
                            if (!empty($site_info['post_types']) && is_array($site_info['post_types'])) {
                                $post_type_options = [];
                                
                                // First try to extract from standard format (object with label property)
                                foreach ($site_info['post_types'] as $slug => $pt_data) {
                                    if (is_array($pt_data) && isset($pt_data['label'])) {
                                        $post_type_options[$slug] = $pt_data['label'];
                                    } elseif (is_object($pt_data) && isset($pt_data->label)) {
                                        $post_type_options[$slug] = $pt_data->label;
                                    }
                                }
                                
                                // If standard format didn't yield results, try simple format (slug => label)
                                if (empty($post_type_options)) {
                                    foreach ($site_info['post_types'] as $slug => $label) {
                                        if (is_string($label)) {
                                            $post_type_options[$slug] = $label;
                                        }
                                    }
                                }
                                
                                // Set post type options if we found any
                                if (!empty($post_type_options)) {
                                    $fields['rest_post_type']['options'] = $post_type_options;
                                }
                            }
                            
                            // Populate categories
                            if (!empty($site_info['taxonomies']['category']['terms']) && is_array($site_info['taxonomies']['category']['terms'])) {
                                $category_options = [0 => '-- All Categories --']; // Default "all" option
                                foreach ($site_info['taxonomies']['category']['terms'] as $cat) {
                                    if (isset($cat['term_id']) && isset($cat['name'])) {
                                        $category_options[$cat['term_id']] = $cat['name'];
                                    }
                                }
                                if (count($category_options) > 1) { // Only update if we found categories
                                    $fields['rest_category']['options'] = $category_options;
                                }
                            }
                            
                            // Populate tags
                            if (!empty($site_info['taxonomies']['post_tag']['terms']) && is_array($site_info['taxonomies']['post_tag']['terms'])) {
                                $tag_options = [0 => '-- All Tags --']; // Default "all" option
                                foreach ($site_info['taxonomies']['post_tag']['terms'] as $tag) {
                                    if (isset($tag['term_id']) && isset($tag['name'])) {
                                        $tag_options[$tag['term_id']] = $tag['name'];
                                    }
                                }
                                if (count($tag_options) > 1) { // Only update if we found tags
                                    $fields['rest_tag']['options'] = $tag_options;
                                }
                            }
                        }
                    }
                }
                // --- End Removed Block --- */
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