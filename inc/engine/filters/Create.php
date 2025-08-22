<?php
namespace DataMachine\Engine\Filters;

/**
 * Centralized creation for pipelines, flows, steps, jobs via dm_create action
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Data Machine Create Actions Class
 *
 * Handles centralized creation operations through the dm_create action hook.
 * Provides consistent validation, permission checking, and service discovery
 * patterns for all creation types.
 *
 * @since NEXT_VERSION
 */
class Create {

    /**
     * Register dm_create filter hooks.
     */
    public static function register() {
        $instance = new self();
        // dm_create_ filter hooks following dm_update_ pattern (using filters to return values)
        add_filter('dm_create_pipeline', [$instance, 'handle_create_pipeline'], 10, 2);
        add_filter('dm_create_step', [$instance, 'handle_create_step'], 10, 2);
        add_filter('dm_create_flow', [$instance, 'handle_create_flow'], 10, 2);
    }


    /**
     * Handle pipeline creation.
     *
     * @param mixed $default Default value (ignored)
     * @param array $data Creation data
     * @return int|false Pipeline ID on success, false on failure
     */
    public function handle_create_pipeline($default, $data = []) {
        // Permission check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for pipeline creation');
            return false;
        }
        
        // Get required database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            do_action('dm_log', 'error', 'Required database services unavailable for pipeline creation', [
                'pipelines_db' => $db_pipelines ? 'available' : 'missing',
                'flows_db' => $db_flows ? 'available' : 'missing'
            ]);
            return false;
        }
        
        // Use provided pipeline name or fallback
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : 'Pipeline';
        
        // Create pipeline with default configuration
        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => '{}' // Empty JSON object
        ];
        
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            do_action('dm_log', 'error', 'Failed to create pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }
        
        // Auto-create flow (maintains existing behavior)
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => 'Flow',
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['interval' => 'manual'])
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', "Failed to create Draft Flow for pipeline {$pipeline_id}");
        }
        
        do_action('dm_log', 'info', 'Pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id
        ]);
        
        // For AJAX context, provide comprehensive response data for immediate UI updates
        if (wp_doing_ajax()) {
            // Get complete pipeline data
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
            
            wp_send_json_success([
                'message' => __('Pipeline created successfully', 'data-machine'),
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => $pipeline_name,
                'pipeline_data' => $pipeline,
                'existing_flows' => $existing_flows,
                'created_type' => 'pipeline'
            ]);
        }
        
        // For non-AJAX contexts, return pipeline_id
        return $pipeline_id;
    }

    /**
     * Handle step creation.
     *
     * @param mixed $default Default value (ignored)
     * @param array $data Creation data (pipeline_id, step_type required)
     * @return string|false Pipeline step ID on success, false on failure
     */
    public function handle_create_step($default, $data = []) {
        // Permission check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for step creation');
            return false;
        }
        
        // Validate required parameters
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        $step_type = isset($data['step_type']) ? sanitize_text_field(wp_unslash($data['step_type'])) : '';
        
        if ($pipeline_id <= 0) {
            do_action('dm_log', 'error', 'Pipeline ID is required for step creation');
            return false;
        }
        
        if (empty($step_type)) {
            do_action('dm_log', 'error', 'Step type is required for step creation');
            return false;
        }
        
        // Get required database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $db_flows = $all_databases['flows'] ?? null;
        
        if (!$db_pipelines || !$db_flows) {
            do_action('dm_log', 'error', 'Required database services unavailable for step creation', [
                'pipelines_db' => $db_pipelines ? 'available' : 'missing',
                'flows_db' => $db_flows ? 'available' : 'missing'
            ]);
            return false;
        }
        
        // Validate step type exists
        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            do_action('dm_log', 'error', 'Invalid step type for step creation', ['step_type' => $step_type]);
            return false;
        }
        
        // Get current pipeline steps for execution order
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        $next_execution_order = count($current_steps);
        
        // Create new step data
        $new_step = [
            'step_type' => $step_type,
            'execution_order' => $next_execution_order,
            'pipeline_step_id' => wp_generate_uuid4(),
            'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];
        
        // Add to pipeline using associative array with pipeline_step_id as key
        $pipeline_config = [];
        foreach ($current_steps as $step) {
            $pipeline_config[$step['pipeline_step_id']] = $step;
        }
        $pipeline_config[$new_step['pipeline_step_id']] = $new_step;
        
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => json_encode($pipeline_config)
        ]);
        
        if (!$success) {
            do_action('dm_log', 'error', 'Failed to add step to pipeline', [
                'pipeline_id' => $pipeline_id,
                'step_type' => $step_type
            ]);
            return false;
        }
        
        // Sync to all existing flows using centralized action
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            do_action('dm_sync_steps_to_flow', $flow_id, [$new_step], ['context' => 'add_step']);
        }
        
        // Trigger auto-save
        do_action('dm_auto_save', $pipeline_id);
        
        do_action('dm_log', 'info', 'Step created successfully', [
            'pipeline_id' => $pipeline_id,
            'step_type' => $step_type,
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'execution_order' => $next_execution_order
        ]);
        
        // For AJAX context, provide comprehensive response data for immediate UI updates
        if (wp_doing_ajax()) {
            // Get step configuration for comprehensive response
            $all_steps = apply_filters('dm_steps', []);
            $step_config = $all_steps[$step_type] ?? [];
            
            wp_send_json_success([
                'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label'] ?? $step_type),
                'step_type' => $step_type,
                'step_config' => $step_config,
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $new_step['pipeline_step_id'],
                'step_data' => $new_step,
                'created_type' => 'step'
            ]);
        }
        
        // For non-AJAX contexts, return pipeline_step_id
        return $new_step['pipeline_step_id'];
    }

    /**
     * Handle flow creation.
     *
     * @param mixed $default Default value (ignored)
     * @param array $data Creation data (pipeline_id required, flow_name optional)
     * @return int|false Flow ID on success, false on failure
     */
    public function handle_create_flow($default, $data = []) {
        // Permission check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for flow creation');
            return false;
        }
        
        // Validate required pipeline_id
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        if ($pipeline_id <= 0) {
            do_action('dm_log', 'error', 'Pipeline ID is required for flow creation');
            return false;
        }
        
        // Get required database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_flows || !$db_pipelines) {
            do_action('dm_log', 'error', 'Required database services unavailable for flow creation', [
                'flows_db' => $db_flows ? 'available' : 'missing',
                'pipelines_db' => $db_pipelines ? 'available' : 'missing'
            ]);
            return false;
        }
        
        // Validate pipeline exists
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            do_action('dm_log', 'error', 'Pipeline not found for flow creation', ['pipeline_id' => $pipeline_id]);
            return false;
        }
        
        // Use provided flow name or fallback  
        $flow_name = isset($data['flow_name']) ? sanitize_text_field(wp_unslash($data['flow_name'])) : 'Flow';
        
        // Create flow with cascade step sync
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['interval' => 'manual'])
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', 'Failed to create flow', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return false;
        }
        
        // Sync existing pipeline steps to new flow using centralized action
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (!empty($pipeline_steps)) {
            do_action('dm_sync_steps_to_flow', $flow_id, $pipeline_steps, ['context' => 'create_flow']);
        }
        
        do_action('dm_log', 'info', 'Flow created successfully', [
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'synced_steps' => count($pipeline_steps)
        ]);
        
        // For AJAX context, provide comprehensive response data for immediate UI updates
        if (wp_doing_ajax()) {
            // Get complete flow data
            $flow_data = $db_flows->get_flow($flow_id);
            
            wp_send_json_success([
                'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
                'flow_id' => $flow_id,
                'flow_name' => $flow_name,
                'pipeline_id' => $pipeline_id,
                'flow_data' => $flow_data,
                'created_type' => 'flow'
            ]);
        }
        
        // For non-AJAX contexts, return flow_id
        return $flow_id;
    }

}