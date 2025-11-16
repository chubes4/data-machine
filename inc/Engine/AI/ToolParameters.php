<?php
/**
 * Centralized parameter building for AI tool execution.
 *
 * Universal parameter building infrastructure shared by Chat and Pipeline agents.
 *
 * @package DataMachine\Engine\AI
 * @since 0.2.0
 */

namespace DataMachine\Engine\AI;

defined('ABSPATH') || exit;

class ToolParameters {

    /**
     * Build unified flat parameter structure for tool execution.
     *
     * @param array $ai_tool_parameters Parameters from AI
     * @param array $unified_parameters Unified context (session_id for chat, job_id + engine_data for pipeline)
     * @param array $tool_definition Tool definition array
     * @return array Complete parameters for tool handler
     */
    public static function buildParameters(
        array $ai_tool_parameters,
        array $unified_parameters,
        array $tool_definition
    ): array {
        $parameters = $unified_parameters;

        $parameters['content'] = self::extractContent($unified_parameters['data'] ?? [], $tool_definition);
        $parameters['title'] = self::extractTitle($unified_parameters['data'] ?? [], $tool_definition);

        $parameters['tool_definition'] = $tool_definition;
        $parameters['tool_name'] = $tool_definition['name'] ?? null;
        $parameters['handler_config'] = $tool_definition['handler_config'] ?? [];

        foreach ($ai_tool_parameters as $key => $value) {
            $parameters[$key] = $value;
        }

        return $parameters;
    }

    /**
     * Extract content from data packet if tool requires content parameter.
     *
     * @param array $data_packet Data packet array
     * @param array $tool_definition Tool definition array
     * @return string|null Extracted content or null
     */
    private static function extractContent(array $data_packet, array $tool_definition): ?string {
        $tool_params = $tool_definition['parameters'] ?? [];
        if (!isset($tool_params['content'])) {
            return null;
        }

        $latest_entry = !empty($data_packet) ? $data_packet[0] : [];
        $content_data = $latest_entry['content'] ?? [];
        return $content_data['body'] ?? null;
    }

    /**
     * Extract title from data packet if tool requires title parameter.
     *
     * @param array $data_packet Data packet array
     * @param array $tool_definition Tool definition array
     * @return string|null Extracted title or null
     */
    private static function extractTitle(array $data_packet, array $tool_definition): ?string {
        $tool_params = $tool_definition['parameters'] ?? [];
        if (!isset($tool_params['title'])) {
            return null;
        }

        $latest_entry = !empty($data_packet) ? $data_packet[0] : [];
        $content_data = $latest_entry['content'] ?? [];
        return $content_data['title'] ?? null;
    }

    /**
     * Build parameters for handler tools with engine data.
     *
     * @param array $ai_tool_parameters Parameters from AI
     * @param array $data Data packets
     * @param array $tool_definition Tool definition array
     * @param array $engine_parameters Engine parameters (source_url, image_url, etc.)
     * @param array $handler_config Handler configuration
     * @return array Complete parameters for handler tool
     */
    public static function buildForHandlerTool(
        array $ai_tool_parameters,
        array $data,
        array $tool_definition,
        array $engine_parameters,
        array $handler_config
    ): array {
        $unified_parameters = [
            'data' => $data,
            'handler_config' => $handler_config
        ];

        $parameters = self::buildParameters($ai_tool_parameters, $unified_parameters, $tool_definition);

        // Merge engine data (source_url, image_url) from centralized access
        foreach ($engine_parameters as $key => $value) {
            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
