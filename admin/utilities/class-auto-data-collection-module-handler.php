<?php
/**
 * Handles module creation and selection operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Module_Handler {

    /**
     * Static flag to prevent duplicate form processing
     * @var bool
     */
    private static $did_handle_save = false;

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Database Modules class instance.
     * @var Data_Machine_Database_Modules
     */
    private $db_modules;

    /**
     * Initialize the class and set its properties.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
        $this->db_modules = $locator->get('database_modules');
    }

    /**
     * Initialize hooks for module handlers.
     */
    public function init_hooks() {
        // Add hooks for module form submissions
        add_action('admin_post_dm_module_selection_update', array($this, 'handle_module_selection_update'));
        add_action('admin_post_dm_create_module', array($this, 'handle_new_module_creation'));
        
        // Add hook for handling module settings on the main settings page
        add_action('admin_init', array($this, 'handle_module_selection_save'), 20); // Run after settings registration
    }

    /**
     * Handle module selection update.
     *
     * @since    0.2.0
     */
    public function handle_module_selection_update() {
        if (!isset($_POST['dm_module_selection_nonce']) || !wp_verify_nonce($_POST['dm_module_selection_nonce'], 'dm_module_selection_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        $module_id = isset($_POST['current_module']) ? absint($_POST['current_module']) : 0;

        // Verify the user owns this module
        $module = $this->db_modules->get_module($module_id, $user_id);
        if (!$module) {
            wp_die('Invalid module selected');
        }

        update_user_meta($user_id, 'Data_Machine_current_module', $module_id);

        wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_updated=1'));
        exit;
    }

    /**
     * Handle new module creation.
     *
     * @since    0.2.0
     */
    public function handle_new_module_creation() {
        if (!isset($_POST['dm_create_module_nonce']) || !wp_verify_nonce($_POST['dm_create_module_nonce'], 'dm_create_module_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        $module_name = isset($_POST['new_module_name']) ? sanitize_text_field($_POST['new_module_name']) : '';
        $api_key = isset($_POST['new_module_api_key']) ? sanitize_text_field($_POST['new_module_api_key']) : '';

        if (empty($module_name)) {
            wp_die('Module name is required');
        }

        $module_data = array(
            'module_name' => $module_name,
            'openai_api_key' => $api_key,
            'process_data_prompt' => 'Review the provided file and provide an output.',
            'fact_check_prompt' => 'Please fact-check the following response and provide corrections and opportunities for enhancement.',
            'finalize_response_prompt' => 'Please finalize the response based on the fact check and the initial request.'
        );

        $module_id = $this->db_modules->create_module($user_id, $module_data);

        if ($module_id) {
            // Set the new module as current
            update_user_meta($user_id, 'Data_Machine_current_module', $module_id);
            wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_created=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=data-machine-settings-page&module_error=1'));
        }
        exit;
    }
    
    /**
     * Handle saving module selection and data directly on admin_init.
     *
     * @since    0.1.0 (Refactored in 0.8.0 for dynamic fields)
     */
    public function handle_module_selection_save() {
        // Check if our settings form was submitted
        if ( !isset( $_POST['option_page'] ) || $_POST['option_page'] !== 'Data_Machine_settings_group' ) {
            return;
        }

        // Prevent duplicate saves on the same request
        if ( self::$did_handle_save ) {
            return;
        }
        self::$did_handle_save = true;

        // Verify nonce (handled by options.php submission)

        // Check if the module selection field is set
        if ( !isset( $_POST['Data_Machine_current_module'] ) ) {
            return; // Nothing to process if module selection isn't submitted
        }

        $submitted_value = sanitize_text_field( $_POST['Data_Machine_current_module'] );
        $user_id = get_current_user_id();
        // Use the injected locator to get the DB modules instance
        $db_modules = $this->locator->get('database_modules');
        // Use the locator to get the settings fields and handler registry services
        $settings_fields_service = $this->locator->get('settings_fields');
        $handler_registry = $this->locator->get('handler_registry');

        // --- Get General Module Data ---
        $module_name = isset($_POST['module_name']) ? sanitize_text_field($_POST['module_name']) : '';
        $process_prompt = isset($_POST['process_data_prompt']) ? wp_kses_post($_POST['process_data_prompt']) : '';
        $fact_check_prompt = isset($_POST['fact_check_prompt']) ? wp_kses_post($_POST['fact_check_prompt']) : '';
        $finalize_prompt = isset($_POST['finalize_response_prompt']) ? wp_kses_post($_POST['finalize_response_prompt']) : '';

        // --- Get Selected Handler Slugs ---
        $data_source_type_slug = isset($_POST['data_source_type']) ? sanitize_key($_POST['data_source_type']) : 'files';
        $output_type_slug = isset($_POST['output_type']) ? sanitize_key($_POST['output_type']) : 'data';

        // --- Prepare Config Arrays ---
        $data_source_config_to_save = [];
        $output_config_to_save = [];

        // --- Process Submitted Config Data ---
        $submitted_ds_config = $_POST['data_source_config'] ?? [];
        $submitted_output_config = $_POST['output_config'] ?? [];

        // Process Data Source Config
        // Get the handler class dynamically
        $input_handler_class = $handler_registry->get_input_handler_class($data_source_type_slug);
        if ($input_handler_class && isset($submitted_ds_config[$data_source_type_slug])) {
            // Check if the dynamic class exists and has the method
            if (class_exists($input_handler_class) && method_exists($input_handler_class, 'get_settings_fields')) {
                $fields = $settings_fields_service->get_fields_for_handler('input', $data_source_type_slug);
                $current_handler_submitted_config = $submitted_ds_config[$data_source_type_slug];
                $sanitized_handler_config = []; // Temporary storage for this handler's sanitized fields
                foreach ($fields as $key => $config_def) {
                    if (isset($current_handler_submitted_config[$key])) {
                        $value = wp_unslash($current_handler_submitted_config[$key]);

                        // Apply specific sanitization based on key
                        switch ($key) {
                            case 'application_password':
                                $sanitized_handler_config[$key] = trim($value);
                                break;
                            case 'target_site_url':
                                $sanitized_handler_config[$key] = esc_url_raw(untrailingslashit($value));
                                break;
                            case 'rest_order':
                            case 'order':
                                $sanitized_handler_config[$key] = in_array(strtoupper($value), ['ASC', 'DESC']) ? strtoupper($value) : 'DESC';
                                break;
                            case 'rest_category':
                            case 'rest_tag':
                            case 'category':
                            case 'tag':
                                $sanitized_handler_config[$key] = intval($value);
                                break;
                            case 'rest_post_type':
                            case 'rest_post_status':
                            case 'rest_orderby':
                            case 'post_type':
                            case 'orderby':
                                $sanitized_handler_config[$key] = sanitize_key($value);
                                break;
                            case 'endpoint_url':
                            case 'feed_url':
                                $sanitized_handler_config[$key] = esc_url_raw($value);
                                break;
                            case 'item_count':
                            case 'per_page':
                                $sanitized_handler_config[$key] = absint($value);
                                break;
                            case 'subreddit':
                                $sanitized_handler_config[$key] = preg_replace('/[^a-zA-Z0-9_]/', '', $value);
                                break;
                            case 'sort_by':
                                $sanitized_handler_config[$key] = sanitize_key($value);
                                break;
                            case 'search':
                            case 'target_username':
                                $sanitized_handler_config[$key] = sanitize_text_field($value);
                                break;
                            case 'timeframe_limit':
                                $allowed_timeframes = ['all_time', '24_hours', '72_hours', '7_days', '30_days'];
                                $sanitized_handler_config[$key] = in_array($value, $allowed_timeframes) ? $value : 'all_time';
                                break;
                            default:
                                if ($config_def['type'] !== 'button') { // Don't save buttons
                                    $sanitized_handler_config[$key] = sanitize_text_field($value);
                                }
                                break;
                        }
                    }
                }
                // Assign the sanitized config for this handler slug
                $data_source_config_to_save[$data_source_type_slug] = $sanitized_handler_config;
            }
        }

        // Process Output Config
        // Get the handler class dynamically
        $output_handler_class = $handler_registry->get_output_handler_class($output_type_slug);
        if ($output_handler_class && isset($submitted_output_config[$output_type_slug])) {
            // Check if the dynamic class exists and has the method
            if (class_exists($output_handler_class) && method_exists($output_handler_class, 'get_settings_fields')) {
                $fields = $settings_fields_service->get_fields_for_handler('output', $output_type_slug);
                $current_handler_submitted_config = $submitted_output_config[$output_type_slug];
                $sanitized_handler_config = []; // Temporary storage for this handler's sanitized fields
                foreach ($fields as $key => $config) {
                    if (isset($current_handler_submitted_config[$key])) {
                        $value = wp_unslash($current_handler_submitted_config[$key]);

                        // Apply specific sanitization based on key
                        switch ($key) {
                            // Remove cases for fields specific to publish_remote that are now handled by location ID
                            case 'application_password':
                            case 'target_site_url':
                            case 'target_username': // Also remove target_username if it was only for remote publish
                                if ($output_type_slug !== 'publish_remote') {
                                    // Apply original sanitization if NOT publish_remote
                                    if ($key === 'application_password') $sanitized_handler_config[$key] = trim($value);
                                    if ($key === 'target_site_url') $sanitized_handler_config[$key] = esc_url_raw(untrailingslashit($value));
                                    if ($key === 'target_username') $sanitized_handler_config[$key] = sanitize_text_field($value);
                                }
                                // If it IS publish_remote, these keys are ignored / not saved directly
                                break;
                            // Add case for the new remote_location_id field
                            case 'remote_location_id':
                                $sanitized_handler_config[$key] = absint($value); // Sanitize as integer
                                break;
                            case 'selected_local_category_id':
                            case 'selected_remote_category_id':
                            case 'selected_local_tag_id':
                            case 'selected_remote_tag_id':
                                $sanitized_handler_config[$key] = intval($value);
                                break;
                            case 'post_type':
                            case 'post_status':
                            case 'remote_post_status':
                            case 'selected_remote_post_type':
                            case 'post_date_source':
                                $sanitized_handler_config[$key] = sanitize_key($value);
                                break;
                            default:
                                if ($config['type'] !== 'button') { // Don't save buttons
                                    $sanitized_handler_config[$key] = sanitize_text_field($value);
                                }
                                break;
                        }
                    }
                }
                // Assign the sanitized config for this handler slug
                $output_config_to_save[$output_type_slug] = $sanitized_handler_config;
            }
        }

        // --- Handle Module Create / Update ---

        // Handle new module creation
        if ($submitted_value === 'new') {
            if (empty($module_name)) {
                add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Module name cannot be empty when creating a new module.', 'data-machine'), 'error');
                return; // Stop processing
            }

            $module_data = array(
                'module_name' => $module_name,
                'process_data_prompt' => $process_prompt,
                'fact_check_prompt' => $fact_check_prompt,
                'finalize_response_prompt' => $finalize_prompt,
                'data_source_type' => $data_source_type_slug,
                'data_source_config' => $data_source_config_to_save, // Pass prepared config
                'output_type' => $output_type_slug,
                'output_config' => $output_config_to_save, // Pass prepared config
            );

            // Get the selected project ID from the hidden field
            $project_id_for_new_module = isset($_POST['Data_Machine_current_project']) ? absint($_POST['Data_Machine_current_project']) : 0;

            if (empty($project_id_for_new_module)) {
                add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Cannot create module: No project selected.', 'data-machine'), 'error');
                return; // Stop processing
            }

            // TODO: Verify user owns the selected project before creating module in it?
            // $db_projects = new Data_Machine_Database_Projects();
            // $project = $db_projects->get_project($project_id_for_new_module, $user_id);
            // if (!$project) { ... error ... }

            $module_id = $db_modules->create_module($project_id_for_new_module, $module_data); // Pass project_id

            if ($module_id) {
                add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('New module created and selected.', 'data-machine'), 'updated');
                // Also update the current project meta if creating a module successfully
                update_user_meta($user_id, 'Data_Machine_current_project', $project_id_for_new_module);
                update_user_meta($user_id, 'Data_Machine_current_module', $module_id);
            } else {
                add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Failed to create new module.', 'data-machine'), 'error');
            }
            return; // Stop processing after handling 'new'
        }

        // Handle updating an existing module
        $module_id_to_update = absint($submitted_value);
        $existing_module = $db_modules->get_module($module_id_to_update, $user_id);

        if ($existing_module) {
            // Prepare data for potential update
            $update_data = array();
            // General fields
            if ($module_name !== $existing_module->module_name) $update_data['module_name'] = $module_name;
            if ($process_prompt !== $existing_module->process_data_prompt) $update_data['process_data_prompt'] = $process_prompt;
            if ($fact_check_prompt !== $existing_module->fact_check_prompt) $update_data['fact_check_prompt'] = $fact_check_prompt;
            if ($finalize_prompt !== $existing_module->finalize_response_prompt) $update_data['finalize_response_prompt'] = $finalize_prompt;
            if ($data_source_type_slug !== $existing_module->data_source_type) $update_data['data_source_type'] = $data_source_type_slug;
            if ($output_type_slug !== $existing_module->output_type) $update_data['output_type'] = $output_type_slug;

            // Decode existing configs for comparison and preserving remote_site_info
            $existing_ds_config = json_decode($existing_module->data_source_config ?: '', true) ?: array();
            $existing_output_config = json_decode($existing_module->output_config ?: '', true) ?: array();

            // Preserve remote_site_info if it exists in the old config
            if (isset($existing_ds_config['remote_site_info'])) {
                $data_source_config_to_save['remote_site_info'] = $existing_ds_config['remote_site_info'];
            }
            if (isset($existing_output_config['remote_site_info'])) {
                $output_config_to_save['remote_site_info'] = $existing_output_config['remote_site_info'];
            }

            // Compare and add config fields if they have changed
            $new_ds_config_json = wp_json_encode($data_source_config_to_save);
            $existing_ds_config_json = wp_json_encode($existing_ds_config);
            if ($new_ds_config_json !== $existing_ds_config_json) {
                $update_data['data_source_config'] = $data_source_config_to_save;
            }

            $new_output_config_json = wp_json_encode($output_config_to_save);
            $existing_output_config_json = wp_json_encode($existing_output_config);
            if ($new_output_config_json !== $existing_output_config_json) {
                $update_data['output_config'] = $output_config_to_save;
            }

            // Only update the database if there are actual changes
            if (!empty($update_data)) {
                $updated = $db_modules->update_module($module_id_to_update, $update_data, $user_id);
                if ($updated === false) {
                    add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Failed to update module settings.', 'data-machine'), 'error');
                } else {
                    add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Module settings updated.', 'data-machine'), $updated > 0 ? 'updated' : 'info');
                }
            } else {
                add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Module settings saved (no changes detected).', 'data-machine'), 'info');
            }

            // Always save the selected module ID as the user's current choice
            update_user_meta($user_id, 'Data_Machine_current_module', $module_id_to_update);

        } else {
            // Invalid module selected
            add_settings_error('Data_Machine_messages', 'Data_Machine_message', __('Invalid module selection or permission denied.', 'data-machine'), 'error');
        }
    } // End handle_module_selection_save()
} 