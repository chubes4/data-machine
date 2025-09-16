<?php
/**
 * AI Tool Parameter Builder - Centralized flat parameter building for AI tool execution.
 *
 * Provides unified parameter structure compatible with all handler tool call methods,
 * handling content extraction, metadata injection, and engine parameter merging.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepToolParameters {
    
    /**
     * Build flat parameter structure for AI tool execution.
     *
     * @param array $ai_tool_parameters Parameters from AI tool call
     * @param array $unified_parameters Unified parameters from engine (job_id, flow_step_id, data, flow_step_config)
     * @param array $tool_definition Tool definition from ai_tools filter
     * @return array Flat parameter structure with extracted content, metadata, and AI parameters merged
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
     * Extract content from data packets for tools requiring content parameter.
     *
     * @param array $data_packet Data packet array (chronological order, newest first)
     * @param array $tool_definition Tool definition with parameters specification
     * @return string|null Content body from latest data packet entry or null if not required
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
     * Extract title from data packets for tools requiring title parameter.
     *
     * @param array $data_packet Data packet array (chronological order, newest first)
     * @param array $tool_definition Tool definition with parameters specification
     * @return string|null Content title from latest data packet entry or null if not required
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
     * Build flat parameter structure for handler tools with engine context.
     *
     * @param array $ai_tool_parameters AI tool call parameters
     * @param array $data Data packet array for content extraction
     * @param array $tool_definition Tool specification
     * @param array $engine_parameters Additional engine context parameters
     * @param array $handler_config Handler-specific settings
     * @return array Flat parameter structure with engine parameters merged
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
        
        foreach ($engine_parameters as $key => $value) {
            $parameters[$key] = $value;
        }
        
        return $parameters;
    }
}