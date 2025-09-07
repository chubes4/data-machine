<?php
/**
 * AI Tool Parameter Builder
 * 
 * Builds standardized flat parameter structures for AI tool execution with unified 
 * parameter format compatible with handler tool call methods. Core component of the
 * two-parameter architecture providing simplified, extensible parameter passing.
 *
 * @package DataMachine\Core\Steps\AI
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * AI Tool Parameter Builder
 * 
 * Implements unified flat parameter architecture for AI tool execution.
 * Merges AI-provided parameters with engine context, content extraction, and handler configuration
 * into single flat array structure compatible with all handler tool call methods.
 */
class AIStepToolParameters {
    
    /**
     * Build parameters for AI tool execution
     * 
     * Core method implementing flat parameter architecture. Creates single flat array
     * by merging AI tool parameters, engine context, and extracted content data.
     * Eliminates complex nested structures for simplified tool execution.
     * 
     * @param array $ai_tool_parameters Parameters from AI tool call (content, custom fields)
     * @param array $unified_parameters Engine parameters (job_id, flow_step_id, data, flow_step_config)
     * @param array $tool_definition Tool definition from ai_tools filter (class, method, handler)
     * @return array Flat parameter structure with all data merged at root level
     */
    public static function buildParameters(
        array $ai_tool_parameters,
        array $unified_parameters,
        array $tool_definition
    ): array {
        // Start with engine parameters as base flat structure
        $parameters = $unified_parameters;
        
        // Add extracted content/title directly to flat structure (no nesting)
        $parameters['content'] = self::extractContent($unified_parameters['data'] ?? [], $tool_definition);
        $parameters['title'] = self::extractTitle($unified_parameters['data'] ?? [], $tool_definition);
        
        // Add tool metadata directly to flat structure
        $parameters['tool_definition'] = $tool_definition;
        $parameters['tool_name'] = $tool_definition['name'] ?? null;
        $parameters['handler_config'] = $tool_definition['handler_config'] ?? [];
        
        // Merge AI parameters directly - overwrites any conflicting keys
        foreach ($ai_tool_parameters as $key => $value) {
            $parameters[$key] = $value;
        }
        
        return $parameters;
    }
    
    /**
     * Extract content from data packet based on tool specifications
     * 
     * Extracts 'body' content from latest data packet entry when tool expects content parameter.
     * Returns null if tool doesn't require content or no data available.
     * 
     * @param array $data_packet Data packet array (newest entry first)
     * @param array $tool_definition Tool definition containing parameter specifications
     * @return string|null Extracted body content or null if not required/available
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
     * Extract title from data packet based on tool specifications
     * 
     * Extracts 'title' content from latest data packet entry when tool expects title parameter.
     * Returns null if tool doesn't require title or no data available.
     * 
     * @param array $data_packet Data packet array (newest entry first)
     * @param array $tool_definition Tool definition containing parameter specifications
     * @return string|null Extracted title content or null if not required/available
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
     * Build parameters for handler tools with engine parameters merged
     * 
     * Specialized method for handler tools requiring additional engine context.
     * Extends standard parameter building to include engine-level parameters
     * like source_url (required by Update handlers). Creates complete flat
     * parameter structure compatible with handler tool call methods.
     * 
     * @param array $ai_tool_parameters Parameters from AI tool call (content, custom fields)
     * @param array $data Data packet array for content extraction
     * @param array $tool_definition Tool definition from ai_tools filter
     * @param array $engine_parameters Additional engine context (source_url, image_url, file_path, mime_type)
     * @param array $handler_config Handler-specific configuration settings
     * @return array Complete flat parameter structure with all context merged at root level
     */
    public static function buildForHandlerTool(
        array $ai_tool_parameters,
        array $data,
        array $tool_definition,
        array $engine_parameters,
        array $handler_config
    ): array {
        // Build base unified parameters for standard processing
        $unified_parameters = [
            'data' => $data,
            'handler_config' => $handler_config
        ];
        
        // Process through standard parameter building (flat architecture)
        $parameters = self::buildParameters($ai_tool_parameters, $unified_parameters, $tool_definition);
        
        // Merge additional engine context directly into flat structure
        // Provides source_url for Update handlers, image_url for media handling
        foreach ($engine_parameters as $key => $value) {
            $parameters[$key] = $value;
        }
        
        return $parameters;
    }
}