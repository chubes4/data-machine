<?php
/**
 * AI Step Tools Management
 * 
 * Centralized management for AI step tool discovery, state management,
 * HTML generation, and form processing. Single source of truth for
 * all AI step tool operations.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * AI Step Tools Management Class
 * 
 * Handles all aspects of AI step tool management including discovery,
 * state persistence, HTML generation, and form processing.
 */
class AIStepTools {
    
    /**
     * Get globally enabled tools from settings
     * 
     * @return array Available tools filtered by settings page
     */
    public function get_global_enabled_tools(): array {
        $settings = dm_get_data_machine_settings();
        
        // Engine mode - no tools available
        if ($settings['engine_mode']) {
            return [];
        }
        
        // Get all registered tools and filter to general tools only
        $all_tools = apply_filters('ai_tools', []);
        $general_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            // Only include general tools (no handler property)
            if (!isset($tool_config['handler'])) {
                $general_tools[$tool_name] = $tool_config;
            }
        }
        
        // Apply settings filtering - determines which tools appear in modal
        if (empty($settings['enabled_tools'])) {
            return $general_tools; // Default: all tools available in modal
        } else {
            $filtered_tools = [];
            foreach ($general_tools as $tool_name => $tool_config) {
                if (!empty($settings['enabled_tools'][$tool_name])) {
                    $filtered_tools[$tool_name] = $tool_config;
                }
            }
            return $filtered_tools;
        }
    }
    
    /**
     * Get modal-level enabled tools for a specific pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Tools enabled for this specific step
     */
    public function get_modal_enabled_tools(string $pipeline_step_id): array {
        if (empty($pipeline_step_id)) {
            do_action('dm_log', 'debug', 'AIStepTools: get_modal_enabled_tools called with empty pipeline_step_id');
            return [];
        }
        
        $saved_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        $modal_enabled_tools = $saved_step_config['enabled_tools'] ?? [];
        
        do_action('dm_log', 'debug', 'AIStepTools: get_modal_enabled_tools retrieved data', [
            'pipeline_step_id' => $pipeline_step_id,
            'saved_step_config_keys' => array_keys($saved_step_config),
            'enabled_tools_raw' => $modal_enabled_tools,
            'enabled_tools_is_array' => is_array($modal_enabled_tools),
            'enabled_tools_count' => is_array($modal_enabled_tools) ? count($modal_enabled_tools) : 0
        ]);
        
        // Ensure we have a clean array (empty array = all tools unchecked for new steps)
        return is_array($modal_enabled_tools) ? $modal_enabled_tools : [];
    }
    
    /**
     * Save tool selections for a pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @param array $post_data POST data from form submission
     * @return array Updated enabled_tools array
     */
    public function save_tool_selections(string $pipeline_step_id, array $post_data): array {
        do_action('dm_log', 'debug', 'AIStepTools: save_tool_selections called', [
            'pipeline_step_id' => $pipeline_step_id,
            'post_data_keys' => array_keys($post_data),
            'has_enabled_tools' => isset($post_data['enabled_tools']),
            'enabled_tools_raw' => $post_data['enabled_tools'] ?? null
        ]);
        
        // Process enabled tools from modal form data (enabled_tools[] array)
        if (isset($post_data['enabled_tools']) && is_array($post_data['enabled_tools'])) {
            $raw_enabled_tools = array_map('sanitize_text_field', wp_unslash($post_data['enabled_tools']));
            
            // Filter out unconfigured tools that require configuration
            $global_enabled_tools = $this->get_global_enabled_tools();
            $valid_enabled_tools = [];
            
            foreach ($raw_enabled_tools as $tool_id) {
                if (!isset($global_enabled_tools[$tool_id])) {
                    continue; // Tool not available globally
                }
                
                $tool_config = $global_enabled_tools[$tool_id];
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                $requires_config = !empty($tool_config['requires_config']);
                
                // Only allow tools that are properly configured (if config is required)
                if (!$requires_config || $tool_configured) {
                    $valid_enabled_tools[] = $tool_id;
                }
            }
            
            $enabled_tools = array_values($valid_enabled_tools); // Ensure clean indexed array
            
            do_action('dm_log', 'debug', 'AIStepTools: Tools processed and filtered', [
                'pipeline_step_id' => $pipeline_step_id,
                'raw_tools' => $raw_enabled_tools,
                'valid_tools' => $valid_enabled_tools,
                'final_tools' => $enabled_tools,
                'tools_count' => count($enabled_tools),
                'filtered_count' => count($raw_enabled_tools) - count($enabled_tools)
            ]);
            
            return $enabled_tools;
        } else {
            do_action('dm_log', 'debug', 'AIStepTools: No tools selected or invalid data', [
                'pipeline_step_id' => $pipeline_step_id,
                'enabled_tools_isset' => isset($post_data['enabled_tools']),
                'enabled_tools_is_array' => isset($post_data['enabled_tools']) ? is_array($post_data['enabled_tools']) : false
            ]);
            
            return []; // No tools selected
        }
    }
    
    /**
     * Render tools HTML for modal
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return string HTML for tool selection checkboxes
     */
    public function render_tools_html(string $pipeline_step_id): string {
        $global_enabled_tools = $this->get_global_enabled_tools();
        
        // No tools available
        if (empty($global_enabled_tools)) {
            return '';
        }
        
        $modal_enabled_tools = $this->get_modal_enabled_tools($pipeline_step_id);
        
        // Debug logging with timestamp to track modal rendering
        do_action('dm_log', 'debug', 'AIStepTools: render_tools_html called', [
            'timestamp' => date('Y-m-d H:i:s'),
            'microtime' => microtime(true),
            'pipeline_step_id' => $pipeline_step_id,
            'modal_enabled_tools' => $modal_enabled_tools,
            'modal_enabled_tools_count' => count($modal_enabled_tools),
            'available_global_tools' => array_keys($global_enabled_tools)
        ]);
        
        $html = '<tr class="form-field">' . "\n";
        $html .= '    <th scope="row">' . "\n";
        $html .= '        <label>' . esc_html__('Available Tools', 'data-machine') . '</label>' . "\n";
        $html .= '    </th>' . "\n";
        $html .= '    <td>' . "\n";
        $html .= '        <fieldset>' . "\n";
        $html .= '            <legend class="screen-reader-text">' . esc_html__('Select available tools for this AI step', 'data-machine') . '</legend>' . "\n";
        
        foreach ($global_enabled_tools as $tool_id => $tool_config) {
            // Configuration requirements
            $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
            $requires_config = !empty($tool_config['requires_config']);
            $config_needed = $requires_config && !$tool_configured;
            
            // Modal checkbox state: what user selected for this specific pipeline step
            $tool_modal_enabled = in_array($tool_id, $modal_enabled_tools);
            
            // Simple logic: checkbox checked if tool is in enabled_tools array (period)
            $should_be_checked = $tool_modal_enabled;
            
            // Generate simple tool name from tool_id (e.g., "local_search" -> "Local Search")
            $tool_name = $tool_config['name'] ?? ucwords(str_replace('_', ' ', $tool_id));
            
            // Debug log for each tool's state and HTML generation
            do_action('dm_log', 'debug', 'AIStepTools: Individual tool state and HTML', [
                'pipeline_step_id' => $pipeline_step_id,
                'tool_id' => $tool_id,
                'tool_modal_enabled' => $tool_modal_enabled,
                'tool_configured' => $tool_configured,
                'requires_config' => $requires_config,
                'config_needed' => $config_needed,
                'should_be_checked' => $should_be_checked,
                'will_generate_checked_html' => $should_be_checked ? 'checked="checked"' : '',
                'will_generate_disabled_html' => $config_needed ? 'disabled="disabled"' : ''
            ]);
            
            $html .= '            <div class="dm-tool-option">' . "\n";
            $html .= '                <label>' . "\n";
            
            // Generate the checkbox HTML attributes
            $checked_attr = checked($should_be_checked, true, false);
            $disabled_attr = disabled($config_needed, true, false);
            
            // Log the actual HTML attributes being generated
            do_action('dm_log', 'debug', 'AIStepTools: Generated HTML attributes', [
                'pipeline_step_id' => $pipeline_step_id,
                'tool_id' => $tool_id,
                'should_be_checked_value' => $should_be_checked,
                'config_needed_value' => $config_needed,
                'checked_attr_output' => $checked_attr,
                'disabled_attr_output' => $disabled_attr
            ]);
            
            $html .= '                    <input type="checkbox" name="enabled_tools[]" value="' . esc_attr($tool_id) . '" ' . $checked_attr . ' ' . $disabled_attr . ' />' . "\n";
            $html .= '                    <span>' . esc_html($tool_name) . '</span>' . "\n";
            $html .= '                </label>' . "\n";
            
            // Only show configuration link for tools that need configuration but aren't configured
            if ($config_needed) {
                $html .= '                <span class="dm-tool-config-warning">' . "\n";
                $html .= '                    ⚠ <a href="' . esc_url(admin_url('options-general.php?page=data-machine-settings')) . '" target="_blank">' . esc_html__('Configure in settings', 'data-machine') . '</a>' . "\n";
                $html .= '                </span>' . "\n";
            }
            
            $html .= '            </div>' . "\n";
        }
        
        $html .= '        </fieldset>' . "\n";
        $html .= '    </td>' . "\n";
        $html .= '</tr>' . "\n";
        
        // Final debug log of complete generated HTML
        do_action('dm_log', 'debug', 'AIStepTools: Complete HTML generated', [
            'pipeline_step_id' => $pipeline_step_id,
            'html_length' => strlen($html),
            'contains_checked' => strpos($html, 'checked="checked"') !== false,
            'checked_count' => substr_count($html, 'checked="checked"'),
            'disabled_count' => substr_count($html, 'disabled="disabled"')
        ]);
        
        return $html;
    }
    
    
    /**
     * Generate a human-readable summary of tool execution results
     * 
     * @param string $tool_name Tool that was executed
     * @param array $tool_data Data returned by tool
     * @param array $parameters Parameters passed to tool
     * @return string Human-readable summary
     */
    public function generate_tool_result_summary(string $tool_name, array $tool_data, array $parameters): string {
        switch ($tool_name) {
            case 'local_search':
                $results_count = $tool_data['results_count'] ?? 0;
                $query = $tool_data['query'] ?? '';
                
                if ($results_count === 0) {
                    return "Local search for '{$query}' returned no results.";
                }
                
                $summary = "Local search for '{$query}' found {$results_count} results:\n\n";
                $results = $tool_data['results'] ?? [];
                
                foreach (array_slice($results, 0, 5) as $result) { // Show max 5 results in summary
                    $title = $result['title'] ?? 'Untitled';
                    $link = $result['link'] ?? '#';
                    $excerpt = $result['excerpt'] ?? '';
                    
                    $summary .= "• [{$title}]({$link})\n";
                    if (!empty($excerpt)) {
                        $summary .= "  " . wp_trim_words($excerpt, 15) . "\n";
                    }
                    $summary .= "\n";
                }
                
                return $summary;
                
            case 'google_search':
                $results_count = count($tool_data['results'] ?? []);
                $query = $tool_data['query'] ?? '';
                
                if ($results_count === 0) {
                    return "Google search for '{$query}' returned no results.";
                }
                
                $summary = "Google search for '{$query}' found {$results_count} results:\n\n";
                $results = $tool_data['results'] ?? [];
                
                foreach (array_slice($results, 0, 3) as $result) { // Show max 3 results in summary
                    $title = $result['title'] ?? 'Untitled';
                    $link = $result['link'] ?? '#';
                    $snippet = $result['snippet'] ?? '';
                    
                    $summary .= "• [{$title}]({$link})\n";
                    if (!empty($snippet)) {
                        $summary .= "  " . wp_trim_words($snippet, 20) . "\n";
                    }
                    $summary .= "\n";
                }
                
                return $summary;
                
            default:
                // Generic tool result summary
                if (!empty($tool_data)) {
                    $data_summary = [];
                    foreach ($tool_data as $key => $value) {
                        if (is_scalar($value)) {
                            $data_summary[] = "{$key}: {$value}";
                        } elseif (is_array($value)) {
                            $data_summary[] = "{$key}: " . count($value) . " items";
                        }
                    }
                    return "Tool '{$tool_name}' executed successfully:\n" . implode(", ", $data_summary);
                }
                
                return "Tool '{$tool_name}' executed successfully.";
        }
    }
}