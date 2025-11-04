<?php
/**
 * Pipeline Step Reorder AJAX Handler
 *
 * Handles drag-and-drop step reordering persistence, updating execution_order
 * in pipeline configurations and synchronizing changes to flow instances.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 0.1.2
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineReorderAjax
{
    /**
     * Register pipeline step reorder AJAX handler.
     */
    public static function register() {
        $instance = new self();
        add_action('wp_ajax_dm_reorder_pipeline_steps', [$instance, 'handle_reorder_steps']);
    }

    /**
     * Handle pipeline step reordering
     */
    public function handle_reorder_steps()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $step_order_json = sanitize_text_field(wp_unslash($_POST['step_order'] ?? ''));

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        if (empty($step_order_json)) {
            wp_send_json_error(['message' => __('Step order required', 'data-machine')]);
        }

        // Parse step order (array of pipeline_step_ids in new sequence)
        $step_order = json_decode($step_order_json, true);
        if (!is_array($step_order) || empty($step_order)) {
            wp_send_json_error(['message' => __('Invalid step order format', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Retrieve current pipeline configuration
        $pipeline_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);
        if (empty($pipeline_steps)) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }

        // Update execution_order based on new sequence
        $updated_steps = [];
        foreach ($step_order as $index => $pipeline_step_id) {
            $pipeline_step_id = sanitize_text_field($pipeline_step_id);

            // Find step in current configuration
            $step_found = false;
            foreach ($pipeline_steps as $step) {
                if ($step['pipeline_step_id'] === $pipeline_step_id) {
                    $step['execution_order'] = $index;
                    $updated_steps[] = $step;
                    $step_found = true;
                    break;
                }
            }

            if (!$step_found) {
                wp_send_json_error(['message' => sprintf(
                    __('Step %s not found in pipeline', 'data-machine'),
                    $pipeline_step_id
                )]);
            }
        }

        // Verify we updated all steps
        if (count($updated_steps) !== count($pipeline_steps)) {
            wp_send_json_error(['message' => __('Step count mismatch during reorder', 'data-machine')]);
        }

        // Save updated pipeline configuration
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => $updated_steps
        ]);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save step order', 'data-machine')]);
        }

        // Clear pipeline cache
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        // Sync execution_order to flows (targeted update - no full AutoSave)
        $flows = apply_filters('dm_get_pipeline_flows', [], $pipeline_id);

        foreach ($flows as $flow) {
            $flow_id = $flow['flow_id'];
            $flow_config = $flow['flow_config'] ?? [];

            // Update only execution_order in flow steps
            foreach ($flow_config as $flow_step_id => &$flow_step) {
                $pipeline_step_id = $flow_step['pipeline_step_id'] ?? null;

                // Find matching updated step and sync execution_order
                foreach ($updated_steps as $updated_step) {
                    if ($updated_step['pipeline_step_id'] === $pipeline_step_id) {
                        $flow_step['execution_order'] = $updated_step['execution_order'];
                        break;
                    }
                }
            }
            unset($flow_step);

            // Update flow with ONLY execution_order changes
            apply_filters('dm_update_flow', false, $flow_id, [
                'flow_config' => $flow_config
            ]);

            // Clear only flow config cache
            do_action('dm_clear_flow_config_cache', $flow_id);
        }

        wp_send_json_success([
            'message' => __('Step order saved successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'step_count' => count($updated_steps)
        ]);
    }
}
