<?php
/**
 * Settings Page AJAX Handler - Tool configuration management via AJAX requests.
 *
 * Handles secure AJAX requests for AI tool configuration including validation,
 * sanitization, and delegated processing through filter-based handlers.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Settings;

if (!defined('WPINC')) {
    die;
}

class SettingsPageAjax
{
    /**
     * Register AJAX handlers for tool configuration.
     */
    public static function register() {
        $instance = new self();
        
        add_action('wp_ajax_dm_save_tool_config', [$instance, 'handle_save_tool_config']);
    }

    /**
     * Handle tool configuration save requests with security validation.
     *
     * Validates nonce, permissions, and input data before delegating to
     * filter-based handlers via dm_save_tool_config action.
     *
     * @return void Sends JSON response via wp_send_json_*
     */
    public function handle_save_tool_config()
    {
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $tool_id = sanitize_text_field(wp_unslash($_POST['tool_id'] ?? ''));
        $config_data = wp_unslash($_POST['config_data'] ?? []);
        
        if (empty($tool_id)) {
            wp_send_json_error(['message' => __('Tool ID is required', 'data-machine')]);
        }

        if (is_array($config_data)) {
            $config_data = array_map('sanitize_text_field', array_map('wp_unslash', $config_data));
        }

        do_action('dm_save_tool_config', $tool_id, $config_data);
        
        wp_send_json_error(['message' => __('Tool configuration handler not found', 'data-machine')]);
    }
}