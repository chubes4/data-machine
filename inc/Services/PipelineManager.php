<?php
/**
 * Pipeline Manager Service
 *
 * Centralized business logic for pipeline CRUD operations.
 * Step operations are handled by PipelineStepManager.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

use WP_Error;

defined('ABSPATH') || exit;

class PipelineManager {

    private \DataMachine\Core\Database\Pipelines\Pipelines $db_pipelines;
    private \DataMachine\Core\Database\Flows\Flows $db_flows;
    private FlowManager $flow_manager;
    private FlowStepManager $flow_step_manager;

    public function __construct() {
        $this->db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $this->db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $this->flow_manager = new FlowManager();
        $this->flow_step_manager = new FlowStepManager();
    }

    /**
     * Create a new pipeline in simple mode (no steps).
     *
     * @param string $name Pipeline name
     * @param array $options Optional settings (flow_config with flow_name, scheduling_config)
     * @return array|null Complete pipeline data on success, null on failure
     */
    public function create(string $name, array $options = []): ?array {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for pipeline creation');
            return null;
        }

        $pipeline_name = sanitize_text_field(wp_unslash($name));
        if (empty(trim($pipeline_name))) {
            do_action('datamachine_log', 'error', 'Cannot create pipeline - missing or empty pipeline name');
            return null;
        }

        $pipeline_id = $this->db_pipelines->create_pipeline([
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => []
        ]);

        if (!$pipeline_id) {
            do_action('datamachine_log', 'error', 'Failed to create pipeline', ['pipeline_name' => $pipeline_name]);
            return null;
        }

        $flow_config = $options['flow_config'] ?? [];
        $flow_name = $flow_config['flow_name'] ?? '';

        $scheduling_config = $flow_config['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_result = $this->flow_manager->create($pipeline_id, $flow_name, [
            'scheduling_config' => $scheduling_config
        ]);

        if (!$flow_result) {
            do_action('datamachine_log', 'error', "Failed to create flow for pipeline {$pipeline_id}");
        }

        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        $flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);

        do_action('datamachine_log', 'info', 'Pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_result['flow_id'] ?? null,
            'creation_mode' => 'simple'
        ]);

        return [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'pipeline_data' => $pipeline,
            'flows' => $flows,
            'creation_mode' => 'simple'
        ];
    }

    /**
     * Create a new pipeline with steps (complete mode).
     *
     * @param string $name Pipeline name
     * @param array $steps Array of step configurations
     * @param array $options Optional settings (flow_config with flow_name, scheduling_config)
     * @return array|null Complete pipeline data on success, null on failure
     */
    public function createWithSteps(string $name, array $steps, array $options = []): ?array {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for pipeline creation');
            return null;
        }

        $pipeline_name = sanitize_text_field(wp_unslash($name));
        if (empty(trim($pipeline_name))) {
            do_action('datamachine_log', 'error', 'Cannot create pipeline - missing or empty pipeline name');
            return null;
        }

        $all_step_types = apply_filters('datamachine_step_types', []);
        foreach ($steps as $step) {
            $step_type = $step['step_type'] ?? '';
            if (!isset($all_step_types[$step_type])) {
                do_action('datamachine_log', 'error', 'Invalid step type in complete pipeline creation', [
                    'step_type' => $step_type,
                    'available_types' => array_keys($all_step_types)
                ]);
                return null;
            }
        }

        $pipeline_id = $this->db_pipelines->create_pipeline([
            'pipeline_name' => $pipeline_name,
            'pipeline_config' => []
        ]);

        if (!$pipeline_id) {
            do_action('datamachine_log', 'error', 'Failed to create pipeline', ['pipeline_name' => $pipeline_name]);
            return null;
        }

        $pipeline_config = [];
        foreach ($steps as $step) {
            $pipeline_step_id = $pipeline_id . '_' . wp_generate_uuid4();
            $step_type = $step['step_type'];
            $step_type_config = $all_step_types[$step_type] ?? [];

            $pipeline_config[$pipeline_step_id] = [
                'step_type' => $step_type,
                'execution_order' => $step['execution_order'] ?? 0,
                'pipeline_step_id' => $pipeline_step_id,
                'label' => $step['label'] ?? $step_type_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
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

        $success = $this->db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => $pipeline_config
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'Failed to update pipeline configuration', ['pipeline_id' => $pipeline_id]);
            return null;
        }

        $flow_config_data = $options['flow_config'] ?? [];
        $flow_name = $flow_config_data['flow_name'] ?? null;

        if (empty($flow_name)) {
            do_action('datamachine_log', 'error', 'Cannot create flow - missing or empty flow name');
            return null;
        }

        $scheduling_config = $flow_config_data['scheduling_config'] ?? ['interval' => 'manual'];

        $flow_id = $this->db_flows->create_flow([
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => [],
            'scheduling_config' => $scheduling_config
        ]);

        if (!$flow_id) {
            do_action('datamachine_log', 'error', "Failed to create flow for complete pipeline {$pipeline_id}");
            return null;
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

        $this->db_flows->update_flow($flow_id, [
            'flow_config' => $flow_config
        ]);

        $step_order_map = [];
        foreach ($pipeline_config as $pipeline_step_id => $step_config) {
            $step_order_map[$step_config['execution_order']] = $pipeline_step_id;
        }

        foreach ($steps as $step) {
            if (isset($step['handler_slug']) && isset($step['handler_config'])) {
                $execution_order = $step['execution_order'] ?? 0;
                $pipeline_step_id = $step_order_map[$execution_order] ?? null;

                if ($pipeline_step_id) {
                    $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
                    if ($step['handler_slug']) {
                        $this->flow_step_manager->updateHandler($flow_step_id, $step['handler_slug'], $step['handler_config'] ?? []);
                    }
                }
            }
        }

        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        $flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);

        do_action('datamachine_log', 'info', 'Complete pipeline created successfully', [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'flow_id' => $flow_id,
            'steps_count' => count($pipeline_config),
            'creation_mode' => 'complete'
        ]);

        return [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'pipeline_data' => $pipeline,
            'flows' => $flows,
            'creation_mode' => 'complete'
        ];
    }

    /**
     * Get a pipeline by ID.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array|null Pipeline data or null if not found
     */
    public function get(int $pipeline_id): ?array {
        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        return $pipeline ?: null;
    }

    /**
     * Get a pipeline with its flows.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array|null Pipeline data with flows or null if not found
     */
    public function getWithFlows(int $pipeline_id): ?array {
        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return null;
        }

        $flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);

        return [
            'pipeline' => $pipeline,
            'flows' => $flows
        ];
    }

    /**
     * Update a pipeline.
     *
     * @param int $pipeline_id Pipeline ID
     * @param array $data Data to update (pipeline_name, pipeline_config)
     * @return bool Success status
     */
    public function update(int $pipeline_id, array $data): bool {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for pipeline update');
            return false;
        }

        $update_data = [];

        if (isset($data['pipeline_name'])) {
            $update_data['pipeline_name'] = sanitize_text_field(wp_unslash($data['pipeline_name']));
        }

        if (isset($data['pipeline_config'])) {
            $update_data['pipeline_config'] = $data['pipeline_config'];
        }

        if (empty($update_data)) {
            return false;
        }

        $success = $this->db_pipelines->update_pipeline($pipeline_id, $update_data);

        return $success;
    }

    /**
     * Delete a pipeline and all associated flows.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array|WP_Error Deletion result or error
     */
    public function delete(int $pipeline_id): array|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'datamachine'),
                ['status' => 403]
            );
        }

        $pipeline_id = absint($pipeline_id);
        if ($pipeline_id <= 0) {
            return new WP_Error(
                'invalid_pipeline_id',
                __('Valid pipeline ID is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found.', 'datamachine'),
                ['status' => 404]
            );
        }

        if (!isset($pipeline['pipeline_name']) || empty(trim($pipeline['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot delete pipeline - missing or empty pipeline name', [
                'pipeline_id' => $pipeline_id
            ]);
            return new WP_Error(
                'data_integrity_error',
                __('Pipeline data is corrupted - missing name.', 'datamachine'),
                ['status' => 500]
            );
        }

        $pipeline_name = $pipeline['pipeline_name'];
        $affected_flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);

        foreach ($affected_flows as $flow) {
            if (!isset($flow['flow_id']) || empty($flow['flow_id'])) {
                do_action('datamachine_log', 'error', 'Flow data missing flow_id during pipeline deletion', [
                    'pipeline_id' => $pipeline_id,
                    'flow' => $flow
                ]);
                continue;
            }
            $success = $this->db_flows->delete_flow((int) $flow['flow_id']);
            if (!$success) {
                return new WP_Error(
                    'flow_deletion_failed',
                    __('Failed to delete associated flows.', 'datamachine'),
                    ['status' => 500]
                );
            }
        }

        $cleanup = new \DataMachine\Core\FilesRepository\FileCleanup();
        $filesystem_deleted = $cleanup->delete_pipeline_directory($pipeline_id);

        if (!$filesystem_deleted) {
            do_action('datamachine_log', 'warning', 'Pipeline filesystem cleanup failed, but continuing with database deletion.', [
                'pipeline_id' => $pipeline_id
            ]);
        }

        $success = $this->db_pipelines->delete_pipeline($pipeline_id);
        if (!$success) {
            return new WP_Error(
                'pipeline_deletion_failed',
                __('Failed to delete pipeline.', 'datamachine'),
                ['status' => 500]
            );
        }

        return [
            'message' => sprintf(
                /* translators: 1: pipeline name, 2: number of flows deleted */
                esc_html__('Pipeline "%1$s" deleted successfully. %2$d flows were also deleted.', 'datamachine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'deleted_flows' => $flow_count
        ];
    }

}
