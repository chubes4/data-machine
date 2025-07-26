<?php
/**
 * Handles module creation, selection, and saving operations via admin actions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\ModuleConfig;

use DataMachine\Database\Modules;
use DataMachine\Handlers\HandlerFactory;
use DataMachine\Constants;
use DataMachine\Helpers\Logger;
use DataMachine\Engine\PipelineStepRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ModuleConfigHandler {

    /**
     * Initialize the class - uses filter-based service access.
     * No constructor dependencies - pure filter-based architecture.
     */
    public function __construct() {
        // Parameter-less constructor - all services accessed via filters
    }

    /**
     * Initialize hooks for module handlers.
     */
    public function init_hooks() {
        // Add hook for handling the main module config save action
        add_action('admin_post_dm_save_module_config', array($this, 'handle_save_request'));
    }



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
            wp_die( esc_html__( 'Permission denied.', 'data-machine' ) );
        }

        $user_id = get_current_user_id();

        // --- Get Submitted Data ---
        // Get values from the hidden fields synced by JS
        $submitted_module_id_hidden = isset($_POST['module_id']) ? sanitize_text_field(wp_unslash($_POST['module_id'])) : null;
        $submitted_project_id_hidden = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;

        // Check the module select field specifically for 'new'
        $module_select_value = isset($_POST['current_module']) ? sanitize_text_field(wp_unslash($_POST['current_module'])) : null;

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

        // Get other fields
        $module_name = isset($_POST['module_name']) ? sanitize_text_field(wp_unslash($_POST['module_name'])) : '';
        
        // Process dynamic prompt fields from pipeline steps
        $prompt_data = $this->process_dynamic_prompt_fields($_POST);
        // Get types from hidden fields (synced by JS)
        $data_source_type_slug = isset($_POST['data_source_type']) ? sanitize_key($_POST['data_source_type']) : 'files';
        $output_type_slug = isset($_POST['output_type']) ? sanitize_key($_POST['output_type']) : 'publish_local';
        $submitted_ds_config_all = isset($_POST['data_source_config']) ? wp_unslash($_POST['data_source_config']) : [];
        $submitted_output_config_all = isset($_POST['output_config']) ? wp_unslash($_POST['output_config']) : [];


        // --- Validate required fields ---
        $logger = apply_filters('dm_get_service', null, 'logger');
        
        $logger->info('[Module Config Save] Starting save process via admin_post.', [
            'submitted_module_id' => $submitted_module_id,
            'project_id' => $project_id,
            'user_id' => $user_id
        ]);
        
        // Check if module ID is missing (null or empty string, but allow 'new')
        if ( is_null($submitted_module_id) || ($submitted_module_id !== 'new' && $submitted_module_id <= 0) ) {
            $logger->error('[Module Config Save] Error: Missing or invalid module ID.', ['raw_module_id' => isset($_POST['module_id']) ? sanitize_text_field(wp_unslash($_POST['module_id'])) : 'not_set', 'raw_current_module' => isset($_POST['current_module']) ? sanitize_text_field(wp_unslash($_POST['current_module'])) : 'not_set']);
            $logger->add_admin_error(__('Module ID is missing or invalid.', 'data-machine'));
            $this->redirect_after_save('error', null, $project_id); // Redirect even on error
            return;
        }
        if ($submitted_module_id === 'new' && empty($project_id)) {
            $logger->error('[Module Config Save] Error: Missing project ID for new module.');
            $logger->add_admin_error(__('Project ID is required to create a new module.', 'data-machine'));
            $this->redirect_after_save('error', null, $project_id);
            return;
        }
        if (empty($module_name)) {
            $logger->error('[Module Config Save] Error: Missing module name.');
            $logger->add_admin_error(__('Module name is required.', 'data-machine'));
            $this->redirect_after_save('error', null, $project_id);
            return;
        }

        // --- Sanitize Configs ---
        
        // Use filter-based service access - with validation error handling
        try {
            $final_clean_ds_config = $this->sanitize_input_config($data_source_type_slug, $submitted_ds_config_all);
        } catch (\InvalidArgumentException $e) {
            $logger->add_admin_error($e->getMessage());
            $this->redirect_after_save('error', null, $project_id);
            return;
        }
        
        try {
            $final_clean_output_config = $this->sanitize_output_config($output_type_slug, $submitted_output_config_all);
        } catch (\InvalidArgumentException $e) {
            $logger->add_admin_error($e->getMessage());
            $this->redirect_after_save('error', null, $project_id);
            return;
        }
        

        // --- Handle Module Create / Update ---
        if ($submitted_module_id === 'new') {
            $this->handle_new_module_create($project_id, $module_name, $prompt_data, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $user_id);
            // Redirect is handled within handle_new_module_create
            return;
        }

        $this->handle_existing_module_update($submitted_module_id, $user_id, $module_name, $prompt_data, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $project_id);
        // Redirect is handled within handle_existing_module_update
    }

    // --- Private Helper Methods ---

    /**
     * Process dynamic prompt fields from pipeline steps.
     *
     * @param array $post_data The POST data array.
     * @return array Array of processed prompt field data.
     */
    private function process_dynamic_prompt_fields(array $post_data): array {
        $pipeline_step_registry = apply_filters('dm_get_service', null, 'pipeline_step_registry');
        $logger = apply_filters('dm_get_service', null, 'logger');
        
        if (!$pipeline_step_registry) {
            $logger->error('[Module Config Save] PipelineStepRegistry service not available.');
            return [];
        }
        
        $prompt_fields = $pipeline_step_registry->get_all_prompt_fields();
        $processed_data = [];
        
        foreach ($prompt_fields as $step_name => $step_data) {
            $step_fields = $step_data['prompt_fields'] ?? [];
            
            foreach ($step_fields as $field_name => $field_config) {
                if (isset($post_data[$field_name])) {
                    $processed_data[$field_name] = $this->sanitize_prompt_field_value(
                        $post_data[$field_name], 
                        $field_config
                    );
                } else {
                    // Set default value if field not present
                    $processed_data[$field_name] = $field_config['default'] ?? '';
                }
            }
        }
        
        return $processed_data;
    }
    
    /**
     * Sanitize a prompt field value based on its configuration.
     *
     * @param mixed $value The field value to sanitize.
     * @param array $field_config The field configuration.
     * @return mixed The sanitized value.
     */
    private function sanitize_prompt_field_value($value, array $field_config) {
        $field_type = $field_config['type'] ?? 'text';
        
        switch ($field_type) {
            case 'textarea':
                return wp_kses_post(wp_unslash($value));
            case 'checkbox':
                return absint($value);
            case 'number':
                return absint($value);
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Sanitizes input configuration using the appropriate handler.
     * Uses filter-based service access.
     */
    private function sanitize_input_config($data_source_type_slug, $submitted_ds_config_all) {
        return $this->sanitize_config($data_source_type_slug, 'input', $submitted_ds_config_all);
    }

    /**
     * Sanitizes output configuration using the appropriate handler.
     * Uses filter-based service access.
     */
    private function sanitize_output_config($output_type_slug, $submitted_output_config_all) {
        return $this->sanitize_config($output_type_slug, 'output', $submitted_output_config_all);
    }

    /**
     * Handles creation of a new module.
     * Uses filter-based service access.
     */
    private function handle_new_module_create($project_id, $module_name, $prompt_data, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $user_id) {
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        $logger = apply_filters('dm_get_service', null, 'logger');
        
        // Build module data with dynamic prompt fields
        $module_data = array_merge(
            [
                'module_name' => $module_name,
                'data_source_type' => $data_source_type_slug,
                'data_source_config' => $final_clean_ds_config,
                'output_type' => $output_type_slug,
                'output_config' => $final_clean_output_config,
                // Add defaults for schedule if needed
                'schedule_interval' => 'project_schedule',
                'schedule_status' => 'active',
            ],
            $prompt_data // Add all dynamic prompt fields
        );

        // Use filter-based service access
        $new_module_id = $db_modules->create_module($project_id, $module_data);

        if ($new_module_id) {
            $logger->info('[Module Config Save] New module created successfully.', ['new_module_id' => $new_module_id, 'project_id' => $project_id]);
            $logger->add_admin_success(__('New module created successfully.', 'data-machine'));
            $this->redirect_after_save('success', $new_module_id, $project_id); // Redirect with new module ID and project ID
        } else {
            $logger->error('[Module Config Save] Failed to create new module in DB.', ['project_id' => $project_id]);
            $logger->add_admin_error(__('Failed to create new module.', 'data-machine'));
            $this->redirect_after_save('error', null, $project_id);
        }
    }

    /**
     * Handles update of an existing module.
     * Uses filter-based service access.
     */
    private function handle_existing_module_update($submitted_module_id, $user_id, $module_name, $prompt_data, $data_source_type_slug, $final_clean_ds_config, $output_type_slug, $final_clean_output_config, $project_id) {
        $db_modules = apply_filters('dm_get_service', null, 'db_modules');
        $logger = apply_filters('dm_get_service', null, 'logger');
        
        $module_id_to_update = absint($submitted_module_id);
        $existing_module = $db_modules->get_module($module_id_to_update); // Pass user ID for ownership check in get_module if implemented there

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
                        $logger->debug('[Module Config Save] Preserved existing remote_site_info for publish_remote.');
                    }
                }
            }

            // Prepare data for update
            $update_data = array();
            if ($module_name !== $existing_module->module_name) $update_data['module_name'] = $module_name;
            
            // Process dynamic prompt fields
            foreach ($prompt_data as $field_name => $field_value) {
                $existing_value = $existing_module->$field_name ?? '';
                
                // Handle different data types for comparison
                if (is_numeric($field_value) && is_numeric($existing_value)) {
                    if ((int)$field_value !== (int)$existing_value) {
                        $update_data[$field_name] = $field_value;
                    }
                } else {
                    if ($field_value !== $existing_value) {
                        $update_data[$field_name] = $field_value;
                    }
                }
            }
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
                $logger->debug('[Module Config Save] Detected change in data_source_config.');
            }

            if (wp_json_encode($final_clean_output_config) !== wp_json_encode($existing_output_config_for_comparison)) {
                $update_data['output_config'] = $final_clean_output_config; // Save the NEWLY sanitized (and nested) config
                $logger->debug('[Module Config Save] Detected change in output_config.');
            }
            // --- End Revised Config Comparison ---

            $updated = false;
            if (!empty($update_data)) {
                $logger->debug('[Module Config Save] Attempting DB update.', ['update_data_keys' => array_keys($update_data)]);
                $updated = $db_modules->update_module($module_id_to_update, $update_data, $user_id);
                if ($updated === false) {
                    $logger->error('[Module Config Save] Failed to update module in DB.', ['module_id' => $module_id_to_update]);
                    $logger->add_admin_error(__('Failed to update module settings.', 'data-machine'));
                    $this->redirect_after_save('error', $module_id_to_update, $project_id); // Redirect even on error
                    return;
                }
            }

            // Add notice and redirect
            $message = $updated ? __('Module settings updated successfully.', 'data-machine') : __('Module settings saved (no changes detected).', 'data-machine');
            $notice_type = $updated ? 'success' : 'info';
            $logger->add_admin_notice( $notice_type, $message );
            $logger->info('[Module Config Save] Update process completed.', ['module_id' => $module_id_to_update, 'changes_made' => (bool)$updated]);
            $this->redirect_after_save($notice_type, $module_id_to_update, $project_id);

        } else {
            $logger->error('[Module Config Save] Invalid module ID or permission denied for update.', ['module_id' => $module_id_to_update, 'user_id' => $user_id]);
            $logger->add_admin_error(__('Invalid module selection or permission denied.', 'data-machine'));
            $this->redirect_after_save('error', null, $project_id);
        }
    }

    /**
     * Helper function to redirect back to the settings page after save.
     *
     * @param string $notice_type Type of notice ('success', 'error', 'info').
     * @param int|null $module_id Optional module ID to select after redirect.
     * @param int|null $project_id Optional project ID to select after redirect.
     */
    private function redirect_after_save(string $notice_type, ?int $module_id = null, ?int $project_id = null) {
         $redirect_url = add_query_arg(array(
            'page' => 'dm-module-config', // Your admin page slug
            'dm_notice_type' => $notice_type // Use a different param than logger transient
        ), admin_url('admin.php'));

        // Add project ID to maintain project selection
        if ($project_id !== null) {
            $redirect_url = add_query_arg('project_id', $project_id, $redirect_url);
        }

        // Add module ID to maintain module selection  
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
        $handler_factory = apply_filters('dm_get_service', null, 'handler_factory');
        $logger = apply_filters('dm_get_service', null, 'logger');
        
        $sanitized_config_selected = [];
        $log_prefix = '[Module Config Save]';

        if (isset($submitted_config_all[$handler_type_slug])) {
            if ($config_type === 'input') {
                $handler_class = Constants::get_input_handler_class($handler_type_slug);
            } elseif ($config_type === 'output') {
                $handler_class = Constants::get_output_handler_class($handler_type_slug);
            } else {
                $logger->error("{$log_prefix} Invalid config type '{$config_type}' provided.", ['slug' => $handler_type_slug]);
                return [ $handler_type_slug => [] ];
            }

            if ($handler_class) {
                try {
                    // Use the unified factory method: create_handler($handler_type, $handler_slug)
                    $handler_instance = $handler_factory->create_handler($config_type, $handler_type_slug);

                    if (method_exists($handler_instance, 'sanitize_settings')) {
                        $current_handler_submitted_config = $submitted_config_all[$handler_type_slug] ?? [];
                        $sanitized_config_selected = $handler_instance->sanitize_settings($current_handler_submitted_config);
                        $logger->debug("{$log_prefix} Sanitized {$config_type} config.", ['slug' => $handler_type_slug]);
                    } else {
                        $logger->warning("{$log_prefix} {$config_type} handler missing sanitize_settings method.", ['slug' => $handler_type_slug, 'handler_class' => $handler_class]);
                        // Keep $sanitized_config_selected as []
                    }
                } catch (\InvalidArgumentException $e) {
                    // Validation error - re-throw with additional context for main handler
                    $logger->error("{$log_prefix} Validation error in {$config_type} handler.", ['slug' => $handler_type_slug, 'error' => esc_html($e->getMessage())]);
                    throw new \InvalidArgumentException('Validation error occurred');
                } catch (\Exception $e) {
                    $logger->error("{$log_prefix} Error getting/sanitizing {$config_type} handler.", ['slug' => $handler_type_slug, 'error' => esc_html($e->getMessage())]);
                    // Keep $sanitized_config_selected as []
                }
            } else {
                $logger->warning("{$log_prefix} {$config_type} handler class not found.", ['slug' => $handler_type_slug]);
                // Keep $sanitized_config_selected as []
            }
        } else {
            $logger->debug("{$log_prefix} No config submitted for selected {$config_type} handler.", ['slug' => $handler_type_slug]);
            // Keep $sanitized_config_selected as []
        }

        // Return the config nested under the slug key, as expected by DB
        return [ $handler_type_slug => $sanitized_config_selected ];
    }

} // End class \DataMachine\Admin\ModuleConfig\ModuleConfigHandler