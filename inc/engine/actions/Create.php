<?php
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
class DataMachine_Create_Actions {

    /**
     * Register create action hooks using static method.
     *
     * Registers the central dm_create action hook that routes to specific
     * creation handlers based on entity type.
     *
     * @since NEXT_VERSION
     */
    public static function register() {
        $instance = new self();
        // Central creation action hook - eliminates code duplication across all creation types
        add_action('dm_create', [$instance, 'handle_create'], 10, 3);
    }

    /**
     * Handle universal creation operations for all entity types.
     *
     * Central creation handler that eliminates code duplication across pipeline, flow, step, and job creation.
     * Provides consistent validation, error handling, and service discovery patterns.
     *
     * @param string $create_type Type of entity to create (pipeline|flow|step|job)
     * @param array $data Creation data and parameters
     * @param array $context Additional context information
     * @since NEXT_VERSION
     */
    public function handle_create($create_type, $data = [], $context = []) {
        // Universal permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions for create operation.', 'data-machine')]);
            return;
        }
        
        // Validate create type
        $valid_create_types = ['pipeline', 'flow', 'step', 'job'];
        if (!in_array($create_type, $valid_create_types)) {
            wp_send_json_error(['message' => __('Invalid creation type.', 'data-machine')]);
            return;
        }
        
        // Get required database services using filter-based discovery
        $required_services = $this->get_required_database_services($create_type);
        $all_databases = apply_filters('dm_db', []);
        
        foreach ($required_services as $service_key) {
            if (!isset($all_databases[$service_key])) {
                wp_send_json_error(['message' => __('Required database services unavailable.', 'data-machine')]);
                return;
            }
        }
        
