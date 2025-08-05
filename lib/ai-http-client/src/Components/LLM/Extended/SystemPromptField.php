<?php
/**
 * AI HTTP Client - System Prompt Field Component
 * 
 * Single Responsibility: Render system prompt textarea
 * Extended component for system prompt/instructions input
 *
 * @package AIHttpClient\Components\Extended
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Extended_SystemPromptField implements AI_HTTP_Component_Interface {
    
    /**
     * Render the system prompt field component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $system_prompt = $current_values['system_prompt'] ?? $config['default_value'];
        
        // Generate step-aware field name
        $field_name = 'ai_system_prompt';
        if (isset($config['step_id']) && !empty($config['step_id'])) {
            $field_name = 'ai_step_' . sanitize_key($config['step_id']) . '_system_prompt';
        }
        
        $html = '<tr class="form-field">';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($unique_id) . '_system_prompt">' . esc_html($config['label']) . '</label>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<textarea id="' . esc_attr($unique_id) . '_system_prompt" ';
        $html .= 'name="' . esc_attr($field_name) . '" ';
        $html .= 'rows="' . esc_attr($config['rows']) . '" ';
        $html .= 'cols="' . esc_attr($config['cols']) . '" ';
        $html .= 'placeholder="' . esc_attr($config['placeholder']) . '" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="system_prompt_field" ';
        $html .= 'class="ai-system-prompt-textarea">';
        $html .= esc_textarea($system_prompt);
        $html .= '</textarea>';
        
        if ($config['show_help']) {
            $html .= '<br><small class="description">' . esc_html($config['help_text']) . '</small>';
        }
        
        $html .= '</td>';
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema() {
        return [
            'label' => [
                'type' => 'string',
                'default' => 'System Prompt',
                'description' => 'Label for the system prompt field'
            ],
            'placeholder' => [
                'type' => 'string',
                'default' => 'Enter system instructions for the AI...',
                'description' => 'Placeholder text for textarea'
            ],
            'rows' => [
                'type' => 'number',
                'default' => 6,
                'description' => 'Number of textarea rows'
            ],
            'cols' => [
                'type' => 'number',
                'default' => 50,
                'description' => 'Number of textarea columns'
            ],
            'default_value' => [
                'type' => 'string',
                'default' => '',
                'description' => 'Default system prompt value'
            ],
            'show_help' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show help text'
            ],
            'help_text' => [
                'type' => 'string',
                'default' => 'Instructions that define the AI\'s behavior and response style.',
                'description' => 'Help text displayed below field'
            ]
        ];
    }
    
    /**
     * Get component default values
     *
     * @return array Default values
     */
    public static function get_defaults() {
        return [
            'label' => 'System Prompt',
            'placeholder' => 'Enter system instructions for the AI...',
            'rows' => 6,
            'cols' => 50,
            'default_value' => '',
            'show_help' => true,
            'help_text' => 'Instructions that define the AI\'s behavior and response style.'
        ];
    }
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config) {
        $schema = self::get_config_schema();
        
        foreach ($config as $key => $value) {
            if (!isset($schema[$key])) {
                return false;
            }
            
            $expected_type = $schema[$key]['type'];
            
            if ($expected_type === 'string' && !is_string($value)) {
                return false;
            }
            
            if ($expected_type === 'number' && !is_numeric($value)) {
                return false;
            }
            
            if ($expected_type === 'boolean' && !is_bool($value)) {
                return false;
            }
        }
        
        // Additional validation for textarea dimensions
        if (isset($config['rows']) && $config['rows'] < 1) {
            return false;
        }
        
        if (isset($config['cols']) && $config['cols'] < 1) {
            return false;
        }
        
        return true;
    }
}