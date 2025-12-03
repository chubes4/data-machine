<?php
/**
 * Flow Step Manager Service
 *
 * Centralized business logic for flow step configuration operations.
 * Handles handler updates, user message updates, and step retrieval.
 *
 * @package DataMachine\Services
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class FlowStepManager {

    private \DataMachine\Core\Database\Flows\Flows $db_flows;

    public function __construct() {
        $this->db_flows = new \DataMachine\Core\Database\Flows\Flows();
    }

    /**
     * Get a flow step configuration by ID.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @return array|null Step configuration or null if not found
     */
    public function get(string $flow_step_id): ?array {
        $step_config = $this->db_flows->get_flow_step_config($flow_step_id);
        return !empty($step_config) ? $step_config : null;
    }

    /**
     * Get all step configurations for a flow.
     *
     * @param int $flow_id Flow ID
     * @return array Flow configuration (keyed by flow_step_id)
     */
    public function getForFlow(int $flow_id): array {
        $flow = $this->db_flows->get_flow($flow_id);
        return $flow['flow_config'] ?? [];
    }

    /**
     * Update handler configuration for a flow step.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $handler_slug Handler slug to set
     * @param array $handler_settings Handler configuration settings
     * @return bool Success status
     */
    public function updateHandler(string $flow_step_id, string $handler_slug, array $handler_settings = []): bool {
        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('datamachine_log', 'error', 'Invalid flow_step_id format for handler update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];

        $flow = $this->db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow handler update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        $flow_config = $flow['flow_config'] ?? [];

        if (!isset($flow_config[$flow_step_id])) {
            if (!isset($parts['pipeline_step_id']) || empty($parts['pipeline_step_id'])) {
                do_action('datamachine_log', 'error', 'Pipeline step ID is required for flow handler update', [
                    'flow_step_id' => $flow_step_id,
                    'parts' => $parts
                ]);
                return false;
            }
            $pipeline_step_id = $parts['pipeline_step_id'];
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $flow['pipeline_id'],
                'flow_id' => $flow_id,
                'handler' => null
            ];
        }

        $flow_config[$flow_step_id]['handler_slug'] = $handler_slug;
        $existing_handler_config = $flow_config[$flow_step_id]['handler_config'] ?? [];
        $flow_config[$flow_step_id]['handler_config'] = array_merge($existing_handler_config, $handler_settings);
        $flow_config[$flow_step_id]['enabled'] = true;

        $success = $this->db_flows->update_flow($flow_id, [
            'flow_config' => $flow_config
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'Flow handler update failed - database update failed', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug
            ]);
            return false;
        }

        return true;
    }

    /**
     * Update user message for an AI flow step.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $user_message User message content
     * @return bool Success status
     */
    public function updateUserMessage(string $flow_step_id, string $user_message): bool {
        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('datamachine_log', 'error', 'Invalid flow_step_id format for user message update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];

        $flow = $this->db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        $flow_config = $flow['flow_config'] ?? [];

        if (!isset($flow_config[$flow_step_id])) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - flow step not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        $flow_config[$flow_step_id]['user_message'] = wp_unslash(sanitize_textarea_field($user_message));

        $success = $this->db_flows->update_flow($flow_id, [
            'flow_config' => $flow_config
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - database update error', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        return true;
    }
}
