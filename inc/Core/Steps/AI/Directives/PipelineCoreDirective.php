<?php
/**
 * Pipeline Core Directive
 *
 * Priority 10 AI directive that establishes foundational agent identity
 * and operational principles for pipeline AI agents.
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

/**
 * Pipeline Core Directive
 *
 * Injects foundational AI agent identity and operational principles
 * into pipeline AI requests.
 */
class PipelineCoreDirective {

    /**
     * Inject core directive into AI request.
     *
     * @param array $request AI request data
     * @param string $provider_name AI provider name
     * @param array $tools Available tools
     * @param string|null $pipeline_step_id Pipeline step ID
     * @param array $payload Step payload
     * @return array Modified request with directive injected
     */
    public static function inject($request, $provider_name, $tools, $pipeline_step_id = null, array $payload = []): array {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $directive = self::generate_core_directive();

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $directive
        ]);



        return $request;
    }

    /**
     * Generate core directive content.
     *
     * @return string Core directive text
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
        $directive .= "- Execute handler tools only when ready to produce final pipeline outputs\n\n";

        $directive .= "DATA PACKET STRUCTURE:\n";
        $directive .= "You will receive content as JSON data packets. Every packet contains these guaranteed fields:\n";
        $directive .= "- type: The step type that created this packet\n";
        $directive .= "- timestamp: When the packet was created\n";
        $directive .= "Additional fields may include data, metadata, content, and handler-specific information.\n";

        return trim($directive);
    }
}

// Register with universal agent directive system
add_filter('datamachine_directives', function($directives) {
    $directives[] = [
        'class' => PipelineCoreDirective::class,
        'priority' => 10,
        'agent_types' => ['pipeline']
    ];
    return $directives;
});
