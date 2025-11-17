<?php
/**
 * AI Conversation Loop
 *
 * Centralized tool execution loop for AI agents.
 * Handles multi-turn conversations with tool execution and result feedback.
 *
 * @package DataMachine\Engine\AI
 * @since 0.2.0
 */

namespace DataMachine\Engine\AI;

use DataMachine\Engine\AI\ToolExecutor;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * AI Conversation Loop Class
 *
 * Executes multi-turn AI conversations with automatic tool execution.
 * Used by both Pipeline AI and Chat API for consistent tool handling.
 */
class AIConversationLoop {

	/**
	 * Execute conversation loop
	 *
	 * @param array  $messages      Initial conversation messages
	 * @param array  $tools          Available tools for AI
	 * @param string $provider       AI provider (openai, anthropic, etc.)
	 * @param string $model          AI model identifier
	 * @param string $agent_type     Agent type: 'pipeline' or 'chat'
	 * @param array  $context        Agent-specific context data
	 * @param int    $max_turns      Maximum conversation turns (default 8)
	 * @return array {
	 *     @type array  $messages        Final conversation state
	 *     @type string $final_content   Last AI text response
	 *     @type int    $turn_count      Number of turns executed
	 *     @type bool   $completed       Whether loop finished naturally (no tool calls)
	 *     @type array  $last_tool_calls Last set of tool calls (if any)
	 * }
	 */
	public function execute(
		array $messages,
		array $tools,
		string $provider,
		string $model,
		string $agent_type,
		array $context = [],
		int $max_turns = 12
	): array {
		$conversation_complete = false;
		$turn_count = 0;
		$final_content = '';
		$last_tool_calls = [];

		do_action('datamachine_log', 'debug', 'AIConversationLoop: Starting conversation loop', [
			'agent_type' => $agent_type,
			'provider' => $provider,
			'model' => $model,
			'initial_message_count' => count($messages),
			'tool_count' => count($tools),
			'max_turns' => $max_turns
		]);

		do {
			$turn_count++;

			do_action('datamachine_log', 'debug', 'AIConversationLoop: Turn started', [
				'agent_type' => $agent_type,
				'turn_count' => $turn_count,
				'message_count' => count($messages)
			]);

			// Build AI request using centralized RequestBuilder
			$ai_response = RequestBuilder::build(
				$messages,
				$provider,
				$model,
				$tools,
				$agent_type,
				$context
			);

			// Handle AI request failure
			if (!$ai_response['success']) {
				do_action('datamachine_log', 'error', 'AIConversationLoop: AI request failed', [
					'agent_type' => $agent_type,
					'turn_count' => $turn_count,
					'error' => $ai_response['error'] ?? 'Unknown error',
					'provider' => $ai_response['provider'] ?? 'Unknown'
				]);

				return [
					'messages' => $messages,
					'final_content' => '',
					'turn_count' => $turn_count,
					'completed' => false,
					'last_tool_calls' => [],
					'error' => $ai_response['error'] ?? 'AI request failed'
				];
			}

			$tool_calls = $ai_response['data']['tool_calls'] ?? [];
			$ai_content = $ai_response['data']['content'] ?? '';

			// Store final content from this turn
			if (!empty($ai_content)) {
				$final_content = $ai_content;
			}

			// Add AI message to conversation if it has content
			if (!empty($ai_content)) {
				$ai_message = ConversationManager::buildConversationMessage('assistant', $ai_content);
				$messages[] = $ai_message;

				do_action('datamachine_log', 'debug', 'AIConversationLoop: AI returned content', [
					'agent_type' => $agent_type,
					'turn_count' => $turn_count,
					'content_length' => strlen($ai_content),
					'has_tool_calls' => !empty($tool_calls)
				]);
			}

			// Process tool calls
			if (!empty($tool_calls)) {
				$last_tool_calls = $tool_calls;

				do_action('datamachine_log', 'debug', 'AIConversationLoop: Processing tool calls', [
					'agent_type' => $agent_type,
					'turn_count' => $turn_count,
					'tool_call_count' => count($tool_calls),
					'tools' => array_column($tool_calls, 'name')
				]);

				foreach ($tool_calls as $tool_call) {
					$tool_name = $tool_call['name'] ?? '';
					$tool_parameters = $tool_call['parameters'] ?? [];

					if (empty($tool_name)) {
						do_action('datamachine_log', 'warning', 'AIConversationLoop: Tool call missing name', [
							'agent_type' => $agent_type,
							'turn_count' => $turn_count,
							'tool_call' => $tool_call
						]);
						continue;
					}

					// Validate for duplicate tool calls
					$validation_result = ConversationManager::validateToolCall(
						$tool_name,
						$tool_parameters,
						$messages
					);

					if ($validation_result['is_duplicate']) {
						$correction_message = ConversationManager::generateDuplicateToolCallMessage($tool_name);
						$messages[] = $correction_message;

						do_action('datamachine_log', 'info', 'AIConversationLoop: Duplicate tool call prevented', [
							'agent_type' => $agent_type,
							'turn_count' => $turn_count,
							'tool_name' => $tool_name
						]);

						continue;
					}

					// Add tool call message to conversation
					$tool_call_message = ConversationManager::formatToolCallMessage(
						$tool_name,
						$tool_parameters,
						$turn_count
					);
					$messages[] = $tool_call_message;

					// Execute the tool
					$tool_result = ToolExecutor::executeTool(
						$tool_name,
						$tool_parameters,
						$tools,
						[], // data packets (empty for chat, populated by pipeline)
						null, // flow_step_id (null for chat, set by pipeline)
						$context
					);

					do_action('datamachine_log', 'debug', 'AIConversationLoop: Tool executed', [
						'agent_type' => $agent_type,
						'turn_count' => $turn_count,
						'tool_name' => $tool_name,
						'success' => $tool_result['success'] ?? false
					]);

					// Determine if this is a handler tool
					$tool_def = $tools[$tool_name] ?? null;
					$is_handler_tool = $tool_def && isset($tool_def['handler']);

					// Add tool result message to conversation
					$tool_result_message = ConversationManager::formatToolResultMessage(
						$tool_name,
						$tool_result,
						$tool_parameters,
						$is_handler_tool,
						$turn_count
					);
					$messages[] = $tool_result_message;
				}
			} else {
				// No tool calls = conversation complete
				$conversation_complete = true;

				do_action('datamachine_log', 'debug', 'AIConversationLoop: Conversation complete', [
					'agent_type' => $agent_type,
					'turn_count' => $turn_count,
					'final_message_count' => count($messages)
				]);
			}

		} while (!$conversation_complete && $turn_count < $max_turns);

		// Log if max turns reached
		if ($turn_count >= $max_turns && !$conversation_complete) {
			do_action('datamachine_log', 'warning', 'AIConversationLoop: Max turns reached', [
				'agent_type' => $agent_type,
				'max_turns' => $max_turns,
				'final_turn_count' => $turn_count,
				'still_had_tool_calls' => !empty($last_tool_calls)
			]);
		}

		return [
			'messages' => $messages,
			'final_content' => $final_content,
			'turn_count' => $turn_count,
			'completed' => $conversation_complete,
			'last_tool_calls' => $last_tool_calls
		];
	}
}
