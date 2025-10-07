<?php
/**
 * Flow-scoped status refresh (single flow config query, no pipeline data).
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class FlowStatusAjax
{
    public static function register() {
        $instance = new self();

        add_action('wp_ajax_dm_refresh_flow_status', [$instance, 'handle_refresh_flow_status']);
    }

    /**
     * Flow-scoped status refresh (handler config, scheduling, flow settings).
     */
    public function handle_refresh_flow_status()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_id = intval($_POST['flow_id'] ?? 0);

        if (!$flow_id) {
            wp_send_json_error(['message' => __('Flow ID required', 'data-machine')]);
        }

        // Get flow configuration (single query, no pipeline data needed)
        $flow_config = apply_filters('dm_get_flow_config', [], $flow_id);

        if (empty($flow_config)) {
            wp_send_json_success([
                'step_statuses' => [],
                'flow_id' => $flow_id
            ]);
            return;
        }

        // Get individual status for each flow step
        $step_statuses = [];
        foreach ($flow_config as $flow_step_id => $step_config) {
            $step_type = $step_config['step_type'] ?? '';

            // Use flow-specific status context for targeted checks
            $step_status = apply_filters('dm_detect_status', 'green', 'flow_step_status', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id,
                'step_type' => $step_type,
                'step_config' => $step_config
            ]);

            $step_statuses[$flow_step_id] = $step_status;
        }

        wp_send_json_success([
            'step_statuses' => $step_statuses,
            'flow_id' => $flow_id
        ]);
    }
}
