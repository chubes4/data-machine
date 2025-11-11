<?php
/**
 * General AI tools fallback filters.
 * Individual tools now self-register via their own classes.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 */

defined('ABSPATH') || exit;

/**
 * Fallback for datamachine_save_tool_config - if no tool handles the save, show error
 */
add_action('datamachine_save_tool_config', function($tool_id, $config_data) {
    wp_send_json_error(['message' => __('Unknown tool configuration', 'data-machine')]);
}, 999, 2);