<?php
/**
 * Pipeline Reorder AJAX Handler
 *
 * Handles all reordering and organization AJAX operations for pipelines and flows.
 * Centralizes step ordering, flow ordering, and flow movement functionality.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineReorderAjax
{
    /**
     * Register pipeline reordering AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Reordering AJAX actions
        add_action('wp_ajax_dm_reorder_steps', [$instance, 'handle_reorder_steps']);
        add_action('wp_ajax_dm_reorder_flows', [$instance, 'handle_reorder_flows']);
        add_action('wp_ajax_dm_move_flow', [$instance, 'handle_move_flow']);
    }

    /**
     * Reorder pipeline steps - update execution_order values
     */
    public function handle_reorder_steps()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $step_order = json_decode(sanitize_textarea_field(wp_unslash($_POST['step_order'] ?? '[]')), true);

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        if (empty($step_order)) {
            wp_send_json_error(['message' => __('Step order data required', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get current pipeline steps
        $current_steps = apply_filters('dm_get_pipeline_steps', [], $pipeline_id);

        if (empty($current_steps)) {
            wp_send_json_error(['message' => __('No pipeline steps found', 'data-machine')]);
        }

        // Validate that all step IDs in the order exist in the pipeline
        foreach ($step_order as $pipeline_step_id => $new_execution_order) {
            if (!isset($current_steps[$pipeline_step_id])) {
                wp_send_json_error(['message' => __('Invalid step ID in reorder data', 'data-machine')]);
            }
        }

        // Update execution_order values
        $updated_steps = $current_steps;
        foreach ($step_order as $pipeline_step_id => $new_execution_order) {
            $updated_steps[$pipeline_step_id]['execution_order'] = (int) $new_execution_order;
        }

        // Save updated pipeline configuration
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_config' => wp_json_encode($updated_steps)
        ]);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save step order', 'data-machine')]);
        }

        // Clear pipeline cache after successful step reorder
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        wp_send_json_success([
            'message' => __('Step order updated successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'step_count' => count($step_order)
        ]);
    }

    /**
     * Reorder flows within a pipeline - update display_order values
     */
    public function handle_reorder_flows()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $flow_order = json_decode(sanitize_textarea_field(wp_unslash($_POST['flow_order'] ?? '[]')), true);

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        if (empty($flow_order)) {
            wp_send_json_error(['message' => __('Flow order data required', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Prepare flow order data for database update
        $flow_orders = [];
        foreach ($flow_order as $flow_data) {
            if (!isset($flow_data['flow_id']) || !isset($flow_data['display_order'])) {
                wp_send_json_error(['message' => __('Invalid flow order data format', 'data-machine')]);
            }
            $flow_orders[(int)$flow_data['flow_id']] = (int)$flow_data['display_order'];
        }

        // Update flow display orders
        $success = $db_flows->update_flow_display_orders($pipeline_id, $flow_orders);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save flow order', 'data-machine')]);
        }

        // Clear pipeline cache after flow order changes
        do_action('dm_clear_pipeline_cache', $pipeline_id);

        wp_send_json_success([
            'message' => __('Flow order updated successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'flow_count' => count($flow_orders)
        ]);
    }

    /**
     * Move a flow up or down in display order
     */
    public function handle_move_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $direction = sanitize_text_field(wp_unslash($_POST['direction'] ?? ''));

        if (!$flow_id || !$pipeline_id) {
            wp_send_json_error(['message' => __('Flow ID and Pipeline ID required', 'data-machine')]);
        }

        if (!in_array($direction, ['up', 'down'])) {
            wp_send_json_error(['message' => __('Invalid direction. Must be "up" or "down"', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Move the flow
        $success = ($direction === 'up')
            ? $db_flows->move_flow_up($flow_id)
            : $db_flows->move_flow_down($flow_id);

        if (!$success) {
            $message = ($direction === 'up')
                ? __('Cannot move flow up. It may already be at the top or an error occurred.', 'data-machine')
                : __('Cannot move flow down. It may already be at the bottom or an error occurred.', 'data-machine');

            wp_send_json_error(['message' => $message]);
        }

        wp_send_json_success([
            'message' => __('Flow moved successfully', 'data-machine'),
            'flow_id' => $flow_id,
            'direction' => $direction
        ]);
    }
}