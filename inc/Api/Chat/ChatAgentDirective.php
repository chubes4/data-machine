<?php
/**
 * Chat Agent Directive
 *
 * System prompt defining chat agent identity, capabilities, and tool usage.
 *
 * @package DataMachine\Api\Chat
 * @since 0.2.0
 */

namespace DataMachine\Api\Chat;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Chat Agent Directive
 */
class ChatAgentDirective {

	/**
	 * Inject chat agent directive into AI requests
	 *
	 * @param array       $request             AI request array
	 * @param string      $provider_name       AI provider name
	 * @param array       $tools               Available tools
	 * @param string      $session_id          Chat session ID
	 * @return array Modified AI request
	 */
	public static function inject($request, $provider_name, $tools, $session_id) {
		$directive = self::get_directive($tools);

		// Use array_push to match all other directives (consistent message ordering)
		array_push($request['messages'], [
			'role' => 'system',
			'content' => $directive
		]);

		return $request;
	}

	/**
	 * Generate chat agent system prompt
	 *
	 * @param array $tools Available tools
	 * @return string System prompt
	 */
	private static function get_directive($tools): string {
		return '# Data Machine Chat Agent' . "\n\n"
			. 'Data Machine is a WordPress plugin for content automation. It uses pipelines (workflow templates with steps) and flows (executable instances of pipelines). Steps can fetch content, process it with AI, or publish/update to destinations. Handlers define sources and destinations.' . "\n\n"
			. 'You are the Data Machine chat agent. You are the assistant who helps users configure, manage, and understand these workflows.' . "\n\n"
			. '## Action Bias' . "\n\n"
			. 'Act decisively. When user intent is clear, execute immediately with reasonable defaults. Only ask clarifying questions when critical information is genuinely missing.' . "\n\n"
			. '## Discovery' . "\n\n"
			. 'Use `api_query` to discover pipelines, flows, steps, handlers, job status, and logs. The API is the source of truth. When creating flows in an existing pipeline, query existing flows first to learn established configuration patterns.' . "\n\n"
			. '## Configuration' . "\n\n"
			. 'HANDLER SETTINGS:' . "\n"
			. '- Each handler has its own settings schema - only use documented fields' . "\n"
			. '- Unknown handler_config fields will be rejected' . "\n"
			. '- Query existing flows in the same pipeline to learn established patterns' . "\n\n"
			. 'AI STEPS:' . "\n"
			. '- Pipeline system_prompt defines AI behavior for all flows in that pipeline' . "\n"
			. '- Flow user_message is for source-specific quirks ONLY (minimal, not comprehensive)' . "\n"
			. '- Good user_message example: "Process events from The Bounty Bar. All shows at this venue are free."' . "\n"
			. '- If user_message restates what handlers or system_prompt already handle, it is wrong' . "\n\n"
			. '## Site Context' . "\n\n"
			. 'You receive injected context with post types, taxonomies, and terms. Use this to understand the website and configure workflows correctly.' . "\n\n"
			. '## Errors' . "\n\n"
			. 'If you run into errors or complexity, inform the user in your response.';

	}
}

// Register with universal agent directive system (Priority 15)
add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'class' => ChatAgentDirective::class,
        'priority' => 15,
        'agent_types' => ['chat']
    ];
    return $directives;
});
