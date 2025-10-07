<?php
/**
 * Pipeline Flow Create AJAX Handler
 *
 * Handles all creation and CRUD AJAX operations for pipelines, flows, and steps.
 * Centralizes pipeline creation, flow creation, step addition, and duplication functionality.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineFlowCreateAjax
{
    /**
     * Register pipeline flow creation AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Creation AJAX actions
        add_action('wp_ajax_dm_create_pipeline', [$instance, 'handle_create_pipeline']);
        add_action('wp_ajax_dm_create_pipeline_from_template', [$instance, 'handle_create_pipeline_from_template']);
        add_action('wp_ajax_dm_add_step', [$instance, 'handle_add_step']);
        add_action('wp_ajax_dm_add_flow', [$instance, 'handle_add_flow']);
        add_action('wp_ajax_dm_duplicate_flow', [$instance, 'handle_duplicate_flow']);
    }

    /**
     * Create a new pipeline in the database - delegated to central dm_create filter
     */
    public function handle_create_pipeline()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Delegate to dm_create_pipeline filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $pipeline_id = apply_filters('dm_create_pipeline', false, $_POST);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline', 'data-machine')]);
        }
    }

    /**
     * Create pipeline from template - transforms template data and delegates to dm_create_pipeline filter
     */
    public function handle_create_pipeline_from_template()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
        $pipeline_name = sanitize_text_field(wp_unslash($_POST['pipeline_name'] ?? ''));

        if (empty($template_id)) {
            wp_send_json_error(['message' => __('Template ID is required', 'data-machine')]);
        }

        // Get available templates
        $templates = apply_filters('dm_pipeline_templates', []);

        if (!isset($templates[$template_id])) {
            wp_send_json_error(['message' => __('Template not found', 'data-machine')]);
        }

        $template = $templates[$template_id];

        // Transform template structure to pipeline data format
        $pipeline_data = [
            'pipeline_name' => !empty($pipeline_name) ? $pipeline_name : $template['name']
        ];

        if (!empty($template['steps'])) {
            $pipeline_data['steps'] = [];
            foreach ($template['steps'] as $index => $step) {
                $pipeline_data['steps'][] = [
                    'step_type' => $step['type'],
                    'execution_order' => $index,
                    'label' => ucfirst(str_replace('_', ' ', $step['type']))
                ];
            }
        }

        // Delegate to dm_create_pipeline filter in complete mode
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $pipeline_id = apply_filters('dm_create_pipeline', false, $pipeline_data);

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create pipeline from template', 'data-machine')]);
        }
    }

    /**
     * Add step to pipeline - delegated to central dm_create action
     */
    public function handle_add_step()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Delegate to dm_create_step filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $step_id = apply_filters('dm_create_step', false, $_POST);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$step_id) {
            wp_send_json_error(['message' => __('Failed to add step', 'data-machine')]);
        }
    }

    /**
     * Add flow to pipeline - delegated to central dm_create filter
     */
    public function handle_add_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Delegate to dm_create_flow filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $flow_id = apply_filters('dm_create_flow', false, $_POST);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Failed to add flow', 'data-machine')]);
        }
    }

    /**
     * Duplicate flow - delegated to central dm_duplicate_flow filter
     */
    public function handle_duplicate_flow()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $source_flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (!$source_flow_id) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Delegate to dm_duplicate_flow filter
        // Filter handles AJAX response directly via wp_send_json when in AJAX context
        $new_flow_id = apply_filters('dm_duplicate_flow', false, $source_flow_id);

        // If we reach here, filter didn't send JSON response (shouldn't happen in AJAX)
        if (!$new_flow_id) {
            wp_send_json_error(['message' => __('Failed to duplicate flow', 'data-machine')]);
        }
    }

}