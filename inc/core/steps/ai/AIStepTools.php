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
     * Get all general tools that are available (configured tools appear in step selection)
     * 
     * @return array Available tools for step-level selection
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
     * Get step-level enabled tools for a specific pipeline step
     * 
     * @param string $pipeline_step_id Pipeline step UUID
     * @return array Tools enabled for this specific step
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
            
            $html .= '            <div class="dm-tool-option">' . "\n";
            $html .= '                <label>' . "\n";
            
            // Generate the checkbox HTML attributes
            $checked_attr = checked($should_be_checked, true, false);
            $disabled_attr = disabled($config_needed, true, false);
            
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
     * @param array $data Current data packet for parameter extraction
     * @param string $flow_step_id Flow step ID for logging
     * @return array Tool execution result
     */
    public static function executeTool(string $tool_name, array $tool_parameters, array $available_tools, array $data, string $flow_step_id): array {
        $tool_def = $available_tools[$tool_name] ?? null;
        if (!$tool_def) {
            return [
                'success' => false,
                'error' => "Tool '{$tool_name}' not found",
                'tool_name' => $tool_name
            ];
        }

        try {
            // Extract additional parameters from data packet
            $handler_config = $tool_def['handler_config'] ?? [];
            
            // Search through ALL data entries to find original_id (like UpdateStep does)
            $data_packet_parameters = [];
            foreach ($data as $data_entry) {
                $entry_parameters = self::extractParametersFromData($data_entry, $handler_config);
                if (isset($entry_parameters['original_id'])) {
                    $data_packet_parameters = $entry_parameters;
                    do_action('dm_log', 'debug', 'AIStepTools: Found original_id in data entry', [
                        'flow_step_id' => $flow_step_id,
                        'tool_name' => $tool_name,
                        'original_id' => $entry_parameters['original_id'],
                        'data_entry_type' => $data_entry['type'] ?? 'unknown',
                        'data_entry_source' => $data_entry['metadata']['source_type'] ?? 'unknown'
                    ]);
                    break; // Found the entry with original_id - use it
                }
            }
            
            // If no original_id found, use the latest data entry as fallback
            if (empty($data_packet_parameters) && !empty($data)) {
                $data_packet_parameters = self::extractParametersFromData($data[0], $handler_config);
                do_action('dm_log', 'debug', 'AIStepTools: No original_id found, using latest data entry', [
                    'flow_step_id' => $flow_step_id,
                    'tool_name' => $tool_name,
                    'latest_entry_type' => $data[0]['type'] ?? 'unknown',
                    'latest_entry_source' => $data[0]['metadata']['source_type'] ?? 'unknown'
                ]);
            }
            
            // AI parameters take precedence over data packet parameters
            $complete_parameters = array_merge($data_packet_parameters, $tool_parameters);
            
            // Debug logging for complete parameters sent to tool
            do_action('dm_log', 'debug', 'AI Step Tools: Complete parameters for tool execution', [
                'flow_step_id' => $flow_step_id,
                'tool_name' => $tool_name,
                'data_packet_source_url' => $data_packet_parameters['source_url'] ?? 'not_set',
                'tool_param_source_url' => $tool_parameters['source_url'] ?? 'not_set', 
                'final_source_url' => $complete_parameters['source_url'] ?? 'not_set',
                'data_packet_image_url' => $data_packet_parameters['image_url'] ?? 'not_set',
                'tool_param_image_url' => $tool_parameters['image_url'] ?? 'not_set',
                'final_image_url' => $complete_parameters['image_url'] ?? 'not_set',
                'all_parameter_keys' => array_keys($complete_parameters)
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
     * Extract tool parameters from data entry for tool calling
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
        
        // Extract metadata - CRITICAL: Include original_id for updates
        $metadata = $data_entry['metadata'] ?? [];
        if (isset($metadata['original_id'])) {
            $parameters['original_id'] = $metadata['original_id'];
        }
        if (isset($metadata['source_url'])) {
            $parameters['source_url'] = $metadata['source_url'];
            
            do_action('dm_log', 'debug', 'AI Step Tools: Source URL extracted from metadata', [
                'metadata_source_url' => $metadata['source_url'],
                'extracted_source_url' => $parameters['source_url'],
                'data_entry_type' => $data_entry['type'] ?? 'unknown'
            ]);
        }
        
        // Extract attachments/media if available
        $attachments = $data_entry['attachments'] ?? [];
        if (!empty($attachments)) {
            // Look for image attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['type']) && $attachment['type'] === 'image') {
                    $parameters['image_url'] = $attachment['url'] ?? null;
                    break;
                }
            }
        }
        
        // Extract image URL from metadata (WordPress Media Fetch pattern)
        if (isset($metadata['image_source_url'])) {
            $parameters['image_url'] = $metadata['image_source_url'];
            
            do_action('dm_log', 'debug', 'AI Step Tools: Image URL extracted from metadata', [
                'metadata_image_source_url' => $metadata['image_source_url'],
                'extracted_image_url' => $parameters['image_url'],
                'url_is_valid' => filter_var($parameters['image_url'], FILTER_VALIDATE_URL)
            ]);
        } else {
            do_action('dm_log', 'debug', 'AI Step Tools: No image_source_url in metadata', [
                'metadata_keys' => array_keys($metadata)
            ]);
        }
        
        return $parameters;
    }



}