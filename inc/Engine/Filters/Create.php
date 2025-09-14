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
        add_filter('dm_create_pipeline_from_template', [$instance, 'handle_create_pipeline_from_template'], 10, 3);
        add_filter('dm_create_step', [$instance, 'handle_create_step'], 10, 2);
        add_filter('dm_create_flow', [$instance, 'handle_create_flow'], 10, 2);
        add_filter('dm_duplicate_flow', [$instance, 'handle_duplicate_flow'], 10, 2);
    }


    /**
     * Handle pipeline creation with support for both simple and complete modes.
     *
     * Simple Mode: Creates empty pipeline with default flow
     * Complete Mode: Creates pipeline with predefined steps and configuration
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

        // Detect creation mode based on input data structure
        $is_complete_mode = isset($data['steps']) && is_array($data['steps']) && !empty($data['steps']);

        if ($is_complete_mode) {
            do_action('dm_log', 'debug', 'Creating pipeline in complete mode', [
                'pipeline_name' => $data['pipeline_name'] ?? 'Pipeline',
                'steps_count' => count($data['steps'])
            ]);
            return $this->create_complete_pipeline($data, $db_pipelines, $db_flows);
        } else {
            do_action('dm_log', 'debug', 'Creating pipeline in simple mode', [
                'pipeline_name' => $data['pipeline_name'] ?? 'Pipeline'
            ]);
            return $this->create_simple_pipeline($data, $db_pipelines, $db_flows);
        }
    }

    /**
     * Create simple pipeline with empty configuration (existing behavior).
     *
     * @param array $data Creation data
     * @param object $db_pipelines Pipeline database service
     * @param object $db_flows Flow database service
     * @return int|false Pipeline ID on success, false on failure
     */
    private function create_simple_pipeline($data, $db_pipelines, $db_flows) {
        // Use provided pipeline name or fallback
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : 'Pipeline';

        // Create pipeline with empty configuration
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
        $flow_config = isset($data['flow_config']) ? $data['flow_config'] : [];
        $flow_name = $flow_config['flow_name'] ?? 'Flow';
        $scheduling_config = $flow_config['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode($scheduling_config)
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', "Failed to create flow for pipeline {$pipeline_id}");
        }

        return $this->finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows);
    }

    /**
     * Create complete pipeline with predefined steps and configuration.
     *
     * @param array $data Creation data with steps
     * @param object $db_pipelines Pipeline database service
     * @param object $db_flows Flow database service
     * @return int|false Pipeline ID on success, false on failure
     */
    private function create_complete_pipeline($data, $db_pipelines, $db_flows) {
        // Use provided pipeline name or fallback
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : 'Pipeline';
        $steps = $data['steps'];

        // Validate step types exist
        $all_steps = apply_filters('dm_steps', []);
        foreach ($steps as $step) {
            $step_type = $step['step_type'] ?? '';
            if (!isset($all_steps[$step_type])) {
                do_action('dm_log', 'error', 'Invalid step type in complete pipeline creation', [
                    'step_type' => $step_type,
                    'available_types' => array_keys($all_steps)
                ]);
                return false;
            }
        }

        // Generate UUIDs and build complete pipeline configuration
        $pipeline_config = [];
        foreach ($steps as $step) {
            $pipeline_step_id = wp_generate_uuid4();
            $step_type = $step['step_type'];
            $step_config = $all_steps[$step_type] ?? [];

            $pipeline_config[$pipeline_step_id] = [
                'step_type' => $step_type,
                'execution_order' => $step['execution_order'] ?? 0,
                'pipeline_step_id' => $pipeline_step_id,
                'label' => $step['label'] ?? $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
            ];

            // Add AI-specific configuration if provided
            if ($step_type === 'ai') {
                if (isset($step['provider'])) {
                    $pipeline_config[$pipeline_step_id]['provider'] = $step['provider'];
                }
                if (isset($step['model'])) {
                    $pipeline_config[$pipeline_step_id]['model'] = $step['model'];
                    $pipeline_config[$pipeline_step_id]['providers'] = [
                        $step['provider'] ?? 'openai' => ['model' => $step['model']]
                    ];
                }
                if (isset($step['system_prompt'])) {
                    $pipeline_config[$pipeline_step_id]['system_prompt'] = sanitize_textarea_field($step['system_prompt']);
                }
            }
        }

        // Create pipeline with complete configuration
        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => json_encode($pipeline_config)
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            do_action('dm_log', 'error', 'Failed to create complete pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }

        // Create flow with step synchronization
        $flow_config_data = isset($data['flow_config']) ? $data['flow_config'] : [];
        $flow_name = $flow_config_data['flow_name'] ?? 'Flow';
        $scheduling_config = $flow_config_data['scheduling_config'] ?? ['interval' => 'manual'];

        // Create initial flow
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]), // Start empty, will be populated below
            'scheduling_config' => json_encode($scheduling_config)
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', "Failed to create flow for complete pipeline {$pipeline_id}");
            return false;
        }

        // Build complete flow configuration with proper flow step IDs
        $flow_config = [];
        foreach ($pipeline_config as $pipeline_step_id => $step_config) {
            $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'step_type' => $step_config['step_type'],
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'execution_order' => $step_config['execution_order'],
                'handler' => null
            ];
        }

        // Update flow with complete configuration
        $update_success = $db_flows->update_flow($flow_id, [
            'flow_config' => json_encode($flow_config)
        ]);

        if (!$update_success) {
            do_action('dm_log', 'error', "Failed to update flow config for complete pipeline {$pipeline_id}");
            // Continue anyway, pipeline was created successfully
        }

        // Configure handlers if provided - maintain execution order mapping
        $step_order_map = [];
        foreach ($pipeline_config as $pipeline_step_id => $step_config) {
            $execution_order = $step_config['execution_order'];
            $step_order_map[$execution_order] = $pipeline_step_id;
        }

        foreach ($data['steps'] as $step) {
            if (isset($step['handler']) || isset($step['handler_config'])) {
                $execution_order = $step['execution_order'] ?? 0;
                $pipeline_step_id = $step_order_map[$execution_order] ?? null;

                if ($pipeline_step_id) {
                    $flow_step_id = apply_filters('dm_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
                    $handler = $step['handler'] ?? null;
                    $handler_config = $step['handler_config'] ?? [];

                    if ($handler) {
                        do_action('dm_update_flow_handler', $flow_step_id, $handler, $handler_config);
                    }
                }
            }
        }

        do_action('dm_log', 'info', 'Complete pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'steps_count' => count($pipeline_config)
        ]);

        return $this->finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows, 'complete');
    }

    /**
     * Finalize pipeline creation with caching, logging, and AJAX response.
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param int $flow_id Flow ID
     * @param object $db_pipelines Pipeline database service
     * @param object $db_flows Flow database service
     * @param string $creation_type Creation type for response
     * @return int Pipeline ID
     */
    private function finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows, $creation_type = 'simple') {
        do_action('dm_log', 'info', 'Pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'creation_type' => $creation_type
        ]);

        // Clear relevant caches after successful creation to ensure UI shows new pipeline
        \DataMachine\Core\Database\DatabaseCache::clear_cache('dm_all_pipelines');
        \DataMachine\Core\Database\DatabaseCache::clear_cache_pattern('dm_pipeline_*');

        // Trigger action for cache invalidation listeners
        do_action('dm_pipeline_created', $pipeline_id);

        // For AJAX context, provide comprehensive response data for immediate UI updates
        if (wp_doing_ajax()) {
            // Get complete pipeline data
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);

            $message = $creation_type === 'complete'
                ? __('Complete pipeline created successfully', 'data-machine')
                : __('Pipeline created successfully', 'data-machine');

            wp_send_json_success([
                'message' => $message,
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => $pipeline_name,
                'pipeline_data' => $pipeline,
                'existing_flows' => $existing_flows,
                'created_type' => 'pipeline',
                'creation_mode' => $creation_type
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
                /* translators: %s: Step type or label */
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
        
        // Increment existing flow orders to place new flow at top
        $increment_success = $db_flows->increment_existing_flow_orders($pipeline_id);
        if (!$increment_success) {
            do_action('dm_log', 'error', 'Failed to increment existing flow orders for new flow creation', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return false;
        }
        
        // Create flow with display_order = 0 (top position)
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['interval' => 'manual']),
            'display_order' => 0 // New flows always appear at top
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
                /* translators: %s: Flow name */
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

    /**
     * Handle flow duplication.
     *
     * @param mixed $default Default value (ignored)
     * @param int $source_flow_id Source flow ID to duplicate
     * @return int|false New flow ID on success, false on failure
     */
    public function handle_duplicate_flow($default, int $source_flow_id) {
        // Permission check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for flow duplication');
            return false;
        }

        // Get required database services using filter-based discovery
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('dm_log', 'error', 'Required database services unavailable for flow duplication', [
                'flows_db' => $db_flows ? 'available' : 'missing'
            ]);
            return false;
        }

        // Get source flow data
        $source_flow = $db_flows->get_flow($source_flow_id);
        if (!$source_flow) {
            do_action('dm_log', 'error', 'Source flow not found for duplication', ['source_flow_id' => $source_flow_id]);
            return false;
        }

        // Create duplicated flow name
        /* translators: %s: Original flow name */
        $duplicate_flow_name = sprintf(__('Copy of %s', 'data-machine'), $source_flow['flow_name']);

        // Increment existing flow orders to place new flow at top
        $increment_success = $db_flows->increment_existing_flow_orders($source_flow['pipeline_id']);
        if (!$increment_success) {
            do_action('dm_log', 'error', 'Failed to increment existing flow orders for flow duplication', [
                'source_flow_id' => $source_flow_id,
                'pipeline_id' => $source_flow['pipeline_id']
            ]);
            return false;
        }

        // Create new flow data with duplicated configuration
        $flow_data = [
            'pipeline_id' => $source_flow['pipeline_id'], // Same pipeline
            'flow_name' => $duplicate_flow_name,
            'flow_config' => wp_json_encode($source_flow['flow_config']), // Copy exact configuration
            'scheduling_config' => wp_json_encode(['interval' => 'manual']), // Set to manual for safety
            'display_order' => 0 // New flows always appear at top
        ];

        $new_flow_id = $db_flows->create_flow($flow_data);
        if (!$new_flow_id) {
            do_action('dm_log', 'error', 'Failed to create duplicated flow', [
                'source_flow_id' => $source_flow_id,
                'pipeline_id' => $source_flow['pipeline_id'],
                'flow_name' => $duplicate_flow_name
            ]);
            return false;
        }

        // Remap flow step IDs from {pipeline_step_id}_{old_flow_id} to {pipeline_step_id}_{new_flow_id}
        $remapped_config = $this->remap_flow_step_ids($source_flow['flow_config'], $source_flow_id, $new_flow_id);

        // Update flow with remapped configuration
        $update_success = $db_flows->update_flow($new_flow_id, [
            'flow_config' => wp_json_encode($remapped_config)
        ]);

        if (!$update_success) {
            do_action('dm_log', 'error', 'Failed to update flow with remapped configuration', [
                'new_flow_id' => $new_flow_id,
                'source_flow_id' => $source_flow_id
            ]);
            // Don't return false here, flow was created successfully, just config wasn't remapped
        }

        do_action('dm_log', 'info', 'Flow duplicated successfully', [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'pipeline_id' => $source_flow['pipeline_id'],
            'duplicate_flow_name' => $duplicate_flow_name
        ]);

        // Clear relevant caches after successful duplication to ensure UI shows new flow
        \DataMachine\Core\Database\DatabaseCache::clear_cache('dm_pipeline_flows_' . $source_flow['pipeline_id']);
        \DataMachine\Core\Database\DatabaseCache::clear_flow_cache($source_flow['pipeline_id']);

        // Trigger action for cache invalidation listeners
        do_action('dm_flow_duplicated', $source_flow['pipeline_id']);

        // For AJAX context, provide comprehensive response data for immediate UI updates
        if (wp_doing_ajax()) {
            // Get complete duplicated flow data
            $duplicated_flow_data = $db_flows->get_flow($new_flow_id);

            wp_send_json_success([
                /* translators: %s: Duplicated flow name */
                'message' => sprintf(__('Flow "%s" duplicated successfully', 'data-machine'), $duplicate_flow_name),
                'source_flow_id' => $source_flow_id,
                'new_flow_id' => $new_flow_id,
                'flow_name' => $duplicate_flow_name,
                'pipeline_id' => $source_flow['pipeline_id'],
                'flow_data' => $duplicated_flow_data,
                'created_type' => 'duplicate_flow'
            ]);
        }

        // For non-AJAX contexts, return new flow_id
        return $new_flow_id;
    }

    /**
     * Remap flow step IDs from old flow to new flow
     *
     * Flow step IDs use pattern: {pipeline_step_id}_{flow_id}
     * When duplicating, we need to change {pipeline_step_id}_{old_flow_id} to {pipeline_step_id}_{new_flow_id}
     *
     * @param array $source_config Original flow configuration
     * @param int $old_flow_id Original flow ID
     * @param int $new_flow_id New flow ID
     * @return array Remapped configuration
     */
    private function remap_flow_step_ids(array $source_config, int $old_flow_id, int $new_flow_id): array {
        $remapped_config = [];

        foreach ($source_config as $old_flow_step_id => $step_config) {
            // Extract pipeline_step_id by removing the old flow_id suffix
            $old_suffix = '_' . $old_flow_id;
            if (strpos($old_flow_step_id, $old_suffix) !== false) {
                $pipeline_step_id = str_replace($old_suffix, '', $old_flow_step_id);
                $new_flow_step_id = $pipeline_step_id . '_' . $new_flow_id;
            } else {
                // Fallback if pattern doesn't match expected format
                $new_flow_step_id = $old_flow_step_id . '_' . $new_flow_id;
                do_action('dm_log', 'warning', 'Unexpected flow step ID format during duplication', [
                    'old_flow_step_id' => $old_flow_step_id,
                    'old_flow_id' => $old_flow_id,
                    'new_flow_id' => $new_flow_id,
                    'fallback_new_id' => $new_flow_step_id
                ]);
            }

            // Copy step configuration with new flow step ID
            $remapped_config[$new_flow_step_id] = $step_config;
        }

        do_action('dm_log', 'debug', 'Flow step IDs remapped successfully', [
            'old_flow_id' => $old_flow_id,
            'new_flow_id' => $new_flow_id,
            'original_steps' => count($source_config),
            'remapped_steps' => count($remapped_config)
        ]);

        return $remapped_config;
    }

    /**
     * Handle pipeline creation from template using enhanced complete mode.
     *
     * @param mixed $default Default value (ignored)
     * @param string $template_id Template identifier
     * @param array $options Optional creation options
     * @return int|false Pipeline ID on success, false on failure
     */
    public function handle_create_pipeline_from_template($default, $template_id, $options = []) {
        // Permission check
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for template pipeline creation');
            return false;
        }

        // Get available templates
        $templates = apply_filters('dm_pipeline_templates', []);

        if (!isset($templates[$template_id])) {
            do_action('dm_log', 'error', 'Template not found', ['template_id' => $template_id]);
            return false;
        }

        $template = $templates[$template_id];

        // Convert template to complete pipeline data structure
        $pipeline_data = [
            'pipeline_name' => $options['pipeline_name'] ?? $template['name']
        ];

        // Add steps if template has them
        if (!empty($template['steps'])) {
            $pipeline_data['steps'] = [];
            foreach ($template['steps'] as $index => $step) {
                $pipeline_data['steps'][] = [
                    'step_type' => $step['type'],
                    'execution_order' => $index,
                    'label' => ucfirst(str_replace('_', ' ', $step['type']))
                ];
            }
        }

        // Add custom flow configuration if provided
        if (isset($options['flow_config'])) {
            $pipeline_data['flow_config'] = $options['flow_config'];
        }

        do_action('dm_log', 'info', 'Creating pipeline from template using complete mode', [
            'template_id' => $template_id,
            'template_name' => $template['name'],
            'steps_count' => count($template['steps'] ?? [])
        ]);

        // Use enhanced filter for atomic creation (complete mode will be triggered by presence of 'steps')
        $pipeline_id = $this->handle_create_pipeline(false, $pipeline_data);

        if (!$pipeline_id) {
            do_action('dm_log', 'error', 'Failed to create pipeline from template', [
                'template_id' => $template_id,
                'template_name' => $template['name']
            ]);
            return false;
        }

        do_action('dm_log', 'info', 'Pipeline created from template successfully', [
            'template_id' => $template_id,
            'pipeline_id' => $pipeline_id,
            'template_name' => $template['name'],
            'steps_count' => count($template['steps'] ?? [])
        ]);

        // AJAX response is handled by handle_create_pipeline in complete mode
        // No additional AJAX handling needed here

        return $pipeline_id;
    }

}