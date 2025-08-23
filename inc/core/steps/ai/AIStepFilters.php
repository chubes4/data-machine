<?php
/**
 * AI Step Filters Registration
 *
 * WordPress-Native AI Processing
 * 
 * This file enables sophisticated AI workflows through comprehensive self-registration,
 * making AI step functionality completely modular and WordPress-native.
 * 
 * AI Innovation Features:
 * - Multi-provider AI client integration (OpenAI, Anthropic, Google, Grok, OpenRouter)
 * - Intelligent pipeline context management and processing
 * - Universal DataPacket conversion for AI workflows
 * - Self-contained AI component architecture
 * 
 * Implementation Pattern:
 * Components self-register via dedicated *Filters.php files, enabling:
 * - Modular functionality without bootstrap modifications
 * - Clean separation of AI logic from core architecture
 * - Template for extensible AI component development
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
 * Register AI step filters for pipeline integration and configuration support.
 *
 * Establishes complete AI step functionality through self-registration:
 * - Registers AI step type for pipeline discovery with consume_all_packets capability
 * - Enables step configuration UI with modal support
 * - Integrates tool selection and system prompt configuration
 *
 * Called automatically when AI components are loaded via dm_autoload_core_steps().
 *
 * @since 1.0.0
 */
function dm_register_ai_step_filters() {
    
    /**
     * Register AI step type for pipeline discovery.
     *
     * Configures AI step with consume_all_packets capability for multi-item processing
     * and positions it appropriately within the step type hierarchy.
     *
     * @param array $steps Current registered steps.
     * @return array Updated steps array including AI step.
     */
    add_filter('dm_steps', function($steps) {
        $steps['ai'] = [
            'label' => __('AI', 'data-machine'),
            'description' => __('Configure a custom prompt to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
            'consume_all_packets' => true,
            'position' => 20
        ];
        return $steps;
    });
    
    
    
    /**
     * Enable AI step configuration UI support.
     *
     * Registers configuration modal capability enabling the Configure button
     * on AI step cards with ai_configuration modal type integration.
     *
     * @param array $configs Current step configuration registry.
     * @return array Updated configurations including AI step settings.
     */
    add_filter('dm_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step', // Links to existing modal content registration
            'button_text' => __('Configure', 'data-machine'),
            'label' => __('AI Configuration', 'data-machine')
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
    // Get enabled tools directly (no external dependencies)
    $settings = dm_get_data_machine_settings();
    
    // Engine mode - no tools available
    if ($settings['engine_mode']) {
        $enabled_general_tools = [];
    } else {
        // Get all registered tools and filter to general tools
        $all_tools = apply_filters('ai_tools', []);
        $general_tools = [];
        foreach ($all_tools as $tool_name => $tool_config) {
            if (!isset($tool_config['handler'])) {
                $general_tools[$tool_name] = $tool_config;
            }
        }
        
        // Apply settings filtering
        if (empty($settings['enabled_tools'])) {
            $enabled_general_tools = $general_tools; // Default: all enabled
        } else {
            $enabled_general_tools = [];
            foreach ($general_tools as $tool_name => $tool_config) {
                if (!empty($settings['enabled_tools'][$tool_name])) {
                    $enabled_general_tools[$tool_name] = $tool_config;
                }
            }
        }
    }
    
    // Start building the Data Machine extensions
    $extensions = '';
    
    
    // Add tool configuration UI if tools are available
    if (!empty($enabled_general_tools)) {
        $enabled_tools = $ai_config['enabled_tools'] ?? [];
        
        $extensions .= '<tr class="form-field">' . "\n";
        $extensions .= '    <th scope="row">' . "\n";
        $extensions .= '        <label>' . esc_html__('Available Tools', 'data-machine') . '</label>' . "\n";
        $extensions .= '    </th>' . "\n";
        $extensions .= '    <td>' . "\n";
        $extensions .= '        <fieldset>' . "\n";
        $extensions .= '            <legend class="screen-reader-text">' . esc_html__('Select available tools for this AI step', 'data-machine') . '</legend>' . "\n";
        
        foreach ($enabled_general_tools as $tool_id => $tool_config) {
            $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
            $configure_needed = !$tool_configured && !empty($tool_config['requires_config']);
            $tool_enabled = in_array($tool_id, $enabled_tools) && $tool_configured;
            
            $extensions .= '            <div class="dm-tool-option">' . "\n";
            $extensions .= '                <label>' . "\n";
            $extensions .= '                    <input type="checkbox" name="enabled_tools[]" value="' . esc_attr($tool_id) . '" ' . checked($tool_enabled, true, false) . ' ' . disabled($configure_needed, true, false) . ' />' . "\n";
            $extensions .= '                    <span>' . esc_html($tool_config['description'] ?? ucfirst($tool_id)) . '</span>' . "\n";
            $extensions .= '                </label>' . "\n";
            
            // Only show configuration link for tools that need configuration but aren't configured
            if ($configure_needed) {
                $extensions .= '                <span class="dm-tool-config-warning">' . "\n";
                $extensions .= '                    ⚠ <a href="' . esc_url(admin_url('options-general.php?page=data-machine-settings')) . '" target="_blank">' . esc_html__('Configure in settings', 'data-machine') . '</a>' . "\n";
                $extensions .= '                </span>' . "\n";
            }
            
            $extensions .= '            </div>' . "\n";
        }
        
        $extensions .= '            <p class="description">' . esc_html__('Tools provide additional capabilities like web search for fact-checking. Configure required tools before enabling them.', 'data-machine') . '</p>' . "\n";
        $extensions .= '        </fieldset>' . "\n";
        $extensions .= '    </td>' . "\n";
        $extensions .= '</tr>' . "\n";
    }
    
    // Insert extensions before closing table tag
    $output = str_replace('</table>', $extensions . '</table>', $output);
    
    return $output;
}, 20, 2);

/**
 * Auto-register AI step filters when this file is loaded.
 * 
 * This follows the self-registration pattern established throughout Data Machine.
 * The dm_autoload_core_steps() function will load this file, and filters
 * will be automatically registered without any bootstrap modifications.
 */
dm_register_ai_step_filters();

/**
 * Load general AI tools registration from Tools subdirectory.
 *
 * Includes universal AI tools available to all AI steps regardless of pipeline context.
 * General tools (no 'handler' property) are discoverable across all AI operations,
 * unlike handler-specific tools which are context-dependent.
 */
require_once __DIR__ . '/Tools/GeneralToolsFilters.php';