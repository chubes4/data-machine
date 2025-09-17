<?php
/**
 * Pipeline Delete AJAX Handler
 *
 * Handles deletion AJAX operations for pipelines, flows, and steps.
 * Centralizes all deletion logic with consistent validation and delegation.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineDeleteAjax
{
    /**
     * Register pipeline deletion AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Deletion AJAX actions
        add_action('wp_ajax_dm_delete_pipeline', [$instance, 'handle_delete_pipeline']);
        add_action('wp_ajax_dm_delete_step', [$instance, 'handle_delete_step']);
        add_action('wp_ajax_dm_delete_flow', [$instance, 'handle_delete_flow']);
    }

    /**
     * Delete pipeline - delegated to central dm_delete action
     */
    public function handle_delete_pipeline()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));

        // Delegate to central deletion system
        do_action('dm_delete', 'pipeline', $pipeline_id);
    }

    /**
     * Delete step from pipeline - delegated to central dm_delete action
     */
    public function handle_delete_step()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_step_id = sanitize_text_field(wp_unslash($_POST['pipeline_step_id'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));

        // Delegate to central deletion system
        do_action('dm_delete', 'step', $pipeline_step_id, ['pipeline_id' => $pipeline_id]);
    }

    /**
     * Delete flow - delegated to central dm_delete action
     */
    public function handle_delete_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        // Delegate to central deletion system
        do_action('dm_delete', 'flow', $flow_id);
    }
}