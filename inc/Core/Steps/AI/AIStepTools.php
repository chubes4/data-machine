<?php
/**
 * AI step tool management with three-layer enablement architecture.
 *
 * Implements comprehensive tool discovery and validation:
 * 1. Global Settings: Admin toggles tools site-wide
 * 2. Modal Selection: Per-step tool activation in pipeline configuration
 * 3. Configuration Validation: Runtime checks for tools requiring API keys
 *
 * Tool Categories:
 * - Handler Tools: Step-specific (twitter_publish, wordpress_update) available when next step matches
 * - General Tools: Universal (Google Search, Local Search, WebFetch) available to all AI agents
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

use DataMachine\Core\Steps\AI\AIStepToolParameters;

if (!defined('WPINC')) {
    die;
}

class AIStepTools {
    
    /**
     * Get available general tools (universal tools without handler property).
     * Filters ai_tools for tools without 'handler' key.
     *
     * @return array General tools available to all AI agents
     */
    public function get_global_enabled_tools(): array {
        $all_tools = apply_filters('ai_tools', []);
        $general_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            if (!isset($tool_config['handler'])) {
                $general_tools[$tool_name] = $tool_config;
            }
        }
        
        return $general_tools;
    }
    
    /**
     * Get enabled tools for pipeline step from modal selection.
     * Retrieves user-selected tools from pipeline step configuration.
     *
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Array of enabled tool IDs for this step
     */
    public function get_step_enabled_tools(string $pipeline_step_id): array {
        if (empty($pipeline_step_id)) {
            return [];
        }
        
        $saved_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        $modal_enabled_tools = $saved_step_config['enabled_tools'] ?? [];
        
        return is_array($modal_enabled_tools) ? $modal_enabled_tools : [];
    }
    
    /**
     * Save tool selections from form submission with validation.
     * Validates against global enabled tools and configuration requirements.
     *
     * @param string $pipeline_step_id Pipeline step UUID
     * @param array $post_data Raw POST data from form
     * @return array Validated array of enabled tool IDs
     */
    public function save_tool_selections(string $pipeline_step_id, array $post_data): array {
        if (isset($post_data['enabled_tools']) && is_array($post_data['enabled_tools'])) {
            $raw_enabled_tools = array_map('sanitize_text_field', wp_unslash($post_data['enabled_tools']));
            
            $global_enabled_tools = $this->get_global_enabled_tools();
            $valid_enabled_tools = [];
            
            foreach ($raw_enabled_tools as $tool_id) {
                if (!isset($global_enabled_tools[$tool_id])) {
                    continue;
                }
                
                $tool_config = $global_enabled_tools[$tool_id];
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                $requires_config = !empty($tool_config['requires_config']);
                
                if (!$requires_config || $tool_configured) {
                    $valid_enabled_tools[] = $tool_id;
                }
            }
            
            return array_values($valid_enabled_tools);
        }
        
        return [];
    }
    
    /**
     * Get tools data for modal template rendering.
     * Combines global enabled tools with step-specific selections.
     *
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Tools data for modal template including enablement states
     */
    public function get_tools_data(string $pipeline_step_id): array {
        $global_enabled_tools = $this->get_global_enabled_tools();
        
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
     * Get available tools from adjacent pipeline steps with context awareness.
     * Handler tools become available when adjacent step matches handler type.
     * Combines previous step tools, next step tools, and general tools.
     *
     * @param array|null $previous_step_config Previous step configuration
     * @param array|null $next_step_config Next step configuration
     * @param string|null $current_pipeline_step_id Current pipeline step UUID
     * @return array Combined available tools with context-aware filtering
     */
    public static function getAvailableTools(?array $previous_step_config = null, ?array $next_step_config = null, ?string $current_pipeline_step_id = null): array {
        $available_tools = [];
        
        if ($previous_step_config) {
            $prev_handler_slug = $previous_step_config['handler']['handler_slug'] ?? null;
            $prev_handler_config = $previous_step_config['handler']['settings'] ?? [];
            
            if ($prev_handler_slug) {
                $prev_tools = apply_filters('ai_tools', [], $prev_handler_slug, $prev_handler_config);
                $allowed_prev_tools = self::getAllowedTools($prev_tools, $prev_handler_slug, $current_pipeline_step_id);
                $available_tools = array_merge($available_tools, $allowed_prev_tools);
            }
        }
        
        if ($next_step_config) {
            $next_handler_slug = $next_step_config['handler']['handler_slug'] ?? null;
            $next_handler_config = $next_step_config['handler']['settings'] ?? [];
            
            if ($next_handler_slug) {
                $next_tools = apply_filters('ai_tools', [], $next_handler_slug, $next_handler_config);
                $allowed_next_tools = self::getAllowedTools($next_tools, $next_handler_slug, $current_pipeline_step_id);
                $available_tools = array_merge($available_tools, $allowed_next_tools);
            }
        }
        
        $general_tools = apply_filters('ai_tools', []);
        $allowed_general_tools = self::getAllowedTools($general_tools, null, $current_pipeline_step_id);
        $available_tools = array_merge($available_tools, $allowed_general_tools);
        
        return array_unique($available_tools, SORT_REGULAR);
    }

    /**
     * Filter tools based on enablement and configuration validation.
     * Applies three-layer enablement: global → modal → configuration.
     *
     * @param array $all_tools All available tools
     * @param string|null $handler_slug Handler slug for context filtering
     * @param string|null $pipeline_step_id Pipeline step UUID for modal filtering
     * @return array Filtered tools meeting all enablement requirements
     */
    private static function getAllowedTools(array $all_tools, ?string $handler_slug, ?string $pipeline_step_id = null): array {
        $allowed_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            if (isset($tool_config['handler'])) {
                if ($tool_config['handler'] === $handler_slug) {
                    $allowed_tools[$tool_name] = $tool_config;
                }
                continue;
            }
            
            if ($pipeline_step_id) {
                $tools_instance = new self();
                $step_enabled_tools = $tools_instance->get_step_enabled_tools($pipeline_step_id);
                $step_enabled = in_array($tool_name, $step_enabled_tools);
            } else {
                $step_enabled = self::isGeneralToolEnabled($tool_name);
            }
            
            $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
            $requires_config = !empty($tool_config['requires_config']);
            
            if ($step_enabled && (!$requires_config || $tool_configured)) {
                $allowed_tools[$tool_name] = $tool_config;
            }
        }
        
        return $allowed_tools;
    }

    /**
     * Check if general tool is enabled globally with configuration validation.
     * Tools requiring configuration must pass dm_tool_configured filter.
     *
     * @param string $tool_name Tool identifier
     * @return bool True if tool is globally enabled and configured
     */
    private static function isGeneralToolEnabled(string $tool_name): bool {
        $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
        $all_tools = apply_filters('ai_tools', []);
        $tool_config = $all_tools[$tool_name] ?? [];
        $requires_config = !empty($tool_config['requires_config']);
        
        return !$requires_config || $tool_configured;
    }

    /**
     * Execute tool using flat parameter structure built by AIStepToolParameters.
     * Instantiates tool class and calls handle_tool_call() with comprehensive error handling.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_parameters AI-provided tool parameters
     * @param array $available_tools Available tools array
     * @param array $data Data packet array
     * @param string $flow_step_id Flow step ID for logging
     * @param array $unified_parameters Engine parameters for tool execution
     * @return array Tool execution result with success/error status
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
            $complete_parameters = AIStepToolParameters::buildParameters(
                $tool_parameters, 
                $unified_parameters, 
                $tool_def
            );
            
            
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