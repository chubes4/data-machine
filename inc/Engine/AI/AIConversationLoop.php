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

use DataMachine\Engine\AI\Tools\ToolExecutor;

if ( ! defined( 'ABSPATH' ) ) {
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
	 * @param array  $payload        Step payload (job_id, flow_step_id, data, flow_step_config)
	 * @param int    $max_turns      Maximum conversation turns (default 12)
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
		array $payload = array(),
		int $max_turns = 12
	): array {
		// Ensure max_turns is within reasonable bounds
		$max_turns              = max( 1, min( 50, $max_turns ) );
		$conversation_complete  = false;
		$turn_count             = 0;
		$final_content          = '';
		$last_tool_calls        = array();
		$tool_execution_results = array();

		// Build base log context from payload for consistent logging
		$base_log_context = array_filter(
			array(
				'agent_type'   => $agent_type,
				'job_id'       => $payload['job_id'] ?? null,
				'flow_step_id' => $payload['flow_step_id'] ?? null,
			),
			fn( $v ) => $v !== null
		);

		do {
			++$turn_count;

			// Build AI request using centralized RequestBuilder
			$ai_response = RequestBuilder::build(
				$messages,
				$provider,
				$model,
				$tools,
				$agent_type,
				$payload
			);

			// Handle AI request failure
			if ( ! $ai_response['success'] ) {
				do_action(
					'datamachine_log',
					'error',
					'AIConversationLoop: AI request failed',
					array_merge(
						$base_log_context,
						array(
							'turn_count' => $turn_count,
							'error'      => $ai_response['error'] ?? 'Unknown error',
							'provider'   => $ai_response['provider'] ?? 'Unknown',
						)
					)
				);

				return array(
					'messages'        => $messages,
					'final_content'   => '',
					'turn_count'      => $turn_count,
					'completed'       => false,
					'last_tool_calls' => array(),
					'error'           => $ai_response['error'] ?? 'AI request failed',
				);
			}

			$tool_calls = $ai_response['data']['tool_calls'] ?? array();
			$ai_content = $ai_response['data']['content'] ?? '';

			// Store final content from this turn
			if ( ! empty( $ai_content ) ) {
				$final_content = $ai_content;
			}

			// Add AI message to conversation if it has content
			if ( ! empty( $ai_content ) ) {
				$ai_message = ConversationManager::buildConversationMessage( 'assistant', $ai_content, array( 'type' => 'text' ) );
				$messages[] = $ai_message;
			}

			// Process tool calls
			if ( ! empty( $tool_calls ) ) {
				$last_tool_calls = $tool_calls;

				foreach ( $tool_calls as $tool_call ) {
					$tool_name       = $tool_call['name'] ?? '';
					$tool_parameters = $tool_call['parameters'] ?? array();

					if ( empty( $tool_name ) ) {
						do_action(
							'datamachine_log',
							'warning',
							'AIConversationLoop: Tool call missing name',
							array_merge(
								$base_log_context,
								array(
									'turn_count' => $turn_count,
									'tool_call'  => $tool_call,
								)
							)
						);
						continue;
					}

					do_action(
						'datamachine_log',
						'debug',
						'AIConversationLoop: Tool call',
						array_merge(
							$base_log_context,
							array(
								'turn'   => $turn_count,
								'tool'   => $tool_name,
								'params' => $tool_parameters,
							)
						)
					);

					// Validate for duplicate tool calls
					$validation_result = ConversationManager::validateToolCall(
						$tool_name,
						$tool_parameters,
						$messages
					);

					if ( $validation_result['is_duplicate'] ) {
						$correction_message = ConversationManager::generateDuplicateToolCallMessage( $tool_name, $turn_count );
						$messages[]         = $correction_message;

						do_action(
							'datamachine_log',
							'info',
							'AIConversationLoop: Duplicate tool call prevented',
							array_merge(
								$base_log_context,
								array(
									'turn_count' => $turn_count,
									'tool_name'  => $tool_name,
								)
							)
						);

						continue;
					}

					// Add tool call message to conversation
					$tool_call_message = ConversationManager::formatToolCallMessage(
						$tool_name,
						$tool_parameters,
						$turn_count
					);
					$messages[]        = $tool_call_message;

					// Execute the tool
					$tool_result = ToolExecutor::executeTool(
						$tool_name,
						$tool_parameters,
						$tools,
						$payload
					);

					do_action(
						'datamachine_log',
						'debug',
						'AIConversationLoop: Tool result',
						array_merge(
							$base_log_context,
							array(
								'turn'    => $turn_count,
								'tool'    => $tool_name,
								'success' => $tool_result['success'] ?? false,
							)
						)
					);

					// Determine if this is a handler tool
					$tool_def        = $tools[ $tool_name ] ?? null;
					$is_handler_tool = $tool_def && isset( $tool_def['handler'] );

					// Force conversation completion if a handler tool was successfully executed in pipeline mode
					if ( $agent_type === 'pipeline' && $is_handler_tool && ( $tool_result['success'] ?? false ) ) {
						$conversation_complete = true;
						do_action(
							'datamachine_log',
							'debug',
							'AIConversationLoop: Handler tool executed successfully, ending conversation',
							array_merge(
								$base_log_context,
								array(
									'tool_name'  => $tool_name,
									'turn_count' => $turn_count,
								)
							)
						);
					}

					// Store tool execution result separately for data packet processing
					$tool_execution_results[] = array(
						'tool_name'       => $tool_name,
						'result'          => $tool_result,
						'parameters'      => $tool_parameters,
						'is_handler_tool' => $is_handler_tool,
						'turn_count'      => $turn_count,
					);

					// Add tool result message to conversation (properly formatted for AI)
					$tool_result_message = ConversationManager::formatToolResultMessage(
						$tool_name,
						$tool_result,
						$tool_parameters,
						$is_handler_tool,
						$turn_count
					);
					$messages[]          = $tool_result_message;
				}
			} else {
				// No tool calls = conversation complete
				$conversation_complete = true;
			}
		} while ( ! $conversation_complete && $turn_count < $max_turns );

		// Log if max turns reached
		if ( $turn_count >= $max_turns && ! $conversation_complete ) {
			do_action(
				'datamachine_log',
				'warning',
				'AIConversationLoop: Max turns reached',
				array_merge(
					$base_log_context,
					array(
						'max_turns'            => $max_turns,
						'final_turn_count'     => $turn_count,
						'still_had_tool_calls' => ! empty( $last_tool_calls ),
					)
				)
			);
		}

		$result = array(
			'messages'               => $messages,
			'final_content'          => $final_content,
			'turn_count'             => $turn_count,
			'completed'              => $conversation_complete,
			'last_tool_calls'        => $last_tool_calls,
			'tool_execution_results' => $tool_execution_results,
		);

		if ( $turn_count >= $max_turns && ! $conversation_complete ) {
			$result['warning'] = 'Maximum conversation turns (' . $max_turns . ') reached. Response may be incomplete.';
		}

		return $result;
	}
}
