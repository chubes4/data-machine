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
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow creation', [
                'user_id' => get_current_user_id(),
                'pipeline_id' => $pipeline_id
            ]);
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
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow deletion', [
                'user_id' => get_current_user_id(),
                'flow_id' => $flow_id
            ]);
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
     * Duplicate an existing flow (same-pipeline copy).
     *
     * Wrapper around copyToPipeline() for backward compatibility.
     *
     * @param int $source_flow_id Source flow ID to duplicate
     * @return array|null Complete duplicated flow data on success, null on failure
     */
    public function duplicate(int $source_flow_id): ?array {
        $result = $this->copyToPipeline($source_flow_id);

        if (!$result['success']) {
            return null;
        }

        return $result['data'];
    }

    /**
     * Copy a flow to a target pipeline with optional configuration overrides.
     *
     * Supports both same-pipeline duplication and cross-pipeline copying.
     * For cross-pipeline copies, validates that both pipelines have compatible
     * step structures (same step types in same execution order).
     *
     * @param int $source_flow_id Source flow ID to copy
     * @param int|null $target_pipeline_id Target pipeline ID (null = same as source)
     * @param string|null $flow_name Name for new flow (null = "Copy of {source}")
     * @param array $options {
     *     @type array $scheduling_config Schedule config (null = copy from source)
     *     @type array $step_config_overrides Step overrides keyed by step_type or execution_order
     * }
     * @return array{success: bool, data?: array, error?: string}
     */
    public function copyToPipeline(
        int $source_flow_id,
        ?int $target_pipeline_id = null,
        ?string $flow_name = null,
        array $options = []
    ): array {
        if (!current_user_can('manage_options')) {
            do_action('datamachine_log', 'error', 'Insufficient permissions for flow copy', [
                'user_id' => get_current_user_id(),
                'source_flow_id' => $source_flow_id,
                'target_pipeline_id' => $target_pipeline_id
            ]);
            return ['success' => false, 'error' => 'Insufficient permissions'];
        }

        // Get source flow
        $source_flow = $this->db_flows->get_flow($source_flow_id);
        if (!$source_flow) {
            do_action('datamachine_log', 'error', 'Source flow not found for copy', ['source_flow_id' => $source_flow_id]);
            return ['success' => false, 'error' => 'Source flow not found'];
        }

        $source_pipeline_id = (int) $source_flow['pipeline_id'];
        $target_pipeline_id = $target_pipeline_id ?? $source_pipeline_id;
        $is_cross_pipeline = ($target_pipeline_id !== $source_pipeline_id);

        // Get source pipeline
        $source_pipeline = $this->db_pipelines->get_pipeline($source_pipeline_id);
        if (!$source_pipeline) {
            return ['success' => false, 'error' => 'Source pipeline not found'];
        }

        // Get target pipeline
        $target_pipeline = $this->db_pipelines->get_pipeline($target_pipeline_id);
        if (!$target_pipeline) {
            return ['success' => false, 'error' => 'Target pipeline not found'];
        }

        // Validate compatibility for cross-pipeline copies
        if ($is_cross_pipeline) {
            $source_pipeline_config = $source_pipeline['pipeline_config'] ?? [];
            $target_pipeline_config = $target_pipeline['pipeline_config'] ?? [];

            $compatibility = $this->validatePipelineCompatibility($source_pipeline_config, $target_pipeline_config);
            if (!$compatibility['compatible']) {
                do_action('datamachine_log', 'error', 'Pipeline compatibility validation failed', [
                    'source_pipeline_id' => $source_pipeline_id,
                    'target_pipeline_id' => $target_pipeline_id,
                    'error' => $compatibility['error']
                ]);
                return ['success' => false, 'error' => $compatibility['error']];
            }
        }

        // Determine flow name
        $new_flow_name = $flow_name ?? sprintf('Copy of %s', $source_flow['flow_name']);
        $new_flow_name = sanitize_text_field($new_flow_name);

        // Determine scheduling config (copy interval only)
        $requested_scheduling_config = $options['scheduling_config'] ?? ($source_flow['scheduling_config'] ?? []);
        $scheduling_config = $this->getIntervalOnlySchedulingConfig(
            is_array($requested_scheduling_config) ? $requested_scheduling_config : []
        );

        // Create the new flow
        $flow_data = [
            'pipeline_id' => $target_pipeline_id,
            'flow_name' => $new_flow_name,
            'flow_config' => [],
            'scheduling_config' => $scheduling_config
        ];

        $new_flow_id = $this->db_flows->create_flow($flow_data);
        if (!$new_flow_id) {
            do_action('datamachine_log', 'error', 'Failed to create flow during copy', [
                'source_flow_id' => $source_flow_id,
                'target_pipeline_id' => $target_pipeline_id
            ]);
            return ['success' => false, 'error' => 'Failed to create new flow'];
        }

        // Build new flow config by mapping source steps to target pipeline steps
        $new_flow_config = $this->buildCopiedFlowConfig(
            $source_flow['flow_config'] ?? [],
            $source_pipeline['pipeline_config'] ?? [],
            $target_pipeline['pipeline_config'] ?? [],
            $new_flow_id,
            $target_pipeline_id,
            $options['step_config_overrides'] ?? []
        );

        // Update flow with the new config
        $update_success = $this->db_flows->update_flow($new_flow_id, [
            'flow_config' => $new_flow_config
        ]);

        if (!$update_success) {
            do_action('datamachine_log', 'error', 'Failed to update flow config during copy', [
                'new_flow_id' => $new_flow_id,
                'source_flow_id' => $source_flow_id
            ]);
        }

        // Handle scheduling if not manual
        if (isset($scheduling_config['interval']) && $scheduling_config['interval'] !== 'manual') {
            $scheduling_result = \DataMachine\Api\Flows\FlowScheduling::handle_scheduling_update($new_flow_id, $scheduling_config);
            if (is_wp_error($scheduling_result)) {
                do_action('datamachine_log', 'error', 'Failed to schedule copied flow', [
                    'flow_id' => $new_flow_id,
                    'error' => $scheduling_result->get_error_message()
                ]);
            }
        }

        $new_flow = $this->db_flows->get_flow($new_flow_id);

        do_action('datamachine_log', 'info', 'Flow copied successfully', [
            'source_flow_id' => $source_flow_id,
            'new_flow_id' => $new_flow_id,
            'source_pipeline_id' => $source_pipeline_id,
            'target_pipeline_id' => $target_pipeline_id,
            'cross_pipeline' => $is_cross_pipeline
        ]);

        return [
            'success' => true,
            'data' => [
                'source_flow_id' => $source_flow_id,
                'new_flow_id' => $new_flow_id,
                'flow_name' => $new_flow_name,
                'source_pipeline_id' => $source_pipeline_id,
                'target_pipeline_id' => $target_pipeline_id,
                'flow_data' => $new_flow,
                'flow_step_ids' => array_keys($new_flow_config),
                'scheduling' => $scheduling_config['interval'] ?? 'manual'
            ]
        ];
    }

     /**
      * Get an interval-only scheduling config for copied flows.
      *
      * Copied flows intentionally do not inherit run history metadata.
      */
     private function getIntervalOnlySchedulingConfig(array $scheduling_config): array {
         $interval = $scheduling_config['interval'] ?? 'manual';
 
         if (!is_string($interval) || $interval === '') {
             $interval = 'manual';
         }
 
         return [
             'interval' => $interval,
         ];
     }
 
     /**
      * Validate that two pipelines have compatible step structures.
      *
      * @param array $source_config Source pipeline config
      * @param array $target_config Target pipeline config
      * @return array{compatible: bool, error?: string}
      */
    private function validatePipelineCompatibility(array $source_config, array $target_config): array {
        $source_steps = $this->getOrderedStepTypes($source_config);
        $target_steps = $this->getOrderedStepTypes($target_config);

        if ($source_steps === $target_steps) {
            return ['compatible' => true];
        }

        return [
            'compatible' => false,
            'error' => sprintf(
                'Incompatible pipeline structures. Source: [%s], Target: [%s]',
                implode(', ', $source_steps),
                implode(', ', $target_steps)
            )
        ];
    }

    /**
     * Get ordered step types from pipeline config.
     *
     * @param array $pipeline_config Pipeline configuration
     * @return array Step types ordered by execution_order
     */
    private function getOrderedStepTypes(array $pipeline_config): array {
        $steps = array_values($pipeline_config);
        usort($steps, fn($a, $b) => ($a['execution_order'] ?? 0) <=> ($b['execution_order'] ?? 0));
        return array_map(fn($s) => $s['step_type'] ?? '', $steps);
    }

    /**
     * Build flow config for copied flow, mapping source to target pipeline steps.
     *
     * @param array $source_flow_config Source flow configuration
     * @param array $source_pipeline_config Source pipeline configuration
     * @param array $target_pipeline_config Target pipeline configuration
     * @param int $new_flow_id New flow ID
     * @param int $target_pipeline_id Target pipeline ID
     * @param array $overrides Step configuration overrides
     * @return array New flow configuration
     */
    private function buildCopiedFlowConfig(
        array $source_flow_config,
        array $source_pipeline_config,
        array $target_pipeline_config,
        int $new_flow_id,
        int $target_pipeline_id,
        array $overrides = []
    ): array {
        $new_flow_config = [];

        // Build execution_order to pipeline_step_id mapping for target
        $target_steps_by_order = [];
        foreach ($target_pipeline_config as $pipeline_step_id => $step) {
            $order = $step['execution_order'] ?? 0;
            $target_steps_by_order[$order] = [
                'pipeline_step_id' => $pipeline_step_id,
                'step_type' => $step['step_type'] ?? ''
            ];
        }

        // Build execution_order to source flow step mapping
        $source_steps_by_order = [];
        foreach ($source_flow_config as $flow_step_id => $step_config) {
            $order = $step_config['execution_order'] ?? 0;
            $source_steps_by_order[$order] = $step_config;
        }

        // Map source flow steps to target pipeline steps by execution_order
        foreach ($target_steps_by_order as $order => $target_step) {
            $target_pipeline_step_id = $target_step['pipeline_step_id'];
            $step_type = $target_step['step_type'];
            $new_flow_step_id = $target_pipeline_step_id . '_' . $new_flow_id;

            // Start with base step config
            $new_step_config = [
                'flow_step_id' => $new_flow_step_id,
                'step_type' => $step_type,
                'pipeline_step_id' => $target_pipeline_step_id,
                'pipeline_id' => $target_pipeline_id,
                'flow_id' => $new_flow_id,
                'execution_order' => $order
            ];

            // Copy configuration from source flow step (if exists)
            if (isset($source_steps_by_order[$order])) {
                $source_step = $source_steps_by_order[$order];

                if (!empty($source_step['handler_slug'])) {
                    $new_step_config['handler_slug'] = $source_step['handler_slug'];
                }
                if (!empty($source_step['handler_config'])) {
                    $new_step_config['handler_config'] = $source_step['handler_config'];
                }
                if (!empty($source_step['user_message'])) {
                    $new_step_config['user_message'] = $source_step['user_message'];
                }
                if (isset($source_step['enabled_tools'])) {
                    $new_step_config['enabled_tools'] = $source_step['enabled_tools'];
                }
            }

            // Apply overrides (keyed by step_type or execution_order)
            $override = $this->resolveOverride($overrides, $step_type, $order);
            if ($override) {
                if (!empty($override['handler_slug'])) {
                    $new_step_config['handler_slug'] = $override['handler_slug'];
                }
                if (!empty($override['handler_config'])) {
                    // Merge handler_config
                    $existing_config = $new_step_config['handler_config'] ?? [];
                    $new_step_config['handler_config'] = array_merge($existing_config, $override['handler_config']);
                }
                if (!empty($override['user_message'])) {
                    $new_step_config['user_message'] = $override['user_message'];
                }
            }

            $new_flow_config[$new_flow_step_id] = $new_step_config;
        }

        return $new_flow_config;
    }

    /**
     * Resolve override config by step_type or execution_order.
     *
     * @param array $overrides Override configurations
     * @param string $step_type Step type
     * @param int $execution_order Execution order
     * @return array|null Override config or null
     */
    private function resolveOverride(array $overrides, string $step_type, int $execution_order): ?array {
        // Check by step_type first
        if (isset($overrides[$step_type])) {
            return $overrides[$step_type];
        }

        // Check by execution_order (as string key)
        if (isset($overrides[(string) $execution_order])) {
            return $overrides[(string) $execution_order];
        }

        // Check by execution_order (as int key)
        if (isset($overrides[$execution_order])) {
            return $overrides[$execution_order];
        }

        return null;
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
