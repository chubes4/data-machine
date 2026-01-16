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
require_once __DIR__ . '/ChatTitleGenerator.php';

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

		register_rest_route('datamachine/v1', '/chat/(?P<session_id>[a-f0-9-]+)', [
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => [self::class, 'delete_session'],
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

		register_rest_route('datamachine/v1', '/chat/sessions', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [self::class, 'list_sessions'],
			'permission_callback' => function() {
				return current_user_can('manage_options');
			},
			'args' => [
				'limit' => [
					'type' => 'integer',
					'required' => false,
					'default' => 20,
					'description' => __('Maximum sessions to return', 'data-machine'),
					'sanitize_callback' => 'absint'
				],
				'offset' => [
					'type' => 'integer',
					'required' => false,
					'default' => 0,
					'description' => __('Pagination offset', 'data-machine'),
					'sanitize_callback' => 'absint'
				],
				'agent_type' => [
					'type' => 'string',
					'required' => false,
					'default' => \DataMachine\Engine\AI\AgentType::CHAT,
					'description' => __('Agent type filter (chat, cli)', 'data-machine'),
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function($param) {
						return \DataMachine\Engine\AI\AgentType::isValid($param);
					}
				]
			]
		]);
	}

	/**
	 * List all chat sessions for current user
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array|WP_Error Response data or error
	 */
	public static function list_sessions(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$limit = min(100, max(1, (int) $request->get_param('limit')));
		$offset = max(0, (int) $request->get_param('offset'));
		$agent_type = $request->get_param('agent_type');

		$chat_db = new ChatDatabase();
		$sessions = $chat_db->get_user_sessions($user_id, $limit, $offset, $agent_type);
		$total = $chat_db->get_user_session_count($user_id, $agent_type);

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'sessions' => $sessions,
				'total' => $total,
				'limit' => $limit,
				'offset' => $offset,
				'agent_type' => $agent_type
			]
		]);
	}

	/**
	 * Delete a chat session
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array|WP_Error Response data or error
	 */
	public static function delete_session(WP_REST_Request $request) {
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

		$deleted = $chat_db->delete_session($session_id);

		if (!$deleted) {
			return new WP_Error(
				'session_delete_failed',
				__('Failed to delete session', 'data-machine'),
				['status' => 500]
			);
		}

		return rest_ensure_response([
			'success' => true,
			'data' => [
				'session_id' => $session_id,
				'deleted' => true
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
		$request_id = $request->get_header('X-Request-ID');
		if ($request_id) {
			$request_id = sanitize_text_field($request_id);
			$cache_key = 'datamachine_chat_request_' . $request_id;
			$cached_response = get_transient($cache_key);
			if ($cached_response !== false) {
				return rest_ensure_response($cached_response);
			}
		}

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
		$is_new_session = false;

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
			// Check for recent pending session to prevent duplicates from timeout retries
			// This handles the case where Cloudflare times out but PHP continues executing,
			// creating orphaned sessions. On retry, we reuse the pending session.
			$pending_session = $chat_db->get_recent_pending_session($user_id, 600, AgentType::CHAT);

			if ($pending_session) {
				$session_id = $pending_session['session_id'];
				$messages = $pending_session['messages'];
				$is_new_session = false;

				do_action('datamachine_log', 'info', 'Chat: Reusing pending session (deduplication)', [
					'session_id' => $session_id,
					'user_id' => $user_id,
					'original_created_at' => $pending_session['created_at'],
					'agent_type' => AgentType::CHAT
				]);
			} else {
				$session_id = $chat_db->create_session($user_id, [
					'started_at' => current_time('mysql', true),
					'message_count' => 0
				], AgentType::CHAT);

				if (empty($session_id)) {
					return new WP_Error(
						'session_creation_failed',
						__('Failed to create chat session', 'data-machine'),
						['status' => 500]
					);
				}

				$messages = [];
				$is_new_session = true;
			}
		}

		$messages[] = ConversationManager::buildConversationMessage('user', $message, ['type' => 'text']);

		// Persist user message immediately so it survives navigation away from page
		$chat_db->update_session(
			$session_id,
			$messages,
			[
				'status' => 'processing',
				'started_at' => current_time('mysql', true),
				'message_count' => count($messages),
			],
			$provider,
			$model
		);

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
				// Update session with error status before returning
				$chat_db->update_session(
					$session_id,
					$messages,
					[
						'status' => 'error',
						'error_message' => $loop_result['error'],
						'last_activity' => current_time('mysql', true),
						'message_count' => count($messages),
					],
					$provider,
					$model
				);

				do_action('datamachine_log', 'error', 'Chat AI loop returned error', [
					'session_id' => $session_id,
					'error' => $loop_result['error'],
					'agent_type' => AgentType::CHAT
				]);

				return new WP_Error(
					'chubes_ai_request_failed',
					$loop_result['error'],
					['status' => 500]
				);
			}
		} catch (\Throwable $e) {
			// Log the error
			do_action('datamachine_log', 'error', 'Chat AI loop failed with exception', [
				'session_id' => $session_id,
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'agent_type' => AgentType::CHAT
			]);

			// Update session with error status
			$chat_db->update_session(
				$session_id,
				$messages,
				[
					'status' => 'error',
					'error_message' => $e->getMessage(),
					'last_activity' => current_time('mysql', true),
					'message_count' => count($messages),
				],
				$provider,
				$model
			);

			return new WP_Error(
				'chat_error',
				$e->getMessage(),
				['status' => 500]
			);
		} finally {
			// Clear agent context after request completes
			AgentContext::clear();
		}

		// Use final conversation state from loop
		$messages = $loop_result['messages'];
		$final_content = $loop_result['final_content'];

		$metadata = [
			'status' => 'completed',
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

		// Schedule title generation for new sessions after first exchange
		if ($is_new_session && function_exists('as_schedule_single_action')) {
			as_schedule_single_action(
				time(),
				'datamachine_generate_chat_title',
				[$session_id],
				'datamachine-chat'
			);
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

		$response = [
			'success' => true,
			'data' => $response_data
		];

		if ($request_id) {
			set_transient($cache_key, $response, 60);
		}

		return rest_ensure_response($response);
	}
}
