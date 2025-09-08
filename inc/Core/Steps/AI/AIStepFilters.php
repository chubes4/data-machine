<?php
/**
 * AI Step Registration
 *
 * Auto-registers AI step type, configuration UI, and tool integration via filter system.
 * Supports multi-provider AI integration with dynamic tool discovery.
 *
 * @package DataMachine\Core\Steps\AI
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AI step filters for pipeline integration
 *
 * Registers AI step type with consume_all_packets capability and configuration UI support.
 * Enables tool selection and system prompt configuration via modal interface.
 */
function dm_register_ai_step_filters() {
    
    /**
     * Register AI agent type for pipeline discovery.
     *
     * Configures AI agent with consume_all_packets capability for multi-item processing
     * and positions it appropriately within the step type hierarchy.
     *
     * @param array $steps Current registered steps.
     * @return array Updated steps array including AI agent.
     */
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
    
    
    
    /**
     * Enable AI agent configuration UI support.
     *
     * Registers configuration modal capability enabling the Configure button
     * on AI agent cards with ai_configuration modal type integration.
     *
     * @param array $configs Current step configuration registry.
     * @return array Updated configurations including AI agent settings.
     */
    add_filter('dm_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step', // Links to existing modal content registration
            'button_text' => __('Configure', 'data-machine'),
            'label' => __('AI Agent Configuration', 'data-machine')
        ];
        return $configs;
    });
    
}

/**
 * Register AI response parsing filter
 * Allows handlers to register custom parsing logic for AI-generated structured content
 */
add_filter('dm_parse_ai_response', '__return_empty_array');

/**
 * Extend AI HTTP Client component with Data Machine-specific tool configuration UI.
 *
 * Dynamically injects tool selection interface into AI HTTP Client component rendering:
 * - Filters tools based on Data Machine settings (enabled_tools)
 * - Displays tool configuration status and setup links
 * - Respects engine mode restrictions
 * - Shows only general tools (handler-specific tools excluded)
 */
add_filter('ai_render_component', function($output, $config) {
    // Use centralized tools management
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
            include __DIR__ . '/../Admin/Pages/Pipelines/templates/modal/ai-step-tools.php';
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

/**
 * Auto-register AI agent filters when this file is loaded.
 * 
 * This follows the self-registration pattern established throughout Data Machine.
 * The dm_autoload_core_steps() function will load this file, and filters
 * will be automatically registered without any bootstrap modifications.
 */
dm_register_ai_step_filters();

/**
 * Load general AI tools registration from Tools subdirectory.
 *
 * Includes universal AI tools available to all AI agents regardless of pipeline context.
 * General tools (no 'handler' property) are discoverable across all AI operations,
 * unlike handler-specific tools which are context-dependent.
 */
require_once __DIR__ . '/Tools/GeneralToolsFilters.php';