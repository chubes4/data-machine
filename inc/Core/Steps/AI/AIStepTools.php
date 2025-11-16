<?php
/**
 * Pipeline-specific tool management for step configuration.
 *
 * Handles pipeline step tool selection, enablement UI, and modal configuration.
 * For shared tool execution logic, see Engine/AI/ToolExecutor.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

if (!defined('WPINC')) {
    die;
}

class AIStepTools {

    public function get_global_enabled_tools(): array {
        // Get global tools (available to all AI agents)
        return apply_filters('datamachine_global_tools', []);
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
}