        // Route to specific creation handler
        switch ($create_type) {
            case 'pipeline':
                $this->create_pipeline_handler($data, $context, $all_databases);
                break;
            case 'flow':
                $this->create_flow_handler($data, $context, $all_databases);
                break;
            case 'step':
                $this->create_step_handler($data, $context, $all_databases);
                break;
            case 'job':
                $this->create_job_handler($data, $context, $all_databases);
                break;
        }
    }

    /**
     * Get required database services for creation operation.
     *
     * Maps creation types to required database service keys for validation
     * and service discovery before creation operations.
     *
     * @param string $create_type Type of entity being created
     * @return array Array of required database service keys
     * @since NEXT_VERSION
     */
    private function get_required_database_services($create_type) {
        $service_map = [
            'pipeline' => ['pipelines', 'flows'],
            'flow' => ['flows', 'pipelines'],
            'step' => ['pipelines', 'flows'],
            'job' => ['jobs', 'flows', 'pipelines']
        ];
        return $service_map[$create_type] ?? [];
    }

    /**
     * Handle pipeline creation with auto-generated Draft Flow.
     *
     * Creates new pipeline with default configuration and automatically
     * generates a Draft Flow instance to maintain existing behavior patterns.
     *
     * @param array $data Creation data
     * @param array $context Context information
     * @param array $databases Database services
     * @since NEXT_VERSION
     */
    private function create_pipeline_handler($data, $context, $databases) {
        $db_pipelines = $databases['pipelines'];
        $db_flows = $databases['flows'];
        
        // Extract pipeline name with fallback
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : __('Draft Pipeline', 'data-machine');
        
        // Create pipeline with default configuration (empty associative array)
        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => '{}' // Empty JSON object, not array
        ];
        
        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline', 'data-machine')]);
            return;
        }
        
        // Auto-create Draft Flow (maintains existing behavior)
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => __('Draft Flow', 'data-machine'),
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['status' => 'inactive', 'interval' => 'manual'])
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', "Failed to create Draft Flow for pipeline {$pipeline_id}");
        }
        
        // Get complete pipeline data for response
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        $existing_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        
        do_action('dm_log', 'debug', "Created pipeline '{$pipeline_name}' (ID: {$pipeline_id}) with auto-generated Draft Flow");
        
        wp_send_json_success([
            'message' => __('Pipeline created successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'pipeline_data' => $pipeline,
            'existing_flows' => $existing_flows,
            'created_type' => 'pipeline'
        ]);
    }

    /**
     * Handle flow creation with pipeline step synchronization.
     *
     * Creates new flow for existing pipeline and synchronizes any existing
     * pipeline steps to the new flow configuration.
     *
     * @param array $data Creation data
     * @param array $context Context information
     * @param array $databases Database services
     * @since NEXT_VERSION
     */
    private function create_flow_handler($data, $context, $databases) {
        $db_flows = $databases['flows'];
        $db_pipelines = $databases['pipelines'];
        
        // Validate required pipeline_id
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        if ($pipeline_id <= 0) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
            return;
        }
        
        // Validate pipeline exists
        $pipeline = apply_filters('dm_get_pipelines', [], $pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
            return;
        }
        
        // Generate flow name
        $pipeline_name = $pipeline['pipeline_name'];
        $existing_flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        $flow_number = count($existing_flows) + 1;
        $flow_name = isset($data['flow_name']) ? sanitize_text_field(wp_unslash($data['flow_name'])) : sprintf(__('%s Flow %d', 'data-machine'), $pipeline_name, $flow_number);
        
        // Create flow with cascade step sync
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['status' => 'inactive', 'interval' => 'manual'])
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Failed to create flow', 'data-machine')]);
            return;
        }
        
        // Sync existing pipeline steps to new flow using centralized action
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (!empty($pipeline_steps)) {
            do_action('dm_sync_steps_to_flow', $flow_id, $pipeline_steps, ['context' => 'create_flow']);
        }
        
        $flow = $db_flows->get_flow($flow_id);
        
        do_action('dm_log', 'debug', "Created flow '{$flow_name}' (ID: {$flow_id}) for pipeline {$pipeline_id} with " . count($pipeline_steps) . " synced steps");
        
        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'flow_data' => $flow,
            'created_type' => 'flow'
        ]);
    }

    /**
     * Handle step creation with flow synchronization.
     *
     * Creates new step in pipeline configuration and synchronizes the addition
     * across all associated flows. Validates step type and handles execution order.
     *
     * @param array $data Creation data
     * @param array $context Context information
     * @param array $databases Database services
     * @since NEXT_VERSION
     */
    private function create_step_handler($data, $context, $databases) {
        $db_pipelines = $databases['pipelines'];
        $db_flows = $databases['flows'];
        
        // Validate required parameters
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        $step_type = isset($data['step_type']) ? sanitize_text_field(wp_unslash($data['step_type'])) : '';
        
        if ($pipeline_id <= 0) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
            return;
        }
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
            return;
        }
        
        // Validate step type exists
        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
            return;
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
            wp_send_json_error(['message' => __('Failed to add step to pipeline', 'data-machine')]);
            return;
        }
        
        // Sync to all existing flows using centralized action
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            do_action('dm_sync_steps_to_flow', $flow_id, [$new_step], ['context' => 'add_step']);
        }
        
        // Trigger auto-save
        do_action('dm_auto_save', $pipeline_id);
        
        do_action('dm_log', 'debug', "Created step '{$step_type}' (ID: {$new_step['pipeline_step_id']}) for pipeline {$pipeline_id}, synced to " . count($flows) . " flows");
        
        wp_send_json_success([
            'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label']),
            'step_type' => $step_type,
            'step_config' => $step_config,
            'pipeline_id' => $pipeline_id,
            'execution_order' => $new_step['execution_order'],
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'step_data' => $new_step,
            'created_type' => 'step'
        ]);
    }

    /**
     * Handle ultra-simple job creation.
     *
     * Creates job with minimal data (pipeline_id + flow_id only).
     * dm_execute_step action hook handles all complexity at runtime.
     *
     * @param array $data Creation data
     * @param array $context Context information (unused)
     * @param array $databases Database services
     * @since NEXT_VERSION
     */
    private function create_job_handler($data, $context, $databases) {
        // Extract minimal required data
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        $flow_id = isset($data['flow_id']) ? (int)sanitize_text_field(wp_unslash($data['flow_id'])) : 0;
        
        if ($pipeline_id <= 0 || $flow_id <= 0) {
            wp_send_json_error(['message' => __('Pipeline ID and Flow ID are required', 'data-machine')]);
            return;
        }
        
        // Simple existence check
        $db_flows = $databases['flows'];
        if (!$db_flows->get_flow($flow_id)) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
            return;
        }
        
        // Create job with minimal data - dm_execute_step action hook handles everything else
        $db_jobs = $databases['jobs'];
        $job_id = $db_jobs->create_job([
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id
        ]);
        
        if (!$job_id) {
            wp_send_json_error(['message' => __('Failed to create job', 'data-machine')]);
            return;
        }
        
        // Find first step (execution_order = 0) using centralized filter
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);
        
        $first_flow_step_id = null;
        foreach ($flow_config as $flow_step_id => $config) {
            if (($config['execution_order'] ?? -1) === 0) {
                $first_flow_step_id = $flow_step_id;
                break;
            }
        }
        
        if (!$first_flow_step_id) {
            wp_send_json_error(['message' => __('No first step found in flow configuration', 'data-machine')]);
            return;
        }
        
        // Schedule execution with flow_step_id
        do_action('dm_schedule_next_step', $job_id, $first_flow_step_id, []);
        
        do_action('dm_log', 'debug', "Created job {$job_id} for pipeline {$pipeline_id}, flow {$flow_id}");
        
        wp_send_json_success([
            'job_id' => $job_id,
            'message' => __('Job started successfully', 'data-machine'),
            'created_type' => 'job'
        ]);
    }

}