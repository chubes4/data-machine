<?php
/**
 * Step Navigation Service
 *
 * Handles navigation logic for determining next/previous steps during flow execution.
 * Uses engine_data for optimal performance during execution.
 *
 * @package DataMachine\Engine
 * @since 0.2.1
 */

namespace DataMachine\Engine;

if (!defined('WPINC')) {
    die;
}

class StepNavigator {

    /**
     * Get next flow step ID based on execution order
     *
     * Uses engine_data from context for optimal performance during execution.
     *
     * @param string $flow_step_id Current flow step ID
     * @param array $context Context containing engine_data or job_id
     * @return string|null Next flow step ID or null if none
     */
    public function get_next_flow_step_id(string $flow_step_id, array $context = []): ?string {
        $engine_data = $context['engine_data'] ?? [];

        if (empty($engine_data) && !empty($context['job_id'])) {
            $engine_data = apply_filters('datamachine_engine_data', [], $context['job_id']);
        }

        $flow_config = $engine_data['flow_config'] ?? [];

        $current_step = $flow_config[$flow_step_id] ?? null;
        if (!$current_step) {
            return null;
        }

        $current_order = $current_step['execution_order'] ?? -1;
        $next_order = $current_order + 1;

        foreach ($flow_config as $step_id => $step) {
            if (($step['execution_order'] ?? -1) === $next_order) {
                return $step_id;
            }
        }

        return null;
    }

    /**
     * Get previous flow step ID based on execution order
     *
     * Uses engine_data from context for optimal performance during execution.
     *
     * @param string $flow_step_id Current flow step ID
     * @param array $context Context containing engine_data or job_id
     * @return string|null Previous flow step ID or null if none
     */
    public function get_previous_flow_step_id(string $flow_step_id, array $context = []): ?string {
        $engine_data = $context['engine_data'] ?? [];

        if (empty($engine_data) && !empty($context['job_id'])) {
            $engine_data = apply_filters('datamachine_engine_data', [], $context['job_id']);
        }

        $flow_config = $engine_data['flow_config'] ?? [];

        $current_step = $flow_config[$flow_step_id] ?? null;
        if (!$current_step) {
            return null;
        }

        $current_order = $current_step['execution_order'] ?? -1;
        $prev_order = $current_order - 1;

        foreach ($flow_config as $step_id => $step) {
            if (($step['execution_order'] ?? -1) === $prev_order) {
                return $step_id;
            }
        }

        return null;
    }
}
