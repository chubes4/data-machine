<?php
/**
 * Centralized tool management for Data Machine AI system.
 *
 * Use-case agnostic tool management serving both Chat and Pipeline agents.
 * Handles tool discovery, configuration, enablement, and validation.
 *
 * @package DataMachine\Engine\AI\Tools
 * @since 0.2.1
 */

namespace DataMachine\Engine\AI\Tools;

defined('ABSPATH') || exit;

class ToolManager {

    // ============================================
    // TOOL DISCOVERY
    // ============================================

    /**
     * Get all global tools (handler-agnostic).
     *
     * @return array All global tools
     */
    public function get_global_tools(): array {
        return apply_filters('datamachine_global_tools', []);
    }

    /**
     * Get globally enabled tools (opt-out pattern).
     *
     * @return array Globally enabled tool IDs
     */
    public function get_globally_enabled_tools(): array {
        $all_settings = get_option('datamachine_settings', []);
        return array_keys($all_settings['enabled_tools'] ?? []);
    }

    // ============================================
    // CONFIGURATION STATUS
    // ============================================

    /**
     * Check if tool is configured.
     *
     * @param string $tool_id Tool identifier
     * @return bool True if configured
     */
    public function is_tool_configured(string $tool_id): bool {
        // If tool doesn't require configuration, it's always configured
        if (!$this->requires_configuration($tool_id)) {
            return true;
        }
        return apply_filters('datamachine_tool_configured', false, $tool_id);
    }

    /**
     * Check if tool requires configuration.
     *
     * @param string $tool_id Tool identifier
     * @return bool True if requires config
     */
    public function requires_configuration(string $tool_id): bool {
        $tools = $this->get_global_tools();
        return !empty($tools[$tool_id]['requires_config']);
    }

    // ============================================
    // GLOBAL ENABLEMENT (OPT-OUT PATTERN)
    // ============================================

    /**
     * Check if tool is globally enabled (opt-out).
     * Configured tools enabled by default unless explicitly disabled.
     *
     * @param string $tool_id Tool identifier
     * @return bool True if globally enabled
     */
    public function is_globally_enabled(string $tool_id): bool {
        $all_settings = get_option('datamachine_settings', []);
        $enabled_tools = $all_settings['enabled_tools'] ?? [];

        // Present in settings = enabled (opt-out pattern)
        return isset($enabled_tools[$tool_id]);
    }

    /**
     * Get list of explicitly disabled tools (opt-out pattern).
     *
     * @return array Tool IDs that are configured but disabled
     */
    public function get_globally_disabled_tools(): array {
        $all_tools = $this->get_global_tools();
        $all_settings = get_option('datamachine_settings', []);
        $enabled_tools = $all_settings['enabled_tools'] ?? [];
        $disabled = [];

        foreach ($all_tools as $tool_id => $tool_config) {
            $configured = $this->is_tool_configured($tool_id);

            // Configured but NOT in enabled_tools = user opted out
            if ($configured && !isset($enabled_tools[$tool_id])) {
                $disabled[] = $tool_id;
            }
        }

        return $disabled;
    }

    // ============================================
    // CONTEXT-AWARE ENABLEMENT
    // ============================================

    /**
     * Get step-enabled tools for specific context.
     * Use-case agnostic - works for pipeline steps or any context ID.
     *
     * @param string|null $context_id Context identifier (pipeline_step_id or null)
     * @return array Enabled tool IDs for context
     */
    public function get_step_enabled_tools(?string $context_id = null): array {
        if (empty($context_id)) {
            return [];
        }

        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $saved_step_config = $db_pipelines->get_pipeline_step_config($context_id);
        $step_tools = $saved_step_config['enabled_tools'] ?? [];

        return is_array($step_tools) ? $step_tools : [];
    }

    /**
     * Check if tool is enabled for specific step/context.
     *
     * @param string $context_id Context identifier
     * @param string $tool_id Tool identifier
     * @return bool True if enabled for context
     */
    public function is_step_tool_enabled(string $context_id, string $tool_id): bool {
        $step_tools = $this->get_step_enabled_tools($context_id);
        return in_array($tool_id, $step_tools);
    }

    // ============================================
    // AVAILABILITY CHECK (REPLACES datamachine_tool_enabled FILTER)
    // ============================================

