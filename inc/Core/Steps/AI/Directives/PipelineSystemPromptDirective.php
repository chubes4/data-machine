<?php
/**
 * Pipeline System Prompt Directive - Priority 30
 *
 * Injects user-configured task instructions and dynamic workflow visualization
 * as the third directive in the 5-tier AI directive system. Provides both workflow
 * context (step order and handlers) and clean user instructions defining what
 * the AI should accomplish for this pipeline.
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

        // Extract current pipeline step ID for "YOU ARE HERE" context
        $current_flow_step_id = apply_filters('dm_current_flow_step_id', null);
        $current_pipeline_step_id = null;
        if ($current_flow_step_id) {
            $flow_parts = apply_filters('dm_split_flow_step_id', null, $current_flow_step_id);
            $current_pipeline_step_id = $flow_parts['pipeline_step_id'] ?? null;
        }

        // Build workflow visualization with current step context
        $workflow_visualization = self::buildWorkflowVisualization($pipeline_step_id, $current_pipeline_step_id);

        // Construct enhanced message with workflow context
        $content = '';
        if (!empty($workflow_visualization)) {
            $content .= "WORKFLOW: " . $workflow_visualization . "\n\n";
        }
        $content .= "PIPELINE GOALS:\n" . trim($system_prompt);

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $content
        ]);

        do_action('dm_log', 'debug', 'Pipeline System Prompt: Injected user configuration with workflow', [
            'pipeline_step_id' => $pipeline_step_id,
            'prompt_length' => strlen($system_prompt),
            'workflow_visualization' => $workflow_visualization,
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }

    /**
     * Build workflow visualization string from flow configuration.
     *
     * Uses dm_get_flow_steps filter for optimized handler loading.
     *
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @param string|null $current_pipeline_step_id Currently executing pipeline step ID
     * @return string Workflow visualization (e.g., "REDDIT FETCH → AI (YOU ARE HERE) → WORDPRESS PUBLISH")
     */
    private static function buildWorkflowVisualization($pipeline_step_id, $current_pipeline_step_id = null): string {
        if (empty($pipeline_step_id)) {
            return '';
        }

        // Get flow_id from current execution context
        $current_flow_step_id = apply_filters('dm_current_flow_step_id', null);
        if (!$current_flow_step_id) {
            do_action('dm_log', 'debug', 'Workflow visualization: No flow context available');
            return '';
        }

        $flow_parts = apply_filters('dm_split_flow_step_id', null, $current_flow_step_id);
        $flow_id = $flow_parts['flow_id'] ?? null;

        if (!$flow_id) {
            do_action('dm_log', 'debug', 'Workflow visualization: Could not extract flow_id');
            return '';
        }

        // Get enriched flow steps (handlers pre-loaded and optimized)
        $flow_steps = apply_filters('dm_get_flow_steps', [], $flow_id);
        if (empty($flow_steps)) {
            do_action('dm_log', 'debug', 'Workflow visualization: No flow steps found', [
                'flow_id' => $flow_id
            ]);
            return '';
        }

        // Sort steps by execution_order
        $sorted_steps = [];
        foreach ($flow_steps as $flow_step_id => $step_config) {
            $execution_order = $step_config['execution_order'] ?? -1;
            if ($execution_order >= 0) {
                // Extract pipeline_step_id from flow_step_id for "YOU ARE HERE" matching
                $step_parts = apply_filters('dm_split_flow_step_id', null, $flow_step_id);
                $step_pipeline_step_id = $step_parts['pipeline_step_id'] ?? '';

                $sorted_steps[$execution_order] = [
                    'pipeline_step_id' => $step_pipeline_step_id,
                    'step_type' => $step_config['step_type'] ?? '',
                    'handler_info' => $step_config['handler_info'] ?? null
                ];
            }
        }
        ksort($sorted_steps);

        // Build workflow visualization
        $workflow_parts = [];
        foreach ($sorted_steps as $step_data) {
            $step_type = strtoupper($step_data['step_type']);
            $step_pipeline_step_id = $step_data['pipeline_step_id'];

            if ($step_type === 'AI') {
                // Show "YOU ARE HERE" for currently executing AI step
                $is_current_step = ($current_pipeline_step_id && $step_pipeline_step_id === $current_pipeline_step_id);
                $workflow_parts[] = $is_current_step ? 'AI (YOU ARE HERE)' : 'AI';
            } else if ($step_data['handler_info']) {
                // Get handler display name from pre-loaded handler info
                $handler_label = strtoupper($step_data['handler_info']['label'] ?? 'UNKNOWN');
                $workflow_parts[] = $handler_label . ' ' . $step_type;
            } else {
                // Fallback if handler info not available
                $workflow_parts[] = $step_type;
            }
        }

        $workflow_string = implode(' → ', $workflow_parts);

        do_action('dm_log', 'debug', 'Workflow visualization: Built from flow config', [
            'flow_id' => $flow_id,
            'steps_count' => count($sorted_steps),
            'current_pipeline_step_id' => $current_pipeline_step_id,
            'workflow_string' => $workflow_string
        ]);

        return $workflow_string;
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