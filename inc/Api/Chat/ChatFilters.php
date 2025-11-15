<?php
/**
 * Chat Filters Registration
 *
 * Registers all chat-related filters, actions, and hooks.
 *
 * @package DataMachine\Api\Chat
 * @since 0.1.2
 */

namespace DataMachine\Api\Chat;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register chat filters and actions
 */
function datamachine_register_chat_filters() {
	add_filter('ai_tools', [Tools\MakeAPIRequest::class, 'register_tool']);

	add_filter('ai_request', [ChatAgentDirective::class, 'inject'], 15, 6);
}

datamachine_register_chat_filters();
