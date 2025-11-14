<?php
/**
 * Tool Definitions Directive - Priority 40
 *
 * Injects available tools list, workflow context, and task completion guidance
 * as the fourth directive in the 5-tier AI directive system. Provides complete
 * pipeline workflow context and tool orchestration instructions.
 *
 * Priority Order in 5-Tier System:
 * 1. Priority 10 - Plugin Core Directive
 * 2. Priority 20 - Global System Prompt
 * 3. Priority 30 - Pipeline System Prompt
 * 4. Priority 40 - Tool Definitions and Workflow Context (THIS CLASS)
 * 5. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class ToolDefinitionsDirective {
    
    /**
     * Inject tool definitions and workflow context into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable|null $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools array from AIStepTools
     * @param string|null $pipeline_step_id Pipeline step ID for workflow context
     * @return array Modified request with tool definitions and workflow context added
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null, array $context = []): array {
        if (empty($tools) || !is_array($tools)) {
            return $request;
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $flow_step_id = $context['flow_step_id'] ?? null;

        $directive = self::generate_dynamic_directive($tools, $request, $pipeline_step_id, $flow_step_id);

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $directive
        ]);

        do_action('datamachine_log', 'debug', 'AI Step Directive: Injected system directive', [
            'tool_count' => count($tools),
            'available_tools' => array_keys($tools),
            'directive_length' => strlen($directive)
        ]);

        return $request;
    }
    
    
    /**
     * Generate dynamic tool directive based on available tools.
     *
     * @param array $tools Available tools array
     * @param array $request AI request array for context
     * @param string|null $pipeline_step_id Pipeline step ID
     * @param string|null $flow_step_id Flow step ID
     * @return string Generated system directive
     */
    public static function generate_dynamic_directive(array $tools, array $request = [], $pipeline_step_id = null, $flow_step_id = null): string {
        $directive = '';

        // Detect (but do not require) presence of handler tools for context
        $has_handler_tools = false;
        foreach ($tools as $tool_config) {
            if (!empty($tool_config['handler'])) { $has_handler_tools = true; break; }
        }

    $directive .= "TOOL STRATEGY:\n";
    $directive .= "Follow the user instructions and use these tools to complete the pipeline goals.\n";
    if (!$has_handler_tools) {
        $directive .= "(Note: No handler/publish tools available in this turn.)\n";
    }
    $directive .= "\n";

        if (!empty($tools)) {
            $directive .= "TOOLS:\n";
            foreach ($tools as $tool_name => $tool_config) {
                $description = $tool_config['description'] ?? 'No description available';
                $directive .= "- {$tool_name}: {$description}\n";
            }
        }

        return trim($directive);
    }
}

// Self-register (Priority 40 = fourth in 5-tier directive system)
add_filter('ai_request', [ToolDefinitionsDirective::class, 'inject'], 40, 6);