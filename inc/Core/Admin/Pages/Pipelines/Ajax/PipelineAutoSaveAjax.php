<?php
/**
 * Pipeline Auto-Save AJAX Handler
 *
 * Handles real-time auto-save AJAX operations for pipelines, flows, and AI content.
 * Integrates with JavaScript PipelineAutoSave controller for seamless user experience.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineAutoSaveAjax
{
    /**
     * Register pipeline auto-save AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // Auto-save AJAX actions
        add_action('wp_ajax_dm_save_pipeline_title', [$instance, 'handle_save_pipeline_title']);
        add_action('wp_ajax_dm_save_flow_title', [$instance, 'handle_save_flow_title']);
        add_action('wp_ajax_dm_save_user_message', [$instance, 'handle_save_user_message']);
        add_action('wp_ajax_dm_save_system_prompt', [$instance, 'handle_save_system_prompt']);
    }

    /**
     * Save pipeline title via auto-save functionality
     */
    public function handle_save_pipeline_title()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_id = (int) sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $pipeline_title = sanitize_text_field(wp_unslash($_POST['pipeline_title'] ?? ''));

        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Pipeline ID required', 'data-machine')]);
        }

        if (empty($pipeline_title)) {
            wp_send_json_error(['message' => __('Pipeline title cannot be empty', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;

        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Update pipeline title
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'pipeline_name' => $pipeline_title
        ]);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save pipeline title', 'data-machine')]);
        }

    do_action('dm_clear_pipeline_cache', $pipeline_id);
    do_action('dm_clear_pipelines_list_cache');

        wp_send_json_success([
            'message' => __('Pipeline title saved successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_title' => $pipeline_title
        ]);
    }

    /**
     * Save flow title via auto-save functionality
     */
    public function handle_save_flow_title()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $flow_title = sanitize_text_field(wp_unslash($_POST['flow_title'] ?? ''));

        if (!$flow_id) {
            wp_send_json_error(['message' => __('Flow ID required', 'data-machine')]);
        }

        if (empty($flow_title)) {
            wp_send_json_error(['message' => __('Flow title cannot be empty', 'data-machine')]);
        }

        // Get database service
        $all_databases = apply_filters('dm_db', []);
        $db_flows = $all_databases['flows'] ?? null;

        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get existing flow to extract pipeline_id for auto-save
        $flow = apply_filters('dm_get_flow', null, $flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }

        // Update flow title
        $success = apply_filters('dm_update_flow', false, $flow_id, [
            'flow_name' => $flow_title
        ]);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to save flow title', 'data-machine')]);
        }

        $pipeline_id = (int) $flow['pipeline_id'];

        wp_send_json_success([
            'message' => __('Flow title saved successfully', 'data-machine'),
            'flow_id' => $flow_id,
            'flow_title' => $flow_title,
            'pipeline_id' => $pipeline_id
        ]);
    }

    /**
     * Handle user message auto-save for AI flow steps
     */
    public function handle_save_user_message() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));
        $user_message = sanitize_textarea_field(wp_unslash($_POST['user_message'] ?? ''));

        if (!$flow_step_id) {
            wp_send_json_error(['message' => __('Flow step ID required', 'data-machine')]);
        }

        // Use centralized flow user message update action with validation
        $success = apply_filters('dm_update_flow_user_message_result', false, $flow_step_id, $user_message);

        if ($success) {
            // Extract flow_id from flow_step_id to get pipeline_id for cache clearing
            $parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
            if ($parts && !empty($parts['flow_id'])) {
                $flow_id = $parts['flow_id'];

                // Get flow data to extract pipeline_id
                $all_databases = apply_filters('dm_db', []);
                $db_flows = $all_databases['flows'] ?? null;

                if ($db_flows) {
                    $flow = apply_filters('dm_get_flow', null, $flow_id);
                    if ($flow && !empty($flow['pipeline_id'])) {
                        $pipeline_id = (int) $flow['pipeline_id'];


                        do_action('dm_clear_pipeline_cache', $pipeline_id);
                    }
                }
            }

            wp_send_json_success([
                'message' => __('User message saved successfully', 'data-machine'),
                'flow_step_id' => $flow_step_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save user message', 'data-machine')]);
        }
    }

    /**
     * Handle system prompt auto-save for AI pipeline steps
     */
    public function handle_save_system_prompt() {
        // Security checks
        check_ajax_referer('dm_ajax_actions', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $pipeline_step_id = sanitize_text_field(wp_unslash($_POST['pipeline_step_id'] ?? ''));
        $system_prompt = sanitize_textarea_field(wp_unslash($_POST['system_prompt'] ?? ''));

        if (!$pipeline_step_id) {
            wp_send_json_error(['message' => __('Pipeline step ID required', 'data-machine')]);
        }

        // Use centralized system prompt update action with validation
        $success = apply_filters('dm_update_system_prompt_result', false, $pipeline_step_id, $system_prompt);

        if ($success) {
            // Get pipeline_id from pipeline_step_id for cache clearing
            $pipeline_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);

            if (!empty($pipeline_step_config['pipeline_id'])) {
                $pipeline_id = (int) $pipeline_step_config['pipeline_id'];

                do_action('dm_clear_pipeline_cache', $pipeline_id);
            }

            wp_send_json_success([
                'message' => __('System prompt saved successfully', 'data-machine'),
                'pipeline_step_id' => $pipeline_step_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save system prompt', 'data-machine')]);
        }
    }
}