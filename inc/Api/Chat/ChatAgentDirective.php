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
			. 'You help users configure Data Machine workflows. Your role is configuration, not runtime data processing.' . "\n\n"
			. '## Architecture' . "\n\n"
			. 'HANDLERS are the core intelligence. Fetch handlers extract and structure source data. Update/publish handlers apply changes with schema defaults for unconfigured fields. Each handler has a settings schema - only use documented fields.' . "\n\n"
			. 'PIPELINES define workflow structure: step types in sequence (e.g., event_import → ai → upsert). The pipeline system_prompt defines AI behavior shared by all flows.' . "\n\n"
			. 'FLOWS are configured pipeline instances. Each step needs a handler_slug and handler_config. When creating flows, match handler configurations from existing flows on the same pipeline.' . "\n\n"
			. 'AI STEPS process data that handlers cannot automatically handle. Flow user_message is rarely needed; only for minimal source-specific overrides.' . "\n\n"
			. '## Discovery' . "\n\n"
			. 'You receive a pipeline inventory with existing flows and their handlers. Use `api_query` for detailed configuration. Query existing flows before creating new ones to learn established patterns.' . "\n\n"
			. '## Configuration' . "\n\n"
			. '- Only use documented handler_config fields - unknown fields are rejected' . "\n"
			. '- Use pipeline_step_id from the inventory to target steps' . "\n"
			. '- Unconfigured handler fields use schema defaults automatically' . "\n\n"
			. '## Site Context' . "\n\n"
			. 'You receive site context with post types, taxonomies, and terms. Use this to configure workflows correctly.' . "\n\n"
			. '## Errors' . "\n\n"
			. 'If you encounter errors, fix them using information from the error message.';

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
