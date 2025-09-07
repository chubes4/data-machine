<?php
/**
 * AI step tool management
 *
 * Three-layer tool enablement system: global availability → modal selection → configuration validation.
 * Handles handler-specific tools (context-aware) and general tools (universal availability).
 * Integrates with AIStepToolParameters for consistent parameter building across all tool types.
 *
 * Tool Discovery:
 * - Handler tools: Available only when next step matches handler type
 * - General tools: Available to all AI agents when enabled and configured
 * - Dynamic tool generation: Tools are discovered via ai_tools filter with handler context
 * 
 * Validation Pipeline:
 * - Global tools: All registered tools are available by default
 * - Modal enablement: Per-step tool selection in pipeline configuration
 * - Configuration check: Tools requiring configuration must pass dm_tool_configured filter
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

use DataMachine\Core\Steps\AI\AIStepToolParameters;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * AI step tool management
 * 
 * Provides comprehensive tool management for AI processing steps with three-layer validation:
 * global availability, modal selection, and configuration requirements.
 */
class AIStepTools {
    
    /**
     * Get available general tools
     * 
     * @return array Available tools
     */
    public function get_global_enabled_tools(): array {
        // Get all registered tools and filter to general tools only
        $all_tools = apply_filters('ai_tools', []);
        $general_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            // Only include general tools (no handler property)
            if (!isset($tool_config['handler'])) {
                $general_tools[$tool_name] = $tool_config;
            }
        }
        
