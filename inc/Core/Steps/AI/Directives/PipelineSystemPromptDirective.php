<?php
/**
 * Pipeline System Prompt Directive - Priority 30
 *
 * Injects user-configured task instructions specific to the current pipeline
 * as the third directive in the 5-tier AI directive system. Contains clean
 * user instructions that define what the AI should accomplish for this workflow.
 *
 * Priority Order in 5-Tier System:
 * 1. Priority 10 - Plugin Core Directive
 * 2. Priority 20 - Global System Prompt
 * 3. Priority 30 - Pipeline System Prompt (THIS CLASS)
 * 4. Priority 40 - Tool Definitions and Workflow Context
 * 5. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class PipelineSystemPromptDirective {
    
    /**
     * Inject pipeline system prompt into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified request with pipeline system prompt added
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }
        
        self::require_pipeline_context($pipeline_step_id, __METHOD__);

        $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
        $system_prompt = $step_ai_config['system_prompt'] ?? '';
        
        if (empty($system_prompt)) {
            return $request;
        }

        array_push($request['messages'], [
            'role' => 'system',
            'content' => "PIPELINE GOALS:\n" . trim($system_prompt)
        ]);

        do_action('dm_log', 'debug', 'Pipeline System Prompt: Injected user configuration', [
            'pipeline_step_id' => $pipeline_step_id,
            'prompt_length' => strlen($system_prompt),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
    
    /**
     * Ensure pipeline context is available for AI directive injection.
     *
     * @param string|null $pipeline_step_id Pipeline step ID
     * @param string $context Method context for error logging
     */
    private static function require_pipeline_context($pipeline_step_id, string $context): void {
        if (empty($pipeline_step_id)) {
            do_action('dm_log', 'error', 'Pipeline context missing', [
                'context' => $context,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            $job_id = apply_filters('dm_current_job_id', null);
            if ($job_id) {
                do_action('dm_fail_job', $job_id, 'missing_pipeline_context', [
                    'context' => $context,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
            }
        }
    }
}

// Self-register (Priority 30 = third in 5-tier directive system)
add_filter('ai_request', [PipelineSystemPromptDirective::class, 'inject'], 30, 5);