<?php
/**
 * Global System Prompt Directive
 * 
 * Injects user-configured foundational AI behavior instructions.
 * This is the highest priority directive that sets the overall tone, personality, 
 * and core behavior for ALL AI interactions across the entire system.
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class GlobalSystemPromptDirective {
    
    /**
     * Inject global system prompt into AI request
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID
     * @return array Modified AI request
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

        // First system message in intuitive priority order
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

// Self-register with WordPress filter system (Priority 10 = executes first, appears first)
add_filter('ai_request', [GlobalSystemPromptDirective::class, 'inject'], 10, 5);