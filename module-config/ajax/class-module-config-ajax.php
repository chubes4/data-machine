<?php
/**
 * Handles AJAX requests related to Modules and Processing Jobs.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.12.0
 */
class Data_Machine_Module_Config_Ajax {

    /** @var Data_Machine_Database_Modules */
    private $db_modules;

    /** @var Data_Machine_Database_Projects */
    private $db_projects;

    /** @var Data_Machine_Job_Executor */
    private $job_executor;

    /** @var Data_Machine_Input_Files */
    private $input_files_handler;

    /** @var Data_Machine_Database_Remote_Locations */
    private $db_locations;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Modules $db_modules Modules DB service.
     * @param Data_Machine_Database_Projects $db_projects Projects DB service.
     * @param Data_Machine_Job_Executor $job_executor Job Executor service.
     * @param Data_Machine_Input_Files $input_files_handler Files Input Handler service.
     * @param Data_Machine_Database_Remote_Locations $db_locations Remote Locations DB service.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        Data_Machine_Job_Executor $job_executor,
        Data_Machine_Input_Files $input_files_handler, // Inject specific handler
        Data_Machine_Database_Remote_Locations $db_locations, // Inject remote locations DB handler
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->job_executor = $job_executor;
        $this->input_files_handler = $input_files_handler;
        $this->db_locations = $db_locations;
        $this->logger = $logger;

