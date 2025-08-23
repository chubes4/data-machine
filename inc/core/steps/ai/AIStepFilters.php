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
 * Register AI step-specific filters.
 * 
 * Called automatically when AI step components are loaded via dm_autoload_core_steps().
 * This maintains the self-registration pattern and keeps AI functionality self-contained.
 * 
 * Registered Filters:
 * - dm_steps: Register AI step for pipeline discovery * 
 * @since 1.0.0
 */
function dm_register_ai_step_filters() {
    
    /**
     * AI Step Registration
     * 
     * Register the AI step type for pipeline discovery via pure discovery mode.
     * This enables the AI step to be discovered and used in pipelines.
     * 
     * @param array $steps Current steps array
     * @return array Updated steps array
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
     * AI Step Configuration Registration
     * 
     * Register AI step configuration capability so the pipeline step card shows Configure button.
     * This tells the step-card template that AI steps have configurable options.
     * 
     * @param mixed $config Current step configuration (null if none)
     * @param string $step_type Step type being requested
     * @param array $context Step context data
     * @return array|mixed Step configuration or original value
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
 * Extend AI HTTP Client component with Data Machine tool configuration
 * 
 * Adds tool selection UI and system prompt to the AI component when rendered in Data Machine context.
 * Only shows tools that are enabled in Data Machine settings.
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
 * Load general AI tools registration
 * 
 * Include general tools directory that would be available to all AI steps regardless of
 * the next step's handler when implemented. Architecture ready for tools like
 * search, data processing, analysis, etc.
 */
require_once __DIR__ . '/Tools/GeneralToolsFilters.php';