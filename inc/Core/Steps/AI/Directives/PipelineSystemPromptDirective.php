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
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @param array $payload Execution payload for context
     * @return array Modified request with pipeline system prompt added
     */
    public static function inject($request, $provider_name, $tools, $pipeline_step_id = null, array $payload = []): array {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $engine_data = datamachine_get_engine_data($payload['job_id']);
        $pipeline_config = $engine_data['pipeline_config'] ?? [];
        $step_config = $pipeline_config[$pipeline_step_id] ?? [];
        $system_prompt = $step_config['system_prompt'] ?? '';

        if (empty($system_prompt)) {
            return $request;
        }

        // Extract current pipeline step ID for "YOU ARE HERE" context
        $current_flow_step_id = $payload['flow_step_id'] ?? null;
        $current_pipeline_step_id = null;
        if ($current_flow_step_id) {
            $flow_parts = apply_filters('datamachine_split_flow_step_id', null, $current_flow_step_id);
            $current_pipeline_step_id = $flow_parts['pipeline_step_id'] ?? null;
        }

        // Build workflow visualization with current step context
        $workflow_visualization = self::buildWorkflowVisualization($pipeline_step_id, $current_pipeline_step_id, $payload);

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



        return $request;
    }

    /**
     * Build workflow visualization string from flow configuration.
     *
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @param string|null $current_pipeline_step_id Currently executing pipeline step ID
     * @param array $payload Execution payload for context
     * @return string Workflow visualization (e.g., "REDDIT FETCH → AI (YOU ARE HERE) → WORDPRESS PUBLISH")
     */
    private static function buildWorkflowVisualization($pipeline_step_id, $current_pipeline_step_id = null, array $payload = []): string {
        if (empty($pipeline_step_id)) {
            return '';
        }

        // Get flow_id from current execution context
        $current_flow_step_id = $payload['flow_step_id'] ?? null;
        if (!$current_flow_step_id) {
            return '';
        }

        $flow_parts = apply_filters('datamachine_split_flow_step_id', null, $current_flow_step_id);
        $flow_id = $flow_parts['flow_id'] ?? null;

        if (!$flow_id) {
            return '';
        }

        // Get flow config directly
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $flow = $db_flows->get_flow( (int) $flow_id );
        $flow_config = $flow['flow_config'] ?? [];

        if ( empty( $flow_config ) ) {
            return '';
        }

        // Sort steps by execution_order
        $sorted_steps = [];
        foreach ( $flow_config as $flow_step_id => $step_config ) {
            $execution_order = $step_config['execution_order'] ?? -1;
            if ( $execution_order >= 0 ) {
                // Extract pipeline_step_id for "YOU ARE HERE" matching
                $step_parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
                $step_pipeline_step_id = $step_parts['pipeline_step_id'] ?? '';

                $sorted_steps[ $execution_order ] = [
                    'pipeline_step_id' => $step_pipeline_step_id,
                    'step_type' => $step_config['step_type'] ?? '',
                    'handler_slug' => $step_config['handler_slug'] ?? ''
                ];
            }
        }
        ksort( $sorted_steps );

        // Build workflow visualization
        $workflow_parts = [];
        foreach ( $sorted_steps as $step_data ) {
            $step_type = $step_data['step_type'];
            $handler_slug = $step_data['handler_slug'];
            $step_pipeline_step_id = $step_data['pipeline_step_id'];

            if ( $step_type === 'ai' ) {
                // Show "YOU ARE HERE" for currently executing AI step
                $is_current = ( $current_pipeline_step_id && $step_pipeline_step_id === $current_pipeline_step_id );
                $workflow_parts[] = $is_current ? 'AI (YOU ARE HERE)' : 'AI';
            } else if ( $handler_slug ) {
                // Get handler label directly when needed
                $handlers = apply_filters( 'datamachine_handlers', [], $step_type );
                $label = strtoupper( $handlers[ $handler_slug ]['label'] ?? 'UNKNOWN' );
                $workflow_parts[] = $label . ' ' . strtoupper( $step_type );
            } else {
                $workflow_parts[] = strtoupper( $step_type );
            }
        }

        $workflow_string = implode(' → ', $workflow_parts);

        return $workflow_string;
    }


}

// Register with universal agent directive system (Priority 30 = third in 5-tier directive system)
add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'class' => PipelineSystemPromptDirective::class,
        'priority' => 30,
        'agent_types' => ['pipeline']
    ];
    return $directives;
});