        // All general tools are available - no global enable/disable filtering
        return $general_tools;
    }
    
    /**
     * Get enabled tools for pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Enabled tools
     */
    public function get_step_enabled_tools(string $pipeline_step_id): array {
        if (empty($pipeline_step_id)) {
            return [];
        }
        
        $saved_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        $modal_enabled_tools = $saved_step_config['enabled_tools'] ?? [];
        
        // Ensure we have a clean array (empty array = all tools unchecked for new steps)
        return is_array($modal_enabled_tools) ? $modal_enabled_tools : [];
    }
    
    /**
     * Save tool selections for a pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @param array $post_data POST data from form submission
     * @return array Updated enabled_tools array
     */
    public function save_tool_selections(string $pipeline_step_id, array $post_data): array {
        // Process enabled tools from modal form data (enabled_tools[] array)
        if (isset($post_data['enabled_tools']) && is_array($post_data['enabled_tools'])) {
            $raw_enabled_tools = array_map('sanitize_text_field', wp_unslash($post_data['enabled_tools']));
            
            // Filter out unconfigured tools that require configuration
            $global_enabled_tools = $this->get_global_enabled_tools();
            $valid_enabled_tools = [];
            
            foreach ($raw_enabled_tools as $tool_id) {
                if (!isset($global_enabled_tools[$tool_id])) {
                    continue; // Tool not available
                }
                
                $tool_config = $global_enabled_tools[$tool_id];
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                $requires_config = !empty($tool_config['requires_config']);
                
                // Only allow tools that are properly configured (if config is required)
                if (!$requires_config || $tool_configured) {
                    $valid_enabled_tools[] = $tool_id;
                }
            }
            
            return array_values($valid_enabled_tools); // Ensure clean indexed array
        }
        
        return []; // No tools selected
    }
    
    /**
     * Get tools data for template rendering
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Tools data for template consumption
     */
    public function get_tools_data(string $pipeline_step_id): array {
        $global_enabled_tools = $this->get_global_enabled_tools();
        
        // No tools available
        if (empty($global_enabled_tools)) {
            return [];
        }
        
        $modal_enabled_tools = $this->get_step_enabled_tools($pipeline_step_id);
        
        return [
            'global_enabled_tools' => $global_enabled_tools,
            'modal_enabled_tools' => $modal_enabled_tools,
            'pipeline_step_id' => $pipeline_step_id
        ];
    }
    
    
    /**
     * Get available tools for the next step in pipeline
     * 
     * Discovers handler-specific and general tools based on next step configuration.
     * Handler tools are only available when the next step matches the handler type.
     * 
     * @param array $next_step_config Next step configuration including handler info
     * @param string|null $current_pipeline_step_id Current AI step's pipeline step ID for tool filtering
     * @return array Available tools filtered by step configuration and enablement
     */
    public static function getAvailableToolsForNextStep(array $next_step_config, ?string $current_pipeline_step_id = null): array {
        // Determine handler context from next step
        $handler_slug = $next_step_config['handler']['handler_slug'] ?? null;
        $handler_config = $next_step_config['handler']['settings'] ?? [];
        
        // Pass handler context to ai_tools filter for dynamic tool generation
        $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
        
        return self::getAllowedTools($all_tools, $handler_slug, $current_pipeline_step_id);
    }

    /**
     * Filter tools based on enablement and configuration
     * 
     * @param array $all_tools All discovered tools
     * @param string|null $handler_slug Handler slug for context
     * @param string|null $pipeline_step_id Pipeline step ID for step-level tool filtering
     * @return array Allowed tools that are enabled and configured
     */
    private static function getAllowedTools(array $all_tools, ?string $handler_slug, ?string $pipeline_step_id = null): array {
        $allowed_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            // Handler tools: Only available when next step matches handler
            if (isset($tool_config['handler'])) {
                if ($tool_config['handler'] === $handler_slug) {
                    // Handler tool matches next step - always allow
                    $allowed_tools[$tool_name] = $tool_config;
                }
                // Handler tool doesn't match - skip
                continue;
            }
            
            // General tools: Check step-level enablement as final authority
            if ($pipeline_step_id) {
                // Create instance to access step-level tools
                $tools_instance = new self();
                $step_enabled_tools = $tools_instance->get_step_enabled_tools($pipeline_step_id);
                $step_enabled = in_array($tool_name, $step_enabled_tools);
            } else {
                // No pipeline step ID - fall back to global enablement only
                $step_enabled = self::isGeneralToolEnabled($tool_name);
            }
            
            $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
            $requires_config = !empty($tool_config['requires_config']);
            
            // Step-level enablement is the final authority - must pass all checks
            if ($step_enabled && (!$requires_config || $tool_configured)) {
                $allowed_tools[$tool_name] = $tool_config;
            }
        }
        
        return $allowed_tools;
    }

    /**
     * Check if a general tool is enabled at global settings level
     * 
     * @param string $tool_name Tool name to check
     * @return bool Whether tool is enabled globally
     */
    private static function isGeneralToolEnabled(string $tool_name): bool {
        // All general tools are enabled - check configuration status only
        $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
        $all_tools = apply_filters('ai_tools', []);
        $tool_config = $all_tools[$tool_name] ?? [];
        $requires_config = !empty($tool_config['requires_config']);
        
        // Tool is enabled if it doesn't require config OR if it's properly configured
        return !$requires_config || $tool_configured;
    }

    /**
     * Execute a single tool using flat parameter structure
     *
     * All tools receive standardized flat parameter structure built by AIStepToolParameters.
     * Handles both handler-specific and general tools uniformly.
     *
     * @param string $tool_name Tool name to execute
     * @param array $tool_parameters Parameters from AI tool call
     * @param array $available_tools Available tools definition
     * @param array $data Data packet for content extraction
     * @param string $flow_step_id Flow step ID for logging
     * @param array $unified_parameters Unified parameter structure from engine
     * @return array Tool execution result
     */
    public static function executeTool(string $tool_name, array $tool_parameters, array $available_tools, array $data, string $flow_step_id, array $unified_parameters): array {
        $tool_def = $available_tools[$tool_name] ?? null;
        if (!$tool_def) {
            return [
                'success' => false,
                'error' => "Tool '{$tool_name}' not found",
                'tool_name' => $tool_name
            ];
        }

        try {
            // Unified parameter building for all tools
            $complete_parameters = AIStepToolParameters::buildParameters(
                $tool_parameters, 
                $unified_parameters, 
                $tool_def
            );
            
            do_action('dm_log', 'debug', 'AIStepTools: Using unified parameter building', [
                'flow_step_id' => $flow_step_id,
                'tool_name' => $tool_name,
                'handler' => $tool_def['handler'] ?? null,
                'has_metadata' => !empty($unified_parameters['metadata'])
            ]);
            
            // Direct tool execution following established pattern
            $class_name = $tool_def['class'];
            if (!class_exists($class_name)) {
                return [
                    'success' => false,
                    'error' => "Tool class '{$class_name}' not found",
                    'tool_name' => $tool_name
                ];
            }
            
            $tool_handler = new $class_name();
            $tool_result = $tool_handler->handle_tool_call($complete_parameters, $tool_def);
            
            return $tool_result;
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AIStepTools: Tool execution exception', [
                'flow_step_id' => $flow_step_id,
                'tool_name' => $tool_name,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Tool execution exception: ' . $e->getMessage(),
                'tool_name' => $tool_name
            ];
        }
    }





}