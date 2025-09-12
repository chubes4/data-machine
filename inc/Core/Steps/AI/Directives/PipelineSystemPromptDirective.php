<?php
/**
 * Pipeline System Prompt Directive
 * 
 * Injects user-configured task instructions specific to the current pipeline.
 * Contains clean user instructions without labels that define what the AI 
 * should accomplish for this particular pipeline workflow.
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class PipelineSystemPromptDirective {
    
    /**
     * Inject pipeline system prompt into AI request
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
        
        // Enforce pipeline context
        self::require_pipeline_context($pipeline_step_id, __METHOD__);

        $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
        $system_prompt = $step_ai_config['system_prompt'] ?? '';
        
        if (empty($system_prompt)) {
            return $request;
        }

        // Pipeline system prompt - clean user instructions without labels
        array_push($request['messages'], [
            'role' => 'system',
            'content' => trim($system_prompt)
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
     * Ensure a pipeline_step_id is present; log + fail job if missing.
     *
     * @param mixed $pipeline_step_id
     * @param string $context Calling context/method for diagnostics
     */
    private static function require_pipeline_context($pipeline_step_id, string $context): void {
        if (empty($pipeline_step_id)) {
            do_action('dm_log', 'error', 'Pipeline context missing', [
                'context' => $context,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            // Fail current job if available in global execution context
            $job_id = apply_filters('dm_current_job_id', null);
            if ($job_id) {
                do_action('dm_fail_job', $job_id, 'missing_pipeline_context', [
                    'context' => $context,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
            }
            // Do not throw; upstream should detect absence and stop further processing
        }
    }
}

// Self-register with WordPress filter system (Priority 20 = second in order)
add_filter('ai_request', [PipelineSystemPromptDirective::class, 'inject'], 20, 5);