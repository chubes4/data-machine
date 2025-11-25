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
        add_filter('datamachine_create_pipeline', [$instance, 'handle_create_pipeline'], 10, 2);
        add_filter('datamachine_create_step', [$instance, 'handle_create_step'], 10, 2);
        add_filter('datamachine_create_flow', [$instance, 'handle_create_flow'], 10, 2);
        add_filter('datamachine_duplicate_flow', [$instance, 'handle_duplicate_flow'], 10, 2);
    }


    /**
     * Handle pipeline creation via datamachine_create_pipeline filter.
     *
     * @param mixed $default Default return value
     * @param array $data Pipeline creation data
     * @return int|false Pipeline ID on success, false on failure
     */
    public function handle_create_pipeline($default, $data = []) {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for pipeline creation');
            return false;
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        $is_complete_mode = isset($data['steps']) && is_array($data['steps']) && !empty($data['steps']);

        if ($is_complete_mode) {
            do_action('datamachine_log', 'debug', 'Creating pipeline in complete mode', [
                'pipeline_name' => $data['pipeline_name'] ?? 'Pipeline',
                'steps_count' => count($data['steps'])
            ]);
            return $this->create_complete_pipeline($data, $db_pipelines, $db_flows);
        } else {
            do_action('datamachine_log', 'debug', 'Creating pipeline in simple mode', [
                'pipeline_name' => $data['pipeline_name'] ?? 'Pipeline'
            ]);
            return $this->create_simple_pipeline($data, $db_pipelines, $db_flows);
        }
    }

    /**
     * Create pipeline in simple mode (no steps provided).
     *
     * @param array $data Pipeline data
     * @param object $db_pipelines Pipelines database handler
     * @param object $db_flows Flows database handler
     * @return int|false Pipeline ID on success, false on failure
     */
    private function create_simple_pipeline($data, $db_pipelines, $db_flows) {
        if (!isset($data['pipeline_name']) || empty(trim($data['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot create pipeline - missing or empty pipeline name');
            return false;
        }
        $pipeline_name = sanitize_text_field(wp_unslash($data['pipeline_name']));

        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => '{}'
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        if (!$pipeline_id) {
            do_action('datamachine_log', 'error', 'Failed to create pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }

        $flow_config = isset($data['flow_config']) ? $data['flow_config'] : [];
        if (!isset($flow_config['flow_name']) || empty(trim($flow_config['flow_name']))) {
            do_action('datamachine_log', 'error', 'Cannot create flow - missing or empty flow name');
            return false;
        }
        $flow_name = $flow_config['flow_name'];
        $scheduling_config = $flow_config['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode($scheduling_config)
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('datamachine_log', 'error', "Failed to create flow for pipeline {$pipeline_id}");
        }

        return $this->finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows);
    }

    /**
     * Create pipeline in complete mode (with steps provided).
     *
     * @param array $data Pipeline data including steps
     * @param object $db_pipelines Pipelines database handler
     * @param object $db_flows Flows database handler
     * @return int|false Pipeline ID on success, false on failure
     */
    private function create_complete_pipeline($data, $db_pipelines, $db_flows) {
        if (!isset($data['pipeline_name']) || empty(trim($data['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot create pipeline - missing or empty pipeline name');
            return false;
        }
        $pipeline_name = sanitize_text_field(wp_unslash($data['pipeline_name']));
        $steps = $data['steps'];
        $all_steps = apply_filters('datamachine_step_types', []);
        foreach ($steps as $step) {
            $step_type = $step['step_type'] ?? '';
            if (!isset($all_steps[$step_type])) {
                do_action('datamachine_log', 'error', 'Invalid step type in complete pipeline creation', [
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
            do_action('datamachine_log', 'error', 'Failed to update pipeline configuration', ['pipeline_id' => $pipeline_id]);
            return false;
        }
        if (!$pipeline_id) {
            do_action('datamachine_log', 'error', 'Failed to create complete pipeline', ['pipeline_name' => $pipeline_name]);
            return false;
        }

        $flow_config_data = isset($data['flow_config']) ? $data['flow_config'] : [];
        if (!isset($flow_config_data['flow_name']) || empty(trim($flow_config_data['flow_name']))) {
            do_action('datamachine_log', 'error', 'Cannot create flow - missing or empty flow name');
            return false;
        }
        $flow_name = $flow_config_data['flow_name'];
        $scheduling_config = $flow_config_data['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]),
            'scheduling_config' => json_encode($scheduling_config)
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('datamachine_log', 'error', "Failed to create flow for complete pipeline {$pipeline_id}");
            return false;
        }

        $flow_config = [];
        foreach ($pipeline_config as $pipeline_step_id => $step_config) {
            $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
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

        $update_success = $db_flows->update_flow($flow_id, [
            'flow_config' => json_encode($flow_config)
        ]);

        if (!$update_success) {
            do_action('datamachine_log', 'error', "Failed to update flow config for complete pipeline {$pipeline_id}");
        }

        $step_order_map = [];
        foreach ($pipeline_config as $pipeline_step_id => $step_config) {
            $execution_order = $step_config['execution_order'];
            $step_order_map[$execution_order] = $pipeline_step_id;
        }

        foreach ($data['steps'] as $step) {
            if (isset($step['handler_slug']) && isset($step['handler_config'])) {
                $execution_order = $step['execution_order'] ?? 0;
                $pipeline_step_id = $step_order_map[$execution_order] ?? null;

                if ($pipeline_step_id) {
                    $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
                    $handler_slug = $step['handler_slug'];
                    $handler_config = $step['handler_config'] ?? [];

                    if ($handler_slug) {
                        do_action('datamachine_update_flow_handler', $flow_step_id, $handler_slug, $handler_config);
                    }
                }
            }
        }

        do_action('datamachine_log', 'info', 'Complete pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'steps_count' => count($pipeline_config)
        ]);

        return $this->finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows, 'complete');
    }

    private function finalize_pipeline_creation($pipeline_id, $pipeline_name, $flow_id, $db_pipelines, $db_flows, $creation_type = 'simple') {
        do_action('datamachine_log', 'info', 'Pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'creation_type' => $creation_type
        ]);

        return $pipeline_id;
    }

    public function handle_create_step($default, $data = []) {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for step creation');
            return false;
        }
        
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        $step_type = isset($data['step_type']) ? sanitize_text_field(wp_unslash($data['step_type'])) : '';
        
        if ($pipeline_id <= 0) {
            do_action('datamachine_log', 'error', 'Pipeline ID is required for step creation');
            return false;
        }
        
        if (empty($step_type)) {
            do_action('datamachine_log', 'error', 'Step type is required for step creation');
            return false;
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        
        $all_steps = apply_filters('datamachine_step_types', []);
        $step_config = $all_steps[$step_type] ?? null;
        if (!$step_config) {
            do_action('datamachine_log', 'error', 'Invalid step type for step creation', ['step_type' => $step_type]);
            return false;
        }
        
        $current_steps = $db_pipelines->get_pipeline_config($pipeline_id);
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
            do_action('datamachine_log', 'error', 'Failed to add step to pipeline', [
                'pipeline_id' => $pipeline_id,
                'step_type' => $step_type
            ]);
            return false;
        }
        
        $flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            do_action('datamachine_sync_steps_to_flow', $flow_id, [$new_step], ['context' => 'add_step']);
        }

        do_action('datamachine_clear_pipeline_cache', $pipeline_id);

        do_action('datamachine_log', 'info', 'Step created successfully', [
            'pipeline_id' => $pipeline_id,
            'step_type' => $step_type,
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'execution_order' => $next_execution_order
        ]);

        return $new_step['pipeline_step_id'];
    }

    public function handle_create_flow($default, $data = []) {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow creation');
            return false;
        }
        
        // Validate required pipeline_id
        $pipeline_id = isset($data['pipeline_id']) ? (int)sanitize_text_field(wp_unslash($data['pipeline_id'])) : 0;
        if ($pipeline_id <= 0) {
            do_action('datamachine_log', 'error', 'Pipeline ID is required for flow creation');
            return false;
        }

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        
        // Validate pipeline exists
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            do_action('datamachine_log', 'error', 'Pipeline not found for flow creation', ['pipeline_id' => $pipeline_id]);
            return false;
        }
        
        $flow_name = isset($data['flow_name']) ? sanitize_text_field(wp_unslash($data['flow_name'])) : 'Flow';

        // Support pre-configured flow creation (optional parameters)
        $flow_config = isset($data['flow_config']) && is_array($data['flow_config'])
            ? $data['flow_config']
            : [];

        $scheduling_config = isset($data['scheduling_config']) && is_array($data['scheduling_config'])
            ? $data['scheduling_config']
            : ['interval' => 'manual'];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode($flow_config),
            'scheduling_config' => json_encode($scheduling_config)
        ];
        
        $flow_id = $db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('datamachine_log', 'error', 'Failed to create flow', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return false;
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $pipeline_steps = $db_pipelines->get_pipeline_config($pipeline_id);
        if (!empty($pipeline_steps)) {
            do_action('datamachine_sync_steps_to_flow', $flow_id, $pipeline_steps, ['context' => 'create_flow']);
        }
        
        do_action('datamachine_log', 'info', 'Flow created successfully', [
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'synced_steps' => count($pipeline_steps)
        ]);

        // Register with Action Scheduler if a recurring schedule was set
        if (isset($scheduling_config['interval']) && $scheduling_config['interval'] !== 'manual') {
            $scheduling_result = \DataMachine\Api\Flows\FlowScheduling::handle_scheduling_update($flow_id, $scheduling_config);
            if (is_wp_error($scheduling_result)) {
                do_action('datamachine_log', 'error', 'Failed to schedule flow with Action Scheduler', [
                    'flow_id' => $flow_id,
                    'error' => $scheduling_result->get_error_message()
                ]);
            } else {
                do_action('datamachine_log', 'info', 'Flow scheduled with Action Scheduler', [
                    'flow_id' => $flow_id,
                    'interval' => $scheduling_config['interval']
                ]);
            }
        }

        if (wp_doing_ajax()) {
            $flow_data = $db_flows->get_flow($flow_id);
            $pipeline_steps = $db_pipelines->get_pipeline_config($pipeline_id);
            do_action('datamachine_clear_pipeline_cache', $pipeline_id);

            wp_send_json_success([
                'message' => sprintf('Flow "%s" created successfully', $flow_name),
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

    public function handle_duplicate_flow($default, int $source_flow_id) {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow duplication');
            return false;
        }

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        // Get source flow data
        $source_flow = $db_flows->get_flow($source_flow_id);
        if (!$source_flow) {
            do_action('datamachine_log', 'error', 'Source flow not found for duplication', ['source_flow_id' => $source_flow_id]);
            return false;
        }

        // Create duplicated flow name
        $duplicate_flow_name = sprintf('Copy of %s', $source_flow['flow_name']);

        $flow_data = [
            'pipeline_id' => $source_flow['pipeline_id'],
            'flow_name' => $duplicate_flow_name,
            'flow_config' => json_encode($source_flow['flow_config']),
            'scheduling_config' => json_encode(['interval' => 'manual'])
        ];

        $new_flow_id = $db_flows->create_flow($flow_data);
        if (!$new_flow_id) {
            do_action('datamachine_log', 'error', 'Failed to create duplicated flow', [
                'source_flow_id' => $source_flow_id,
                'pipeline_id' => $source_flow['pipeline_id'],
                'flow_name' => $duplicate_flow_name
            ]);
            return false;
        }

        $remapped_config = $this->remap_flow_step_ids($source_flow['flow_config'], $source_flow_id, $new_flow_id);

        $update_success = $db_flows->update_flow($new_flow_id, [
            'flow_config' => json_encode($remapped_config)
        ]);

        if (!$update_success) {
            do_action('datamachine_log', 'error', 'Failed to update flow with remapped configuration', [
                'new_flow_id' => $new_flow_id,
                'source_flow_id' => $source_flow_id
            ]);
        }

        do_action('datamachine_log', 'info', 'Flow duplicated successfully', [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'pipeline_id' => $source_flow['pipeline_id'],
            'duplicate_flow_name' => $duplicate_flow_name
        ]);

        return $new_flow_id;
    }

    private function remap_flow_step_ids(array $source_config, int $old_flow_id, int $new_flow_id): array {
        $remapped_config = [];

        foreach ($source_config as $old_flow_step_id => $step_config) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $old_flow_step_id);
            if ($parts) {
                $pipeline_step_id = $parts['pipeline_step_id'];
                $new_flow_step_id = $pipeline_step_id . '_' . $new_flow_id;
            } else {
                $new_flow_step_id = $old_flow_step_id . '_' . $new_flow_id;
                do_action('datamachine_log', 'warning', 'Unexpected flow step ID format during duplication', [
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

        do_action('datamachine_log', 'debug', 'Flow step IDs remapped successfully', [
            'old_flow_id' => $old_flow_id,
            'new_flow_id' => $new_flow_id,
            'original_steps' => count($source_config),
            'remapped_steps' => count($remapped_config),
            'fixed_internal_ids' => true
        ]);

        return $remapped_config;
    }

}