<?php
/**
 * Pipeline-wide status refresh (step add/delete, AI config, architectural changes).
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since NEXT_VERSION
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineStatusAjax
{
    public static function register() {
        $instance = new self();

        add_action('wp_ajax_dm_refresh_pipeline_status', [$instance, 'handle_refresh_pipeline_status']);
    }

    /**
     * Pipeline-wide status with viability and cascade effect checks.
     */
    public function handle_refresh_pipeline_status()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = intval($_POST['pipeline_id'] ?? 0);

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        // Get all pipeline steps
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);

        if (empty($pipeline_steps)) {
            wp_send_json_success([
                'step_statuses' => [],
                'pipeline_id' => $pipeline_id
            ]);
            return;
        }

        // Get individual status for each step
        $step_statuses = [];
        foreach ($pipeline_steps as $pipeline_step_id => $step_config) {
            $step_type = $step_config['step_type'] ?? '';

            $step_status = apply_filters('dm_detect_status', 'green', 'pipeline_step_status', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id,
                'step_type' => $step_type
            ]);

            $step_statuses[$pipeline_step_id] = $step_status;
        }

        wp_send_json_success([
            'step_statuses' => $step_statuses,
            'pipeline_id' => $pipeline_id
        ]);
    }
}
