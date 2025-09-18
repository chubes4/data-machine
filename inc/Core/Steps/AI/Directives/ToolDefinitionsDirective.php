<?php
/**
 * Tool Definitions Directive - Priority 30
 *
 * Injects available tools list, workflow context, and task completion guidance
 * as the fourth directive in the 6-tier AI directive system. Provides complete
 * pipeline workflow context and tool orchestration instructions.
 *
 * Priority Order in 6-Tier System:
 * 1. Priority 5 - Plugin Core Directive
 * 2. Priority 10 - Global System Prompt
 * 3. Priority 20 - Pipeline System Prompt
 * 4. Priority 30 - Tool Definitions and Workflow Context (THIS CLASS)
 * 5. Priority 40 - Data Packet Structure
 * 6. Priority 50 - WordPress Site Context
 */

namespace DataMachine\Core\Steps\AI\Directives;

defined('ABSPATH') || exit;

class ToolDefinitionsDirective {
    
    /**
     * Inject tool definitions and workflow context into AI request.
     *
     * @param array $request AI request array with messages
     * @param string $provider_name AI provider name
     * @param callable|null $streaming_callback Streaming callback (unused)
     * @param array $tools Available tools array from AIStepTools
     * @param string|null $pipeline_step_id Pipeline step ID for workflow context
     * @return array Modified request with tool definitions and workflow context added
     */
    public static function inject($request, $provider_name, $streaming_callback, $tools, $pipeline_step_id = null): array {
        if (empty($tools) || !is_array($tools)) {
            return $request;
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        self::require_pipeline_context($pipeline_step_id, __METHOD__);

        $flow_step_id = apply_filters('dm_current_flow_step_id', null);
        
        $directive = self::generate_dynamic_directive($tools, $request, $pipeline_step_id, $flow_step_id);

        array_push($request['messages'], [
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
     * Ensure pipeline context is available for AI directive injection.
     *
     * @param string|null $pipeline_step_id Pipeline step ID
     * @param string $context Method context for error logging
     */
    private static function require_pipeline_context($pipeline_step_id, string $context): void {
        if (empty($pipeline_step_id)) {
            do_action('dm_log', 'error', 'Pipeline context missing', [
                'context' => $context,
                'pipeline_step_id' => $pipeline_step_id
            ]);
                $job_id = apply_filters('dm_current_job_id', null);
            if ($job_id) {
                do_action('dm_fail_job', $job_id, 'missing_pipeline_context', [
                    'context' => $context,
                    'pipeline_step_id' => $pipeline_step_id
                ]);
            }
        }
    }
    
    /**
     * Generate dynamic tool directive based on available tools.
     *
     * @param array $tools Available tools array
     * @param array $request AI request array for context
     * @param string|null $pipeline_step_id Pipeline step ID
     * @param string|null $flow_step_id Flow step ID
     * @return string Generated system directive
     */
    public static function generate_dynamic_directive(array $tools, array $request = [], $pipeline_step_id = null, $flow_step_id = null): string {
        self::require_pipeline_context($pipeline_step_id, __METHOD__);
        if (empty($pipeline_step_id)) {
            return 'ERROR: Missing required pipeline context (pipeline_step_id). Job has been failed.';
        }
        $directive = "AVAILABLE TOOLS AND WORKFLOW ORCHESTRATION:\n\n";
        
        $handler_tools = [];
        foreach ($tools as $tool_name => $tool_config) {
            if (isset($tool_config['handler'])) {
                $handler_tools[] = $tool_config['handler'];
            }
        }
        
        if (!empty($handler_tools)) {
            $unique_handlers = array_unique($handler_tools);
            $directive .= "PRIMARY HANDLER TOOLS:\n";
            $directive .= "Available for this workflow: " . implode(', ', $unique_handlers) . "\n";
            $directive .= "These tools advance your pipeline objective when executed.\n\n";

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
            $turn_count = 0;
            if (!empty($request['messages'])) {
                foreach ($request['messages'] as $message) {
                    if ($message['role'] === 'user') {
                        $turn_count++;
                    }
                }
            }
            
            $directive .= "WORKFLOW STATUS:\n";
            $directive .= "Current Turn Count: {$turn_count}\n";
            if (!empty($handler_tools)) {
                $objective = implode(', ', array_keys($handler_tools));
                $directive .= "Objective: Execute {$objective} handler tools\n";
            }
            $directive .= "Status: Execute handler tools to complete pipeline objective\n\n";
        }
        
        $general_tool_directives = self::generate_general_tool_directives($tools);
        if (!empty($general_tool_directives)) {
            $directive .= $general_tool_directives . "\n\n";
        }
        
        $directive .= "TOOL USAGE STRATEGY:\n";
        $directive .= "- Use handler tools to produce pipeline outputs and advance workflow\n";

        
        if (!empty($tools)) {
            $directive .= "AVAILABLE TOOLS:\n\n";
            
            // Separate tools by type
            $handler_tools = [];
            $general_tools = [];
            foreach ($tools as $tool_name => $tool_config) {
                if (isset($tool_config['handler'])) {
                    $handler_tools[$tool_name] = $tool_config;
                } else {
                    $general_tools[$tool_name] = $tool_config;
                }
            }
            
            // Handler tools: WORKFLOW EXECUTION
            if (!empty($handler_tools)) {
                $directive .= "WORKFLOW EXECUTION TOOLS:\n";
                foreach ($handler_tools as $tool_name => $tool_config) {
                    $description = $tool_config['description'] ?? 'No description available';
                    $directive .= "- {$tool_name}: {$description}\n";
                }
                $directive .= "→ These tools produce pipeline outputs and advance the workflow.\n\n";
            }

            // General tools: CONTEXT GATHERING
            if (!empty($general_tools)) {
                $directive .= "CONTEXT GATHERING TOOLS:\n";
                foreach ($general_tools as $tool_name => $tool_config) {
                    $description = $tool_config['description'] ?? 'No description available';
                    $directive .= "- {$tool_name}: {$description}\n";
                }
            }
            
            // Real workflow context (must succeed now)
            $workflow_context = self::generate_workflow_context_for_directive($pipeline_step_id, $handler_tools, $general_tools, $flow_step_id);
            $directive .= $workflow_context; // generate_workflow_context_for_directive will throw if it cannot build
        }

        return trim($directive);
    }
    
    /**
     * Generate workflow context for AI directive using flow-specific data.
     *
     * @param string $pipeline_step_id Pipeline step ID
     * @param array $handler_tools Available handler tools
     * @param array $general_tools Available general tools
     * @param string|null $flow_step_id Flow step ID
     * @return string Workflow context directive
     */
    private static function generate_workflow_context_for_directive($pipeline_step_id, $handler_tools, $general_tools, $flow_step_id = null): string {
        self::require_pipeline_context($pipeline_step_id, __METHOD__);
        if (empty($pipeline_step_id)) {
            return "WORKFLOW CONTEXT UNAVAILABLE: Missing pipeline context. Job failed.";
        }
        
        // Use flow context when available, otherwise fall back to pipeline context
        if ($flow_step_id) {
            $current_flow_step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
            $flow_id = $current_flow_step_config['flow_id'] ?? null;
            $flow_config = $flow_id ? apply_filters('dm_get_flow_config', [], $flow_id) : [];

            // Missing flow data indicates a serious system error
            if (empty($current_flow_step_config) || !$flow_id || empty($flow_config)) {
                do_action('dm_log', 'error', 'SYSTEM ERROR: Missing flow context during AI execution', [
                    'flow_step_id' => $flow_step_id,
                    'pipeline_step_id' => $pipeline_step_id,
                    'missing_flow_step_config' => empty($current_flow_step_config),
                    'missing_flow_id' => !$flow_id,
                    'missing_flow_config' => empty($flow_config)
                ]);

                $job_id = apply_filters('dm_current_job_id', null);
                if ($job_id) {
                    do_action('dm_fail_job', $job_id, 'missing_flow_context', [
                        'flow_step_id' => $flow_step_id,
                        'pipeline_step_id' => $pipeline_step_id
                    ]);
                }

                return "SYSTEM ERROR: Flow context missing during execution. This indicates a serious system bug.";
            }
            
            // Sort flow steps by execution order
            uasort($flow_config, function($a, $b) {
                $order_a = $a['execution_order'] ?? 999;
                $order_b = $b['execution_order'] ?? 999;
                return $order_a <=> $order_b;
            });
            
            $directive = "WORKFLOW CONTEXT:\n\n";
            
            // Build flow-specific workflow visualization
            $workflow_steps = [];
            $current_position = 0;
            $step_counter = 1;
            
            foreach ($flow_config as $step_id => $flow_step_config) {
                $step_type = $flow_step_config['step_type'] ?? 'Unknown';
                $handler_info = $flow_step_config['handler'] ?? [];
                $handler_slug = $handler_info['handler_slug'] ?? '';
                
                $step_description = self::get_readable_step_description($step_type, $handler_slug);
                $workflow_steps[] = $step_description;
                
                if ($step_id === $flow_step_id) {
                    $current_position = $step_counter;
                }
                $step_counter++;
            }
            
            $directive .= "Pipeline Flow: " . implode(' → ', $workflow_steps) . "\n";
            $directive .= "Current Position: Step {$current_position} of " . count($workflow_steps) . "\n\n";
            
            // Previous step context (flow-specific)
            if ($current_position > 1) {
                $previous_flow_step_id = apply_filters('dm_get_previous_flow_step_id', null, $flow_step_id);
                if ($previous_flow_step_id && isset($flow_config[$previous_flow_step_id])) {
                    $prev_step = $flow_config[$previous_flow_step_id];
                    $prev_description = self::get_readable_step_description(
                        $prev_step['step_type'] ?? 'Unknown',
                        $prev_step['handler']['handler_slug'] ?? ''
                    );
                    $directive .= "Previous Step: {$prev_description} completed\n";
                }
            }
            
            // Current step objective with specific handler tools
            if (!empty($handler_tools)) {
                $tool_names = array_keys($handler_tools);
                $directive .= "Your Objective: Execute " . implode(', ', $tool_names) . " tool(s)\n";
            } else {
                $directive .= "Your Objective: Process content for next step\n";
            }
            
            // Next step context (flow-specific)
            if ($current_position < count($workflow_steps)) {
                $next_flow_step_id = apply_filters('dm_get_next_flow_step_id', null, $flow_step_id);
                if ($next_flow_step_id && isset($flow_config[$next_flow_step_id])) {
                    $next_step = $flow_config[$next_flow_step_id];
                    $next_description = self::get_readable_step_description(
                        $next_step['step_type'] ?? 'Unknown',
                        $next_step['handler']['handler_slug'] ?? ''
                    );
                    $directive .= "Next Step: {$next_description} awaiting your output\n";
                }
            }
            
            // Success criteria
            $directive .= "\nSuccess Criteria: ";
            if (!empty($handler_tools)) {
                $directive .= "Workflow objective achieved when handler tools complete successfully. Stop after successful handler tool execution.";
            } else {
                $directive .= "Content processed for next step";
            }
            $directive .= "\n";
            
            return $directive;
            
        } else {
            // AI steps should always have flow context
            do_action('dm_log', 'error', 'SYSTEM ERROR: AI directive called without flow step ID', [
                'pipeline_step_id' => $pipeline_step_id
            ]);

            $job_id = apply_filters('dm_current_job_id', null);
            if ($job_id) {
                do_action('dm_fail_job', $job_id, 'missing_flow_step_id', [
                    'pipeline_step_id' => $pipeline_step_id
                ]);
            }

            return "SYSTEM ERROR: AI directive called without flow context. This indicates a serious system bug.";
        }
    }
    
    
    /**
     * Generate general tool usage directives.
     *
     * @param array $tools Available tools array
     * @return string General tool directives
     */
    public static function generate_general_tool_directives(array $tools): string {
        $directive = "";
        
        $general_tool_guidance = [
            'local_search' => 'Use local_search to find existing content on this WordPress site. Results include titles, excerpts, and exact permalinks. Use the "link" field for accurate URLs when referencing found content.',
            'google_search' => 'Use google_search to find current information and facts from the web. Results include titles, URLs, and snippets. Use for research and fact-checking when you need external context.'
        ];
        
        $found_general_tools = [];
        
        foreach ($tools as $tool_name => $tool_config) {
            if (!isset($tool_config['handler']) && isset($general_tool_guidance[$tool_name])) {
                $found_general_tools[$tool_name] = $general_tool_guidance[$tool_name];
            }
        }
        
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
     * Convert step types to readable descriptions.
     *
     * @param string $step_type Step type
     * @param string $handler_slug Handler slug
     * @return string Human-readable description
     */
    private static function get_readable_step_description($step_type, $handler_slug = ''): string {
        $handler_map = [
            'twitter' => 'Twitter',
            'facebook' => 'Facebook',
            'threads' => 'Threads',
            'bluesky' => 'Bluesky',
            'wordpress' => 'WordPress',
            'google_sheets' => 'Google Sheets',
            'rss' => 'RSS Feed',
            'reddit' => 'Reddit',
            'files' => 'File Upload',
            'wordpress_media' => 'WordPress Media',
            'wordpress_api' => 'WordPress API'
        ];
        
        $handler_name = $handler_map[$handler_slug] ?? ucfirst(str_replace('_', ' ', $handler_slug));
        
        switch ($step_type) {
            case 'fetch':
                return $handler_name ? "Fetch: {$handler_name}" : "Fetch Data";
            case 'ai':
                return $handler_name ? "AI Agent: {$handler_name} Processing" : "AI Agent: Content Processing";
            case 'publish':
                return $handler_name ? "Publish: {$handler_name}" : "Publish Content";
            case 'update':
                return $handler_name ? "Update: {$handler_name}" : "Update Content";
            default:
                return $handler_name ? "{$handler_name}" : ucfirst($step_type);
        }
    }
}

// Self-register (Priority 30 = fourth in 6-tier directive system)
add_filter('ai_request', [ToolDefinitionsDirective::class, 'inject'], 30, 5);