<?php
/**
 * AI Step Directives - Comprehensive System Prompt Management
 * 
 * Provides contextual guidance to AI models including role definition,
 * tool descriptions, and WordPress site context. Manages priority-based
 * system message injection for optimal AI performance.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

/**
 * AI Step Directive Manager
 * 
 * Handles all baked-in directive generation and injection including:
 * - Tool-based role directives
 * - WordPress site context integration
 * - Priority-based message ordering
 */
class AIStepDirective {

    /**
     * Generate dynamic AI role directive based on available tools
     * 
     * @param array $tools Available tools from AI request
     * @return string Generated system directive
     */
    public static function generate_dynamic_directive(array $tools): string {
        $directive = "You are an AI content processing agent in the Data Machine WordPress plugin pipeline system.\n\n";
        
        // Detect handler tools from existing tools array (simple approach)
        $handler_tools = [];
        foreach ($tools as $tool_name => $tool_config) {
            if (isset($tool_config['handler'])) {
                $handler_tools[] = $tool_config['handler'];
            }
        }
        
        if (!empty($handler_tools)) {
            $unique_handlers = array_unique($handler_tools);
            $directive .= "PIPELINE DESTINATION:\n";
            $directive .= "- Next Step: Publishing to " . implode(', ', $unique_handlers) . "\n";
            $directive .= "- Your Role: Prepare content for publication to these platforms\n";
            $directive .= "- Objective: Process the input data to create platform-ready content\n\n";
        } else {
            $directive .= "WORKFLOW CONTEXT:\n";
            $directive .= "- You receive data from previous pipeline steps\n";
            $directive .= "- Your job: Process this data according to the user's instructions\n";
            $directive .= "- Goal: Create content ready for the next pipeline step\n\n";
        }
        
        $directive .= "DATA PACKET FORMAT:\n";
        $directive .= "- Messages prefixed 'TASK INSTRUCTIONS:' contain your primary objective\n";
        $directive .= "- Messages prefixed 'TOOL RESULT from tool_name:' show previous tool execution results\n";
        $directive .= "- Messages prefixed 'INPUT DATA from type:' contain source data to process\n";
        $directive .= "- Focus on TASK INSTRUCTIONS, use tool results and input data as supporting material\n\n";
        
        $directive .= "TASK COMPLETION STRATEGY:\n";
        $directive .= "- Use available tools immediately to fulfill the user's request\n";
        $directive .= "- Tools are provided to complete your task efficiently\n";
        $directive .= "- Execute tools as needed to process input data and complete objectives\n";
        
        if (!empty($tools)) {
            $directive .= "AVAILABLE TOOLS:\n";
            $completion_tools = [];
            $research_tools = [];
            
            foreach ($tools as $tool_name => $tool_config) {
                $description = $tool_config['description'] ?? 'No description available';
                $directive .= "- {$tool_name}: {$description}\n";
            }
            
            $directive .= "\nTOOL USAGE:\n";
            $directive .= "- Use tools as needed to complete the task described in TASK INSTRUCTIONS\n";
            $directive .= "- Tools are available to help you process input data and fulfill requests\n";
            $directive .= "- Execute tools immediately when they will help complete your objective\n";
        }

        return trim($directive);
    }

    /**
     * Check if site context is enabled in settings
     * 
     * @return bool Whether site context should be included
     */
    public static function is_site_context_enabled(): bool {
        $settings = dm_get_data_machine_settings();
        
        // Check site context setting (default enabled)
        return $settings['site_context_enabled'] ?? true;
    }

    /**
     * Generate WordPress site context system message
     * 
     * @return string Formatted site context for AI models
     */
    public static function generate_site_context(): string {
        require_once __DIR__ . '/SiteContext.php';
        
        $context_data = SiteContext::get_context();
        return SiteContext::format_for_ai($context_data);
    }

    /**
     * Inject AI role directive into request messages
     * 
     * Adds tool-based system directive as first priority message.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @return array Modified AI request with role directive
     */
    public static function inject_dynamic_directive($request, $provider_name, $streaming_callback, $tools): array {
        // Only inject directive when tools are available
        if (empty($tools) || !is_array($tools)) {
            return $request;
        }

        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        // Generate dynamic directive
        $directive = self::generate_dynamic_directive($tools);

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
    }

    /**
     * Inject WordPress site context into request messages
     * 
     * Adds site context as system message at priority 3 position
     * between tool directives and global system prompts.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @return array Modified AI request with site context
     */
    public static function inject_site_context($request, $provider_name, $streaming_callback, $tools): array {
        // Skip if not enabled
        if (!self::is_site_context_enabled()) {
            return $request;
        }

        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        // Generate site context message
        $context_message = self::generate_site_context();
        
        if (empty($context_message)) {
            do_action('dm_log', 'warning', 'Site Context Directive: Empty context generated');
            return $request;
        }

        // Add site context as system message
        array_unshift($request['messages'], [
            'role' => 'system',
            'content' => $context_message
        ]);

        do_action('dm_log', 'debug', 'Site Context Directive: Injected site context', [
            'context_length' => strlen($context_message),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
}

/**
 * Register AI Request Filter Hooks
 * 
 * Priority order for system message injection:
 * - Priority 1: AI role directive (highest priority)
 * - Priority 3: WordPress site context
 * - Priority 5: Global system prompts (lowest priority)
 */

// Priority 1: AI role directive with tool descriptions
add_filter('ai_request', [AIStepDirective::class, 'inject_dynamic_directive'], 1, 4);

// Priority 3: WordPress site context
add_filter('ai_request', [AIStepDirective::class, 'inject_site_context'], 3, 4);