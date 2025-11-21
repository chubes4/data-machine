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

/**
 * Register AI step type and configuration filters.
 *
 * Registers the AI step type for pipeline workflows and configures
 * AI provider settings and directive management.
 *
 * @since 0.1.0
 */
function datamachine_register_ai_step_filters() {

    add_filter('datamachine_step_types', function($steps) {
        $steps['ai'] = [
            'label' => 'AI Agent',
            'description' => 'Configure an intelligent agent with custom prompts and tools to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)',
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
            'consume_all_packets' => true,
            'position' => 20,
            'uses_handler' => false,
            'has_pipeline_config' => true
        ];
        return $steps;
    });

    add_filter('datamachine_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step',
            'button_text' => 'Configure',
            'label' => 'AI Agent Configuration'
        ];
        return $configs;
    });

}

add_filter('datamachine_parse_ai_response', '__return_empty_array');

datamachine_register_ai_step_filters();

add_action('chubes_ai_library_error', function($error_data) {
    do_action('datamachine_log', 'error', 'AI Library Error: ' . $error_data['component'] . ' - ' . $error_data['message'], [
        'component' => $error_data['component'],
        'message' => $error_data['message'],
        'context' => $error_data['context'],
        'timestamp' => $error_data['timestamp']
    ]);
});