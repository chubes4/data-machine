<?php
/**
 * Centralized creation operations for pipelines, flows, and steps.
 *
 * @package DataMachine\Engine\Filters
 */

namespace DataMachine\Engine\Filters;

defined('WPINC') || exit;

class Create {

    public static function register() {
        $instance = new self();
        add_filter('dm_create_pipeline', [$instance, 'handle_create_pipeline'], 10, 2);
        add_filter('dm_create_pipeline_from_template', [$instance, 'handle_create_pipeline_from_template'], 10, 3);
        add_filter('dm_create_step', [$instance, 'handle_create_step'], 10, 2);
        add_filter('dm_create_flow', [$instance, 'handle_create_flow'], 10, 2);
        add_filter('dm_duplicate_flow', [$instance, 'handle_duplicate_flow'], 10, 2);
    }


    /**
     * @param mixed $default Unused
     * @param array $data Pipeline creation data
     * @return int|false Pipeline ID or false
     */
    public function handle_create_pipeline($default, $data = []) {
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for pipeline creation');
            return false;
        }

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

    private function create_simple_pipeline($data, $db_pipelines, $db_flows) {
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : 'Pipeline';

        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => '{}'
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            do_action('dm_log', 'error', 'Failed to create pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }

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

    private function create_complete_pipeline($data, $db_pipelines, $db_flows) {
        $pipeline_name = isset($data['pipeline_name']) ? sanitize_text_field(wp_unslash($data['pipeline_name'])) : 'Pipeline';
        $steps = $data['steps'];
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

        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => '{}'
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            return false;
        }

        $pipeline_config = [];
        foreach ($steps as $step) {
            $pipeline_step_id = $pipeline_id . '_' . wp_generate_uuid4();
            $step_type = $step['step_type'];
            $step_config = $all_steps[$step_type] ?? [];

            $pipeline_config[$pipeline_step_id] = [
                'step_type' => $step_type,
                'execution_order' => $step['execution_order'] ?? 0,
                'pipeline_step_id' => $pipeline_step_id,
                'label' => $step['label'] ?? $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
            ];

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

        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$success) {
            do_action('dm_log', 'error', 'Failed to update pipeline configuration', ['pipeline_id' => $pipeline_id]);
            return false;
        }
        if (!$pipeline_id) {
            do_action('dm_log', 'error', 'Failed to create complete pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }

        $flow_config_data = isset($data['flow_config']) ? $data['flow_config'] : [];
        $flow_name = $flow_config_data['flow_name'] ?? 'Flow';
        $scheduling_config = $flow_config_data['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode($scheduling_config)
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', "Failed to create flow for complete pipeline {$pipeline_id}");
            return false;
        }

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

        $update_success = apply_filters('dm_update_flow', false, $flow_id, [
            'flow_config' => json_encode($flow_config)
        ]);

        if (!$update_success) {
            do_action('dm_log', 'error', "Failed to update flow config for complete pipeline {$pipeline_id}");
        }

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

    private function finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows, $creation_type = 'simple') {
        do_action('dm_log', 'info', 'Pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'creation_type' => $creation_type
        ]);


        do_action('dm_pipeline_created', $pipeline_id);

        // Handle AJAX response
        if (wp_doing_ajax()) {
            $pipeline = $db_pipelines->get_pipeline($pipeline_id);
            $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);

            $message = $creation_type === 'complete'
                ? __('Complete pipeline created successfully', 'data-machine')
                : __('Pipeline created successfully', 'data-machine');

            do_action('dm_clear_pipelines_list_cache');

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

        return $pipeline_id;
    }

    /**
     * @param mixed $default Unused
     * @param array $data Step creation data
     * @return string|false Pipeline step ID or false
     */
    public function handle_create_step($default, $data = []) {
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for step creation');
            return false;
        }
        
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
        
        $all_steps = apply_filters('dm_steps', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            do_action('dm_log', 'error', 'Invalid step type for step creation', ['step_type' => $step_type]);
            return false;
        }
        
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        $next_execution_order = count($current_steps);
        
        $new_step = [
            'step_type' => $step_type,
            'execution_order' => $next_execution_order,
            'pipeline_step_id' => $pipeline_id . '_' . wp_generate_uuid4(),
            'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];
        
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
        
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            do_action('dm_sync_steps_to_flow', $flow_id, [$new_step], ['context' => 'add_step']);
        }

        do_action('dm_clear_pipeline_cache', $pipeline_id);

        do_action('dm_auto_save', $pipeline_id);

        do_action('dm_log', 'info', 'Step created successfully', [
            'pipeline_id' => $pipeline_id,
            'step_type' => $step_type,
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'execution_order' => $next_execution_order
        ]);

        // Handle AJAX response
        if (wp_doing_ajax()) {
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
        
        return $new_step['pipeline_step_id'];
    }

    /**
     * @param mixed $default Unused
     * @param array $data Flow creation data
     * @return int|false Flow ID or false
     */
    public function handle_create_flow($default, $data = []) {
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
        
        $flow_name = isset($data['flow_name']) ? sanitize_text_field(wp_unslash($data['flow_name'])) : 'Flow';

        $increment_success = $db_flows->increment_existing_flow_orders($pipeline_id);
        if (!$increment_success) {
            do_action('dm_log', 'error', 'Failed to increment existing flow orders for new flow creation', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return false;
        }

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode(['interval' => 'manual']),
            'display_order' => 0
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('dm_log', 'error', 'Failed to create flow', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return false;
        }

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

        if (wp_doing_ajax()) {
            $flow_data = apply_filters('dm_get_flow', null, $flow_id);
            $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
            do_action('dm_clear_pipeline_cache', $pipeline_id);

            wp_send_json_success([
                /* translators: %s: Flow name */
                'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
                'flow_id' => $flow_id,
                'flow_name' => $flow_name,
                'pipeline_id' => $pipeline_id,
                'flow_data' => $flow_data,
                'pipeline_steps' => $pipeline_steps,
                'template_data' => [
                    'flow' => $flow_data,
                    'pipeline_steps' => $pipeline_steps
                ],
                'created_type' => 'flow'
            ]);
        }

        return $flow_id;
    }

    /**
     * @param mixed $default Unused
     * @param int $source_flow_id Flow to duplicate
     * @return int|false New flow ID or false
     */
    public function handle_duplicate_flow($default, int $source_flow_id) {
        if (!current_user_can('manage_options')) {
            do_action('dm_log', 'error', 'Insufficient permissions for flow duplication');
            return false;
        }

        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            do_action('dm_log', 'error', 'Required database services unavailable for flow duplication', [
                'flows_db' => $db_flows ? 'available' : 'missing'
            ]);
            return false;
        }

        // Get source flow data
        $source_flow = apply_filters('dm_get_flow', null, $source_flow_id);
        if (!$source_flow) {
            do_action('dm_log', 'error', 'Source flow not found for duplication', ['source_flow_id' => $source_flow_id]);
            return false;
        }

        // Create duplicated flow name
        /* translators: %s: Original flow name */
        $duplicate_flow_name = sprintf(__('Copy of %s', 'data-machine'), $source_flow['flow_name']);

        $increment_success = $db_flows->increment_existing_flow_orders($source_flow['pipeline_id']);
        if (!$increment_success) {
            do_action('dm_log', 'error', 'Failed to increment existing flow orders for flow duplication', [
                'source_flow_id' => $source_flow_id,
                'pipeline_id' => $source_flow['pipeline_id']
            ]);
            return false;
        }

        $flow_data = [
            'pipeline_id' => $source_flow['pipeline_id'],
            'flow_name' => $duplicate_flow_name,
            'flow_config' => json_encode($source_flow['flow_config']),
            'scheduling_config' => json_encode(['interval' => 'manual']),
            'display_order' => 0
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

        $remapped_config = $this->remap_flow_step_ids($source_flow['flow_config'], $source_flow_id, $new_flow_id);

        $update_success = apply_filters('dm_update_flow', false, $new_flow_id, [
            'flow_config' => json_encode($remapped_config)
        ]);

        if (!$update_success) {
            do_action('dm_log', 'error', 'Failed to update flow with remapped configuration', [
                'new_flow_id' => $new_flow_id,
                'source_flow_id' => $source_flow_id
            ]);
        }

        do_action('dm_log', 'info', 'Flow duplicated successfully', [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'pipeline_id' => $source_flow['pipeline_id'],
            'duplicate_flow_name' => $duplicate_flow_name
        ]);


        do_action('dm_flow_duplicated', $source_flow['pipeline_id']);

        if (wp_doing_ajax()) {
            $duplicated_flow_data = apply_filters('dm_get_flow', null, $new_flow_id);
            $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $source_flow['pipeline_id']);
            do_action('dm_clear_pipeline_cache', $source_flow['pipeline_id']);

            wp_send_json_success([
                /* translators: %s: Duplicated flow name */
                'message' => sprintf(__('Flow "%s" duplicated successfully', 'data-machine'), $duplicate_flow_name),
                'source_flow_id' => $source_flow_id,
                'new_flow_id' => $new_flow_id,
                'flow_name' => $duplicate_flow_name,
                'pipeline_id' => $source_flow['pipeline_id'],
                'flow_data' => $duplicated_flow_data,
                'pipeline_steps' => $pipeline_steps,
                'created_type' => 'duplicate_flow'
            ]);
        }

        return $new_flow_id;
    }

    private function remap_flow_step_ids(array $source_config, int $old_flow_id, int $new_flow_id): array {
        $remapped_config = [];

        foreach ($source_config as $old_flow_step_id => $step_config) {
            $old_suffix = '_' . $old_flow_id;
            if (strpos($old_flow_step_id, $old_suffix) !== false) {
                $pipeline_step_id = str_replace($old_suffix, '', $old_flow_step_id);
                $new_flow_step_id = $pipeline_step_id . '_' . $new_flow_id;
            } else {
                $new_flow_step_id = $old_flow_step_id . '_' . $new_flow_id;
                do_action('dm_log', 'warning', 'Unexpected flow step ID format during duplication', [
                    'old_flow_step_id' => $old_flow_step_id,
                    'old_flow_id' => $old_flow_id,
                    'new_flow_id' => $new_flow_id,
                    'fallback_new_id' => $new_flow_step_id
                ]);
            }

            // Update internal flow_step_id and flow_id to match the new config key
            $step_config['flow_step_id'] = $new_flow_step_id;
            $step_config['flow_id'] = $new_flow_id;

            $remapped_config[$new_flow_step_id] = $step_config;
        }

        do_action('dm_log', 'debug', 'Flow step IDs remapped successfully', [
            'old_flow_id' => $old_flow_id,
            'new_flow_id' => $new_flow_id,
            'original_steps' => count($source_config),
            'remapped_steps' => count($remapped_config),
            'fixed_internal_ids' => true
        ]);

        return $remapped_config;
    }

    /**
     * @param mixed $default Unused
     * @param string $template_id Template identifier
     * @param array $options Template options
     * @return int|false Pipeline ID or false
     */
    public function handle_create_pipeline_from_template($default, $template_id, $options = []) {
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

        $pipeline_data = [
            'pipeline_name' => $options['pipeline_name'] ?? $template['name']
        ];

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

        if (isset($options['flow_config'])) {
            $pipeline_data['flow_config'] = $options['flow_config'];
        }

        do_action('dm_log', 'info', 'Creating pipeline from template using complete mode', [
            'template_id' => $template_id,
            'template_name' => $template['name'],
            'steps_count' => count($template['steps'] ?? [])
        ]);

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

        return $pipeline_id;
    }

}