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
        if (isset($config['step_key']) && !empty($config['step_key'])) {
            $field_name = 'ai_step_' . sanitize_key($config['step_key']) . '_temperature';
        }
        
        $html = '<div class="ai-field-group ai-temperature-slider">';
        $html .= '<label for="' . esc_attr($unique_id) . '_temperature">' . esc_html($config['label']) . ':</label>';
        $html .= '<div class="ai-slider-container">';
        $html .= '<input type="range" ';
        $html .= 'id="' . esc_attr($unique_id) . '_temperature" ';
        $html .= 'name="' . esc_attr($field_name) . '" ';
        $html .= 'min="' . esc_attr($config['min']) . '" ';
        $html .= 'max="' . esc_attr($config['max']) . '" ';
        $html .= 'step="' . esc_attr($config['step']) . '" ';
        $html .= 'value="' . esc_attr($temperature) . '" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="temperature_slider" ';
        $html .= 'class="ai-temperature-range" ';
        $html .= 'oninput="aiHttpUpdateTemperatureValue(\'' . esc_attr($unique_id) . '\', this.value)" />';
        
        $html .= '<div class="ai-slider-labels">';
        $html .= '<span class="ai-slider-label-left">' . esc_html($config['labels']['creative']) . '</span>';
        $html .= '<span class="ai-slider-value" id="' . esc_attr($unique_id) . '_temperature_value">' . esc_html($temperature) . '</span>';
        $html .= '<span class="ai-slider-label-right">' . esc_html($config['labels']['focused']) . '</span>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        if ($config['show_help']) {
            $html .= '<p class="ai-field-help">' . esc_html($config['help_text']) . '</p>';
        }
        
        $html .= '</div>';
        
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
                'default' => 2,
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
                'default' => 'Controls response randomness. Lower values = more focused, higher values = more creative.',
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
            'max' => 2,
            'step' => 0.1,
            'default_value' => 0.7,
            'labels' => [
                'creative' => 'Creative',
                'focused' => 'Focused'
            ],
            'show_help' => true,
            'help_text' => 'Controls response randomness. Lower values = more focused, higher values = more creative.'
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