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
            
            // Add handler-specific directives for each detected handler
            $all_handler_directives = apply_filters('dm_handler_directives', []);
            foreach ($unique_handlers as $handler_slug) {
                if (!empty($all_handler_directives[$handler_slug])) {
                    $directive .= "HANDLER-SPECIFIC GUIDANCE FOR " . strtoupper($handler_slug) . ":\n";
                    $directive .= $all_handler_directives[$handler_slug] . "\n\n";
                    
                    do_action('dm_log', 'debug', 'AI Step Directive: Applied handler directive', [
                        'handler_slug' => $handler_slug,
                        'directive_length' => strlen($all_handler_directives[$handler_slug]),
                        'has_directive' => true
                    ]);
                } else {
                    do_action('dm_log', 'debug', 'AI Step Directive: No directive found for handler', [
                        'handler_slug' => $handler_slug,
                        'available_directives' => array_keys($all_handler_directives),
                        'has_directive' => false
                    ]);
                }
            }
        } else {
            $directive .= "WORKFLOW CONTEXT:\n";
            $directive .= "- You receive data from previous pipeline steps\n";
            $directive .= "- Your job: Process this data according to the user's instructions\n";
            $directive .= "- Goal: Create content ready for the next pipeline step\n\n";
        }
        
        // Add general tool directives for critical usage guidance
        $general_tool_directives = self::generate_general_tool_directives($tools);
        if (!empty($general_tool_directives)) {
            $directive .= $general_tool_directives . "\n\n";
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
     * Generate general tool directives for critical usage guidance
     * 
     * @param array $tools Available tools array
     * @return string Generated general tool directives
     */
    public static function generate_general_tool_directives(array $tools): string {
        $directive = "";
        
        // Define critical directives for general tools that need specific usage guidance
        $general_tool_guidance = [
            'local_search' => 'Use local_search to find existing content on this WordPress site. Results include titles, excerpts, and exact permalinks. Use the "link" field for accurate URLs when referencing found content.',
            'google_search' => 'Use google_search to find current information and facts from the web. Results include titles, URLs, and snippets. Use for research and fact-checking when you need external context.',
            'read_post' => 'Use read_post to retrieve full content of existing WordPress posts/pages by ID. Provides complete post content, metadata, and custom fields when include_meta is true.',
            'google_search_console' => 'Use google_search_console to analyze SEO performance data for specific page URLs. Provides search analytics, keyword performance, and click-through rates from Google Search Console.'
        ];
        
        $found_general_tools = [];
        
        // Check which general tools are available (tools without 'handler' property)
        foreach ($tools as $tool_name => $tool_config) {
            if (!isset($tool_config['handler']) && isset($general_tool_guidance[$tool_name])) {
                $found_general_tools[$tool_name] = $general_tool_guidance[$tool_name];
            }
        }
        
        // Generate directives for found general tools
        if (!empty($found_general_tools)) {
            $directive = "GENERAL TOOL USAGE DIRECTIVES:\n";
            foreach ($found_general_tools as $tool_name => $guidance) {
                $directive .= "- {$guidance}\n";
                
                do_action('dm_log', 'debug', 'AI Step Directive: Applied general tool directive', [
                    'tool_name' => $tool_name,
                    'directive_length' => strlen($guidance),
                    'has_directive' => true
                ]);
            }
        }
        
        return $directive;
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
        
        // Pass raw site context as structured JSON with explanation
        $context_message = "WORDPRESS SITE CONTEXT:\n\n";
        $context_message .= "The following structured data provides comprehensive information about this WordPress site:\n\n";
        $context_message .= json_encode($context_data, JSON_PRETTY_PRINT);
        
        return $context_message;
    }

    /**
     * Inject global system prompt into request messages
     * 
     * Adds global background guidance as the highest priority system message.
     * Uses the global_system_prompt setting from Data Machine settings.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified AI request with global system prompt
     */
    public static function inject_global_system_prompt($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        // Get global system prompt from settings
        $settings = get_option('dm_data_machine_settings', []);
        $global_prompt = $settings['global_system_prompt'] ?? '';
        
        // Skip if no global prompt configured
        if (empty($global_prompt)) {
            return $request;
        }

        // Inject global system prompt as highest priority message
        array_unshift($request['messages'], [
            'role' => 'system',
            'content' => trim($global_prompt)
        ]);

        do_action('dm_log', 'debug', 'Global System Prompt: Injected background guidance', [
            'prompt_length' => strlen($global_prompt),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }

    /**
     * Inject pipeline system prompt into request messages
     * 
     * Adds user-configured pipeline instructions as priority 20 system message.
     * This contains the critical internal linking and user workflow instructions.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified AI request with pipeline system prompt
     */
    public static function inject_pipeline_system_prompt($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        // Skip if no pipeline step ID provided
        if (empty($pipeline_step_id)) {
            return $request;
        }

        // Get pipeline step configuration
        $step_ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
        $system_prompt = $step_ai_config['system_prompt'] ?? '';
        
        // Skip if no system prompt configured for this pipeline step
        if (empty($system_prompt)) {
            return $request;
        }

        // Inject pipeline system prompt as priority 20 message
        array_unshift($request['messages'], [
            'role' => 'system',
            'content' => trim($system_prompt)
        ]);

        do_action('dm_log', 'debug', 'Pipeline System Prompt: Injected user configuration', [
            'pipeline_step_id' => $pipeline_step_id,
            'prompt_length' => strlen($system_prompt),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
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
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified AI request with role directive
     */
    public static function inject_dynamic_directive($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
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
     * Adds site context as system message at priority 40 position
     * as the lowest priority environmental information.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified AI request with site context
     */
    public static function inject_site_context($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
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

    /**
     * Inject data packet structure directive into request messages
     * 
     * Adds data packet structure explanation as system message at priority 35 position
     * between tool directives and site context.
     * 
     * @param array $request AI request array
     * @param string $provider_name Provider identifier
     * @param mixed $streaming_callback Streaming callback
     * @param array $tools Available tools array
     * @param string|null $pipeline_step_id Pipeline step ID for context
     * @return array Modified AI request with data packet structure directive
     */
    public static function inject_data_packet_directive($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        // Validate request structure
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $directive = "DATA PACKET STRUCTURE:\n\n";
        $directive .= "You will receive workflow data as a structured data_packets array. Each packet contains:\n\n";
        $directive .= "- type: Packet type (fetch, ai_response, tool_result, user_message, etc.)\n";
        $directive .= "- content: {title: string, body: string} - The actual content data\n";
        $directive .= "- metadata: {source_type, tool_name, flow_step_id, etc.} - Processing metadata\n";
        $directive .= "- timestamp: Unix timestamp of packet creation\n\n";
        $directive .= "Process packets chronologically (oldest first). Interpret structured data directly - no formatting required.";

        // Inject directive as system message
        array_unshift($request['messages'], [
            'role' => 'system',
            'content' => $directive
        ]);

        do_action('dm_log', 'debug', 'AI Step Directive: Injected data packet structure directive', [
            'directive_length' => strlen($directive),
            'provider' => $provider_name,
            'total_messages' => count($request['messages'])
        ]);

        return $request;
    }
}

/**
 * Register AI Request Filter Hooks
 * 
 * Centralized AI message priority system with standardized spacing.
 * 10-unit spacing allows developers to extend with custom priorities (15, 25, 35, etc.)
 * 
 * Priority order for system message injection:
 * - Priority 10: Global system prompt (background guidance)
 * - Priority 20: Pipeline system prompt (user configuration)
 * - Priority 30: Tool definitions and directives (how to use tools)
 * - Priority 35: Data packet structure explanation (workflow data format)
 * - Priority 40: WordPress site context (environment info)
 */

// Priority 10: Global system prompt (background guidance)
add_filter('ai_request', [AIStepDirective::class, 'inject_global_system_prompt'], 10, 5);

// Priority 20: Pipeline system prompt (user configuration - internal linking instructions)
add_filter('ai_request', [AIStepDirective::class, 'inject_pipeline_system_prompt'], 20, 5);

// Priority 30: Tool definitions and directives (how to use available tools)
add_filter('ai_request', [AIStepDirective::class, 'inject_dynamic_directive'], 30, 5);

// Priority 35: Data packet structure explanation (workflow data format)
add_filter('ai_request', [AIStepDirective::class, 'inject_data_packet_directive'], 35, 5);

// Priority 40: WordPress site context (environment info - lowest priority)
add_filter('ai_request', [AIStepDirective::class, 'inject_site_context'], 40, 5);