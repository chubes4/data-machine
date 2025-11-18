<?php
/**
 * Universal AI tool execution infrastructure.
 *
 * Shared tool execution logic used by both Chat and Pipeline agents.
 * Handles tool discovery, validation, execution, and parameter building.
 *
 * @package DataMachine\Engine\AI\Tools
 * @since 0.2.0
 */

namespace DataMachine\Engine\AI\Tools;

defined('ABSPATH') || exit;

class ToolExecutor {

    /**
     * Get available tools for AI agent execution.
     * Used by both chat and pipeline agents.
     *
     * @param array|null $previous_step_config Previous step configuration (pipeline only)
     * @param array|null $next_step_config Next step configuration (pipeline only)
     * @param string|null $current_pipeline_step_id Current pipeline step ID (pipeline only)
     * @return array Available tools array
     */
    public static function getAvailableTools(?array $previous_step_config = null, ?array $next_step_config = null, ?string $current_pipeline_step_id = null): array {
        $available_tools = [];

        if ($previous_step_config) {
            $prev_handler_slug = $previous_step_config['handler_slug'] ?? null;
            $prev_handler_config = $previous_step_config['handler_config'] ?? [];

            if ($prev_handler_slug) {
                $prev_tools = apply_filters('chubes_ai_tools', [], $prev_handler_slug, $prev_handler_config);
                $allowed_prev_tools = self::getAllowedTools($prev_tools, $prev_handler_slug, $current_pipeline_step_id);
                $available_tools = array_merge($available_tools, $allowed_prev_tools);
            }
        }

        if ($next_step_config) {
            $next_handler_slug = $next_step_config['handler_slug'] ?? null;
            $next_handler_config = $next_step_config['handler_config'] ?? [];

            if ($next_handler_slug) {
                $next_tools = apply_filters('chubes_ai_tools', [], $next_handler_slug, $next_handler_config);
                $allowed_next_tools = self::getAllowedTools($next_tools, $next_handler_slug, $current_pipeline_step_id);
                $available_tools = array_merge($available_tools, $allowed_next_tools);
            }
        }

        // Load global tools (available to all AI agents)
        $global_tools = apply_filters('datamachine_global_tools', []);
        $allowed_global_tools = self::getAllowedTools($global_tools, null, $current_pipeline_step_id);
        $available_tools = array_merge($available_tools, $allowed_global_tools);

        return array_unique($available_tools, SORT_REGULAR);
    }

    /**
     * Get allowed tools based on enablement and configuration.
     *
     * @param array $all_tools All available tools
     * @param string|null $handler_slug Handler slug for filtering
     * @param string|null $pipeline_step_id Pipeline step ID (pipeline only, null for chat)
     * @return array Filtered allowed tools
     */
    private static function getAllowedTools(array $all_tools, ?string $handler_slug, ?string $pipeline_step_id = null): array {
        $allowed_tools = [];
        $tool_manager = new ToolManager();

        foreach ($all_tools as $tool_name => $tool_config) {
            if (isset($tool_config['handler'])) {
                if ($tool_config['handler'] === $handler_slug) {
                    $allowed_tools[$tool_name] = $tool_config;
                }
                continue;
            }

            // Direct ToolManager call replaces filter
            if ($tool_manager->is_tool_available($tool_name, $pipeline_step_id)) {
                $allowed_tools[$tool_name] = $tool_config;
            }
        }

        return $allowed_tools;
    }

    /**
     * Execute tool with parameter merging and comprehensive error handling.
     * Builds complete parameters by combining AI parameters with step payload.
     *
     * @param string $tool_name Tool name to execute
     * @param array $tool_parameters Parameters from AI
     * @param array $available_tools Available tools array
     * @param array $payload Step payload (job_id, flow_step_id, data, flow_step_config, engine_data)
     * @return array Tool execution result
     */
    public static function executeTool(string $tool_name, array $tool_parameters, array $available_tools, array $payload): array {
        $tool_def = $available_tools[$tool_name] ?? null;
        if (!$tool_def) {
            return [
                'success' => false,
                'error' => "Tool '{$tool_name}' not found",
                'tool_name' => $tool_name
            ];
        }

        $complete_parameters = ToolParameters::buildParameters(
            $tool_parameters,
            $payload,
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
    }
}
