<?php
/**
 * AI Tool Parameter Builder
 * 
 * Unified flat parameter architecture for AI tool execution.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepToolParameters {
    
    public static function buildParameters(
        array $ai_tool_parameters,
        array $unified_parameters,
        array $tool_definition
    ): array {
        // Base engine parameters
        $parameters = $unified_parameters;
        
        // Extract content/title
        $parameters['content'] = self::extractContent($unified_parameters['data'] ?? [], $tool_definition);
        $parameters['title'] = self::extractTitle($unified_parameters['data'] ?? [], $tool_definition);
        
        // Tool metadata
        $parameters['tool_definition'] = $tool_definition;
        $parameters['tool_name'] = $tool_definition['name'] ?? null;
        $parameters['handler_config'] = $tool_definition['handler_config'] ?? [];
        
        // Merge AI parameters
        foreach ($ai_tool_parameters as $key => $value) {
            $parameters[$key] = $value;
        }
        
        return $parameters;
    }
    
    private static function extractContent(array $data_packet, array $tool_definition): ?string {
        $tool_params = $tool_definition['parameters'] ?? [];
        if (!isset($tool_params['content'])) {
            return null;
        }
        
        $latest_entry = !empty($data_packet) ? $data_packet[0] : [];
        $content_data = $latest_entry['content'] ?? [];
        return $content_data['body'] ?? null;
    }
    
    private static function extractTitle(array $data_packet, array $tool_definition): ?string {
        $tool_params = $tool_definition['parameters'] ?? [];
        if (!isset($tool_params['title'])) {
            return null;
        }
        
        $latest_entry = !empty($data_packet) ? $data_packet[0] : [];
        $content_data = $latest_entry['content'] ?? [];
        return $content_data['title'] ?? null;
    }
    
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
        
        // Merge engine context
        foreach ($engine_parameters as $key => $value) {
            $parameters[$key] = $value;
        }
        
        return $parameters;
    }
}