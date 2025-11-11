<?php
/**
 * AI step tool management with three-layer enablement and validation.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

use DataMachine\Core\Steps\AI\AIStepToolParameters;

if (!defined('WPINC')) {
    die;
}

class AIStepTools {
    
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
    
    public function get_step_enabled_tools(string $pipeline_step_id): array {
        if (empty($pipeline_step_id)) {
            return [];
        }
        
        $saved_step_config = apply_filters('datamachine_get_pipeline_step_config', [], $pipeline_step_id);
        $modal_enabled_tools = $saved_step_config['enabled_tools'] ?? [];
        
        return is_array($modal_enabled_tools) ? $modal_enabled_tools : [];
    }
    
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
                $tool_configured = apply_filters('datamachine_tool_configured', false, $tool_id);
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
    
    
    public static function getAvailableTools(?array $previous_step_config = null, ?array $next_step_config = null, ?string $current_pipeline_step_id = null): array {
        $available_tools = [];

        if ($previous_step_config) {
            $prev_handler_slug = $previous_step_config['handler_slug'] ?? null;
            $prev_handler_config = $previous_step_config['handler_config'] ?? [];

            if ($prev_handler_slug) {
                $prev_tools = apply_filters('ai_tools', [], $prev_handler_slug, $prev_handler_config);
                $allowed_prev_tools = self::getAllowedTools($prev_tools, $prev_handler_slug, $current_pipeline_step_id);
                $available_tools = array_merge($available_tools, $allowed_prev_tools);
            }
        }

        if ($next_step_config) {
            $next_handler_slug = $next_step_config['handler_slug'] ?? null;
            $next_handler_config = $next_step_config['handler_config'] ?? [];

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
            
            $tool_configured = apply_filters('datamachine_tool_configured', false, $tool_name);
            $requires_config = !empty($tool_config['requires_config']);
            
            if ($step_enabled && (!$requires_config || $tool_configured)) {
                $allowed_tools[$tool_name] = $tool_config;
            }
        }
        
        return $allowed_tools;
    }

    private static function isGeneralToolEnabled(string $tool_name): bool {
        $tool_configured = apply_filters('datamachine_tool_configured', false, $tool_name);
        $all_tools = apply_filters('ai_tools', []);
        $tool_config = $all_tools[$tool_name] ?? [];
        $requires_config = !empty($tool_config['requires_config']);
        
        return !$requires_config || $tool_configured;
    }

    /**
     * Executes tool with parameter merging and comprehensive error handling.
     * Builds complete parameters by combining AI parameters with unified engine parameters.
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
            do_action('datamachine_log', 'error', 'AIStepTools: Tool execution exception', [
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