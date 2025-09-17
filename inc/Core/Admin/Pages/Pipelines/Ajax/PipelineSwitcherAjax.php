<?php
/**
 * Pipeline Switcher AJAX Handler
 *
 * Handles pipeline selection and switching AJAX operations.
 * Manages dropdown functionality for pipeline selection and user preferences.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineSwitcherAjax
{
    /**
     * Register pipeline switcher AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Pipeline switcher AJAX actions
        add_action('wp_ajax_dm_switch_pipeline_selection', [$instance, 'handle_switch_pipeline_selection']);
        add_action('wp_ajax_dm_save_pipeline_preference', [$instance, 'handle_save_pipeline_preference']);
    }

    /**
     * Handle pipeline selection switch for dropdown functionality
     */
    public function handle_switch_pipeline_selection() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $selected_pipeline_id = sanitize_text_field(wp_unslash($_POST['selected_pipeline_id'] ?? ''));

        if (!$selected_pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        // Get pipeline list to validate selection
        $all_pipelines = apply_filters('dm_get_pipelines_list', []);
        $selected_pipeline = null;

        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_id'] === $selected_pipeline_id) {
                $selected_pipeline = $pipeline;
                break;
            }
        }

        if (!$selected_pipeline) {
            wp_send_json_error(['message' => __('Invalid pipeline ID', 'data-machine')]);
        }

        // Get full pipeline data for response
        $full_pipeline_data = apply_filters('dm_get_pipelines', [], $selected_pipeline_id);

        // Get flows for the selected pipeline
        $existing_flows = apply_filters('dm_get_pipeline_flows', [], $selected_pipeline_id);


        wp_send_json_success([
            'message' => __('Pipeline switched successfully', 'data-machine'),
            'pipeline_id' => $selected_pipeline_id,
            'pipeline_data' => $full_pipeline_data,
            'existing_flows' => $existing_flows
        ]);
    }

    /**
     * Handle saving user's pipeline selection preference
     */
    public function handle_save_pipeline_preference() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $selected_pipeline_id = sanitize_text_field(wp_unslash($_POST['selected_pipeline_id'] ?? ''));

        if (!$selected_pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        // Validate pipeline exists
        $all_pipelines = apply_filters('dm_get_pipelines_list', []);
        $pipeline_exists = false;

        foreach ($all_pipelines as $pipeline) {
            if ($pipeline['pipeline_id'] === $selected_pipeline_id) {
                $pipeline_exists = true;
                break;
            }
        }

        if (!$pipeline_exists) {
            wp_send_json_error(['message' => __('Invalid pipeline ID', 'data-machine')]);
        }

        // Save user preference
        $updated = update_user_meta(get_current_user_id(), 'dm_selected_pipeline_id', $selected_pipeline_id);

        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to save preference', 'data-machine')]);
        }

        wp_send_json_success([
            'message' => __('Pipeline preference saved', 'data-machine'),
            'pipeline_id' => $selected_pipeline_id
        ]);
    }
}