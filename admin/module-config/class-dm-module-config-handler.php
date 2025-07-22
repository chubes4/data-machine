<?php
/**
 * Handles module creation, selection, and saving operations via admin actions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Module_Handler {

    /** @var Data_Machine_Database_Modules */
    private $db_modules;

    /** @var Data_Machine_Handler_Registry */
    private $handler_registry;

    /** @var Dependency_Injection_Handler_Factory */ // Assuming this is the type
    private $handler_factory;

    /** @var Data_Machine_Logger */
    private $logger;

    /**
     * Initialize the class and set its properties.
     *
     * @param Data_Machine_Database_Modules $db_modules         Injected DB Modules service.
     * @param Data_Machine_Handler_Registry $handler_registry   Injected Handler Registry service.
     * @param Dependency_Injection_Handler_Factory $handler_factory Injected Handler Factory service.
     * @param Data_Machine_Logger $logger             Injected Logger service.
     */
    public function __construct(
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Handler_Registry $handler_registry,
        Dependency_Injection_Handler_Factory $handler_factory, // Assuming type
        Data_Machine_Logger $logger
    ) {
        $this->db_modules = $db_modules;
        $this->handler_registry = $handler_registry;
        $this->handler_factory = $handler_factory;
        $this->logger = $logger;
    }

    /**
     * Initialize hooks for module handlers.
     */
    public function init_hooks() {
        // Keep existing hooks if needed
        // add_action('admin_post_dm_module_selection_update', array($this, 'handle_module_selection_update'));
        // add_action('admin_post_dm_create_module', array($this, 'handle_new_module_creation'));

        // Add hook for handling the main module config save action
        add_action('admin_post_dm_save_module_config', array($this, 'handle_save_request'));
    }

    /**
     * Handle module selection update.
     *
     * @since    0.2.0
     */
    // public function handle_module_selection_update() { ... } // Keep if needed

    /**
     * Handle new module creation.
     *
     * @since    0.2.0
     */
    // public function handle_new_module_creation() { ... } // Keep if needed


    /**
     * Handles saving of module configuration (create/update) triggered by admin_post hook.
     */
    public function handle_save_request() {
        // Security Checks
        // Check the nonce specific to this action
        // Note: admin_post actions use check_admin_referer, not check_ajax_referer
        // The action name here corresponds to the form's _wpnonce_... field name
        check_admin_referer( 'dm_save_module_settings_action', '_wpnonce_dm_save_module' );
        if ( ! current_user_can( 'manage_options' ) ) { // Or appropriate capability check
            wp_die( __( 'Permission denied.', 'data-machine' ) );
        }

        $user_id = get_current_user_id();

        // --- Get Submitted Data ---
        // Get values from the hidden fields synced by JS
        $submitted_module_id_hidden = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : null;
        $submitted_project_id_hidden = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;

        // Check the module select field specifically for 'new'
        $module_select_value = isset($_POST['current_module']) ? sanitize_text_field($_POST['current_module']) : null;

        // Determine the final module ID: prioritize 'new' from select, otherwise use hidden field
        if ($module_select_value === 'new') {
            $submitted_module_id = 'new';
        } else {
            $submitted_module_id = $submitted_module_id_hidden;
            // Ensure it's a positive integer if not 'new'
            if (!is_null($submitted_module_id) && $submitted_module_id !== 'new') {
                $submitted_module_id = absint($submitted_module_id);
                if ($submitted_module_id <= 0) {
                    $submitted_module_id = null; // Treat invalid IDs as missing
                }
            }
        }

        // Use the project ID from the hidden field
        $project_id = $submitted_project_id_hidden;

        // Get other fields (rest are mostly correct)
        $module_name = isset($_POST['module_name']) ? sanitize_text_field($_POST['module_name']) : '';
        $process_prompt = isset($_POST['process_data_prompt']) ? wp_kses_post(wp_unslash($_POST['process_data_prompt'])) : '';
        $fact_check_prompt = isset($_POST['fact_check_prompt']) ? wp_kses_post(wp_unslash($_POST['fact_check_prompt'])) : '';
        $finalize_prompt = isset($_POST['finalize_response_prompt']) ? wp_kses_post(wp_unslash($_POST['finalize_response_prompt'])) : '';
        // START: Add skip_fact_check retrieval and sanitization
        // Checkbox value is '1' if checked, otherwise the hidden field sends '0'
        $skip_fact_check = isset($_POST['skip_fact_check']) ? absint($_POST['skip_fact_check']) : 0;
        // END: Add skip_fact_check retrieval and sanitization
        // Get types from hidden fields (synced by JS)
        $data_source_type_slug = isset($_POST['data_source_type']) ? sanitize_key($_POST['data_source_type']) : 'files';
        $output_type_slug = isset($_POST['output_type']) ? sanitize_key($_POST['output_type']) : 'data_export';
        $submitted_ds_config_all = $_POST['data_source_config'] ?? [];
        $submitted_output_config_all = $_POST['output_config'] ?? [];

        $this->logger->info('[Module Config Save] Starting save process via admin_post.', [
            'submitted_module_id' => $submitted_module_id,
            'project_id' => $project_id,
            'user_id' => $user_id
        ]);

        // --- Validate required fields ---
        // Check if module ID is missing (null or empty string, but allow 'new')
        if ( is_null($submitted_module_id) || ($submitted_module_id !== 'new' && $submitted_module_id <= 0) ) {
            $this->logger->error('[Module Config Save] Error: Missing or invalid module ID.', ['raw_module_id' => $_POST['module_id'] ?? 'not_set', 'raw_current_module' => $_POST['current_module'] ?? 'not_set']);
            $this->logger->add_admin_error(__('Module ID is missing or invalid.', 'data-machine'));
            $this->redirect_after_save('error'); // Redirect even on error
            return;
        }
        if ($submitted_module_id === 'new' && empty($project_id)) {
            $this->logger->error('[Module Config Save] Error: Missing project ID for new module.');
            $this->logger->add_admin_error(__('Project ID is required to create a new module.', 'data-machine'));
            $this->redirect_after_save('error');
            return;
        }
        if (empty($module_name)) {
            $this->logger->error('[Module Config Save] Error: Missing module name.');
            $this->logger->add_admin_error(__('Module name is required.', 'data-machine'));
            $this->redirect_after_save('error');
            return;
        }

        // --- Sanitize Configs ---
        // Use class properties for dependencies
        $final_clean_ds_config = $this->sanitize_input_config($data_source_type_slug, $submitted_ds_config_all);
        $final_clean_output_config = $this->sanitize_output_config($output_type_slug, $submitted_output_config_all);

        // --- Handle Module Create / Update ---
        if ($submitted_module_id === 'new') {
            $this->handle_new_module_create($project_id, $module_name, $process_prompt, $fact_check_prompt, $finalize_prompt, $skip_fact_check, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $user_id);
            // Redirect is handled within handle_new_module_create
            return;
        }

        $this->handle_existing_module_update($submitted_module_id, $user_id, $module_name, $process_prompt, $fact_check_prompt, $finalize_prompt, $skip_fact_check, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $project_id);
        // Redirect is handled within handle_existing_module_update
    }

    // --- Private Helper Methods ---

    /**
     * Sanitizes input configuration using the appropriate handler.
     * Uses class properties for dependencies.
     */
    private function sanitize_input_config($data_source_type_slug, $submitted_ds_config_all) {
        return $this->sanitize_config($data_source_type_slug, 'input', $submitted_ds_config_all);
    }

    /**
     * Sanitizes output configuration using the appropriate handler.
     * Uses class properties for dependencies.
     */
    private function sanitize_output_config($output_type_slug, $submitted_output_config_all) {
        return $this->sanitize_config($output_type_slug, 'output', $submitted_output_config_all);
    }

    /**
     * Handles creation of a new module.
     * Uses class properties for dependencies ($db_modules, $logger).
     */
    private function handle_new_module_create($project_id, $module_name, $process_prompt, $fact_check_prompt, $finalize_prompt, $skip_fact_check, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $user_id) {
        $module_data = array(
            'module_name' => $module_name,
            'process_data_prompt' => $process_prompt,
            'fact_check_prompt' => $fact_check_prompt,
            'finalize_response_prompt' => $finalize_prompt,
            'skip_fact_check' => $skip_fact_check,
            'data_source_type' => $data_source_type_slug,
            'data_source_config' => $final_clean_ds_config,
            'output_type' => $output_type_slug,
            'output_config' => $final_clean_output_config,
            // Add defaults for schedule if needed
             'schedule_interval' => 'project_schedule',
             'schedule_status' => 'active',
        );

        // Use class property db_modules
        $new_module_id = $this->db_modules->create_module($project_id, $module_data);

        if ($new_module_id) {
            $this->logger->info('[Module Config Save] New module created successfully.', ['new_module_id' => $new_module_id, 'project_id' => $project_id]);
            update_user_meta($user_id, 'Data_Machine_current_project', $project_id);
            // Always update active module after create
            update_user_meta($user_id, 'Data_Machine_current_module', $new_module_id);
            // Use class property logger for notices
            $this->logger->add_admin_success(__('New module created successfully.', 'data-machine'));
            $this->redirect_after_save('success', $new_module_id); // Redirect with new module ID
        } else {
            $this->logger->error('[Module Config Save] Failed to create new module in DB.', ['project_id' => $project_id]);
            // Use class property logger for notices
            $this->logger->add_admin_error(__('Failed to create new module.', 'data-machine'));
            $this->redirect_after_save('error');
        }
    }

    /**
     * Handles update of an existing module.
     * Uses class properties for dependencies ($db_modules, $logger).
     */
    private function handle_existing_module_update($submitted_module_id, $user_id, $module_name, $process_prompt, $fact_check_prompt, $finalize_prompt, $skip_fact_check, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $project_id) {
        $module_id_to_update = absint($submitted_module_id);
        // Use class property db_modules
        $existing_module = $this->db_modules->get_module($module_id_to_update); // Pass user ID for ownership check in get_module if implemented there

        // Perform ownership check - IMPORTANT: Assumes get_module doesn't check or we need another way
        // Let's assume db_modules->update_module handles the check internally based on user_id
        if ($existing_module) {
            // Preserve remote_site_info logic (seems specific, keep as is)
            if ($output_type_slug === 'publish_remote') {
                $existing_output_config_for_check = json_decode($existing_module->output_config ?: '', true) ?: array();
                if (isset($existing_output_config_for_check['publish_remote']['remote_site_info'])) {
                    if (!isset($final_clean_output_config['publish_remote'])) {
                        $final_clean_output_config['publish_remote'] = [];
                    }
                    if (!isset($final_clean_output_config['publish_remote']['remote_site_info'])) {
                        $final_clean_output_config['publish_remote']['remote_site_info'] = $existing_output_config_for_check['publish_remote']['remote_site_info'];
                        $this->logger->debug('[Module Config Save] Preserved existing remote_site_info for publish_remote.');
                    }
                }
            }

            // Prepare data for update
            $update_data = array();
            if ($module_name !== $existing_module->module_name) $update_data['module_name'] = $module_name;
            if ($process_prompt !== $existing_module->process_data_prompt) $update_data['process_data_prompt'] = $process_prompt;
            if ($fact_check_prompt !== $existing_module->fact_check_prompt) $update_data['fact_check_prompt'] = $fact_check_prompt;
            if ($finalize_prompt !== $existing_module->finalize_response_prompt) $update_data['finalize_response_prompt'] = $finalize_prompt;
            if ($skip_fact_check !== (int)$existing_module->skip_fact_check) $update_data['skip_fact_check'] = $skip_fact_check;
            if ($data_source_type_slug !== $existing_module->data_source_type) $update_data['data_source_type'] = $data_source_type_slug;
            if ($output_type_slug !== $existing_module->output_type) $update_data['output_type'] = $output_type_slug;

            // --- Revised Config Comparison ---
            // Decode existing configs for comparison
            $existing_ds_config_decoded = json_decode($existing_module->data_source_config ?: '{}', true);
            if (!is_array($existing_ds_config_decoded)) $existing_ds_config_decoded = []; // Ensure array

            $existing_output_config_decoded = json_decode($existing_module->output_config ?: '{}', true);
            if (!is_array($existing_output_config_decoded)) $existing_output_config_decoded = []; // Ensure array

            // Ensure existing configs have the same nesting as the new ones for fair comparison
            // If the decoded array is not empty AND not already nested under the correct slug...
            if (!empty($existing_ds_config_decoded) && !(count($existing_ds_config_decoded) === 1 && key($existing_ds_config_decoded) === $existing_module->data_source_type)) {
                 // ...nest it under the EXISTING type slug for comparison purposes.
                 $existing_ds_config_for_comparison = [$existing_module->data_source_type => $existing_ds_config_decoded];
            } else {
                 // It's empty or already correctly nested
                 $existing_ds_config_for_comparison = $existing_ds_config_decoded;
            }

            if (!empty($existing_output_config_decoded) && !(count($existing_output_config_decoded) === 1 && key($existing_output_config_decoded) === $existing_module->output_type)) {
                 $existing_output_config_for_comparison = [$existing_module->output_type => $existing_output_config_decoded];
            } else {
                 $existing_output_config_for_comparison = $existing_output_config_decoded;
            }

            // Now compare the PHP arrays (new $final_clean_... config is already nested)
            // Use wp_json_encode for a canonical comparison that ignores key order issues etc.
            if (wp_json_encode($final_clean_ds_config) !== wp_json_encode($existing_ds_config_for_comparison)) {
                $update_data['data_source_config'] = $final_clean_ds_config; // Save the NEWLY sanitized (and nested) config
                $this->logger->debug('[Module Config Save] Detected change in data_source_config.');
            }

            if (wp_json_encode($final_clean_output_config) !== wp_json_encode($existing_output_config_for_comparison)) {
                $update_data['output_config'] = $final_clean_output_config; // Save the NEWLY sanitized (and nested) config
                $this->logger->debug('[Module Config Save] Detected change in output_config.');
            }
            // --- End Revised Config Comparison ---

            $updated = false;
            if (!empty($update_data)) {
                $this->logger->debug('[Module Config Save] Attempting DB update.', ['update_data_keys' => array_keys($update_data)]);
                // Use class property db_modules, pass user_id for ownership check
                $updated = $this->db_modules->update_module($module_id_to_update, $update_data, $user_id);
                if ($updated === false) {
                    $this->logger->error('[Module Config Save] Failed to update module in DB.', ['module_id' => $module_id_to_update]);
                    // Use class property logger for notices
                    $this->logger->add_admin_error(__('Failed to update module settings.', 'data-machine'));
                    $this->redirect_after_save('error', $module_id_to_update); // Redirect even on error
                    return;
                }
            }

            // Always update active module after update, regardless of dropdown presence
            update_user_meta($user_id, 'Data_Machine_current_module', $module_id_to_update); // <-- Always set active module
            // Always update project meta as well
            if (isset($_POST['Data_Machine_current_project_selector'])) {
                $selected_project_via_dropdown = absint($_POST['Data_Machine_current_project_selector']);
                update_user_meta($user_id, 'Data_Machine_current_project', $selected_project_via_dropdown);
            } else {
                update_user_meta($user_id, 'Data_Machine_current_project', $project_id);
            }

            // Add notice and redirect
            $message = $updated ? __('Module settings updated successfully.', 'data-machine') : __('Module settings saved (no changes detected).', 'data-machine');
            $notice_type = $updated ? 'success' : 'info';
            $this->logger->add_admin_notice( $notice_type, $message );
            $this->logger->info('[Module Config Save] Update process completed.', ['module_id' => $module_id_to_update, 'changes_made' => (bool)$updated]);
            $this->redirect_after_save($notice_type, $module_id_to_update);

        } else {
            $this->logger->error('[Module Config Save] Invalid module ID or permission denied for update.', ['module_id' => $module_id_to_update, 'user_id' => $user_id]);
            $this->logger->add_admin_error(__('Invalid module selection or permission denied.', 'data-machine'));
            $this->redirect_after_save('error');
        }
    }

    /**
     * Helper function to redirect back to the settings page after save.
     *
     * @param string $notice_type Type of notice ('success', 'error', 'info').
     * @param int|null $module_id Optional module ID to select after redirect.
     */
    private function redirect_after_save(string $notice_type, ?int $module_id = null) {
         $redirect_url = add_query_arg(array(
            'page' => 'dm-module-config', // Your admin page slug
            'dm_notice_type' => $notice_type // Use a different param than logger transient
        ), admin_url('admin.php'));

        // Optionally add module ID to select it after redirect
        if ($module_id !== null) {
            $redirect_url = add_query_arg('module_id', $module_id, $redirect_url);
        }

        wp_safe_redirect(wp_unslash($redirect_url)); // Unslash for safety
        exit;
    }

    /**
     * Generic method to sanitize configuration using the appropriate handler.
     *
     * @param string $handler_type_slug The slug of the handler type (e.g., 'rss', 'publish_local').
     * @param string $config_type       The type of config ('input' or 'output').
     * @param array  $submitted_config_all All submitted configuration data for the config type.
     * @return array The sanitized configuration, nested under the handler slug key.
     */
    private function sanitize_config(string $handler_type_slug, string $config_type, array $submitted_config_all): array {
        $sanitized_config_selected = [];
        $log_prefix = '[Module Config Save]';

        if (isset($submitted_config_all[$handler_type_slug])) {
            $get_handler_class_method = "get_{$config_type}_handler_class";
            $create_handler_method = "create_{$config_type}_handler";
            $handler_interface = ($config_type === 'input')
                ? Data_Machine_Input_Handler_Interface::class
                : Data_Machine_Output_Handler_Interface::class;

            if (!method_exists($this->handler_registry, $get_handler_class_method)) {
                 $this->logger->error("{$log_prefix} Invalid config type '{$config_type}' provided for registry lookup.", ['slug' => $handler_type_slug]);
                 return [ $handler_type_slug => [] ];
            }
            if (!method_exists($this->handler_factory, $create_handler_method)) {
                 $this->logger->error("{$log_prefix} Invalid config type '{$config_type}' provided for factory lookup.", ['slug' => $handler_type_slug]);
                 return [ $handler_type_slug => [] ];
            }

            $handler_class = $this->handler_registry->{$get_handler_class_method}($handler_type_slug);

            if ($handler_class) {
                try {
                    $handler_instance = $this->handler_factory->{$create_handler_method}($handler_type_slug);

                    if ($handler_instance instanceof $handler_interface && method_exists($handler_instance, 'sanitize_settings')) {
                        $current_handler_submitted_config = $submitted_config_all[$handler_type_slug] ?? [];
                        $sanitized_config_selected = $handler_instance->sanitize_settings($current_handler_submitted_config);
                        $this->logger->debug("{$log_prefix} Sanitized {$config_type} config.", ['slug' => $handler_type_slug]);
                    } else {
                        $this->logger->warning("{$log_prefix} {$config_type} handler missing sanitize_settings or wrong type.", ['slug' => $handler_type_slug, 'interface' => $handler_interface]);
                        // Keep $sanitized_config_selected as []
                    }
                } catch (\Exception $e) {
                    $this->logger->error("{$log_prefix} Error getting/sanitizing {$config_type} handler.", ['slug' => $handler_type_slug, 'error' => $e->getMessage()]);
                    // Keep $sanitized_config_selected as []
                }
            } else {
                $this->logger->warning("{$log_prefix} {$config_type} handler class not found.", ['slug' => $handler_type_slug]);
                // Keep $sanitized_config_selected as []
            }
        } else {
            $this->logger->debug("{$log_prefix} No config submitted for selected {$config_type} handler.", ['slug' => $handler_type_slug]);
            // Keep $sanitized_config_selected as []
        }

        // Return the config nested under the slug key, as expected by DB
        return [ $handler_type_slug => $sanitized_config_selected ];
    }

} // End class Data_Machine_Module_Handler