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

defined( 'ABSPATH' ) || exit;

class RequestBuilder {

	/**
	 * Build standardized AI request for any agent type
	 *
	 * Centralizes request construction logic to ensure Chat and Pipeline agents
	 * build identical request structures. Handles tool restructuring, directive
	 * application via PromptBuilder, and consistent chubes_ai_request filter invocation.
	 *
	 * @param array  $messages    Initial messages array with role/content
	 * @param string $provider    AI provider name (openai, anthropic, google, grok, openrouter)
	 * @param string $model       Model identifier
	 * @param array  $tools       Raw tools array from filters
	 * @param string $agent_type  Agent type: 'chat' or 'pipeline'
	 * @param array  $payload     Step payload (session_id, job_id, flow_step_id, data, etc)
	 * @return array AI response from provider
	 */
	public static function build(
		array $messages,
		string $provider,
		string $model,
		array $tools,
		string $agent_type,
		array $payload = array()
	): array {

		// 1. Initialize request with model and messages
		$request = array(
			'model'    => $model,
			'messages' => $messages,
		);

		// 2. Restructure tools to standard format (ensures consistent tool structure for all providers)
		$structured_tools = self::restructure_tools( $tools );

		// 3. Apply directives via PromptBuilder
		$promptBuilder = new PromptBuilder();
		$promptBuilder->setMessages( $messages )->setTools( $structured_tools );

		// Get registered directives
		$directives = apply_filters( 'datamachine_directives', array() );

		// Add each directive to the builder
		foreach ( $directives as $directive ) {
			$promptBuilder->addDirective(
				$directive['class'],
				$directive['priority'],
				$directive['agent_types'] ?? array( 'all' )
			);
		}

		// Build the request with directives applied
		$request            = $promptBuilder->build( $agent_type, $provider, $payload );
		$applied_directives = $request['applied_directives'] ?? array();
		unset( $request['applied_directives'] );
		$request['model'] = $model;

		do_action(
			'datamachine_log',
			'debug',
			'AI request built',
			array_filter(
				array(
					'agent_type'    => $agent_type,
					'job_id'        => $payload['job_id'] ?? null,
					'flow_step_id'  => $payload['flow_step_id'] ?? null,
					'provider'      => $provider,
					'model'         => $model,
					'message_count' => count( $request['messages'] ),
					'tool_count'    => count( $structured_tools ),
					'directives'    => $applied_directives,
				),
				fn( $v ) => null !== $v
			)
		);

		// 4. Send to ai-http-client via chubes_ai_request filter
		return apply_filters(
			'chubes_ai_request',
			$request,
			$provider,
			null, // streaming_callback
			$structured_tools,
			$payload['step_id'] ?? $payload['session_id'] ?? null,
			array(
				'agent_type' => $agent_type,
				'payload'    => $payload,
			)
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
	private static function restructure_tools( array $raw_tools ): array {
		$structured = array();

		foreach ( $raw_tools as $tool_name => $tool_config ) {
			$structured[ $tool_name ] = array(
				'name'           => $tool_name,
				'description'    => $tool_config['description'] ?? '',
				'parameters'     => $tool_config['parameters'] ?? array(),
				'handler'        => $tool_config['handler'] ?? null,
				'handler_config' => $tool_config['handler_config'] ?? array(),
			);
		}

		return $structured;
	}
}
