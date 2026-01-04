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
use DataMachine\Engine\AI\AgentType;
use DataMachine\Engine\AI\AgentContext;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

require_once __DIR__ . '/ChatPipelinesDirective.php';

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
	 * Register chat endpoints
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
					'description' => __('User message', 'data-machine'),
					'sanitize_callback' => 'sanitize_textarea_field'
				],
				'session_id' => [
					'type' => 'string',
					'required' => false,
					'description' => __('Optional session ID for conversation continuity', 'data-machine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'provider' => [
					'type' => 'string',
					'required' => false,
					'validate_callback' => function($param) {
						if (empty($param)) {
							return true;
						}
						$providers = apply_filters('chubes_ai_providers', []);
						return isset($providers[$param]);
					},
					'description' => __('AI provider (optional, uses default if not provided)', 'data-machine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'model' => [
					'type' => 'string',
					'required' => false,
					'description' => __('Model identifier (optional, uses default if not provided)', 'data-machine'),
					'sanitize_callback' => 'sanitize_text_field'
				],
				'selected_pipeline_id' => [
					'type' => 'integer',
					'required' => false,
					'description' => __('Currently selected pipeline ID for context', 'data-machine'),
					'sanitize_callback' => 'absint'
				]
			]
		]);

		register_rest_route('datamachine/v1', '/chat/(?P<session_id>[a-f0-9-]+)', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'get_session'],
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
			'args' => [
				'session_id' => [
					'type' => 'string',
					'required' => true,
					'description' => __('Session ID', 'data-machine'),
					'sanitize_callback' => 'sanitize_text_field'
				]
			]
		]);
	}

	/**
	 * Get existing chat session
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array|WP_Error Response data or error
	 */
	public static function get_session(WP_REST_Request $request) {
		$session_id = sanitize_text_field($request->get_param('session_id'));
		$user_id = get_current_user_id();

		$chat_db = new ChatDatabase();
		$session = $chat_db->get_session($session_id);

		if (!$session) {
			return new WP_Error(
				'session_not_found',
				__('Session not found', 'data-machine'),
				['status' => 404]
			);
		}

		if ((int) $session['user_id'] !== $user_id) {
			return new WP_Error(
				'session_access_denied',
				__('Access denied to this session', 'data-machine'),
				['status' => 403]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'session_id' => $session['session_id'],
				'conversation' => $session['messages'],
				'metadata' => $session['metadata']
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
		// Set agent context for logging - all logs during this request go to chat log
		AgentContext::set(AgentType::CHAT);

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

		$session_id = $request->get_param('session_id');
		$selected_pipeline_id = (int) $request->get_param('selected_pipeline_id');
		$user_id = get_current_user_id();

		// Validate that we have provider and model
		if (empty($provider)) {
			return new WP_Error(
				'provider_required',
				__('AI provider is required. Please set a default provider in Data Machine settings or provide one in the request.', 'data-machine'),
				['status' => 400]
			);
		}

		if (empty($model)) {
			return new WP_Error(
				'model_required',
				__('AI model is required. Please set a default model in Data Machine settings or provide one in the request.', 'data-machine'),
				['status' => 400]
			);
		}

		$chat_db = new ChatDatabase();

		if ($session_id) {
			$session = $chat_db->get_session($session_id);

			if (!$session) {
				return new WP_Error(
					'session_not_found',
					__('Session not found', 'data-machine'),
					['status' => 404]
				);
			}

			if ((int) $session['user_id'] !== $user_id) {
				return new WP_Error(
					'session_access_denied',
					__('Access denied to this session', 'data-machine'),
					['status' => 403]
				);
			}

			$messages = $session['messages'];
		} else {
			$session_id = $chat_db->create_session($user_id, [
				'started_at' => current_time('mysql', true),
				'message_count' => 0
			]);

			if (empty($session_id)) {
				return new WP_Error(
					'session_creation_failed',
					__('Failed to create chat session', 'data-machine'),
					['status' => 500]
				);
			}

			$messages = [];
		}

		$messages[] = ConversationManager::buildConversationMessage('user', $message);

		// Load available tools using ToolManager (filters out unconfigured/disabled tools)
		$tool_manager = new ToolManager();
		$all_tools = $tool_manager->getAvailableToolsForChat();

		try {
			// Execute conversation loop with tool execution
			$loop = new AIConversationLoop();
			$loop_result = $loop->execute(
				$messages,
				$all_tools,
				$provider,
				$model,
				AgentType::CHAT,
				[
					'session_id' => $session_id,
					'selected_pipeline_id' => $selected_pipeline_id ?: null,
				],
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
		} finally {
			// Clear agent context after request completes
			AgentContext::clear();
		}

		// Use final conversation state from loop
		$messages = $loop_result['messages'];
		$final_content = $loop_result['final_content'];

		$metadata = [
			'last_activity' => current_time('mysql', true),
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
