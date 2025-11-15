<?php
/**
 * AI Request Builder - Centralized AI request construction for all agents
 *
 * Single source of truth for building standardized AI requests across Chat and Pipeline agents.
 * Ensures consistent request structure, tool formatting, and directive application to prevent
 * architectural drift between different AI agent types.
 *
 * @package DataMachine\Engine\AI
 * @since 0.2.0
 */

namespace DataMachine\Engine\AI;

defined('ABSPATH') || exit;

class RequestBuilder {

	/**
	 * Build standardized AI request for any agent type
	 *
	 * Centralizes request construction logic to ensure Chat and Pipeline agents
	 * build identical request structures. Handles tool restructuring, directive
	 * application, and consistent ai_request filter invocation.
	 *
	 * @param array  $messages    Initial messages array with role/content
	 * @param string $provider    AI provider name (openai, anthropic, google, grok, openrouter)
	 * @param string $model       Model identifier
	 * @param array  $tools       Raw tools array from filters
	 * @param string $agent_type  Agent type: 'chat' or 'pipeline'
	 * @param array  $context     Agent-specific context (session_id, step_id, payload, etc)
	 * @return array AI response from provider
	 */
	public static function build(
		array $messages,
		string $provider,
		string $model,
		array $tools,
		string $agent_type,
		array $context = []
	): array {

		// 1. Initialize request with model and messages
		$request = [
			'model' => $model,
			'messages' => $messages
		];

		// 2. Restructure tools to standard format (ensures consistent tool structure for all providers)
		$structured_tools = self::restructure_tools($tools);

		// 3. Apply global directives (applies to ALL AI agents - chat + pipeline)
		$request = apply_filters(
			'datamachine_global_directives',
			$request,
			$provider,
			$structured_tools,
			$context['step_id'] ?? null,
			$context['payload'] ?? []
		);

		// 4. Apply agent-specific directives
		if ($agent_type === 'chat') {
			// Chat-specific directives
			$request = apply_filters(
				'datamachine_chat_directives',
				$request,
				$provider,
				$structured_tools,
				$context['session_id'] ?? null
			);
		} elseif ($agent_type === 'pipeline') {
			// Pipeline-specific directives
			$request = apply_filters(
				'datamachine_pipeline_directives',
				$request,
				$provider,
				$structured_tools,
				$context['step_id'] ?? null,
				$context['payload'] ?? []
			);
		}

		do_action('datamachine_log', 'debug', 'RequestBuilder: Built AI request', [
			'agent_type' => $agent_type,
			'provider' => $provider,
			'model' => $model,
			'message_count' => count($request['messages']),
			'tool_count' => count($structured_tools)
		]);

		// 5. Send to ai-http-client via ai_request filter
		return apply_filters(
			'ai_request',
			$request,
			$provider,
			null, // streaming_callback
			$structured_tools,
			$context['step_id'] ?? $context['session_id'] ?? null,
			[
				'agent_type' => $agent_type,
				'context' => $context
			]
		);
	}

	/**
	 * Restructure tools with explicit field mapping
	 *
	 * Normalizes raw tool definitions to ensure all tools have consistent structure
	 * with name, description, parameters, handler, and handler_config fields.
	 * Prevents tool format mismatches with AI providers.
	 *
	 * @param array $raw_tools Raw tools array from filters
	 * @return array Structured tools with explicit fields
	 */
	private static function restructure_tools(array $raw_tools): array {
		$structured = [];

		foreach ($raw_tools as $tool_name => $tool_config) {
			$structured[$tool_name] = [
				'name' => $tool_name,
				'description' => $tool_config['description'] ?? '',
				'parameters' => $tool_config['parameters'] ?? [],
				'handler' => $tool_config['handler'] ?? null,
				'handler_config' => $tool_config['handler_config'] ?? []
			];
		}

		return $structured;
	}
}
