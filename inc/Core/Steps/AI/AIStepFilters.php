<?php
/**
 * AI step registration with tool integration.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_ai_step_filters() {

    add_filter('datamachine_step_types', function($steps) {
        $steps['ai'] = [
            'label' => __('AI Agent', 'datamachine'),
            'description' => __('Configure an intelligent agent with custom prompts and tools to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'datamachine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
            'consume_all_packets' => true,
            'position' => 20,
            'uses_handler' => false
        ];
        return $steps;
    });

    add_filter('datamachine_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step',
            'button_text' => __('Configure', 'datamachine'),
            'label' => __('AI Agent Configuration', 'datamachine')
        ];
        return $configs;
    });

    // Register pipeline-specific tool enablement for universal Engine layer
    add_filter('datamachine_tool_enabled', function($enabled, $tool_name, $tool_config, $context_id) {
        // Pipeline agent: check step-specific tool selections
        if ($context_id) {
            $tools_instance = new AIStepTools();
            $step_enabled_tools = $tools_instance->get_step_enabled_tools($context_id);
            return in_array($tool_name, $step_enabled_tools);
        }

        // No context ID: use global tool enablement logic
        $tool_configured = apply_filters('datamachine_tool_configured', false, $tool_name);
        $requires_config = !empty($tool_config['requires_config']);
        return !$requires_config || $tool_configured;
    }, 10, 4);

}

add_filter('datamachine_parse_ai_response', '__return_empty_array');

// Removed ai_render_component filter extension since the library no longer provides this filter
// AI provider UI is now handled by React components in the frontend

datamachine_register_ai_step_filters();

add_action('chubes_ai_library_error', function($error_data) {
    do_action('datamachine_log', 'error', 'AI Library Error: ' . $error_data['component'] . ' - ' . $error_data['message'], [
        'component' => $error_data['component'],
        'message' => $error_data['message'],
        'context' => $error_data['context'],
        'timestamp' => $error_data['timestamp']
    ]);
});