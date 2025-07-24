<?php
/**
 * AI HTTP Client - Provider Selector Component
 * 
 * Single Responsibility: Render provider selection dropdown
 * Core component that handles AI provider selection
 *
 * @package AIHttpClient\Components\Core
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Core_ProviderSelector implements AI_HTTP_Component_Interface {
    
    /**
     * Render the provider selector component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $available_providers = self::get_available_providers($config['allowed_providers']);
        $selected_provider = $current_values['provider'] ?? $config['default_provider'];
        
        // Generate step-aware field name
        $field_name = 'ai_provider';
        if (isset($config['step_key']) && !empty($config['step_key'])) {
            $field_name = 'ai_step_' . sanitize_key($config['step_key']) . '_provider';
        }
        
        $html = '<tr class="form-field">';
        $html .= '<th scope="row">';
        $html .= '<label for="' . esc_attr($unique_id) . '_provider">' . esc_html($config['label']) . '</label>';
        $html .= '</th>';
        $html .= '<td>';
        $html .= '<select id="' . esc_attr($unique_id) . '_provider" ';
        $html .= 'name="' . esc_attr($field_name) . '" ';
        $html .= 'class="regular-text" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="provider_selector">';
        
        foreach ($available_providers as $provider_key => $provider_name) {
            $selected = selected($selected_provider, $provider_key, false);
            $html .= '<option value="' . esc_attr($provider_key) . '" ' . $selected . '>';
            $html .= esc_html($provider_name);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        
        if ($config['show_status']) {
            $html .= '<br><small class="description" id="' . esc_attr($unique_id) . '_provider_status">';
            $html .= self::get_provider_status($selected_provider, $current_values);
            $html .= '</small>';
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
                'default' => 'AI Provider',
                'description' => 'Label for the provider selector'
            ],
            'allowed_providers' => [
                'type' => 'array',
                'default' => [],
                'description' => 'Array of allowed provider keys. Empty = all providers'
            ],
            'default_provider' => [
                'type' => 'string',
                'default' => 'openai',
                'description' => 'Default selected provider'
            ],
            'show_status' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show provider configuration status'
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
            'label' => 'AI Provider',
            'allowed_providers' => [],
            'default_provider' => 'openai',
            'show_status' => true
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
            
            if ($expected_type === 'array' && !is_array($value)) {
                return false;
            }
            
            if ($expected_type === 'boolean' && !is_bool($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get available providers
     *
     * @param array $allowed_providers Array of allowed provider keys
     * @return array Available providers
     */
    private static function get_available_providers($allowed_providers) {
        $all_providers = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'grok' => 'Grok',
            'openrouter' => 'OpenRouter'
        ];
        
        if (empty($allowed_providers)) {
            return $all_providers;
        }
        
        $filtered = [];
        foreach ($allowed_providers as $provider) {
            if (isset($all_providers[$provider])) {
                $filtered[$provider] = $all_providers[$provider];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get provider status
     *
     * @param string $provider Provider key
     * @param array $current_values Current saved values
     * @return string Status HTML
     */
    private static function get_provider_status($provider, $current_values) {
        $api_key = $current_values['api_key'] ?? '';
        
        if (empty($api_key)) {
            return '<span style="color: #d63638;">⚠ Not configured</span>';
        }
        
        return '<span style="color: #00a32a;">✓ Configured</span>';
    }
}