<?php
/**
 * Flow Manager Service
 *
 * Centralized business logic for flow CRUD operations.
 * Eliminates filter indirection and redundant database queries.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class FlowManager {

    private \DataMachine\Core\Database\Flows\Flows $db_flows;
    private \DataMachine\Core\Database\Pipelines\Pipelines $db_pipelines;

    public function __construct() {
        $this->db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $this->db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
    }

    /**
     * Create a new flow for a pipeline.
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $name Flow name
     * @param array $options Optional settings (scheduling_config, flow_config)
     * @return array|null Complete flow data on success, null on failure
     */
    public function create(int $pipeline_id, string $name, array $options = []): ?array {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow creation');
            return null;
        }

        if ($pipeline_id <= 0) {
            do_action('datamachine_log', 'error', 'Pipeline ID is required for flow creation');
            return null;
        }

        $pipeline = $this->db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            do_action('datamachine_log', 'error', 'Pipeline not found for flow creation', ['pipeline_id' => $pipeline_id]);
            return null;
        }

        $flow_name = sanitize_text_field(wp_unslash($name));
        if (empty(trim($flow_name))) {
            $flow_name = 'Flow';
        }

        $scheduling_config = $options['scheduling_config'] ?? ['interval' => 'manual'];
        $flow_config = $options['flow_config'] ?? [];

        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => $flow_config,
            'scheduling_config' => $scheduling_config
        ];

        $flow_id = $this->db_flows->create_flow($flow_data);
        if (!$flow_id) {
            do_action('datamachine_log', 'error', 'Failed to create flow', [
                'pipeline_id' => $pipeline_id,
                'flow_name' => $flow_name
            ]);
            return null;
        }

        $pipeline_config = $pipeline['pipeline_config'] ?? [];
        if (!empty($pipeline_config)) {
            $pipeline_steps = is_array($pipeline_config) ? array_values($pipeline_config) : [];
            $this->syncStepsToFlow($flow_id, $pipeline_id, $pipeline_steps, $pipeline_config);
        }

        if (isset($scheduling_config['interval']) && $scheduling_config['interval'] !== 'manual') {
            $scheduling_result = \DataMachine\Api\Flows\FlowScheduling::handle_scheduling_update($flow_id, $scheduling_config);
            if (is_wp_error($scheduling_result)) {
                do_action('datamachine_log', 'error', 'Failed to schedule flow with Action Scheduler', [
                    'flow_id' => $flow_id,
                    'error' => $scheduling_result->get_error_message()
                ]);
            }
        }

        $flow = $this->db_flows->get_flow($flow_id);

        do_action('datamachine_log', 'info', 'Flow created successfully', [
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'synced_steps' => count($pipeline_config)
        ]);

        return [
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'flow_data' => $flow,
            'synced_steps' => count($pipeline_config)
        ];
    }

    /**
     * Get a flow by ID.
     *
     * @param int $flow_id Flow ID
     * @return array|null Flow data or null if not found
     */
    public function get(int $flow_id): ?array {
        $flow = $this->db_flows->get_flow($flow_id);
        return $flow ?: null;
    }

    /**
     * Delete a flow.
     *
     * @param int $flow_id Flow ID
     * @return bool Success status
     */
    public function delete(int $flow_id): bool {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow deletion');
            return false;
        }

        $flow = $this->db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow not found for deletion', ['flow_id' => $flow_id]);
            return false;
        }

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('datamachine_run_flow_now', [$flow_id], 'data-machine');
        }

        $success = $this->db_flows->delete_flow($flow_id);

        if ($success) {
            do_action('datamachine_log', 'info', 'Flow deleted successfully', [
                'flow_id' => $flow_id,
                'pipeline_id' => $flow['pipeline_id']
            ]);
        } else {
            do_action('datamachine_log', 'error', 'Failed to delete flow', ['flow_id' => $flow_id]);
        }

        return $success;
    }

    /**
     * Duplicate an existing flow.
     *
     * @param int $source_flow_id Source flow ID to duplicate
     * @return array|null Complete duplicated flow data on success, null on failure
     */
    public function duplicate(int $source_flow_id): ?array {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow duplication');
            return null;
        }

        $source_flow = $this->db_flows->get_flow($source_flow_id);
        if (!$source_flow) {
            do_action('datamachine_log', 'error', 'Source flow not found for duplication', ['source_flow_id' => $source_flow_id]);
            return null;
        }

        $duplicate_flow_name = sprintf('Copy of %s', $source_flow['flow_name']);

        $flow_data = [
            'pipeline_id' => $source_flow['pipeline_id'],
            'flow_name' => $duplicate_flow_name,
            'flow_config' => $source_flow['flow_config'],
            'scheduling_config' => ['interval' => 'manual']
        ];

        $new_flow_id = $this->db_flows->create_flow($flow_data);
        if (!$new_flow_id) {
            do_action('datamachine_log', 'error', 'Failed to create duplicated flow', [
                'source_flow_id' => $source_flow_id,
                'pipeline_id' => $source_flow['pipeline_id']
            ]);
            return null;
        }

        $remapped_config = $this->remapFlowStepIds($source_flow['flow_config'], $source_flow_id, $new_flow_id);

        $update_success = $this->db_flows->update_flow($new_flow_id, [
            'flow_config' => $remapped_config
        ]);

        if (!$update_success) {
            do_action('datamachine_log', 'error', 'Failed to update flow with remapped configuration', [
                'new_flow_id' => $new_flow_id,
                'source_flow_id' => $source_flow_id
            ]);
        }

        $new_flow = $this->db_flows->get_flow($new_flow_id);
        $pipeline_steps = $this->db_pipelines->get_pipeline_config($source_flow['pipeline_id']);

        do_action('datamachine_log', 'info', 'Flow duplicated successfully', [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'pipeline_id' => $source_flow['pipeline_id']
        ]);

        return [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'flow_name' => $duplicate_flow_name,
            'pipeline_id' => $source_flow['pipeline_id'],
            'flow_data' => $new_flow,
            'pipeline_steps' => $pipeline_steps
        ];
    }

    /**
     * Sync pipeline steps to a flow's configuration.
     *
     * @param int $flow_id Flow ID
     * @param int $pipeline_id Pipeline ID
     * @param array $steps Array of pipeline step data
     * @param array $pipeline_config Full pipeline config (for enabled_tools lookup)
     * @return bool Success status
     */
    public function syncStepsToFlow(int $flow_id, int $pipeline_id, array $steps, array $pipeline_config = []): bool {
        $flow = $this->db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow not found for step sync', ['flow_id' => $flow_id]);
            return false;
        }

        $flow_config = $flow['flow_config'] ?? [];

        foreach ($steps as $step) {
            $pipeline_step_id = $step['pipeline_step_id'] ?? null;
            if (!$pipeline_step_id) {
                continue;
            }

            $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);

            $enabled_tools = $pipeline_config[$pipeline_step_id]['enabled_tools'] ?? [];

            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'step_type' => $step['step_type'] ?? '',
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'execution_order' => $step['execution_order'] ?? 0,
                'enabled_tools' => $enabled_tools,
                'handler' => null
            ];
        }

        $success = $this->db_flows->update_flow($flow_id, [
            'flow_config' => $flow_config
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'Flow step sync failed - database update failed', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps)
            ]);
            return false;
        }

        return true;
    }

    /**
     * Remap flow step IDs when duplicating a flow.
     *
     * @param array $source_config Source flow configuration
     * @param int $old_flow_id Original flow ID
     * @param int $new_flow_id New flow ID
     * @return array Remapped configuration
     */
    private function remapFlowStepIds(array $source_config, int $old_flow_id, int $new_flow_id): array {
        $remapped_config = [];

        foreach ($source_config as $old_flow_step_id => $step_config) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $old_flow_step_id);
            if ($parts) {
                $pipeline_step_id = $parts['pipeline_step_id'];
                $new_flow_step_id = $pipeline_step_id . '_' . $new_flow_id;
            } else {
                $new_flow_step_id = $old_flow_step_id . '_' . $new_flow_id;
            }

            $step_config['flow_step_id'] = $new_flow_step_id;
            $step_config['flow_id'] = $new_flow_id;

            $remapped_config[$new_flow_step_id] = $step_config;
        }

        return $remapped_config;
    }
}
