<?php
/**
 * AI step filter registration with tool integration.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

function dm_register_ai_step_filters() {
    
    add_filter('dm_steps', function($steps) {
        $steps['ai'] = [
            'label' => __('AI Agent', 'data-machine'),
            'description' => __('Configure an intelligent agent with custom prompts and tools to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
            'consume_all_packets' => true,
            'position' => 20
        ];
        return $steps;
    });
    
    add_filter('dm_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step',
            'button_text' => __('Configure', 'data-machine'),
            'label' => __('AI Agent Configuration', 'data-machine')
        ];
        return $configs;
    });
    
}

add_filter('dm_parse_ai_response', '__return_empty_array');

add_filter('ai_render_component', function($output, $config) {
    require_once __DIR__ . '/AIStepTools.php';
    $tools_manager = new \DataMachine\Core\Steps\AI\AIStepTools();
    
    // Start building the Data Machine extensions
    $extensions = '';
    
    // Add tool configuration UI if pipeline step ID is available
    if (isset($config['step_context']['pipeline_step_id'])) {
        $pipeline_step_id = $config['step_context']['pipeline_step_id'];
        $tools_data = $tools_manager->get_tools_data($pipeline_step_id);
        
        // Render tools using template if tools are available
        if (!empty($tools_data)) {
            // Extract data for template
            $global_enabled_tools = $tools_data['global_enabled_tools'];
            $modal_enabled_tools = $tools_data['modal_enabled_tools'];
            
            // Render template
            ob_start();
            include __DIR__ . '/../../Admin/Pages/Pipelines/templates/modal/ai-step-tools.php';
            $tools_html = ob_get_clean();
            
            // Add description before closing tags
            if (!empty($tools_html)) {
                $tools_html = str_replace('</fieldset>', '            <p class="description">' . esc_html__('Tools provide additional capabilities like web search for fact-checking. Configure required tools before enabling them.', 'data-machine') . '</p>' . "\n" . '        </fieldset>', $tools_html);
            }
            
            $extensions .= $tools_html;
        }
    }
    
    // Insert extensions before closing table tag
    $output = str_replace('</table>', $extensions . '</table>', $output);
    
    return $output;
}, 20, 2);

dm_register_ai_step_filters();

// Register AI error handlers
add_action('ai_api_error', function($error_data) {
    do_action('dm_log', 'error', 'AI API Error: ' . $error_data['provider'] . ' ' . $error_data['endpoint'], [
        'provider' => $error_data['provider'],
        'endpoint' => $error_data['endpoint'],
        'error_response' => $error_data['response'],
        'timestamp' => $error_data['timestamp']
    ]);
});

add_action('ai_library_error', function($error_data) {
    do_action('dm_log', 'error', 'AI Library Error: ' . $error_data['component'] . ' - ' . $error_data['message'], [
        'component' => $error_data['component'],
        'message' => $error_data['message'],
        'context' => $error_data['context'],
        'timestamp' => $error_data['timestamp']
    ]);
});

require_once __DIR__ . '/Tools/GeneralToolsFilters.php';