<?php
/**
 * Chat agent filter registration.
 *
 * Registers chat-specific behavior with the universal Engine layer.
 *
 * @package DataMachine\Api\Chat
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register chat API filters and tool enablement.
 *
 * Registers chat-specific tools, directive application, and session management
 * for the conversational AI workflow builder.
 *
 * @since 0.2.0
 */
function datamachine_register_chat_filters() {

    // Register chat-specific tool enablement for universal Engine layer
    add_filter('datamachine_tool_enabled', function($enabled, $tool_name, $tool_config, $context_id) {
        // Chat agent: no context ID (null) - use global tool enablement
        if ($context_id === null) {
            // Check global enablement setting
            $all_settings = get_option('datamachine_settings', []);
            $enabled_tools = $all_settings['enabled_tools'] ?? [];
            $is_globally_enabled = isset($enabled_tools[$tool_name]);

            if (!$is_globally_enabled) {
                return false; // Globally disabled
            }

            // Check configuration status
            $tool_configured = apply_filters('datamachine_tool_configured', false, $tool_name);
            $requires_config = !empty($tool_config['requires_config']);
            return !$requires_config || $tool_configured;
        }

        // Context ID provided: not a chat agent, pass through
        return $enabled;
    }, 5, 4); // Priority 5 so pipeline (priority 10) can override

}

datamachine_register_chat_filters();
