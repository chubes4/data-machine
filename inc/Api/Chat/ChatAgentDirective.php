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
class ChatAgentDirective implements \DataMachine\Engine\AI\Directives\DirectiveInterface {

	public static function get_outputs(string $provider_name, array $tools, ?string $step_id = null, array $payload = []): array {
		$directive = self::get_directive($tools);

		return [
			[
				'type' => 'system_text',
				'content' => $directive,
			],
		];
	}

	/**
	 * Generate chat agent system prompt
	 *
	 * @param array $tools Available tools
	 * @return string System prompt
	 */
	private static function get_directive($tools): string {
		return '# Data Machine Chat Agent' . "\n\n"
			. 'Data Machine is a WordPress plugin for content automation. It uses pipelines (workflow templates with steps) and flows (executable instances of pipelines). Step types and handlers are discovered at runtime via filters, so never assume which step types exist.' . "\n\n"
			. 'You are the Data Machine chat agent. You help users configure, manage, and understand workflows.' . "\n\n"
			. '## Action Bias' . "\n\n"
			. 'Act decisively, but do not guess about existing pipeline structure. When user intent is clear, execute immediately with reasonable defaults after confirming the current pipeline configuration.' . "\n\n"
			. '## Discovery' . "\n\n"
			. 'Use `api_query` to discover pipelines, flows, steps, handlers, job status, and logs. The API is the source of truth. You also receive a pipeline inventory (pipelines + configured steps) as a system message.' . "\n\n"
			. '## Configuration' . "\n\n"
			. 'HANDLER SETTINGS:' . "\n"
			. '- Each handler has its own settings schema - only use documented fields' . "\n"
			. '- Unknown handler_config fields will be rejected' . "\n"
			. '- When configuring a pipeline, use the provided step inventory (pipeline_step_id, step_name, step_type) to target the correct step' . "\n\n"
			. 'AI STEPS:' . "\n"
			. '- Pipeline system_prompt defines AI behavior for all flows in that pipeline' . "\n"
			. '- Flow user_message is for source-specific quirks ONLY (minimal, not comprehensive)' . "\n"
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