        // Register AJAX hooks
        add_action('wp_ajax_get_module_data', array($this, 'get_module_data_ajax_handler'));
        add_action('wp_ajax_dm_get_module_details', array($this, 'ajax_get_module_details'));
        add_action('wp_ajax_dm_sync_remote_site_details', array($this, 'ajax_sync_remote_site_details'));
        add_action('wp_ajax_dm_get_handler_template', array($this, 'ajax_get_handler_template'));
        add_action('wp_ajax_dm_get_project_modules',  [ $this, 'ajax_get_project_modules' ] );
    }

    /**
	 * AJAX handler to fetch data for a specific module.
     * Moved from Data_Machine class.
	 * @since 0.2.0 (Moved 0.12.0)
	 */
	public function get_module_data_ajax_handler() {
		// Check the nonce action that was actually used to create the nonce value in localization
		check_ajax_referer('dm_module_config_actions_nonce', 'nonce');

		$module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
		$user_id = get_current_user_id();

		if (empty($module_id)) {
			wp_send_json_error(array('message' => 'Module ID missing.'));
			return;
		}

        // Get dependencies from properties
        $db_modules = $this->db_modules;
        $db_projects = $this->db_projects;
        $logger = $this->logger;

		// --- Ownership Check (using Project) ---
        $module = $db_modules->get_module($module_id); // Get module without user check first
        if (!$module || !isset($module->project_id)) {
             wp_send_json_error(array('message' => __('Invalid module or project association missing.', 'data-machine')));
             return;
        }
        $project = $db_projects->get_project($module->project_id, $user_id);
        if (!$project) {
            wp_send_json_error(array('message' => __('Permission denied for this module.', 'data-machine')));
            return;
        }
        // --- End Ownership Check ---

        // Decode configs first
        $raw_ds_config_string = $module->data_source_config ?? null;
        $decoded_ds_config = !empty($raw_ds_config_string) ? json_decode(wp_unslash($raw_ds_config_string), true) : array();
        if (!is_array($decoded_ds_config)) $decoded_ds_config = [];

        $raw_output_config_string = $module->output_config ?? null;
        $decoded_output_config = !empty($raw_output_config_string) ? json_decode(wp_unslash($raw_output_config_string), true) : array();
        if (!is_array($decoded_output_config)) $decoded_output_config = [];

        // --- Refactored Nesting Logic --- 
        $final_ds_config = [];
        $final_output_config = [];
        if (count($decoded_ds_config) === 1 && key($decoded_ds_config) === $module->data_source_type) {
            $final_ds_config = $decoded_ds_config;
        } elseif (!empty($decoded_ds_config)) {
            $final_ds_config = [$module->data_source_type => $decoded_ds_config];
        } else {
             $final_ds_config = [$module->data_source_type => []];
        }
        if (count($decoded_output_config) === 1 && key($decoded_output_config) === $module->output_type) {
            $final_output_config = $decoded_output_config;
        } elseif (!empty($decoded_output_config)) {
            $final_output_config = [$module->output_type => $decoded_output_config];
        } else {
            $final_output_config = [$module->output_type => []];
        }
        // --- End Refactored Nesting Logic ---

		// Prepare data to return using the consistently nested configs
		$data_to_return = array(
			'module_id' => $module->module_id,
			'module_name' => $module->module_name,
			'data_source_type' => $module->data_source_type,
			'output_type' => $module->output_type,
			'data_source_config' => $final_ds_config, // Return nested
			'output_config' => $final_output_config, // Return nested
			'schedule_interval' => $module->schedule_interval ?? 'project_schedule',
			'schedule_status' => $module->schedule_status ?? 'active',
			// Add prompts
			'process_data_prompt' => $module->process_data_prompt ?? '',
			'fact_check_prompt' => $module->fact_check_prompt ?? '',
			'finalize_response_prompt' => $module->finalize_response_prompt ?? '',
			'skip_fact_check' => isset($module->skip_fact_check) ? (int)$module->skip_fact_check : 0
		);

		wp_send_json_success( $data_to_return );
		wp_die(); // this is required to terminate immediately and return a proper response
	}

    /**
     * AJAX handler to fetch HTML for a specific handler's settings template.
     *
     * Responds to the 'dm_get_handler_template' action.
     */
    public function ajax_get_handler_template() {
        // Verify nonce using the action used during nonce creation
        check_ajax_referer( 'dm_module_config_actions_nonce', 'nonce' );

        // Check user capability (adjust if needed)
        if (!current_user_can('manage_options')) {
            wp_send_json_error( ['message' => __('Permission denied.', 'data-machine')], 403 );
        }

        // Sanitize input
        $handler_type = isset( $_POST['handler_type'] ) ? sanitize_key( $_POST['handler_type'] ) : '';
        $handler_slug = isset( $_POST['handler_slug'] ) ? sanitize_key( $_POST['handler_slug'] ) : '';
        $location_id  = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
        $module_id    = isset( $_POST['module_id'] ) && $_POST['module_id'] !== 'new' ? absint( $_POST['module_id'] ) : 0;
        $user_id      = get_current_user_id();

        // Validate input
        if ( empty( $handler_type ) || !in_array( $handler_type, ['input', 'output'] ) || empty( $handler_slug ) ) {
            wp_send_json_error( ['message' => __('Missing or invalid handler type or slug.', 'data-machine')], 400 );
        }

        // Construct template path
        $template_path = DATA_MACHINE_PATH . "module-config/handler-templates/{$handler_type}/{$handler_slug}.php";

        if ( file_exists( $template_path ) ) {
             // --- Initialize variables --- 
             $site_info                = null; 
             $saved_config             = null; 
             $all_locations            = [];   
             $enabled_post_types_array = []; 
             $enabled_taxonomies_array = [];
             // Filtered options arrays to be generated here
             $filtered_post_type_options = [];
             $filtered_category_options  = [];
             $filtered_tag_options       = [];
             $filtered_custom_taxonomies = []; // Will store [slug => [label=>..., terms=>...]]

             // --- Fetch data for remote handlers ---
             if (in_array($handler_slug, ['publish_remote', 'airdrop_rest_api'])) {
                 // Fetch all locations for dropdown
                 require_once(DATA_MACHINE_PATH . 'module-config/remote-locations/RemoteLocationService.php');
                 $remote_location_service = new Data_Machine_Remote_Location_Service($this->db_locations);
                 $all_locations = $remote_location_service->get_user_locations_for_js($user_id); 

                 // Fetch specific location's info if ID provided
                 if ($location_id > 0) {
                     $location_data = $this->db_locations->get_location($location_id, $user_id, false); 
                     
                     if ($location_data) {
                         // Decode synced info
                         $raw_synced_info = $location_data->synced_site_info ?? null;
                         if (!empty($raw_synced_info)) {
                             $decoded_info = json_decode(wp_unslash($raw_synced_info), true);
                             if (is_array($decoded_info)) $site_info = $decoded_info;
                         }
                         // Decode enabled post types
                         $raw_enabled_pt = $location_data->enabled_post_types ?? null;
                         if (!empty($raw_enabled_pt)) {
                              $decoded_enabled_pt = json_decode(wp_unslash($raw_enabled_pt), true);
                              if (is_array($decoded_enabled_pt)) $enabled_post_types_array = $decoded_enabled_pt;
                         }
                         // Decode enabled taxonomies
                         $raw_enabled_tax = $location_data->enabled_taxonomies ?? null;
                         if (!empty($raw_enabled_tax)) {
                              $decoded_enabled_tax = json_decode(wp_unslash($raw_enabled_tax), true);
                              if (is_array($decoded_enabled_tax)) $enabled_taxonomies_array = $decoded_enabled_tax;
                         }
                     }
                 } // End if ($location_id > 0)

                 // --- *** NEW: Filter options based on enabled items *** ---
                 if ($site_info && is_array($site_info)) {
                     // Filter Post Types
                     if (isset($site_info['post_types']) && is_array($site_info['post_types'])) {
                         foreach ($site_info['post_types'] as $pt_slug => $pt_data) {
                             if (!in_array($pt_slug, $enabled_post_types_array)) continue;
                             
                             if (is_array($pt_data) && isset($pt_data['label'], $pt_data['name'])) {
                                 $filtered_post_type_options[] = ['value' => $pt_data['name'], 'text' => $pt_data['label']];
                             } elseif (is_string($pt_data)) {
                                 $filtered_post_type_options[] = ['value' => $pt_slug, 'text' => $pt_data];
                             }
                         }
                         usort($filtered_post_type_options, fn($a, $b) => strcmp($a['text'], $b['text']));
                     }

                     // Filter Taxonomies
                     if (isset($site_info['taxonomies']) && is_array($site_info['taxonomies'])) {
                         // Category
                         if (in_array('category', $enabled_taxonomies_array) && isset($site_info['taxonomies']['category']['terms']) && is_array($site_info['taxonomies']['category']['terms'])) {
                             $filtered_category_options = $site_info['taxonomies']['category']['terms'];
                             usort($filtered_category_options, fn($a, $b) => strcmp($a['name'], $b['name']));
                         }
                         // Post Tag
                         if (in_array('post_tag', $enabled_taxonomies_array) && isset($site_info['taxonomies']['post_tag']['terms']) && is_array($site_info['taxonomies']['post_tag']['terms'])) {
                             $filtered_tag_options = $site_info['taxonomies']['post_tag']['terms'];
                             usort($filtered_tag_options, fn($a, $b) => strcmp($a['name'], $b['name']));
                         }
                         // Custom Taxonomies
                         foreach ($site_info['taxonomies'] as $slug => $tax_data) {
                             if ($slug === 'category' || $slug === 'post_tag' || strpos($slug, 'post_format') !== false) continue;
                             if (!in_array($slug, $enabled_taxonomies_array)) continue;

                             if (isset($tax_data['terms']) && is_array($tax_data['terms'])) {
                                 usort($tax_data['terms'], fn($a, $b) => strcmp($a['name'], $b['name']));
                             }
                             // Store the whole enabled tax_data (label + sorted terms)
                             $filtered_custom_taxonomies[$slug] = $tax_data;
                         }
                     }
                 }
                 // --- *** END NEW Filter options *** ---

                 // Fetch saved module config if an ID is provided
                 if ($module_id > 0) {
                     $module = $this->db_modules->get_module($module_id);
                     if ($module) {
                         $raw_config_string = ($handler_type === 'output') ? $module->output_config : $module->data_source_config;
                         $decoded_config = !empty($raw_config_string) ? json_decode(wp_unslash($raw_config_string), true) : [];
                         // Extract the config specific to this handler slug
                         if (is_array($decoded_config) && isset($decoded_config[$handler_slug])) {
                             $saved_config = $decoded_config[$handler_slug];
                         } elseif (is_array($decoded_config) && count($decoded_config) === 1 && key($decoded_config) === $handler_slug) { // Handle old format potentially?
                             $saved_config = $decoded_config[$handler_slug];
                         } else {
                             $saved_config = []; // Default to empty if not found
                         }
                     }
                 } else {
                     $saved_config = []; // Ensure array if no module id
                 }

             } // End if (in_array($handler_slug, ...))
             // --- END Fetching data for remote handlers ---

            // Make data available to the template via GLOBALS
            $GLOBALS['dm_template_saved_config']             = $saved_config; // Pass saved config
            $GLOBALS['dm_template_all_locations']            = $all_locations; // Pass all locations for dropdown
            $GLOBALS['dm_template_selected_location_id']     = $location_id;  // Pass selected location ID
            $GLOBALS['dm_template_enabled_taxonomies']     = $enabled_taxonomies_array; // Pass enabled slugs for conditional rows
            // Pass the FILTERED options arrays
            $GLOBALS['dm_template_filtered_post_type_options'] = $filtered_post_type_options;
            $GLOBALS['dm_template_filtered_category_options']  = $filtered_category_options;
            $GLOBALS['dm_template_filtered_tag_options']       = $filtered_tag_options;
            $GLOBALS['dm_template_filtered_custom_taxonomies'] = $filtered_custom_taxonomies;

            ob_start();
            include $template_path;
            $template_html = ob_get_clean();

            // --- Clean up globals after include --- 
            unset(
                $GLOBALS['dm_template_saved_config'], 
                $GLOBALS['dm_template_all_locations'], 
                $GLOBALS['dm_template_selected_location_id'],
                $GLOBALS['dm_template_enabled_taxonomies'], // Keep this unset? Yes.
                $GLOBALS['dm_template_filtered_post_type_options'],
                $GLOBALS['dm_template_filtered_category_options'],
                $GLOBALS['dm_template_filtered_tag_options'],
                $GLOBALS['dm_template_filtered_custom_taxonomies']
            );
            // --- End cleanup ---

            // Send back the rendered HTML
            $response_data = ['html' => $template_html];
            wp_send_json_success($response_data);

        } else {
            $this->logger?->error('Handler template file not found.', [
                'template_path' => $template_path,
                'handler_type' => $handler_type,
                'handler_slug' => $handler_slug,
            ]);
            wp_send_json_error( ['message' => __('Template not found.', 'data-machine'), 'template' => basename($template_path)], 404 );
        }
        wp_die(); // Important for AJAX handlers
    }

    /**
     * AJAX handler to fetch modules for a given project.
     * MOVED from Project Management Ajax Handler.
     * @since NEXT_VERSION
     */
    public function ajax_get_project_modules() { // Renamed method to fit convention
		// Use the nonce action used during nonce creation
		check_ajax_referer( 'dm_module_config_actions_nonce', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$user_id    = get_current_user_id();

		if ( ! $project_id || ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing project ID or user ID.', 'data-machine' ) ] );
			return;
		}

        try {
            // Use the injected db_modules service (available in this class)
            $modules = $this->db_modules->get_modules_for_project( $project_id, $user_id );
            if ( null === $modules ) { // Check if project exists/permission denied
                // Use logger if available
                $this->logger?->warning('Attempt to fetch modules for invalid/inaccessible project.', [
                    'project_id' => $project_id,
                    'user_id' => $user_id
                ]);
                wp_send_json_error( [ 'message' => __( 'Project not found or permission denied.', 'data-machine' ) ] );
                return;
            }

            // Format data similarly to the original handler (optional simplification)
            $formatted_modules = array_map(
                fn ( $m ) => [
                    'module_id'   => $m->module_id,
                    'module_name' => $m->module_name,
                    // Add other fields needed by JS if necessary
                ],
                $modules ?: [] // Ensure it's an array even if empty
            );

            wp_send_json_success( [ 'modules' => $formatted_modules ] );

        } catch (\Exception $e) {
            $this->logger?->error('Error fetching project modules via AJAX (in Module Config Handler).', [
                'project_id' => $project_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error(['message' => __('Error fetching modules for the project.', 'data-machine')], 500);
        }
	}

}