    /**
     * Check if tool is available for use.
     * Direct logic replacement for datamachine_tool_enabled filter.
     *
     * @param string $tool_id Tool identifier
     * @param string|null $context_id Context ID (pipeline_step_id for pipeline, null for chat)
     * @return bool True if tool is available
     */
    public function is_tool_available(string $tool_id, ?string $context_id = null): bool {
        $tools = $this->get_global_tools();
        $tool_config = $tools[$tool_id] ?? null;

        if (!$tool_config) {
            return false; // Tool doesn't exist
        }

        // Pipeline context: check step-specific selections
        if ($context_id) {
            return $this->is_step_tool_enabled($context_id, $tool_id);
        }

        // Chat context (no context_id): check global enablement + configuration
        if (!$this->is_globally_enabled($tool_id)) {
            return false; // Globally disabled
        }

        $requires_config = $this->requires_configuration($tool_id);
        $configured = $this->is_tool_configured($tool_id);

        return !$requires_config || $configured;
    }

    // ============================================
    // VALIDATION & SAVING
    // ============================================

    /**
     * Validate tool selection against rules.
     *
     * @param string $tool_id Tool identifier
     * @return bool True if valid selection
     */
    public function validate_tool_selection(string $tool_id): bool {
        $tools = $this->get_global_tools();
        if (!isset($tools[$tool_id])) {
            return false; // Tool doesn't exist
        }

        $tool_config = $tools[$tool_id];
        $requires_config = !empty($tool_config['requires_config']);
        $configured = $this->is_tool_configured($tool_id);

        // Must be configured if configuration required
        if ($requires_config && !$configured) {
            return false;
        }

        // Must not be globally disabled (opt-out check)
        if (!$this->is_globally_enabled($tool_id)) {
            return false;
        }

        return true;
    }

    /**
     * Filter valid tools from array of tool IDs.
     *
     * @param array $tool_ids Array of tool identifiers
     * @return array Valid tool IDs only
     */
    public function filter_valid_tools(array $tool_ids): array {
        return array_values(array_filter($tool_ids, [$this, 'validate_tool_selection']));
    }

    /**
     * Save tool selections for context.
     *
     * @param string $context_id Context identifier
     * @param array $tool_ids Tool IDs to save
     * @return array Validated and saved tool IDs
     */
    public function save_step_tool_selections(string $context_id, array $tool_ids): array {
        return $this->filter_valid_tools($tool_ids);
    }

    // ============================================
    // DATA AGGREGATION FOR UI
    // ============================================

    /**
     * Get tools data for step configuration modal.
     *
     * @param string $context_id Context identifier
     * @return array Tools data for modal rendering
     */
    public function get_tools_for_step_modal(string $context_id): array {
        return [
            'global_enabled_tools' => $this->get_global_tools(),
            'modal_enabled_tools' => $this->get_step_enabled_tools($context_id),
            'pipeline_step_id' => $context_id
        ];
    }

    /**
     * Get tools data for settings page.
     *
     * @return array All global tools with status
     */
    public function get_tools_for_settings_page(): array {
        $tools = $this->get_global_tools();
        $data = [];

        foreach ($tools as $tool_id => $tool_config) {
            $data[$tool_id] = [
                'config' => $tool_config,
                'configured' => $this->is_tool_configured($tool_id),
                'globally_enabled' => $this->is_globally_enabled($tool_id),
                'requires_config' => $this->requires_configuration($tool_id)
            ];
        }

        return $data;
    }

    /**
     * Get tools for REST API response.
     *
     * @return array Tools formatted for API
     */
    public function get_tools_for_api(): array {
        $tools = $this->get_global_tools();
        $formatted = [];

        foreach ($tools as $tool_id => $tool_config) {
            $is_globally_enabled = $this->is_globally_enabled($tool_id);

            $formatted[$tool_id] = [
                'label' => $tool_config['label'] ?? ucfirst(str_replace('_', ' ', $tool_id)),
                'description' => $tool_config['description'] ?? '',
                'requires_config' => $this->requires_configuration($tool_id),
                'configured' => $this->is_tool_configured($tool_id),
                'globally_enabled' => $is_globally_enabled
            ];
        }

        return $formatted;
    }

    /**
     * Get opt-out defaults (configured tools).
     * Used for pre-populating settings.
     *
     * @return array Tool IDs that should be enabled by default
     */
    public function get_opt_out_defaults(): array {
        $tools = $this->get_global_tools();
        $defaults = [];

        foreach ($tools as $tool_id => $tool_config) {
            if ($this->is_tool_configured($tool_id)) {
                $defaults[] = $tool_id;
            }
        }

        return $defaults;
    }
}
