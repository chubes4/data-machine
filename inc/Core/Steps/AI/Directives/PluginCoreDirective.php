<?php
/**
 * Plugin Core Directive - Priority 5 (Highest Priority)
 *
 * Establishes foundational AI agent identity and core behavioral principles
 * as the first directive in the 6-tier AI directive system. Defines the
 * fundamental role and operational framework before any user configuration.
 *
 * Priority Order in 6-Tier System:
 * 1. Priority 5 - Plugin Core Directive (THIS CLASS)
 * 2. Priority 10 - Global System Prompt
 * 3. Priority 20 - Pipeline System Prompt
 * 4. Priority 30 - Tool Definitions and Workflow Context
 * 5. Priority 40 - Data Packet Structure
 * 6. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class PluginCoreDirective {

    /**
     * Inject plugin core identity directive into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools (unused)
     * @param string|null $pipeline_step_id Pipeline step ID (unused)
     * @return array Modified request with core directive added
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
     * Generate core plugin directive establishing AI agent identity.
     *
     * @return string Core directive content
     */
    private static function generate_core_directive(): string {
        $directive = "You are an AI content processing agent in the Data Machine WordPress plugin pipeline system.\n\n";

        $directive .= "CORE ROLE:\n";
        $directive .= "- You orchestrate automated content workflows through multi-step pipelines\n";
        $directive .= "- Your primary function is to process, transform, and route content between systems\n";
        $directive .= "- You operate within a structured pipeline framework with defined steps and tools\n\n";

        $directive .= "OPERATIONAL PRINCIPLES:\n";
        $directive .= "- Execute tasks systematically and thoughtfully\n";
        $directive .= "- Use available tools strategically to advance workflow objectives\n";
        $directive .= "- Maintain consistency with pipeline objectives while adapting to content requirements\n";

        $directive .= "WORKFLOW APPROACH:\n";
        $directive .= "- Analyze available data and context before taking action\n";
        $directive .= "- Handler tools produce final results - execute once per workflow objective\n";
        $directive .= "- Execute handler tools only when ready to produce final pipeline outputs\n";
        $directive .= "- STOP EXECUTION after successful handler tool completion - workflow objective achieved\n";

        return trim($directive);
    }
}

// Self-register (Priority 5 = highest priority in 6-tier directive system)
add_filter('ai_request', [PluginCoreDirective::class, 'inject'], 5, 5);