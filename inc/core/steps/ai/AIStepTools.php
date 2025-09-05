<?php
/**
 * AI step tool management
 *
 * Three-layer tool management: global enablement → modal selection → configuration validation.
 * Handles handler-specific tools (next-step context) and general tools (universal).
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * AI step tool management
 */
class AIStepTools {
    
    /**
     * Get available general tools
     * 
     * @return array Available tools
     */
    public function get_global_enabled_tools(): array {
        // Get all registered tools and filter to general tools only
        $all_tools = apply_filters('ai_tools', []);
        $general_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            // Only include general tools (no handler property)
            if (!isset($tool_config['handler'])) {
                $general_tools[$tool_name] = $tool_config;
            }
        }
        
        // All general tools are available - no global enable/disable filtering
        return $general_tools;
    }
    
    /**
     * Get enabled tools for pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Enabled tools
     */
    public function get_step_enabled_tools(string $pipeline_step_id): array {
        if (empty($pipeline_step_id)) {
            return [];
        }
        
        $saved_step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        $modal_enabled_tools = $saved_step_config['enabled_tools'] ?? [];
        
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
        // Process enabled tools from modal form data (enabled_tools[] array)
        if (isset($post_data['enabled_tools']) && is_array($post_data['enabled_tools'])) {
            $raw_enabled_tools = array_map('sanitize_text_field', wp_unslash($post_data['enabled_tools']));
            
            // Filter out unconfigured tools that require configuration
            $global_enabled_tools = $this->get_global_enabled_tools();
            $valid_enabled_tools = [];
            
            foreach ($raw_enabled_tools as $tool_id) {
                if (!isset($global_enabled_tools[$tool_id])) {
                    continue; // Tool not available
                }
                
                $tool_config = $global_enabled_tools[$tool_id];
                $tool_configured = apply_filters('dm_tool_configured', false, $tool_id);
                $requires_config = !empty($tool_config['requires_config']);
                
                // Only allow tools that are properly configured (if config is required)
                if (!$requires_config || $tool_configured) {
                    $valid_enabled_tools[] = $tool_id;
                }
            }
            
            return array_values($valid_enabled_tools); // Ensure clean indexed array
        }
        
        return []; // No tools selected
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
        
        $modal_enabled_tools = $this->get_step_enabled_tools($pipeline_step_id);
        
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
            
            // Get tool description for tooltip
            $tool_description = $tool_config['description'] ?? '';
            
            $html .= '            <div class="dm-tool-option">' . "\n";
            $html .= '                <label>' . "\n";
            
            // Generate the checkbox HTML attributes
            $checked_attr = checked($should_be_checked, true, false);
            $disabled_attr = disabled($config_needed, true, false);
            
            $html .= '                    <input type="checkbox" name="enabled_tools[]" value="' . esc_attr($tool_id) . '" ' . $checked_attr . ' ' . $disabled_attr . ' />' . "\n";
            $html .= '                    <span>' . esc_html($tool_name) . '</span>' . "\n";
            
            // Add info icon with tooltip if description exists
            if (!empty($tool_description)) {
                $html .= '                    <span class="dm-tool-info" data-tooltip="' . esc_attr($tool_description) . '">ⓘ</span>' . "\n";
            }
            
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
        
        return $html;
    }
    
    
    /**
     * Get available tools for the next step in pipeline
     * 
     * Discovers handler-specific and general tools based on next step configuration.
     * Handler tools are only available when the next step matches the handler type.
     * 
     * @param array $next_step_config Next step configuration including handler info
     * @param string|null $current_pipeline_step_id Current AI step's pipeline step ID for tool filtering
     * @return array Available tools filtered by step configuration and enablement
     */
    public static function getAvailableToolsForNextStep(array $next_step_config, ?string $current_pipeline_step_id = null): array {
        // Determine handler context from next step
        $handler_slug = $next_step_config['handler']['handler_slug'] ?? null;
        $handler_config = $next_step_config['handler']['settings'] ?? [];
        
        // Pass handler context to ai_tools filter for dynamic tool generation
        $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
        
        return self::getAllowedTools($all_tools, $handler_slug, $current_pipeline_step_id);
    }

    /**
     * Filter tools based on enablement and configuration
     * 
     * @param array $all_tools All discovered tools
     * @param string|null $handler_slug Handler slug for context
     * @param string|null $pipeline_step_id Pipeline step ID for step-level tool filtering
     * @return array Allowed tools that are enabled and configured
     */
    private static function getAllowedTools(array $all_tools, ?string $handler_slug, ?string $pipeline_step_id = null): array {
        $allowed_tools = [];
        
        foreach ($all_tools as $tool_name => $tool_config) {
            // Handler tools: Only available when next step matches handler
            if (isset($tool_config['handler'])) {
                if ($tool_config['handler'] === $handler_slug) {
                    // Handler tool matches next step - always allow
                    $allowed_tools[$tool_name] = $tool_config;
                }
                // Handler tool doesn't match - skip
                continue;
            }
            
            // General tools: Check step-level enablement as final authority
            if ($pipeline_step_id) {
                // Create instance to access step-level tools
                $tools_instance = new self();
                $step_enabled_tools = $tools_instance->get_step_enabled_tools($pipeline_step_id);
                $step_enabled = in_array($tool_name, $step_enabled_tools);
            } else {
                // No pipeline step ID - fall back to global enablement only
                $step_enabled = self::isGeneralToolEnabled($tool_name);
            }
            
            $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
            $requires_config = !empty($tool_config['requires_config']);
            
            // Step-level enablement is the final authority - must pass all checks
            if ($step_enabled && (!$requires_config || $tool_configured)) {
                $allowed_tools[$tool_name] = $tool_config;
            }
        }
        
        return $allowed_tools;
    }

    /**
     * Check if a general tool is enabled at global settings level
     * 
     * @param string $tool_name Tool name to check
     * @return bool Whether tool is enabled globally
     */
    private static function isGeneralToolEnabled(string $tool_name): bool {
        // All general tools are enabled - check configuration status only
        $tool_configured = apply_filters('dm_tool_configured', false, $tool_name);
        $all_tools = apply_filters('ai_tools', []);
        $tool_config = $all_tools[$tool_name] ?? [];
        $requires_config = !empty($tool_config['requires_config']);
        
        // Tool is enabled if it doesn't require config OR if it's properly configured
        return !$requires_config || $tool_configured;
    }

    /**
     * Execute a single tool and return result
     *
     * @param string $tool_name Tool name to execute
     * @param array $tool_parameters Parameters from AI tool call
     * @param array $available_tools Available tools definition
     * @param array $data Data packet for content extraction
     * @param string $flow_step_id Flow step ID for logging
     * @param string|null $source_url Source URL from engine
     * @param string|null $image_url Image URL from engine
     * @return array Tool execution result
     */
    public static function executeTool(string $tool_name, array $tool_parameters, array $available_tools, array $data, string $flow_step_id, $source_url = null, $image_url = null): array {
        $tool_def = $available_tools[$tool_name] ?? null;
        if (!$tool_def) {
            return [
                'success' => false,
                'error' => "Tool '{$tool_name}' not found",
                'tool_name' => $tool_name
            ];
        }

        try {
            // Build parameters using engine-provided parameters and data content
            $handler_config = $tool_def['handler_config'] ?? [];
            $complete_parameters = self::buildToolParameters($tool_parameters, $data, $source_url, $image_url, $available_tools, $tool_name);
            
            do_action('dm_log', 'debug', 'AIStepTools: Executing tool', [
                'flow_step_id' => $flow_step_id,
                'tool_name' => $tool_name,
                'has_source_url' => isset($complete_parameters['source_url'])
            ]);
            
            // Direct tool execution following established pattern
            $class_name = $tool_def['class'];
            if (!class_exists($class_name)) {
                return [
                    'success' => false,
                    'error' => "Tool class '{$class_name}' not found",
                    'tool_name' => $tool_name
                ];
            }
            
            $tool_handler = new $class_name();
            $tool_result = $tool_handler->handle_tool_call($complete_parameters, $tool_def);
            
            return $tool_result;
            
        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'AIStepTools: Tool execution exception', [
                'flow_step_id' => $flow_step_id,
                'tool_name' => $tool_name,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Tool execution exception: ' . $e->getMessage(),
                'tool_name' => $tool_name
            ];
        }
    }

    /**
     * Build tool parameters using engine-provided parameters and data content
     * 
     * @param array $tool_parameters AI-provided parameters
     * @param array $data Data packet array for content extraction
     * @param string|null $source_url Engine-provided source_url
     * @param string|null $image_url Engine-provided image_url
     * @param array $available_tools Available tools definition to check parameter requirements
     * @param string $tool_name Tool name to check parameter definition
     * @return array Complete tool parameters
     */
    private static function buildToolParameters(array $tool_parameters, array $data, $source_url = null, $image_url = null, array $available_tools = [], string $tool_name = ''): array {
        $complete_parameters = $tool_parameters;
        
        // Extract content from data (title and body for tool calls) - only if not already provided by AI
        if (!empty($data)) {
            $latest_entry = $data[0];
            $content_params = self::extractParametersFromData($latest_entry, []);
            
            // Add content parameters only if they exist in data AND not already provided by AI
            // AND the tool definition explicitly declares them as parameters
            if (isset($content_params['title']) && !isset($complete_parameters['title'])) {
                $tool_def = $available_tools[$tool_name] ?? [];
                $tool_parameters_def = $tool_def['parameters'] ?? [];
                if (isset($tool_parameters_def['title'])) {
                    $complete_parameters['title'] = $content_params['title'];
                }
            }
            if (isset($content_params['content']) && !isset($complete_parameters['content'])) {
                $complete_parameters['content'] = $content_params['content'];
            }
        }
        
        // Add engine-provided metadata parameters directly
        if ($source_url !== null) {
            $complete_parameters['source_url'] = $source_url;
        }
        if ($image_url !== null) {
            $complete_parameters['image_url'] = $image_url;
        }
        
        return $complete_parameters;
    }

    /**
     * Extract tool parameters from data entry for tool calling
     * 
     * Used by buildToolParameters to extract content (title, body) and metadata
     * while engine-provided parameters (source_url, image_url, file_path, mime_type) are handled separately
     * 
     * @param array $data_entry Latest data entry from data packet array
     * @param array $handler_config Handler configuration settings
     * @return array Tool parameters extracted from data entry
     */
    public static function extractParametersFromData(array $data_entry, array $handler_config): array {
        $parameters = [];
        
        // Extract content from data entry
        $content_data = $data_entry['content'] ?? [];
        if (isset($content_data['title'])) {
            $parameters['title'] = $content_data['title'];
        }
        if (isset($content_data['body'])) {
            $parameters['content'] = $content_data['body'];
        }
        
        // Extract metadata
        $metadata = $data_entry['metadata'] ?? [];
        if (isset($metadata['source_url'])) {
            $parameters['source_url'] = $metadata['source_url'];
        }
        
        // Extract image URL from attachments or metadata
        $attachments = $data_entry['attachments'] ?? [];
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['type']) && $attachment['type'] === 'image') {
                    $parameters['image_url'] = $attachment['url'] ?? null;
                    break;
                }
            }
        }
        
        // WordPress Media Fetch pattern
        if (isset($metadata['image_url'])) {
            $parameters['image_url'] = $metadata['image_url'];
        }
        
        return $parameters;
    }



}