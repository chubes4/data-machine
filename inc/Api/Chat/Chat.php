<?php
/**
 * Chat REST API Endpoint
 *
 * Conversational AI endpoint for building and executing Data Machine workflows
 * through natural language interaction.
 *
 * @package DataMachine\Api\Chat
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat;

use DataMachine\Core\Database\Chat\Chat as ChatDatabase;
use DataMachine\Core\PluginSettings;
use DataMachine\Engine\AI\ConversationManager;
use DataMachine\Engine\AI\AIConversationLoop;
use DataMachine\Engine\AI\Tools\ToolManager;
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
					'required' => false,
					'enum' => ['openai', 'anthropic', 'google', 'grok', 'openrouter'],
					'description' => __('AI provider (optional, uses default if not provided)', 'datamachine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'model' => [
					'type' => 'string',
					'required' => false,
					'description' => __('Model identifier (optional, uses default if not provided)', 'datamachine'),
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

		// Get provider and model with defaults
		$provider = $request->get_param('provider');
		$model = $request->get_param('model');
		$max_turns = PluginSettings::get('max_turns', 12);

		if (empty($provider)) {
			$provider = PluginSettings::get('default_provider', '');
		}
		if (empty($model)) {
			$model = PluginSettings::get('default_model', '');
		}

		$provider = sanitize_text_field($provider);
		$model = sanitize_text_field($model);
		$user_id = get_current_user_id();

		// Validate that we have provider and model
		if (empty($provider)) {
			return new WP_Error(
				'provider_required',
				__('AI provider is required. Please set a default provider in Data Machine settings or provide one in the request.', 'datamachine'),
				['status' => 400]
			);
		}

		if (empty($model)) {
			return new WP_Error(
				'model_required',
				__('AI model is required. Please set a default model in Data Machine settings or provide one in the request.', 'datamachine'),
				['status' => 400]
			);
		}

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

			if ((int) $session['user_id'] !== $user_id) {
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

		$messages[] = ConversationManager::buildConversationMessage('user', $message);

		// Load available tools using ToolManager (filters out unconfigured/disabled tools)
		$tool_manager = new ToolManager();
		$all_tools = $tool_manager->getAvailableToolsForChat();

		// Execute conversation loop with tool execution
		$loop = new AIConversationLoop();
		$loop_result = $loop->execute(
			$messages,
			$all_tools,
			$provider,
			$model,
			'chat',
			['session_id' => $session_id],
			$max_turns
		);

		// Check for errors
		if (isset($loop_result['error'])) {
			return new WP_Error(
				'chubes_ai_request_failed',
				$loop_result['error'],
				['status' => 500]
			);
		}

		// Use final conversation state from loop
		$messages = $loop_result['messages'];
		$final_content = $loop_result['final_content'];

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
		}

		$response_data = [
			'session_id' => $session_id,
			'response' => $final_content,
			'tool_calls' => $loop_result['last_tool_calls'],
			'conversation' => $messages,
			'metadata' => $metadata,
			'completed' => $loop_result['completed'] ?? true
		];

		if (isset($loop_result['warning'])) {
			$response_data['warning'] = $loop_result['warning'];
		}

		return rest_ensure_response([
			'success' => true,
			'data' => $response_data
		]);
	}
}
