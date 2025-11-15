<?php
/**
 * Chat REST API Endpoint
 *
 * Conversational AI endpoint for building and executing Data Machine workflows
 * through natural language interaction.
 *
 * @package DataMachine\Api
 * @since 0.1.2
 */

namespace DataMachine\Api;

use DataMachine\Core\Database\Chat\Chat as ChatDatabase;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Chat API Handler
 */
class Chat {

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	/**
	 * Register chat endpoint
	 */
	public static function register_routes() {
		register_rest_route('datamachine/v1', '/chat', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [self::class, 'handle_chat'],
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
			'args' => [
				'message' => [
					'type' => 'string',
					'required' => true,
					'description' => __('User message', 'datamachine'),
					'sanitize_callback' => 'sanitize_textarea_field'
				],
				'session_id' => [
					'type' => 'string',
					'required' => false,
					'description' => __('Optional session ID for conversation continuity', 'datamachine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'provider' => [
					'type' => 'string',
					'required' => true,
					'enum' => ['openai', 'anthropic', 'google', 'grok', 'openrouter'],
					'description' => __('AI provider', 'datamachine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'model' => [
					'type' => 'string',
					'required' => true,
					'description' => __('Model identifier', 'datamachine'),
					'sanitize_callback' => 'sanitize_text_field'
				]
			]
		]);
	}

	/**
	 * Handle chat request
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array|WP_Error Response data or error
	 */
	public static function handle_chat(WP_REST_Request $request) {
		$message = sanitize_textarea_field(wp_unslash($request->get_param('message')));
		$session_id = $request->get_param('session_id');
		$provider = sanitize_text_field($request->get_param('provider'));
		$model = sanitize_text_field($request->get_param('model'));
		$user_id = get_current_user_id();

		$chat_db = new ChatDatabase();

		if ($session_id) {
			$session = $chat_db->get_session($session_id);

			if (!$session) {
				return new WP_Error(
					'session_not_found',
					__('Session not found or expired', 'datamachine'),
					['status' => 404]
				);
			}

			if ($session['user_id'] !== $user_id) {
				return new WP_Error(
					'session_access_denied',
					__('Access denied to this session', 'datamachine'),
					['status' => 403]
				);
			}

			$messages = $session['messages'];
		} else {
			$session_id = $chat_db->create_session($user_id, [
				'started_at' => current_time('mysql'),
				'message_count' => 0
			]);

			if (empty($session_id)) {
				return new WP_Error(
					'session_creation_failed',
					__('Failed to create chat session', 'datamachine'),
					['status' => 500]
				);
			}

			$messages = [];
		}

		$messages[] = [
			'role' => 'user',
			'content' => $message
		];

		$ai_request = [
			'model' => $model,
			'messages' => $messages,
		];

		$all_tools = apply_filters('ai_tools', [], null, []);

		$chat_tools = [];
		foreach ($all_tools as $tool_name => $tool_def) {
			if (isset($tool_def['chat_enabled']) && $tool_def['chat_enabled'] === true) {
				$chat_tools[$tool_name] = $tool_def;
			}
		}

		do_action('datamachine_log', 'debug', 'Chat endpoint processing message', [
			'session_id' => $session_id,
			'user_id' => $user_id,
			'provider' => $provider,
			'model' => $model,
			'message_length' => strlen($message),
			'available_tools' => array_keys($chat_tools)
		]);

		$response = apply_filters('ai_request', $ai_request, $provider, null, $chat_tools, [
			'context' => 'chat',
			'session_id' => $session_id
		]);

		if (is_wp_error($response)) {
			do_action('datamachine_log', 'error', 'Chat AI request failed', [
				'session_id' => $session_id,
				'provider' => $provider,
				'error' => $response->get_error_message()
			]);

			return $response;
		}

		$ai_message = $response['choices'][0]['message'] ?? null;

		if (!$ai_message) {
			do_action('datamachine_log', 'error', 'Invalid AI response format', [
				'session_id' => $session_id,
				'response' => $response
			]);

			return new WP_Error(
				'invalid_ai_response',
				__('Invalid AI response format', 'datamachine'),
				['status' => 500]
			);
		}

		$messages[] = $ai_message;

		$metadata = [
			'last_activity' => current_time('mysql'),
			'message_count' => count($messages)
		];

		$update_success = $chat_db->update_session(
			$session_id,
			$messages,
			$metadata,
			$provider,
			$model
		);

		if (!$update_success) {
			do_action('datamachine_log', 'warning', 'Failed to update chat session', [
				'session_id' => $session_id
			]);
		}

		do_action('datamachine_log', 'info', 'Chat message processed successfully', [
			'session_id' => $session_id,
			'message_count' => count($messages),
			'tool_calls' => count($ai_message['tool_calls'] ?? [])
		]);

		return rest_ensure_response([
			'success' => true,
			'session_id' => $session_id,
			'response' => $ai_message['content'] ?? '',
			'tool_calls' => $ai_message['tool_calls'] ?? [],
			'conversation' => $messages,
			'metadata' => $metadata
		]);
	}
}
