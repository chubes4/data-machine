<?php
/**
 * AI HTTP Client - Temperature Slider Component
 * 
 * Single Responsibility: Render temperature control slider
 * Extended component for controlling AI response randomness
 *
 * @package AIHttpClient\Components\Extended
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Extended_TemperatureSlider implements AI_HTTP_Component_Interface {
    
    /**
     * Render the temperature slider component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $temperature = $current_values['temperature'] ?? $config['default_value'];
        
        // Generate step-aware field name
        $field_name = 'ai_temperature';
        if (isset($config['step_id']) && !empty($config['step_id'])) {
            $field_name = 'ai_step_' . sanitize_key($config['step_id']) . '_temperature';
        }
        
        $html = '<tr class="form-field">';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($unique_id) . '_temperature">' . esc_html($config['label']) . '</label>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<input type="text" ';
        $html .= 'id="' . esc_attr($unique_id) . '_temperature" ';
        $html .= 'name="' . esc_attr($field_name) . '" ';
        $html .= 'value="' . esc_attr($temperature) . '" ';
        $html .= 'placeholder="0.7" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="temperature_input" ';
        $html .= 'class="ai-temperature-input" ';
        $html .= 'style="width: 80px;" />';
        
        if ($config['show_help']) {
            $html .= '<br><small class="description">' . esc_html($config['help_text']) . '</small>';
        }
        
        $html .= '</div>';
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
                'default' => 'Temperature',
                'description' => 'Label for the temperature slider'
            ],
            'min' => [
                'type' => 'number',
                'default' => 0,
                'description' => 'Minimum temperature value'
            ],
            'max' => [
                'type' => 'number',
                'default' => 1,
                'description' => 'Maximum temperature value'
            ],
            'step' => [
                'type' => 'number',
                'default' => 0.1,
                'description' => 'Step size for temperature slider'
            ],
            'default_value' => [
                'type' => 'number',
                'default' => 0.7,
                'description' => 'Default temperature value'
            ],
            'labels' => [
                'type' => 'array',
                'default' => [
                    'creative' => 'Creative',
                    'focused' => 'Focused'
                ],
                'description' => 'Labels for slider extremes'
            ],
            'show_help' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show help text'
            ],
            'help_text' => [
                'type' => 'string',
                'default' => 'Enter a value between 0 and 1. Lower values = more focused, higher values = more creative.',
                'description' => 'Help text displayed below slider'
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
            'label' => 'Temperature',
            'min' => 0,
            'max' => 1,
            'step' => 0.1,
            'default_value' => 0.7,
            'labels' => [
                'creative' => 'Creative',
                'focused' => 'Focused'
            ],
            'show_help' => true,
            'help_text' => 'Enter a value between 0 and 1. Lower values = more focused, higher values = more creative.'
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
            
            if ($expected_type === 'array' && !is_array($value)) {
                return false;
            }
        }
        
        // Additional validation for temperature range
        if (isset($config['min']) && isset($config['max']) && $config['min'] >= $config['max']) {
            return false;
        }
        
        if (isset($config['default_value']) && isset($config['min']) && isset($config['max'])) {
            if ($config['default_value'] < $config['min'] || $config['default_value'] > $config['max']) {
                return false;
            }
        }
        
        return true;
    }
}