<?php
/**
 * Settings Page AJAX Handler
 *
 * Handles AJAX operations for the settings page including tool configuration.
 * Placeholder for Phase 2 implementation when tool config is moved from pipelines.
 *
 * @package DataMachine\Core\Admin\Settings
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Settings;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class SettingsPageAjax
{
    /**
     * Register all settings page AJAX handlers.
     *
     * Self-contained registration pattern following WordPress-native approach.
     * Handles tool configuration from Settings page context.
     *
     * @since 1.0.0
     */
    public static function register() {
        $instance = new self();
        
        // Tool configuration AJAX handlers
        add_action('wp_ajax_dm_save_tool_config', [$instance, 'handle_save_tool_config']);
    }

    /**
     * Handle tool configuration form submissions
     */
    public function handle_save_tool_config()
    {
        // Security verification
        if (!check_ajax_referer('dm_ajax_actions', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        $tool_id = sanitize_text_field(wp_unslash($_POST['tool_id'] ?? ''));
        $config_data = $_POST['config_data'] ?? [];
        
        if (empty($tool_id)) {
            wp_send_json_error(['message' => __('Tool ID is required', 'data-machine')]);
        }

        // Sanitize config data array
        if (is_array($config_data)) {
            $config_data = array_map('sanitize_text_field', array_map('wp_unslash', $config_data));
        }

        // Trigger tool-specific save action (same action, different context)
        do_action('dm_save_tool_config', $tool_id, $config_data);
        
        // If we reach here, the tool-specific handler didn't send a response
        wp_send_json_error(['message' => __('Tool configuration handler not found', 'data-machine')]);
    }
}