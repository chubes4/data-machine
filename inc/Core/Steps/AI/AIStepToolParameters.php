<?php
/**
 * Centralized flat parameter building for AI tool execution.
 *
 * Creates unified flat parameter structures compatible with all handler tool call methods.
 * Provides two build methods: standard parameter building and enhanced building for handler tools
 * with additional engine context (like source_url for Update handlers).
 *
 * Parameter Building Process:
 * 1. Starts with engine parameters as base flat structure
 * 2. Extracts content/title from data packets based on tool specifications
 * 3. Adds tool metadata directly to flat structure
 * 4. Merges AI-provided parameters (overwrites conflicting keys)
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepToolParameters {
    
    /**
     * Build flat parameter structure merging AI parameters with engine context.
     * Creates unified flat array compatible with all handler tool call methods.
     *
     * @param array $ai_tool_parameters Parameters from AI tool call
     * @param array $unified_parameters Unified parameter structure from engine
     * @param array $tool_definition Tool definition from ai_tools filter
     * @return array Flat parameter structure with all data at root level
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
     * Extract content from latest data packet entry if tool requires content parameter.
     *
     * @param array $data_packet Data packet array with chronological entries
     * @param array $tool_definition Tool definition containing parameter specifications
     * @return string|null Content body from latest entry or null if not required
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
     * Extract title from latest data packet entry if tool requires title parameter.
     *
     * @param array $data_packet Data packet array with chronological entries
     * @param array $tool_definition Tool definition containing parameter specifications
     * @return string|null Content title from latest entry or null if not required
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
     * Build parameters for handler tools with additional engine context.
     * Enhanced parameter building that merges engine parameters (like source_url)
     * for specialized handlers like Update handlers requiring source identification.
     *
     * @param array $ai_tool_parameters AI tool call parameters
     * @param array $data Data packet array for content extraction
     * @param array $tool_definition Tool specification
     * @param array $engine_parameters Additional parameters from engine context (source_url, etc.)
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