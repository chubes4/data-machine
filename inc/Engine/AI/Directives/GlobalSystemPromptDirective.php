<?php
/**
 * Global System Prompt Directive - Priority 20
 *
 * Injects user-configured foundational AI behavior instructions as the second directive
 * in the 5-tier AI directive system. Sets overall tone, personality, and core behavior
 * for ALL AI interactions across the entire system.
 *
 * Priority Order in 5-Tier System:
 * 1. Priority 10 - Plugin Core Directive
 * 2. Priority 20 - Global System Prompt (THIS CLASS)
 * 3. Priority 30 - Pipeline System Prompt
 * 4. Priority 40 - Tool Definitions and Workflow Context
 * 5. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Engine\AI\Directives;

use DataMachine\Core\PluginSettings;
use DataMachine\Engine\AI\Directives\DirectiveInterface;

defined('ABSPATH') || exit;

class GlobalSystemPromptDirective implements DirectiveInterface {

    public static function get_outputs(string $provider_name, array $tools, ?string $step_id = null, array $payload = []): array {
        $global_prompt = PluginSettings::get('global_system_prompt', '');

        if (empty($global_prompt)) {
            return [];
        }

        return [
            [
                'type' => 'system_text',
                'content' => trim($global_prompt),
            ],
        ];
    }
}

// Self-register (Priority 20 = global directive for all AI agents)
add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'class' => GlobalSystemPromptDirective::class,
        'priority' => 20,
        'agent_types' => ['all']
    ];
    return $directives;
});
