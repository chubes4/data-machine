<?php
/**
 * Pipeline Step Manager Service
 *
 * Centralized business logic for pipeline step CRUD operations.
 * Handles step creation, deletion, reordering, and configuration updates.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

use DataMachine\Services\StepTypeService;
use WP_Error;

defined('ABSPATH') || exit;

class PipelineStepManager {

    private \DataMachine\Core\Database\Pipelines\Pipelines $db_pipelines;
    private \DataMachine\Core\Database\Flows\Flows $db_flows;
    private FlowManager $flow_manager;

    public function __construct() {
        $this->db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $this->db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $this->flow_manager = new FlowManager();
    }

    /**
     * Get a pipeline step configuration by ID.
     *
     * @param string $pipeline_step_id Pipeline step ID (format: {pipeline_id}_{uuid4})
     * @return array|null Step configuration or null if not found
     */
    public function get(string $pipeline_step_id): ?array {
        $step_config = $this->db_pipelines->get_pipeline_step_config($pipeline_step_id);
        return !empty($step_config) ? $step_config : null;
    }

    /**
     * Get all step configurations for a pipeline.
     *
     * @param int $pipeline_id Pipeline ID
     * @return array Pipeline step configurations
     */
    public function getForPipeline(int $pipeline_id): array {
        return $this->db_pipelines->get_pipeline_config($pipeline_id);
    }

    /**
     * Add a step to a pipeline.
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $step_type Step type
     * @return string|null Pipeline step ID on success, null on failure
     */
    public function add(int $pipeline_id, string $step_type): ?string {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for step creation');
            return null;
        }

        if ($pipeline_id <= 0) {
            do_action('datamachine_log', 'error', 'Pipeline ID is required for step creation');
            return null;
        }

        $step_type = sanitize_text_field(wp_unslash($step_type));
        if (empty($step_type)) {
            do_action('datamachine_log', 'error', 'Step type is required for step creation');
            return null;
        }

        $step_type_service = new StepTypeService();
        $step_type_config = $step_type_service->get($step_type);

        if (!$step_type_config) {
            do_action('datamachine_log', 'error', 'Invalid step type for step creation', ['step_type' => $step_type]);
            return null;
        }

        $current_steps = $this->db_pipelines->get_pipeline_config($pipeline_id);
        $next_execution_order = count($current_steps);

        $new_step = [
            'step_type' => $step_type,
            'execution_order' => $next_execution_order,
            'pipeline_step_id' => $pipeline_id . '_' . wp_generate_uuid4(),
            'label' => $step_type_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];

        $pipeline_config = [];
        foreach ($current_steps as $step) {
            $pipeline_config[$step['pipeline_step_id']] = $step;
        }
        $pipeline_config[$new_step['pipeline_step_id']] = $new_step;

        $success = $this->db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => $pipeline_config
        ]);

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

        if (!$success) {
            do_action('datamachine_log', 'error', 'Failed to add step to pipeline', [
                'pipeline_id' => $pipeline_id,
                'step_type' => $step_type
            ]);
            return null;
        }

        $flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);
        foreach ($flows as $flow) {
            $this->flow_manager->syncStepsToFlow($flow['flow_id'], $pipeline_id, [$new_step], $pipeline_config);
        }

        do_action('datamachine_log', 'info', 'Step created successfully', [
            'pipeline_id' => $pipeline_id,
            'step_type' => $step_type,
            'pipeline_step_id' => $new_step['pipeline_step_id'],
            'execution_order' => $next_execution_order
        ]);

        return $new_step['pipeline_step_id'];
    }

    /**
     * Delete a step from a pipeline.
     *
     * @param string $pipeline_step_id Pipeline step ID
     * @param int $pipeline_id Pipeline ID
     * @return array|WP_Error Deletion result or error
     */
    public function delete(string $pipeline_step_id, int $pipeline_id): array|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'data-machine'),
                ['status' => 403]
            );
        }

        $pipeline_id = absint($pipeline_id);
        $pipeline_step_id = trim($pipeline_step_id);

        if ($pipeline_id <= 0 || $pipeline_step_id === '') {
            return new WP_Error(
                'invalid_step_parameters',
                __('Valid pipeline ID and step ID are required.', 'data-machine'),
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
            do_action('datamachine_log', 'error', 'Cannot delete pipeline step - pipeline missing or empty name', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id
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

        $current_steps = $this->db_pipelines->get_pipeline_config($pipeline_id);
        $remaining_steps = [];
        $step_found = false;

        foreach ($current_steps as $step) {
            if (($step['pipeline_step_id'] ?? '') !== $pipeline_step_id) {
                $remaining_steps[] = $step;
            } else {
                $step_found = true;
            }
        }

        if (!$step_found) {
            return new WP_Error(
                'step_not_found',
                __('Step not found in pipeline.', 'data-machine'),
                ['status' => 404]
            );
        }

        $updated_steps = [];
        foreach ($remaining_steps as $index => $step) {
            $step['execution_order'] = $index;
            $updated_steps[$step['pipeline_step_id']] = $step;
        }

        $success = $this->db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => $updated_steps
        ]);

        if (!$success) {
            return new WP_Error(
                'step_deletion_failed',
                __('Failed to delete step from pipeline.', 'data-machine'),
                ['status' => 500]
            );
        }

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

        foreach ($affected_flows as $flow) {
            if (!isset($flow['flow_id']) || empty($flow['flow_id'])) {
                continue;
            }
            $flow_id = (int) $flow['flow_id'];
            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => $step_data) {
                if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
                    unset($flow_config[$flow_step_id]);
                }
            }

            foreach ($flow_config as $flow_step_id => &$flow_step) {
                if (!isset($flow_step['pipeline_step_id'])) {
                    continue;
                }
                foreach ($updated_steps as $updated_step) {
                    if ($updated_step['pipeline_step_id'] === $flow_step['pipeline_step_id']) {
                        $flow_step['execution_order'] = $updated_step['execution_order'];
                        break;
                    }
                }
            }
            unset($flow_step);

            $this->db_flows->update_flow($flow_id, [
                'flow_config' => $flow_config
            ]);
        }

        $remaining_step_count = count($this->db_pipelines->get_pipeline_config($pipeline_id));

        return [
            'message' => sprintf(
                /* translators: 1: pipeline name, 2: number of flows affected */
                esc_html__('Step deleted successfully from pipeline "%1$s". %2$d flows were affected.', 'data-machine'),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_step_id' => $pipeline_step_id,
            'affected_flows' => $flow_count,
            'remaining_steps' => $remaining_step_count
        ];
    }

    /**
     * Update system prompt for an AI pipeline step.
     *
     * @param string $pipeline_step_id Pipeline step ID
     * @param string $system_prompt System prompt content
     * @return bool Success status
     */
    public function updateSystemPrompt(string $pipeline_step_id, string $system_prompt): bool {
        $step_config = $this->db_pipelines->get_pipeline_step_config($pipeline_step_id);

        if (empty($step_config) || empty($step_config['pipeline_id'])) {
            do_action('datamachine_log', 'error', 'System prompt update failed - pipeline step not found', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        $pipeline_id = $step_config['pipeline_id'];

        $target_pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        if (!$target_pipeline) {
            do_action('datamachine_log', 'error', 'System prompt update failed - pipeline not found', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        $pipeline_config = $target_pipeline['pipeline_config'] ?? [];

        if (!isset($pipeline_config[$pipeline_step_id])) {
            $pipeline_config[$pipeline_step_id] = [];
        }
        $pipeline_config[$pipeline_step_id]['system_prompt'] = wp_unslash($system_prompt);

        $success = $this->db_pipelines->update_pipeline($target_pipeline['pipeline_id'], [
            'pipeline_config' => $pipeline_config
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'System prompt update failed - database update error', [
                'pipeline_id' => $target_pipeline['pipeline_id'],
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

        return true;
    }

    /**
     * Reorder steps within a pipeline.
     *
     * @param int $pipeline_id Pipeline ID
     * @param array $step_order Array of step order items with pipeline_step_id and execution_order
     * @return array|WP_Error Reorder result or error
     */
    public function reorder(int $pipeline_id, array $step_order): array|WP_Error {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'data-machine'),
                ['status' => 403]
            );
        }

        $pipeline_steps = $this->db_pipelines->get_pipeline_config($pipeline_id);
        if (empty($pipeline_steps)) {
            return new WP_Error(
                'pipeline_not_found',
                __('Pipeline not found', 'data-machine'),
                ['status' => 404]
            );
        }

        $updated_steps = [];
        foreach ($step_order as $item) {
            $pipeline_step_id = sanitize_text_field($item['pipeline_step_id']);
            $execution_order = (int) $item['execution_order'];

            $step_found = false;
            foreach ($pipeline_steps as $step) {
                if ($step['pipeline_step_id'] === $pipeline_step_id) {
                    $step['execution_order'] = $execution_order;
                    $updated_steps[] = $step;
                    $step_found = true;
                    break;
                }
            }

            if (!$step_found) {
                return new WP_Error(
                    'step_not_found',
                    sprintf(
                        /* translators: %s: pipeline step ID */
                        __('Step %s not found in pipeline', 'data-machine'),
                        $pipeline_step_id
                    ),
                    ['status' => 400]
                );
            }
        }

        if (count($updated_steps) !== count($pipeline_steps)) {
            return new WP_Error(
                'step_count_mismatch',
                __('Step count mismatch during reorder', 'data-machine'),
                ['status' => 400]
            );
        }

        $success = $this->db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => $updated_steps
        ]);

        if (!$success) {
            return new WP_Error(
                'save_failed',
                __('Failed to save step order', 'data-machine'),
                ['status' => 500]
            );
        }

        \DataMachine\Api\Chat\ChatPipelinesDirective::clear_cache();
        do_action('datamachine_chat_pipelines_inventory_cleared');

        $flows = $this->db_flows->get_flows_for_pipeline($pipeline_id);

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = $flow['flow_config'] ?? [];

            foreach ($flow_config as $flow_step_id => &$flow_step) {
                if (!isset($flow_step['pipeline_step_id']) || empty($flow_step['pipeline_step_id'])) {
                    continue;
                }
                $pipeline_step_id = $flow_step['pipeline_step_id'];

                foreach ($updated_steps as $updated_step) {
                    if ($updated_step['pipeline_step_id'] === $pipeline_step_id) {
                        $flow_step['execution_order'] = $updated_step['execution_order'];
                        break;
                    }
                }
            }
            unset($flow_step);

            $this->db_flows->update_flow($flow_id, [
                'flow_config' => $flow_config
            ]);
        }

        return [
            'pipeline_id' => $pipeline_id,
            'step_count' => count($updated_steps)
        ];
    }
}
