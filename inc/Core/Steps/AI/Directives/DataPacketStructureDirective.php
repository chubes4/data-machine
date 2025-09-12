<?php
/**
 * Data Packet Structure Directive
 * 
 * Injects explanation of the data packet JSON structure so the AI understands
 * the workflow data format and how new packets appear across turns.
 *
 * Priority: 40 (after tool definitions, before site context)
 *
 * @package DataMachine\Core\Steps\AI\Directives
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class DataPacketStructureDirective {
    /**
     * Inject data packet structure directive
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

        $directive = "DATA PACKET STRUCTURE:\n\n";
        $directive .= "All workflow data is provided in a JSON object with an array named data_packets.\n";
        $directive .= "You receive the ENTIRE updated structure each turn (new packets are prepended).\n\n";
        $directive .= "ROOT WRAPPER:\n";
        $directive .= '{"data_packets": [packet1, packet2, ...]}' . "\n\n";
        $directive .= "ORDERING:\n";
        $directive .= "- Index 0 = most recent packet (new packets are added to the front).\n";
        $directive .= "- Older packets shift toward the end of the array.\n\n";
        $directive .= "CORE FIELDS (all packets):\n";
        $directive .= "- type: 'fetch' | 'ai_response' | 'tool_result' | 'ai_handler_complete'\n";
        $directive .= "- content: {title: string, body: string}\n";
        $directive .= "- metadata: {source_type, flow_step_id, ...context}\n";
        $directive .= "- timestamp: Unix epoch (int)\n\n";
        $directive .= "TYPE-SPECIFIC FIELDS:\n";
        $directive .= "- handler: (fetch/tool packets) Source handler e.g. 'rss', 'reddit', 'files'\n";
        $directive .= "- attachments: (optional) Array of file/media attachment objects\n";
        $directive .= "- tool_name: (tool_result packets) Executed tool identifier\n\n";
        $directive .= "WORKFLOW DYNAMICS:\n";
        $directive .= "- When you execute a tool, the system adds a new tool_result packet next turn.\n";
        $directive .= "- When a handler (publish/update) tool completes successfully an ai_handler_complete packet is added.\n";
        $directive .= "- You NEVER mutate existing packets; you only decide next actions based on them.\n\n";
        $directive .= "USAGE GUIDANCE:\n";
        $directive .= "1. Read newest packets first (index 0 downward).\n";
        $directive .= "2. Use only the packets relevant to your objective.\n";
        $directive .= "3. Execute handler tools when ready to complete the current pipeline step.\n";
        $directive .= "4. Avoid unnecessary research/tool calls once sufficient context is present.\n";
        $directive .= "5. Declare completion once handler objective achieved.\n";

        array_push($request['messages'], [
            'role' => 'system',
            'content' => $directive
        ]);

        do_action('dm_log', 'debug', 'Data Packet Structure Directive: Injected', [
            'directive_length' => strlen($directive),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
}

// Self-register (Priority 40 = after tool definitions, before site context)
add_filter('ai_request', [DataPacketStructureDirective::class, 'inject'], 40, 5);
