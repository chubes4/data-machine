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

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

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

        if ($success) {
            \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
            do_action('datamachine_chat_pipelines_inventory_cleared');
        }

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
                __('Insufficient permissions', 'data-machine'),
                ['status' => 403]
            );
        }

        $pipeline_id = absint($pipeline_id);
        if ($pipeline_id <= 0) {
            return new WP_Error(
                'invalid_pipeline_id',
                __('Valid pipeline ID is required.', 'data-machine'),
                ['status' => 400]
            );
        }

        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found.', 'data-machine'),
                ['status' => 404]
            );
        }

        if (!isset($pipeline['pipeline_name']) || empty(trim($pipeline['pipeline_name']))) {
            do_action('datamachine_log', 'error', 'Cannot delete pipeline - missing or empty pipeline name', [
                'pipeline_id' => $pipeline_id
            ]);
            return new WP_Error(
                'data_integrity_error',
                __('Pipeline data is corrupted - missing name.', 'data-machine'),
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
                    __('Failed to delete associated flows.', 'data-machine'),
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
                __('Failed to delete pipeline.', 'data-machine'),
                ['status' => 500]
            );
        }

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

        return [
            'message' => sprintf(
                /* translators: 1: pipeline name, 2: number of flows deleted */
                esc_html__('Pipeline "%1$s" deleted successfully. %2$d flows were also deleted.', 'data-machine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'deleted_flows' => $flow_count
        ];
    }

}
