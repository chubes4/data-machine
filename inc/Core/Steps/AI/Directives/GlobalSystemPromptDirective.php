<?php
/**
 * Global System Prompt Directive - Priority 10 (Highest Priority)
 *
 * Injects user-configured foundational AI behavior instructions as the first directive
 * in the 5-tier AI directive system. Sets overall tone, personality, and core behavior
 * for ALL AI interactions across the entire system.
 *
 * Priority Order in 5-Tier System:
 * 1. Priority 10 - Global System Prompt (THIS CLASS)
 * 2. Priority 20 - Pipeline System Prompt
 * 3. Priority 30 - Tool Definitions and Workflow Context
 * 4. Priority 40 - Data Packet Structure
 * 5. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class GlobalSystemPromptDirective {
    
    /**
     * Inject global system prompt into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID (unused)
     * @return array Modified request with global system prompt added
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $settings = get_option('dm_data_machine_settings', []);
        $global_prompt = $settings['global_system_prompt'] ?? '';
        
        if (empty($global_prompt)) {
            return $request;
        }

        array_push($request['messages'], [
            'role' => 'system',
            'content' => trim($global_prompt)
        ]);

        do_action('dm_log', 'debug', 'Global System Prompt: Injected background guidance', [
            'prompt_length' => strlen($global_prompt),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
}

// Self-register (Priority 10 = highest priority in 5-tier directive system)
add_filter('ai_request', [GlobalSystemPromptDirective::class, 'inject'], 10, 5);