<?php
/**
 * Handles AJAX requests related to Modules and Processing Jobs.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      0.12.0
 */
namespace DataMachine\Admin\ModuleConfig\Ajax;

use DataMachine\Database\{Modules, Projects, RemoteLocations};
use DataMachine\Admin\RemoteLocations\RemoteLocationService;
use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class ModuleConfigAjax {

    /** @var array Simple cache for site info to avoid redundant JSON decoding */
    private static $site_info_cache = [];

    /**
     * Constructor.
     * Uses filter-based service access for dependencies.
     */
    public function __construct() {

        // Register AJAX hooks
        add_action('wp_ajax_get_module_data', array($this, 'get_module_data_ajax_handler'));
        add_action('wp_ajax_dm_get_module_details', array($this, 'ajax_get_module_details'));
        add_action('wp_ajax_dm_sync_remote_site_details', array($this, 'ajax_sync_remote_site_details'));
        add_action('wp_ajax_dm_get_handler_template', array($this, 'ajax_get_handler_template'));
        add_action('wp_ajax_dm_get_project_modules',  [ $this, 'ajax_get_project_modules' ] );
    }

    /**
     * Get Files handler via factory pattern when needed.
     * 
     * @return object|\WP_Error Files handler instance or WP_Error on failure
     */
    private function get_files_handler() {
        $handler_factory = apply_filters('dm_get_service', null, 'handler_factory');
        if (!$handler_factory) {
            return new \WP_Error('missing_factory', 'Handler factory not available');
        }
        
        return $handler_factory->create_handler('input', 'files');
    }

    /**
	 * AJAX handler to fetch data for a specific module.
     * Moved from Data_Machine class.
	 * @since 0.2.0 (Moved 0.12.0)
	 */
	public function get_module_data_ajax_handler() {
		// Check the nonce action that was actually used to create the nonce value in localization
		check_ajax_referer('dm_module_config_actions_nonce', 'nonce');

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'data-machine')));
			return;
		}

		$module_id = isset($_POST['module_id']) ? absint($_POST['module_id']) : 0;
		$user_id = get_current_user_id();

		if (empty($module_id)) {
			wp_send_json_error(array('message' => 'Module ID missing.'));
			return;
		}

        // Get dependencies from filter-based service access
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        $db_projects = apply_filters('dm_get_service', null, 'db_projects');
        $logger = apply_filters('dm_get_service', null, 'logger');

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
		// wp_send_json_success() already terminates execution
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

        // Get current config for the module if needed
        $current_config = [];
        if ($module_id > 0) {
            $db_modules = apply_filters('dm_get_service', null, 'db_modules');
            $module = $db_modules->get_module($module_id);
            if ($module) {
                $raw_config_string = ($handler_type === 'output') ? $module->output_config : $module->data_source_config;
                $decoded_config = !empty($raw_config_string) ? json_decode(wp_unslash($raw_config_string), true) : [];
                // Extract the config specific to this handler slug
                if (is_array($decoded_config) && isset($decoded_config[$handler_slug])) {
                    $current_config = $decoded_config[$handler_slug];
                }
            }
        }
        
        // Get field definitions via filter system - this is the unified approach
        $fields = apply_filters('dm_handler_settings_fields', [], $handler_type, $handler_slug, $current_config);
        
        // Start output buffering for form content
        ob_start();
        
        if (!empty($fields)) {
            // Render form programmatically from field definitions
            echo wp_kses_post(\DataMachine\Admin\ModuleConfig\FormRenderer::render_form_fields($fields, $current_config, $handler_slug));
        } else {
            echo '<p>' . esc_html__('No configuration options available for this handler.', 'data-machine') . '</p>';
        }
        // Always proceed with data preparation for remote handlers and form output
        {
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
                 // RemoteLocationService class auto-loaded via PSR-4
                 $db_locations = apply_filters('dm_get_service', null, 'db_remote_locations');
                 $remote_location_service = new RemoteLocationService($db_locations);
                 $all_locations = $remote_location_service->get_user_locations_for_js($user_id); 

                 // Fetch specific location's info if ID provided
                 if ($location_id > 0) {
                     $cache_key = "location_{$location_id}";
                     
                     // Check cache first
                     if (isset(self::$site_info_cache[$cache_key])) {
                         $cached_data = self::$site_info_cache[$cache_key];
                         $site_info = $cached_data['site_info'];
                         $enabled_post_types_array = $cached_data['enabled_post_types'];
                         $enabled_taxonomies_array = $cached_data['enabled_taxonomies'];
                     } else {
                         $location_data = $db_locations->get_location($location_id, $user_id, false); 
                         
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
                             
                             // Cache the decoded data
                             self::$site_info_cache[$cache_key] = [
                                 'site_info' => $site_info,
                                 'enabled_post_types' => $enabled_post_types_array,
                                 'enabled_taxonomies' => $enabled_taxonomies_array
                             ];
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
                     $module = $db_modules->get_module($module_id);
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

            // Prepare template data in a secure, encapsulated way
            $template_data = [
                'saved_config' => $saved_config,
                'all_locations' => $all_locations,
                'selected_location_id' => $location_id,
                'enabled_taxonomies' => $enabled_taxonomies_array,
                'filtered_post_type_options' => $filtered_post_type_options,
                'filtered_category_options' => $filtered_category_options,
                'filtered_tag_options' => $filtered_tag_options,
                'filtered_custom_taxonomies' => $filtered_custom_taxonomies
            ];

            $template_html = ob_get_clean();

            // Send back the rendered HTML
            $response_data = ['html' => $template_html];
            wp_send_json_success($response_data);
        }
        // wp_send_json_error() already terminates execution
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
            // Use filter-based service access
            $db_modules = apply_filters('dm_get_service', null, 'db_modules');
            $modules = $db_modules->get_modules_for_project( $project_id, $user_id );
            if ( null === $modules ) { // Check if project exists/permission denied
                // Use logger if available
                $logger = apply_filters('dm_get_service', null, 'logger');
                $logger?->warning('Attempt to fetch modules for invalid/inaccessible project.', [
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
            $logger = apply_filters('dm_get_service', null, 'logger');
            $logger?->error('Error fetching project modules via AJAX (in Module Config Handler).', [
                'project_id' => $project_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error(['message' => __('Error fetching modules for the project.', 'data-machine')], 500);
        }
	}

}