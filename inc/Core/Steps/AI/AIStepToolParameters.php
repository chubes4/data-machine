<?php
/**
 * Centralized flat parameter building for AI tool execution.
 *
 * Creates unified flat parameter structures compatible with all handler tool call methods,
 * merging AI-provided parameters with engine context and extracting content from data packets.
 *
 * @package DataMachine
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepToolParameters {
    
    /**
     * Build flat parameter structure for AI tool execution.
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