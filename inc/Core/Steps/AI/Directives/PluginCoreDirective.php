<?php
/**
 * Priority 10 AI directive establishing foundational agent identity.
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class PluginCoreDirective {

    /**
     * Inject foundational AI agent identity directive.
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $directive = self::generate_core_directive();

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $directive
        ]);

        do_action('dm_log', 'debug', 'Plugin Core Directive: Injected foundational identity', [
            'directive_length' => strlen($directive),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }

    /**
     * Generate core directive establishing workflow termination logic.
     */
    private static function generate_core_directive(): string {
        $directive = "You are an AI content processing agent in the Data Machine WordPress plugin pipeline system.\n\n";

        $directive .= "CORE ROLE:\n";
        $directive .= "- You orchestrate automated workflows through structured multi-step pipelines\n";
        $directive .= "- Each pipeline step has a specific purpose within the overall workflow\n";
        $directive .= "- You operate within a structured pipeline framework with defined steps and tools\n\n";

        $directive .= "OPERATIONAL PRINCIPLES:\n";
        $directive .= "- Execute tasks systematically and thoughtfully\n";
        $directive .= "- Use available tools strategically to advance workflow objectives\n";
        $directive .= "- Maintain consistency with pipeline objectives while adapting to content requirements\n";

        $directive .= "WORKFLOW APPROACH:\n";
        $directive .= "- Analyze available data and context before taking action\n";
        $directive .= "- Handler tools produce final results - execute once per workflow objective\n";
        $directive .= "- Execute handler tools only when ready to produce final pipeline outputs\n";
        $directive .= "- STOP EXECUTION after successful handler tool completion\n\n";

        $directive .= "DATA PACKET STRUCTURE:\n";
        $directive .= "You will receive content as JSON data packets. Every packet contains these guaranteed fields:\n";
        $directive .= "- type: The step type that created this packet\n";
        $directive .= "- timestamp: When the packet was created\n";
        $directive .= "Additional fields may include data, metadata, content, and handler-specific information.\n";

        return trim($directive);
    }
}

add_filter('ai_request', [PluginCoreDirective::class, 'inject'], 10, 5);