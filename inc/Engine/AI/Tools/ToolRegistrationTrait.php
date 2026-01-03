<?php
/**
 * Tool Registration Trait
 *
 * Standardized registration functionality for AI tools (global and chat).
 * Eliminates repetitive registration code across tool implementations.
 *
 * IMPORTANT: Tool definitions should be passed as callables to enable lazy
 * evaluation after WordPress translations are loaded. This prevents the
 * "translations loaded too early" error in WordPress 6.7+.
 *
 * @package DataMachine\Engine\AI\Tools
 * @since 0.2.2
 */

namespace DataMachine\Engine\AI\Tools;

defined('ABSPATH') || exit;

trait ToolRegistrationTrait {
    /**
     * Register a tool for any agent type.
     *
     * Agent-agnostic tool registration that dynamically creates the appropriate filter
     * based on the agent type. Enables unlimited agent specialization while maintaining
     * consistent registration patterns.
     *
     * IMPORTANT: Pass a callable (e.g., [$this, 'getToolDefinition']) instead of
     * calling the method directly. This enables lazy evaluation after translations
     * are loaded, preventing WordPress 6.7+ translation timing errors.
     *
     * Example:
     *   // CORRECT - lazy evaluation
     *   $this->registerTool('chat', 'my_tool', [$this, 'getToolDefinition']);
     *
     *   // WRONG - eager evaluation (causes translation errors)
     *   $this->registerTool('chat', 'my_tool', $this->getToolDefinition());
     *
     * @param string $agentType Agent type (global, chat, frontend, supportbot, etc.)
     * @param string $toolName Tool identifier
     * @param array|callable $toolDefinition Tool definition array OR callable that returns it
     */
    protected function registerTool(string $agentType, string $toolName, array|callable $toolDefinition): void {
        $filterName = "datamachine_{$agentType}_tools";
        add_filter($filterName, function($tools) use ($toolName, $toolDefinition) {
            // Store as-is (callable or array) - resolution happens in ToolManager
            $tools[$toolName] = $toolDefinition;
            return $tools;
        });
    }

    /**
     * Register a global tool available to all AI agents.
     *
     * @param string $tool_name Tool identifier
     * @param array|callable $tool_definition Tool definition array OR callable
     */
    protected function registerGlobalTool(string $tool_name, array|callable $tool_definition): void {
        $this->registerTool('global', $tool_name, $tool_definition);
    }

    /**
     * Register a chat-specific tool.
     *
     * @param string $tool_name Tool identifier
     * @param array|callable $tool_definition Tool definition array OR callable
     */
    protected function registerChatTool(string $tool_name, array|callable $tool_definition): void {
        $this->registerTool('chat', $tool_name, $tool_definition);
    }

    /**
     * Register configuration management handlers for tools that need them.
     *
     * @param string $tool_id Tool identifier for configuration
     */
    protected function registerConfigurationHandlers(string $tool_id): void {
        add_filter('datamachine_tool_configured', [$this, 'check_configuration'], 10, 2);
        add_filter('datamachine_get_tool_config', [$this, 'get_configuration'], 10, 2);
        add_action('datamachine_save_tool_config', [$this, 'save_configuration'], 10, 2);
    }
}
