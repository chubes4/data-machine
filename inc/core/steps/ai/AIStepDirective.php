<?php
/**
 * AI Step Directive - Foundational System Prompt
 * 
 * Provides contextual guidance to AI models about their role as backend 
 * processing agents within the Data Machine WordPress plugin ecosystem.
 * Dynamically generates tool descriptions based on available tools.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

/**
 * AI Step Directive Generator
 * 
 * Generates simple system directive that informs AI models about their role
 * and available tools using actual descriptions from the codebase.
 */
class AIStepDirective {

    /**
     * Generate simple system directive based on available tools
     * 
     * @param array $tools Available tools from AI request
     * @return string Generated system directive
     */
    public static function generate_dynamic_directive(array $tools): string {
        $directive = "You are an AI processing agent in the Data Machine WordPress plugin.\n\n";
        
        $directive .= "CRITICAL: When tools provide information (like search results), incorporate that information into comprehensive, well-written content. ";
        $directive .= "Do not publish raw tool output - always create polished content that uses tool results as supporting information.\n\n";
        
        if (!empty($tools)) {
            $directive .= "Available Tools:\n";
            foreach ($tools as $tool_name => $tool_config) {
                $description = $tool_config['description'] ?? 'No description available';
                $directive .= "- {$tool_name}: {$description}\n";
            }
        }

        return trim($directive);
    }
}

/**
 * Register AI Request Filter Hook
 * 
 * Injects the dynamic directive as the FIRST system message in all AI requests,
 * ensuring it takes precedence over global system prompts and user prompts.
 */
add_filter('ai_request', function($request, $provider_name, $streaming_callback, $tools) {
    // Only inject directive when tools are available
    if (empty($tools) || !is_array($tools)) {
        return $request;
    }

    // Validate request structure
    if (!isset($request['messages']) || !is_array($request['messages'])) {
        return $request;
    }

    // Generate dynamic directive
    $directive = AIStepDirective::generate_dynamic_directive($tools);

    // Inject directive as FIRST system message
    array_unshift($request['messages'], [
        'role' => 'system',
        'content' => $directive
    ]);

    do_action('dm_log', 'debug', 'AI Step Directive: Injected system directive', [
        'tool_count' => count($tools),
        'available_tools' => array_keys($tools),
        'directive_length' => strlen($directive)
    ]);

    return $request;
}, 1, 4); // Priority 1 = BEFORE global system prompt (priority 5)