<?php
/**
 * Tool Registration Trait
 *
 * Standardized registration functionality for AI tools (global and chat).
 * Eliminates repetitive registration code across tool implementations.
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
     * @param string $agentType Agent type (global, chat, frontend, supportbot, etc.)
     * @param string $toolName Tool identifier
     * @param array $toolDefinition Tool definition array
     */
    protected function registerTool(string $agentType, string $toolName, array $toolDefinition): void {
        $filterName = "datamachine_{$agentType}_tools";
        add_filter($filterName, function($tools) use ($toolName, $toolDefinition) {
            $tools[$toolName] = $toolDefinition;
            return $tools;
        });
    }

    /**
     * Register a global tool available to all AI agents.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_definition Tool definition array
     */
    protected function registerGlobalTool(string $tool_name, array $tool_definition): void {
        $this->registerTool('global', $tool_name, $tool_definition);
    }

    /**
     * Register a chat-specific tool.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_definition Tool definition array
     */
    protected function registerChatTool(string $tool_name, array $tool_definition): void {
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